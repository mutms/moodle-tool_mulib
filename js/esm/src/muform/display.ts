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

/**
 * Display manager: hides and disables elements according to rules from the server.
 *
 * The logic mirrors tool_mulib\muform\util\display_manager exactly. Rules depend on
 * element values only, they are evaluated in a single pass in rule order, results of
 * rules for the same target are ORed, and the state of a section or button row cascades
 * to the elements inside it. Hiding and disabling are cosmetic, the server ignores them.
 *
 * @module     tool_mulib/muform/display
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {DisplayRule, ElementLike, Value} from './types';

/**
 * Compare dependency value with rule value, the same way as PHP display_manager::matches().
 *
 * @param op rule operator
 * @param depvalue current value of the dependency element
 * @param value rule value
 * @returns true when the rule applies
 */
export function matches(op: string, depvalue: Value, value: string | string[] | null): boolean {
    switch (op) {
        case 'eq':
            if (Array.isArray(depvalue)) {
                return depvalue.includes(String(value));
            }
            return depvalue === String(value);
        case 'neq':
            return !matches('eq', depvalue, value);
        case 'in': {
            const list = (Array.isArray(value) ? value : [value]).map(String);
            if (Array.isArray(depvalue)) {
                return depvalue.some((item) => list.includes(item));
            }
            return depvalue !== null && list.includes(depvalue);
        }
        case 'notin':
            return !matches('in', depvalue, value);
        case 'checked':
            return depvalue === '1';
        case 'notchecked':
            return depvalue !== '1';
        case 'empty':
            return depvalue === null || depvalue === '' || (Array.isArray(depvalue) && depvalue.length === 0);
        case 'notempty':
            return !matches('empty', depvalue, null);
        default:
            throw new Error(`Invalid display rule operator: ${op}`);
    }
}

export default class DisplayManager {
    /** Elements indexed by name. */
    private readonly elements: Map<string, ElementLike>;

    /** Rules from the server. */
    private readonly rules: DisplayRule[];

    /** Names of elements other elements depend on. */
    private readonly dependencies: Set<string>;

    /**
     * Prepare the manager, call apply() to evaluate.
     *
     * @param elements elements indexed by name
     * @param rules rules from data-muform-rules
     */
    constructor(elements: Map<string, ElementLike>, rules: DisplayRule[]) {
        this.elements = elements;
        this.rules = rules;
        this.dependencies = new Set(rules.map((rule) => rule.dep));
    }

    /**
     * Does a change of the given element affect any rule?
     *
     * @param name element name
     * @returns true when apply() should run
     */
    dependsOn(name: string): boolean {
        return this.dependencies.has(name);
    }

    /**
     * Evaluate all rules and update element states, returns names of elements whose state changed.
     *
     * @returns changed element names
     */
    apply(): string[] {
        const hidden = new Set<string>();
        const disabled = new Set<string>();
        for (const rule of this.rules) {
            const dependency = this.elements.get(rule.dep);
            if (!dependency || !this.elements.has(rule.target)) {
                continue;
            }
            if (matches(rule.op, dependency.getValue(), rule.value)) {
                (rule.action === 'hide' ? hidden : disabled).add(rule.target);
            }
        }

        const changed: string[] = [];
        for (const [name, element] of this.elements) {
            let isHidden = false;
            let isDisabled = false;
            let current: string | undefined = name;
            while (current !== undefined) {
                isHidden = isHidden || hidden.has(current);
                isDisabled = isDisabled || disabled.has(current);
                current = this.parentOf(current);
            }
            if (element.state.hidden !== isHidden || element.state.disabled !== isDisabled) {
                element.state.hidden = isHidden;
                element.state.disabled = isDisabled;
                element.syncUI();
                changed.push(name);
            }
        }
        return changed;
    }

    /**
     * Name of the closest enclosing element, sections and button rows contain other elements.
     *
     * @param name element name
     * @returns parent name or undefined for top level elements
     */
    private parentOf(name: string): string | undefined {
        const element = this.elements.get(name);
        const parent = element?.wrapper.parentElement?.closest<HTMLElement>('[data-muform-element]');
        return parent?.dataset.muformName;
    }
}
