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

namespace tool_mulib\phpunit\muform\userpicker;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fake_user_source.php');

use tool_mulib\muform\userpicker\base;
use tool_mulib\muform\userpicker\site_users;
use tool_mulib\phpunit\muform\fixtures\fake_user_source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Users picker family tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(base::class)]
#[CoversClass(site_users::class)]
#[Group('MuTMS')]
final class base_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_search(): void {
        global $CFG;
        $this->setAdminUser();
        set_config('showuseridentity', 'email,idnumber');
        $generator = $this->getDataGenerator();
        $users = [];
        for ($i = 1; $i <= 55; $i++) {
            $users[$i] = $generator->create_user([
                'firstname' => 'User', 'lastname' => sprintf('Many%02d', $i), 'email' => "many$i@example.com", 'idnumber' => "ID$i",
            ]);
        }
        $hidden = $generator->create_user(['firstname' => 'User', 'lastname' => 'Hidden']);
        $suspended = $generator->create_user(['firstname' => 'User', 'lastname' => 'Many99', 'suspended' => 1]);
        $source = new fake_user_source();

        $this->assertSame([
            ['name' => 'email', 'label' => 'Email address'],
            ['name' => 'idnumber', 'label' => 'ID number'],
        ], $source->get_columns());

        $result = $source->search('Many', [], 0);
        $this->assertSame(55, $result['total']);
        $this->assertSame(0, $result['page']);
        $this->assertSame(base::PERPAGE, $result['perpage']);
        $this->assertCount(50, $result['rows']);
        $this->assertSame((int)$users[1]->id, $result['rows'][0]['id']);
        $this->assertSame(['email' => 'many1@example.com', 'idnumber' => 'ID1'], $result['rows'][0]['fields']);
        $this->assertSame('User Many01', $result['rows'][0]['fullname']);
        $this->assertStringContainsString('many1@example.com', $result['rows'][0]['label']);

        $result = $source->search('Many', [(string)$users[51]->id], 1);
        $this->assertSame(54, $result['total']);
        $this->assertSame(1, $result['page']);
        $expected = [(int)$users[52]->id, (int)$users[53]->id, (int)$users[54]->id, (int)$users[55]->id];
        $this->assertSame($expected, array_column($result['rows'], 'id'));
        // Pages out of range show the last page.
        $this->assertSame(1, $source->search('Many', [], 9)['page']);

        // Identity fields are searched.
        $result = $source->search('many7@', [], 0);
        $this->assertSame([(int)$users[7]->id], array_column($result['rows'], 'id'));
        $this->assertSame(0, $source->search('Hidden', [], 0)['total']);
        $this->assertSame(0, $source->search('Many99', [], 0)['total']);
        // Admin and the 55 users, not guest, hidden or suspended users.
        $result = $source->search('', [], 1);
        $this->assertSame(56, $result['total']);
        $ids = array_merge(array_column($source->search('', [], 0)['rows'], 'id'), array_column($result['rows'], 'id'));
        $this->assertNotContains((int)$CFG->siteguest, $ids);
        $this->assertNotContains((int)$hidden->id, $ids);
        $this->assertNotContains((int)$suspended->id, $ids);

        // Without the capability identity fields are not shown.
        $this->setUser($generator->create_user());
        $this->assertSame([], $source->get_columns());
        $this->assertSame([], $source->search('many7@', [], 0)['rows']);
    }

    public function test_labels_and_validate(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Visible']);
        $hidden = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Hidden']);
        $kept = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Kept']);
        $suspended = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Suspended', 'suspended' => 1]);
        $source = new fake_user_source();

        $labels = $source->labels([(string)$user->id, (string)$hidden->id, (string)$kept->id, '0', 'x', (string)$suspended->id]);
        $this->assertSame([(string)$user->id, (string)$kept->id, (string)$suspended->id], array_map('strval', array_keys($labels)));
        $this->assertStringContainsString('Anna Visible', $labels[$user->id]);
        $this->assertSame(0, $source->search('Kept', [], 0)['total']);
        $errors = $source->validate([(string)$user->id, (string)$suspended->id]);
        $this->assertSame([(string)$suspended->id => 'Suspended user'], $errors);

        $panel = $source->export_panel(true);
        $this->assertSame('tool_mulib/muform/userpicker/panel', $panel['panel']);
        $this->assertSame('Select users', $panel['strings']['title']);
        $this->assertSame('Select user', $source->export_panel(false)['strings']['title']);
    }

    public function test_site_users(): void {
        $syscontext = \core\context\system::instance();
        $this->setAdminUser();
        $source = new site_users($syscontext->id);
        $this->assertSame([$syscontext->id], $source->get_args());
        $this->assertInstanceOf(site_users::class, base::instantiate(site_users::class, [$syscontext->id]));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\core\exception\required_capability_exception::class);
        new site_users($syscontext->id);
    }
}
