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
 * Browser side of the autocomplete element: replaces the fallback text input
 * with the single value picker island; the island renders the hidden input the form posts.
 *
 * @module     tool_mulib/muform/element/autocomplete
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {mountReactApp} from '@moodle/lms/core/mount';
import {getString} from '@moodle/lms/core/stringUtils';
import Picker, {type PickerStrings, type Selected} from '../autocompletepicker';
import NativeElement from '../native';
import type {FormApi, Value} from '../types';

export default class extends NativeElement {
    /** Island container, null when frozen. */
    private readonly container: HTMLElement | null;

    /** Field name posted. */
    private readonly fieldname: string;

    /** Field id for the label. */
    private readonly fieldid: string;

    /** Required flag of the server rendered input. */
    private readonly required: boolean;

    /** Placeholder of the server rendered input. */
    private readonly placeholder: string;

    /** Width class of the server rendered input. */
    private readonly widthclass: string;

    /** Current selection, kept across remounts. */
    private selected: Selected | null = null;

    /** Unmount function of the island. */
    private unmountPicker: (() => void) | null = null;

    /** Disabled state the island was mounted with. */
    private pickerDisabled: boolean | null = null;

    /** Localised strings, loaded once. */
    private strings: PickerStrings | null = null;

    /**
     * Read the server rendered state and mount the island.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        super(wrapper, form);
        this.container = wrapper.querySelector<HTMLElement>('[data-muform-autocomplete]');
        const input = this.container?.querySelector<HTMLInputElement>('input[type="text"]') ?? null;
        this.fieldname = input?.name ?? '';
        this.fieldid = input?.id ?? '';
        this.required = input?.required ?? false;
        this.placeholder = input?.placeholder ?? '';
        this.widthclass = Array.from(input?.classList ?? []).find((name) => name.startsWith('muform-width-')) ?? '';
        if (!this.container || !input) {
            this.container = null;
            return;
        }
        try {
            const selected = JSON.parse(this.container.dataset.muformAutocompleteSelected ?? '[]') as Selected[];
            this.selected = selected[0] ?? null;
        } catch {
            this.selected = null;
        }
        void this.mountPicker();
    }

    /**
     * The island owns its inputs.
     *
     * @returns nothing
     */
    override controls(): never[] {
        return [];
    }

    /**
     * Selected value.
     *
     * @returns the value or null
     */
    override getValue(): Value {
        if (!this.container) {
            return null;
        }
        return this.selected?.value ?? null;
    }

    /**
     * Required check, the server validates the value itself.
     *
     * @returns error messages, empty when valid
     */
    override validate(): string[] {
        if (this.state.hidden || this.state.disabled || !this.required) {
            return [];
        }
        return this.getValue() === null ? [this.getHint('required')] : [];
    }

    /**
     * Remount the island when the disabled state changes.
     */
    override syncUI(): void {
        super.syncUI();
        if (this.strings && this.pickerDisabled !== this.state.disabled) {
            this.renderPicker();
        }
    }

    /**
     * Unmount the island.
     */
    override destroy(): void {
        this.unmountPicker?.();
        this.unmountPicker = null;
    }

    /**
     * Focus the combobox.
     */
    override focus(): void {
        this.container?.querySelector<HTMLInputElement>('input[role="combobox"]')?.focus();
    }

    /**
     * Load strings, drop the fallback markup and mount.
     */
    private async mountPicker(): Promise<void> {
        const [noresults, searching, toomanyresults, clearselection, close] = await Promise.all([
            getString('muform_noresults', 'tool_mulib'),
            getString('muform_searching', 'tool_mulib'),
            getString('muform_toomanyresults', 'tool_mulib'),
            getString('muform_clearselection', 'tool_mulib'),
            getString('closebuttontitle', 'core'),
        ]);
        this.strings = {noresults, searching, toomanyresults, clearselection, close};
        this.container?.replaceChildren();
        this.renderPicker();
    }

    /**
     * Mount or remount the island with the current state.
     */
    private renderPicker(): void {
        const container = this.container;
        if (!container || !this.strings) {
            return;
        }
        let source = {'class': '', args: [] as unknown[]};
        try {
            source = JSON.parse(container.dataset.muformAutocompleteSource ?? '{}') as {class: string; args: unknown[]};
        } catch {
            // Keep the empty source, searches will fail visibly.
        }
        this.unmountPicker?.();
        this.pickerDisabled = this.state.disabled;
        this.unmountPicker = mountReactApp(container, Picker, {
            url: container.dataset.muformAutocompleteUrl ?? '',
            source,
            initial: this.selected,
            name: this.fieldname,
            id: this.fieldid,
            placeholder: this.placeholder,
            widthclass: this.widthclass,
            disabled: this.state.disabled,
            strings: this.strings,
            onChange: (value: string | null) => {
                if (value === null) {
                    this.selected = null;
                } else if (this.selected?.value !== value) {
                    this.selected = {value, label: value, error: null};
                }
                if (this.state.errors.length) {
                    this.state.errors = [];
                    this.syncUI();
                }
                this.emitChange();
            },
        }, {id: `muform-autocomplete-${this.name}`});
    }
}
