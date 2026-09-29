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
 * Tag field of the tags element, a React island.
 *
 * Tags are pills before a combobox input. Typed text becomes a tag on Enter or comma,
 * unless only standard tags may be used; standard tags are suggested by the tool_mulib
 * tags endpoint. Written independently of the autocomplete pickers on purpose.
 *
 * @module     tool_mulib/muform/tagspicker
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

/** An entered tag with an optional refusal from the server. */
export interface Tag {
    name: string;
    error: string | null;
}

/** Suggestion endpoint answer. */
interface Answer {
    list: string[] | null;
    overflow: boolean;
    maxitems: number;
}

/** Localised texts. */
export interface TagsPickerStrings {
    noresults: string;
    searching: string;
    toomanyresults: string;
    remove: string;
    close: string;
}

/** Picker props. */
export interface TagsPickerProps {
    area: {class: string; args: unknown[]};
    initial: Tag[];
    suggest: boolean;
    standardonly: boolean;
    name: string;
    id: string;
    placeholder: string;
    disabled: boolean;
    strings: TagsPickerStrings;
    onChange: (names: string[]) => void;
}

/** Suggestions state of the listbox. */
type Results = {kind: 'idle'} | {kind: 'loading'} | {kind: 'overflow'} | {kind: 'list'; items: string[]};

/**
 * Tag name as core stores it: trimmed, spaces collapsed.
 *
 * @param text typed text
 * @returns tag name, empty when nothing was typed
 */
export function normaliseTag(text: string): string {
    return text.replace(/\s+/g, ' ').trim();
}

export default function TagsPicker(
    {area, initial, suggest, standardonly, name, id, placeholder, disabled, strings, onChange}: TagsPickerProps
) {
    const [tags, setTags] = useState<Tag[]>(initial);
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
            if (!state && standardonly) {
                // Text that is not a standard tag cannot be added, it must not look like a value.
                // Free text is added as a tag when the field loses focus.
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
            outsidePress: (event) => !(event.target instanceof Element && event.target.closest('[data-muform-tags-close]')),
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
        if (!open || !suggest) {
            return undefined;
        }
        setResults({kind: 'loading'});
        const timer = setTimeout(async() => {
            const body = {area: area.class, args: area.args, query, exclude: tags.map((tag) => tag.name)};
            try {
                const response = await Fetch.performPost('tool_mulib', 'muform/tags', {body});
                const answer = await response.json() as Answer;
                if (answer.overflow || answer.list === null) {
                    setResults({kind: 'overflow'});
                } else {
                    setResults({kind: 'list', items: answer.list});
                    // Typing a new tag must not pick a suggestion on Enter.
                    setActive(standardonly && answer.list.length ? 0 : null);
                }
            } catch {
                setResults({kind: 'list', items: []});
            }
        }, 250);
        return () => clearTimeout(timer);
    }, [open, suggest, standardonly, query, tags, area]);

    const update = (next: Tag[]): void => {
        setTags(next);
        onChange(next.map((tag) => tag.name));
    };

    /**
     * Add a tag.
     *
     * @param text typed or suggested tag
     * @param refocus keep focus in the tag field, not when the field is being left
     */
    const add = (text: string, refocus = true): void => {
        const tag = normaliseTag(text);
        setQuery('');
        setOpen(false);
        if (refocus) {
            focusInput();
        }
        if (tag === '' || tags.some((existing) => existing.name.toLowerCase() === tag.toLowerCase())) {
            return;
        }
        update([...tags, {name: tag, error: null}]);
    };

    /**
     * Remove a tag; the tag field gets focus only when typing is expected,
     * a pill button removal keeps focus on the pills while some are left.
     *
     * @param tag removed tag name
     * @param frompill removed with the pill button
     */
    const remove = (tag: string, frompill = false): void => {
        const index = tags.findIndex((existing) => existing.name === tag);
        const next = tags.filter((existing) => existing.name !== tag);
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
        const buttons = fieldRef.current?.querySelectorAll<HTMLButtonElement>('[data-muform-tags-pill] button');
        buttons?.[focusPill.current]?.focus();
        focusPill.current = null;
    }, [tags]);

    const items = results.kind === 'list' ? results.items : [];

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'Enter' || event.key === ',') {
            if (event.key === 'Enter' && query === '' && active === null) {
                // Let Enter submit the form when nothing is being typed.
                return;
            }
            event.preventDefault();
            if (open && active !== null && items[active]) {
                add(items[active]);
            } else if (!standardonly) {
                add(query);
            }
        } else if (event.key === 'Backspace' && query === '' && tags.length) {
            remove(tags[tags.length - 1].name);
        } else if (event.key === 'Tab') {
            // Focus moves on, the list must not stay open over the rest of the form.
            setOpen(false);
            if (standardonly) {
                // Text that is not a standard tag cannot be added, it must not look like a value.
                setQuery('');
            }
        } else if (event.key === 'Escape' && open) {
            // Only the list closes, a dialog around the form stays open.
            event.preventDefault();
            setOpen(false);
        }
    };

    const fieldClass = disabled ? ' disabled' : '';
    const pillClass = (tag: Tag): string => (tag.error ? 'text-bg-danger' : 'bg-secondary-subtle text-body fw-normal');
    const closeClass = (tag: Tag): string => (tag.error ? 'btn-close btn-close-white' : 'btn-close');
    const empty = results.kind === 'list' && items.length === 0;
    const showlist = open && suggest && (results.kind !== 'list' || items.length > 0 || query !== '');

    return (
        <>
            <div className={`form-control d-flex flex-wrap align-items-center gap-1 muform-tags-field${fieldClass}`}
                ref={fieldRef} onClick={() => inputRef.current?.focus()}>
                {tags.map((tag) => (
                    <span key={tag.name} className={`badge d-inline-flex align-items-center gap-1 ${pillClass(tag)}`}
                        title={tag.error ?? undefined} data-muform-tags-pill={tag.name}>
                        <span>{tag.name}</span>
                        {tag.error && <span className="visually-hidden">{tag.error}</span>}
                        {!disabled && (
                            <button type="button" className={closeClass(tag)} style={{fontSize: '.6em'}}
                                aria-label={strings.remove.replace('{$a}', tag.name)}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    remove(tag.name, true);
                                }}></button>
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
                    className="border-0 flex-grow-1 bg-transparent muform-tags-input"
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
                        onBlur: () => {
                            // A typed tag is not lost when the user moves on.
                            if (!standardonly && normaliseTag(query) !== '') {
                                add(query, false);
                            }
                        },
                        onKeyDown,
                    })}
                />
                {open && !disabled && (
                    <button type="button" className="btn-close ms-auto" style={{fontSize: '.6em'}} aria-label={strings.close}
                        title={strings.close} data-muform-tags-close
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={(event) => {
                            event.stopPropagation();
                            setOpen(false);
                            setQuery('');
                        }}></button>
                )}
                <input type="hidden" name={name} value={tags.map((tag) => tag.name).join(',')} disabled={disabled} />
            </div>
            {showlist && (
                <ul ref={refs.setFloating} style={floatingStyles}
                    className="list-group shadow muform-tags-listbox" {...getFloatingProps()}>
                    {results.kind === 'loading' && <li className="list-group-item text-muted">{strings.searching}</li>}
                    {results.kind === 'overflow' && <li className="list-group-item text-muted">{strings.toomanyresults}</li>}
                    {empty && <li className="list-group-item text-muted">{strings.noresults}</li>}
                    {items.map((item, index) => (
                        <li
                            key={item}
                            role="option"
                            aria-selected={active === index}
                            className={`list-group-item list-group-item-action${active === index ? ' active' : ''}`}
                            data-muform-tags-option={item}
                            ref={(node) => {
                                listRef.current[index] = node;
                            }}
                            {...getItemProps({
                                // Keep focus in the input, the blur would add the typed text.
                                onMouseDown: (event: React.MouseEvent) => event.preventDefault(),
                                onClick: () => add(item),
                            })}
                        >{item}</li>
                    ))}
                </ul>
            )}
        </>
    );
}
