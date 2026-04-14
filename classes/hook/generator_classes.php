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

namespace tool_mulib\hook;

use tool_mulib\local\generator\base;

/**
 * Hook for registering external generator classes.
 *
 * Plugins register their generator classes via callbacks in db/hooks.php.
 * Built-in generators (core_course, mod_*) are hardcoded in the manager
 * and do not need to use this hook.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Generator classes for production data creation')]
#[\core\attribute\tags('tool_mulib')]
final class generator_classes {

    /** @var array<string, class-string<base>> component => generator class */
    private array $classes = [];

    /**
     * Constructor — dispatches itself to collect registrations from plugins.
     */
    public function __construct() {
        \core\di::get(\core\hook\manager::class)->dispatch($this);
    }

    /**
     * Register a generator class for a component.
     *
     * @param string $component e.g. 'tool_muprog', 'tool_mucertify'
     * @param class-string<base> $classname generator class extending base
     */
    public function register(string $component, string $classname): void {
        if (isset($this->classes[$component])) {
            debugging("Generator for '$component' is already registered");
            return;
        }
        if (!is_subclass_of($classname, base::class)) {
            debugging("Class '$classname' for '$component' is not a valid generator");
            return;
        }
        $this->classes[$component] = $classname;
    }

    /**
     * Returns all registered generator classes.
     *
     * @return array<string, class-string<base>>
     */
    public function get_classes(): array {
        return $this->classes;
    }
}
