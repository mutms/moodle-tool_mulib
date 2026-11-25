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
 * Date and time picker panel over the right half of the screen, the same for forms and reports.
 *
 * The panel only edits date parts, the text it returns is parsed by the server. Modes: one date and time,
 * and a range of at most one day (datetimerange element, changing the start keeps the duration).
 * Month and weekday names come from Intl for display only,
 * the first day of week comes from Moodle. "Today" is in the timezone of the value.
 *
 * @module     tool_mulib/date/panel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {mountReactApp} from '@moodle/lms/core/mount';
import Pending from '@moodle/lms/core/pending';
import {getString} from '@moodle/lms/core/stringUtils';
import {useEffect, useId, useRef, useState, type ReactNode} from 'react';
import {openPanel} from '../picker/panel';
import {formatComponents, todayIn, type Components} from './api';

/** Localised texts of the panel. */
export interface PickerStrings {
    pick: string;
    today: string;
    clear: string;
    apply: string;
    close: string;
    hour: string;
    minute: string;
    monthprev: string;
    monthnext: string;
    yearprev: string;
    yearnext: string;
}

/**
 * Localised texts of the panel.
 *
 * @returns strings
 */
export async function getPickerStrings(): Promise<PickerStrings> {
    const keys = ['pickdatetime', 'today', 'clear', 'apply', 'close', 'hour', 'minute', 'monthprev', 'monthnext', 'yearprev',
        'yearnext'];
    const [pick, today, clear, apply, close, hour, minute, monthprev, monthnext, yearprev, yearnext] = await Promise.all(
        keys.map((key) => (key === 'close' ? getString('closebuttontitle', 'core') : getString(`muform_${key}`, 'tool_mulib'))),
    );
    return {pick, today, clear, apply, close, hour, minute, monthprev, monthnext, yearprev, yearnext};
}

/** Duration offered in range mode. */
export interface PickerDuration {
    seconds: number;
    label: string;
}

/** Range mode, used by the datetimerange element. */
export interface RangeOptions {
    /** Durations offered in the duration select. */
    durations: PickerDuration[];
    /** Seconds of empty values, null means one hour. */
    defaultDuration: number | null;
    /** Localised texts. */
    strings: {start: string; end: string; duration: string};
}

/** User settings, see tool_mulib\local\date_util::get_picker_settings(). */
export interface DateSettings {
    /** IANA timezone of the value. */
    timezone: string;
    /** Minute step of the minute list. */
    step: number;
    /** First day of week from Moodle, 0 is Sunday, null means the language default. */
    firstDay?: number | null;
    /** Hours are shown with AM and PM. */
    hour12?: boolean;
}

/** Options of a panel. */
export interface DatePanelOptions extends DateSettings {
    mode: 'single' | 'range';
    /** Title of the panel, for example the label of the field, the pick string by default. */
    title?: string;
    strings: PickerStrings;
    /** Current value or start, null when empty. */
    start: Components | null;
    /** Current range end, null when empty. */
    end?: Components | null;
    /** Hour and minute of empty values. */
    defaultHour?: number;
    defaultMinute?: number;
    range?: RangeOptions | null;
}

/** Applied value: text for the server, empty texts clear values. */
export interface DatePanelResult {
    text: string;
}

/** Locale week information, not yet in the TypeScript DOM library. */
interface WeekInfo {
    firstDay?: number;
}

/** Day without time. */
type Day = Pick<Components, 'year' | 'month' | 'day'>;

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
 * Move a day by a number of days or months.
 *
 * @param day
 * @param days positive or negative
 * @param months positive or negative
 * @returns new day
 */
export function moveDay<T extends Day>(day: T, days: number, months = 0): T {
    const lastDay = daysInMonth(new Date(day.year, day.month - 1 + months, 1).getFullYear(),
        new Date(day.year, day.month - 1 + months, 1).getMonth() + 1);
    const date = new Date(day.year, day.month - 1 + months, Math.min(day.day, lastDay) + days);
    return {...day, year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate()};
}

/**
 * Number for comparing days.
 *
 * @param day
 * @returns days since the epoch
 */
function dayNumber(day: Day): number {
    return Date.UTC(day.year, day.month - 1, day.day) / 86400000;
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
 * Wall clock minutes of the current range, the default duration for empty values.
 *
 * @param end current end parts
 * @param start current start parts
 * @param range range options
 * @returns positive number of minutes
 */
function rangeMinutes(end: Components | null, start: Components, range: RangeOptions): number {
    if (end) {
        const minutes = wallMinutes(end) - wallMinutes(start);
        // Ranges are at most one day long.
        if (minutes > 0 && minutes <= 24 * 60) {
            return minutes;
        }
    }
    return Math.max(1, Math.round((range.defaultDuration ?? 3600) / 60));
}

/** Calendar props. */
interface CalendarProps {
    view: {year: number; month: number};
    onView: (view: {year: number; month: number}) => void;
    /** Day with focus, keyboard moves it. */
    focus: Day;
    onFocus: (day: Day) => void;
    /** Selection state of a day. */
    selection: (day: Day) => boolean;
    onSelect: (day: Day) => void;
    onActivate?: (day: Day) => void;
    firstDay: number;
    today: Day;
    lang: string;
    strings: PickerStrings;
}

/**
 * Month grid with navigation.
 *
 * @param props
 * @returns element
 */
function Calendar(props: CalendarProps): ReactNode {
    const {view, focus, firstDay, today, lang, strings} = props;
    const gridRef = useRef<HTMLTableElement>(null);
    const keyboard = useRef(false);

    useEffect(() => {
        if (keyboard.current) {
            gridRef.current?.querySelector<HTMLButtonElement>('button[tabindex="0"]')?.focus();
            keyboard.current = false;
        }
    }, [focus]);

    const moveMonth = (delta: number): void => {
        const date = new Date(view.year, view.month - 1 + delta, 1);
        props.onView({year: date.getFullYear(), month: date.getMonth() + 1});
    };
    const moveFocus = (day: Day): void => {
        keyboard.current = true;
        props.onFocus(day);
        props.onView({year: day.year, month: day.month});
    };
    const onKeyDown = (event: React.KeyboardEvent): void => {
        const weekday = (new Date(focus.year, focus.month - 1, focus.day).getDay() + 7 - firstDay) % 7;
        const moves: Record<string, () => Day> = {
            ArrowLeft: () => moveDay(focus, -1),
            ArrowRight: () => moveDay(focus, 1),
            ArrowUp: () => moveDay(focus, -7),
            ArrowDown: () => moveDay(focus, 7),
            Home: () => moveDay(focus, -weekday),
            End: () => moveDay(focus, 6 - weekday),
            PageUp: () => moveDay(focus, 0, event.shiftKey ? -12 : -1),
            PageDown: () => moveDay(focus, 0, event.shiftKey ? 12 : 1),
        };
        if (moves[event.key]) {
            event.preventDefault();
            moveFocus(moves[event.key]());
        }
    };

    const monthName = new Intl.DateTimeFormat(lang, {month: 'long', year: 'numeric'})
        .format(new Date(view.year, view.month - 1, 1));
    const weekdayFormat = new Intl.DateTimeFormat(lang, {weekday: 'short'});
    const weekdays = Array.from({length: 7}, (_, i) => {
        // The 4th of January 2026 is a Sunday, so the 5th is Monday which is weekday 1.
        const weekday = ((firstDay - 1 + i) % 7) + 1;
        return weekdayFormat.format(new Date(2026, 0, 4 + weekday));
    });
    const first = new Date(view.year, view.month - 1, 1);
    const firstWeekday = first.getDay() === 0 ? 7 : first.getDay();
    const cells: (number | null)[] = Array<null>((firstWeekday - firstDay + 7) % 7).fill(null);
    for (let day = 1; day <= daysInMonth(view.year, view.month); day++) {
        cells.push(day);
    }
    while (cells.length % 7 !== 0) {
        cells.push(null);
    }
    const rows: (number | null)[][] = [];
    for (let i = 0; i < cells.length; i += 7) {
        rows.push(cells.slice(i, i + 7));
    }
    const focusInView = focus.year === view.year && focus.month === view.month;
    const navButton = (label: string, icon: string, delta: number): ReactNode => (
        <button type="button" className="btn btn-light" aria-label={label} title={label} onClick={() => moveMonth(delta)}>
            <i className={`fa ${icon}`} aria-hidden="true"></i>
        </button>
    );

    return (
        <div className="tool_mulib-date-calendar">
            <div className="d-flex align-items-center justify-content-between mb-2">
                <span className="d-flex gap-1">
                    {navButton(strings.yearprev, 'fa-angles-left', -12)}
                    {navButton(strings.monthprev, 'fa-chevron-left', -1)}
                </span>
                <span className="fw-bold" aria-live="polite">{monthName}</span>
                <span className="d-flex gap-1">
                    {navButton(strings.monthnext, 'fa-chevron-right', 1)}
                    {navButton(strings.yearnext, 'fa-angles-right', 12)}
                </span>
            </div>
            <table className="table table-borderless text-center mb-0" ref={gridRef} role="grid" onKeyDown={onKeyDown}>
                <thead>
                    <tr>{weekdays.map((name) => <th key={name} scope="col" className="small fw-normal">{name}</th>)}</tr>
                </thead>
                <tbody>
                    {rows.map((row, r) => (
                        <tr key={r}>
                            {row.map((number, c) => {
                                if (number === null) {
                                    return <td key={c}></td>;
                                }
                                const day = {year: view.year, month: view.month, day: number};
                                const state = props.selection(day);
                                const isToday = dayNumber(day) === dayNumber(today);
                                const classes = ['btn', 'tool_mulib-date-day', state ? 'btn-primary' : 'btn-light',
                                    isToday ? 'fw-bold' : ''];
                                return (
                                    <td key={c}>
                                        <button type="button" className={classes.join(' ').trim()}
                                            tabIndex={(focusInView ? focus.day : 1) === number ? 0 : -1}
                                            aria-pressed={state} aria-current={isToday ? 'date' : undefined}
                                            data-tool-mulib-date-day={number}
                                            onClick={() => {
                                                props.onFocus(day);
                                                props.onSelect(day);
                                            }}
                                            onDoubleClick={() => props.onActivate?.(day)}>
                                            {number}
                                        </button>
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Time picker props. */
interface TimeProps {
    hour: number;
    minute: number;
    step: number;
    hour12: boolean;
    lang: string;
    strings: PickerStrings;
    /** Visible label of the pair, for example Start. */
    label?: string;
    /** Shown after the selects, for example the day offset of a range end. */
    children?: ReactNode;
    onChange: (hour: number, minute: number) => void;
}

/**
 * Hour and minute selects.
 *
 * @param props
 * @returns element
 */
function TimePicker(props: TimeProps): ReactNode {
    const {hour, minute, step, hour12, lang, strings} = props;
    const minutes: number[] = [];
    for (let item = 0; item < 60; item += Math.max(1, step)) {
        minutes.push(item);
    }
    if (!minutes.includes(minute)) {
        minutes.push(minute);
        minutes.sort((a, b) => a - b);
    }
    const hourFormat = new Intl.DateTimeFormat(lang, {hour: 'numeric', hour12: true});
    const hourLabel = (item: number): string => (hour12 ? hourFormat.format(new Date(2026, 0, 5, item))
        : String(item).padStart(2, '0'));
    return (
        <div role="group" aria-label={props.label} className="d-flex align-items-center gap-2 tool_mulib-date-time">
            {props.label && <span className="tool_mulib-date-timelabel" aria-hidden="true">{props.label}</span>}
            <select className="form-select w-auto" aria-label={strings.hour} value={hour}
                onChange={(event) => props.onChange(Number(event.target.value), minute)}>
                {Array.from({length: 24}, (_, i) => i).map((item) => <option key={item} value={item}>{hourLabel(item)}</option>)}
            </select>
            <span aria-hidden="true">:</span>
            <select className="form-select w-auto" aria-label={strings.minute} value={minute}
                onChange={(event) => props.onChange(hour, Number(event.target.value))}>
                {minutes.map((item) => <option key={item} value={item}>{String(item).padStart(2, '0')}</option>)}
            </select>
            {props.children}
        </div>
    );
}

/** Panel component props. */
interface PanelProps {
    options: DatePanelOptions;
    onApply: (result: DatePanelResult) => void;
    onClose: () => void;
}

/**
 * Footer buttons.
 *
 * @param props
 * @param props.strings
 * @param props.onToday
 * @param props.onClear
 * @param props.onApply
 * @param props.onClose
 * @returns element
 */
function Footer({strings, onToday, onClear, onApply, onClose}: {
    strings: PickerStrings; onToday: () => void; onClear: () => void; onApply: () => void; onClose: () => void;
}): ReactNode {
    return (
        <div className="d-flex flex-wrap align-items-center gap-2 p-3 border-top">
            <button type="button" className="btn btn-light" onClick={onToday}>{strings.today}</button>
            <button type="button" className="btn btn-light" onClick={onClear}>{strings.clear}</button>
            <span className="ms-auto d-inline-flex gap-2">
                <button type="button" className="btn btn-primary" data-tool-mulib-date-apply onClick={onApply}>
                    {strings.apply}
                </button>
                <button type="button" className="btn btn-secondary" onClick={onClose}>{strings.close}</button>
            </span>
        </div>
    );
}

/**
 * Header with title and close button.
 *
 * @param props
 * @param props.title
 * @param props.close
 * @param props.onClose
 * @returns element
 */
function Header({title, close, onClose}: {title: string; close: string; onClose: () => void}): ReactNode {
    return (
        <div className="d-flex align-items-center gap-2 p-3 border-bottom">
            <h2 className="h5 mb-0 flex-grow-1">{title}</h2>
            <button type="button" className="btn-close" aria-label={close} title={close} onClick={onClose}></button>
        </div>
    );
}

/**
 * Panel of one date and time, or of a range of at most one day.
 *
 * @param props
 * @returns element
 */
function SinglePanel({options, onApply, onClose}: PanelProps): ReactNode {
    const lang = document.documentElement.lang || 'en';
    const {strings, range} = options;
    const empty = {...todayIn(options.timezone), hour: options.defaultHour ?? 0, minute: options.defaultMinute ?? 0, second: 0};
    const [parts, setParts] = useState<Components>(options.start ?? empty);
    const [view, setView] = useState({year: parts.year, month: parts.month});
    const [duration, setDuration] = useState(() => (range ? rangeMinutes(options.end ?? null, parts, range) : 0));
    const durationId = useId();
    const firstDay = firstDayOfWeek(options.firstDay, lang);

    const textOf = (start: Components): string => {
        if (!range) {
            return formatComponents(start);
        }
        // Full ISO end, the server shortens it to a time with +N days.
        return formatComponents(start) + ' – ' + formatComponents(fromWallMinutes(wallMinutes(start) + duration, start.second));
    };
    const endParts = fromWallMinutes(wallMinutes(parts) + duration, parts.second);
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
        durations.push({minutes: duration, label: `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, '0')}`});
        durations.sort((a, b) => a.minutes - b.minutes);
    }
    const offset = dayNumber(endParts) - dayNumber(parts);
    const time = (label?: string): ReactNode => (
        <TimePicker hour={parts.hour} minute={parts.minute} step={options.step} hour12={!!options.hour12} lang={lang}
            strings={strings} label={label} onChange={(hour, minute) => setParts({...parts, hour, minute})} />
    );

    return (
        <div className="d-flex flex-column h-100 tool_mulib-date-panel" data-tool-mulib-date-panel={options.mode}>
            <Header title={options.title || strings.pick} close={strings.close} onClose={onClose} />
            <div className="flex-grow-1 overflow-auto p-3 d-flex flex-column align-items-center gap-3">
                <Calendar view={view} onView={setView} focus={parts} onFocus={(day) => setParts({...parts, ...day})}
                    selection={(day) => dayNumber(day) === dayNumber(parts)}
                    onSelect={(day) => setParts({...parts, ...day})}
                    onActivate={(day) => onApply({text: textOf({...parts, ...day})})}
                    firstDay={firstDay} today={todayIn(options.timezone)} lang={lang} strings={strings} />
                <div className="d-flex flex-column gap-2">
                    {time(range?.strings.start)}
                    {range && (
                        <div data-muform-datetimerange-end>
                            <TimePicker hour={endParts.hour} minute={endParts.minute} step={options.step}
                                hour12={!!options.hour12} lang={lang} strings={strings} label={range.strings.end}
                                onChange={setEndTime}>
                                {offset > 0 && <sup data-muform-datetimerange-dayoffset>+{offset}</sup>}
                            </TimePicker>
                        </div>
                    )}
                    {range && (
                        <div className="d-flex align-items-center gap-2">
                            <label className="tool_mulib-date-timelabel mb-0" htmlFor={durationId}>{range.strings.duration}</label>
                            <select className="form-select w-auto" id={durationId} value={duration}
                                data-muform-datetimerange-duration onChange={(event) => setDuration(Number(event.target.value))}>
                                {durations.map((item) => <option key={item.minutes} value={item.minutes}>{item.label}</option>)}
                            </select>
                        </div>
                    )}
                </div>
            </div>
            <Footer strings={strings} onClose={onClose} onClear={() => onApply({text: ''})}
                onToday={() => {
                    const today = todayIn(options.timezone);
                    setParts({...parts, ...today});
                    setView({year: today.year, month: today.month});
                }}
                onApply={() => onApply({text: textOf(parts)})} />
        </div>
    );
}

/**
 * Open the panel.
 *
 * @param options
 * @param trigger element focused again after closing
 * @returns applied result, null when closed without applying
 */
export function openDatePanel(options: DatePanelOptions, trigger?: HTMLElement | null): Promise<DatePanelResult | null> {
    return new Promise((resolve) => {
        let result: DatePanelResult | null = null;
        let pending: Pending | null = null;
        const label = options.title || options.strings.pick;
        const Panel = SinglePanel;
        openPanel({
            label,
            mount: (dialog, close) => mountReactApp(dialog, Panel, {
                options,
                onApply: (applied: DatePanelResult) => {
                    result = applied;
                    // Callers normalise the result, the wait covers the time until their own requests start.
                    pending = new Pending('tool_mulib/date:panel');
                    close();
                },
                onClose: close,
            }, {id: 'tool_mulib-date-panel'}),
            onClosed: () => {
                trigger?.focus();
                resolve(result);
                if (pending) {
                    const applied = pending;
                    setTimeout(() => applied.resolve(), 0);
                }
            },
        });
    });
}

/** Button props, the button opens the panel of one value or of a range. */
export interface PickerProps extends DateSettings {
    /** Returns date parts of the current value, null when there is no value. */
    getValue: () => Components | null;
    /** Returns date parts of the current range end, range mode only. */
    getEnd?: () => Components | null;
    /** Hour of empty values. */
    defaultHour?: number;
    /** Minute of empty values. */
    defaultMinute?: number;
    /** Disabled elements have no picker. */
    disabled: boolean;
    /** Localised texts. */
    strings: PickerStrings;
    /** Sends text to the server, empty text clears the value. */
    apply: (text: string) => Promise<void>;
    /** Range mode, getEnd returns the end. */
    range?: RangeOptions | null;
    /** Small picker button. */
    small?: boolean;
    /** Title of the panel, for example the label of the field. */
    title?: string;
    /** Clear button after the calendar button, for optional values. */
    clearable?: boolean;
}

/**
 * Calendar button of date inputs and elements.
 *
 * @param props
 * @returns element
 */
export default function DatePicker(props: PickerProps): ReactNode {
    const {strings, disabled, small = false} = props;
    const button = useRef<HTMLButtonElement>(null);
    if (disabled) {
        return null;
    }
    const open = async(): Promise<void> => {
        const result = await openDatePanel({
            mode: props.range ? 'range' : 'single', title: props.title,
            timezone: props.timezone, step: props.step, firstDay: props.firstDay, hour12: props.hour12,
            strings, start: props.getValue(), end: props.getEnd?.() ?? null,
            defaultHour: props.defaultHour, defaultMinute: props.defaultMinute, range: props.range ?? null,
        }, button.current);
        if (result) {
            await props.apply(result.text);
        }
    };
    return (
        <>
            <button ref={button} type="button" className={`btn btn-outline-secondary${small ? ' btn-sm' : ''}`}
                aria-label={strings.pick} title={strings.pick} aria-haspopup="dialog" onClick={() => {
                    void open();
                }}>
                <i className="fa fa-calendar" aria-hidden="true"></i>
            </button>
            {props.clearable && (
                <button type="button" className={`btn btn-outline-secondary${small ? ' btn-sm' : ''}`} data-muform-datetime-clear
                    aria-label={strings.clear} title={strings.clear} onClick={() => {
                        void props.apply('');
                    }}>
                    <i className="fa fa-xmark" aria-hidden="true"></i>
                </button>
            )}
        </>
    );
}
