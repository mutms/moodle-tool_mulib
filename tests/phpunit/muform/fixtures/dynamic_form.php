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
use tool_mulib\muform\element\radios;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;

/**
 * Test form with definition depending on submitted data.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dynamic_form extends form {
    #[\Override]
    protected function definition(): void {
        $mode = new radios('mode', 'Mode', ['basic' => 'Basic', 'extra' => 'Extra']);
        $this->add($mode);
        if ($mode->get_value() === 'extra') {
            $extra = (new text('extra', 'Extra'))
                ->set_required(true);
            $this->add($extra);
        }
        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new reload('update', 'Update'), 'buttons');
    }
}
