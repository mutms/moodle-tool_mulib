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
 * Single value picker of the autocomplete element, a React island.
 *
 * The selected label is shown with its html in a form-control styled box,
 * activating it brings back the combobox input; typing searches the
 * tool_mulib autocomplete endpoint. Written independently of the token field on purpose,
 * this picker is expected to get its own better UI later.
 *
 * @module     tool_mulib/muform/autocompletepicker
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    autoUpdate,
    flip,
    offset,
    size,
    useDismiss,
    useFloating,
    useInteractions,
    useListNavigation,
    useRole,
} from '@floating-ui/react';
import Fetch from '@moodle/lms/core/fetch';
import {useEffect, useRef, useState} from 'react';

/** The selected value with its label html and an optional refusal. */
export interface Selected {
    value: string;
    label: string;
    error: string | null;
}

/** Search endpoint answer. */
interface Answer {
    list: {value: string; label: string}[] | null;
    overflow: boolean;
    maxitems: number;
}

/** Localised texts. */
export interface PickerStrings {
    noresults: string;
    searching: string;
    toomanyresults: string;
    clearselection: string;
    close: string;
}

/** Picker props. */
export interface PickerProps {
    url: string;
    source: {class: string; args: unknown[]};
    initial: Selected | null;
    name: string;
    id: string;
    placeholder: string;
    widthclass: string;
    disabled: boolean;
    strings: PickerStrings;
    onChange: (value: string | null) => void;
}

/** Results state of the listbox. */
type Results = {kind: 'idle'} | {kind: 'loading'} | {kind: 'overflow'} | {kind: 'list'; items: {value: string; label: string}[]};

/**
 * Plain text of a label html, shown in the input.
 *
 * @param html label html
 * @returns text
 */
function labelText(html: string): string {
    const div = document.createElement('div');
    div.innerHTML = html;
    return (div.textContent ?? '').replace(/\s+/g, ' ').trim();
}

/** Field button props. */
interface FieldButtonProps {
    open: boolean;
    chosen: boolean;
    disabled: boolean;
    strings: PickerStrings;
    onClose: () => void;
    onClear: () => void;
}

/**
 * Button after the field: closes the open list, otherwise clears the chosen value.
 *
 * @param props button props
 * @returns button or nothing
 */
function FieldButton({open, chosen, disabled, strings, onClose, onClear}: FieldButtonProps) {
    if (disabled || (!open && !chosen)) {
        return null;
    }
    const label = open ? strings.close : strings.clearselection;
    return (
        <button type="button" className="btn btn-outline-secondary" aria-label={label} title={label}
            data-muform-autocomplete-close={open ? '' : undefined}
            // Keep focus in the search field, the blur would close the list first.
            onMouseDown={(event) => event.preventDefault()}
            onClick={open ? onClose : onClear}>
            <i className="fa fa-times" aria-hidden="true"></i>
        </button>
    );
}

export default function Picker(props: PickerProps) {
    const {url, source, initial, name, id, placeholder, widthclass, disabled, strings, onChange} = props;
    const [selected, setSelected] = useState<Selected | null>(initial);
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState(false);
    const [open, setOpen] = useState(false);
    const [results, setResults] = useState<Results>({kind: 'idle'});
    const [active, setActive] = useState<number | null>(null);
    const listRef = useRef<(HTMLElement | null)[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);
    // Focus moved by the picker itself prepares typing, only the user opens the list.
    const quietFocus = useRef(false);
    const focusInput = (): void => {
        quietFocus.current = true;
        inputRef.current?.focus();
        quietFocus.current = false;
    };

    const {refs, floatingStyles, context} = useFloating({
        open,
        onOpenChange: (state) => {
            setOpen(state);
            if (!state) {
                setEditing(false);
                setQuery('');
            }
        },
        placement: 'bottom-start',
        // Fixed positioning escapes scrolling ancestors such as dialog bodies, which would clip the list.
        strategy: 'fixed',
        middleware: [offset(4), flip(), size({
            apply({rects, elements, availableHeight}) {
                elements.floating.style.minWidth = `${rects.reference.width}px`;
                // Long lists scroll inside the window instead of growing past it.
                elements.floating.style.maxHeight = `${Math.max(120, availableHeight - 8)}px`;
                elements.floating.style.overflowY = 'auto';
            },
        })],
        whileElementsMounted: autoUpdate,
    });
    const {getReferenceProps, getFloatingProps, getItemProps} = useInteractions([
        useRole(context, {role: 'listbox'}),
        // The close button must receive its click, an outside press would turn it into the clear button first.
        useDismiss(context, {
            outsidePress: (event) => !(event.target instanceof Element && event.target.closest('[data-muform-autocomplete-close]')),
        }),
        useListNavigation(context, {listRef, activeIndex: active, onNavigate: setActive, virtual: true, loop: true}),
    ]);

    // Keyboard navigation reaches options outside the visible part of a long list.
    useEffect(() => {
        if (active !== null) {
            listRef.current[active]?.scrollIntoView({block: 'nearest'});
        }
    }, [active]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        setResults({kind: 'loading'});
        const timer = setTimeout(async() => {
            const body = {source: source.class, args: source.args, query};
            try {
                const response = await Fetch.performPost('tool_mulib', 'muform/autocomplete', {body});
                const answer = await response.json() as Answer;
                if (answer.overflow || answer.list === null) {
                    setResults({kind: 'overflow'});
                } else {
                    setResults({kind: 'list', items: answer.list});
                    setActive(answer.list.length ? 0 : null);
                }
            } catch {
                setResults({kind: 'list', items: []});
            }
        }, 250);
        return () => clearTimeout(timer);
    }, [open, query, source, url]);

    const update = (next: Selected | null): void => {
        setSelected(next);
        onChange(next ? next.value : null);
    };

    const pick = (item: {value: string; label: string}): void => {
        update({value: item.value, label: item.label, error: null});
        setQuery('');
        setEditing(false);
        setOpen(false);
    };

    const clear = (): void => {
        update(null);
        setQuery('');
        setEditing(true);
        focusInput();
    };

    /** Close the list and cancel the search, the chosen value stays. */
    const closeSearch = (): void => {
        setOpen(false);
        setEditing(false);
        setQuery('');
    };

    const startEditing = (): void => {
        setEditing(true);
        setQuery('');
        setOpen(true);
    };

    useEffect(() => {
        if (editing) {
            focusInput();
        }
    }, [editing]);

    const onSelectedKeyDown = (event: React.KeyboardEvent<HTMLDivElement>): void => {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
            event.preventDefault();
            startEditing();
        } else if (event.key === 'Backspace' || event.key === 'Delete') {
            event.preventDefault();
            clear();
        }
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (open && results.kind === 'list' && active !== null && results.items[active]) {
                pick(results.items[active]);
            }
        } else if (event.key === 'Tab') {
            // Focus moves on, the list must not stay open over the rest of the form, the chosen value stays.
            closeSearch();
        } else if (event.key === 'Escape' && open) {
            // Only the list closes, a dialog around the form stays open.
            event.preventDefault();
            setOpen(false);
        }
    };

    const items = results.kind === 'list' ? results.items : [];
    const empty = results.kind === 'list' && items.length === 0;
    const showSelected = selected !== null && !editing;
    const inputClass = ['form-control', 'muform-autocomplete-input', widthclass, showSelected ? 'd-none' : '']
        .filter(Boolean).join(' ');
    const selectedClass = ['form-control', 'muform-autocomplete-selected', widthclass, selected?.error ? 'is-invalid' : '']
        .filter(Boolean).join(' ');

    return (
        <>
            <div className="d-flex align-items-start gap-1 w-100 muform-autocomplete-field">
                {showSelected && (
                    <div className={selectedClass} tabIndex={disabled ? -1 : 0} role="button" data-muform-autocomplete-chosen
                        aria-label={labelText(selected.label)} onClick={disabled ? undefined : startEditing}
                        onKeyDown={disabled ? undefined : onSelectedKeyDown}
                        dangerouslySetInnerHTML={{__html: selected.label}}></div>
                )}
                <input
                    ref={(node) => {
                        inputRef.current = node;
                        refs.setReference(node);
                    }}
                    type="text"
                    id={id}
                    className={inputClass}
                    role="combobox"
                    aria-autocomplete="list"
                    autoComplete="off"
                    placeholder={disabled ? undefined : placeholder}
                    disabled={disabled}
                    value={query}
                    {...getReferenceProps({
                        onChange: (event: React.ChangeEvent<HTMLInputElement>) => {
                            setEditing(true);
                            setQuery(event.target.value);
                            setOpen(true);
                        },
                        onFocus: () => {
                            setEditing(true);
                            if (!quietFocus.current) {
                                setOpen(true);
                            }
                        },
                        onClick: () => setOpen(true),
                        onKeyDown,
                    })}
                />
                <FieldButton open={open} chosen={selected !== null} disabled={disabled} strings={strings}
                    onClose={closeSearch} onClear={clear} />
                <input type="hidden" name={name} value={selected ? selected.value : ''} disabled={disabled} />
            </div>
            {selected?.error && <div className="form-text text-danger w-100">{selected.error}</div>}
            {open && (
                <ul ref={refs.setFloating} style={floatingStyles}
                    className="list-group shadow muform-autocomplete-listbox" {...getFloatingProps()}>
                    {results.kind === 'loading' && <li className="list-group-item text-muted">{strings.searching}</li>}
                    {results.kind === 'overflow' && <li className="list-group-item text-muted">{strings.toomanyresults}</li>}
                    {empty && <li className="list-group-item text-muted">{strings.noresults}</li>}
                    {items.map((item, index) => (
                        <li
                            key={item.value}
                            role="option"
                            aria-selected={active === index}
                            className={`list-group-item list-group-item-action${active === index ? ' active' : ''}`}
                            data-muform-autocomplete-option={item.value}
                            ref={(node) => {
                                listRef.current[index] = node;
                            }}
                            {...getItemProps({
                                onClick: () => pick(item),
                            })}
                            dangerouslySetInnerHTML={{__html: item.label}}
                        ></li>
                    ))}
                </ul>
            )}
        </>
    );
}
