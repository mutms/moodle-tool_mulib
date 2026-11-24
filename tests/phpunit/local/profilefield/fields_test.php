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

namespace tool_mulib\phpunit\local\profilefield;

use tool_mulib\local\profilefield\fields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Profile fields tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(fields::class)]
#[CoversClass(\tool_mulib\local\profilefield_cache::class)]
#[Group('MuTMS')]
final class fields_test extends \advanced_testcase {
    public function test_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $text = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'a', 'name' => 'A']);
        $textarea = $generator->create_custom_profile_field(['datatype' => 'textarea', 'shortname' => 'b', 'name' => 'B']);

        $fields = \core\di::get(fields::class);
        $this->assertSame([(int)$text->id, (int)$textarea->id], array_keys($fields->get_fields()));
        $this->assertSame('b', $fields->get_field((int)$textarea->id)->shortname);
        $this->assertNull($fields->get_field(-1));
        $this->assertSame([(int)$text->id], array_keys($fields->get_handlers()));
        $this->assertSame('a', $fields->get_handlers()[$text->id]->get_field()->shortname);
        $this->assertSame((int)$text->id, (int)$fields->get_handler_by_shortname('a')->get_field()->id);
        $this->assertNull($fields->get_handler_by_shortname('b'));
        $this->assertNull($fields->get_handler_by_shortname('x'));

        $fields->get_field((int)$text->id)->shortname = 'x';
        $this->assertSame('a', $fields->get_field((int)$text->id)->shortname);

        $field = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'c', 'name' => 'C']);
        $this->assertCount(2, \core\di::get(fields::class)->get_fields());
        \core\event\user_info_field_created::create_from_field($field)->trigger();
        $this->assertCount(3, \core\di::get(fields::class)->get_fields());

        $DB->set_field('user_info_field', 'name', 'Changed', ['id' => $field->id]);
        \core\event\user_info_field_updated::create_from_field($DB->get_record('user_info_field', ['id' => $field->id]))->trigger();
        $this->assertSame('Changed', \core\di::get(fields::class)->get_field((int)$field->id)->name);

        $DB->delete_records('user_info_field', ['id' => $field->id]);
        \core\event\user_info_field_deleted::create_from_field($field)->trigger();
        $this->assertCount(2, \core\di::get(fields::class)->get_fields());
    }
}
