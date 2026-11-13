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
use tool_mulib\muform\element\collapsible;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;

/**
 * Test form with collapsible sections.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class collapsible_form extends form {
    #[\Override]
    protected function definition(): void {
        $this->add((new collapsible('first', 'First', 'key-first'))->set_summary('Summary <b>one</b>'));
        $this->add(new text('name', 'Name'), 'first');
        $this->add(new collapsible('second', 'Second'));
        $this->add((new text('nick', 'Nick'))->set_required(true), 'second');
        $remove = (new reload('remove', 'Remove'))->set_attribute('link', true)->set_attribute('small', true);
        $this->add($remove->set_confirm('Really?'), 'second');
        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
    }
}
