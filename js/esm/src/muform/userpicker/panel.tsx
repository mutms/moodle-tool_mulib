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
 * Panel of the users picker family, a React island inside a picker panel dialog.
 *
 * Search field and one page of users with full names and visible identity fields from the
 * tool_mulib userpicker endpoint. One user: Enter picks the highlighted row, a click picks any row.
 * Several users: rows are ticked, Enter ticks the highlighted row and clears the search for the next
 * name, Enter with an empty search adds the ticked users, the footer button adds all ticked.
 *
 * Filters of the source are pills below the search field, the same as in the report filter bar:
 * a pill opens the editor of its value, the cross removes the value, filters without a value are
 * in the "Add filter" list. The endpoint applies default values until the user changes a filter.
 *
 * @module     tool_mulib/muform/userpicker/panel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fetch from '@moodle/lms/core/fetch';
import {useEffect, useRef, useState} from 'react';
import type {PickerPanelProps} from '../pickerpanel';
import Filters, {type Filter} from '../pickerfilters';

/** Localised texts. */
export interface Strings {
    addselected: string;
    close: string;
    filteradd: string;
    filterremove: string;
    filterresetall: string;
    filterresetdefault: string;
    found: string;
    name: string;
    next: string;
    noresults: string;
    page: string;
    previous: string;
    search: string;
    searching: string;
    selectall: string;
    selectrow: string;
    title: string;
}

/** Panel props. */
export type PanelProps = PickerPanelProps<Strings>;

/** Identity column. */
export interface Column {
    name: string;
    label: string;
}

/** One user. */
export interface User {
    id: number;
    fullname: string;
    fields: Record<string, string>;
    label: string;
}

/** Answer of the search endpoint. */
export interface Answer {
    columns: Column[];
    rows: User[];
    total: number;
    page: number;
    perpage: number;
    filters?: Filter[];
}

/** Delay of searches while typing. */
const TYPING_DELAY = 250;

export default function Panel({source, multiple, selected, strings, onPick, onClose}: PanelProps) {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(0);
    const [answer, setAnswer] = useState<Answer | null>(null);
    const [loading, setLoading] = useState(true);
    const [active, setActive] = useState(0);
    const [ticked, setTicked] = useState<User[]>([]);
    // Null until the user changes a filter, the endpoint applies default values then.
    const [filterValues, setFilterValues] = useState<Record<string, string> | null>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const request = useRef(0);
    // Typing is delayed, everything else searches at once.
    const delay = useRef(0);
    // Enter pressed before the answer arrived is applied to the answer, fast typists do not wait for the list.
    const enterPending = useRef(false);
    const enterRows = useRef<(rows: User[]) => void>(() => undefined);

    useEffect(() => {
        searchRef.current?.focus();
    }, []);

    useEffect(() => {
        setLoading(true);
        const id = ++request.current;
        const timer = setTimeout(async() => {
            const body: Record<string, unknown> = {source: source.class, args: source.args, query, page, exclude: selected};
            if (filterValues) {
                body.filters = filterValues;
            }
            try {
                const response = await Fetch.performPost('tool_mulib', 'muform/userpicker/search', {body});
                const data = await response.json() as Answer;
                if (id === request.current) {
                    setAnswer(data);
                    setActive(0);
                    setLoading(false);
                    if (enterPending.current) {
                        enterPending.current = false;
                        enterRows.current(data.rows);
                    }
                }
            } catch {
                if (id === request.current) {
                    enterPending.current = false;
                    setAnswer((previous) => (
                        {columns: [], rows: [], total: 0, page: 0, perpage: 1, filters: previous?.filters ?? []}
                    ));
                    setLoading(false);
                }
            }
        }, delay.current);
        delay.current = 0;
        return () => clearTimeout(timer);
    }, [query, page, source, selected, filterValues]);

    const rows = answer?.rows ?? [];
    const columns = answer?.columns ?? [];
    const pages = answer ? Math.max(1, Math.ceil(answer.total / answer.perpage)) : 1;
    const isTicked = (user: User): boolean => ticked.some((item) => item.id === user.id);
    // Values the user asked for are shown at once, the answer has the values applied by the endpoint.
    const filters = (answer?.filters ?? []).map((filter) => (
        filterValues ? {...filter, value: filterValues[filter.key] ?? null} : filter
    ));

    /**
     * Search again with other filter values.
     *
     * @param changes values by filter keys, null removes the value
     */
    const changeFilters = (changes: Record<string, string | null>): void => {
        const values: Record<string, string> = {};
        for (const filter of filters) {
            const value = filter.key in changes ? changes[filter.key] : filter.value;
            if (value !== null) {
                values[filter.key] = value;
            }
        }
        setFilterValues(values);
        setPage(0);
        searchRef.current?.focus();
    };

    /**
     * Return users to the element.
     *
     * @param users
     */
    const pick = (users: User[]): void => {
        onPick(users.map((user) => ({value: String(user.id), label: user.label})));
    };

    const toggle = (user: User): void => {
        setTicked(isTicked(user) ? ticked.filter((item) => item.id !== user.id) : [...ticked, user]);
    };

    /**
     * Click on a row - the answer for one user, a tick for several users.
     *
     * @param user
     */
    const choose = (user: User): void => {
        if (multiple) {
            toggle(user);
        } else {
            pick([user]);
        }
    };

    /**
     * Enter on a row - the answer for one user; for several users a tick and an empty search for the next name.
     *
     * @param user highlighted row, undefined when nothing was found
     */
    const enter = (user: User | undefined): void => {
        if (!user) {
            return;
        }
        if (multiple) {
            if (!isTicked(user)) {
                setTicked([...ticked, user]);
            }
            setQuery('');
            setPage(0);
        } else {
            pick([user]);
        }
    };
    enterRows.current = (found) => enter(found[0]);

    const onSearchKeyDown = (event: React.KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(Math.min(active + 1, Math.max(0, rows.length - 1)));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(Math.max(active - 1, 0));
        } else if (event.key === 'Enter') {
            // Never submit the form below.
            event.preventDefault();
            if (multiple && query.trim() === '' && ticked.length) {
                pick(ticked);
            } else if (loading) {
                enterPending.current = true;
            } else {
                enter(rows[active]);
            }
        } else {
            enterPending.current = false;
        }
    };

    const allTicked = rows.length > 0 && rows.every(isTicked);
    const toggleAll = (): void => {
        if (allTicked) {
            setTicked(ticked.filter((item) => !rows.some((row) => row.id === item.id)));
        } else {
            setTicked([...ticked, ...rows.filter((row) => !isTicked(row))]);
        }
    };

    return (
        <div className="d-flex flex-column h-100" data-userpicker-panel>
            <div className="d-flex align-items-center gap-2 p-3 border-bottom">
                <h2 className="h5 mb-0 flex-grow-1">{strings.title}</h2>
                <button type="button" className="btn-close" aria-label={strings.close} title={strings.close}
                    onClick={onClose}></button>
            </div>
            <div className="p-3 border-bottom">
                <input ref={searchRef} type="search" className="form-control" autoComplete="off"
                    placeholder={strings.search} aria-label={strings.search} value={query} data-userpicker-search
                    onChange={(event) => {
                        delay.current = TYPING_DELAY;
                        setQuery(event.target.value);
                        setPage(0);
                    }}
                    onKeyDown={onSearchKeyDown} />
                <Filters filters={filters} strings={strings} onChange={changeFilters}
                    onCancel={() => searchRef.current?.focus()} />
            </div>
            <div className="flex-grow-1 overflow-auto" aria-busy={loading}>
                <table className="table table-sm table-hover align-middle mb-0">
                    <thead className="sticky-top">
                        <tr>
                            {multiple && (
                                <th scope="col" className="ps-3">
                                    <input type="checkbox" className="form-check-input" checked={allTicked}
                                        disabled={!rows.length} aria-label={strings.selectall} title={strings.selectall}
                                        onChange={toggleAll} />
                                </th>
                            )}
                            <th scope="col" className={multiple ? '' : 'ps-3'}>{strings.name}</th>
                            {columns.map((column) => <th key={column.name} scope="col">{column.label}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((user, index) => (
                            <tr key={user.id} role="button" className={index === active ? 'table-active' : ''}
                                data-userpicker-row={user.id} data-userpicker-name={user.fullname}
                                onClick={() => choose(user)}>
                                {multiple && (
                                    <td className="ps-3">
                                        <input type="checkbox" className="form-check-input" checked={isTicked(user)}
                                            aria-label={`${strings.selectrow}: ${user.fullname}`}
                                            onClick={(event) => event.stopPropagation()}
                                            onChange={() => toggle(user)} />
                                    </td>
                                )}
                                <td className={multiple ? '' : 'ps-3'}>
                                    {multiple ? user.fullname : (
                                        <button type="button" className="btn btn-link p-0 text-start">{user.fullname}</button>
                                    )}
                                </td>
                                {columns.map((column) => (
                                    <td key={column.name} data-userpicker-field={column.name}>{user.fields[column.name] ?? ''}</td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {!rows.length && (
                    <p className="text-muted p-3 mb-0" data-userpicker-empty>{loading ? strings.searching : strings.noresults}</p>
                )}
            </div>
            <div className="d-flex flex-wrap align-items-center gap-2 p-3 border-top">
                <span className="text-muted small" data-userpicker-total={answer ? answer.total : undefined}>
                    {answer ? strings.found.replace('{$a}', String(answer.total)) : ''}
                </span>
                {pages > 1 && answer && (
                    <span className="d-inline-flex align-items-center gap-1">
                        <button type="button" className="btn btn-sm btn-outline-secondary" disabled={answer.page <= 0}
                            aria-label={strings.previous} title={strings.previous}
                            onClick={() => setPage(answer.page - 1)}>‹</button>
                        <span className="small">{strings.page.replace('{$a}', `${answer.page + 1} / ${pages}`)}</span>
                        <button type="button" className="btn btn-sm btn-outline-secondary" disabled={answer.page >= pages - 1}
                            aria-label={strings.next} title={strings.next}
                            onClick={() => setPage(answer.page + 1)}>›</button>
                    </span>
                )}
                <span className="ms-auto d-inline-flex gap-2">
                    {multiple && (
                        <button type="button" className="btn btn-primary" disabled={!ticked.length} data-userpicker-add
                            onClick={() => pick(ticked)}>
                            {strings.addselected.replace('{$a}', String(ticked.length))}
                        </button>
                    )}
                    <button type="button" className="btn btn-secondary" onClick={onClose}>{strings.close}</button>
                </span>
            </div>
        </div>
    );
}
