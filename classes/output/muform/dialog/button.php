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

use core\output\renderer_base;
use core\url;
use lang_string;

/**
 * Button that opens a muform handler URL in a native dialog.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class button extends action {
    /** @var bool primary button styling */
    private bool $primary;
    /** @var bool outline button styling */
    private bool $outline = false;

    /**
     * Constructor.
     *
     * @param url $url handler URL
     * @param string|lang_string $label button label
     * @param bool $primary
     */
    public function __construct(url $url, string|lang_string $label, bool $primary = false) {
        parent::__construct($url, $label);
        $this->primary = $primary;
    }

    /**
     * Use primary button styling.
     *
     * @param bool $primary
     * @return static
     */
    public function set_primary(bool $primary): static {
        $this->primary = $primary;
        return $this;
    }

    /**
     * Use outline button styling.
     *
     * @param bool $outline
     * @return static
     */
    public function set_outline(bool $outline): static {
        $this->outline = $outline;
        return $this;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $data = parent::export_for_template($output);
        $data['is_primary'] = $this->primary;
        $data['is_outline'] = $this->outline;
        return $data;
    }
}
