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

namespace tool_mulib\local\extdb\form;

use tool_mulib\local\extdb\query_manager;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;
use tool_mulib\muform\util\options;

/**
 * Query type selection, the first step of query creation.
 *
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class query_create_select extends form {
    #[\Override]
    protected function definition(): void {
        $type = (new select('type', get_string('extdb_query_type', 'tool_mulib'), self::get_type_options()))
            ->set_required(true);
        $this->add($type);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('continue')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    /**
     * Query type options grouped by plugin, keys are "component-type".
     *
     * @return options
     */
    public static function get_type_options(): options {
        $qman = \core\di::get(query_manager::class);
        $result = new options(['' => get_string('choosedots')]);
        foreach ($qman->get_classes() as $component => $types) {
            $group = [];
            foreach ($types as $type => $classname) {
                $group[$component . '-' . $type] = $classname::get_name();
            }
            $result->add_optgroup(get_string('pluginname', $component), $group);
        }
        return $result;
    }

    /**
     * Decode the selected type option.
     *
     * @param string $component
     * @param string $type either the type or the "component-type" option key
     * @return string[] component and type, both empty when unknown
     */
    public static function decode_type_option(string $component, string $type): array {
        if (!$type) {
            return ['component' => '', 'type' => ''];
        }
        if (str_contains($type, '-')) {
            [$component, $type] = explode('-', $type, 2);
        }
        $qman = \core\di::get(query_manager::class);
        $classes = $qman->get_classes();
        if (isset($classes[$component][$type])) {
            return ['component' => $component, 'type' => $type];
        }
        return ['component' => '', 'type' => ''];
    }
}
