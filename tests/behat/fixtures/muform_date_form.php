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

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\datetime;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

defined('MOODLE_INTERNAL') || die();

// Test-only form, included by the fixture controller from Behat and PHPUnit.
if (!(defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
    die();
}

/**
 * Behat fixture form with a single date, shown in a small dialog.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_date_form extends form {
    #[\Override]
    protected function definition(): void {
        $this->add(new datetime('due', 'Due'));

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
