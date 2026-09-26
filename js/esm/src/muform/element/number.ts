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
 * Browser side of the number element.
 *
 * The input is a text input with numeric keyboard hints, number inputs change values
 * on mouse wheel scrolling. The pattern attribute checks the format and decimal places
 * natively, the range comes from data attributes, the server checks everything again.
 *
 * @module     tool_mulib/muform/element/number
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import NativeElement from '../native';

export default class extends NativeElement {
    /**
     * Native constraints, then the number format and the range.
     *
     * @returns error messages, empty when valid
     */
    override validate(): string[] {
        const errors = super.validate();
        if (errors.length || this.state.hidden || this.state.disabled) {
            return errors;
        }
        const input = this.wrapper.querySelector<HTMLInputElement>('input[inputmode]');
        const text = input?.value.trim() ?? '';
        if (!input || text === '') {
            return errors;
        }
        // Format and decimal places are checked natively by the pattern, comma is a decimal separator.
        const normalised = text.replace(',', '.');
        const number = Number(normalised);
        const min = input.dataset.muformMin;
        const max = input.dataset.muformMax;
        if (!/^-?(\d+(\.\d*)?|\.\d+)$/.test(normalised) || !Number.isFinite(number)
            || (min !== undefined && number < Number(min)) || (max !== undefined && number > Number(max))) {
            errors.push(this.getHint('invalid'));
        }
        return errors;
    }
}
