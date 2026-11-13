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
 * Behat fixture page for the muform collapsible element and button confirmation.
 *
 * See muform_fixture_lib.php for the query parameters and the printed output,
 * parameter "key" is the state key of collapsed sections.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\collapsible;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_once(__DIR__ . '/muform_fixture_lib.php');
require_admin();

/**
 * Fixture form with collapsible sections.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_fixture_collapsible_form extends form {
    #[\Override]
    protected function definition(): void {
        $flags = $this->get_extra_data();
        $key = $flags['key'];

        $this->add((new collapsible('first', 'First part', $key . '-first'))->set_summary('Summary of first'));
        $this->add(new text('fullname', 'Full name'), 'first');

        $this->add(new collapsible('second', 'Second part', $key . '-second'));
        $this->add((new text('nickname', 'Nickname'))->set_required($flags['required']), 'second');
        $this->add((new reload('removesecond', 'Remove second'))->set_confirm('Really remove the second part?'), 'second');

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if (($data['nickname'] ?? '') === 'bad') {
            $allerrors['nickname'][] = 'Nickname is bad';
        }
    }
}

$flags = tool_mulib_muform_fixture_flags();
$key = optional_param('key', 'one', PARAM_ALPHANUM);
$pageurl = tool_mulib_muform_fixture_setup('collapsible', $flags);
$pageurl->param('key', $key);

$form = new tool_mulib_muform_fixture_collapsible_form($pageurl, [], $flags + ['key' => $key]);
tool_mulib_muform_fixture_finish($form, $pageurl);
