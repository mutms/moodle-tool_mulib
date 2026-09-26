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
 * Element with native form controls inside the standard wrapper.
 *
 * Values, constraint validation, disabling and error display work for any
 * combination of inputs, selects, textareas and buttons, so the tool_mulib
 * element modules do not override anything.
 *
 * @module     tool_mulib/muform/native
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Element from './element';
import type {FormApi, Value} from './types';

/** Native controls that carry values or can be disabled. */
type Control = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement;

export default class NativeElement extends Element {
    /**
     * Listen for value changes of native controls.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        super(wrapper, form);
        wrapper.addEventListener('input', () => this.onInput());
        wrapper.addEventListener('change', () => this.onInput());
    }

    /**
     * Native controls inside the wrapper, without the hidden carriers of empty values.
     *
     * @returns controls in document order
     */
    controls(): Control[] {
        const selector = 'input, select, textarea, button';
        const all = Array.from(this.wrapper.querySelectorAll<Control>(selector));
        if (this.wrapper.matches(selector)) {
            all.unshift(this.wrapper as Control);
        }
        return all.filter((control) => control.type !== 'hidden');
    }

    /**
     * Value derived from the control types: checkbox groups and multiple selects give lists,
     * a single checkbox gives '1' or '0', radios give the checked value or null.
     *
     * @returns the normalised value
     */
    override getValue(): Value {
        const controls = this.controls().filter((control) => control.type !== 'submit' && control.type !== 'button');
        if (controls.length === 0) {
            return null;
        }
        const first = controls[0];
        if (first instanceof HTMLSelectElement) {
            if (first.multiple) {
                return Array.from(first.selectedOptions).map((option) => option.value);
            }
            return first.value;
        }
        if (first.type === 'radio') {
            const checked = controls.find((control) => (control as HTMLInputElement).checked);
            return checked ? checked.value : null;
        }
        if (first.type === 'checkbox') {
            if (controls.length === 1 && !first.name.endsWith('[]')) {
                return (first as HTMLInputElement).checked ? '1' : '0';
            }
            return controls.filter((control) => (control as HTMLInputElement).checked).map((control) => control.value);
        }
        return first.value;
    }

    /**
     * Native constraint validation of every control, hidden and disabled elements are always valid.
     *
     * @returns error messages, empty when valid
     */
    override validate(): string[] {
        if (this.state.hidden || this.state.disabled) {
            return [];
        }
        const errors: string[] = [];
        for (const control of this.controls()) {
            if (control.type === 'submit' || control.type === 'button' || control.checkValidity()) {
                continue;
            }
            const message = control.validity.valueMissing ? this.getHint('required') : this.getHint('invalid');
            if (!errors.includes(message)) {
                errors.push(message);
            }
        }
        return errors;
    }

    /**
     * Disable controls and show errors in the error area of the standard wrapper.
     */
    override syncUI(): void {
        super.syncUI();
        const {state} = this;
        const invalid = state.errors.length > 0;
        for (const control of this.controls()) {
            control.disabled = state.disabled;
            if (control.type === 'submit' || control.type === 'button') {
                continue;
            }
            control.classList.toggle('is-invalid', invalid);
            if (invalid) {
                control.setAttribute('aria-invalid', 'true');
            } else {
                control.removeAttribute('aria-invalid');
            }
        }
        for (const carrier of this.wrapper.querySelectorAll<HTMLInputElement>('input[type="hidden"]')) {
            carrier.disabled = state.disabled;
        }
        const area = this.wrapper.querySelector<HTMLElement>('.invalid-feedback');
        if (area) {
            area.replaceChildren(...state.errors.map((error) => {
                const div = document.createElement('div');
                div.textContent = error;
                return div;
            }));
            area.style.display = invalid ? 'block' : '';
        }
    }

    /**
     * Focus the first enabled control, for radios the checked one.
     */
    override focus(): void {
        const controls = this.controls().filter((control) => !control.disabled);
        const checked = controls.find((control) => (control as HTMLInputElement).checked);
        (checked ?? controls[0])?.focus();
    }

    /**
     * Text shown for missing or invalid values, rendered by the wrapper template.
     *
     * @param kind required or invalid
     * @returns the hint
     */
    protected getHint(kind: 'required' | 'invalid'): string {
        const holder = this.wrapper.querySelector<HTMLElement>('[data-muform-required-hint]') ?? this.wrapper;
        const hint = kind === 'required' ? holder.dataset.muformRequiredHint : holder.dataset.muformInvalidHint;
        return hint || (kind === 'required' ? 'Required' : 'Error');
    }

    /**
     * Any change clears client side errors and notifies the form.
     */
    protected onInput(): void {
        if (this.state.errors.length) {
            this.state.errors = [];
            this.syncUI();
        }
        this.emitChange();
    }
}
