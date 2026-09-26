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
 * Client of the datetime normalisation endpoint, the browser never interprets dates itself.
 *
 * The input carries the timezone, language and display format it was rendered with
 * as data attributes, the endpoint answers with the three representations of the date.
 *
 * @module     tool_mulib/muform/datetimeapi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fetch from '@moodle/lms/core/fetch';
import Pending from '@moodle/lms/core/pending';

/** Date parts in the timezone of the input. */
export interface Components {
    year: number;
    month: number;
    day: number;
    hour: number;
    minute: number;
    second: number;
}

/** Answer of the endpoint. */
export interface Answer {
    valid: boolean;
    text: string;
    timestamp: number | null;
    components: Components | null;
    timezone: string;
}

/**
 * Ask the server what the text means, valid answers update the data attributes of the input.
 *
 * @param input the datetime text input
 * @param text text to parse, empty means no value
 * @returns the answer
 */
export async function normalise(input: HTMLInputElement, text: string): Promise<Answer> {
    const pending = new Pending('tool_mulib/muform:datetime');
    try {
        const body = {
            text,
            timezone: input.dataset.muformDatetimeTimezone ?? '',
            lang: input.dataset.muformDatetimeLang ?? '',
            format: input.dataset.muformDatetimeFormat ?? '',
        };
        const response = await Fetch.performPost('tool_mulib', 'muform/datetime', {body});
        const answer = await response.json() as Answer;
        if (answer.valid) {
            input.dataset.muformDatetimeTimestamp = answer.timestamp === null ? '' : String(answer.timestamp);
            input.dataset.muformDatetimeComponents = answer.components ? JSON.stringify(answer.components) : '';
        }
        return answer;
    } finally {
        pending.resolve();
    }
}

/**
 * Date parts of the current value from the data attributes.
 *
 * @param input the datetime text input
 * @returns parts or null when there is no value
 */
export function readComponents(input: HTMLInputElement): Components | null {
    const json = input.dataset.muformDatetimeComponents;
    if (!json) {
        return null;
    }
    try {
        return JSON.parse(json) as Components;
    } catch {
        return null;
    }
}

/**
 * Text the server parses regardless of the display format.
 *
 * @param parts date parts
 * @returns Y-m-d H:i:s text
 */
export function formatComponents(parts: Components): string {
    const pad = (value: number): string => String(value).padStart(2, '0');
    return `${parts.year}-${pad(parts.month)}-${pad(parts.day)} ${pad(parts.hour)}:${pad(parts.minute)}:${pad(parts.second)}`;
}
