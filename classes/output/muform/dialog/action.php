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

namespace tool_mulib\output\muform\dialog;

use core\exception\coding_exception;
use core\output\named_templatable;
use core\output\pix_icon;
use core\output\renderable;
use core\output\renderer_base;
use core\url;
use lang_string;
use tool_mulib\muform\handler\dialog;

/**
 * Base class of triggers that open a muform handler URL in a native dialog.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class action implements named_templatable, renderable {
    /** @var url handler URL, serves both the page and the dialog */
    protected url $url;
    /** @var string|lang_string element label */
    protected string|lang_string $label;
    /** @var string what happens after submission */
    protected string $action = dialog::ACTION_RELOAD;
    /** @var string dialog size: sm, lg or xl */
    protected string $size = 'lg';
    /** @var string|null dialog title, falls back to label */
    protected ?string $title = null;
    /** @var string[] extra CSS classes */
    protected array $classes = [];
    /** @var pix_icon|null optional icon */
    protected ?pix_icon $icon = null;

    /**
     * Constructor.
     *
     * @param url $url handler URL
     * @param string|lang_string $label element label
     */
    public function __construct(url $url, string|lang_string $label) {
        $this->url = $url;
        $this->label = $label;
    }

    /**
     * Returns CSS classes.
     *
     * @return string[]
     */
    public function get_classes(): array {
        return $this->classes;
    }

    /**
     * Replace CSS classes.
     *
     * @param string[] $classes
     * @return static
     */
    public function set_classes(array $classes): static {
        $this->classes = $classes;
        return $this;
    }

    /**
     * Add CSS class.
     *
     * @param string $class
     * @return static
     */
    public function add_class(string $class): static {
        $this->classes[] = $class;
        $this->classes = array_values(array_unique($this->classes));
        return $this;
    }

    /**
     * Set optional icon.
     *
     * @param pix_icon $icon
     * @return static
     */
    public function set_icon(pix_icon $icon): static {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Set dialog size.
     *
     * @param string $size sm, lg or xl
     * @return static
     */
    public function set_form_size(string $size): static {
        if (!in_array($size, ['sm', 'lg', 'xl'], true)) {
            throw new coding_exception('Invalid dialog size, use: sm, lg or xl');
        }
        $this->size = $size;
        return $this;
    }

    /**
     * Set dialog title, handler may change it when rendering.
     *
     * @param string|null $title null means use label
     * @return static
     */
    public function set_modal_title(?string $title): static {
        $this->title = $title;
        return $this;
    }

    /**
     * Specify what happens after the form is submitted.
     *
     * @param string $action one of dialog::ACTION_* constants
     * @return static
     */
    public function set_submitted_action(string $action): static {
        if (!in_array($action, [dialog::ACTION_RELOAD, dialog::ACTION_REDIRECT, dialog::ACTION_NOTHING], true)) {
            throw new coding_exception('Invalid submitted action: ' . $action);
        }
        $this->action = $action;
        return $this;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $data = [
            'url' => $this->url->out(false),
            'label' => clean_string((string)$this->label),
            'title' => clean_string($this->title ?? (string)$this->label),
            'size' => $this->size,
            'action' => $this->action,
            'classes' => implode(' ', $this->classes),
            'iconhtml' => '',
        ];
        if ($this->icon) {
            $data['iconhtml'] = \core\output\icon_system::instance()->render_pix_icon($output, $this->icon);
        }
        return $data;
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        $parts = explode('\\', static::class);
        return 'tool_mulib/muform/dialog/' . array_pop($parts);
    }
}
