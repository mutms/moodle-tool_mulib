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
 * The input carries the timezone, language and default time it was rendered with
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

/** Settings of text interpretation. */
export interface DateTimeSettings {
    /** IANA timezone of the value. */
    timezone: string;
    /** Language of the display format. */
    lang: string;
    /** Hour of dates without time. */
    defaultHour: number;
    /** Minute of dates without time. */
    defaultMinute: number;
}

/**
 * Ask the server what the text means.
 *
 * @param text text to parse, empty means no value, integers are timestamps
 * @param settings timezone, language and default time
 * @returns the answer
 */
export async function normaliseText(text: string, settings: DateTimeSettings): Promise<Answer> {
    const pending = new Pending('tool_mulib/muform:datetime');
    try {
        const body = {
            text,
            timezone: settings.timezone,
            lang: settings.lang,
            defaulthour: settings.defaultHour,
            defaultminute: settings.defaultMinute,
        };
        const response = await Fetch.performPost('tool_mulib', 'muform/datetime', {body});
        return await response.json() as Answer;
    } finally {
        pending.resolve();
    }
}

/**
 * Ask the server what the text means, valid answers update the data attributes of the input.
 *
 * @param input the datetime text input
 * @param text text to parse, empty means no value
 * @returns the answer
 */
export async function normalise(input: HTMLInputElement, text: string): Promise<Answer> {
    const answer = await normaliseText(text, {
        timezone: input.dataset.muformDatetimeTimezone ?? '',
        lang: input.dataset.muformDatetimeLang ?? '',
        defaultHour: Number(input.dataset.muformDatetimeDefaulthour) || 0,
        defaultMinute: Number(input.dataset.muformDatetimeDefaultminute) || 0,
    });
    if (answer.valid) {
        input.dataset.muformDatetimeTimestamp = answer.timestamp === null ? '' : String(answer.timestamp);
        input.dataset.muformDatetimeComponents = answer.components ? JSON.stringify(answer.components) : '';
    }
    return answer;
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

/**
 * Date of a timestamp in a PHP date() format, only date characters Y, y, m, n, d, j, M and F are supported.
 *
 * @param timestamp seconds
 * @param timezone IANA timezone
 * @param lang language of month names
 * @param format date format such as "Y-m-d"
 * @returns text
 */
export function formatDate(timestamp: number, timezone: string, lang: string, format: string): string {
    const date = new Date(timestamp * 1000);
    let parts: Intl.DateTimeFormatPart[];
    try {
        parts = new Intl.DateTimeFormat('en-GB', {timeZone: timezone, year: 'numeric', month: 'numeric', day: 'numeric'})
            .formatToParts(date);
    } catch {
        parts = new Intl.DateTimeFormat('en-GB', {year: 'numeric', month: 'numeric', day: 'numeric'}).formatToParts(date);
    }
    const part = (type: string): number => Number(parts.find((item) => item.type === type)?.value);
    const year = part('year');
    const month = part('month');
    const day = part('day');
    const monthName = (style: 'short' | 'long'): string => {
        try {
            return new Intl.DateTimeFormat(lang, {month: style, timeZone: 'UTC'}).format(new Date(Date.UTC(2026, month - 1, 15)));
        } catch {
            return String(month);
        }
    };
    const pad = (value: number): string => String(value).padStart(2, '0');
    let result = '';
    for (let i = 0; i < format.length; i++) {
        const char = format[i];
        switch (char) {
            case 'Y': result += String(year); break;
            case 'y': result += pad(year % 100); break;
            case 'm': result += pad(month); break;
            case 'n': result += String(month); break;
            case 'd': result += pad(day); break;
            case 'j': result += String(day); break;
            case 'M': result += monthName('short'); break;
            case 'F': result += monthName('long'); break;
            case '\\':
                i++;
                result += format[i] ?? '';
                break;
            default: result += char;
        }
    }
    return result;
}
