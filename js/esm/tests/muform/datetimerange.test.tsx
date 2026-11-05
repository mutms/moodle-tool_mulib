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
import DatetimerangeElement from '../../src/muform/element/datetimerange';
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

const START = '{"year":2026,"month":11,"day":6,"hour":23,"minute":30,"second":0}';
const END = '{"year":2026,"month":11,"day":7,"hour":1,"minute":30,"second":0}';
const DURATIONS = '[{"seconds":1800,"label":"30 minutes"},{"seconds":3600,"label":"1 hour"},'
    + '{"seconds":7200,"label":"2 hours"}]';

/**
 * Input as rendered by element/datetimerange.mustache.
 *
 * @param value text
 * @param start start timestamp or empty
 * @param end end timestamp or empty
 * @param components start JSON or empty
 * @param endcomponents end JSON or empty
 * @param defaultduration seconds or empty
 * @returns html
 */
function fixture(value: string, start: string, end: string, components: string, endcomponents: string,
    defaultduration = ''): string {
    return wrap('session', 'datetimerange',
        '<div class="muform-datetime d-flex"><input type="text" class="form-control" id="id_session" name="session"'
        + ` value="${value}" data-muform-datetimerange-start="${start}" data-muform-datetimerange-end="${end}"`
        + ` data-muform-datetime-components='${components}' data-muform-datetimerange-endcomponents='${endcomponents}'`
        + ' data-muform-datetime-timezone="Europe/Prague" data-muform-datetime-lang="en"'
        + ' data-muform-datetime-defaulthour="0" data-muform-datetime-defaultminute="0"'
        + ` data-muform-datetimerange-defaultduration="${defaultduration}">`
        + '<span data-muform-datetime-picker data-muform-datetime-step="15" data-muform-datetime-firstday="1"'
        + ` data-muform-datetimerange-durations='${DURATIONS}'></span>`
        + '</div><div data-muform-datetimerange-hint>old hint</div>');
}

/**
 * Create the element.
 *
 * @param html fixture
 * @returns element, wrapper and change events
 */
async function make(html: string): Promise<{element: DatetimerangeElement; wrapper: HTMLElement; changes: ChangeDetail[]}> {
    const formel = form(html);
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="session"]')!;
    const changes: ChangeDetail[] = [];
    wrapper.addEventListener('muform:change', (event) => changes.push((event as CustomEvent<ChangeDetail>).detail));
    let element!: DatetimerangeElement;
    await act(async() => {
        element = new DatetimerangeElement(wrapper, fakeForm(formel));
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

/**
 * Hidden carrier by key.
 *
 * @param wrapper element wrapper
 * @param key start, end or text
 * @returns input
 */
function carrier(wrapper: HTMLElement, key: string): HTMLInputElement {
    return wrapper.querySelector<HTMLInputElement>(`input[type="hidden"][name="session[${key}]"]`)!;
}

/**
 * Change a select inside act().
 *
 * @param select the select
 * @param value new value
 */
async function choose(select: HTMLSelectElement, value: string): Promise<void> {
    await act(async() => {
        select.value = value;
        select.dispatchEvent(new Event('change', {bubbles: true}));
        await settle();
    });
}

beforeEach(() => {
    answers = [];
    bodies = [];
    performPost.mockImplementation(async(component: string, action: string, {body}: {body: object}) => {
        expect(component).toBe('tool_mulib');
        expect(action).toBe('muform/datetimerange');
        bodies.push(body);
        const answer = answers.shift() ?? {valid: false, text: '', start: null, end: null, hint: '', timezone: 'Europe/Prague'};
        return {json: async() => answer};
    });
    for (const [key, text] of Object.entries({
        'muform_pickdatetimerange': 'Pick', 'muform_today': 'Today', 'muform_clear': 'Clear', 'muform_apply': 'Apply',
        'muform_hour': 'Hour', 'muform_minute': 'Minute', 'muform_monthprev': 'Prev', 'muform_monthnext': 'Next',
        'muform_yearprev': 'Prev year', 'muform_yearnext': 'Next year', 'muform_rangestart': 'Start',
        'muform_rangeend': 'End', 'muform_duration': 'Duration',
    })) {
        mockString(key, 'tool_mulib', text);
    }
});

describe('tool_mulib/muform/element/datetimerange', () => {
    it('unhooks the text input and submits both timestamps instead', async() => {
        const {element, wrapper} = await make(fixture('2026-11-06 23:30 – 01:30 +1', '1793917800', '1793925000', START, END));
        const text = wrapper.querySelector<HTMLInputElement>('input[type="text"]')!;
        expect(text.hasAttribute('name')).toBe(false);
        expect(carrier(wrapper, 'start').value).toBe('1793917800');
        expect(carrier(wrapper, 'end').value).toBe('1793925000');
        expect(carrier(wrapper, 'text').value).toBe('');
        expect(element.getValue()).toBe('1793917800-1793925000');
        expect(wrapper.querySelector('button[aria-label="Pick"]')).not.toBeNull();
    });

    it('submits invalid server rendered text so the server reports it again', async() => {
        const {element, wrapper} = await make(fixture('sometime', '', '', '', ''));
        expect(carrier(wrapper, 'start').value).toBe('');
        expect(carrier(wrapper, 'text').value).toBe('sometime');
        expect(element.getValue()).toBe('sometime');

        const {element: empty} = await make(fixture('', '', '', '', ''));
        expect(empty.getValue()).toBeNull();
    });

    it('normalises typed text through the endpoint and shows the hint', async() => {
        const {element, wrapper, changes} = await make(fixture('', '', '', '', '', '3600'));
        const text = wrapper.querySelector<HTMLInputElement>('input[type="text"]')!;
        const hint = wrapper.querySelector<HTMLElement>('[data-muform-datetimerange-hint]')!;

        text.value = '2026-11-06 23:30 - 1:30';
        await fire(text, new Event('input', {bubbles: true}));
        expect(carrier(wrapper, 'text').value).toBe('2026-11-06 23:30 - 1:30');

        answers.push({
            valid: true, text: '2026-11-06 23:30 – 01:30 +1', start: 1793917800, end: 1793925000, duration: 7200,
            dayoffset: 1, startcomponents: JSON.parse(START), endcomponents: JSON.parse(END),
            endtext: '2026-11-07 01:30', hint: '2 hours, ends 2026-11-07 01:30', timezone: 'Europe/Prague',
        });
        await fire(text, new Event('change', {bubbles: true}));
        expect(bodies).toEqual([{
            text: '2026-11-06 23:30 - 1:30', timezone: 'Europe/Prague', lang: 'en', defaulthour: 0, defaultminute: 0,
            defaultduration: 3600,
        }]);
        expect(text.value).toBe('2026-11-06 23:30 – 01:30 +1');
        expect(carrier(wrapper, 'start').value).toBe('1793917800');
        expect(carrier(wrapper, 'end').value).toBe('1793925000');
        expect(carrier(wrapper, 'text').value).toBe('');
        expect(hint.textContent).toBe('2 hours, ends 2026-11-07 01:30');
        expect(text.dataset.muformDatetimerangeEnd).toBe('1793925000');
        expect(JSON.parse(text.dataset.muformDatetimerangeEndcomponents!)).toEqual(JSON.parse(END));
        expect(changes.at(-1)).toEqual({name: 'session', value: '1793917800-1793925000'});
        expect(element.validate()).toEqual([]);

        text.value = 'nonsense';
        await fire(text, new Event('input', {bubbles: true}));
        await fire(text, new Event('change', {bubbles: true}));
        expect(text.value).toBe('nonsense');
        expect(carrier(wrapper, 'start').value).toBe('');
        expect(carrier(wrapper, 'text').value).toBe('nonsense');
        expect(hint.textContent).toBe('');
        expect(element.validate()).toEqual(['Error']);
    });

    it('picks start, end time and duration and applies them', async() => {
        const {wrapper} = await make(fixture('2026-11-06 23:30 – 01:30 +1', '1793917800', '1793925000', START, END));
        await fire(wrapper.querySelector('button[aria-label="Pick"]')!, new MouseEvent('click', {bubbles: true}));
        const panel = wrapper.querySelector<HTMLElement>('.muform-datetime-panel')!;
        const startgroup = panel.querySelector<HTMLElement>('[role="group"][aria-label="Start"]')!;
        const endgroup = panel.querySelector<HTMLElement>('[role="group"][aria-label="End"]')!;
        const duration = panel.querySelector<HTMLSelectElement>('select[aria-label="Duration"]')!;
        expect(startgroup.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!.value).toBe('23');
        expect(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!.value).toBe('1');
        expect(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Minute"]')!.value).toBe('30');
        expect(endgroup.querySelector('[data-muform-datetimerange-dayoffset]')!.textContent).toBe('+1');
        expect(duration.value).toBe('120');
        expect(Array.from(duration.options).map((option) => option.textContent)).toEqual(['30 minutes', '1 hour', '2 hours']);

        // Changing the start keeps the duration.
        await choose(startgroup.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!, '10');
        expect(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!.value).toBe('12');
        expect(endgroup.querySelector('[data-muform-datetimerange-dayoffset]')).toBeNull();

        // End time sets the duration, earlier times mean the next day.
        await choose(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Minute"]')!, '45');
        expect(duration.value).toBe('135');
        expect(Array.from(duration.options).map((option) => option.textContent))
            .toEqual(['30 minutes', '1 hour', '2 hours', '2:15']);
        await choose(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!, '9');
        expect(duration.value).toBe(String(23 * 60 + 15));
        expect(endgroup.querySelector('[data-muform-datetimerange-dayoffset]')!.textContent).toBe('+1');

        await choose(duration, '60');
        expect(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!.value).toBe('11');
        expect(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Minute"]')!.value).toBe('30');

        answers.push({
            valid: true, text: '2026-11-06 10:30 – 11:30', start: 1793871000, end: 1793874600, duration: 3600,
            dayoffset: 0, startcomponents: null, endcomponents: null, endtext: '', hint: '1 hour', timezone: 'Europe/Prague',
        });
        const apply = Array.from(panel.querySelectorAll('button')).find((button) => button.textContent === 'Apply')!;
        await fire(apply, new MouseEvent('click', {bubbles: true}));
        expect(bodies.at(-1)).toMatchObject({text: '2026-11-06 10:30:00 – 2026-11-06 11:30:00'});
        expect(carrier(wrapper, 'start').value).toBe('1793871000');
        expect(wrapper.querySelector('.muform-datetime-panel')).toBeNull();
    });

    it('opens empty values with the default duration', async() => {
        const {wrapper} = await make(fixture('', '', '', '', '', '5400'));
        await fire(wrapper.querySelector('button[aria-label="Pick"]')!, new MouseEvent('click', {bubbles: true}));
        const panel = wrapper.querySelector<HTMLElement>('.muform-datetime-panel')!;
        const duration = panel.querySelector<HTMLSelectElement>('select[aria-label="Duration"]')!;
        expect(duration.value).toBe('90');
        const endgroup = panel.querySelector<HTMLElement>('[role="group"][aria-label="End"]')!;
        expect(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Hour"]')!.value).toBe('1');
        expect(endgroup.querySelector<HTMLSelectElement>('select[aria-label="Minute"]')!.value).toBe('30');

        // Double click applies the day with the duration.
        await fire(panel.querySelector('[data-muform-datetime-day="10"]')!, new MouseEvent('dblclick', {bubbles: true}));
        expect((bodies.at(-1) as {text: string}).text).toMatch(/^\d{4}-\d{2}-10 00:00:00 – \d{4}-\d{2}-10 01:30:00$/);
    });

    it('does nothing for frozen elements', async() => {
        const {element, wrapper} = await make(wrap('session', 'datetimerange', '<div class="form-control-plaintext">x</div>'));
        expect(element.getValue()).toBeNull();
        expect(wrapper.querySelector('input')).toBeNull();
    });
});
