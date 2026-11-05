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
 * Calendar and time picker of the datetime and datetimerange elements, a React island.
 *
 * The picker only edits date parts, the text sent to the element is parsed
 * by the server. In range mode it also edits the end time or duration, changing
 * the start keeps the duration. Month and weekday names come from Intl for display only,
 * the first day of week comes from Moodle. "Today" is in the timezone of the element,
 * empty values start at the default time of the element.
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
import {readEndComponents} from './datetimerangeapi';

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

/** Duration offered in range mode. */
export interface PickerDuration {
    seconds: number;
    label: string;
}

/** Range mode of the picker, used by the datetimerange element. */
export interface RangeOptions {
    /** Durations offered in the duration select. */
    durations: PickerDuration[];
    /** Seconds of empty values, null means one hour. */
    defaultDuration: number | null;
    /** Localised texts. */
    strings: {start: string; end: string; duration: string};
}

/** Picker props. */
export interface PickerProps {
    /** The datetime text input carrying the current value in data attributes. */
    input: HTMLInputElement;
    /** Minute step of the minute select. */
    step: number;
    /** First day of week from Moodle, 0 is Sunday, null means the language default. */
    firstDay?: number | null;
    /** Hours are shown with AM and PM. */
    hour12?: boolean;
    /** Disabled elements have no picker. */
    disabled: boolean;
    /** Localised texts. */
    strings: PickerStrings;
    /** Sends text to the server, empty text clears the value. */
    apply: (text: string) => Promise<void>;
    /** Range mode, the input carries the end in data attributes. */
    range?: RangeOptions | null;
}

/** Locale week information, not yet in the TypeScript DOM library. */
interface WeekInfo {
    firstDay?: number;
}

/**
 * First day of week, 1 is Monday and 7 is Sunday.
 *
 * @param moodle first day from Moodle, 0 is Sunday, null when not known
 * @param lang page language tag used when Moodle did not tell
 * @returns weekday number
 */
function firstDayOfWeek(moodle: number | null | undefined, lang: string): number {
    if (moodle !== null && moodle !== undefined && Number.isInteger(moodle) && moodle >= 0 && moodle <= 6) {
        return moodle === 0 ? 7 : moodle;
    }
    try {
        const locale = new Intl.Locale(lang) as unknown as {getWeekInfo?: () => WeekInfo; weekInfo?: WeekInfo};
        const info = locale.getWeekInfo ? locale.getWeekInfo() : locale.weekInfo;
        return info?.firstDay ?? 1;
    } catch {
        return 1;
    }
}

/**
 * Today's date in a timezone, the browser may be in a different one.
 *
 * @param timezone IANA timezone of the element
 * @returns year, month and day
 */
export function todayIn(timezone: string): Pick<Components, 'year' | 'month' | 'day'> {
    const now = new Date();
    try {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone, year: 'numeric', month: 'numeric', day: 'numeric',
        }).formatToParts(now);
        const part = (type: string): number => Number(parts.find((item) => item.type === type)?.value);
        return {year: part('year'), month: part('month'), day: part('day')};
    } catch {
        return {year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate()};
    }
}

/**
 * Starting parts of an empty value: today with the default time of the element.
 *
 * @param input the datetime text input
 * @returns parts
 */
function emptyParts(input: HTMLInputElement): Components {
    return {
        ...todayIn(input.dataset.muformDatetimeTimezone ?? ''),
        hour: Number(input.dataset.muformDatetimeDefaulthour) || 0,
        minute: Number(input.dataset.muformDatetimeDefaultminute) || 0,
        second: 0,
    };
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

/**
 * Wall clock minutes since the epoch, timezone transitions are ignored on purpose.
 *
 * @param parts date parts
 * @returns minutes
 */
function wallMinutes(parts: Components): number {
    return Math.floor(Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute) / 60000);
}

/**
 * Date parts from wall clock minutes.
 *
 * @param minutes minutes since the epoch
 * @param second seconds to keep
 * @returns parts
 */
function fromWallMinutes(minutes: number, second: number): Components {
    const date = new Date(minutes * 60000);
    return {
        year: date.getUTCFullYear(), month: date.getUTCMonth() + 1, day: date.getUTCDate(),
        hour: date.getUTCHours(), minute: date.getUTCMinutes(), second,
    };
}

/**
 * Days between the start date and end date.
 *
 * @param start start parts
 * @param end end parts
 * @returns number of days
 */
function dayOffset(start: Components, end: Components): number {
    const day = (parts: Components): number => Date.UTC(parts.year, parts.month - 1, parts.day) / 86400000;
    return Math.round(day(end) - day(start));
}

/**
 * Wall clock minutes of the current range, the default duration for empty values.
 *
 * @param input the datetimerange text input
 * @param start current start parts
 * @param range range options
 * @returns positive number of minutes
 */
function rangeMinutes(input: HTMLInputElement, start: Components, range: RangeOptions): number {
    const end = readEndComponents(input);
    if (end) {
        const minutes = wallMinutes(end) - wallMinutes(start);
        // Ranges are at most one day long.
        if (minutes > 0 && minutes <= 24 * 60) {
            return minutes;
        }
    }
    return Math.max(1, Math.round((range.defaultDuration ?? 3600) / 60));
}

export default function DateTimePicker(
    {input, step, firstDay: moodleFirstDay, hour12 = false, disabled, strings, apply, range = null}: PickerProps
) {
    const lang = document.documentElement.lang || 'en';
    const timezone = input.dataset.muformDatetimeTimezone ?? '';
    const [open, setOpen] = useState(false);
    const [parts, setParts] = useState<Components>(() => readComponents(input) ?? emptyParts(input));
    const [view, setView] = useState({year: parts.year, month: parts.month});
    const [duration, setDuration] = useState<number>(() => (range ? rangeMinutes(input, parts, range) : 0));
    const [focusDay, setFocusDay] = useState(false);
    const gridRef = useRef<HTMLTableElement>(null);

    const onOpenChange = (state: boolean): void => {
        if (state) {
            const current = readComponents(input) ?? emptyParts(input);
            setParts(current);
            setView({year: current.year, month: current.month});
            if (range) {
                setDuration(rangeMinutes(input, current, range));
            }
        }
        setOpen(state);
    };

    const {refs, floatingStyles, context} = useFloating({
        open,
        onOpenChange,
        placement: 'bottom-end',
        // Fixed positioning escapes scrolling ancestors such as dialog bodies, which would clip the panel.
        strategy: 'fixed',
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

    const onPanelKeyDown = (event: React.KeyboardEvent): void => {
        if (event.key === 'Escape') {
            // Only the calendar closes, a dialog around the form stays open.
            event.preventDefault();
            setOpen(false);
        }
    };

    const applyAndClose = (text: string): void => {
        setOpen(false);
        void apply(text);
    };

    const monthFormat = new Intl.DateTimeFormat(lang, {month: 'long', year: 'numeric'});
    const monthName = monthFormat.format(new Date(view.year, view.month - 1, 1));
    const weekdayFormat = new Intl.DateTimeFormat(lang, {weekday: 'short'});
    const firstDay = firstDayOfWeek(moodleFirstDay, lang);
    const weekdays = Array.from({length: 7}, (_, i) => {
        // The 4th of January 2026 is a Sunday, so the 5th is Monday which is weekday 1.
        const weekday = ((firstDay - 1 + i) % 7) + 1;
        return weekdayFormat.format(new Date(2026, 0, 4 + weekday));
    });

    const today = todayIn(timezone);
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

    const minuteOptions = (current: number): number[] => {
        const minutes: number[] = [];
        for (let minute = 0; minute < 60; minute += step) {
            minutes.push(minute);
        }
        if (!minutes.includes(current)) {
            minutes.push(current);
            minutes.sort((a, b) => a - b);
        }
        return minutes;
    };
    const minutes = minuteOptions(parts.minute);
    const hours = Array.from({length: 24}, (_, i) => i);
    const hourFormat = new Intl.DateTimeFormat(lang, {hour: 'numeric', hour12: true});
    const hourLabel = (hour: number): string => (
        hour12 ? hourFormat.format(new Date(2026, 0, 5, hour)) : String(hour).padStart(2, '0')
    );

    const endParts = fromWallMinutes(wallMinutes(parts) + duration, parts.second);
    const selectedText = (start: Components): string => {
        if (!range) {
            return formatComponents(start);
        }
        // Full ISO end, the server shortens it to a time with +N days.
        const end = fromWallMinutes(wallMinutes(start) + duration, start.second);
        return formatComponents(start) + ' \u2013 ' + formatComponents(end);
    };
    const setEndTime = (hour: number, minute: number): void => {
        // The first such time after the start, the same rule as typed end times.
        let minutes = wallMinutes({...parts, hour, minute}) - wallMinutes(parts);
        if (minutes <= 0) {
            minutes += 24 * 60;
        }
        setDuration(minutes);
    };
    const durations = range ? range.durations.map((item) => ({minutes: Math.round(item.seconds / 60), label: item.label})) : [];
    if (range && !durations.some((item) => item.minutes === duration)) {
        const label = `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, '0')}`;
        durations.push({minutes: duration, label});
        durations.sort((a, b) => a.minutes - b.minutes);
    }
    const endOffset = dayOffset(parts, endParts);

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
                        {...getFloatingProps({onKeyDown: onPanelKeyDown})}
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
                                                        onDoubleClick={() => applyAndClose(
                                                            selectedText({...parts, year: view.year, month: view.month, day})
                                                        )}
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
                        <div className="d-flex align-items-center gap-1 mb-2" role={range ? 'group' : undefined}
                            aria-label={range ? range.strings.start : undefined}>
                            {range && <span className="muform-datetime-rangelabel" aria-hidden="true">{range.strings.start}</span>}
                            <select className="form-select form-select-sm w-auto" aria-label={strings.hour} value={parts.hour}
                                onChange={(event) => setParts({...parts, hour: Number(event.target.value)})}>
                                {hours.map((hour) => <option key={hour} value={hour}>{hourLabel(hour)}</option>)}
                            </select>
                            <span aria-hidden="true">:</span>
                            <select className="form-select form-select-sm w-auto" aria-label={strings.minute} value={parts.minute}
                                onChange={(event) => setParts({...parts, minute: Number(event.target.value)})}>
                                {minutes.map((minute) => (
                                    <option key={minute} value={minute}>{String(minute).padStart(2, '0')}</option>
                                ))}
                            </select>
                        </div>
                        {range && (
                            <div className="d-flex align-items-center gap-1 mb-2" role="group" aria-label={range.strings.end}
                                data-muform-datetimerange-end>
                                <span className="muform-datetime-rangelabel" aria-hidden="true">{range.strings.end}</span>
                                <select className="form-select form-select-sm w-auto" aria-label={strings.hour}
                                    value={endParts.hour}
                                    onChange={(event) => setEndTime(Number(event.target.value), endParts.minute)}>
                                    {hours.map((hour) => <option key={hour} value={hour}>{hourLabel(hour)}</option>)}
                                </select>
                                <span aria-hidden="true">:</span>
                                <select className="form-select form-select-sm w-auto" aria-label={strings.minute}
                                    value={endParts.minute}
                                    onChange={(event) => setEndTime(endParts.hour, Number(event.target.value))}>
                                    {minuteOptions(endParts.minute).map((minute) => (
                                        <option key={minute} value={minute}>{String(minute).padStart(2, '0')}</option>
                                    ))}
                                </select>
                                {endOffset > 0 && <sup data-muform-datetimerange-dayoffset>+{endOffset}</sup>}
                            </div>
                        )}
                        {range && (
                            <div className="d-flex align-items-center gap-1 mb-2">
                                <select className="form-select form-select-sm w-auto" aria-label={range.strings.duration}
                                    title={range.strings.duration} value={duration}
                                    data-muform-datetimerange-duration
                                    onChange={(event) => setDuration(Number(event.target.value))}>
                                    {durations.map((item) => (
                                        <option key={item.minutes} value={item.minutes}>{item.label}</option>
                                    ))}
                                </select>
                            </div>
                        )}
                        <div className="d-flex gap-1">
                            <button type="button" className="btn btn-sm btn-light"
                                onClick={() => select({...parts, ...todayIn(timezone)})}>
                                {strings.today}
                            </button>
                            <button type="button" className="btn btn-sm btn-light" onClick={() => applyAndClose('')}>
                                {strings.clear}
                            </button>
                            <button type="button" className="btn btn-sm btn-primary ms-auto"
                                onClick={() => applyAndClose(selectedText(parts))}>
                                {strings.apply}
                            </button>
                        </div>
                    </div>
                </FloatingFocusManager>
            )}
        </>
    );
}
