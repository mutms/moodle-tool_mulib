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
 * Client of the datetime range normalisation endpoint, the browser never interprets dates itself.
 *
 * The input carries the timezone, language, default time and default duration it was rendered
 * with as data attributes, the endpoint answers with both timestamps, the text and a hint.
 *
 * @module     tool_mulib/muform/datetimerangeapi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fetch from '@moodle/lms/core/fetch';
import Pending from '@moodle/lms/core/pending';
import type {Components} from './datetimeapi';

/** Answer of the endpoint. */
export interface RangeAnswer {
    valid: boolean;
    text: string;
    start: number | null;
    end: number | null;
    duration: number | null;
    dayoffset: number | null;
    startcomponents: Components | null;
    endcomponents: Components | null;
    endtext: string;
    hint: string;
    timezone: string;
}

/**
 * Ask the server what the text means, valid answers update the data attributes of the input.
 *
 * @param input the datetimerange text input
 * @param text text to parse, empty means no value
 * @returns the answer
 */
export async function normaliseRange(input: HTMLInputElement, text: string): Promise<RangeAnswer> {
    const pending = new Pending('tool_mulib/muform:datetimerange');
    try {
        const body: Record<string, string | number> = {
            text,
            timezone: input.dataset.muformDatetimeTimezone ?? '',
            lang: input.dataset.muformDatetimeLang ?? '',
            defaulthour: Number(input.dataset.muformDatetimeDefaulthour) || 0,
            defaultminute: Number(input.dataset.muformDatetimeDefaultminute) || 0,
        };
        const duration = Number(input.dataset.muformDatetimerangeDefaultduration);
        if (duration > 0) {
            body.defaultduration = duration;
        }
        const response = await Fetch.performPost('tool_mulib', 'muform/datetimerange', {body});
        const answer = await response.json() as RangeAnswer;
        if (answer.valid) {
            input.dataset.muformDatetimerangeStart = answer.start === null ? '' : String(answer.start);
            input.dataset.muformDatetimerangeEnd = answer.end === null ? '' : String(answer.end);
            input.dataset.muformDatetimeComponents = answer.startcomponents ? JSON.stringify(answer.startcomponents) : '';
            input.dataset.muformDatetimerangeEndcomponents = answer.endcomponents ? JSON.stringify(answer.endcomponents) : '';
        }
        return answer;
    } finally {
        pending.resolve();
    }
}

/**
 * End date parts of the current value from the data attributes.
 *
 * @param input the datetimerange text input
 * @returns parts or null when there is no value
 */
export function readEndComponents(input: HTMLInputElement): Components | null {
    const json = input.dataset.muformDatetimerangeEndcomponents;
    if (!json) {
        return null;
    }
    try {
        return JSON.parse(json) as Components;
    } catch {
        return null;
    }
}
