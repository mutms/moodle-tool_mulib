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

namespace tool_mulib\phpunit\muform\fixtures;

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\hidden;
use tool_mulib\muform\element\number;
use tool_mulib\muform\element\radios;
use tool_mulib\muform\element\section;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;

/**
 * Test form with all basic elements.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class simple_form extends form {
    #[\Override]
    protected function definition(): void {
        $this->add(new section('general', 'General'));
        $this->add(new hidden('itemid'), 'general');
        $name = (new text('name', 'Name', ['maxlength' => 20]))
            ->set_required(true);
        $this->add($name, 'general');
        $this->add(new number('count', 'Count', ['min' => 0, 'max' => 10]), 'general');
        $this->add(new textarea('notes', 'Notes'), 'general');
        $this->add(new checkbox('enabled', 'Enabled', 'Yes'), 'general');
        $this->add(new radios('color', 'Colour', ['red' => 'Red', 'green' => 'Green']), 'general');
        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new cancel(), 'buttons');

        $dm = $this->get_display_manager();
        $dm->hide_if('notes', 'enabled', 'notchecked');
        $dm->disable_if('count', 'color', 'eq', 'red');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if ($data['name'] === 'invalid') {
            $allerrors['name'][] = 'Name is invalid';
        }
        if ($data['name'] === 'formerror') {
            $allerrors['__form'][] = 'Whole form is invalid';
        }
    }
}
