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
 * Browser side of the sharedkey element: a show/hide toggle that also reveals the
 * current key from the data attribute, and the clear checkbox disabling the input.
 *
 * Intentionally separate from the secret element, which must never reveal anything.
 *
 * @module     tool_mulib/muform/element/sharedkey
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from '@moodle/lms/core/stringUtils';
import NativeElement from '../native';
import type {FormApi} from '../types';

export default class extends NativeElement {
    /** Masked text input, null when frozen. */
    private readonly input: HTMLInputElement | null;

    /** Optional clear checkbox. */
    private readonly clear: HTMLInputElement | null;

    /** Show/hide button, created once strings are loaded. */
    private button: HTMLButtonElement | null = null;

    /** Placeholder rendered by the server, dots when a value exists. */
    private readonly placeholder: string;

    /** Placeholder while unmasked, loaded with the strings. */
    private typenew = '';

    /**
     * Wire the toggle and the clear checkbox.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        super(wrapper, form);
        this.input = wrapper.querySelector<HTMLInputElement>('input.muform-sharedkey-masked');
        this.clear = wrapper.querySelector<HTMLInputElement>('input[type="checkbox"]');
        this.placeholder = this.input?.placeholder ?? '';
        if (!this.input) {
            return;
        }
        const {input, clear} = this;

        if (clear) {
            clear.addEventListener('change', () => {
                if (clear.checked) {
                    input.value = '';
                    this.setMasked(true);
                }
                this.syncUI();
            });
        }
        void this.addToggle(wrapper, input);
    }

    /**
     * Clearing disables the input and the toggle, the placeholder follows the masked state.
     */
    override syncUI(): void {
        super.syncUI();
        if (!this.input || this.state.disabled) {
            return;
        }
        const clearing = this.clear?.checked ?? false;
        this.input.disabled = clearing;
        if (this.button) {
            this.button.disabled = clearing;
        }
        const masked = this.input.classList.contains('muform-sharedkey-masked');
        if (clearing) {
            this.input.placeholder = '';
        } else {
            // Dots would look like a revealed value, so an unmasked input asks for a new value.
            this.input.placeholder = masked ? this.placeholder : this.typenew;
        }
    }

    /**
     * Mask or unmask the typed text.
     *
     * @param masked true to mask
     */
    private setMasked(masked: boolean): void {
        if (!this.input) {
            return;
        }
        this.input.classList.toggle('muform-sharedkey-masked', masked);
        if (this.button) {
            this.button.textContent = masked ? this.button.dataset.show ?? '' : this.button.dataset.hide ?? '';
        }
    }

    /**
     * Add the show/hide button after the input, showing also reveals the current key.
     *
     * @param wrapper outer element
     * @param input the masked input
     */
    private async addToggle(wrapper: HTMLElement, input: HTMLInputElement): Promise<void> {
        const actions = wrapper.querySelector<HTMLElement>('[data-muform-sharedkey-actions]');
        if (!actions) {
            return;
        }
        const [show, hide, typenew] = await Promise.all([
            getString('muform_show', 'tool_mulib'),
            getString('muform_hide', 'tool_mulib'),
            getString('muform_typenewvalue', 'tool_mulib'),
        ]);
        this.typenew = typenew;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary';
        button.dataset.show = show;
        button.dataset.hide = hide;
        button.setAttribute('aria-controls', input.id);
        // The label changes between show and hide, so no aria-pressed; the field label disambiguates.
        button.setAttribute('aria-describedby', `${input.id}_label`);
        button.addEventListener('click', () => {
            const masked = !input.classList.contains('muform-sharedkey-masked');
            this.setMasked(masked);
            const current = input.dataset.muformSharedkeyCurrent ?? '';
            if (!masked && input.value === '' && current !== '') {
                // Show the current key, submitting it unchanged sets the same key again.
                input.value = current;
                input.dispatchEvent(new Event('input', {bubbles: true}));
            }
            this.syncUI();
        });
        this.button = button;
        actions.replaceChildren(button);
        this.setMasked(input.classList.contains('muform-sharedkey-masked'));
        this.syncUI();
    }
}
