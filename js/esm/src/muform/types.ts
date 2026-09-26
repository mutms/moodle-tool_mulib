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
 * Shared types of the muform browser side.
 *
 * @module     tool_mulib/muform/types
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Element value as seen by display rules: string, list of strings or nothing. */
export type Value = string | string[] | null;

/** Display rule serialised by the PHP display manager. */
export interface DisplayRule {
    action: 'hide' | 'disable';
    target: string;
    dep: string;
    op: string;
    value: string | string[] | null;
}

/** Cosmetic state owned by every element, written by the orchestrator and projected by syncUI(). */
export interface ElementState {
    hidden: boolean;
    disabled: boolean;
    errors: string[];
    touched: boolean;
}

/** Events emitted by the form orchestrator. */
export type FormEvent = 'ready' | 'change' | 'submit' | 'invalid' | 'display';

/** Detail of the muform:change DOM event dispatched on element wrappers. */
export interface ChangeDetail {
    name: string;
    value: Value;
}

/** What elements may ask of the form. */
export interface FormApi {
    readonly form: HTMLFormElement;
    getValue(name: string): Value;
    submit(): void;
    reload(): void;
    cancel(): void;
    touched(name: string): void;
    on(event: FormEvent, handler: (detail: unknown) => void): () => void;
}

/** Shape of the default export of an element module. */
export interface ElementLike {
    readonly name: string;
    readonly wrapper: HTMLElement;
    readonly state: ElementState;
    getValue(): Value;
    validate(): string[];
    syncUI(): void;
    focus(): void;
    destroy(): void;
}

/** Constructor signature of the default export of an element module. */
export type ElementConstructor = new (wrapper: HTMLElement, form: FormApi) => ElementLike;
