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
 * Browser side of the collapsible section element, a details element with children of their own.
 *
 * Expanded is the default, a state collapsed by the user is kept in session storage under the state key
 * of the section. Sections rendered with errors stay expanded, the form expands sections with errors
 * after a failed submission.
 *
 * @module     tool_mulib/muform/element/collapsible
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Element from '../element';
import type {FormApi} from '../types';

/** Prefix of session storage keys of sections collapsed by the user. */
const STORAGE_PREFIX = 'tool_mulib-collapsed-';

export default class extends Element {
    /**
     * Apply the remembered state and remember states changed by the user.
     *
     * @param wrapper the details element
     * @param form the form API
     */
    constructor(wrapper: HTMLElement, form: FormApi) {
        super(wrapper, form);
        if (!(wrapper instanceof HTMLDetailsElement)) {
            return;
        }
        if (wrapper.dataset.muformCollapsibleErrors !== '1' && this.isStoredCollapsed()) {
            wrapper.open = false;
        }
        // Only clicks and keyboard on the summary are user toggles, the toggle event fires for code too.
        wrapper.querySelector(':scope > summary')?.addEventListener('click', (event) => {
            if (!event.defaultPrevented) {
                this.store(wrapper.open);
            }
        });
        // Expand and collapse all links of the form.
        wrapper.addEventListener('muform:collapsible', (event) => {
            const open = (event as CustomEvent<{open: boolean}>).detail.open;
            wrapper.open = open;
            this.store(!open);
        });
    }

    /**
     * Was the section collapsed by the user? Storage may be unavailable, everything is expanded then.
     *
     * @returns true when collapsed
     */
    private isStoredCollapsed(): boolean {
        const key = this.wrapper.dataset.muformStatekey;
        if (!key) {
            return false;
        }
        try {
            return window.sessionStorage.getItem(STORAGE_PREFIX + key) === '1';
        } catch {
            return false;
        }
    }

    /**
     * Remember the state, expanded is the default and is not stored.
     *
     * @param collapsed new state
     */
    private store(collapsed: boolean): void {
        const key = this.wrapper.dataset.muformStatekey;
        if (!key) {
            return;
        }
        try {
            if (collapsed) {
                window.sessionStorage.setItem(STORAGE_PREFIX + key, '1');
            } else {
                window.sessionStorage.removeItem(STORAGE_PREFIX + key);
            }
        } catch {
            // Not remembered.
        }
    }
}
