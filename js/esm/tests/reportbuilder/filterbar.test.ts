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
    ANY_VALUE, BOOLEAN_NO, BOOLEAN_YES, Filter, SELECT_EQUAL_TO, Strings, TEXT_CONTAINS, TEXT_IS_EMPTY, buildValues, describe,
    isActive,
} from '../../src/reportbuilder/filterbar';

const STRINGS: Strings = {
    add: 'Add', advanced: 'Advanced', remove: 'Remove', apply: 'Apply', useadvanced: 'Use advanced',
    search: 'Search', yes: 'Yes', no: 'No',
};

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
