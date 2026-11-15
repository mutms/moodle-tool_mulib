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

namespace tool_mulib\tests\muform\picker;

use behat_base;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;

/**
 * Behat helper of one picker panel family, used by picker and pickermany element helpers
 * and by steps working with panels opened elsewhere.
 *
 * Panel module "<component>/<path>" is served by class <component>\tests\muform\picker\<path with _>
 * in <component>/tests/classes/muform/picker/<path with _>.php, e.g. module "tool_x/userpicker/panel"
 * by tool_x\tests\muform\picker\userpicker_panel.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class panel_base {
    /** @var string CSS selector of the open panel dialog */
    public const string PANEL = 'dialog.tool_mulib-picker-panel[open]';

    /**
     * Constructor.
     *
     * @param behat_base $context
     * @param NodeElement $panel open panel dialog
     * @param string $what description for errors, e.g. muform element name
     */
    final public function __construct(
        /** @var behat_base $context */
        protected readonly behat_base $context,
        /** @var NodeElement $panel open panel dialog */
        protected readonly NodeElement $panel,
        /** @var string $what description for errors */
        protected readonly string $what,
    ) {
    }

    /**
     * Is the panel content loaded?
     *
     * @param NodeElement $panel
     * @return bool
     */
    abstract public static function is_ready(NodeElement $panel): bool;

    /**
     * Type into the search field of the panel.
     *
     * @param string $text
     */
    abstract public function search(string $text): void;

    /**
     * Pick one item described by a value of the step table - one value picks and closes the panel,
     * several values are chosen for confirm().
     *
     * @param string $item
     */
    abstract public function pick(string $item): void;

    /**
     * Add the chosen items in a panel of several values.
     */
    abstract public function confirm(): void;

    /**
     * Does the plain text of a pill label match a value of the step table?
     *
     * @param string $label pill text with normalised white space
     * @param string $item
     * @return bool
     */
    public static function label_matches(string $label, string $item): bool {
        return $label === $item;
    }

    /**
     * Helper class of a panel module.
     *
     * @param string $module panel module name, e.g. "tool_x/userpicker/panel"
     * @return string class name, the file is loaded
     */
    final public static function get_class(string $module): string {
        if (!preg_match('/^([a-z][a-z0-9_]*)\/([a-z][a-z0-9_\/]*)$/D', $module, $matches)) {
            throw new \coding_exception('Invalid picker panel module: ' . $module);
        }
        $name = str_replace('/', '_', $matches[2]);
        $class = $matches[1] . '\\tests\\muform\\picker\\' . $name;
        if (!class_exists($class)) {
            $file = \core_component::get_component_directory($matches[1]) . '/tests/classes/muform/picker/' . $name . '.php';
            if (!file_exists($file)) {
                throw new \coding_exception('Picker panel ' . $module . ' has no Behat helper ' . $file);
            }
            require_once($file);
        }
        return $class;
    }

    /**
     * Wait for the open panel of a module and create its helper.
     *
     * @param behat_base $context
     * @param string $module panel module name
     * @param string $what description for errors
     * @return static
     */
    final public static function wait_for_panel(behat_base $context, string $module, string $what): static {
        /** @var static $class */
        $class = self::get_class($module);
        $session = $context->getSession();
        $deadline = microtime(true) + 6;
        while (microtime(true) < $deadline) {
            $panel = $session->getPage()->find('css', self::PANEL);
            if ($panel && $class::is_ready($panel)) {
                return new $class($context, $panel, $what);
            }
            usleep(100000);
        }
        throw new ExpectationException('Panel of ' . $what . ' did not open', $session);
    }
}
