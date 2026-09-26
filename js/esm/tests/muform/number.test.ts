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

import NumberElement from '../../src/muform/element/number';
import {fakeForm, form, wrap} from './fixtures';

/**
 * Number input as rendered by element/number.mustache.
 *
 * @param attributes extra input attributes
 * @returns html
 */
function fixture(attributes: string): string {
    return wrap('count', 'number',
        `<input type="text" inputmode="numeric" class="form-control" id="id_count" name="count"${attributes}>`);
}

/**
 * Validate a typed value.
 *
 * @param attributes extra input attributes
 * @param value typed text
 * @returns errors
 */
function validate(attributes: string, value: string): string[] {
    const formel = form(fixture(attributes));
    const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="count"]')!;
    const element = new NumberElement(wrapper, fakeForm(formel));
    wrapper.querySelector<HTMLInputElement>('input')!.value = value;
    return element.validate();
}

describe('tool_mulib/muform/element/number', () => {
    it('renders no spinner and keeps the text value', () => {
        const formel = form(fixture(''));
        const wrapper = formel.querySelector<HTMLElement>('[data-muform-name="count"]')!;
        const element = new NumberElement(wrapper, fakeForm(formel));
        wrapper.querySelector<HTMLInputElement>('input')!.value = '12';
        expect(element.getValue()).toBe('12');
        expect(wrapper.querySelector('input[type="number"]')).toBeNull();
    });

    it('validates the format and the range', () => {
        expect(validate('', '')).toEqual([]);
        expect(validate('', '12')).toEqual([]);
        expect(validate('', ' -1.5 ')).toEqual([]);
        expect(validate('', 'abc')).toEqual(['Error']);
        expect(validate('', '1,5')).toEqual([]);
        expect(validate('', '1,5,1')).toEqual(['Error']);
        const decimals = ' pattern="([0-9]+([.,][0-9]{1,2}0*)?|[.,][0-9]{1,2}0*)"';
        expect(validate(decimals, '1,25')).toEqual([]);
        expect(validate(decimals, '1.250')).toEqual([]);
        expect(validate(decimals, '1.255')).toEqual(['Error']);
        expect(validate(decimals + ' data-muform-max="1.5"', '1,6')).toEqual(['Error']);
        expect(validate(' data-muform-min="0" data-muform-max="10"', '0')).toEqual([]);
        expect(validate(' data-muform-min="0" data-muform-max="10"', '10')).toEqual([]);
        expect(validate(' data-muform-min="0" data-muform-max="10"', '-1')).toEqual(['Error']);
        expect(validate(' data-muform-min="0" data-muform-max="10"', '11')).toEqual(['Error']);
        expect(validate(' pattern="[0-9]+"', '1.5')).toEqual(['Error']);
        expect(validate(' required', '')).toEqual(['Required']);
    });
});
