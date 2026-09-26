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

namespace tool_mulib\muform\customfield;

use stdClass;
use tool_mulib\muform\element;

/**
 * Date custom field, timestamp stored in intvalue, 0 means no date.
 *
 * Fields without time show the date only and store midnight in the user timezone.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class date extends base {
    /** @var string display format of fields without time */
    private const string DATEFORMAT = 'Y-m-d';

    /**
     * Does the field include time?
     *
     * @return bool
     */
    private function has_time(): bool {
        return !empty($this->config['includetime']);
    }

    #[\Override]
    public function create_element(string $elname, string $label, ?stdClass $data, \context $context): element {
        $element = new element\datetime($elname, $label, [], $this->has_time() ? null : self::DATEFORMAT);
        $element->set_required($this->is_required());
        return $element;
    }

    #[\Override]
    public function get_default_value(): mixed {
        return null;
    }

    #[\Override]
    public function get_stored_value(stdClass $data): mixed {
        return empty($data->intvalue) ? null : (int)$data->intvalue;
    }

    #[\Override]
    public function get_datafield(): string {
        return 'intvalue';
    }

    #[\Override]
    public function get_data_columns(element $element): array {
        $value = $element->get_value();
        $value = empty($value) ? 0 : $this->normalise((int)$value);
        return ['intvalue' => $value, 'value' => (string)$value];
    }

    /**
     * Strip time from dates of fields without time.
     *
     * @param int $timestamp
     * @return int
     */
    private function normalise(int $timestamp): int {
        if ($this->has_time()) {
            return $timestamp;
        }
        $date = new \DateTime('@' . $timestamp);
        $date->setTimezone(\core_date::get_user_timezone_object());
        $date->setTime(0, 0);
        return $date->getTimestamp();
    }

    #[\Override]
    public function validate(element $element, array &$allerrors): void {
        $value = $element->get_value();
        if (!$value) {
            return;
        }
        // Compare dates as texts trimmed to minutes or days, the same way as the core field.
        $machineformat = $this->has_time() ? '%Y-%m-%d %H:%M' : '%Y-%m-%d';
        $humanformat = $this->has_time() ? get_string('strftimedatetimeshort') : get_string('strftimedatefullshort');
        $value = userdate($value, $machineformat, 99, false, false);
        $mindate = (int)($this->config['mindate'] ?? 0);
        $maxdate = (int)($this->config['maxdate'] ?? 0);
        if ($mindate && userdate($mindate, $machineformat, 99, false, false) > $value) {
            $allerrors[$element->get_name()][] = get_string('errormindate', 'customfield_date', userdate($mindate, $humanformat));
        }
        if ($maxdate && userdate($maxdate, $machineformat, 99, false, false) < $value) {
            $allerrors[$element->get_name()][] = get_string('errormaxdate', 'customfield_date', userdate($maxdate, $humanformat));
        }
    }
}
