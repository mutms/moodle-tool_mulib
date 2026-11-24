var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Date and time input for React code outside of muform forms, same text entry and picker as the datetime element.
 *
 * The value is a timestamp, typed text is normalised by the server.
 *
 * @module     tool_mulib/muform/datetimeinput
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef, useState } from "react";
import { normaliseText } from "./datetimeapi";
import DateTimePicker, { getPickerStrings } from "./datetimepicker";
let stringsPromise = null;
function DateTimeInput(props) {
  const { value, settings, defaultHour = 0, defaultMinute = 0, small = false } = props;
  const [text, setText] = useState("");
  const [invalid, setInvalid] = useState(false);
  const [strings, setStrings] = useState(null);
  const components = useRef(null);
  const known = useRef(void 0);
  const committed = useRef("");
  const onChange = useRef(props.onChange);
  onChange.current = props.onChange;
  const normaliseSettings = { timezone: settings.timezone, lang: settings.lang, defaultHour, defaultMinute };
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
      committed.current = "";
      setText("");
      setInvalid(false);
      return;
    }
    void (async () => {
      const answer = await normaliseText(String(value), normaliseSettings);
      if (answer.valid && known.current === value) {
        components.current = answer.components;
        committed.current = answer.text;
        setText(answer.text);
        setInvalid(false);
      }
    })();
  }, [value]);
  const commit = /* @__PURE__ */ __name(async (input) => {
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
  }, "commit");
  return /* @__PURE__ */ jsxDEV("div", { className: "muform-datetime d-flex align-items-start gap-1", children: [
    /* @__PURE__ */ jsxDEV(
      "input",
      {
        type: "text",
        className: `form-control${small ? " form-control-sm" : ""}${invalid ? " is-invalid" : ""}`,
        id: props.id,
        "aria-label": props.label,
        "aria-invalid": invalid || void 0,
        autoComplete: "off",
        autoFocus: props.autoFocus,
        value: text,
        onChange: (event) => {
          setText(event.target.value);
          setInvalid(false);
        },
        onBlur: () => {
          if (text !== committed.current) {
            void commit(text);
          }
        },
        onKeyDown: (event) => {
          if (event.key === "Enter") {
            event.preventDefault();
            void commit(text);
          }
        }
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimeinput.tsx",
        lineNumber: 123,
        columnNumber: 13
      },
      this
    ),
    strings && /* @__PURE__ */ jsxDEV(
      DateTimePicker,
      {
        getValue: () => components.current,
        timezone: settings.timezone,
        defaultHour,
        defaultMinute,
        step: settings.step,
        firstDay: settings.firstday,
        hour12: settings.hour12,
        disabled: false,
        strings,
        small,
        apply: (picked) => commit(picked)
      },
      void 0,
      false,
      {
        fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimeinput.tsx",
        lineNumber: 142,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/admin/tool/mulib/js/esm/src/muform/datetimeinput.tsx",
    lineNumber: 122,
    columnNumber: 9
  }, this);
}
__name(DateTimeInput, "DateTimeInput");
export {
  DateTimeInput as default
};
//# sourceMappingURL=datetimeinput.dev.js.map
