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
 * Browser side of the dateinterval element.
 *
 * Native number inputs for the configured units, the value is the ISO 8601 duration they describe.
 * The server marks a required group with data-muform-required instead of marking every input.
 *
 * @module     tool_mulib/muform/element/dateinterval
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import NativeElement from '../native';
import type {Value} from '../types';

/** ISO designator per unit input, date part then time part. */
const DATE: Record<string, string> = {y: 'Y', m: 'M', w: 'W', d: 'D'};
const TIME: Record<string, string> = {h: 'H', i: 'M', s: 'S'};

export default class extends NativeElement {
    /**
     * Canonical ISO 8601 duration of the unit inputs, null when everything is empty or zero.
     *
     * @returns the interval string
     */
    override getValue(): Value {
        let date = '';
        let time = '';
        for (const input of this.wrapper.querySelectorAll<HTMLInputElement>('input[data-muform-unit]')) {
            const unit = input.dataset.muformUnit ?? '';
            const count = Number(input.value);
            if (input.value === '' || !Number.isInteger(count) || count <= 0) {
                continue;
            }
            if (unit in DATE) {
                date += `${count}${DATE[unit]}`;
            } else if (unit in TIME) {
                time += `${count}${TIME[unit]}`;
            }
        }
        if (date === '' && time === '') {
            return null;
        }
        return `P${date}${time === '' ? '' : `T${time}`}`;
    }

    /**
     * Native constraints of the inputs, then the required check of the whole group.
     *
     * @returns error messages, empty when valid
     */
    override validate(): string[] {
        const errors = super.validate();
        if (errors.length === 0 && !this.state.hidden && !this.state.disabled) {
            const required = this.wrapper.querySelector('[data-muform-required]');
            if (required && this.getValue() === null) {
                errors.push(this.getHint('required'));
            }
        }
        return errors;
    }
}
