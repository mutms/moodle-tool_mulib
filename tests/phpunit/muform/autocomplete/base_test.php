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

namespace tool_mulib\phpunit\muform\autocomplete;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fake_source.php');
require_once(__DIR__ . '/../fixtures/fake_sources.php');

use tool_mulib\muform\autocomplete\base;
use tool_mulib\phpunit\muform\fixtures\fake_source;
use tool_mulib\phpunit\muform\fixtures\fake_sources;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Autocomplete source base tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(base::class)]
#[Group('MuTMS')]
final class base_test extends \advanced_testcase {
    public function test_value_hooks(): void {
        $source = new fake_source('b');
        $this->assertSame(['a' => '<b>Alpha</b>', 'c' => 'Gamma'], $source->labels(['a', 'x', 'c']));
        $this->assertSame([], $source->labels([]));
        $this->assertSame(['b' => 'Not allowed'], $source->validate(['a', 'b', 'c']));
        $this->assertSame([], $source->validate(['a']));
        $this->assertSame(50, $source->get_maxitems());
    }

    public function test_set_methods(): void {
        $source = new fake_sources('b');
        $this->assertSame(['a' => '<b>Alpha</b>', 'c' => 'Gamma'], $source->labels(['a', 'x', 'c']));
        $this->assertSame(['b' => 'Not allowed'], $source->validate(['a', 'b']));
        $this->assertSame(['c' => 'Gamma'], $source->search('', 50, ['a', 'b']));
    }

    public function test_missing_labels(): void {
        $source = new class extends base {
            #[\Override]
            public function get_args(): array {
                return [];
            }

            #[\Override]
            public function search(string $query, int $maxitems, array $exclude): ?array {
                return [];
            }
        };
        $this->assertSame([], $source->validate(['a']));
        $this->expectException(\core\exception\coding_exception::class);
        $source->labels(['a']);
    }
}
