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

/**
 * Open date panel.
 *
 * @returns panel content
 */
const panel = (): HTMLElement | null => document.querySelector<HTMLElement>('dialog[open] .tool_mulib-date-panel');

/**
 * Selected value of a time select.
 *
 * @param root panel
 * @param label select label
 * @returns value
 */
const listValue = (root: HTMLElement, label: string): string | null => root.querySelector<HTMLSelectElement>(
    `select[aria-label="${label}"]`)?.value ?? null;

/**
 * Options of a time select.
 *
 * @param root panel
 * @param label select label
 * @returns values and labels
 */
const listOptions = (root: HTMLElement, label: string): {values: string[]; labels: string[]} => {
    const options = Array.from(root.querySelector<HTMLSelectElement>(`select[aria-label="${label}"]`)!.options);
    return {values: options.map((option) => option.value), labels: options.map((option) => option.textContent ?? '')};
};

/**
 * Change a time select inside act().
 *
 * @param root panel
 * @param label select label
 * @param value
 */
async function pickTime(root: HTMLElement, label: string, value: string): Promise<void> {
    await act(async() => {
        const select = root.querySelector<HTMLSelectElement>(`select[aria-label="${label}"]`)!;
        select.value = value;
        select.dispatchEvent(new Event('change', {bubbles: true}));
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

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
 * @param options default time, first day of week and timezone
 * @param options.defaulthour default hour
 * @param options.defaultminute default minute
 * @param options.firstday first day of week, 0 is Sunday
 * @param options.timezone timezone of the element
 * @param options.hour12 hours with AM and PM
 * @param options.clearable optional element with clear button
 * @returns html
 */
function fixture(value: string, timestamp: string, components: string,
    {
        defaulthour = 0, defaultminute = 0, firstday = 1, timezone = 'Europe/Prague', hour12 = false, clearable = false,
    } = {}): string {
    return wrap('starts', 'datetime',
        '<div class="muform-datetime d-flex"><input type="text" class="form-control" id="id_starts" name="starts"'
        + ` value="${value}" data-muform-datetime-timestamp="${timestamp}"`
        + ` data-muform-datetime-components='${components}' data-muform-datetime-timezone="${timezone}"`
        + ' data-muform-datetime-lang="en"'
        + ` data-muform-datetime-defaulthour="${defaulthour}" data-muform-datetime-defaultminute="${defaultminute}">`
        + `<span data-muform-datetime-picker data-muform-datetime-step="15" data-muform-datetime-firstday="${firstday}"`
        + `${hour12 ? ' data-muform-datetime-hour12="1"' : ''}${clearable ? ' data-muform-datetime-clearable="1"' : ''}></span>`
        + '</div>');
}

/** Body fields sent with every request of the default fixture. */
const BODY = {timezone: 'Europe/Prague', lang: 'en', defaulthour: 0, defaultminute: 0};

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
        expect(action).toBe('date/datetime');
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
    mockString('closebuttontitle', 'core', 'Close');
});

afterEach(() => {
    for (const dialog of document.querySelectorAll('dialog')) {
        dialog.close();
        dialog.remove();
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

        text.value = '2026-09-27 10:00';
        await fire(text, new Event('input', {bubbles: true}));
        expect(carrier.value).toBe('2026-09-27 10:00');

        answers.push({
            valid: true, text: '2026-09-27 10:00:00', timestamp: 1790496000,
            components: {year: 2026, month: 9, day: 27, hour: 10, minute: 0, second: 0}, timezone: 'Europe/Prague',
        });
        await fire(text, new Event('change', {bubbles: true}));
        expect(bodies).toEqual([{text: '2026-09-27 10:00', ...BODY}]);
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
        const root = panel()!;
        expect(root).not.toBeNull();
        expect(root.querySelector('.tool_mulib-date-calendar [aria-live]')!.textContent).toBe('September 2026');
        expect(root.querySelector('[data-tool-mulib-date-day="26"]')!.getAttribute('aria-pressed')).toBe('true');
        expect(listValue(root, 'Hour')).toBe('19');
        expect(listValue(root, 'Minute')).toBe('28');
        expect(listOptions(root, 'Minute').values).toEqual(['0', '15', '28', '30', '45']);

        await fire(root.querySelector('button[aria-label="Next"]')!, new MouseEvent('click', {bubbles: true}));
        expect(root.querySelector('.tool_mulib-date-calendar [aria-live]')!.textContent).toBe('October 2026');
        await fire(root.querySelector('[data-tool-mulib-date-day="3"]')!, new MouseEvent('click', {bubbles: true}));
        expect(root.querySelector('[data-tool-mulib-date-day="3"]')!.getAttribute('aria-pressed')).toBe('true');

        await pickTime(root, 'Hour', '8');
        await pickTime(root, 'Minute', '15');

        answers.push({
            valid: true, text: '2026-10-03 08:15:43', timestamp: 1791008143,
            components: {year: 2026, month: 10, day: 3, hour: 8, minute: 15, second: 43}, timezone: 'Europe/Prague',
        });
        await fire(root.querySelector('[data-tool-mulib-date-apply]')!, new MouseEvent('click', {bubbles: true}));
        expect(bodies.at(-1)).toEqual({text: '2026-10-03 08:15:43', ...BODY});
        expect(text.value).toBe('2026-10-03 08:15:43');
        expect(wrapper.querySelector<HTMLInputElement>('input[type="hidden"]')!.value).toBe('1791008143');
        expect(panel()).toBeNull();
        expect(changes.at(-1)).toEqual({name: 'starts', value: '1791008143'});
    });

    it('applies a double clicked day and closes without changes', async() => {
        const {wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS));
        const text = wrapper.querySelector<HTMLInputElement>('input[type="text"]')!;
        const trigger = wrapper.querySelector<HTMLButtonElement>('button[aria-label="Pick"]')!;

        await fire(trigger, new MouseEvent('click', {bubbles: true}));
        let root = panel()!;
        await fire(root.querySelector('[data-tool-mulib-date-day="12"]')!, new MouseEvent('click', {bubbles: true}));
        await fire(root.querySelector('button[aria-label="Close"]')!, new MouseEvent('click', {bubbles: true}));
        expect(panel()).toBeNull();
        expect(text.value).toBe('2026-09-26 19:28:43');

        await fire(trigger, new MouseEvent('click', {bubbles: true}));
        root = panel()!;
        answers.push({
            valid: true, text: '2026-09-10 19:28:43', timestamp: 1789061323,
            components: {year: 2026, month: 9, day: 10, hour: 19, minute: 28, second: 43}, timezone: 'Europe/Prague',
        });
        await fire(root.querySelector('[data-tool-mulib-date-day="10"]')!, new MouseEvent('dblclick', {bubbles: true}));
        expect(bodies.at(-1)).toEqual({text: '2026-09-10 19:28:43', ...BODY});
        expect(text.value).toBe('2026-09-10 19:28:43');
        expect(panel()).toBeNull();
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
        for (const control of panel()!.querySelectorAll('button, select')) {
            expect(control.classList.contains('is-invalid')).toBe(false);
            expect(control.hasAttribute('aria-invalid')).toBe(false);
            expect(control.hasAttribute('disabled')).toBe(false);
        }
        expect(element.validate()).toEqual([]);
    });

    it('clears the value and hides the picker when disabled', async() => {
        const {element, wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS));
        const trigger = wrapper.querySelector<HTMLButtonElement>('button[aria-label="Pick"]')!;
        await fire(trigger, new MouseEvent('click', {bubbles: true}));
        answers.push({valid: true, text: '', timestamp: null, components: null, timezone: 'Europe/Prague'});
        const clear = Array.from(panel()!.querySelectorAll('button')).find((button) => button.textContent === 'Clear')!;
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

    it('clears optional values with the clear button', async() => {
        const {element, wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS, {clearable: true}));
        const box = wrapper.querySelector<HTMLElement>('.muform-datetime')!;
        const clear = wrapper.querySelector<HTMLButtonElement>('button[data-muform-datetime-clear]')!;
        expect(clear.getAttribute('aria-label')).toBe('Clear');
        expect(box.classList.contains('muform-datetime-empty')).toBe(false);
        answers.push({valid: true, text: '', timestamp: null, components: null, timezone: 'Europe/Prague'});
        await fire(clear, new MouseEvent('click', {bubbles: true}));
        expect(bodies.at(-1)).toMatchObject({text: ''});
        expect(element.getValue()).toBeNull();
        expect(box.classList.contains('muform-datetime-empty')).toBe(true);

        const {wrapper: empty} = await make(fixture('', '', '', {clearable: true}));
        expect(empty.querySelector('.muform-datetime')!.classList.contains('muform-datetime-empty')).toBe(true);

        const {wrapper: required} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS));
        expect(required.querySelector('button[data-muform-datetime-clear]')).toBeNull();
    });

    it('starts the week on the day configured in Moodle', async() => {
        const weekdays = async(firstday: number): Promise<string[]> => {
            const {wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS, {firstday}));
            await fire(wrapper.querySelector('button[aria-label="Pick"]')!, new MouseEvent('click', {bubbles: true}));
            const weekdays = Array.from(panel()!.querySelectorAll('thead th')).map((th) => th.textContent ?? '');
            panel()!.closest('dialog')!.close();
            return weekdays;
        };
        expect(await weekdays(1)).toEqual(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
        expect(await weekdays(0)).toEqual(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
        expect(await weekdays(6)).toEqual(['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri']);
    });

    it('opens empty values on today in the element timezone with the default time', async() => {
        // 23:30 UTC on 4 November is already 5 November in Prague and still 4 November in New York.
        jest.useFakeTimers({now: new Date('2026-11-04T23:30:00Z'), doNotFake: ['setTimeout', 'queueMicrotask']});
        try {
            const {wrapper} = await make(fixture('', '', '', {defaulthour: 23, defaultminute: 59}));
            await fire(wrapper.querySelector('button[aria-label="Pick"]')!, new MouseEvent('click', {bubbles: true}));
            const root = panel()!;
            expect(root.querySelector('.tool_mulib-date-calendar [aria-live]')!.textContent).toBe('November 2026');
            expect(root.querySelector('[data-tool-mulib-date-day="5"]')!.getAttribute('aria-pressed')).toBe('true');
            expect(root.querySelector('[data-tool-mulib-date-day="5"]')!.getAttribute('aria-current')).toBe('date');
            expect(listValue(root, 'Hour')).toBe('23');
            expect(listValue(root, 'Minute')).toBe('59');
            expect(listOptions(root, 'Minute').values).toEqual(['0', '15', '30', '45', '59']);

            answers.push({
                valid: true, text: '2026-11-05 23:59:00', timestamp: 1793919540,
                components: {year: 2026, month: 11, day: 5, hour: 23, minute: 59, second: 0}, timezone: 'Europe/Prague',
            });
            await fire(root.querySelector('[data-tool-mulib-date-apply]')!, new MouseEvent('click', {bubbles: true}));
            expect(bodies.at(-1)).toEqual({...BODY, text: '2026-11-05 23:59:00', defaulthour: 23, defaultminute: 59});

            const {wrapper: ny} = await make(fixture('', '', '', {timezone: 'America/New_York'}));
            await fire(ny.querySelector('button[aria-label="Pick"]')!, new MouseEvent('click', {bubbles: true}));
            const nypanel = panel()!;
            expect(nypanel.querySelector('[data-tool-mulib-date-day="4"]')!.getAttribute('aria-pressed')).toBe('true');
            expect(listValue(nypanel, 'Hour')).toBe('0');
            expect(listValue(nypanel, 'Minute')).toBe('0');
        } finally {
            jest.useRealTimers();
        }
    });

    it('shows hours with AM and PM when the display format uses them', async() => {
        const hours = async(hour12: boolean): Promise<string[]> => {
            const {wrapper} = await make(fixture('2026-09-26 19:28:43', '1790443723', COMPONENTS, {hour12}));
            await fire(wrapper.querySelector('button[aria-label="Pick"]')!, new MouseEvent('click', {bubbles: true}));
            const root = panel()!;
            expect(listValue(root, 'Hour')).toBe('19');
            const labels = listOptions(root, 'Hour').labels;
            root.closest('dialog')!.close();
            return labels;
        };
        const twelve = await hours(true);
        expect(twelve).toHaveLength(24);
        expect([twelve[0], twelve[1], twelve[11], twelve[12], twelve[19]].map((label) => label.replace(/\s/g, ' ')))
            .toEqual(['12 AM', '1 AM', '11 AM', '12 PM', '7 PM']);
        const plain = await hours(false);
        expect([plain[0], plain[9], plain[19]]).toEqual(['00', '09', '19']);
    });

    it('does nothing for frozen elements', async() => {
        const {element, wrapper} = await make(wrap('starts', 'datetime', '<div class="form-control-plaintext">2026</div>'));
        expect(element.getValue()).toBeNull();
        expect(wrapper.querySelector('input')).toBeNull();
    });
});
