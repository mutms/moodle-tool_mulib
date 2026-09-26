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
namespace tool_mulib\muform\element;

use core\exception\coding_exception;
use core\output\core_renderer;
use tool_mulib\muform\element;
use tool_mulib\muform\util\calendar;

/**
 * Duration element, the value is a number of seconds, always a multiple of 60.
 *
 * Users enter any combination of weeks, days, hours and minutes into four
 * number inputs posted as name[w], name[d], name[h] and name[i], the server adds them up. Seconds are ignored, current data
 * is rounded to whole minutes. Empty inputs mean zero, the value is never null.
 * The value is shown normalised, 90 minutes come back as 1 hour 30 minutes.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class duration extends element {
    /** @var array seconds per supported unit in display order, keys are the calendar util unit keys */
    private const array UNITS = ['w' => WEEKSECS, 'd' => DAYSECS, 'h' => HOURSECS, 'i' => MINSECS, 's' => 1];

    /** @var array seconds per configured unit in display order */
    private array $units = [];

    /** @var array|null posted texts that could not be parsed, shown again so the user can fix them */
    private ?array $invalidparts = null;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param array $units unit keys always shown, a subset of w, d, h, i, s in that order
     */
    public function __construct(string $name, string $label, array $units = ['d', 'h', 'i']) {
        parent::__construct($name);
        $this->label = $label;

        $expected = array_intersect_key(self::UNITS, array_flip($units));
        if (!$units || array_values($units) !== array_keys($expected)) {
            throw new coding_exception('Invalid duration units, expected a subset of w, d, h, i, s in that order');
        }
        $this->units = $expected;
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        $value = $this->value;

        if ($value === null || $value === '') {
            $this->value = 0;
            return;
        }

        if (is_array($value)) {
            // Posted unit inputs.
            $total = 0;
            $parts = [];
            foreach (self::UNITS as $unit => $seconds) {
                $part = $value[$unit] ?? '';
                if (is_array($part) || is_bool($part)) {
                    $part = '';
                }
                $part = trim((string)$part);
                $parts[$unit] = $part;
                if ($part === '') {
                    continue;
                }
                if (!preg_match('/^\d+$/D', $part)) {
                    $total = null;
                    continue;
                }
                if ($total !== null) {
                    $total += (int)$part * $seconds;
                }
            }
            if ($total === null) {
                $this->value = 0;
                // Show the configured units and whatever else was posted.
                $this->invalidparts = array_filter(
                    $parts,
                    fn($text, $unit) => $text !== '' || isset($this->units[$unit]),
                    ARRAY_FILTER_USE_BOTH
                );
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $this->value = $total;
            return;
        }

        if (is_bool($value) || !is_numeric($value)) {
            $this->value = 0;
            $this->errors[] = $this->get_invalid_hint();
            return;
        }

        $this->value = (int)round((float)$value);
        if ($this->value < 0) {
            $this->value = 0;
            $this->errors[] = $this->get_invalid_hint();
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return $this->value > 0;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['nolabelfor'] = true;

        $parts = $this->invalidparts ?? $this->split($this->value);
        $context['units'] = [];
        foreach ($parts as $unit => $unused) {
            $context['units'][] = [
                'unit' => $unit,
                'id' => $context['id'] . '_' . $unit,
                'label' => clean_string(calendar::get_unit_label($unit)),
                'value' => (string)$parts[$unit],
            ];
        }

        $text = calendar::to_text($parts);
        if ($text === '') {
            $text = calendar::to_text([array_key_last($parts) => 0], true);
        }
        $context['value'] = $text;

        return $context;
    }

    /**
     * Split seconds into unit numbers, zero units are empty strings.
     *
     * The configured units are always present, smaller units are added
     * when the seconds cannot be expressed without them.
     *
     * @param int $seconds
     * @return array texts indexed by unit
     */
    private function split(int $seconds): array {
        $units = $this->units;
        $smallest = min($units);
        foreach (self::UNITS as $unit => $unitseconds) {
            if ($unitseconds < $smallest && $seconds % $smallest !== 0) {
                $units[$unit] = $unitseconds;
                $smallest = $unitseconds;
            }
        }
        $parts = [];
        foreach ($units as $unit => $unitseconds) {
            $number = intdiv($seconds, $unitseconds);
            $seconds -= $number * $unitseconds;
            $parts[$unit] = $number ? (string)$number : '';
        }
        return $parts;
    }
}
