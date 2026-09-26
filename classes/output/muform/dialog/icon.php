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

use core\output\pix_icon;
use core\url;
use lang_string;

/**
 * Icon link that opens a muform handler URL in a native dialog.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class icon extends action {
    /**
     * Constructor.
     *
     * @param url $url handler URL
     * @param string|lang_string $title icon title
     * @param string $pixname
     * @param string $pixcomponent
     */
    public function __construct(url $url, string|lang_string $title, string $pixname, string $pixcomponent = 'core') {
        parent::__construct($url, $title);
        $this->icon = new pix_icon($pixname, '', $pixcomponent, ['aria-hidden' => 'true']);
    }
}
