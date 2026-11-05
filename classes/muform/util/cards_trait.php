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

namespace tool_mulib\muform\util;

use core\exception\coding_exception;

/**
 * Options, descriptions and layout of radiocards and checkboxcards elements.
 *
 * Inspired by Bootstrap 6 field group cards, own Bootstrap 5 compatible implementation,
 * MuTMS cannot wait for the Bootstrap 6 migration of Moodle.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait cards_trait {
    /** @var options */
    private options $options;
    /** @var array plain text descriptions indexed by option keys */
    private array $descriptions = [];
    /** @var int maximum number of card columns when the element is wide enough */
    private int $maxcolumns = 2;

    /**
     * Returns options.
     *
     * @return options
     */
    public function get_options(): options {
        return $this->options;
    }

    /**
     * Set maximum number of card columns.
     *
     * Cards are shown in two columns when the element is at least 48rem wide,
     * use 1 when long descriptions do not read well side by side.
     *
     * @param int $columns 1 or 2
     * @return static
     */
    public function set_max_columns(int $columns): static {
        if ($columns !== 1 && $columns !== 2) {
            throw new coding_exception('Card columns must be 1 or 2');
        }
        $this->maxcolumns = $columns;
        return $this;
    }

    /**
     * Set options and validate their descriptions, called from the constructor.
     *
     * @param array|options $options option titles indexed by value keys or options instance with groups
     * @param array $descriptions plain text descriptions indexed by option keys
     */
    private function init_cards(array|options $options, array $descriptions): void {
        $this->options = is_array($options) ? new options($options) : $options;
        $this->descriptions = [];
        foreach ($descriptions as $key => $description) {
            $key = (string)$key;
            if (!is_string($description)) {
                throw new coding_exception('Option descriptions must be strings: ' . $key);
            }
            if (!$this->options->has_key($key)) {
                throw new coding_exception('Description of unknown option: ' . $key);
            }
            $this->descriptions[$key] = $description;
        }
    }

    /**
     * Add cards to template data.
     *
     * @param array $context template data of the element
     * @param array $selected keys of selected options
     * @return array
     */
    private function add_cards_template_data(array $context, array $selected): array {
        $context['nolabelfor'] = true;
        $groups = $this->options->get_template_data($context['id'], $selected);
        foreach ($groups as $g => $group) {
            foreach ($group['options'] as $o => $option) {
                $description = $this->descriptions[$option['key']] ?? '';
                $groups[$g]['options'][$o]['description'] = clean_string($description);
                $groups[$g]['options'][$o]['has_description'] = ($description !== '');
            }
        }
        $context['groups'] = $groups;
        $context['singlecolumn'] = ($this->maxcolumns === 1);
        return $context;
    }
}
