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

use core\output\core_renderer;
use tool_mulib\muform\element;

/**
 * Yes or No choice shown as two inline radios, value is always 1 or 0.
 *
 * Frozen elements show the answer as plain text.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class yesno extends element {
    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     */
    public function __construct(string $name, string $label) {
        parent::__construct($name);
        $this->label = $label;
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        $value = $this->value;
        if ($value === null || $value === '' || $value === false || $value === 0 || $value === '0') {
            $this->value = 0;
        } else if ($value === true || $value === 1 || $value === '1') {
            $this->value = 1;
        } else {
            $this->value = 0;
            $this->errors[] = $this->get_invalid_hint();
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        // There is always an answer.
        return true;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['nolabelfor'] = true;
        $context['value'] = $this->value;
        $context['is_yes'] = ($this->value === 1);
        $context['yeslabel'] = clean_string(get_string('yes'));
        $context['nolabel'] = clean_string(get_string('no'));
        $context['valuelabel'] = ($this->value === 1) ? $context['yeslabel'] : $context['nolabel'];
        return $context;
    }
}
