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
 * Token field of the autocompletemany element, a React island.
 *
 * Selected values are pills before a combobox input, results come from the search function,
 * the autocompletemany element searches the tool_mulib autocomplete endpoint. Written independently
 * of the single value picker on purpose, the two may diverge.
 *
 * @module     tool_mulib/muform/autocompletemanypicker
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
import {getString} from '@moodle/lms/core/stringUtils';
import {useEffect, useRef, useState} from 'react';

/** A selected value with its label html and an optional refusal. */
export interface Selected {
    value: string;
    label: string;
    error: string | null;
}

/** Search answer, null list or overflow means too many results. */
export interface SearchAnswer {
    list: {value: string; label: string}[] | null;
    overflow: boolean;
}

/** Search function, excluded values are already selected. */
export type Search = (query: string, exclude: string[]) => Promise<SearchAnswer>;

/** Localised texts. */
export interface ManyPickerStrings {
    noresults: string;
    searching: string;
    toomanyresults: string;
    remove: string;
    close: string;
}

/** Picker props. */
export interface ManyPickerProps {
    search: Search;
    initial: Selected[];
    /** Name of hidden input with comma separated values, none when not set. */
    name?: string;
    id: string;
    placeholder: string;
    disabled: boolean;
    strings: ManyPickerStrings;
    onChange: (values: string[]) => void;
}

/** Results state of the listbox. */
type Results = {kind: 'idle'} | {kind: 'loading'} | {kind: 'overflow'} | {kind: 'list'; items: {value: string; label: string}[]};

/**
 * Plain text of a label html, for aria labels.
 *
 * @param html label html
 * @returns text
 */
function labelText(html: string): string {
    const div = document.createElement('div');
    div.innerHTML = html;
    return (div.textContent ?? '').replace(/\s+/g, ' ').trim();
}

/**
 * Localised texts of the picker.
 *
 * @returns strings
 */
export async function getManyPickerStrings(): Promise<ManyPickerStrings> {
    const [noresults, searching, toomanyresults, remove, close] = await Promise.all([
        getString('muform_noresults', 'tool_mulib'),
        getString('muform_searching', 'tool_mulib'),
        getString('muform_toomanyresults', 'tool_mulib'),
        getString('muform_remove', 'tool_mulib'),
        getString('closebuttontitle', 'core'),
    ]);
    return {noresults, searching, toomanyresults, remove, close};
}

export default function ManyPicker({search, initial, name, id, placeholder, disabled, strings, onChange}: ManyPickerProps) {
    const [selected, setSelected] = useState<Selected[]>(initial);
    const [query, setQuery] = useState('');
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
    const fieldRef = useRef<HTMLDivElement>(null);
    const focusPill = useRef<number | null>(null);

    const {refs, floatingStyles, context} = useFloating({
        open,
        onOpenChange: (state) => {
            setOpen(state);
            if (!state) {
                // The typed text was not chosen, it must not look like a value.
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
        // The close button must receive its click, an outside press would remove it first.
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
            try {
                const answer = await search(query, selected.map((item) => item.value));
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
    }, [open, query, selected, search]);

    const update = (next: Selected[]): void => {
        setSelected(next);
        onChange(next.map((item) => item.value));
    };

    const pick = (item: {value: string; label: string}): void => {
        update([...selected, {value: item.value, label: item.label, error: null}]);
        setQuery('');
        // Typing opens the list again, an open list would cover the form buttons.
        setOpen(false);
        focusInput();
    };

    /**
     * Remove a value; the search field gets focus only when typing is expected,
     * a pill button removal keeps focus on the pills while some are left.
     *
     * @param value removed value
     * @param frompill removed with the pill button
     */
    const remove = (value: string, frompill = false): void => {
        const index = selected.findIndex((item) => item.value === value);
        const next = selected.filter((item) => item.value !== value);
        update(next);
        if (frompill && next.length) {
            focusPill.current = Math.min(index, next.length - 1);
        } else {
            focusInput();
        }
    };

    useEffect(() => {
        if (focusPill.current === null) {
            return;
        }
        const buttons = fieldRef.current?.querySelectorAll<HTMLButtonElement>('[data-muform-autocomplete-pill] button');
        buttons?.[focusPill.current]?.focus();
        focusPill.current = null;
    }, [selected]);

    /** Close the list and discard the typed text. */
    const closeSearch = (): void => {
        setOpen(false);
        setQuery('');
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (open && results.kind === 'list' && active !== null && results.items[active]) {
                pick(results.items[active]);
            }
        } else if (event.key === 'Backspace' && query === '' && selected.length) {
            remove(selected[selected.length - 1].value);
        } else if (event.key === 'Tab') {
            // Focus moves on, the list must not stay open over the rest of the form,
            // the typed text was not chosen and must not look like a value.
            closeSearch();
        } else if (event.key === 'Escape' && open) {
            // Only the list closes, a dialog around the form stays open.
            event.preventDefault();
            setOpen(false);
        }
    };

    const items = results.kind === 'list' ? results.items : [];
    const fieldClass = disabled ? ' disabled' : '';
    const empty = results.kind === 'list' && items.length === 0;

    return (
        <>
            <div className={`form-control d-flex flex-wrap align-items-center gap-1 muform-autocompletemany-field${fieldClass}`}
                ref={fieldRef} onClick={() => inputRef.current?.focus()}>
                {selected.map((item) => (
                    <span key={item.value} className={`muform-chip${item.error ? ' muform-chip-error' : ''}`}
                        title={item.error ?? undefined} data-muform-autocomplete-pill={item.value}>
                        <span dangerouslySetInnerHTML={{__html: item.label}}></span>
                        {item.error && <span className="visually-hidden">{item.error}</span>}
                        {!disabled && (
                            <button type="button" className="muform-chip-dismiss"
                                aria-label={strings.remove.replace('{$a}', labelText(item.label))}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    remove(item.value, true);
                                }}>
                                <i className="icon fa fa-xmark m-0" aria-hidden="true"></i>
                            </button>
                        )}
                    </span>
                ))}
                <input
                    ref={(node) => {
                        inputRef.current = node;
                        refs.setReference(node);
                    }}
                    type="text"
                    id={id}
                    className="border-0 flex-grow-1 bg-transparent muform-autocomplete-input"
                    role="combobox"
                    aria-autocomplete="list"
                    autoComplete="off"
                    placeholder={disabled ? undefined : placeholder}
                    disabled={disabled}
                    value={query}
                    {...getReferenceProps({
                        onChange: (event: React.ChangeEvent<HTMLInputElement>) => {
                            setQuery(event.target.value);
                            setOpen(true);
                        },
                        onFocus: () => {
                            if (!quietFocus.current) {
                                setOpen(true);
                            }
                        },
                        onClick: () => setOpen(true),
                        onKeyDown,
                    })}
                />
                {open && !disabled && (
                    <button type="button" className="btn-close ms-auto" style={{fontSize: '.6em'}} aria-label={strings.close}
                        title={strings.close} data-muform-autocomplete-close
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={(event) => {
                            event.stopPropagation();
                            closeSearch();
                        }}></button>
                )}
                {name !== undefined && (
                    <input type="hidden" name={name} value={selected.map((item) => item.value).join(',')} disabled={disabled} />
                )}
            </div>
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
