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
 * Element contract of muform.
 *
 * An element owns one wrapper element and a small cosmetic state. The orchestrator and
 * the display manager never touch the DOM inside the wrapper: they read values through
 * getValue(), write the state and call syncUI(), which projects the state onto the DOM.
 * Anything may be rendered inside the wrapper, custom elements override what they need.
 *
 * @module     tool_mulib/muform/element
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ChangeDetail, ElementState, FormApi, Value} from './types';

/** Selector of things that can receive focus. */
const FOCUSABLE = 'input:not([type="hidden"]), select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])';

export default class Element {
    /** Element name, the same as in the PHP definition. */
    readonly name: string;

    /** Outer element with data-muform-* attributes. */
    readonly wrapper: HTMLElement;

    /** Cosmetic state, written by the orchestrator, projected by syncUI(). */
    readonly state: ElementState;

    /** The form the element belongs to. */
    protected readonly form: FormApi;

    /**
     * Read the initial state from the server rendered markup.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        this.wrapper = wrapper;
        this.form = form;
        this.name = wrapper.dataset.muformName ?? '';
        this.state = {
            hidden: wrapper.dataset.muformHidden === '1',
            disabled: wrapper.dataset.muformDisabled === '1',
            // Server side errors survive the first syncUI() of elements that render during init.
            errors: Element.readErrors(wrapper),
            touched: false,
        };
        wrapper.addEventListener('focusout', (event) => {
            if (!this.state.touched) {
                this.state.touched = true;
            }
            // Showing an error now would move a button under the pointer and swallow the click,
            // the submit handler validates anyway.
            const next = event.relatedTarget;
            if (next instanceof HTMLButtonElement && next.form === wrapper.closest('form')) {
                return;
            }
            this.form.touched(this.name);
        });
    }

    /**
     * Errors rendered by the server in the error area of the standard wrapper.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @returns error messages
     */
    private static readErrors(wrapper: HTMLElement): string[] {
        const areas = Array.from(wrapper.querySelectorAll<HTMLElement>('.invalid-feedback'));
        const area = areas.find((candidate) => candidate.closest('[data-muform-name]') === wrapper);
        if (!area) {
            return [];
        }
        return Array.from(area.children).map((child) => (child.textContent ?? '').trim()).filter((text) => text !== '');
    }

    /**
     * Current value normalised for display rules and validators.
     *
     * @returns null when the element has no value
     */
    getValue(): Value {
        return null;
    }

    /**
     * Client side validation, called by the orchestrator only.
     *
     * @returns error messages, empty when valid
     */
    validate(): string[] {
        return [];
    }

    /**
     * Project the state onto the DOM, must be idempotent.
     * The base writes only the wrapper attributes.
     */
    syncUI(): void {
        const {wrapper, state} = this;
        if (state.hidden) {
            wrapper.hidden = true;
            wrapper.dataset.muformHidden = '1';
        } else {
            wrapper.hidden = false;
            delete wrapper.dataset.muformHidden;
        }
        if (state.disabled) {
            wrapper.dataset.muformDisabled = '1';
        } else {
            delete wrapper.dataset.muformDisabled;
        }
    }

    /**
     * Move keyboard focus into the element.
     */
    focus(): void {
        const target = this.wrapper.querySelector<HTMLElement>(FOCUSABLE);
        target?.focus();
    }

    /**
     * Release resources, called when the form goes away.
     */
    destroy(): void {
        // Nothing to do by default.
    }

    /**
     * Tell the form that the value changed.
     */
    protected emitChange(): void {
        const detail: ChangeDetail = {name: this.name, value: this.getValue()};
        this.wrapper.dispatchEvent(new CustomEvent<ChangeDetail>('muform:change', {bubbles: true, detail}));
    }
}
