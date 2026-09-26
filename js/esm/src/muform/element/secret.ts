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
 * Browser side of the secret element: a show/hide toggle for the text being typed
 * and the clear checkbox disabling the input. The current value never exists in the page.
 *
 * Intentionally separate from the sharedkey element.
 *
 * @module     tool_mulib/muform/element/secret
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
        this.input = wrapper.querySelector<HTMLInputElement>('input.muform-secret-masked');
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
        const masked = this.input.classList.contains('muform-secret-masked');
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
        this.input.classList.toggle('muform-secret-masked', masked);
        if (this.button) {
            this.button.textContent = masked ? this.button.dataset.show ?? '' : this.button.dataset.hide ?? '';
        }
    }

    /**
     * Add the show/hide button after the input.
     *
     * @param wrapper outer element
     * @param input the masked input
     */
    private async addToggle(wrapper: HTMLElement, input: HTMLInputElement): Promise<void> {
        const actions = wrapper.querySelector<HTMLElement>('[data-muform-secret-actions]');
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
            const masked = !input.classList.contains('muform-secret-masked');
            this.setMasked(masked);
            this.syncUI();
        });
        this.button = button;
        actions.replaceChildren(button);
        this.setMasked(input.classList.contains('muform-secret-masked'));
        this.syncUI();
    }
}
