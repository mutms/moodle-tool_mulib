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

namespace tool_mulib\phpunit\muform\autocomplete;

use tool_mulib\muform\autocomplete\member_cohorts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests of the source of cohorts whose members may be listed.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(member_cohorts::class)]
#[Group('MuTMS')]
final class member_cohorts_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_source(): void {
        $syscontext = \core\context\system::instance();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \core\context\coursecat::instance($category->id);
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Alpha <b>one</b>', 'idnumber' => 'A1']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Beta two', 'contextid' => $catcontext->id]);
        $cohort3 = $this->getDataGenerator()->create_cohort(['name' => 'Beta three', 'contextid' => $catcontext->id]);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $catcontext);
        role_assign($roleid, $user->id, $catcontext);

        $this->setAdminUser();
        $source = new member_cohorts($syscontext->id);
        $this->assertSame([$syscontext->id], $source->get_args());
        $expected = [(string)$cohort1->id => 'Alpha one', (string)$cohort3->id => 'Beta three', (string)$cohort2->id => 'Beta two'];
        $this->assertSame($expected, $source->search('', 10, []));
        $this->assertSame([(string)$cohort1->id => 'Alpha one'], $source->search('a1', 10, []));
        $this->assertSame([(string)$cohort2->id => 'Beta two'], $source->search('beta', 10, [(string)$cohort3->id]));
        $this->assertNull($source->search('', 2, []));
        $this->assertSame([], $source->search('nothing', 10, []));
        $this->assertSame($expected, $source->labels([(string)$cohort2->id, (string)$cohort1->id, 'x', '0', (string)$cohort3->id]));
        $this->assertSame([], $source->labels([]));

        $this->setUser($user);
        $source = new member_cohorts($catcontext->id);
        $expected = [(string)$cohort3->id => 'Beta three', (string)$cohort2->id => 'Beta two'];
        $this->assertSame($expected, $source->search('', 10, []));
        $this->assertSame([], $source->search('alpha', 10, []));
        $this->assertSame([(string)$cohort2->id => 'Beta two'], $source->labels([(string)$cohort1->id, (string)$cohort2->id]));

        $this->setGuestUser();
        try {
            new member_cohorts($syscontext->id);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\require_login_exception::class, $ex);
        }
    }
}
