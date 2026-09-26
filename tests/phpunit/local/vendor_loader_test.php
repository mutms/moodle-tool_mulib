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

use tool_mulib\local\vendor_loader;

/**
 * Plugin vendor class loader tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\vendor_loader
 */
final class vendor_loader_test extends \advanced_testcase {
    public function test_register(): void {
        global $CFG;

        $rootbefore = \Composer\InstalledVersions::getRootPackage();
        $loadersbefore = array_keys(\Composer\Autoload\ClassLoader::getRegisteredLoaders());

        vendor_loader::register($CFG->dirroot . '/admin/tool/mulib/vendor');
        // Repeated registration does nothing.
        $count = count(spl_autoload_functions());
        vendor_loader::register($CFG->dirroot . '/admin/tool/mulib/classes/../vendor');
        $this->assertCount($count, spl_autoload_functions());

        $this->assertTrue(class_exists(\Opis\JsonSchema\Validator::class));
        $this->assertTrue(class_exists(\Opis\String\UnicodeString::class));

        // Composer runtime state stays with Moodle.
        $this->assertSame($rootbefore, \Composer\InstalledVersions::getRootPackage());
        $this->assertSame('moodle/moodle', \Composer\InstalledVersions::getRootPackage()['name']);
        $this->assertSame($loadersbefore, array_keys(\Composer\Autoload\ClassLoader::getRegisteredLoaders()));

        // The loader is appended after all other loaders, Moodle wins for shared packages.
        $functions = spl_autoload_functions();
        $this->assertInstanceOf(\Closure::class, end($functions));

        [$valid, $errors] = \tool_mulib\local\json_schema::validate((object)['a' => 1], (object)['type' => 'object']);
        $this->assertTrue($valid);
        $this->assertSame([], $errors);
    }

    public function test_register_invalid(): void {
        global $CFG;
        $this->expectException(\core\exception\coding_exception::class);
        vendor_loader::register($CFG->dirroot . '/admin/tool/mulib/classes');
    }
}
