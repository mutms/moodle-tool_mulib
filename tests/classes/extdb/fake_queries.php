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

namespace tool_mulib\tests\extdb;

use tool_mulib\hook\extdb_query_classes;
use tool_mulib\local\extdb\query_manager;

/**
 * Registration of fake query types in tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fake_queries {
    /**
     * Replace all query types with tool_mulib/alpha and tool_mulib/beta.
     *
     * Tests must not depend on other MuTMS plugins, CI installs tool_mulib alone.
     */
    public static function register(): void {
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(
            extdb_query_classes::class,
            function (extdb_query_classes $hook): void {
                $hook->register('tool_mulib', 'alpha', fake_query_alpha::class);
                $hook->register('tool_mulib', 'beta', fake_query_beta::class);
            }
        );
        // The manager collects the types only once.
        \core\di::set(query_manager::class, new query_manager());
    }
}
