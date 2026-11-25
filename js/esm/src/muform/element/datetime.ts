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
 * Browser side of the datetime element.
 *
 * The server renders a named text input. This module unhooks it from the form
 * and injects a hidden input with the same name carrying the timestamp, so the
 * server receives an integer whenever JavaScript ran. Typed text is normalised
 * by the server through the datetime endpoint, the React picker is an enhancement.
 *
 * @module     tool_mulib/muform/element/datetime
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {mountReactApp} from '@moodle/lms/core/mount';
import {normalise, readComponents} from '../../date/api';
import DatePicker, {getPickerStrings, type PickerStrings} from '../../date/panel';
import NativeElement from '../native';
import type {FormApi, Value} from '../types';

export default class extends NativeElement {
    /** Visible text input, null when frozen. */
    private readonly input: HTMLInputElement | null;

    /** Hidden input submitting the timestamp. */
    private readonly carrier: HTMLInputElement | null = null;

    /** The last normalisation said the text is invalid. */
    private invalid = false;

    /** Unmount function of the picker island. */
    private unmountPicker: (() => void) | null = null;

    /** Disabled state the picker was mounted with. */
    private pickerDisabled: boolean | null = null;

    /** Localised picker strings, loaded once. */
    private strings: PickerStrings | null = null;

    /**
     * Unhook the text input, inject the timestamp carrier and mount the picker.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        super(wrapper, form);
        this.input = wrapper.querySelector<HTMLInputElement>('input[data-muform-datetime-timezone]');
        if (!this.input) {
            return;
        }
        const {input} = this;

        const carrier = document.createElement('input');
        carrier.type = 'hidden';
        carrier.name = input.name;
        carrier.value = input.dataset.muformDatetimeTimestamp || input.value;
        carrier.disabled = input.disabled;
        input.removeAttribute('name');
        input.insertAdjacentElement('beforebegin', carrier);
        this.carrier = carrier;

        // Until the server answers the raw text is submitted, the server parses it the same way.
        input.addEventListener('input', () => {
            this.markEmpty();
            carrier.value = input.value;
            this.invalid = false;
        });
        input.addEventListener('change', () => {
            void this.applyText(input.value);
        });

        this.markEmpty();
        void this.mountPicker();
    }

    /**
     * Only the text input is a control, the picker island has its own selects and buttons.
     *
     * @returns the text input when not frozen
     */
    override controls(): HTMLInputElement[] {
        return this.input ? [this.input] : [];
    }

    /**
     * The timestamp, or the raw text before normalisation.
     *
     * @returns null when there is no value
     */
    override getValue(): Value {
        if (!this.carrier) {
            return null;
        }
        return this.carrier.value === '' ? null : this.carrier.value;
    }

    /**
     * Native validation plus the last answer of the server.
     *
     * @returns error messages, empty when valid
     */
    override validate(): string[] {
        const errors = super.validate();
        if (this.invalid && !this.state.hidden && !this.state.disabled && errors.length === 0) {
            errors.push(this.getHint('invalid'));
        }
        return errors;
    }

    /**
     * Project the state, the picker follows the disabled flag.
     */
    override syncUI(): void {
        super.syncUI();
        this.markEmpty();
        if (this.strings && this.pickerDisabled !== this.state.disabled) {
            this.renderPicker();
        }
    }

    /**
     * Mark empty values, the clear button is hidden for them.
     */
    private markEmpty(): void {
        this.wrapper.querySelector('.muform-datetime')?.classList.toggle('muform-datetime-empty', !this.input?.value);
    }

    /**
     * Unmount the picker.
     */
    override destroy(): void {
        this.unmountPicker?.();
        this.unmountPicker = null;
    }

    /**
     * Let the server interpret text and show the result.
     *
     * @param text typed or picked text, empty clears the value
     */
    async applyText(text: string): Promise<void> {
        const {input, carrier} = this;
        if (!input || !carrier) {
            return;
        }
        const answer = await normalise(input, text);
        if (answer.valid) {
            input.value = answer.text;
            carrier.value = answer.timestamp === null ? '' : String(answer.timestamp);
            this.invalid = false;
        } else {
            input.value = text;
            carrier.value = text;
            this.invalid = true;
        }
        // The answer arrives after the blur validation ran, so project the result now.
        const errors = (this.invalid && this.state.touched) ? [this.getHint('invalid')] : [];
        if (this.state.errors.length || errors.length) {
            this.state.errors = errors;
            this.syncUI();
        }
        this.markEmpty();
        this.emitChange();
    }

    /**
     * Load the strings and mount the picker island.
     */
    private async mountPicker(): Promise<void> {
        const span = this.wrapper.querySelector<HTMLElement>('[data-muform-datetime-picker]');
        if (!span) {
            return;
        }
        this.strings = await getPickerStrings();
        this.renderPicker();
    }

    /**
     * Mount or remount the picker with the current disabled state.
     */
    private renderPicker(): void {
        const span = this.wrapper.querySelector<HTMLElement>('[data-muform-datetime-picker]');
        if (!span || !this.input || !this.strings) {
            return;
        }
        this.unmountPicker?.();
        this.pickerDisabled = this.state.disabled;
        const {input} = this;
        this.unmountPicker = mountReactApp(span, DatePicker, {
            getValue: () => readComponents(input),
            timezone: input.dataset.muformDatetimeTimezone ?? '',
            defaultHour: Number(input.dataset.muformDatetimeDefaulthour) || 0,
            defaultMinute: Number(input.dataset.muformDatetimeDefaultminute) || 0,
            step: Number(span.dataset.muformDatetimeStep) || 5,
            firstDay: span.dataset.muformDatetimeFirstday === undefined ? null : Number(span.dataset.muformDatetimeFirstday),
            hour12: span.dataset.muformDatetimeHour12 === '1',
            disabled: this.state.disabled,
            strings: this.strings,
            apply: (text: string) => this.applyText(text),
            clearable: span.dataset.muformDatetimeClearable === '1',
            title: this.wrapper.querySelector(`#${CSS.escape(input.id)}_label`)?.textContent?.trim() || undefined,
        }, {id: `muform-datetime-${this.name}`});
    }
}
