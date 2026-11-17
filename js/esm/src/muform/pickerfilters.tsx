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
 * Filters of picker panels, pills that look and work like the report filter bar.
 *
 * A pill opens the editor of the filter value in a popover, its cross removes the value;
 * filters without a value are offered by the "Add filter" button, pinned filters stay in the bar instead.
 * Values of autocomplete filters are searched in their source through the tool_mulib autocomplete endpoint.
 * Any picker family can use it: the panel renders this component with the filters exported
 * by tool_mulib\muform\picker\filter_set and sends the changed values to its endpoint.
 *
 * @module     tool_mulib/muform/pickerfilters
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fetch from '@moodle/lms/core/fetch';
import {useEffect, useRef, useState} from 'react';
import {labelText, type PickerSource} from './pickerpanel';

/** Option of a filter. */
export interface FilterOption {
    value: string;
    label: string;
}

/** Filter of the source with its current value. */
export interface Filter {
    key: string;
    /** Boolean filters have buttons for their two options, select filters a list, autocomplete filters a search. */
    type: 'boolean' | 'select' | 'autocomplete';
    label: string;
    options: FilterOption[];
    /** Null when the filter does not restrict the list. */
    value: string | null;
    /** Value when the panel opens. */
    'default': string | null;
    /** Pinned filters stay in the bar when they do not restrict the list. */
    pinned?: boolean;
    /** Text of a pinned filter without a value, such as "Any". */
    anylabel?: string;
    /** Autocomplete source searched for values of autocomplete filters. */
    source?: PickerSource;
    /** Label html of the value of an autocomplete filter. */
    valuelabel?: string | null;
}

/** Localised texts. */
export interface FilterStrings {
    filteradd: string;
    filterremove: string;
    filterresetall: string;
    filterresetdefault: string;
    filternoresults: string;
    filtersearching: string;
    filtertoomanyresults: string;
    search: string;
}

/** Component props. */
export interface FiltersProps {
    filters: Filter[];
    strings: FilterStrings;
    /** New values by filter keys, null removes the value. */
    onChange: (changes: Record<string, string | null>) => void;
    /** A popover was closed without a change. */
    onCancel: () => void;
}

/** Open popover: editor of one filter or the list of filters without a value. */
interface Popover {
    /** Filter key, null for the list of filters. */
    key: string | null;
    left: number;
    top: number;
}

/** Select filters with more options get a search box. */
const SEARCH_THRESHOLD = 10;

/** Delay of searches while typing. */
const TYPING_DELAY = 250;

/** Props of the value editor. */
interface EditorProps {
    filter: Filter;
    strings: FilterStrings;
    /** Label html is known for values found in autocomplete sources. */
    onValue: (value: string | null, label?: string) => void;
}

/** Answer of the autocomplete endpoint. */
interface AutocompleteAnswer {
    list: FilterOption[] | null;
    overflow: boolean;
}

/**
 * Search of a value in the autocomplete source of a filter.
 *
 * @param props
 * @param props.filter
 * @param props.strings
 * @param props.onValue
 * @returns search field and found values
 */
function AutocompleteEditor({filter, strings, onValue}: EditorProps) {
    const [query, setQuery] = useState('');
    const [answer, setAnswer] = useState<AutocompleteAnswer | null>(null);
    const request = useRef(0);
    const delay = useRef(0);

    useEffect(() => {
        setAnswer(null);
        const id = ++request.current;
        const timer = setTimeout(async() => {
            const body = {source: filter.source?.class ?? '', args: filter.source?.args ?? [], query, exclude: []};
            let data: AutocompleteAnswer = {list: [], overflow: false};
            try {
                const response = await Fetch.performPost('tool_mulib', 'muform/autocomplete', {body});
                data = await response.json() as AutocompleteAnswer;
            } catch {
                // Nothing can be offered.
            }
            if (id === request.current) {
                setAnswer(data);
            }
        }, delay.current);
        delay.current = 0;
        return () => clearTimeout(timer);
    }, [query, filter.source]);

    const list = answer?.list ?? [];
    let message = '';
    if (!answer) {
        message = strings.filtersearching;
    } else if (answer.overflow || answer.list === null) {
        message = strings.filtertoomanyresults;
    } else if (!list.length) {
        message = strings.filternoresults;
    }
    return (
        <>
            <input type="search" className="form-control form-control-sm mb-2" placeholder={strings.search}
                aria-label={strings.search} value={query} data-picker-filter-search
                onChange={(event) => {
                    delay.current = TYPING_DELAY;
                    setQuery(event.target.value);
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        // Never submit the form below.
                        event.preventDefault();
                        if (list.length === 1) {
                            onValue(list[0].value, list[0].label);
                        }
                    }
                }} />
            <div className="list-group list-group-flush" aria-busy={!answer}>
                {list.map((option) => (
                    <button key={option.value} type="button" data-picker-filter-option={option.value}
                        className={'list-group-item list-group-item-action border-0'
                            + (option.value === filter.value ? ' active' : '')}
                        onClick={() => onValue(option.value, option.label)}>
                        {labelText(option.label)}
                    </button>
                ))}
            </div>
            {message !== '' && <div className="text-muted small" data-picker-filter-message>{message}</div>}
        </>
    );
}

/**
 * Value editor of one filter.
 *
 * @param props
 * @param props.filter
 * @param props.strings
 * @param props.onValue
 * @returns editor
 */
function Editor({filter, strings, onValue}: EditorProps) {
    const [query, setQuery] = useState('');
    const search = query.trim().toLowerCase();
    return (
        <>
            <div className="fw-bold mb-2">{filter.label}</div>
            {filter.type === 'autocomplete' && <AutocompleteEditor filter={filter} strings={strings} onValue={onValue} />}
            {filter.type === 'boolean' && (
                <div className="d-flex gap-2">
                    {filter.options.map((option) => (
                        <button key={option.value} type="button" data-picker-filter-option={option.value}
                            className={'btn btn-sm ' + (option.value === filter.value ? 'btn-primary' : 'btn-outline-primary')}
                            onClick={() => onValue(option.value)}>
                            {option.label}
                        </button>
                    ))}
                </div>
            )}
            {filter.type === 'select' && (
                <>
                    {filter.options.length > SEARCH_THRESHOLD && (
                        <input type="search" className="form-control form-control-sm mb-2" placeholder={strings.search}
                            aria-label={strings.search} value={query} onChange={(event) => setQuery(event.target.value)} />
                    )}
                    <div className="list-group list-group-flush">
                        {filter.options.filter((option) => option.label.toLowerCase().includes(search)).map((option) => (
                            <button key={option.value} type="button" data-picker-filter-option={option.value}
                                className={'list-group-item list-group-item-action border-0'
                                    + (option.value === filter.value ? ' active' : '')}
                                onClick={() => onValue(option.value)}>
                                {option.label}
                            </button>
                        ))}
                    </div>
                </>
            )}
            {filter.default !== null && filter.value !== filter.default && (
                <button type="button" className="btn btn-sm btn-link px-0 mt-2 align-self-start"
                    onClick={() => onValue(filter.default)}>
                    {strings.filterresetdefault}
                </button>
            )}
        </>
    );
}

export default function Filters({filters, strings, onChange, onCancel}: FiltersProps) {
    const [popover, setPopover] = useState<Popover | null>(null);
    // Labels of values picked in autocomplete editors, shown until the endpoint returns them.
    const [picked, setPicked] = useState<Record<string, FilterOption>>({});
    const rootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!popover) {
            return undefined;
        }
        rootRef.current?.querySelector<HTMLElement>('[data-picker-filter-popover] :is(input, button)')?.focus();
        const close = (event: MouseEvent): void => {
            if (event.target instanceof Node && !rootRef.current?.contains(event.target)) {
                setPopover(null);
            }
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [popover]);

    if (!filters.length) {
        return null;
    }
    const inactive = filters.filter((filter) => filter.value === null && !filter.pinned);
    const edited = popover?.key ? filters.find((filter) => filter.key === popover.key) : undefined;

    /**
     * Text of the value in a pill.
     *
     * @param filter filter with a value
     * @returns text
     */
    const valueText = (filter: Filter): string => {
        if (filter.type === 'autocomplete') {
            const known = picked[filter.key]?.value === filter.value ? picked[filter.key].label : filter.valuelabel;
            return known ? labelText(known) : (filter.value ?? '');
        }
        return filter.options.find((option) => option.value === filter.value)?.label ?? filter.value ?? '';
    };

    const change = (changes: Record<string, string | null>): void => {
        setPopover(null);
        onChange(changes);
    };

    /**
     * Open a popover below a pill or button, the same one closes it.
     *
     * @param key filter key, null for the list of filters without a value
     * @param anchor
     */
    const toggle = (key: string | null, anchor: HTMLElement): void => {
        if (popover && popover.key === key) {
            setPopover(null);
            return;
        }
        setPopover({key, left: anchor.offsetLeft, top: anchor.offsetTop + anchor.offsetHeight + 4});
    };

    const onKeyDown = (event: React.KeyboardEvent): void => {
        if (event.key === 'Escape' && popover) {
            // Only the popover is closed, not the panel.
            event.preventDefault();
            event.stopPropagation();
            setPopover(null);
            onCancel();
        }
    };

    return (
        <div ref={rootRef} className="d-flex flex-wrap align-items-center gap-2 mt-2 position-relative"
            data-picker-filters onKeyDown={onKeyDown}>
            {filters.filter((filter) => filter.value !== null || filter.pinned).map((filter) => (
                <div key={filter.key} className="btn-group btn-group-sm" data-picker-filter={filter.key}>
                    <button type="button" className={filter.value === null ? 'btn btn-outline-secondary' : 'btn btn-primary'}
                        onClick={(event) => toggle(filter.key, event.currentTarget.parentElement ?? event.currentTarget)}>
                        {filter.label}: {filter.value === null ? (filter.anylabel ?? '') : valueText(filter)}
                    </button>
                    {filter.value !== null && (
                        <button type="button" className="btn btn-primary" title={strings.filterremove}
                            aria-label={`${strings.filterremove}: ${filter.label}`} data-picker-filter-remove
                            onClick={() => change({[filter.key]: null})}>×</button>
                    )}
                </div>
            ))}
            {inactive.length > 0 && (
                <button type="button" className="btn btn-sm btn-outline-secondary" data-picker-filter-add
                    onClick={(event) => toggle(null, event.currentTarget)}>
                    + {strings.filteradd}
                </button>
            )}
            {filters.some((filter) => filter.value !== filter.default) && (
                <button type="button" className="btn btn-sm btn-link" data-picker-filter-reset
                    onClick={() => change(Object.fromEntries(filters.map((filter) => [filter.key, filter.default])))}>
                    ↺ {strings.filterresetall}
                </button>
            )}
            {popover && (
                <div className="card shadow p-2 position-absolute overflow-auto" data-picker-filter-popover
                    style={{left: popover.left, top: popover.top, zIndex: 1050, minWidth: '16rem', maxHeight: '20rem'}}>
                    {edited ? (
                        <Editor key={edited.key} filter={edited} strings={strings}
                            onValue={(value, label) => {
                                if (value !== null && label !== undefined) {
                                    setPicked({...picked, [edited.key]: {value, label}});
                                }
                                change({[edited.key]: value});
                            }} />
                    ) : (
                        <div className="list-group list-group-flush">
                            {inactive.map((filter) => (
                                <button key={filter.key} type="button" className="list-group-item list-group-item-action border-0"
                                    data-picker-filter-choice={filter.key}
                                    onClick={() => setPopover({...popover, key: filter.key})}>
                                    {filter.label}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
