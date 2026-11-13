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
 * Behat fixture page for the report filter bar.
 *
 * Core users system report is used because it has text, select, boolean
 * and other filter types that only the core filters form can edit.
 * Parameter pinned=1 pins some of the filters, defaults=1 sets default values.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_admin\reportbuilder\local\systemreports\users;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\system_report_factory;
use tool_mulib\output\report_filter_bar;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_admin();

$context = \core\context\system::instance();

$PAGE->set_context($context);
$url = new \core\url('/admin/tool/mulib/tests/behat/fixtures/report_filter_bar.php');
if (optional_param('pinned', 0, PARAM_BOOL)) {
    $url->param('pinned', 1);
}
if (optional_param('defaults', 0, PARAM_BOOL)) {
    $url->param('defaults', 1);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Report filter bar fixture');
$PAGE->set_heading('Report filter bar fixture');

$report = system_report_factory::create(users::class, $context);

$pinned = [];
if (optional_param('pinned', 0, PARAM_BOOL)) {
    // The last filter does not exist in the report.
    $pinned = ['user:country' => 'All countries', 'user:suspended' => null, 'user:courserole' => null, 'user:unknown' => null];
}

$defaults = [];
if (optional_param('defaults', 0, PARAM_BOOL)) {
    $defaults = ['user:suspended' => ['operator' => boolean_select::NOT_CHECKED]];
}

echo $OUTPUT->header();
echo $OUTPUT->render(new report_filter_bar($report, $pinned, $defaults));
echo $report->output();
echo $OUTPUT->footer();
