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

use core\param;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\checkboxes;
use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\element\autocompletemany;
use tool_mulib\muform\element\datetime;
use tool_mulib\muform\element\editor;
use tool_mulib\muform\element\filemanager;
use tool_mulib\muform\element\hidden;
use tool_mulib\muform\element\multiselect;
use tool_mulib\muform\element\number;
use tool_mulib\muform\element\radios;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\section;
use tool_mulib\muform\element\secret;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\sharedkey;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;
use tool_mulib\muform\util\options;
use tool_mulib\muform\validator\required_if_visible;

defined('MOODLE_INTERNAL') || die();

// Test-only form, included by the fixture controller from Behat and PHPUnit.
if (!(defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
    die();
}

/**
 * Behat fixture form with all muform elements.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_elements_form extends form {
    #[\Override]
    protected function definition(): void {
        $this->add(new hidden('id'));
        $this->add(new hidden('token', param::ALPHANUM));

        $this->add(new section('texts', 'Texts'));

        $fullname = (new text('fullname', 'Full name', ['maxlength' => 100]))
            ->set_required(true);
        $this->add($fullname, 'texts');

        $this->add(new text('email', 'Email', ['type' => 'email']), 'texts');
        $this->add(new text('code', 'Code', ['type' => 'rawtext']), 'texts');
        $this->add(new textarea('notes', 'Notes', ['rows' => 3]), 'texts');

        $created = (new text('created', 'Created'))
            ->set_frozen(true);
        $this->add($created, 'texts');

        $this->add(new section('numbers', 'Numbers'));
        $this->add(new number('quantity', 'Quantity', ['min' => 0, 'max' => 100]), 'numbers');
        $this->add(new number('price', 'Price', ['min' => 0, 'decimals' => 2]), 'numbers');

        $this->add(new section('dates', 'Dates'));
        $starts = (new datetime('starts', 'Starts'))
            ->set_required(true);
        $this->add($starts, 'dates');
        $this->add(new datetime('ends', 'Ends'), 'dates');

        $this->add(new section('keys', 'Keys'));
        $this->add(new secret('dbsecret', 'Database secret', [], true), 'keys');
        $this->add(new sharedkey('enrolkey', 'Enrolment key', [], true), 'keys');

        $this->add(new section('files', 'Files'));
        $this->add(new filemanager('attachments', 'Attachments', 2, ['.txt', '.csv']), 'files');
        $this->add(new filemanager('photo', 'Photo', 1, ['image']), 'files');
        $this->add(new editor('description', 'Description', -1, false, ['rows' => 5]), 'files');

        $this->add(new section('pickers', 'Pickers'));
        $syscontextid = \core\context\system::instance()->id;
        $ownersource = new \tool_mulib\muform\autocomplete\site_user($syscontextid);
        $this->add(new autocomplete('owner', 'Owner', $ownersource, ['placeholder' => 'Find a user...']), 'pickers');
        $memberssource = new \tool_mulib\muform\autocompletemany\site_users($syscontextid);
        $this->add(new autocompletemany('members', 'Members', $memberssource, ['placeholder' => 'Find a user...']), 'pickers');

        $this->add(new section('choices', 'Choices'));

        $agree = (new checkbox('agree', 'Terms', 'I agree'))
            ->set_required(true);
        $this->add($agree, 'choices');

        $this->add(new checkbox('subscribe', 'Newsletter', 'Send me updates'), 'choices');

        $frequency = (new radios('frequency', 'Frequency', ['daily' => 'Daily', 'weekly' => 'Weekly']))
            ->set_required_marker(true)
            ->add_validator(new required_if_visible());
        $this->add($frequency, 'choices');

        $roleoptions = (new options(['manager' => 'Manager']))
            ->add_optgroup('Teachers', ['editingteacher' => 'Editing teacher', 'teacher' => 'Non-editing teacher'])
            ->add_optgroup('Others', ['student' => 'Student']);
        $this->add(new checkboxes('roles', 'Roles', $roleoptions), 'choices');

        $countryoptions = (new options(['' => 'Choose...']))
            ->add_optgroup('Europe', ['cz' => 'Czechia', 'de' => 'Germany'])
            ->add_optgroup('America', ['us' => 'United States']);
        $country = (new select('country', 'Country', $countryoptions))
            ->set_required(true);
        $this->add($country, 'choices');

        $this->add(new multiselect('languages', 'Languages', ['en' => 'English', 'cs' => 'Czech', 'de' => 'German']), 'choices');

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new reload('refresh', 'Refresh'), 'buttons');
        $this->add(new cancel(), 'buttons');

        $dm = $this->get_display_manager();
        $dm->hide_if('frequency', 'subscribe', 'notchecked');
        $dm->disable_if('languages', 'country', 'eq', '');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if ($data['fullname'] === 'invalid') {
            $allerrors['fullname'][] = 'This name is not allowed';
        }
    }
}
