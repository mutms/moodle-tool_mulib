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
 * Behat fixture page for the report pager.
 *
 * Core users system report is used together with the filter bar,
 * the range of records in the pager depends on filtering.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_admin\reportbuilder\local\systemreports\users;
use core_reportbuilder\system_report_factory;
use tool_mulib\output\report_filter_bar;
use tool_mulib\output\report_pager;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_admin();

$context = \core\context\system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new \core\url('/admin/tool/mulib/tests/behat/fixtures/report_pager.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Report pager fixture');
$PAGE->set_heading('Report pager fixture');

$report = system_report_factory::create(users::class, $context);

echo $OUTPUT->header();
echo $OUTPUT->render(new report_filter_bar($report));
echo $report->output();
echo $OUTPUT->render(new report_pager($report));
echo $OUTPUT->footer();
