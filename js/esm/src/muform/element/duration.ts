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
 * Browser side of the duration element.
 *
 * Native number inputs for the configured units, the value is their sum in seconds. The server
 * marks a required group with data-muform-required instead of marking every input.
 *
 * @module     tool_mulib/muform/element/duration
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import NativeElement from '../native';
import type {Value} from '../types';

/** Seconds per unit input. */
const SECONDS: Record<string, number> = {w: 604800, d: 86400, h: 3600, i: 60, s: 1};

export default class extends NativeElement {
    /**
     * Sum of the unit inputs in seconds, '0' when everything is empty.
     *
     * @returns seconds as text
     */
    override getValue(): Value {
        let total = 0;
        for (const input of this.wrapper.querySelectorAll<HTMLInputElement>('input[data-muform-unit]')) {
            const count = Number(input.value);
            if (input.value === '' || !Number.isFinite(count)) {
                continue;
            }
            total += count * (SECONDS[input.dataset.muformUnit ?? ''] ?? 0);
        }
        return String(total);
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
            if (required && this.getValue() === '0') {
                errors.push(this.getHint('required'));
            }
        }
        return errors;
    }
}
