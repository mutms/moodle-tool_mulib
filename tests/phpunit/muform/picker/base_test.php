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

namespace tool_mulib\phpunit\muform\picker;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fake_picker_source.php');
require_once(__DIR__ . '/../fixtures/fake_source.php');

use core\exception\invalid_parameter_exception;
use tool_mulib\muform\picker\base;
use tool_mulib\phpunit\muform\fixtures\fake_picker_family;
use tool_mulib\phpunit\muform\fixtures\fake_picker_source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Picker source base tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(base::class)]
#[Group('MuTMS')]
final class base_test extends \advanced_testcase {
    public function test_export_panel(): void {
        $source = new fake_picker_source('b');
        $this->assertSame([
            'panel' => 'tool_mulib/fake/panel',
            'source' => ['class' => fake_picker_source::class, 'args' => ['b']],
            'strings' => ['title' => 'Pick many', 'close' => 'Close'],
        ], $source->export_panel(true));
        $this->assertSame('Pick one', $source->export_panel(false)['strings']['title']);
    }

    public function test_validate_value(): void {
        $source = new class extends fake_picker_family {
            #[\Override]
            public function get_args(): array {
                return [];
            }

            #[\Override]
            public function labels(array $values): array {
                return array_combine($values, $values);
            }

            #[\Override]
            protected function validate_value(string $value): ?string {
                return ($value === 'x') ? 'No x' : null;
            }
        };
        $this->assertSame(['x' => 'No x'], $source->validate(['a', 'x']));
        $this->assertSame([], $source->validate(['a']));
    }

    public function test_instantiate(): void {
        $source = fake_picker_family::instantiate(fake_picker_source::class, ['b']);
        $this->assertInstanceOf(fake_picker_source::class, $source);
        $this->assertSame(['b'], $source->get_args());

        $bad = [
            // Not a subclass of the family.
            \tool_mulib\phpunit\muform\fixtures\fake_source::class,
            // Abstract family base.
            fake_picker_family::class,
            // Wrong case or other namespaces.
            'tool_mulib\\phpunit\\muform\\fixtures\\Fake_picker_source',
            'tool_mulib\\phpunit\\muform\\fixtures\\sub\\fake_picker_source',
            'tool_mulib\\phpunit\\muform\\fake_picker_source',
            '\\' . fake_picker_source::class,
            'tool_mulib\\phpunit\\muform\\fixtures\\missing',
            // Same relative namespace in a missing component.
            'tool_missingplugin\\phpunit\\muform\\fixtures\\fake_picker_source',
            '',
        ];
        foreach ($bad as $class) {
            try {
                fake_picker_family::instantiate($class, []);
                $this->fail('Exception expected: ' . $class);
            } catch (invalid_parameter_exception $e) {
                $this->assertStringContainsString('Invalid picker source', $e->getMessage());
            }
        }
        try {
            fake_picker_family::instantiate(fake_picker_source::class, [['b']]);
            $this->fail('Exception expected');
        } catch (invalid_parameter_exception $e) {
            $this->assertStringContainsString('Invalid picker source arguments', $e->getMessage());
        }
        // Sources of a family are not sources of the generic base namespace.
        try {
            base::instantiate(fake_picker_source::class, []);
            $this->fail('Exception expected');
        } catch (invalid_parameter_exception $e) {
            $this->assertStringContainsString('Invalid picker source', $e->getMessage());
        }
    }

    public function test_require_browser_session(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        base::require_browser_session();

        $this->setGuestUser();
        $this->expectException(\core\exception\moodle_exception::class);
        base::require_browser_session();
    }
}
