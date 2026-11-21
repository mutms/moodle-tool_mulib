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
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mulib\phpunit\local;

use tool_mulib\local\profilefield_cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Profile field cache tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\local\profilefield_cache::class)]
#[CoversClass(\tool_mulib\local\profilefield\text::class)]
#[CoversClass(\tool_mulib\local\profilefield\menu::class)]
#[CoversClass(\tool_mulib\local\profilefield\checkbox::class)]
#[CoversClass(\tool_mulib\local\profilefield\datetime::class)]
#[CoversClass(\tool_mulib\hook\profilefield_handlers::class)]
#[CoversClass(\tool_mulib\task\profilefield_populate::class)]
#[CoversClass(\tool_mulib\task\profilefield_cron::class)]
#[Group('MuTMS')]
final class profilefield_cache_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns cached values of user.
     *
     * @param int $userid
     * @param int $fieldid
     * @return array|null [charvalue, intvalue]
     */
    private function get_cached(int $userid, int $fieldid): ?array {
        global $DB;
        $record = $DB->get_record('tool_mulib_profilefield', ['userid' => $userid, 'fieldid' => $fieldid]);
        if (!$record) {
            return null;
        }
        return [$record->charvalue, $record->intvalue === null ? null : (int)$record->intvalue];
    }

    public function test_handlers(): void {
        $generator = $this->getDataGenerator();
        $text = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept']);
        $textarea = $generator->create_custom_profile_field(['datatype' => 'textarea', 'shortname' => 'notes', 'name' => 'Notes']);

        $this->assertSame(['text', 'menu', 'checkbox', 'datetime'], array_keys(profilefield_cache::get_handler_classes()));
        $this->assertInstanceOf(\tool_mulib\local\profilefield\text::class, profilefield_cache::get_handler($text));
        $this->assertNull(profilefield_cache::get_handler($textarea));
        $this->assertSame([(int)$text->id], array_keys(profilefield_cache::get_supported_fields()));
        $this->assertSame('Dept', profilefield_cache::get_handler($text)->get_field_name());

        $this->redirectHook(\tool_mulib\hook\profilefield_handlers::class, function ($hook) {
            $hook->unregister('text');
            $hook->register('textarea', \tool_mulib\local\profilefield\text::class);
        });
        $this->assertNull(profilefield_cache::get_handler($text));
        $this->assertNotNull(profilefield_cache::get_handler($textarea));
    }

    public function test_populate_and_update(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $text = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept', 'defaultdata' => 'None']);
        $menu = $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'level', 'name' => 'Level', 'param1' => "Junior\nSenior", 'defaultdata' => 'Junior']);
        $checkbox = $generator->create_custom_profile_field(['datatype' => 'checkbox', 'shortname' => 'vip', 'name' => 'VIP', 'defaultdata' => 0]);
        $datetime = $generator->create_custom_profile_field(['datatype' => 'datetime', 'shortname' => 'hired', 'name' => 'Hired', 'param1' => 2000, 'param2' => 2050]);
        $textarea = $generator->create_custom_profile_field(['datatype' => 'textarea', 'shortname' => 'notes', 'name' => 'Notes']);

        $user1 = $generator->create_user(['profile_field_dept' => ' Sales ', 'profile_field_level' => 'Senior', 'profile_field_vip' => 1, 'profile_field_hired' => 1767225600]);
        $user2 = $generator->create_user();

        $this->assertSame([], profilefield_cache::get_fields());
        $this->assertFalse(profilefield_cache::is_ready((int)$text->id));
        profilefield_cache::populate_field((int)$text->id, new \null_progress_trace());
        $this->assertSame([], profilefield_cache::get_fields());
        $this->assertSame(0, $DB->count_records('tool_mulib_profilefield', []));

        $this->assertFalse(profilefield_cache::add_field((int)$textarea->id));
        $this->assertFalse(profilefield_cache::add_field(-1));
        profilefield_cache::add_supported_fields();
        $fieldids = [(int)$text->id, (int)$menu->id, (int)$checkbox->id, (int)$datetime->id];
        $this->assertSame(array_fill_keys($fieldids, 0), profilefield_cache::get_fields());
        $this->assertFalse(profilefield_cache::is_ready((int)$text->id));
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(\tool_mulib\task\profilefield_populate::class));
        $this->assertTrue(profilefield_cache::add_field((int)$text->id));
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(\tool_mulib\task\profilefield_populate::class));

        profilefield_cache::populate_pending_fields(new \null_progress_trace());
        $this->assertSame($fieldids, array_keys(array_filter(profilefield_cache::get_fields())));
        $this->assertTrue(profilefield_cache::is_ready((int)$text->id));
        set_config('profilefieldlifetime', HOURSECS, 'tool_mulib');
        $this->assertFalse(profilefield_cache::is_outdated((int)$text->id));
        set_config('profilefieldlifetime', 0, 'tool_mulib');
        $this->assertTrue(profilefield_cache::is_outdated((int)$text->id));
        $this->assertTrue(profilefield_cache::is_outdated(-1));

        $this->assertSame(['Sales', null], $this->get_cached((int)$user1->id, (int)$text->id));
        $this->assertSame(['Senior', null], $this->get_cached((int)$user1->id, (int)$menu->id));
        $this->assertSame([null, 1], $this->get_cached((int)$user1->id, (int)$checkbox->id));
        $this->assertSame([null, 1767225600], $this->get_cached((int)$user1->id, (int)$datetime->id));
        $this->assertSame(['None', null], $this->get_cached((int)$user2->id, (int)$text->id));
        $this->assertSame(['Junior', null], $this->get_cached((int)$user2->id, (int)$menu->id));
        $this->assertSame([null, 0], $this->get_cached((int)$user2->id, (int)$checkbox->id));
        $this->assertSame([null, 0], $this->get_cached((int)$user2->id, (int)$datetime->id));
        $this->assertNull($this->get_cached((int)$user1->id, (int)$textarea->id));

        $user3 = $generator->create_user(['profile_field_dept' => 'HR']);
        $this->assertSame(['HR', null], $this->get_cached((int)$user3->id, (int)$text->id));
        $this->assertSame(['Junior', null], $this->get_cached((int)$user3->id, (int)$menu->id));
        $DB->set_field('user_info_data', 'data', 'IT', ['userid' => $user3->id, 'fieldid' => $text->id]);
        $DB->set_field('user_info_data', 'data', 'Senior', ['userid' => $user1->id, 'fieldid' => $menu->id]);
        profilefield_cache::update_user((int)$user3->id, (int)$text->id);
        $this->assertSame(['IT', null], $this->get_cached((int)$user3->id, (int)$text->id));
        $DB->set_field('user_info_data', 'data', 'Support', ['userid' => $user3->id, 'fieldid' => $text->id]);
        profilefield_cache::update_user((int)$user3->id);
        $this->assertSame(['Support', null], $this->get_cached((int)$user3->id, (int)$text->id));

        $DB->set_field('user_info_data', 'data', 'Marketing', ['userid' => $user3->id, 'fieldid' => $text->id]);
        \core\event\user_updated::create_from_userid($user3->id)->trigger();
        $this->assertSame(['Marketing', null], $this->get_cached((int)$user3->id, (int)$text->id));

        $DB->set_field('user_info_field', 'defaultdata', 'Nobody', ['id' => $text->id]);
        $user4 = $generator->create_user();
        $this->assertSame(['Nobody', null], $this->get_cached((int)$user4->id, (int)$text->id));
        delete_user($user1);
        $this->assertNull($this->get_cached((int)$user1->id, (int)$text->id));
        profilefield_cache::populate_field((int)$text->id, new \null_progress_trace());
        $this->assertSame(['Nobody', null], $this->get_cached((int)$user2->id, (int)$text->id));

        profilefield_cache::remove_field((int)$menu->id);
        $this->assertArrayNotHasKey((int)$menu->id, profilefield_cache::get_fields());
        $this->assertSame(0, $DB->count_records('tool_mulib_profilefield', ['fieldid' => $menu->id]));
        profilefield_cache::populate_field((int)$menu->id, new \null_progress_trace());
        $this->assertSame(0, $DB->count_records('tool_mulib_profilefield', ['fieldid' => $menu->id]));
    }

    public function test_references(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $field1 = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept']);
        $field2 = $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'level', 'name' => 'Level', 'param1' => "Junior\nSenior"]);
        $field3 = $generator->create_custom_profile_field(['datatype' => 'checkbox', 'shortname' => 'vip', 'name' => 'VIP']);
        $this->assertSame([], profilefield_cache::get_required_fieldids());

        profilefield_cache::set_references('tool_mucohort', 'rules', 10, [(int)$field1->id, (int)$field2->id, (int)$field2->id, -1]);
        profilefield_cache::set_references('tool_mucohort', 'rules', 11, [(int)$field2->id]);
        $this->assertSame([(int)$field1->id, (int)$field2->id], profilefield_cache::get_required_fieldids());
        $this->assertSame([(int)$field1->id => 0, (int)$field2->id => 0], profilefield_cache::get_fields());
        $this->assertCount(2, profilefield_cache::get_references((int)$field2->id));
        $this->assertSame(3, $DB->count_records('tool_mulib_profilefield_reference', []));

        try {
            profilefield_cache::remove_field((int)$field1->id);
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $ex) {
            $this->assertStringContainsString('Required profile field cannot be removed from cache', $ex->getMessage());
        }

        profilefield_cache::set_references('tool_mucohort', 'rules', 10, [(int)$field2->id]);
        $this->assertSame([(int)$field2->id], profilefield_cache::get_required_fieldids());
        profilefield_cache::remove_field((int)$field1->id);
        $this->assertSame([(int)$field2->id => 0], profilefield_cache::get_fields());

        set_config('showuseridentity', 'email,profile_field_vip,profile_field_unknown');
        $this->assertSame([(int)$field3->id], profilefield_cache::get_identity_fieldids());
        $this->assertSame([(int)$field2->id, (int)$field3->id], profilefield_cache::get_required_fieldids());

        profilefield_cache::set_references('tool_mucohort', 'rules', 10, []);
        profilefield_cache::set_references('tool_mucohort', 'rules', 11, []);
        $this->assertSame([(int)$field3->id], profilefield_cache::get_required_fieldids());
        $this->assertSame(0, $DB->count_records('tool_mulib_profilefield_reference', []));

        try {
            profilefield_cache::set_references('tool mucohort', 'rules', 10, []);
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $ex) {
            $this->assertStringContainsString('Invalid component', $ex->getMessage());
        }
        try {
            profilefield_cache::set_references('tool_mucohort', '', 10, []);
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $ex) {
            $this->assertStringContainsString('Invalid area', $ex->getMessage());
        }
    }

    public function test_field_events(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $field1 = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept']);
        $field2 = $generator->create_custom_profile_field(['datatype' => 'textarea', 'shortname' => 'notes', 'name' => 'Notes']);

        \core\event\user_info_field_created::create_from_field($field1)->trigger();
        \core\event\user_info_field_created::create_from_field($field2)->trigger();
        $this->assertSame([(int)$field1->id => 0], profilefield_cache::get_fields());
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(\tool_mulib\task\profilefield_populate::class));
        ob_start();
        $this->runAdhocTasks(\tool_mulib\task\profilefield_populate::class);
        ob_end_clean();
        $this->assertTrue(profilefield_cache::is_ready((int)$field1->id));
        $this->assertSame(['', null], $this->get_cached((int)$user->id, (int)$field1->id));

        $DB->set_field('user_info_field', 'defaultdata', 'Sales', ['id' => $field1->id]);
        \core\event\user_info_field_updated::create_from_field($field1)->trigger();
        \core\event\user_info_field_updated::create_from_field($field2)->trigger();
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(\tool_mulib\task\profilefield_populate::class));
        $this->assertTrue(profilefield_cache::is_ready((int)$field1->id));
        $this->assertSame(['', null], $this->get_cached((int)$user->id, (int)$field1->id));
        ob_start();
        $this->runAdhocTasks(\tool_mulib\task\profilefield_populate::class);
        ob_end_clean();
        $this->assertSame(['Sales', null], $this->get_cached((int)$user->id, (int)$field1->id));

        profilefield_cache::set_references('tool_mucohort', 'rules', 10, [(int)$field1->id]);
        profile_delete_field($field1->id);
        $this->assertSame([], profilefield_cache::get_fields());
        $this->assertSame(0, $DB->count_records('tool_mulib_profilefield', []));
        $this->assertSame(0, $DB->count_records('tool_mulib_profilefield_reference', []));
    }

    public function test_cron(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $field1 = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Dept']);
        $field2 = $generator->create_custom_profile_field(['datatype' => 'checkbox', 'shortname' => 'vip', 'name' => 'VIP']);
        $field3 = $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'level', 'name' => 'Level', 'param1' => "Junior\nSenior"]);

        set_config('showuseridentity', 'email,profile_field_vip');
        $reference = ['component' => 'tool_mucohort', 'area' => 'rules', 'itemid' => 1, 'timecreated' => time()];
        $DB->insert_record('tool_mulib_profilefield_reference', ['fieldid' => $field3->id] + $reference);
        $DB->insert_record('tool_mulib_profilefield_reference', ['fieldid' => -1] + $reference);
        $DB->insert_record('tool_mulib_profilefield', ['userid' => $user->id, 'fieldid' => $field1->id, 'charvalue' => 'x']);

        ob_start();
        (new \tool_mulib\task\profilefield_cron())->execute();
        ob_end_clean();

        $fields = profilefield_cache::get_fields();
        $this->assertSame([(int)$field2->id, (int)$field3->id], array_keys($fields));
        $this->assertTrue(profilefield_cache::is_ready((int)$field2->id));
        $this->assertTrue(profilefield_cache::is_ready((int)$field3->id));
        $this->assertSame([null, 0], $this->get_cached((int)$user->id, (int)$field2->id));
        $this->assertNull($this->get_cached((int)$user->id, (int)$field1->id));
        $references = $DB->get_records('tool_mulib_profilefield_reference', [], 'fieldid ASC', 'fieldid, component, area, itemid');
        $this->assertSame([(int)$field2->id, (int)$field3->id], array_map('intval', array_keys($references)));
        $this->assertSame('core', $references[$field2->id]->component);
        $this->assertSame('showuseridentity', $references[$field2->id]->area);

        set_config('showuseridentity', 'email');
        ob_start();
        (new \tool_mulib\task\profilefield_cron())->execute();
        ob_end_clean();
        $this->assertSame([(int)$field3->id], profilefield_cache::get_required_fieldids());
        profilefield_cache::remove_field((int)$field2->id);
        $this->assertSame([(int)$field3->id], array_keys(profilefield_cache::get_fields()));
    }
}
