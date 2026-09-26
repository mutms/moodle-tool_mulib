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
 * Browser side of the editor element.
 *
 * The site editor (TinyMCE) attaches itself to the textarea from the collected page
 * JavaScript. Tiny only writes back to the textarea on blur and jQuery submit, so this
 * module saves the editor content before values are read, validated or submitted.
 *
 * @module     tool_mulib/muform/element/editor
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requireAsync} from '@moodle/lms/core/amd';
import NativeElement from '../native';
import type {FormApi, Value} from '../types';

/** Subset of a TinyMCE editor instance used here. */
interface TinyInstance {
    save(): void;
    mode: {set(mode: 'readonly' | 'design'): void};
}

/** Subset of editor_tiny/editor. */
interface TinyModule {
    getInstanceForElement(element: Element): TinyInstance | null;
}

export default class extends NativeElement {
    /** The textarea, null when frozen. */
    private readonly textarea: HTMLTextAreaElement | null;

    /** Tiny module once loaded, null when Tiny is not on the page. */
    private tiny: TinyModule | null = null;

    /**
     * Save editor content before the form submits.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        super(wrapper, form);
        this.textarea = wrapper.querySelector<HTMLTextAreaElement>('textarea');
        if (!this.textarea) {
            return;
        }
        form.on('submit', () => this.saveEditor());
        void this.loadTiny();
    }

    /**
     * The textarea and the format select, never the DOM Tiny injects.
     *
     * @returns controls
     */
    override controls(): (HTMLTextAreaElement | HTMLSelectElement)[] {
        const result: (HTMLTextAreaElement | HTMLSelectElement)[] = [];
        if (this.textarea) {
            result.push(this.textarea);
        }
        const select = this.wrapper.querySelector<HTMLSelectElement>('select');
        if (select) {
            result.push(select);
        }
        return result;
    }

    /**
     * Current text after saving the editor.
     *
     * @returns the text or null when frozen
     */
    override getValue(): Value {
        this.saveEditor();
        return this.textarea ? this.textarea.value : null;
    }

    /**
     * Native validation of the textarea after saving the editor.
     *
     * @returns error messages, empty when valid
     */
    override validate(): string[] {
        this.saveEditor();
        return super.validate();
    }

    /**
     * Disable the editor together with the textarea.
     */
    override syncUI(): void {
        super.syncUI();
        const instance = this.getInstance();
        instance?.mode.set(this.state.disabled ? 'readonly' : 'design');
    }

    /**
     * Copy the editor content into the textarea.
     */
    private saveEditor(): void {
        this.getInstance()?.save();
    }

    /**
     * Tiny instance attached to the textarea, if any.
     *
     * @returns the instance or null
     */
    private getInstance(): TinyInstance | null {
        if (!this.tiny || !this.textarea) {
            return null;
        }
        return this.tiny.getInstanceForElement(this.textarea);
    }

    /**
     * Load the Tiny module when Tiny is used on this page.
     */
    private async loadTiny(): Promise<void> {
        try {
            this.tiny = await requireAsync<TinyModule>('editor_tiny/editor');
        } catch {
            this.tiny = null;
        }
        this.syncUI();
    }
}
