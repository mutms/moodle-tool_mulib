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
// phpcs:disable moodle.Files.LineLength.TooLong

namespace tool_mulib\external\form_autocomplete;

use stdClass;

/**
 * Base class for user auto-completion fields.
 *
 * @package    tool_mulib
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class user extends base {
    /** @var string|null user table */
    protected const ITEM_TABLE = 'user';
    /** @var string|null not used, there is custom format_label() method */
    protected const ITEM_FIELD = null;

    #[\Override]
    public static function format_label(stdClass $item, \context $context): string {
        global $OUTPUT;

        if ($item->deleted) {
            return get_string('deleted');
        }

        $fields = \core_user\fields::for_name()->with_identity($context, false);

        $data = (object)[
            'id' => $item->id,
            'fullname' => fullname($item, has_capability('moodle/site:viewfullnames', $context)),
            'extrafields' => [],
        ];

        foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
            $data->extrafields[] = (object)[
                'name' => $extrafield,
                'value' => s($item->$extrafield),
            ];
        }

        return clean_text($OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $data));
    }
}
