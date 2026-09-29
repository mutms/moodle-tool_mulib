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
import Fetch from '@moodle/lms/core/fetch';
import DatetimeElement from '../../src/muform/element/datetime';
import type {ChangeDetail} from '../../src/muform/types';
import {fakeForm, form, wrap} from './fixtures';

jest.mock('@moodle/lms/core/fetch', () => ({
    __esModule: true,
    'default': {performPost: jest.fn()},
}));

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

/** Queued endpoint answers. */
let answers: object[] = [];

/** Recorded endpoint bodies. */
let bodies: object[] = [];

const performPost = Fetch.performPost as jest.Mock;

/**
 * Datetime input as rendered by element/datetime.mustache.
 *
 * @param value text
 * @param timestamp timestamp or empty
 * @param components JSON or empty
 * @returns html
 */
function fixture(value: string, timestamp: string, components: string): string {
    return wrap('starts', 'datetime',
        '<div class="muform-datetime d-flex"><input type="text" class="form-control" id="id_starts" name="starts"'
        + ` value="${value}" data-muform-datetime-timestamp="${timestamp}"`
        + ` data-muform-datetime-components='${components}' data-muform-datetime-timezone="Europe/Prague"`
        + ' data-muform-datetime-lang="en" data-muform-datetime-format="Y-m-d H:i:s">'
        + '<span data-muform-datetime-picker data-muform-datetime-step="15"></span></div>');
}

const COMPONENTS = '{"year":2026,"month":9,"day":26,"hour":19,"minute":28,"second":43}';

/**
 * Create the element.
 *
 * @param html fixture
 * @returns element, wrapper and change events
 */
async function make(html: string): Promise<{element: DatetimeElement; wrapper: HTMLElement; changes: ChangeDetail[]}> {
    const formel = form(html);
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="starts"]')!;
    const changes: ChangeDetail[] = [];
    wrapper.addEventListener('muform:change', (event) => changes.push((event as CustomEvent<ChangeDetail>).detail));
    let element!: DatetimeElement;
    await act(async() => {
        element = new DatetimeElement(wrapper, fakeForm(formel));
        await settle();
    });
    return {element, wrapper, changes};
}

/**
 * Wait for queued promises.
 */
async function settle(): Promise<void> {
    for (let i = 0; i < 5; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
}

/**
 * Dispatch an event inside act().
 *
 * @param target element
 * @param event event
 */
async function fire(target: EventTarget, event: Event): Promise<void> {
    await act(async() => {
        target.dispatchEvent(event);
        await settle();
    });
}

beforeEach(() => {
    answers = [];
    bodies = [];
    performPost.mockImplementation(async(component: string, action: string, {body}: {body: object}) => {
        expect(component).toBe('tool_mulib');
        expect(action).toBe('muform/datetime');
        bodies.push(body);
        const answer = answers.shift() ?? {valid: false, text: '', timestamp: null, components: null, timezone: 'Europe/Prague'};
        return {json: async() => answer};
    });
    for (const [key, text] of Object.entries({
        'muform_pickdatetime': 'Pick', 'muform_today': 'Today', 'muform_clear': 'Clear', 'muform_apply': 'Apply',
        'muform_hour': 'Hour', 'muform_minute': 'Minute', 'muform_monthprev': 'Prev', 'muform_monthnext': 'Next',
        'muform_yearprev': 'Prev year', 'muform_yearnext': 'Next year',
    })) {
        mockString(key, 'tool_mulib', text);
    }
});

describe('tool_mulib/muform/element/datetime', () => {
    it('unhooks the text input and submits the timestamp instead', async() => {
        const {element, wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS));
        const text = wrapper.querySelector<HTMLInputElement>('input[type="text"]')!;
        const carrier = wrapper.querySelector<HTMLInputElement>('input[type="hidden"]')!;
        expect(text.hasAttribute('name')).toBe(false);
        expect(carrier.name).toBe('starts');
        expect(carrier.value).toBe('1790443723');
        expect(element.getValue()).toBe('1790443723');
        expect(wrapper.querySelector('button[aria-label="Pick"]')).not.toBeNull();
    });

    it('keeps invalid server rendered text so the server reports it again', async() => {
        const {element, wrapper} = await make(fixture('sometime', '', ''));
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"]')!.value).toBe('sometime');
        expect(element.getValue()).toBe('sometime');

        const {element: empty} = await make(fixture('', '', ''));
        expect(empty.getValue()).toBeNull();
    });

    it('normalises typed text through the endpoint', async() => {
        const {element, wrapper, changes} = await make(fixture('', '', ''));
        const text = wrapper.querySelector<HTMLInputElement>('input[type="text"]')!;
        const carrier = wrapper.querySelector<HTMLInputElement>('input[type="hidden"]')!;

        text.value = 'tomorrow 10:00';
        await fire(text, new Event('input', {bubbles: true}));
        expect(carrier.value).toBe('tomorrow 10:00');

        answers.push({
            valid: true, text: '2026-09-27 10:00:00', timestamp: 1790496000,
            components: {year: 2026, month: 9, day: 27, hour: 10, minute: 0, second: 0}, timezone: 'Europe/Prague',
        });
        await fire(text, new Event('change', {bubbles: true}));
        expect(bodies).toEqual([{text: 'tomorrow 10:00', timezone: 'Europe/Prague', lang: 'en', format: 'Y-m-d H:i:s'}]);
        expect(text.value).toBe('2026-09-27 10:00:00');
        expect(carrier.value).toBe('1790496000');
        expect(text.dataset.muformDatetimeTimestamp).toBe('1790496000');
        expect(JSON.parse(text.dataset.muformDatetimeComponents!))
            .toEqual({year: 2026, month: 9, day: 27, hour: 10, minute: 0, second: 0});
        expect(changes.at(-1)).toEqual({name: 'starts', value: '1790496000'});
        expect(element.validate()).toEqual([]);

        text.value = 'nonsense';
        await fire(text, new Event('input', {bubbles: true}));
        await fire(text, new Event('change', {bubbles: true}));
        expect(text.value).toBe('nonsense');
        expect(carrier.value).toBe('nonsense');
        expect(text.dataset.muformDatetimeTimestamp).toBe('1790496000');
        expect(element.validate()).toEqual(['Error']);
        expect(changes.at(-1)).toEqual({name: 'starts', value: 'nonsense'});
    });

    it('picks a day and time and applies it', async() => {
        const {wrapper, changes} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS));
        const text = wrapper.querySelector<HTMLInputElement>('input[type="text"]')!;
        const trigger = wrapper.querySelector<HTMLButtonElement>('button[aria-label="Pick"]')!;

        await fire(trigger, new MouseEvent('click', {bubbles: true}));
        const panel = wrapper.querySelector<HTMLElement>('.muform-datetime-panel')!;
        expect(panel).not.toBeNull();
        expect(panel.querySelector('[aria-live]')!.textContent).toBe('September 2026');
        expect(panel.querySelector('[data-muform-datetime-day="26"]')!.getAttribute('aria-pressed')).toBe('true');
        expect(panel.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!.value).toBe('19');
        const minute = panel.querySelector<HTMLSelectElement>('select[aria-label="Minute"]')!;
        expect(minute.value).toBe('28');
        expect(Array.from(minute.options).map((option) => option.value)).toEqual(['0', '15', '28', '30', '45']);

        await fire(panel.querySelector('button[aria-label="Next"]')!, new MouseEvent('click', {bubbles: true}));
        expect(panel.querySelector('[aria-live]')!.textContent).toBe('October 2026');
        await fire(panel.querySelector('[data-muform-datetime-day="3"]')!, new MouseEvent('click', {bubbles: true}));
        expect(panel.querySelector('[data-muform-datetime-day="3"]')!.getAttribute('aria-pressed')).toBe('true');

        await act(async() => {
            const hour = panel.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!;
            hour.value = '8';
            hour.dispatchEvent(new Event('change', {bubbles: true}));
            minute.value = '15';
            minute.dispatchEvent(new Event('change', {bubbles: true}));
            await settle();
        });

        answers.push({
            valid: true, text: '2026-10-03 08:15:43', timestamp: 1791008143,
            components: {year: 2026, month: 10, day: 3, hour: 8, minute: 15, second: 43}, timezone: 'Europe/Prague',
        });
        const apply = Array.from(panel.querySelectorAll('button')).find((button) => button.textContent === 'Apply')!;
        await fire(apply, new MouseEvent('click', {bubbles: true}));
        expect(bodies.at(-1)).toEqual({text: '2026-10-03 08:15:43', timezone: 'Europe/Prague', lang: 'en', format: 'Y-m-d H:i:s'});
        expect(text.value).toBe('2026-10-03 08:15:43');
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"]')!.value).toBe('1791008143');
        expect(wrapper.querySelector('.muform-datetime-panel')).toBeNull();
        expect(changes.at(-1)).toEqual({name: 'starts', value: '1791008143'});
    });

    it('applies a double clicked day and closes only the calendar on Escape', async() => {
        const {wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS));
        const text = wrapper.querySelector<HTMLInputElement>('input[type="text"]')!;
        const trigger = wrapper.querySelector<HTMLButtonElement>('button[aria-label="Pick"]')!;

        await fire(trigger, new MouseEvent('click', {bubbles: true}));
        let panel = wrapper.querySelector<HTMLElement>('.muform-datetime-panel')!;
        const escape = new KeyboardEvent('keydown', {key: 'Escape', bubbles: true, cancelable: true});
        await fire(panel.querySelector('[data-muform-datetime-day="26"]')!, escape);
        // The default action would close a dialog around the form.
        expect(escape.defaultPrevented).toBe(true);
        expect(wrapper.querySelector('.muform-datetime-panel')).toBeNull();
        expect(text.value).toBe('2026-09-26 19:28:43');

        await fire(trigger, new MouseEvent('click', {bubbles: true}));
        panel = wrapper.querySelector<HTMLElement>('.muform-datetime-panel')!;
        answers.push({
            valid: true, text: '2026-09-10 19:28:43', timestamp: 1789061323,
            components: {year: 2026, month: 9, day: 10, hour: 19, minute: 28, second: 43}, timezone: 'Europe/Prague',
        });
        await fire(panel.querySelector('[data-muform-datetime-day="10"]')!, new MouseEvent('dblclick', {bubbles: true}));
        expect(bodies.at(-1)).toEqual({text: '2026-09-10 19:28:43', timezone: 'Europe/Prague', lang: 'en', format: 'Y-m-d H:i:s'});
        expect(text.value).toBe('2026-09-10 19:28:43');
        expect(wrapper.querySelector('.muform-datetime-panel')).toBeNull();
    });

    it('keeps errors and disabling away from the picker controls', async() => {
        const {element, wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS));
        const trigger = wrapper.querySelector<HTMLButtonElement>('button[aria-label="Pick"]')!;
        await fire(trigger, new MouseEvent('click', {bubbles: true}));
        await act(async() => {
            element.state.errors = ['Required'];
            element.syncUI();
            await settle();
        });
        const text = wrapper.querySelector<HTMLInputElement>('input[type="text"]')!;
        expect(text.classList.contains('is-invalid')).toBe(true);
        for (const control of wrapper.querySelectorAll('.muform-datetime-panel select, .muform-datetime-panel button')) {
            expect(control.classList.contains('is-invalid')).toBe(false);
            expect(control.hasAttribute('aria-invalid')).toBe(false);
            expect((control as HTMLSelectElement).disabled).toBe(false);
        }
        expect(element.validate()).toEqual([]);
    });

    it('clears the value and hides the picker when disabled', async() => {
        const {element, wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS));
        const trigger = wrapper.querySelector<HTMLButtonElement>('button[aria-label="Pick"]')!;
        await fire(trigger, new MouseEvent('click', {bubbles: true}));
        const panel = wrapper.querySelector<HTMLElement>('.muform-datetime-panel')!;
        answers.push({valid: true, text: '', timestamp: null, components: null, timezone: 'Europe/Prague'});
        const clear = Array.from(panel.querySelectorAll('button')).find((button) => button.textContent === 'Clear')!;
        await fire(clear, new MouseEvent('click', {bubbles: true}));
        expect(bodies.at(-1)).toMatchObject({text: ''});
        expect(wrapper.querySelector<HTMLInputElement>('input[type="text"]')!.value).toBe('');
        expect(element.getValue()).toBeNull();

        await act(async() => {
            element.state.disabled = true;
            element.syncUI();
            await settle();
        });
        expect(wrapper.querySelector('button[aria-label="Pick"]')).toBeNull();
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"]')!.disabled).toBe(true);
        expect(wrapper.querySelector<HTMLInputElement>('input[type="text"]')!.disabled).toBe(true);

        await act(async() => {
            element.state.disabled = false;
            element.syncUI();
            await settle();
        });
        expect(wrapper.querySelector('button[aria-label="Pick"]')).not.toBeNull();
        await act(async() => {
            element.destroy();
            await settle();
        });
        expect(wrapper.querySelector('button[aria-label="Pick"]')).toBeNull();
    });

    it('does nothing for frozen elements', async() => {
        const {element, wrapper} = await make(wrap('starts', 'datetime', '<div class="form-control-plaintext">2026</div>'));
        expect(element.getValue()).toBeNull();
        expect(wrapper.querySelector('input')).toBeNull();
    });
});
