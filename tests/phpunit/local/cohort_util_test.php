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
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mulib\phpunit\local;

use tool_mulib\local\cohort_util;
use tool_mulib\local\mulib;

/**
 * Cohort helper tests.
 *
 * @group      MuTMS
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\cohort_util
 */
final class cohort_util_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_cohort_links(): void {
        $syscontext = \core\context\system::instance();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort <b>1</b>']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 2', 'contextid' => $catcontext->id]);
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $catcontext);
        role_assign($roleid, $viewer->id, $catcontext);

        if (mulib::is_mucohort_available()) {
            $expected1 = '/admin/tool/mucohort/cohort.php?id=' . $cohort1->id;
            $expected2 = '/admin/tool/mucohort/cohort.php?id=' . $cohort2->id;
        } else {
            $url = new \core\url('/cohort/index.php', ['contextid' => $syscontext->id, 'search' => $cohort1->name]);
            $expected1 = $url->out(false);
            $url = new \core\url('/cohort/index.php', ['contextid' => $catcontext->id, 'search' => $cohort2->name]);
            $expected2 = $url->out(false);
        }

        $this->setAdminUser();
        $this->assertStringEndsWith($expected1, cohort_util::get_cohort_url($cohort1)->out(false));
        $this->assertStringEndsWith($expected2, cohort_util::get_cohort_url($cohort2)->out(false));

        $this->setUser($viewer);
        $this->assertNull(cohort_util::get_cohort_url($cohort1));
        $this->assertStringEndsWith($expected2, cohort_util::get_cohort_url($cohort2)->out(false));

        $this->assertSame('Cohort 1', cohort_util::format_cohort_name($cohort1));
        $html = cohort_util::format_cohort_name($cohort2);
        $this->assertStringStartsWith('<a href=', $html);
        $this->assertStringEndsWith('>Cohort 2</a>', $html);
        $this->assertSame('Cohort 2', cohort_util::format_cohort_name($cohort2, false));

        $names = cohort_util::format_cohort_names([$cohort2->id, -1, $cohort1->id], false);
        $this->assertSame([$cohort2->id => 'Cohort 2', $cohort1->id => 'Cohort 1'], $names);
        $this->assertSame([], cohort_util::format_cohort_names([]));
    }
}
