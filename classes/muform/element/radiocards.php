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
use tool_mulib\muform\util\cards;
use tool_mulib\muform\util\options;

/**
 * Radio buttons rendered as cards with title and optional description, value is the key of selected option.
 *
 * Use it instead of help buttons when options need an explanation, for example their consequences.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class radiocards extends element {
    /** @var options */
    private options $options;
    /** @var array plain text descriptions indexed by option keys */
    private array $descriptions;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param array|options $options option titles indexed by value keys or options instance with groups
     * @param array $descriptions plain text option descriptions indexed by value keys, optional
     */
    public function __construct(string $name, string $label, array|options $options, array $descriptions = []) {
        parent::__construct($name);
        $this->label = $label;
        $this->options = is_array($options) ? new options($options) : $options;
        $this->descriptions = cards::validate_descriptions($descriptions, $this->options);
    }

    /**
     * Returns options.
     *
     * @return options
     */
    public function get_options(): options {
        return $this->options;
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        if ($this->value === null || $this->value === '' || is_array($this->value) || is_bool($this->value)) {
            $this->value = null;
            return;
        }
        $this->value = (string)$this->value;
        if (!$this->options->has_key($this->value)) {
            $this->value = null;
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['nolabelfor'] = true;
        $selected = ($this->value === null) ? [] : [$this->value];
        $groups = $this->options->get_template_data($context['id'], $selected);
        $context['groups'] = cards::add_descriptions($groups, $this->descriptions);
        return $context;
    }
}
