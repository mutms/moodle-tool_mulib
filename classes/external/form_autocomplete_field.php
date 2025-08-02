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

namespace tool_mulib\external;

use core_external\external_function_parameters;
use core_external\external_description;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use stdClass;

/**
 * Base class for simplified form ajax autocomplete fields.
 *
 * @package     tool_mulib
 * @copyright   2023 Open LMS (https://www.openlms.net/)
 * @copyright   2025 Petr Skoda
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class form_autocomplete_field extends \core_external\external_api {
    /**
     * Describes the external function arguments.
     *
     * @return external_function_parameters
     */
    abstract public static function execute_parameters(): external_function_parameters;

    /**
     * Describes the external function result value.
     *
     * @return external_description
     */
    final public static function execute_returns(): external_description {
        return new external_single_structure([
            'notice' => new external_value(
                PARAM_RAW,
                'Notice message when data cannot be returned, NULl means success.',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
            'list' => new external_multiple_structure(
                new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'Value of item'),
                    'label' => new external_value(PARAM_RAW, 'Label of item'),
                ], 'List of options, empty if notice set')
            ),
        ]);
    }

    /**
     * Return function that return label for given value.
     *
     * @param array $arguments
     * @return callable
     */
    public static function get_label_callback(array $arguments): callable {
        return function ($value) use ($arguments): string {
            return "get_label_callback() not implemented: $value";
        };
    }

    /**
     * True means returned field data is array, false means value is scalar.
     *
     * @return bool
     */
    public static function is_multi_select_field(): bool {
        return false;
    }

    /**
     * Return name of WS function which is defined in db/services.php file.
     *
     * @return string
     */
    public static function get_web_service_name(): string {
        $parts = explode('\\', static::class);
        $component = reset($parts);
        $name = array_pop($parts);
        return $component . '_' . $name;
    }

    /**
     * Add form element.
     *
     * @param \MoodleQuickForm $mform
     * @param array $arguments WS call parameters
     * @param string $name field name
     * @param string $label field label
     * @param array $attributes autocomplete field attributes
     * @return \html_quickform_element
     */
    public static function add_form_element(\MoodleQuickForm $mform, array $arguments, string $name, string $label, array $attributes = []): \html_quickform_element {
        $attributes['tags'] = false;
        $attributes['multiple'] = static::is_multi_select_field();
        $attributes['ajax'] = 'tool_mulib/form_autocomplete_ajax';
        $attributes['valuehtmlcallback'] = static::get_label_callback($arguments);
        $attributes['data-ws-method'] = static::get_web_service_name();
        $attributes['data-ws-args'] = json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $mform->addElement('autocomplete', $name, $label, [], $attributes);
    }

    /**
     * Returns formatted user list to be return from WS.
     *
     * @param \moodle_recordset $rs
     * @param array $extrafields
     * @return array
     */
    public static function prepare_user_list(\moodle_recordset $rs, array $extrafields): array {
        global $CFG, $OUTPUT;

        $count = 0;
        $list = [];
        $notice = null;

        foreach ($rs as $record) {
            $count++;
            if ($count > $CFG->maxusersperpage) {
                $notice = get_string('toomanyuserstoshow', 'core', $CFG->maxusersperpage);
                break;
            }

            $user = (object) [
                'id' => $record->id,
                'fullname' => fullname($record, true),
                'extrafields' => [],
            ];
            foreach ($extrafields as $extrafield) {
                // Sanitize the extra fields to prevent potential XSS exploit.
                $user->extrafields[] = (object) [
                    'name' => $extrafield,
                    'value' => s($record->$extrafield),
                ];
            }
            $list[] = [
                'value' => $record->id,
                'label' => clean_text($OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user)),
            ];
        }
        $rs->close();

        return [
            'notice' => $notice,
            'list' => $list,
        ];
    }

    /**
     * Returns user label.
     *
     * @param false|stdClass $record user record
     * @param \context $context
     * @param string|null $error extra error message
     * @return string
     */
    public static function prepare_user_label($record, \context $context, ?string $error = null): string {
        global $OUTPUT;

        // For now just append the error, do not apply any classes here.
        $error = (string)$error;

        if (empty($record->id)) {
            return $error;
        }

        if ($record->deleted) {
            return get_string('deleted');
        }

        $fields = \core_user\fields::for_name()->with_identity($context, false);
        $record = \core_user::get_user($record->id, 'id' . $fields->get_sql()->selects, MUST_EXIST);

        $user = (object) [
            'id' => $record->id,
            'fullname' => fullname($record, true),
            'extrafields' => [],
        ];

        foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
            $user->extrafields[] = (object) [
                'name' => $extrafield,
                'value' => s($record->$extrafield),
            ];
        }

        return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user) . $error;
    }

    /**
     * Returns tenant query data.
     *
     * @param string $search
     * @param string $tablealias
     * @return array
     */
    public static function get_tenant_search_query(string $search, string $tablealias = ''): array {
        global $DB;

        if ($tablealias !== '' && !str_ends_with($tablealias, '.')) {
            $tablealias .= '.';
        }

        $conditions = [];
        $params = [];

        if (trim($search) !== '') {
            $searchparam = '%' . $DB->sql_like_escape($search) . '%';
            $fields = ['name', 'idnumber'];
            $cnt = 0;
            foreach ($fields as $field) {
                $conditions[] = $DB->sql_like($tablealias . $field, ':tensearch' . $cnt, false);
                $params['tensearch' . $cnt] = $searchparam;
                $cnt++;
            }
        }

        if ($conditions) {
            $sql = '(' . implode(' OR ', $conditions) . ') ';
            return [$sql, $params];
        } else {
            return ['1=1 ', $params];
        }
    }
}
