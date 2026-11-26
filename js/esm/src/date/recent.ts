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
 * Recently used dates of the browser tab, latest first, offered in the date panel.
 *
 * @module     tool_mulib/date/recent
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Components} from './api';

/** Recently used date, or range when end is set. */
export interface RecentDate {
    text: string;
    start: Components;
    end?: Components;
}

/** Maximum number of remembered values of each kind. */
export const RECENT_LIMIT = 10;

const KEY_SINGLE = 'tool_mulib-date-recent';
const KEY_RANGE = 'tool_mulib-date-recentrange';

/**
 * Key of date parts, seconds are ignored.
 *
 * @param parts
 * @returns key
 */
const partsKey = (parts: Components): string => [parts.year, parts.month, parts.day, parts.hour, parts.minute].join('-');

/**
 * Key of an entry.
 *
 * @param entry
 * @returns key
 */
const entryKey = (entry: RecentDate): string => partsKey(entry.start) + (entry.end ? '/' + partsKey(entry.end) : '');

/**
 * Read a list.
 *
 * @param key
 * @returns entries
 */
const read = (key: string): RecentDate[] => {
    try {
        const list: unknown = JSON.parse(window.sessionStorage.getItem(key) ?? '[]');
        return Array.isArray(list) ? list.filter((entry: RecentDate) => entry?.text && entry.start) : [];
    } catch {
        return [];
    }
};

/**
 * Add an entry to the top of a list.
 *
 * @param key
 * @param entry
 */
const remember = (key: string, entry: RecentDate): void => {
    const id = entryKey(entry);
    const list = [entry, ...read(key).filter((item) => entryKey(item) !== id)].slice(0, RECENT_LIMIT);
    try {
        window.sessionStorage.setItem(key, JSON.stringify(list));
    } catch {
        // Storage is not available, nothing is remembered.
    }
};

/**
 * Remember a used date.
 *
 * @param text normalised text
 * @param parts date parts, null when there is no value
 */
export function rememberDate(text: string, parts: Components | null): void {
    if (text !== '' && parts) {
        remember(KEY_SINGLE, {text, start: parts});
    }
}

/**
 * Remember a used range.
 *
 * @param text normalised text
 * @param start start parts, null when there is no value
 * @param end end parts, null when there is no value
 */
export function rememberRange(text: string, start: Components | null, end: Components | null): void {
    if (text !== '' && start && end) {
        remember(KEY_RANGE, {text, start, end});
    }
}

/**
 * Recently used values, latest first.
 *
 * @param range ranges instead of dates
 * @returns entries
 */
export function recentDates(range = false): RecentDate[] {
    return read(range ? KEY_RANGE : KEY_SINGLE).filter((entry) => !range || entry.end);
}

/**
 * Do the parts match the entry start.
 *
 * @param entry
 * @param start
 * @param end
 * @returns match
 */
export function isRecent(entry: RecentDate, start: Components, end?: Components | null): boolean {
    return partsKey(entry.start) === partsKey(start) && (!entry.end || !end || partsKey(entry.end) === partsKey(end));
}
