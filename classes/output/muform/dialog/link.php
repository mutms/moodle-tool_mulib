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
use core\output\pix_icon;
use core\url;
use lang_string;

/**
 * Link that opens a muform handler URL in a native dialog,
 * can be converted to icon, button or report builder action.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link extends action {
    /**
     * Constructor.
     *
     * @param url $url handler URL
     * @param string|lang_string $text link text
     * @param string $pixname optional icon
     * @param string $pixcomponent
     */
    public function __construct(url $url, string|lang_string $text, string $pixname = '', string $pixcomponent = 'core') {
        parent::__construct($url, $text);
        if ($pixname !== '') {
            $this->icon = new pix_icon($pixname, '', $pixcomponent, ['aria-hidden' => 'true']);
        }
    }

    /**
     * Create report builder action opening the dialog.
     *
     * @param array $attributes extra link attributes
     * @return \core_reportbuilder\local\report\action
     */
    public function create_report_action(array $attributes = []): \core_reportbuilder\local\report\action {
        // The URL is taken from the href, which the report builder fills with the row placeholders.
        $attributes['data-muform-dialog-title'] = $this->title ?? (string)$this->label;
        $attributes['data-muform-dialog-size'] = $this->size;
        $attributes['data-muform-dialog-action'] = $this->action;
        $attributes['onclick'] = "import('@moodle/lms/tool_mulib/muform/dialog').then((m) => m.openFrom(this)); return false;";
        $attributes['title'] = $this->label;
        return new \core_reportbuilder\local\report\action($this->url, $this->icon, $attributes, false, null);
    }

    /**
     * Create icon with the same settings.
     *
     * @return icon
     */
    public function create_icon(): icon {
        if (!$this->icon) {
            throw new coding_exception('Link does not have an icon defined');
        }
        $icon = new icon($this->url, $this->label, $this->icon->pix, $this->icon->component);
        $icon->set_form_size($this->size);
        $icon->set_submitted_action($this->action);
        $icon->set_modal_title($this->title);
        return $icon;
    }

    /**
     * Create button with the same settings.
     *
     * @param bool $primary
     * @param bool $useicon
     * @param bool $outline
     * @return button
     */
    public function create_button(bool $primary = false, bool $useicon = false, bool $outline = false): button {
        $button = new button($this->url, $this->label, $primary);
        $button->set_form_size($this->size);
        $button->set_submitted_action($this->action);
        $button->set_modal_title($this->title);
        $button->set_outline($outline);
        if ($useicon && $this->icon) {
            $button->set_icon($this->icon);
        }
        return $button;
    }
}
