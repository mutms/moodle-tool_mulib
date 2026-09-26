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
 * Date interval element, the value is an ISO 8601 duration string such as P1Y2M or P1W2DT3H, or null.
 *
 * For periods in months and years that are added to dates with PHP DateInterval,
 * where a number of seconds does not work. Users enter any combination of units
 * into number inputs posted as name[unit] with the calendar unit keys (y, m, w, d,
 * h, i, s). The configured units (years, months, weeks and days by default) are
 * always shown, any other unit with a non-zero part is added automatically, so
 * nothing is ever lost. The value is canonical: zero parts are omitted and an all
 * zero interval is null. Current data is validated with a regex and canonicalised
 * (P0Y2M becomes P2M).
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dateinterval extends element {
    /** @var string[] configured unit keys in display order */
    private array $units = [];

    /** @var array|null posted texts that could not be parsed, shown again so the user can fix them */
    private ?array $invalidparts = null;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param array $units unit keys always shown, a subset of y, m, w, d, h, i, s in that order
     */
    public function __construct(string $name, string $label, array $units = ['y', 'm', 'w', 'd']) {
        parent::__construct($name);
        $this->label = $label;

        $expected = array_values(array_intersect(calendar::UNITS, $units));
        if (!$units || array_values($units) !== $expected) {
            throw new coding_exception('Invalid dateinterval units, expected a subset of y, m, w, d, h, i, s in that order');
        }
        $this->units = $expected;
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        $value = $this->value;

        if ($value === null || $value === '') {
            $this->value = null;
            return;
        }

        if (is_array($value)) {
            // Posted unit inputs.
            $parts = [];
            $texts = [];
            $valid = true;
            foreach (calendar::UNITS as $unit) {
                $part = $value[$unit] ?? '';
                if (is_array($part) || is_bool($part)) {
                    $part = '';
                }
                $part = trim((string)$part);
                $texts[$unit] = $part;
                if ($part === '') {
                    $parts[$unit] = 0;
                    continue;
                }
                if (!preg_match('/^\d+$/D', $part)) {
                    $valid = false;
                    continue;
                }
                $parts[$unit] = (int)$part;
            }
            if (!$valid) {
                $this->value = null;
                $this->invalidparts = $texts;
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $this->value = calendar::build_interval($parts);
            return;
        }

        $parts = is_string($value) ? calendar::parse_interval($value) : null;
        if ($parts === null) {
            $this->value = null;
            $this->errors[] = $this->get_invalid_hint();
            return;
        }

        $this->value = calendar::build_interval($parts);
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return $this->value !== null;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['nolabelfor'] = true;

        $parts = $this->value === null ? [] : calendar::parse_interval($this->value);
        $context['units'] = [];
        foreach (calendar::UNITS as $unit) {
            $number = $parts[$unit] ?? 0;
            $text = $this->invalidparts[$unit] ?? ($number ? (string)$number : '');
            if ($text === '' && !in_array($unit, $this->units, true)) {
                continue;
            }
            $context['units'][] = [
                'unit' => $unit,
                'id' => $context['id'] . '_' . $unit,
                'label' => clean_string(calendar::get_unit_label($unit)),
                'value' => $text,
            ];
        }

        $context['value'] = $this->value === null ? get_string('muform_notset', 'tool_mulib') : calendar::to_text($parts);

        return $context;
    }
}
