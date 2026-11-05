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

/**
 * Behat fixture page for the muform datetimerange element.
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
use tool_mulib\muform\element\datetimerange;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_once(__DIR__ . '/muform_fixture_lib.php');
require_admin();

/**
 * Fixture form with datetimerange elements.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_fixture_datetimerange_form extends form {
    #[\Override]
    protected function definition(): void {
        $flags = $this->get_extra_data();

        $this->add(new checkbox('lock', 'Lock rule', 'Lock the element'));

        $session = (new datetimerange('session', 'Session'))
            ->set_required($flags['required'])
            ->set_frozen($flags['frozen'])
            ->set_default_duration(HOURSECS);
        if ($flags['default']) {
            $session->set_default(['start' => 1893459600, 'end' => 1893463200]);
        }
        $this->add($session);

        // Workshops start at 9 by default and last at most 8 hours.
        $this->add((new datetimerange('workshop', 'Workshop'))->set_default_time('09:00')->set_max_duration(8 * HOURSECS));

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new reload('refresh', 'Refresh'), 'buttons');
        $this->add(new cancel(), 'buttons');

        $this->get_display_manager()->disable_if('session', 'lock', 'checked');
    }
}

$flags = tool_mulib_muform_fixture_flags();
$pageurl = tool_mulib_muform_fixture_setup('datetimerange', $flags);

$current = [];
if ($flags['prefill']) {
    // 2026-11-06 23:30 - 2026-11-07 01:30 in Australia/Perth, the timezone of Behat users.
    $current = [
        'session' => ['start' => 1793979000, 'end' => 1793986200],
        'workshop' => null,
    ];
}

$form = new tool_mulib_muform_fixture_datetimerange_form($pageurl, $current, $flags);
tool_mulib_muform_fixture_finish($form, $pageurl);
