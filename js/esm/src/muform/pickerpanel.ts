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
 * Shell of picker panels: a native modal dialog over the right half of the screen, the page stays visible on the left.
 *
 * It is not a muform dialog, so it also works over forms shown in muform dialogs: modal dialogs are in the
 * top layer and only the top one closes with Escape. Families of pickers render their panel component into it,
 * see tool_mulib\muform\picker\base.
 *
 * @module     tool_mulib/muform/pickerpanel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Source of a picker, the family endpoints create it again from the class name and arguments. */
export interface PickerSource {
    class: string;
    args: unknown[];
}

/** Picked value with its label html. */
export interface PickerItem {
    value: string;
    label: string;
}

/** Props of panel components, the default export of the panel module of a picker family. */
export interface PickerPanelProps<S = Record<string, string>> {
    /** Source of the values. */
    source: PickerSource;
    /** Several values may be picked. */
    multiple: boolean;
    /** Values already selected in the field, they must not be offered again. */
    selected: string[];
    /** Localised strings from the source, always with title. */
    strings: S;
    /** Picked values with labels. */
    onPick: (items: PickerItem[]) => void;
    /** Close without picking. */
    onClose: () => void;
}

/** Options of a panel. */
export interface PanelOptions {
    /** Accessible name of the dialog. */
    label: string;
    /** Render the content into the dialog, returns the function removing it. */
    mount: (dialog: HTMLDialogElement, close: () => void) => () => void;
    /** Called after the panel was closed and removed, e.g. to return focus. */
    onClosed?: () => void;
}

/** CSS class of panel dialogs. */
export const PANEL_CLASS = 'tool_mulib-picker-panel';

/**
 * Open a panel.
 *
 * @param options
 * @returns the dialog, close() closes the panel
 */
export const openPanel = (options: PanelOptions): HTMLDialogElement => {
    const dialog = document.createElement('dialog');
    dialog.className = PANEL_CLASS;
    dialog.setAttribute('aria-label', options.label);
    // The body is the only place not clipped or restyled by forms, modal dialogs are in the top layer anyway.
    document.body.append(dialog);

    let unmount: (() => void) | null = null;
    dialog.addEventListener('close', () => {
        unmount?.();
        dialog.remove();
        options.onClosed?.();
    });
    // A press on the backdrop closes the panel, presses inside never reach the dialog element itself.
    dialog.addEventListener('mousedown', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
    unmount = options.mount(dialog, () => dialog.close());
    dialog.showModal();
    return dialog;
};

/**
 * Plain text of a label html, for aria labels.
 *
 * @param html label html
 * @returns text
 */
export const labelText = (html: string): string => {
    const div = document.createElement('div');
    div.innerHTML = html;
    return (div.textContent ?? '').replace(/\s+/g, ' ').trim();
};
