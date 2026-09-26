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
 * Trusted HTML display, no value is returned.
 *
 * The html comes from the form definition only, never from current data,
 * and is printed without any processing, so it must be safe already.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class inforawhtml extends element {
    /** @var string trusted html */
    private string $html;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label empty label renders the html across the whole row
     * @param string $html trusted html
     */
    public function __construct(string $name, string $label, string $html) {
        parent::__construct($name);
        $this->label = $label;
        $this->html = $html;
    }

    #[\Override]
    public function returns_data(): bool {
        return false;
    }

    #[\Override]
    protected function parse_value(): void {
        $this->value = null;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['nolabelfor'] = true;
        $context['contentrawhtml'] = $this->html;
        $context['nolabel'] = ($this->label === '');
        return $context;
    }
}
