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

namespace tool_mulib\local\generator;

use stdClass;

/**
 * Feedback activity generator.
 *
 * Accepts an optional `items` array — each entry produces one feedback_item
 * row via the canonical feedback_get_item_class($typ)->save_item() path,
 * inserted sequentially after the activity instance exists.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_feedback_generator extends mod_base {
    /**
     * Module name.
     *
     * @return string always 'feedback'
     */
    public function get_modulename(): string {
        return 'feedback';
    }

    /**
     * Create a feedback activity and its items.
     *
     * @param array{
     *     course: int|stdClass,
     *     name?: string,
     *     section?: int,
     *     intro?: string,
     *     introformat?: int,
     *     introfiles?: array<string, \stored_file|string|array{content: string}>,
     *     visible?: bool,
     *     anonymous?: int,
     *     multiple_submit?: int,
     *     publish_stats?: int,
     *     timeopen?: int,
     *     timeclose?: int,
     *     items?: array<int, array{
     *         typ: string,
     *         name?: string,
     *         label?: string,
     *         presentation?: string,
     *         required?: int,
     *         options?: string,
     *     }>,
     * } $record
     * @return stdClass feedback record from DB with extra ->cmid field
     */
    public function create_activity(array $record): stdClass {
        [$record, $course] = $this->prepare_record($record);

        $moduleinfo = $this->build_moduleinfo(
            $course,
            (int)($record['section'] ?? 0),
            $record['name'],
            $record['intro'] ?? '',
            $record['introformat'] ?? null,
            !isset($record['visible']) || $record['visible'],
            $record['introfiles'] ?? null,
        );

        global $CFG;
        require_once($CFG->dirroot . '/mod/feedback/lib.php');

        $moduleinfo->anonymous = $record['anonymous'] ?? FEEDBACK_ANONYMOUS_NO;
        $moduleinfo->multiple_submit = $record['multiple_submit'] ?? 0;
        $moduleinfo->email_notification = 0;
        $moduleinfo->autonumbering = 1;
        $moduleinfo->site_after_submit = '';
        $moduleinfo->page_after_submit = '';
        $moduleinfo->page_after_submitformat = FORMAT_HTML;
        $moduleinfo->page_after_submit_editor = [
            'itemid' => 0,
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $moduleinfo->publish_stats = $record['publish_stats'] ?? 0;
        $moduleinfo->timeopen = $record['timeopen'] ?? 0;
        $moduleinfo->timeclose = $record['timeclose'] ?? 0;
        $moduleinfo->completionsubmit = 0;

        $instance = $this->add($moduleinfo, $course);

        if (!empty($record['items'])) {
            $position = 0;
            foreach ($record['items'] as $itemspec) {
                $position++;
                self::insert_item($instance, $itemspec, $position);
            }
        }

        return $instance;
    }

    /**
     * Insert one feedback_item via the canonical save_item() path.
     *
     * @param stdClass $feedback feedback instance record
     * @param array $spec item spec (typ, name, label, presentation, required, options)
     * @param int $position 1-based position within the feedback
     */
    private static function insert_item(stdClass $feedback, array $spec, int $position): void {
        $typ = $spec['typ'];
        $itemobj = feedback_get_item_class($typ);
        $item = (object)[
            'id' => 0,
            'feedback' => $feedback->id,
            'template' => 0,
            'name' => $spec['name'] ?? '',
            'label' => $spec['label'] ?? '',
            'typ' => $typ,
            'hasvalue' => in_array($typ, ['pagebreak', 'label'], true) ? 0 : 1,
            'position' => $position,
            'required' => $spec['required'] ?? 0,
            'dependitem' => 0,
            'dependvalue' => '',
            'options' => $spec['options'] ?? '',
            'presentation' => $spec['presentation'] ?? '',
        ];
        // feedback_item_multichoice::save_item() reads these properties
        // off the item object directly and falls back to PHP warnings if
        // they're unset. Provide sane defaults: include empty answers,
        // don't suppress the "please select" prompt.
        if ($typ === 'multichoice' || $typ === 'multichoicerated') {
            $item->ignoreempty = $spec['ignoreempty'] ?? 0;
            $item->hidenoselect = $spec['hidenoselect'] ?? 0;
        }
        // feedback_item_label::save_item() routes the visible text through
        // file_postupdate_standard_editor, which reads `presentation_editor`
        // not `presentation`. Without this the second update of the item
        // ends up writing NULL into the NOT-NULL presentation column.
        if ($typ === 'label') {
            $item->presentation_editor = [
                'text' => (string)($spec['presentation'] ?? ''),
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ];
        }
        $itemobj->set_data($item);
        $itemobj->save_item();
    }
}
