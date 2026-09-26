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

namespace tool_mulib\phpunit\muform\util\autocomplete;

use core\context\system;
use tool_mulib\local\sql;
use tool_mulib\muform\autocomplete\site_user;
use tool_mulib\muform\autocompletemany\site_users;

/**
 * User trait tests through the shipped site user sources.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\util\autocomplete\user_trait
 * @covers \tool_mulib\muform\autocomplete\site_user
 * @covers \tool_mulib\muform\autocompletemany\site_users
 */
final class user_trait_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_search(): void {
        $generator = $this->getDataGenerator();
        $u1 = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Zeta', 'email' => 'anna@example.com']);
        $u2 = $generator->create_user(['firstname' => 'Bert', 'lastname' => 'Zeta']);
        $u3 = $generator->create_user(['firstname' => 'Cara', 'lastname' => 'Zeta', 'deleted' => 1]);
        $u4 = $generator->create_user(['firstname' => 'Dan', 'lastname' => 'Zeta', 'confirmed' => 0]);
        $u5 = $generator->create_user(['firstname' => 'Eve', 'lastname' => 'Zeta', 'suspended' => 1]);
        $syscontext = system::instance();

        $source = new site_users($syscontext->id);
        $this->assertSame([$syscontext->id], $source->get_args());

        $result = $source->search('Zeta', 50, []);
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id, $u5->id], array_keys($result));
        $this->assertStringContainsString('Anna Zeta', $result[$u1->id]);

        $result = $source->search('Zeta', 50, [(string)$u2->id]);
        $this->assertEqualsCanonicalizing([$u1->id, $u5->id], array_keys($result));

        $this->assertNull($source->search('Zeta', 2, []));
        $this->assertSame([(int)$u1->id], array_keys($source->search('anna@', 50, [])));
        $this->assertArrayHasKey($u1->id, $source->search('', 50, []));

        $labels = $source->labels([(string)$u1->id, (string)$u3->id, (string)$u4->id, 'x', '999999']);
        $this->assertSame([(int)$u1->id], array_keys($labels));
        $this->assertSame([$u5->id => 'Suspended user'], $source->validate([(string)$u1->id, (string)$u5->id]));

        $single = new site_user($syscontext->id);
        $this->assertStringContainsString('Bert Zeta', $single->label((string)$u2->id));
        $this->assertNull($single->label((string)$u3->id));
        $this->assertNull($single->validate((string)$u1->id));
        $this->assertSame('Suspended user', $single->validate((string)$u5->id));
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id, $u5->id], array_keys($single->search('Zeta', 50)));
    }

    public function test_extra_where(): void {
        $generator = $this->getDataGenerator();
        $u1 = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Zeta']);
        $u2 = $generator->create_user(['firstname' => 'Bert', 'lastname' => 'Zeta']);
        $source = new class (system::instance()->id, $u2->id) extends \tool_mulib\muform\autocompletemany\base {
            use \tool_mulib\muform\util\autocomplete\user_trait;

            /**
             * Constructor.
             *
             * @param int $contextid
             * @param int $onlyid
             */
            public function __construct(
                /** @var int context id */
                private int $contextid,
                /** @var int the only user allowed */
                private int $onlyid,
            ) {
            }

            #[\Override]
            public function get_args(): array {
                return [$this->contextid, $this->onlyid];
            }

            #[\Override]
            public function search(string $query, int $maxitems, array $exclude): ?array {
                $where = new sql('u.id = :onlyid', ['onlyid' => $this->onlyid]);
                return $this->search_users(system::instance(), $query, $maxitems, $exclude, $where);
            }

            #[\Override]
            public function labels(array $values): array {
                $where = new sql('u.id = :onlyid', ['onlyid' => $this->onlyid]);
                return $this->user_labels(system::instance(), $values, $where);
            }
        };
        $this->assertSame([(int)$u2->id], array_keys($source->search('Zeta', 50, [])));
        $this->assertSame([(int)$u2->id], array_keys($source->labels([(string)$u1->id, (string)$u2->id])));
    }

    public function test_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\core\exception\required_capability_exception::class);
        new site_users(system::instance()->id);
    }
}
