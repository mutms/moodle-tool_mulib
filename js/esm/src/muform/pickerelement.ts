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
 * Browser side of picker and pickermany elements.
 *
 * Selected values are pills with a remove button, the button after them opens the panel of the picker family
 * (see pickerpanel). The panel module is loaded on first use, it returns picked values with their labels.
 * The server validates the submitted values.
 *
 * @module     tool_mulib/muform/pickerelement
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {mountReactApp} from '@moodle/lms/core/mount';
import type {ComponentType} from 'react';
import NativeElement from './native';
import {labelText, openPanel, type PickerItem, type PickerPanelProps, type PickerSource} from './pickerpanel';
import type {FormApi, Value} from './types';

/** Selected value with label html and an optional refusal. */
export interface Selected {
    value: string;
    label: string;
    error: string | null;
}

/** Strings of the element, the rest belongs to the panel. */
interface Strings {
    title: string;
    pickerAdd: string;
    pickerChange: string;
    pickerRemove: string;
    pickerSelect: string;
    [key: string]: string;
}

/** Loads panel modules, replaceable for tests. */
let loadModule = (specifier: string): Promise<unknown> => import(specifier);

/**
 * Replace the panel module loader, intended for tests.
 *
 * @param loader function resolving a module specifier to its exports
 */
export function setPanelLoader(loader: (specifier: string) => Promise<unknown>): void {
    loadModule = loader;
}

export default class PickerElement extends NativeElement {
    /** Hidden input with the submitted value. */
    private readonly input: HTMLInputElement | null;

    /** Container of pills and the button. */
    private readonly container: HTMLElement | null;

    /** Panel module name. */
    private panelModule = '';

    /** Source of values. */
    private source: PickerSource = {'class': '', args: []};

    /** Localised strings. */
    private strings: Strings | null = null;

    /** May several values be picked? */
    private multiple = false;

    /** Current selection. */
    private selected: Selected[] = [];

    /** Open panel, null when closed. */
    private panel: HTMLDialogElement | null = null;

    /** Panel is being loaded. */
    private opening = false;

    /**
     * Read the server rendered data and render the pills.
     *
     * @param wrapper outer element with data-muform-* attributes
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        super(wrapper, form);
        this.container = wrapper.querySelector<HTMLElement>('[data-picker-source]');
        this.input = wrapper.querySelector<HTMLInputElement>(`input[type="hidden"][name="${this.name}"]`);
        if (!this.container || !this.input) {
            return;
        }
        const data = this.container.dataset;
        try {
            this.source = JSON.parse(data.pickerSource ?? '') as PickerSource;
            this.selected = JSON.parse(data.pickerSelected ?? '[]') as Selected[];
            this.strings = JSON.parse(data.pickerStrings ?? '{}') as Strings;
        } catch {
            return;
        }
        this.panelModule = data.pickerPanel ?? '';
        this.multiple = data.pickerMultiple === '1';
        this.render();
    }

    /**
     * Selected values.
     *
     * @returns list of values when multiple, otherwise one value or null
     */
    override getValue(): Value {
        const values = this.selected.map((item) => item.value);
        if (this.multiple) {
            return values;
        }
        return values[0] ?? null;
    }

    /**
     * The server validates the values.
     *
     * @returns no errors
     */
    override validate(): string[] {
        return [];
    }

    /**
     * Focus the button opening the panel.
     */
    override focus(): void {
        this.container?.querySelector<HTMLButtonElement>('[data-picker-open]')?.focus();
    }

    /**
     * Close the panel when the form goes away.
     */
    override destroy(): void {
        this.panel?.close();
    }

    /**
     * Render pills and the button again.
     */
    private render(): void {
        const {container, strings} = this;
        if (!container || !strings) {
            return;
        }
        const nodes: HTMLElement[] = this.selected.map((item) => {
            const pill = document.createElement('span');
            pill.className = 'badge d-inline-flex align-items-center gap-1 fs-6 text-wrap text-start '
                + (item.error ? 'text-bg-danger' : 'bg-secondary text-dark fw-normal');
            pill.dataset.pickerPill = item.value;
            if (item.error) {
                pill.title = item.error;
            }
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = item.error ? 'btn-close btn-close-white' : 'btn-close';
            remove.style.fontSize = '.6em';
            remove.setAttribute('aria-label', strings.pickerRemove + ': ' + labelText(item.label));
            remove.title = strings.pickerRemove;
            remove.addEventListener('click', () => {
                this.selected = this.selected.filter((candidate) => candidate !== item);
                this.changed();
                this.focus();
            });
            const label = document.createElement('span');
            // Labels are trusted html built by the server.
            label.innerHTML = item.label;
            pill.append(remove, label);
            return pill;
        });

        const open = document.createElement('button');
        open.type = 'button';
        open.className = 'btn btn-sm btn-outline-secondary';
        open.dataset.pickerOpen = '1';
        if (!this.selected.length) {
            open.textContent = '+ ' + strings.pickerSelect;
        } else {
            open.textContent = this.multiple ? '+ ' + strings.pickerAdd : strings.pickerChange;
        }
        open.addEventListener('click', () => void this.open(open));
        nodes.push(open);

        container.replaceChildren(...nodes);
        this.syncUI();
    }

    /**
     * Store the selection in the hidden input and notify the form.
     */
    private changed(): void {
        if (this.input) {
            this.input.value = this.selected.map((item) => item.value).join(',');
        }
        this.render();
        this.form.markDirty();
        this.onInput();
    }

    /**
     * Open the panel.
     *
     * @param trigger button that gets focus back
     */
    private async open(trigger: HTMLButtonElement): Promise<void> {
        const strings = this.strings;
        if (this.panel || this.opening || !strings || !this.panelModule) {
            return;
        }
        this.opening = true;
        let Panel: ComponentType<PickerPanelProps>;
        try {
            const module = await loadModule(`@moodle/lms/${this.panelModule}`) as {default: ComponentType<PickerPanelProps>};
            Panel = module.default;
        } finally {
            this.opening = false;
        }
        this.panel = openPanel({
            label: strings.title,
            mount: (dialog, close) => mountReactApp(dialog, Panel, {
                source: this.source,
                multiple: this.multiple,
                selected: this.selected.map((item) => item.value),
                strings,
                onPick: (items: PickerItem[]) => {
                    const picked = items.map((item) => ({value: item.value, label: item.label, error: null}));
                    close();
                    if (!picked.length) {
                        return;
                    }
                    this.selected = this.multiple ? [...this.selected, ...picked] : picked.slice(0, 1);
                    this.changed();
                },
                onClose: close,
            }, {id: `tool_mulib-picker-${this.name}`}),
            onClosed: () => {
                this.panel = null;
                // The trigger was rendered again if the selection changed.
                if (trigger.isConnected) {
                    trigger.focus();
                } else {
                    this.focus();
                }
            },
        });
    }
}
