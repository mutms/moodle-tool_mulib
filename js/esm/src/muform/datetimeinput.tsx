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
 * Date and time input for React code outside of muform forms, same text entry and picker as the datetime element.
 *
 * The value is a timestamp, typed text is normalised by the server.
 *
 * @module     tool_mulib/muform/datetimeinput
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState, type ReactNode} from 'react';
import {normaliseText, type Components} from './datetimeapi';
import DateTimePicker, {getPickerStrings, type PickerStrings} from './datetimepicker';

/** User settings from the server, see tool_mulib\muform\util\calendar::get_picker_settings(). */
export interface DateTimeInputSettings {
    timezone: string;
    lang: string;
    step: number;
    firstday: number | null;
    hour12: boolean;
}

/** Input props. */
export interface DateTimeInputProps {
    /** Timestamp, null means no value. */
    value: number | null;
    onChange: (value: number | null) => void;
    settings: DateTimeInputSettings;
    /** Hour of dates typed or picked without time. */
    defaultHour?: number;
    /** Minute of dates typed or picked without time. */
    defaultMinute?: number;
    id?: string;
    label?: string;
    autoFocus?: boolean;
    /** Small input and picker button. */
    small?: boolean;
}

/** Picker strings are loaded once. */
let stringsPromise: Promise<PickerStrings> | null = null;

/**
 * Date and time input.
 *
 * @param props
 * @returns element
 */
export default function DateTimeInput(props: DateTimeInputProps): ReactNode {
    const {value, settings, defaultHour = 0, defaultMinute = 0, small = false} = props;
    const [text, setText] = useState('');
    const [invalid, setInvalid] = useState(false);
    const [strings, setStrings] = useState<PickerStrings | null>(null);
    const components = useRef<Components | null>(null);
    const known = useRef<number | null | undefined>(undefined);
    const committed = useRef('');
    const onChange = useRef(props.onChange);
    onChange.current = props.onChange;

    const normaliseSettings = {timezone: settings.timezone, lang: settings.lang, defaultHour, defaultMinute};

    useEffect(() => {
        stringsPromise ??= getPickerStrings();
        void stringsPromise.then(setStrings);
    }, []);

    useEffect(() => {
        if (value === known.current) {
            return;
        }
        known.current = value;
        if (value === null) {
            components.current = null;
            committed.current = '';
            setText('');
            setInvalid(false);
            return;
        }
        void (async() => {
            const answer = await normaliseText(String(value), normaliseSettings);
            if (answer.valid && known.current === value) {
                components.current = answer.components;
                committed.current = answer.text;
                setText(answer.text);
                setInvalid(false);
            }
        })();
    }, [value]);

    const commit = async(input: string): Promise<void> => {
        const answer = await normaliseText(input, normaliseSettings);
        if (!answer.valid) {
            setInvalid(true);
            return;
        }
        components.current = answer.components;
        committed.current = answer.text;
        known.current = answer.timestamp;
        setText(answer.text);
        setInvalid(false);
        if (answer.timestamp !== value) {
            onChange.current(answer.timestamp);
        }
    };

    return (
        <div className="muform-datetime d-flex align-items-start gap-1">
            <input type="text" className={`form-control${small ? ' form-control-sm' : ''}${invalid ? ' is-invalid' : ''}`}
                id={props.id} aria-label={props.label} aria-invalid={invalid || undefined} autoComplete="off"
                autoFocus={props.autoFocus} value={text}
                onChange={(event) => {
                    setText(event.target.value);
                    setInvalid(false);
                }}
                onBlur={() => {
                    if (text !== committed.current) {
                        void commit(text);
                    }
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        void commit(text);
                    }
                }} />
            {strings && (
                <DateTimePicker getValue={() => components.current} timezone={settings.timezone}
                    defaultHour={defaultHour} defaultMinute={defaultMinute} step={settings.step} firstDay={settings.firstday}
                    hour12={settings.hour12} disabled={false} strings={strings} small={small}
                    apply={(picked: string) => commit(picked)} />
            )}
        </div>
    );
}
