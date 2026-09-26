<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon

namespace tool_mulib\local;

use core\context;

/**
 * Custom field helpers for MuTMS entities.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class customfield_util {
    /**
     * Update context of stored custom field data after instances were moved to a different context.
     *
     * Textarea files are moved to the new context too, the stored texts use @@PLUGINFILE@@ links,
     * so they do not change.
     *
     * @param string $component handler component
     * @param string $area handler area
     * @param int $itemid handler itemid
     * @param sql $instanceids query returning instance ids
     * @param context $context new instance context
     */
    public static function change_instances_context(
        string $component,
        string $area,
        int $itemid,
        sql $instanceids,
        context $context
    ): void {
        global $DB;

        $sql = new sql(
            "SELECT d.id, d.contextid
               FROM {customfield_data} d
              WHERE d.component = :component AND d.area = :area AND d.itemid = :itemid
                    AND d.contextid <> :contextid AND d.instanceid IN (/* instanceids */)",
            ['component' => $component, 'area' => $area, 'itemid' => $itemid, 'contextid' => $context->id]
        );
        $sql = $sql->replace_comment('instanceids', $instanceids);

        $fs = get_file_storage();
        $rs = $DB->get_recordset_sql($sql->sql, $sql->params);
        foreach ($rs as $data) {
            $files = $fs->get_area_files($data->contextid, 'customfield_textarea', 'value', $data->id, 'id', true);
            foreach ($files as $file) {
                $fs->create_file_from_storedfile(['contextid' => $context->id], $file);
                $file->delete();
            }
            $DB->set_field('customfield_data', 'contextid', $context->id, ['id' => $data->id]);
        }
        $rs->close();
    }
}
