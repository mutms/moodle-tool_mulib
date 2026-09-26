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

namespace tool_mulib\phpunit\local;

use tool_mulib\local\mulib;
use tool_mulib\local\search_util;
use tool_mulib\local\sql;

/**
 * Search helper tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\search_util
 */
final class search_util_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_get_search_query(): void {
        global $DB;

        $category1 = $this->getDataGenerator()->create_category(['name' => 'First 50%', 'idnumber' => 'CX1']);
        $category2 = $this->getDataGenerator()->create_category(['name' => 'Second', 'idnumber' => 'CX2']);

        $result = search_util::get_search_query(' ', ['name', 'idnumber'], 'cc');
        $this->assertSame(' 1=1 ', $result->sql);
        $this->assertSame([], $result->params);

        $sql0 = new sql("SELECT cc.id FROM {course_categories} cc WHERE /* search */ ORDER BY cc.id ASC");

        $sql = $sql0->replace_comment('search', search_util::get_search_query('cx', ['name', 'idnumber'], 'cc'));
        $this->assertEquals([$category1->id, $category2->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));

        $sql = $sql0->replace_comment('search', search_util::get_search_query('0%', ['name', 'idnumber'], 'cc.'));
        $this->assertEquals([$category1->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));

        $sql = $sql0->replace_comment('search', search_util::get_search_query('secOND', ['name'], 'cc'));
        $this->assertEquals([$category2->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));

        // Two queries in one statement must not share parameter names.
        $search1 = search_util::get_search_query('x', ['name'], 'cc');
        $search2 = search_util::get_search_query('x', ['name'], 'cc');
        $this->assertSame([], array_intersect_key($search1->params, $search2->params));
    }

    public function test_cohort_helpers(): void {
        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Alpha', 'idnumber' => 'A1']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Beta', 'description' => 'Some alpha text',
            'contextid' => $catcontext->id, 'visible' => 0]);
        $cohort3 = $this->getDataGenerator()->create_cohort(['name' => 'Gamma']);

        $sql = (new sql("SELECT ch.id FROM {cohort} ch WHERE /* search */ ORDER BY ch.id ASC"))
            ->replace_comment('search', search_util::get_cohort_search_query('alpha', 'ch'));
        $this->assertEquals([$cohort1->id, $cohort2->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertTrue(search_util::is_cohort_visible($cohort3));
        $this->assertFalse(search_util::is_cohort_visible($cohort2));

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $catcontext);
        role_assign($roleid, $user->id, $catcontext->id);
        $this->assertTrue(search_util::is_cohort_visible($cohort2));

        $cohort2->contextid = -1;
        $this->assertFalse(search_util::is_cohort_visible($cohort2));
    }

    public function test_user_helpers(): void {
        global $DB;

        $syscontext = \context_system::instance();
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Petr', 'lastname' => 'Novak',
            'email' => 'pn@example.com']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Jana', 'lastname' => 'Nova',
            'email' => 'jn@example.com']);
        $this->setAdminUser();

        $search = search_util::get_user_search_query('nova', 'u', $syscontext);
        $orderby = search_util::get_user_search_orderby('nova', 'u', $syscontext);
        $sql = (new sql("SELECT u.id FROM {user} u WHERE /* search */ ORDER BY /* orderby */"))
            ->replace_comment('search', $search)
            ->replace_comment('orderby', $orderby);
        $userids = array_keys($DB->get_records_sql($sql->sql, $sql->params));
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userids);

        $sql = (new sql("SELECT u.id FROM {user} u WHERE /* search */"))
            ->replace_comment('search', search_util::get_user_search_query('Petr', 'u', $syscontext));
        $this->assertEquals([$user1->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));

        set_config('showuseridentity', 'email');
        $label = search_util::format_user_label($user1, $syscontext);
        $this->assertStringContainsString('Petr Novak', $label);
        $this->assertStringContainsString('pn@example.com', $label);

        $user1->deleted = 1;
        $this->assertSame(get_string('deleted'), search_util::format_user_label($user1, $syscontext));
    }

    public function test_get_tenant_related_users_where(): void {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $syscontext = \context_system::instance();
        $result = search_util::get_tenant_related_users_where('u.id', $syscontext);
        $this->assertSame("", $result->sql);

        if (!mulib::is_mutenancy_available()) {
            return;
        }

        \tool_mutenancy\local\tenancy::activate();

        $cohort2 = $this->getDataGenerator()->create_cohort();

        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');

        $tenant1 = $tenantgenerator->create_tenant();
        $tenantcontext1 = \context_tenant::instance($tenant1->id);
        $tenant2 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort2->id]);
        $tenantcontext2 = \context_tenant::instance($tenant2->id);

        $user1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $user2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);
        cohort_add_member($cohort2->id, $user0->id);

        $result = search_util::get_tenant_related_users_where('u.id', $syscontext);
        $this->assertSame("", $result->sql);

        $sql0 = new sql(
            "SELECT u.id
               FROM {user} u
              /* where */
           ORDER BY u.id ASC"
        );

        $result = search_util::get_tenant_related_users_where('u.id', $tenantcontext1);
        $sql = $sql0->replace_comment('where', $result->wrap('WHERE ', ''));
        $this->assertEquals([$user1->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));

        $result = search_util::get_tenant_related_users_where('u.id', $tenantcontext2);
        $sql = $sql0->replace_comment('where', $result->wrap('WHERE ', ''));
        $this->assertEquals([$user0->id, $user2->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));

        \tool_mutenancy\local\tenancy::force_current_tenantid($tenant2->id);

        $result = search_util::get_tenant_related_users_where('u.id', $syscontext);
        $sql = $sql0->replace_comment('where', $result->wrap('WHERE ', ''));
        $this->assertEquals([$user0->id, $user2->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));

        $result = search_util::get_tenant_related_users_where('u.id', $tenantcontext1);
        $sql = $sql0->replace_comment('where', $result->wrap('WHERE ', ''));
        $this->assertEquals([$user1->id], array_keys($DB->get_records_sql($sql->sql, $sql->params)));
    }
}
