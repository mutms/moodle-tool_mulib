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
 * Calendar and time picker of the datetime element, a React island.
 *
 * The picker only edits date parts, the text sent to the element is parsed
 * by the server. Month and weekday names come from Intl for display only.
 *
 * @module     tool_mulib/muform/datetimepicker
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    FloatingFocusManager,
    autoUpdate,
    flip,
    offset,
    shift,
    useClick,
    useDismiss,
    useFloating,
    useInteractions,
    useRole,
} from '@floating-ui/react';
import {useEffect, useRef, useState} from 'react';
import {formatComponents, readComponents, type Components} from './datetimeapi';

/** Localised texts of the picker. */
export interface PickerStrings {
    pick: string;
    today: string;
    clear: string;
    apply: string;
    hour: string;
    minute: string;
    monthprev: string;
    monthnext: string;
    yearprev: string;
    yearnext: string;
}

/** Picker props. */
export interface PickerProps {
    /** The datetime text input carrying the current value in data attributes. */
    input: HTMLInputElement;
    /** Minute step of the minute select. */
    step: number;
    /** Disabled elements have no picker. */
    disabled: boolean;
    /** Localised texts. */
    strings: PickerStrings;
    /** Sends text to the server, empty text clears the value. */
    apply: (text: string) => Promise<void>;
}

/** Locale week information, not yet in the TypeScript DOM library. */
interface WeekInfo {
    firstDay?: number;
}

/**
 * First day of week for the page language, 1 is Monday and 7 is Sunday.
 *
 * @param lang page language tag
 * @returns weekday number
 */
function firstDayOfWeek(lang: string): number {
    try {
        const locale = new Intl.Locale(lang) as unknown as {getWeekInfo?: () => WeekInfo; weekInfo?: WeekInfo};
        const info = locale.getWeekInfo ? locale.getWeekInfo() : locale.weekInfo;
        return info?.firstDay ?? 1;
    } catch {
        return 1;
    }
}

/**
 * Current date and time rounded down to the minute step.
 *
 * @param step minute step
 * @returns parts
 */
function nowParts(step: number): Components {
    const now = new Date();
    return {
        year: now.getFullYear(),
        month: now.getMonth() + 1,
        day: now.getDate(),
        hour: now.getHours(),
        minute: now.getMinutes() - (now.getMinutes() % step),
        second: 0,
    };
}

/**
 * Today's date parts without time.
 *
 * @returns year, month and day
 */
function nowDate(): Pick<Components, 'year' | 'month' | 'day'> {
    const now = new Date();
    return {year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate()};
}

/**
 * Days in a month.
 *
 * @param year full year
 * @param month 1 to 12
 * @returns number of days
 */
function daysInMonth(year: number, month: number): number {
    return new Date(year, month, 0).getDate();
}

/**
 * Move a date by a number of days, keeping the time.
 *
 * @param parts date parts
 * @param days positive or negative
 * @returns new parts
 */
function addDays(parts: Components, days: number): Components {
    const date = new Date(parts.year, parts.month - 1, parts.day + days);
    return {...parts, year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate()};
}

export default function DateTimePicker({input, step, disabled, strings, apply}: PickerProps) {
    const lang = document.documentElement.lang || 'en';
    const [open, setOpen] = useState(false);
    const [parts, setParts] = useState<Components>(() => readComponents(input) ?? nowParts(step));
    const [view, setView] = useState({year: parts.year, month: parts.month});
    const [focusDay, setFocusDay] = useState(false);
    const gridRef = useRef<HTMLTableElement>(null);

    const onOpenChange = (state: boolean): void => {
        if (state) {
            const current = readComponents(input) ?? nowParts(step);
            setParts(current);
            setView({year: current.year, month: current.month});
        }
        setOpen(state);
    };

    const {refs, floatingStyles, context} = useFloating({
        open,
        onOpenChange,
        placement: 'bottom-end',
        middleware: [offset(4), flip(), shift({padding: 8})],
        whileElementsMounted: autoUpdate,
    });
    const {getReferenceProps, getFloatingProps} = useInteractions([
        useClick(context),
        useDismiss(context),
        useRole(context, {role: 'dialog'}),
    ]);

    useEffect(() => {
        if (focusDay && gridRef.current) {
            gridRef.current.querySelector<HTMLButtonElement>('button[tabindex="0"]')?.focus();
            setFocusDay(false);
        }
    }, [focusDay, parts]);

    if (disabled) {
        return null;
    }

    const select = (next: Components): void => {
        setParts(next);
        setView({year: next.year, month: next.month});
    };

    const moveMonth = (delta: number): void => {
        const date = new Date(view.year, view.month - 1 + delta, 1);
        setView({year: date.getFullYear(), month: date.getMonth() + 1});
    };

    const onGridKeyDown = (event: React.KeyboardEvent): void => {
        const moves: Record<string, number> = {ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7};
        const delta = moves[event.key];
        if (delta === undefined) {
            return;
        }
        event.preventDefault();
        select(addDays(parts, delta));
        setFocusDay(true);
    };

    const applyAndClose = (text: string): void => {
        setOpen(false);
        void apply(text);
    };

    const monthFormat = new Intl.DateTimeFormat(lang, {month: 'long', year: 'numeric'});
    const monthName = monthFormat.format(new Date(view.year, view.month - 1, 1));
    const weekdayFormat = new Intl.DateTimeFormat(lang, {weekday: 'short'});
    const firstDay = firstDayOfWeek(lang);
    const weekdays = Array.from({length: 7}, (_, i) => {
        // The 4th of January 2026 is a Sunday, so the 5th is Monday which is weekday 1.
        const weekday = ((firstDay - 1 + i) % 7) + 1;
        return weekdayFormat.format(new Date(2026, 0, 4 + weekday));
    });

    const today = nowDate();
    const first = new Date(view.year, view.month - 1, 1);
    const firstWeekday = first.getDay() === 0 ? 7 : first.getDay();
    const leading = (firstWeekday - firstDay + 7) % 7;
    const total = daysInMonth(view.year, view.month);
    const cells: (number | null)[] = [...Array<null>(leading).fill(null)];
    for (let day = 1; day <= total; day++) {
        cells.push(day);
    }
    while (cells.length % 7 !== 0) {
        cells.push(null);
    }
    const rows: (number | null)[][] = [];
    for (let i = 0; i < cells.length; i += 7) {
        rows.push(cells.slice(i, i + 7));
    }
    const selectedInView = parts.year === view.year && parts.month === view.month;
    const focusable = selectedInView ? parts.day : 1;

    const minutes: number[] = [];
    for (let minute = 0; minute < 60; minute += step) {
        minutes.push(minute);
    }
    if (!minutes.includes(parts.minute)) {
        minutes.push(parts.minute);
        minutes.sort((a, b) => a - b);
    }
    const hours = Array.from({length: 24}, (_, i) => i);

    return (
        <>
            <button
                type="button"
                className="btn btn-outline-secondary"
                ref={refs.setReference}
                aria-label={strings.pick}
                title={strings.pick}
                {...getReferenceProps()}
            >
                <i className="fa fa-calendar" aria-hidden="true"></i>
            </button>
            {open && (
                <FloatingFocusManager context={context} modal={false}>
                    <div
                        ref={refs.setFloating}
                        style={floatingStyles}
                        className="muform-datetime-panel card shadow p-2"
                        aria-label={strings.pick}
                        {...getFloatingProps()}
                    >
                        <div className="d-flex align-items-center justify-content-between mb-2">
                            <span className="d-flex gap-1">
                                <button type="button" className="btn btn-sm btn-light" aria-label={strings.yearprev}
                                    onClick={() => moveMonth(-12)}>
                                    <i className="fa fa-angles-left" aria-hidden="true"></i>
                                </button>
                                <button type="button" className="btn btn-sm btn-light" aria-label={strings.monthprev}
                                    onClick={() => moveMonth(-1)}>
                                    <i className="fa fa-chevron-left" aria-hidden="true"></i>
                                </button>
                            </span>
                            <span className="fw-bold" aria-live="polite">{monthName}</span>
                            <span className="d-flex gap-1">
                                <button type="button" className="btn btn-sm btn-light" aria-label={strings.monthnext}
                                    onClick={() => moveMonth(1)}>
                                    <i className="fa fa-chevron-right" aria-hidden="true"></i>
                                </button>
                                <button type="button" className="btn btn-sm btn-light" aria-label={strings.yearnext}
                                    onClick={() => moveMonth(12)}>
                                    <i className="fa fa-angles-right" aria-hidden="true"></i>
                                </button>
                            </span>
                        </div>
                        <table className="table table-sm table-borderless text-center mb-2" ref={gridRef}
                            role="grid" onKeyDown={onGridKeyDown}>
                            <thead>
                                <tr>
                                    {weekdays.map((name) => <th key={name} scope="col" className="small fw-normal">{name}</th>)}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, r) => (
                                    <tr key={r}>
                                        {row.map((day, c) => {
                                            if (day === null) {
                                                return <td key={c}></td>;
                                            }
                                            const isSelected = selectedInView && day === parts.day;
                                            const isToday = today.year === view.year && today.month === view.month
                                                && today.day === day;
                                            const classes = ['btn', 'btn-sm', 'muform-datetime-day'];
                                            classes.push(isSelected ? 'btn-primary' : 'btn-light');
                                            if (isToday) {
                                                classes.push('fw-bold');
                                            }
                                            return (
                                                <td key={c}>
                                                    <button
                                                        type="button"
                                                        className={classes.join(' ')}
                                                        tabIndex={day === focusable ? 0 : -1}
                                                        aria-pressed={isSelected}
                                                        aria-current={isToday ? 'date' : undefined}
                                                        data-muform-datetime-day={day}
                                                        onClick={() => select({...parts, year: view.year, month: view.month, day})}
                                                    >
                                                        {day}
                                                    </button>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="d-flex align-items-center gap-1 mb-2">
                            <select className="form-select form-select-sm w-auto" aria-label={strings.hour} value={parts.hour}
                                onChange={(event) => setParts({...parts, hour: Number(event.target.value)})}>
                                {hours.map((hour) => <option key={hour} value={hour}>{String(hour).padStart(2, '0')}</option>)}
                            </select>
                            <span aria-hidden="true">:</span>
                            <select className="form-select form-select-sm w-auto" aria-label={strings.minute} value={parts.minute}
                                onChange={(event) => setParts({...parts, minute: Number(event.target.value)})}>
                                {minutes.map((minute) => (
                                    <option key={minute} value={minute}>{String(minute).padStart(2, '0')}</option>
                                ))}
                            </select>
                        </div>
                        <div className="d-flex gap-1">
                            <button type="button" className="btn btn-sm btn-light" onClick={() => select({...parts, ...nowDate()})}>
                                {strings.today}
                            </button>
                            <button type="button" className="btn btn-sm btn-light" onClick={() => applyAndClose('')}>
                                {strings.clear}
                            </button>
                            <button type="button" className="btn btn-sm btn-primary ms-auto"
                                onClick={() => applyAndClose(formatComponents(parts))}>
                                {strings.apply}
                            </button>
                        </div>
                    </div>
                </FloatingFocusManager>
            )}
        </>
    );
}
