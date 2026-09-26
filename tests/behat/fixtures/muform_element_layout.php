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
 * Behat fixture page for the muform layout element.
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
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\section;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_once(__DIR__ . '/muform_fixture_lib.php');
require_admin();

/**
 * Fixture form with layout elements.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_fixture_layout_form extends form {
    #[\Override]
    protected function definition(): void {
        $flags = $this->get_extra_data();

        $this->add(new checkbox('hide', 'Hide rule', 'Hide the element'));
        $this->add(new checkbox('lock', 'Lock rule', 'Lock the element'));

        $this->add(new section('basics', 'Basic details'));

        $created = (new info('created', 'Created', 'Not created yet', info::TEXTFORMAT))
            ->set_frozen($flags['frozen']);
        $this->add($created, 'basics');

        $this->add(new inforawhtml('notice', 'Notice', '<em>Raw</em> <strong>html</strong> notice'), 'basics');
        $this->add(new inforawhtml('banner', '', '<div class="alert alert-info">Full width banner</div>'), 'basics');

        $fullname = (new text('fullname', 'Full name'))
            ->set_required($flags['required']);
        $this->add($fullname, 'basics');

        $this->add(new section('extras', 'Extra details'));
        $this->add(new text('nickname', 'Nickname'), 'extras');

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new reload('refresh', 'Refresh'), 'buttons');
        $this->add(new cancel(), 'buttons');

        $dm = $this->get_display_manager();
        $dm->hide_if('extras', 'hide', 'checked');
        $dm->disable_if('extras', 'lock', 'checked');
    }
}

$flags = tool_mulib_muform_fixture_flags();
$pageurl = tool_mulib_muform_fixture_setup('layout', $flags);

$current = [];
if ($flags['prefill']) {
    $current = [
        'created' => 'Created **yesterday**',
        'createdformat' => FORMAT_MARKDOWN,
        'fullname' => 'Jane Doe',
    ];
}

$form = new tool_mulib_muform_fixture_layout_form($pageurl, $current, $flags);
tool_mulib_muform_fixture_finish($form, $pageurl);
