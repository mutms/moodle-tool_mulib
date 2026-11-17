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

namespace tool_mulib\muform\picker;

use core\exception\access_denied_exception;
use core\exception\coding_exception;
use core\exception\invalid_parameter_exception;
use core\exception\moodle_exception;

/**
 * Source of picker and pickermany elements.
 *
 * Pickers are families: an abstract subclass of this base, a panel component (React) opened over the page
 * and the family's own endpoints the panel talks to. A source owns access control and decides which values
 * may be selected; the constructor takes scalar arguments only and does the access control, because the same
 * class is instantiated in the form definition and again in the family endpoints from get_args().
 *
 * Values are opaque strings matching VALUE_REGEX, for example ids or "type:id" tokens. Labels are HTML,
 * plain text must be passed through s().
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var string allowed value characters, commas separate values in the browser */
    public const string VALUE_REGEX = '/^[a-zA-Z0-9_\-.:@]+$/D';

    /** @var string format of panel module names without the "@moodle/lms/" prefix */
    public const string PANEL_REGEX = '/^[a-z][a-z0-9_]*(\/[a-z][a-z0-9_]*)+$/D';

    /**
     * Constructor arguments as a list of scalars, used to reinstantiate the source in family endpoints.
     *
     * @return array
     */
    abstract public function get_args(): array;

    /**
     * Labels of values that may be the current value or may be picked, others are missing in the result.
     *
     * @param string[] $values
     * @return string[] value => label html, in the given order
     */
    abstract public function labels(array $values): array;

    /**
     * Why values are refused, called only for values known to labels().
     *
     * @param string[] $values
     * @return string[] value => error text
     */
    public function validate(array $values): array {
        $result = [];
        foreach ($values as $value) {
            $error = $this->validate_value((string)$value);
            if ($error !== null) {
                $result[(string)$value] = $error;
            }
        }
        return $result;
    }

    /**
     * May all the values be selected? Forms use it to check values again after something else changed,
     * for example with a source created for the newly selected context.
     *
     * @param string[] $values
     * @return bool
     */
    final public function are_allowed(array $values): bool {
        $values = array_map('strval', array_values($values));
        $labels = $this->labels($values);
        foreach ($values as $value) {
            if (!isset($labels[$value])) {
                return false;
            }
        }
        return !$this->validate($values);
    }

    /**
     * Why one value is refused, used by the default validate(); override validate() instead for checks of all values at once.
     *
     * @param string $value
     * @return string|null error text, null when it may be selected
     */
    protected function validate_value(string $value): ?string {
        return null;
    }

    /**
     * Panel module, the default export is a React component taking the picker panel props.
     *
     * @return string module name without "@moodle/lms/" prefix, e.g. "tool_mulib/muform/somepicker/panel"
     */
    abstract public function get_panel(): string;

    /**
     * Localised strings of the panel.
     *
     * @param bool $multiple
     * @return string[] must include "title", which is also the accessible name of the panel
     */
    abstract public function get_panel_strings(bool $multiple): array;

    /**
     * Panel description for the browser.
     *
     * @param bool $multiple
     * @return array with panel, source (class and args) and strings
     */
    final public function export_panel(bool $multiple): array {
        $panel = $this->get_panel();
        if (!preg_match(self::PANEL_REGEX, $panel)) {
            throw new coding_exception('Invalid picker panel module: ' . $panel);
        }
        $strings = $this->get_panel_strings($multiple);
        if (!isset($strings['title'])) {
            throw new coding_exception('Picker panel strings must include title');
        }
        return [
            'panel' => $panel,
            'source' => ['class' => static::class, 'args' => array_values($this->get_args())],
            'strings' => $strings,
        ];
    }

    /**
     * Reinstantiate a source in a family endpoint.
     *
     * Call it on the family base: only instantiable subclasses of it are accepted, living in the same namespace
     * as the family base below any installed component - family "tool_x\muform\somepicker\base" accepts
     * "tool_x\muform\somepicker\abc" and "tool_y\muform\somepicker\def".
     *
     * @param string $class full class name sent by the browser
     * @param array $args scalar constructor arguments
     * @return static
     */
    final public static function instantiate(string $class, array $args): static {
        $namespace = substr(static::class, 0, (int)strrpos(static::class, '\\'));
        $pos = strpos($namespace, '\\');
        // Namespace of the family without the component, for example "muform\somepicker".
        $relative = ($pos === false) ? '' : substr($namespace, $pos + 1);
        if ($relative === '') {
            throw new invalid_parameter_exception('Invalid picker source');
        }
        $regex = '/^([a-z][a-z0-9_]*)\\\\' . preg_quote($relative, '/') . '\\\\[a-z][a-z0-9_]*$/D';
        if (!preg_match($regex, $class, $matches) || !\core_component::get_component_directory($matches[1])) {
            throw new invalid_parameter_exception('Invalid picker source');
        }
        if (!class_exists($class) || !is_subclass_of($class, static::class)) {
            throw new invalid_parameter_exception('Invalid picker source');
        }
        if (!(new \ReflectionClass($class))->isInstantiable()) {
            throw new invalid_parameter_exception('Invalid picker source');
        }
        foreach ($args as $arg) {
            if (!is_scalar($arg)) {
                throw new invalid_parameter_exception('Invalid picker source arguments');
            }
        }
        return new $class(...array_values($args));
    }

    /**
     * Checks of family endpoints before instantiate(): browser session only, no guests, system page context.
     *
     * Access control is the login session and the source, not OAuth scopes, so family routes are unscoped;
     * requests authenticated with OAuth2 tokens or API keys have no session and are refused.
     */
    final public static function require_browser_session(): void {
        global $PAGE;

        if (!\core\session\manager::is_session_active() && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            throw new access_denied_exception('accessdenied', 'admin');
        }
        if (isguestuser() || !isloggedin()) {
            throw new moodle_exception('noguest');
        }
        // Labels may be rendered from templates, which needs a page context.
        $PAGE->set_context(\core\context\system::instance());
    }
}
