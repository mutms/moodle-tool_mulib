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

/**
 * Stage form of the muform wizard fixture page.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\filemanager;
use tool_mulib\muform\element\text;
use tool_mulib\muform\element\wizardcontinue;
use tool_mulib\muform\form;

defined('MOODLE_INTERNAL') || die();
defined('BEHAT_SITE_RUNNING') || die();

/**
 * Stage 1: details, read-only when it is locked.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_wizard_details_form extends form {
    #[\Override]
    protected function definition(): void {
        $locked = !empty($this->get_extra_data()['locked']);
        $fullname = (new text('fullname', 'Full name'))
            ->set_required(true)
            ->set_frozen($locked);
        $this->add($fullname);
        $this->add((new filemanager('attachments', 'Attachments', 1, ['.txt']))->set_frozen($locked));

        $this->add(new buttons('buttons'));
        $this->add(new wizardcontinue(), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
