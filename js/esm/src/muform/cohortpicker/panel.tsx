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
 * Panel of the cohorts picker family, a React island inside a picker panel dialog.
 *
 * Search field and the cohorts the source offers, each below the course category it belongs to; only
 * categories with found cohorts and their parents are shown, so a search by name keeps the matching
 * cohorts in their place of the tree. One cohort: Enter picks the highlighted row, a click picks any row.
 * Several cohorts: rows are ticked, Enter ticks the highlighted row and clears the search for the next
 * name, Enter with an empty search adds the ticked cohorts, the footer button adds all ticked.
 *
 * @module     tool_mulib/muform/cohortpicker/panel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fetch from '@moodle/lms/core/fetch';
import {useEffect, useRef, useState} from 'react';
import type {PickerPanelProps} from '../pickerpanel';

/** Localised texts. */
export interface Strings {
    addselected: string;
    close: string;
    found: string;
    noresults: string;
    overflow: string;
    search: string;
    searching: string;
    selectrow: string;
    title: string;
}

/** Panel props. */
export type PanelProps = PickerPanelProps<Strings>;

/** System or course category with cohorts somewhere below. */
export interface TreeNode {
    id: number;
    name: string;
    depth: number;
    parentid: number | null;
}

/** One cohort. */
export interface Cohort {
    id: number;
    name: string;
    idnumber: string;
    nodeid: number;
    label: string;
}

/** Answer of the search endpoint, nodes are in the order of the tree. */
export interface Answer {
    nodes: TreeNode[];
    cohorts: Cohort[];
    total: number;
    overflow: boolean;
}

/** Delay of searches while typing. */
const TYPING_DELAY = 250;

/** Indentation of one tree level. */
const INDENT = 1.25;

export default function Panel({source, multiple, selected, strings, onPick, onClose}: PanelProps) {
    const [query, setQuery] = useState('');
    const [answer, setAnswer] = useState<Answer | null>(null);
    const [loading, setLoading] = useState(true);
    const [active, setActive] = useState(0);
    const [ticked, setTicked] = useState<Cohort[]>([]);
    const searchRef = useRef<HTMLInputElement>(null);
    const request = useRef(0);
    // Typing is delayed, everything else searches at once.
    const delay = useRef(0);
    // Enter pressed before the answer arrived is applied to the answer, fast typists do not wait for the list.
    const enterPending = useRef(false);
    const enterRows = useRef<(rows: Cohort[]) => void>(() => undefined);

    useEffect(() => {
        searchRef.current?.focus();
    }, []);

    useEffect(() => {
        setLoading(true);
        const id = ++request.current;
        const timer = setTimeout(async() => {
            const body = {source: source.class, args: source.args, query, exclude: selected};
            let data: Answer = {nodes: [], cohorts: [], total: 0, overflow: false};
            let failed = false;
            try {
                const response = await Fetch.performPost('tool_mulib', 'muform/cohortpicker/search', {body});
                data = await response.json() as Answer;
            } catch {
                failed = true;
            }
            if (id !== request.current) {
                return;
            }
            const ordered = inTreeOrder(data);
            setAnswer(data);
            setActive(0);
            setLoading(false);
            if (enterPending.current) {
                enterPending.current = false;
                if (!failed) {
                    enterRows.current(ordered);
                }
            }
        }, delay.current);
        delay.current = 0;
        return () => clearTimeout(timer);
    }, [query, source, selected]);

    // Rows in the order they are shown, the highlighted row is an index into them.
    const rows = answer ? inTreeOrder(answer) : [];
    const isTicked = (cohort: Cohort): boolean => ticked.some((item) => item.id === cohort.id);

    /**
     * Return cohorts to the element.
     *
     * @param cohorts
     */
    const pick = (cohorts: Cohort[]): void => {
        onPick(cohorts.map((cohort) => ({value: String(cohort.id), label: cohort.label})));
    };

    const toggle = (cohort: Cohort): void => {
        setTicked(isTicked(cohort) ? ticked.filter((item) => item.id !== cohort.id) : [...ticked, cohort]);
    };

    /**
     * Click on a row - the answer for one cohort, a tick for several cohorts.
     *
     * @param cohort
     */
    const choose = (cohort: Cohort): void => {
        if (multiple) {
            toggle(cohort);
        } else {
            pick([cohort]);
        }
    };

    /**
     * Enter on a row - the answer for one cohort; for several cohorts a tick and an empty search for the next name.
     *
     * @param cohort highlighted row, undefined when nothing was found
     */
    const enter = (cohort: Cohort | undefined): void => {
        if (!cohort) {
            return;
        }
        if (multiple) {
            if (!isTicked(cohort)) {
                setTicked([...ticked, cohort]);
            }
            setQuery('');
        } else {
            pick([cohort]);
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

    return (
        <div className="d-flex flex-column h-100" data-cohortpicker-panel>
            <div className="d-flex align-items-center gap-2 p-3 border-bottom">
                <h2 className="h5 mb-0 flex-grow-1">{strings.title}</h2>
                <button type="button" className="btn-close" aria-label={strings.close} title={strings.close}
                    onClick={onClose}></button>
            </div>
            <div className="p-3 border-bottom">
                <input ref={searchRef} type="search" className="form-control" autoComplete="off"
                    placeholder={strings.search} aria-label={strings.search} value={query} data-cohortpicker-search
                    onChange={(event) => {
                        delay.current = TYPING_DELAY;
                        setQuery(event.target.value);
                    }}
                    onKeyDown={onSearchKeyDown} />
            </div>
            <div className="flex-grow-1 overflow-auto py-2" aria-busy={loading}>
                {(answer?.nodes ?? []).map((node) => (
                    <div key={node.id} data-cohortpicker-node={node.id}>
                        <div className="fw-bold pe-3 py-1" style={{paddingInlineStart: `${1 + node.depth * INDENT}rem`}}
                            data-cohortpicker-node-name={node.name} data-cohortpicker-depth={node.depth}>
                            {node.name}
                        </div>
                        {rows.filter((cohort) => cohort.nodeid === node.id).map((cohort) => (
                            <div key={cohort.id} role="button"
                                className={'d-flex align-items-center gap-2 pe-3 py-1'
                                    + (rows[active]?.id === cohort.id ? ' bg-body-secondary' : '')}
                                style={{paddingInlineStart: `${1 + (node.depth + 1) * INDENT}rem`}}
                                data-cohortpicker-row={cohort.id} data-cohortpicker-name={cohort.name}
                                data-cohortpicker-idnumber={cohort.idnumber}
                                onClick={() => choose(cohort)}>
                                {multiple && (
                                    <input type="checkbox" className="form-check-input mt-0" checked={isTicked(cohort)}
                                        aria-label={`${strings.selectrow}: ${cohort.name}`}
                                        onClick={(event) => event.stopPropagation()}
                                        onChange={() => toggle(cohort)} />
                                )}
                                {multiple ? <span>{cohort.name}</span> : (
                                    <button type="button" className="btn btn-link p-0 text-start">{cohort.name}</button>
                                )}
                                {cohort.idnumber !== '' && <span className="text-muted small">{cohort.idnumber}</span>}
                            </div>
                        ))}
                    </div>
                ))}
                {!rows.length && (
                    <p className="text-muted px-3 mb-0" data-cohortpicker-empty>
                        {loading ? strings.searching : strings.noresults}
                    </p>
                )}
            </div>
            <div className="d-flex flex-wrap align-items-center gap-2 p-3 border-top">
                <span className="text-muted small" data-cohortpicker-total={answer ? answer.total : undefined}>
                    {answer ? strings.found.replace('{$a}', String(answer.total)) : ''}
                </span>
                {answer?.overflow && (
                    <span className="text-warning-emphasis small" data-cohortpicker-overflow>{strings.overflow}</span>
                )}
                <span className="ms-auto d-inline-flex gap-2">
                    {multiple && (
                        <button type="button" className="btn btn-primary" disabled={!ticked.length} data-cohortpicker-add
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

/**
 * Cohorts in the order of the tree, the endpoint orders them by name only.
 *
 * @param answer
 * @returns cohorts
 */
function inTreeOrder(answer: Answer): Cohort[] {
    return answer.nodes.flatMap((node) => answer.cohorts.filter((cohort) => cohort.nodeid === node.id));
}
