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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cohort helper tests.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\local\cohort_util::class)]
#[Group('MuTMS')]
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

    public function test_use(): void {
        global $DB;

        $syscontext = \core\context\system::instance();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 1']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 2', 'visible' => 0]);
        $hidden = ['visible' => 0];
        $cohort3 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 3', 'contextid' => $catcontext->id] + $hidden);
        $cohort4 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 4', 'component' => 'enrol_database'] + $hidden);
        $cohorts = [$cohort1, $cohort2, $cohort3, $cohort4];
        $user = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $viewer->id, $syscontext);
        $catviewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $catcontext);
        role_assign($roleid, $catviewer->id, $catcontext);
        $assigner = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $assigner->id, $syscontext);

        $ids = function (int $userid) use ($DB): array {
            $sql = (new \tool_mulib\local\sql("SELECT ch.id FROM {cohort} ch WHERE /* use */ ORDER BY ch.id"))
                ->replace_comment('use', cohort_util::get_use_sql('ch', $userid));
            return array_map('intval', $DB->get_fieldset_sql($sql->sql, $sql->params));
        };

        $all = [(int)$cohort1->id, (int)$cohort2->id, (int)$cohort3->id, (int)$cohort4->id];
        $this->assertSame([(int)$cohort1->id], $ids((int)$user->id));
        $this->assertSame([(int)$cohort1->id], $ids((int)$assigner->id));
        $this->assertSame($all, $ids((int)$viewer->id));
        $this->assertSame([(int)$cohort1->id, (int)$cohort3->id], $ids((int)$catviewer->id));
        $this->assertSame($all, $ids((int)get_admin()->id));
        $this->assertSame([(int)$cohort1->id], $ids((int)guest_user()->id));
        $this->assertSame([(int)$cohort1->id], $ids(0));

        foreach ([$user, $viewer, $catviewer, $assigner, get_admin(), guest_user()] as $account) {
            $this->setUser($account);
            $expected = $ids((int)$account->id);
            foreach ($cohorts as $cohort) {
                $this->assertSame(in_array((int)$cohort->id, $expected), cohort_util::can_use($cohort), $cohort->name);
            }
        }

        $this->setAdminUser();
        $cohort1->contextid = -1;
        $this->assertFalse(cohort_util::can_use($cohort1));
    }

    public function test_view_members(): void {
        global $DB;

        $syscontext = \core\context\system::instance();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 1']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 2', 'contextid' => $catcontext->id]);
        $cohort3 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort 3', 'component' => 'enrol_database']);
        $user = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $viewer->id, $syscontext);
        $assigner = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $catcontext);
        role_assign($roleid, $assigner->id, $catcontext);

        $ids = function (\stdClass $user) use ($DB): array {
            $sql = (new \tool_mulib\local\sql("SELECT ch.id FROM {cohort} ch WHERE /* viewmembers */ ORDER BY ch.id"))
                ->replace_comment('viewmembers', cohort_util::get_view_members_sql('ch', (int)$user->id));
            return array_map('intval', $DB->get_fieldset_sql($sql->sql, $sql->params));
        };

        // Visible cohorts and moodle/cohort:view are not enough.
        $this->assertSame([], $ids($user));
        $this->assertSame([], $ids($viewer));
        $this->assertSame([(int)$cohort2->id], $ids($assigner));
        $this->assertSame([(int)$cohort1->id, (int)$cohort2->id, (int)$cohort3->id], $ids(get_admin()));
        $this->assertSame('1=2', cohort_util::get_view_members_sql('ch', (int)guest_user()->id)->sql);
        $this->assertSame('1=2', cohort_util::get_view_members_sql('ch', 0)->sql);

        foreach ([$user, $viewer, $assigner, get_admin()] as $account) {
            $this->setUser($account);
            $expected = $ids($account);
            foreach ([$cohort1, $cohort2, $cohort3] as $cohort) {
                $this->assertSame(in_array((int)$cohort->id, $expected), cohort_util::can_view_members($cohort));
            }
        }
        $this->setGuestUser();
        $this->assertFalse(cohort_util::can_view_members($cohort2));
        $this->setUser(null);
        $this->assertFalse(cohort_util::can_view_members($cohort2));
    }
}
