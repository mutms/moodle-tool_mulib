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
 * Browser side of the tags element: replaces the fallback text input with the
 * tag field island; the island renders the hidden input the form posts.
 *
 * @module     tool_mulib/muform/element/tags
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {mountReactApp} from '@moodle/lms/core/mount';
import {getString} from '@moodle/lms/core/stringUtils';
import TagsPicker, {type Tag, type TagsPickerStrings} from '../tagspicker';
import NativeElement from '../native';
import type {FormApi, Value} from '../types';

export default class extends NativeElement {
    /** Island container, null when frozen or tagging is disabled. */
    private readonly container: HTMLElement | null;

    /** Field name posted. */
    private readonly fieldname: string;

    /** Field id for the label. */
    private readonly fieldid: string;

    /** Required flag of the server rendered input. */
    private readonly required: boolean;

    /** Placeholder of the server rendered input. */
    private readonly placeholder: string;

    /** Current tags, kept across remounts. */
    private tags: Tag[] = [];

    /** Unmount function of the island. */
    private unmountPicker: (() => void) | null = null;

    /** Disabled state the island was mounted with. */
    private pickerDisabled: boolean | null = null;

    /** Localised strings, loaded once. */
    private strings: TagsPickerStrings | null = null;

    /**
     * Read the server rendered state and mount the island.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        super(wrapper, form);
        this.container = wrapper.querySelector<HTMLElement>('[data-muform-tags]');
        const input = this.container?.querySelector<HTMLInputElement>('input[type="text"]') ?? null;
        this.fieldname = input?.name ?? '';
        this.fieldid = input?.id ?? '';
        this.required = input?.required ?? false;
        this.placeholder = input?.placeholder ?? '';
        if (!this.container || !input) {
            this.container = null;
            return;
        }
        try {
            this.tags = JSON.parse(this.container.dataset.muformTagsSelected ?? '[]') as Tag[];
        } catch {
            this.tags = [];
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
     * Entered tag names.
     *
     * @returns list of names, empty when there are none
     */
    override getValue(): Value {
        if (!this.container) {
            return null;
        }
        return this.tags.map((tag) => tag.name);
    }

    /**
     * Required check, the server validates the names themselves.
     *
     * @returns error messages, empty when valid
     */
    override validate(): string[] {
        if (this.state.hidden || this.state.disabled || !this.required) {
            return [];
        }
        return (this.getValue() as string[]).length ? [] : [this.getHint('required')];
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
        const [noresults, searching, toomanyresults, remove, close] = await Promise.all([
            getString('muform_noresults', 'tool_mulib'),
            getString('muform_searching', 'tool_mulib'),
            getString('muform_toomanyresults', 'tool_mulib'),
            getString('muform_remove', 'tool_mulib'),
            getString('closebuttontitle', 'core'),
        ]);
        this.strings = {noresults, searching, toomanyresults, remove, close};
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
        let area = {'class': '', args: [] as unknown[]};
        try {
            area = JSON.parse(container.dataset.muformTagsArea ?? '{}') as {class: string; args: unknown[]};
        } catch {
            // Keep the empty area, suggestions will fail visibly.
        }
        this.unmountPicker?.();
        this.pickerDisabled = this.state.disabled;
        this.unmountPicker = mountReactApp(container, TagsPicker, {
            area,
            initial: this.tags,
            suggest: container.dataset.muformTagsSuggest === '1',
            standardonly: container.dataset.muformTagsStandardonly === '1',
            name: this.fieldname,
            id: this.fieldid,
            placeholder: this.placeholder,
            disabled: this.state.disabled,
            strings: this.strings,
            onChange: (names: string[]) => {
                this.tags = names.map((name) => this.tags.find((tag) => tag.name === name) ?? {name, error: null});
                if (this.state.errors.length) {
                    this.state.errors = [];
                    this.syncUI();
                }
                this.emitChange();
            },
        }, {id: `muform-tags-${this.name}`});
    }
}
