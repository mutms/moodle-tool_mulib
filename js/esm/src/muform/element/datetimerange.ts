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
 * Browser side of the datetimerange element.
 *
 * The server renders a named text input. This module unhooks it from the form
 * and injects hidden inputs name[start], name[end] and name[text]: normalised values
 * submit both timestamps, text the server did not answer yet is submitted as it is
 * and parsed by the server the same way. The React picker is an enhancement.
 *
 * @module     tool_mulib/muform/element/datetimerange
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {mountReactApp} from '@moodle/lms/core/mount';
import {getString} from '@moodle/lms/core/stringUtils';
import {normaliseRange} from '../datetimerangeapi';
import DateTimePicker, {type PickerDuration, type PickerStrings, type RangeOptions} from '../datetimepicker';
import NativeElement from '../native';
import type {FormApi, Value} from '../types';

/** Hidden inputs submitting the value. */
interface Carriers {
    start: HTMLInputElement;
    end: HTMLInputElement;
    text: HTMLInputElement;
}

export default class extends NativeElement {
    /** Visible text input, null when frozen. */
    private readonly input: HTMLInputElement | null;

    /** Hidden inputs submitting timestamps or text. */
    private readonly carriers: Carriers | null = null;

    /** Duration and end date of the current value. */
    private readonly hint: HTMLElement | null = null;

    /** The last normalisation said the text is invalid. */
    private invalid = false;

    /** Unmount function of the picker island. */
    private unmountPicker: (() => void) | null = null;

    /** Disabled state the picker was mounted with. */
    private pickerDisabled: boolean | null = null;

    /** Localised picker strings, loaded once. */
    private strings: PickerStrings | null = null;

    /** Range options of the picker, loaded once. */
    private range: RangeOptions | null = null;

    /**
     * Unhook the text input, inject the carriers and mount the picker.
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
        this.hint = wrapper.querySelector<HTMLElement>('[data-muform-datetimerange-hint]');

        const carrier = (key: string, value: string): HTMLInputElement => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `${input.name}[${key}]`;
            hidden.value = value;
            hidden.disabled = input.disabled;
            input.insertAdjacentElement('beforebegin', hidden);
            return hidden;
        };
        const start = input.dataset.muformDatetimerangeStart ?? '';
        const end = input.dataset.muformDatetimerangeEnd ?? '';
        const normalised = start !== '' && end !== '';
        this.carriers = {
            start: carrier('start', normalised ? start : ''),
            end: carrier('end', normalised ? end : ''),
            text: carrier('text', normalised ? '' : input.value),
        };
        input.removeAttribute('name');

        // Until the server answers the raw text is submitted, the server parses it the same way.
        input.addEventListener('input', () => {
            this.setCarriers('', '', input.value);
            this.invalid = false;
        });
        input.addEventListener('change', () => {
            void this.applyText(input.value);
        });

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
     * Start and end timestamps joined with a hyphen, or the raw text before normalisation.
     *
     * @returns null when there is no value
     */
    override getValue(): Value {
        if (!this.carriers) {
            return null;
        }
        const {start, end, text} = this.carriers;
        if (start.value !== '' && end.value !== '') {
            return `${start.value}-${end.value}`;
        }
        return text.value === '' ? null : text.value;
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
        if (this.strings && this.pickerDisabled !== this.state.disabled) {
            this.renderPicker();
        }
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
        const {input} = this;
        if (!input || !this.carriers) {
            return;
        }
        const answer = await normaliseRange(input, text);
        if (answer.valid) {
            input.value = answer.text;
            if (answer.start === null || answer.end === null) {
                this.setCarriers('', '', '');
            } else {
                this.setCarriers(String(answer.start), String(answer.end), '');
            }
            this.setHint(answer.hint);
            this.invalid = false;
        } else {
            input.value = text;
            this.setCarriers('', '', text);
            this.setHint('');
            this.invalid = true;
        }
        // The answer arrives after the blur validation ran, so project the result now.
        const errors = (this.invalid && this.state.touched) ? [this.getHint('invalid')] : [];
        if (this.state.errors.length || errors.length) {
            this.state.errors = errors;
            this.syncUI();
        }
        this.emitChange();
    }

    /**
     * Update the hidden inputs.
     *
     * @param start start timestamp or empty
     * @param end end timestamp or empty
     * @param text raw text or empty
     */
    private setCarriers(start: string, end: string, text: string): void {
        if (!this.carriers) {
            return;
        }
        this.carriers.start.value = start;
        this.carriers.end.value = end;
        this.carriers.text.value = text;
    }

    /**
     * Show the duration hint of the current value.
     *
     * @param text hint text from the server, empty hides it
     */
    private setHint(text: string): void {
        if (this.hint) {
            this.hint.textContent = text;
        }
    }

    /**
     * Load the strings and mount the picker island.
     */
    private async mountPicker(): Promise<void> {
        const span = this.wrapper.querySelector<HTMLElement>('[data-muform-datetime-picker]');
        if (!span || !this.input) {
            return;
        }
        const [pick, today, clear, apply, hour, minute, monthprev, monthnext, yearprev, yearnext, start, end, duration]
            = await Promise.all([
                getString('muform_pickdatetimerange', 'tool_mulib'),
                getString('muform_today', 'tool_mulib'),
                getString('muform_clear', 'tool_mulib'),
                getString('muform_apply', 'tool_mulib'),
                getString('muform_hour', 'tool_mulib'),
                getString('muform_minute', 'tool_mulib'),
                getString('muform_monthprev', 'tool_mulib'),
                getString('muform_monthnext', 'tool_mulib'),
                getString('muform_yearprev', 'tool_mulib'),
                getString('muform_yearnext', 'tool_mulib'),
                getString('muform_rangestart', 'tool_mulib'),
                getString('muform_rangeend', 'tool_mulib'),
                getString('muform_duration', 'tool_mulib'),
            ]);
        this.strings = {pick, today, clear, apply, hour, minute, monthprev, monthnext, yearprev, yearnext};
        let durations: PickerDuration[] = [];
        try {
            durations = JSON.parse(span.dataset.muformDatetimerangeDurations || '[]') as PickerDuration[];
        } catch {
            durations = [];
        }
        const defaultDuration = Number(this.input.dataset.muformDatetimerangeDefaultduration) || null;
        this.range = {durations, defaultDuration, strings: {start, end, duration}};
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
        this.unmountPicker = mountReactApp(span, DateTimePicker, {
            input: this.input,
            step: Number(span.dataset.muformDatetimeStep) || 5,
            firstDay: span.dataset.muformDatetimeFirstday === undefined ? null : Number(span.dataset.muformDatetimeFirstday),
            hour12: span.dataset.muformDatetimeHour12 === '1',
            disabled: this.state.disabled,
            strings: this.strings,
            apply: (text: string) => this.applyText(text),
            range: this.range,
        }, {id: `muform-datetimerange-${this.name}`});
    }
}
