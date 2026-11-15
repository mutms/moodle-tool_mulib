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

import {act} from 'react';
import PickerElement, {setPanelLoader} from '../../src/muform/pickerelement';
import {labelText, PANEL_CLASS, type PickerPanelProps} from '../../src/muform/pickerpanel';
import type {ChangeDetail} from '../../src/muform/types';
import {fakeForm, form, wrap} from './fixtures';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

// The jsdom environment has no modal dialogs.
if (!HTMLDialogElement.prototype.showModal) {
    HTMLDialogElement.prototype.showModal = function(this: HTMLDialogElement): void {
        this.open = true;
    };
    HTMLDialogElement.prototype.close = function(this: HTMLDialogElement): void {
        this.open = false;
        this.dispatchEvent(new Event('close'));
    };
}

/** Props of the last mounted panel. */
let lastProps: PickerPanelProps | null = null;

/** Requested panel modules. */
let loaded: string[] = [];

/** Elements to destroy after each test. */
let mounted: {destroy(): void}[] = [];

/**
 * Fake panel offering two fixed items.
 *
 * @param props
 * @returns panel
 */
function FakePanel(props: PickerPanelProps) {
    lastProps = props;
    return (
        <div data-fake-panel>
            <h2>{props.strings.title}</h2>
            <button type="button" data-pick="one"
                onClick={() => props.onPick([{value: 'unit:1', label: '<b>One</b>'}])}>one</button>
            <button type="button" data-pick="two" onClick={() => props.onPick([
                {value: '2', label: 'Two'}, {value: '3', label: 'Three &amp; more'},
            ])}>two</button>
            <button type="button" data-close onClick={props.onClose}>close</button>
        </div>
    );
}

/**
 * Markup as rendered by element/picker.mustache.
 *
 * @param type picker or pickermany
 * @param selected selected JSON
 * @param value hidden value
 * @returns html
 */
function fixture(type: string, selected: string, value: string): string {
    const strings = {title: 'Pick', pickerAdd: 'Add', pickerChange: 'Change', pickerRemove: 'Remove', pickerSelect: 'Select'};
    return wrap('items', type,
        `<input type="hidden" id="id_items" name="items" value="${value}">`
        + `<div class="muform-picker" data-picker-panel="tool_x/fake/panel"`
        + ` data-picker-source='{"class":"tool_x\\\\muform\\\\picker\\\\fake","args":[1]}'`
        + ` data-picker-selected='${selected}' data-picker-strings='${JSON.stringify(strings)}'`
        + (type === 'pickermany' ? ' data-picker-multiple="1"' : '') + '></div>');
}

/**
 * Create the element.
 *
 * @param type picker or pickermany
 * @param selected selected JSON
 * @param value hidden value
 * @returns element, wrapper and change events
 */
async function make(type: string, selected = '[]', value = '') {
    const formel = form(fixture(type, selected, value));
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="items"]')!;
    const changes: ChangeDetail[] = [];
    wrapper.addEventListener('muform:change', (event) => changes.push((event as CustomEvent<ChangeDetail>).detail));
    const element = new PickerElement(wrapper, fakeForm(formel));
    mounted.push(element);
    return {element, wrapper, changes};
}

/**
 * Click and wait.
 *
 * @param node
 */
async function click(node: Element | null): Promise<void> {
    await act(async() => {
        (node as HTMLElement).click();
        for (let i = 0; i < 5; i++) {
            await new Promise((resolve) => setTimeout(resolve, 0));
        }
    });
}

/**
 * The open panel dialog.
 *
 * @returns dialog or null
 */
const panel = (): HTMLDialogElement | null => document.querySelector<HTMLDialogElement>(`dialog.${PANEL_CLASS}`);

beforeEach(() => {
    lastProps = null;
    loaded = [];
    setPanelLoader(async(specifier: string) => {
        loaded.push(specifier);
        return {'default': FakePanel};
    });
});

afterEach(async() => {
    await act(async() => {
        mounted.forEach((element) => element.destroy());
    });
    mounted = [];
});

describe('tool_mulib/muform/pickerelement', () => {
    it('renders pills of the server state', async() => {
        const {element, wrapper} = await make('pickermany',
            '[{"value":"2","label":"Two"},{"value":"3","label":"<b>Three</b>","error":"Not allowed"}]', '2,3');
        const pills = wrapper.querySelectorAll<HTMLElement>('[data-picker-pill]');
        expect(pills).toHaveLength(2);
        expect(pills[1].innerHTML).toContain('<b>Three</b>');
        expect(pills[1].className).toContain('text-bg-danger');
        expect(pills[1].title).toBe('Not allowed');
        expect(pills[1].querySelector('button')!.getAttribute('aria-label')).toBe('Remove: Three');
        expect(wrapper.querySelector('[data-picker-open]')!.textContent).toBe('+ Add');
        expect(element.getValue()).toEqual(['2', '3']);
    });

    it('picks one value', async() => {
        const {element, wrapper, changes} = await make('picker');
        expect(element.getValue()).toBeNull();
        expect(wrapper.querySelector('[data-picker-open]')!.textContent).toBe('+ Select');
        await click(wrapper.querySelector('[data-picker-open]'));
        expect(loaded).toEqual(['@moodle/lms/tool_x/fake/panel']);
        expect(panel()!.getAttribute('aria-label')).toBe('Pick');
        expect(lastProps!.multiple).toBe(false);
        expect(lastProps!.source).toEqual({'class': 'tool_x\\muform\\picker\\fake', args: [1]});
        expect(lastProps!.selected).toEqual([]);

        await click(panel()!.querySelector('[data-pick="one"]'));
        expect(panel()).toBeNull();
        expect(element.getValue()).toBe('unit:1');
        expect(wrapper.querySelector<HTMLInputElement>('input[name="items"]')!.value).toBe('unit:1');
        expect(wrapper.querySelector('[data-picker-open]')!.textContent).toBe('Change');
        expect(changes.length).toBeGreaterThan(0);

        // Another pick replaces the value.
        await click(wrapper.querySelector('[data-picker-open]'));
        expect(lastProps!.selected).toEqual(['unit:1']);
        await click(panel()!.querySelector('[data-pick="two"]'));
        expect(element.getValue()).toBe('2');
    });

    it('adds and removes several values', async() => {
        const {element, wrapper} = await make('pickermany', '[{"value":"unit:1","label":"One"}]', 'unit:1');
        await click(wrapper.querySelector('[data-picker-open]'));
        expect(lastProps!.multiple).toBe(true);
        expect(lastProps!.selected).toEqual(['unit:1']);
        await click(panel()!.querySelector('[data-pick="two"]'));
        expect(element.getValue()).toEqual(['unit:1', '2', '3']);
        expect(wrapper.querySelector<HTMLInputElement>('input[name="items"]')!.value).toBe('unit:1,2,3');

        await click(wrapper.querySelector('[data-picker-pill="2"] button'));
        expect(element.getValue()).toEqual(['unit:1', '3']);
        expect(wrapper.querySelector<HTMLInputElement>('input[name="items"]')!.value).toBe('unit:1,3');
    });

    it('closes without changes', async() => {
        const {element, wrapper} = await make('picker', '[{"value":"5","label":"Five"}]', '5');
        await click(wrapper.querySelector('[data-picker-open]'));
        await click(panel()!.querySelector('[data-close]'));
        expect(panel()).toBeNull();
        expect(element.getValue()).toBe('5');
    });

    it('converts label html to text', () => {
        expect(labelText(' <b>Jane</b>\n  Doe ')).toBe('Jane Doe');
    });
});
