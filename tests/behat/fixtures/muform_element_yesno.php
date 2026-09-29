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
 * Behat fixture page for the muform yesno element.
 *
 * See muform_fixture_lib.php for the query parameters and the printed output.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\yesno;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_once(__DIR__ . '/muform_fixture_lib.php');
require_admin();

/**
 * Fixture form with yesno elements.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_fixture_yesno_form extends form {
    #[\Override]
    protected function definition(): void {
        $flags = $this->get_extra_data();

        $this->add(new checkbox('hide', 'Hide rule', 'Hide the element'));
        $this->add(new checkbox('lock', 'Lock rule', 'Lock the element'));

        $active = new yesno('active', 'Active');
        $active->set_frozen($flags['frozen']);
        if ($flags['default']) {
            $active->set_default(1);
        }
        $this->add($active);

        $this->add(new yesno('signups', 'Allow sign-ups'));

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new reload('refresh', 'Refresh'), 'buttons');
        $this->add(new cancel(), 'buttons');

        $dm = $this->get_display_manager();
        $dm->hide_if('active', 'hide', 'checked');
        $dm->disable_if('active', 'lock', 'checked');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if ($data['signups'] && !$data['active']) {
            $allerrors['signups'][] = 'Sign-ups need an active source';
        }
    }
}

$flags = tool_mulib_muform_fixture_flags();
$pageurl = tool_mulib_muform_fixture_setup('yesno', $flags);

$current = [];
if ($flags['prefill']) {
    $current = [
        'active' => 1,
        'signups' => true,
    ];
}

$form = new tool_mulib_muform_fixture_yesno_form($pageurl, $current, $flags);
tool_mulib_muform_fixture_finish($form, $pageurl);
