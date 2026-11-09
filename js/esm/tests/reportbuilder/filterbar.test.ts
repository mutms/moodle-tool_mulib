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

import {
    ANY_VALUE, BOOLEAN_NO, BOOLEAN_YES, DATE_AFTER, DATE_BEFORE, DATE_CURRENT, DATE_EMPTY, DATE_LAST, DATE_NEXT, DATE_PAST,
    DATE_RANGE, DATE_UNIT_DAY, DateTimeSettings, Filter, SELECT_EQUAL_TO, Strings, TEXT_CONTAINS, TEXT_IS_EMPTY, buildValues,
    describe, formatUnitName, formatUnits, isActive,
} from '../../src/reportbuilder/filterbar';

const STRINGS: Strings = {
    add: 'Add', advanced: 'Advanced', remove: 'Remove', apply: 'Apply', useadvanced: 'Use advanced',
    search: 'Search', yes: 'Yes', no: 'No', rangefrom: 'from {$a}', rangeto: 'until {$a}', datefrom: 'Date from',
    dateto: 'Date to', fieldoperator: '{$a} operator', fieldvalue: '{$a} value', fieldunit: '{$a} unit',
    invalid: 'Invalid', invalidrange: 'Invalid range',
};

const UNIT_WEEK = 2;
const UNIT_MONTH = 3;

const SETTINGS: DateTimeSettings = {
    timezone: 'Europe/Prague', timezonelabel: '', lang: 'en', firstday: 1, hour12: false,
    units: [{value: '1', label: 'day(s)'}, {value: '2', label: 'week(s)'}, {value: '3', label: 'month(s)'}],
};

const date = (operator: number, extra: Partial<Filter> = {}): Filter => ({
    key: 'u:created', type: 'date', label: 'Created', operator, value: '1', unit: DATE_UNIT_DAY, from: 0, to: 0,
    fromtext: '', totext: '', options: [
        {value: String(DATE_RANGE), label: 'Range'}, {value: String(DATE_LAST), label: 'Last'},
        {value: String(DATE_CURRENT), label: 'Current'}, {value: String(DATE_NEXT), label: 'Next'},
        {value: String(DATE_BEFORE), label: 'Before'}, {value: String(DATE_AFTER), label: 'After'},
        {value: String(DATE_PAST), label: 'In the past'}, {value: String(DATE_EMPTY), label: 'Is empty'},
    ], ...extra,
});

const text = (operator: number, value: string): Filter => ({
    key: 'u:name', type: 'text', label: 'Name', operator, value, options: [],
});
const select = (operator: number, value: string): Filter => ({key: 'u:rank', type: 'select', label: 'Rank', operator, value,
    options: [{value: '1', label: 'Major'}, {value: '2', label: 'Colonel'}]});
const bool = (operator: number): Filter => ({key: 'u:active', type: 'boolean', label: 'Active', operator, value: '', options: []});

test('isActive', () => {
    expect(isActive(text(ANY_VALUE, 'x'))).toBe(false);
    expect(isActive(text(TEXT_CONTAINS, ' '))).toBe(false);
    expect(isActive(text(TEXT_CONTAINS, 'x'))).toBe(true);
    expect(isActive(select(SELECT_EQUAL_TO, '1'))).toBe(true);
    expect(isActive(bool(ANY_VALUE))).toBe(false);
    expect(isActive(bool(BOOLEAN_NO))).toBe(true);
});

test('describe', () => {
    expect(describe(text(TEXT_CONTAINS, 'Liberec'), STRINGS)).toBe('Liberec');
    expect(describe(select(SELECT_EQUAL_TO, '2'), STRINGS)).toBe('Colonel');
    expect(describe(bool(BOOLEAN_YES), STRINGS)).toBe('Yes');
    expect(describe(bool(BOOLEAN_NO), STRINGS)).toBe('No');
});

test('buildValues', () => {
    expect(buildValues([text(TEXT_CONTAINS, 'x'), select(ANY_VALUE, '1'), bool(BOOLEAN_YES), text(TEXT_CONTAINS, '')])).toEqual({
        'u:name_operator': ANY_VALUE,
        'u:name_value': '',
        'u:rank_operator': ANY_VALUE,
        'u:rank_value': '',
        'u:active_operator': BOOLEAN_YES,
    });
    expect(buildValues([text(TEXT_CONTAINS, 'x')])).toEqual({'u:name_operator': TEXT_CONTAINS, 'u:name_value': 'x'});
});

test('operators not offered by the bar are kept', () => {
    const startswith = {...text(5, 'Lib'), operatorlabel: 'Starts with'};
    expect(isActive(startswith)).toBe(true);
    expect(describe(startswith, STRINGS)).toBe('starts with Lib');
    expect(buildValues([startswith])).toEqual({'u:name_operator': 5, 'u:name_value': 'Lib'});

    const empty = {...text(TEXT_IS_EMPTY, ''), operatorlabel: 'Is empty'};
    expect(isActive(empty)).toBe(true);
    expect(describe(empty, STRINGS)).toBe('is empty');
    expect(buildValues([empty])).toEqual({'u:name_operator': TEXT_IS_EMPTY, 'u:name_value': ''});
});

test('other filters are kept or removed as a whole', () => {
    const date: Filter = {key: 'u:created', type: 'other', label: 'Created', operator: ANY_VALUE, value: '', options: [],
        values: {'u:created_operator': 3, 'u:created_from': 100, 'u:created_to': 200}, active: true};
    expect(isActive(date)).toBe(true);
    expect(describe(date, STRINGS)).toBe('');
    expect(buildValues([date, text(TEXT_CONTAINS, 'x')])).toEqual({
        'u:created_operator': 3, 'u:created_from': 100, 'u:created_to': 200, 'u:name_operator': TEXT_CONTAINS, 'u:name_value': 'x',
    });
    expect(buildValues([{...date, active: false}])).toEqual({});
});

test('date filters', () => {
    expect(isActive(date(ANY_VALUE))).toBe(false);
    expect(isActive(date(DATE_EMPTY))).toBe(true);
    expect(isActive(date(DATE_PAST))).toBe(true);
    expect(isActive(date(DATE_RANGE))).toBe(false);
    expect(isActive(date(DATE_RANGE, {from: 100}))).toBe(true);
    expect(isActive(date(DATE_RANGE, {to: 100}))).toBe(true);
    expect(isActive(date(DATE_LAST, {value: '0'}))).toBe(false);
    expect(isActive(date(DATE_NEXT, {value: '2'}))).toBe(true);
    expect(isActive(date(DATE_BEFORE, {value: '0'}))).toBe(true);

    expect(describe(date(DATE_EMPTY), STRINGS, SETTINGS)).toBe('is empty');
    expect(describe(date(DATE_PAST), STRINGS, SETTINGS)).toBe('in the past');
    expect(describe(date(DATE_LAST, {value: '2', unit: UNIT_WEEK}), STRINGS, SETTINGS)).toBe('last 2 weeks');
    expect(describe(date(DATE_NEXT, {value: '1'}), STRINGS, SETTINGS)).toBe('next 1 day');
    expect(describe(date(DATE_BEFORE, {value: '3', unit: UNIT_MONTH}), STRINGS, SETTINGS)).toBe('before 3 months');
    expect(describe(date(DATE_CURRENT, {value: '5', unit: UNIT_MONTH}), STRINGS, SETTINGS)).toBe('current month');
    const range = {from: 100, fromtext: '2026-01-01 00:00', to: 200, totext: '2026-01-31 23:59'};
    expect(describe(date(DATE_RANGE, range), STRINGS, SETTINGS)).toBe('2026-01-01 00:00 \u2013 2026-01-31 23:59');
    expect(describe(date(DATE_RANGE, {...range, to: 0}), STRINGS, SETTINGS)).toBe('from 2026-01-01 00:00');
    expect(describe(date(DATE_RANGE, {...range, from: 0}), STRINGS, SETTINGS)).toBe('until 2026-01-31 23:59');

    // Core form defaults are stored for unused parts.
    expect(buildValues([date(DATE_RANGE, {...range, value: '3', unit: UNIT_WEEK})])).toEqual({
        'u:created_operator': DATE_RANGE, 'u:created_value': 1, 'u:created_unit': DATE_UNIT_DAY,
        'u:created_from': 100, 'u:created_to': 200,
    });
    expect(buildValues([date(DATE_LAST, {...range, value: '3', unit: UNIT_WEEK})])).toEqual({
        'u:created_operator': DATE_LAST, 'u:created_value': 3, 'u:created_unit': UNIT_WEEK,
        'u:created_from': 0, 'u:created_to': 0,
    });
    expect(buildValues([date(DATE_CURRENT, {value: '3', unit: UNIT_MONTH})])).toEqual({
        'u:created_operator': DATE_CURRENT, 'u:created_value': 1, 'u:created_unit': UNIT_MONTH,
        'u:created_from': 0, 'u:created_to': 0,
    });
    expect(buildValues([date(DATE_EMPTY, {value: '3', unit: UNIT_MONTH})])).toEqual({
        'u:created_operator': DATE_EMPTY, 'u:created_value': 1, 'u:created_unit': DATE_UNIT_DAY,
        'u:created_from': 0, 'u:created_to': 0,
    });
    expect(buildValues([date(DATE_LAST, {value: '0'})])).toEqual({
        'u:created_operator': ANY_VALUE, 'u:created_value': 1, 'u:created_unit': DATE_UNIT_DAY,
        'u:created_from': 0, 'u:created_to': 0,
    });
});

test('date units are localised', () => {
    expect(formatUnits(5, DATE_UNIT_DAY, {...SETTINGS, lang: 'cs'})).toBe('5 dnů');
    expect(formatUnits(2, DATE_UNIT_DAY, {...SETTINGS, lang: 'cs'})).toBe('2 dny');
    expect(formatUnitName(UNIT_WEEK, {...SETTINGS, lang: 'de'})).toBe('Woche');
    expect(formatUnitName(UNIT_MONTH, {...SETTINGS, lang: 'cs'})).toBe('měsíc');
    expect(formatUnits(2, UNIT_WEEK, {...SETTINGS, lang: 'en_us'})).toBe('2 weeks');
    // Unknown languages fall back to unit labels.
    expect(formatUnits(2, UNIT_WEEK, {...SETTINGS, lang: '!!'})).toBe('2 week(s)');
    expect(formatUnitName(UNIT_WEEK, {...SETTINGS, lang: '!!'})).toBe('week(s)');
});
