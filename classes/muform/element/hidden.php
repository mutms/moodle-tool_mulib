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

use core\param;
use tool_mulib\muform\element;

/**
 * Hidden element.
 *
 * Without a type the element is frozen: submitted data is ignored and the value
 * comes from current data or explicit default, which is the usual case for ids. With a type the
 * submitted value is user input, it is cleaned with the type and any change
 * caused by cleaning is reported as invalid value.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hidden extends element {
    /** @var param|null type of submitted value, null means frozen element */
    private ?param $type;

    /**
     * Constructor.
     *
     * @param string $name
     * @param param|null $type type of submitted value, null means frozen element using current data or explicit default
     */
    public function __construct(string $name, ?param $type = null) {
        parent::__construct($name);
        $this->type = $type;
        $this->set_frozen($type === null);
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        if ($this->value === null || is_array($this->value)) {
            $this->value = null;
            return;
        }
        if (is_bool($this->value)) {
            $this->value = (int)$this->value;
        }
        $this->value = (string)$this->value;

        if ($this->type === null) {
            return;
        }
        $cleaned = (string)$this->type->clean($this->value);
        if ($cleaned !== $this->value) {
            $this->errors[] = $this->get_invalid_hint();
        }
        $this->value = $cleaned;
    }

    #[\Override]
    public function get_value(): mixed {
        $value = parent::get_value();
        // Integers are returned as int when the string round trips without any loss.
        if ($this->type === param::INT && is_string($value) && (string)(int)$value === $value) {
            return (int)$value;
        }
        return $value;
    }
}
