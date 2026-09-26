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

namespace tool_mulib\tests\muform\element;

use behat_base;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;

/**
 * Base class for Behat helpers of muform elements.
 *
 * One helper per element type lives in <plugin>/tests/classes/muform/element/<type>.php
 * with class name <component>\tests\muform\element\<type>, it is found automatically
 * via the data-muform-element and data-muform-component attributes of the element wrapper. Helpers are not
 * autoloaded in Behat, the behat_tool_mulib context loads them with require_once.
 *
 * Table values for option elements are exact option keys, comma separated
 * where multiple values are possible, exact option labels are accepted
 * only when no option has the given key.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Constructor.
     *
     * @param behat_base $context Behat context, use for JS execution and waiting
     * @param NodeElement $wrapper element wrapper node with data-muform-element attribute
     */
    public function __construct(
        /** @var behat_base Behat context */
        protected readonly behat_base $context,
        /** @var NodeElement element wrapper */
        protected readonly NodeElement $wrapper,
    ) {
    }

    /**
     * Set element value from table cell.
     *
     * @param string $value
     */
    abstract public function set_value(string $value): void;

    /**
     * Returns current element value normalised to table cell format.
     *
     * @return string
     */
    abstract public function get_value(): string;

    /**
     * Does the current value match expected table cell?
     *
     * @param string $expected
     * @return bool
     */
    public function matches(string $expected): bool {
        return trim($this->get_value()) === trim($expected);
    }

    /**
     * Type text into the search field of a picker without picking any result, JavaScript only.
     *
     * @param string $text
     */
    public function type_search(string $text): void {
        throw new ExpectationException(
            'muform element "' . $this->get_name() . '" has no search field',
            $this->context->getSession()
        );
    }

    /**
     * Returns element name.
     *
     * @return string
     */
    final public function get_name(): string {
        return (string)$this->wrapper->getAttribute('data-muform-name');
    }

    /**
     * Find exactly one node inside the wrapper.
     *
     * @param string $selector css or xpath
     * @param string $locator
     * @return NodeElement
     */
    final protected function find(string $selector, string $locator): NodeElement {
        $node = $this->wrapper->find($selector, $locator);
        if (!$node) {
            throw new ExpectationException(
                'Cannot find "' . $locator . '" in muform element "' . $this->get_name() . '"',
                $this->context->getSession()
            );
        }
        return $node;
    }

    /**
     * Find all nodes inside the wrapper.
     *
     * @param string $selector css or xpath
     * @param string $locator
     * @return NodeElement[]
     */
    final protected function find_all(string $selector, string $locator): array {
        return $this->wrapper->findAll($selector, $locator);
    }

    /**
     * Split comma separated table cell, literal comma is escaped as backslash comma.
     *
     * @param string $value
     * @return string[] trimmed items, empty items removed
     */
    final protected function split_values(string $value): array {
        $items = preg_split('/(?<!\\\\),/', trim($value));
        $result = [];
        foreach ($items as $item) {
            $item = trim(str_replace('\\,', ',', $item));
            if ($item !== '') {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * Resolve table cell item to option key, exact label is used when no option has the key.
     *
     * @param string $item
     * @param array $options option labels indexed by keys
     * @return string
     */
    final protected function resolve_option(string $item, array $options): string {
        if (array_key_exists($item, $options)) {
            return $item;
        }
        $key = array_search($item, $options, true);
        if ($key !== false) {
            return (string)$key;
        }
        throw new ExpectationException(
            'Unknown option "' . $item . '" in muform element "' . $this->get_name() . '"',
            $this->context->getSession()
        );
    }

    /**
     * Returns option labels indexed by keys for radio, checkbox or select options inside the wrapper.
     *
     * @return array
     */
    final protected function get_options(): array {
        $options = [];
        foreach ($this->find_all('css', 'input[type="radio"], input[type="checkbox"]') as $input) {
            $label = $this->wrapper->find('css', 'label[for="' . $input->getAttribute('id') . '"]');
            $options[(string)$input->getAttribute('value')] = $label ? trim($label->getText()) : '';
        }
        foreach ($this->find_all('css', 'option') as $option) {
            $options[(string)$option->getAttribute('value')] = trim($option->getText());
        }
        return $options;
    }

    /**
     * Compare two lists of keys ignoring order.
     *
     * @param string[] $expected
     * @param string[] $actual
     * @return bool
     */
    final protected function same_keys(array $expected, array $actual): bool {
        sort($expected);
        sort($actual);
        return $expected === $actual;
    }
}
