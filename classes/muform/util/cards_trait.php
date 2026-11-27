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
use core\output\core_renderer;

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
    private int $maxcolumns = 3;
    /** @var array icons indexed by option keys, see set_icons() */
    private array $icons = [];
    /** @var float|null minimum card column width in rem for more columns in narrow elements, null means fixed element widths */
    private ?float $mincolumnwidth = null;

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
     * Cards are shown in two columns when the element is at least 32rem wide and in three columns
     * from 50rem, use 1 or 2 when long descriptions do not read well side by side.
     *
     * @param int $columns 1, 2 or 3
     * @return static
     */
    public function set_max_columns(int $columns): static {
        if ($columns < 1 || $columns > 3) {
            throw new coding_exception('Card columns must be 1, 2 or 3');
        }
        $this->maxcolumns = $columns;
        return $this;
    }

    /**
     * Show more card columns (up to the maximum) whenever each column can be at least this wide.
     *
     * By default cards are in two columns when the element is at least 32rem wide and in three from 50rem,
     * use this for short cards that fit side by side in smaller dialogs too. Ignored with set_max_columns(1).
     *
     * @param float $rem minimum column width in rem, for example 12
     * @return static
     */
    public function set_min_column_width(float $rem): static {
        if ($rem <= 0) {
            throw new coding_exception('Card column width must be positive');
        }
        $this->mincolumnwidth = $rem;
        return $this;
    }

    /**
     * Show a big icon in each card, the check box or radio moves to the top right corner.
     *
     * Icons are Font Awesome classes ("fa-solid fa-gun", "fa-bullseye") or images from pix directories
     * as "component:name" ("local_example:type_basic"), usually SVG. The icon is decorative only,
     * the card title must say everything. Options without an icon get an empty box of the same size.
     *
     * @param array $icons icons indexed by option keys
     * @return static
     */
    public function set_icons(array $icons): static {
        foreach ($icons as $key => $icon) {
            $key = (string)$key;
            if (!$this->options->has_key($key)) {
                throw new coding_exception('Icon of unknown option: ' . $key);
            }
            if (!is_string($icon) || !preg_match('/^(fa-[a-z0-9-]+( fa-[a-z0-9-]+)*|[a-z][a-z0-9_]*:[a-zA-Z0-9_\/.-]+)$/', $icon)) {
                throw new coding_exception('Invalid card icon: ' . $key);
            }
        }
        $this->icons = array_map('strval', $icons);
        return $this;
    }

    /**
     * HTML of a card icon.
     *
     * @param core_renderer $output
     * @param string $icon see set_icons()
     * @return string
     */
    private static function render_card_icon(core_renderer $output, string $icon): string {
        if (str_starts_with($icon, 'fa-')) {
            $classes = str_contains($icon, ' ') ? $icon : 'fa-solid ' . $icon;
            return \html_writer::tag('i', '', ['class' => 'icon-fa ' . $classes, 'aria-hidden' => 'true']);
        }
        [$component, $name] = explode(':', $icon, 2);
        return \html_writer::empty_tag('img', ['src' => $output->image_url($name, $component)->out(false), 'alt' => '']);
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
     * @param core_renderer $output
     * @param array $context template data of the element
     * @param array $selected keys of selected options
     * @return array
     */
    private function add_cards_template_data(core_renderer $output, array $context, array $selected): array {
        $context['nolabelfor'] = true;
        $groups = $this->options->get_template_data($context['id'], $selected);
        foreach ($groups as $g => $group) {
            foreach ($group['options'] as $o => $option) {
                $description = $this->descriptions[$option['key']] ?? '';
                $groups[$g]['options'][$o]['description'] = clean_string($description);
                $groups[$g]['options'][$o]['has_description'] = ($description !== '');
                $icon = $this->icons[$option['key']] ?? null;
                $groups[$g]['options'][$o]['iconhtml'] = $icon === null ? '' : self::render_card_icon($output, $icon);
                $groups[$g]['options'][$o]['is_iconimage'] = $icon !== null && !str_starts_with($icon, 'fa-');
            }
        }
        $context['has_icons'] = !empty($this->icons);
        $context['groups'] = $groups;
        $context['singlecolumn'] = ($this->maxcolumns === 1);
        $context['maxtwocolumns'] = ($this->maxcolumns === 2);
        $context['maxcolumns'] = $this->maxcolumns;
        $context['mincolumnwidth'] = ($this->maxcolumns > 1 && $this->mincolumnwidth !== null)
            ? rtrim(rtrim(sprintf('%.3F', $this->mincolumnwidth), '0'), '.') : null;
        return $context;
    }
}
