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
 * Browser side of the checkboxes element.
 *
 * Native controls do the rest, only the required check is on the group
 * because no single checkbox can carry the required attribute.
 *
 * @module     tool_mulib/muform/element/checkboxes
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import NativeElement from '../native';

export default class extends NativeElement {
    /**
     * Native constraints, then the required check of the whole group.
     *
     * @returns error messages, empty when valid
     */
    override validate(): string[] {
        const errors = super.validate();
        if (errors.length === 0 && !this.state.hidden && !this.state.disabled) {
            const required = this.wrapper.querySelector('[data-muform-required]');
            const value = this.getValue();
            if (required && Array.isArray(value) && value.length === 0) {
                errors.push(this.getHint('required'));
            }
        }
        return errors;
    }
}
