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
// phpcs:disable moodle.Files.LineLength.TooLong

namespace tool_mulib\phpunit\muform\autocomplete;

use tool_mulib\muform\autocomplete\extdb_query_context;

/**
 * External database query context autocomplete source tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\autocomplete\extdb_query_context
 */
final class extdb_query_context_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create categories and users with the site configuration capability in different contexts.
     *
     * @return array
     */
    private function set_up_data(): array {
        global $DB;

        $category0 = $DB->get_record('course_categories', []);
        $category1 = $this->getDataGenerator()->create_category([
            'name' => 'Kategorie 1',
            'idnumber' => 'KAT1',
            'description' => 'Popis 1',
        ]);
        $category2 = $this->getDataGenerator()->create_category([
            'name' => 'Kategorie 2',
            'idnumber' => 'KAT2',
            'description' => 'Popis 2',
            'parent' => $category1->id,
        ]);
        $course1 = $this->getDataGenerator()->create_course(['category' => $category1->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $syscontext = \context_system::instance();
        $managerroleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/site:config', CAP_ALLOW, $managerroleid, $syscontext);
        role_assign($managerroleid, $user1->id, $syscontext->id);
        role_assign($managerroleid, $user2->id, \context_coursecat::instance($category1->id)->id);

        return [
            'sys' => $syscontext,
            'cat0' => \context_coursecat::instance($category0->id),
            'cat1' => \context_coursecat::instance($category1->id),
            'cat2' => \context_coursecat::instance($category2->id),
            'course1' => \context_course::instance($course1->id),
            'category0' => $category0,
            'user1' => $user1,
            'user2' => $user2,
        ];
    }

    public function test_search(): void {
        $d = $this->set_up_data();
        $this->setUser($d['user1']);

        $source = new extdb_query_context($d['sys']->id);
        $this->assertSame([$d['sys']->id], $source->get_args());

        $this->assertSame([
            (string)$d['sys']->id => 'System',
            (string)$d['cat0']->id => $d['category0']->name,
            (string)$d['cat1']->id => 'Kategorie 1',
            (string)$d['cat2']->id => 'Kategorie 1 / Kategorie 2',
        ], $source->search('', 50));

        // Name, idnumber and description are searched.
        $this->assertSame([
            (string)$d['cat1']->id => 'Kategorie 1',
        ], $source->search('AT1', 50));
        $this->assertSame([
            (string)$d['cat2']->id => 'Kategorie 1 / Kategorie 2',
        ], $source->search('Popis 2', 50));
        $this->assertSame([
            (string)$d['sys']->id => 'System',
        ], $source->search('syst', 50));

        // Too many results.
        $this->assertNull($source->search('', 3));
    }

    public function test_label(): void {
        $d = $this->set_up_data();
        $this->setUser($d['user1']);

        $source = new extdb_query_context($d['sys']->id);
        $this->assertSame('System', $source->label((string)$d['sys']->id));
        $this->assertSame('Kategorie 1', $source->label((string)$d['cat1']->id));
        $this->assertSame('Kategorie 1 / Kategorie 2', $source->label((string)$d['cat2']->id));
        $this->assertNull($source->label((string)$d['course1']->id));
        $this->assertNull($source->label('-1'));
        $this->assertNull($source->label('abc'));
    }

    public function test_label_capability(): void {
        $d = $this->set_up_data();
        $this->setUser($d['user1']);
        $source = new extdb_query_context($d['cat2']->id);

        // Categories without the capability are refused, except the current value of the query.
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/site:config', CAP_PROHIBIT, $roleid, $d['sys']);
        role_assign($roleid, $d['user1']->id, $d['cat1']->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertNull($source->label((string)$d['cat1']->id));
        $this->assertSame('Kategorie 1 / Kategorie 2', $source->label((string)$d['cat2']->id));
        $this->assertNotContains('Kategorie 1', $source->search('', 50));
    }

    public function test_constructor_requires_system_capability(): void {
        $d = $this->set_up_data();
        $this->setUser($d['user2']);
        $this->expectException(\core\exception\required_capability_exception::class);
        new extdb_query_context($d['sys']->id);
    }

    public function test_search_tenant(): void {
        global $DB;

        if (!\tool_mulib\local\mulib::is_mutenancy_available()) {
            $this->markTestSkipped('multitenancy not available');
        }

        $category0 = $DB->get_record('course_categories', []);
        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        $tenant1 = $tenantgenerator->create_tenant();
        $tenant2 = $tenantgenerator->create_tenant();
        $syscontext = \context_system::instance();
        $catcontext0 = \context_coursecat::instance($category0->id);
        $catcontext1 = \context_coursecat::instance($tenant1->categoryid);
        $catcontext2 = \context_coursecat::instance($tenant2->categoryid);

        $this->setAdminUser();
        $source = new extdb_query_context($syscontext->id);
        $this->assertEqualsCanonicalizing(
            [$syscontext->id, $catcontext0->id, $catcontext1->id, $catcontext2->id],
            array_keys($source->search('', 50))
        );

        \tool_mutenancy\local\tenancy::switch($tenant1->id);
        $this->assertEqualsCanonicalizing(
            [$syscontext->id, $catcontext0->id, $catcontext1->id],
            array_keys($source->search('', 50))
        );
    }
}
