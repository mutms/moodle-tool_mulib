var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the datetimerange element.
 *
 * The server renders a named text input. This module unhooks it from the form
 * and injects hidden inputs name[start], name[end] and name[text]: normalised values
 * submit both timestamps, text the server did not answer yet is submitted as it is
 * and parsed by the server the same way. The React picker is an enhancement.
 *
 * @module     tool_mulib/muform/element/datetimerange
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { mountReactApp } from "@moodle/lms/core/mount";
import { getString } from "@moodle/lms/core/stringUtils";
import { normaliseRange } from "../datetimerangeapi";
import DateTimePicker from "../datetimepicker";
import NativeElement from "../native";
class datetimerange_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /** Visible text input, null when frozen. */
  input;
  /** Hidden inputs submitting timestamps or text. */
  carriers = null;
  /** Duration and end date of the current value. */
  hint = null;
  /** The last normalisation said the text is invalid. */
  invalid = false;
  /** Unmount function of the picker island. */
  unmountPicker = null;
  /** Disabled state the picker was mounted with. */
  pickerDisabled = null;
  /** Localised picker strings, loaded once. */
  strings = null;
  /** Range options of the picker, loaded once. */
  range = null;
  /**
   * Unhook the text input, inject the carriers and mount the picker.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @param form the form API
   */
  constructor(wrapper, form) {
    super(wrapper, form);
    this.input = wrapper.querySelector("input[data-muform-datetime-timezone]");
    if (!this.input) {
      return;
    }
    const { input } = this;
    this.hint = wrapper.querySelector("[data-muform-datetimerange-hint]");
    const carrier = /* @__PURE__ */ __name((key, value) => {
      const hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = `${input.name}[${key}]`;
      hidden.value = value;
      hidden.disabled = input.disabled;
      input.insertAdjacentElement("beforebegin", hidden);
      return hidden;
    }, "carrier");
    const start = input.dataset.muformDatetimerangeStart ?? "";
    const end = input.dataset.muformDatetimerangeEnd ?? "";
    const normalised = start !== "" && end !== "";
    this.carriers = {
      start: carrier("start", normalised ? start : ""),
      end: carrier("end", normalised ? end : ""),
      text: carrier("text", normalised ? "" : input.value)
    };
    input.removeAttribute("name");
    input.addEventListener("input", () => {
      this.setCarriers("", "", input.value);
      this.invalid = false;
    });
    input.addEventListener("change", () => {
      void this.applyText(input.value);
    });
    void this.mountPicker();
  }
  /**
   * Only the text input is a control, the picker island has its own selects and buttons.
   *
   * @returns the text input when not frozen
   */
  controls() {
    return this.input ? [this.input] : [];
  }
  /**
   * Start and end timestamps joined with a hyphen, or the raw text before normalisation.
   *
   * @returns null when there is no value
   */
  getValue() {
    if (!this.carriers) {
      return null;
    }
    const { start, end, text } = this.carriers;
    if (start.value !== "" && end.value !== "") {
      return `${start.value}-${end.value}`;
    }
    return text.value === "" ? null : text.value;
  }
  /**
   * Native validation plus the last answer of the server.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    const errors = super.validate();
    if (this.invalid && !this.state.hidden && !this.state.disabled && errors.length === 0) {
      errors.push(this.getHint("invalid"));
    }
    return errors;
  }
  /**
   * Project the state, the picker follows the disabled flag.
   */
  syncUI() {
    super.syncUI();
    if (this.strings && this.pickerDisabled !== this.state.disabled) {
      this.renderPicker();
    }
  }
  /**
   * Unmount the picker.
   */
  destroy() {
    this.unmountPicker?.();
    this.unmountPicker = null;
  }
  /**
   * Let the server interpret text and show the result.
   *
   * @param text typed or picked text, empty clears the value
   */
  async applyText(text) {
    const { input } = this;
    if (!input || !this.carriers) {
      return;
    }
    const answer = await normaliseRange(input, text);
    if (answer.valid) {
      input.value = answer.text;
      if (answer.start === null || answer.end === null) {
        this.setCarriers("", "", "");
      } else {
        this.setCarriers(String(answer.start), String(answer.end), "");
      }
      this.setHint(answer.hint);
      this.invalid = false;
    } else {
      input.value = text;
      this.setCarriers("", "", text);
      this.setHint("");
      this.invalid = true;
    }
    const errors = this.invalid && this.state.touched ? [this.getHint("invalid")] : [];
    if (this.state.errors.length || errors.length) {
      this.state.errors = errors;
      this.syncUI();
    }
    this.emitChange();
  }
  /**
   * Update the hidden inputs.
   *
   * @param start start timestamp or empty
   * @param end end timestamp or empty
   * @param text raw text or empty
   */
  setCarriers(start, end, text) {
    if (!this.carriers) {
      return;
    }
    this.carriers.start.value = start;
    this.carriers.end.value = end;
    this.carriers.text.value = text;
  }
  /**
   * Show the duration hint of the current value.
   *
   * @param text hint text from the server, empty hides it
   */
  setHint(text) {
    if (this.hint) {
      this.hint.textContent = text;
    }
  }
  /**
   * Load the strings and mount the picker island.
   */
  async mountPicker() {
    const span = this.wrapper.querySelector("[data-muform-datetime-picker]");
    if (!span || !this.input) {
      return;
    }
    const [pick, today, clear, apply, hour, minute, monthprev, monthnext, yearprev, yearnext, start, end, duration] = await Promise.all([
      getString("muform_pickdatetimerange", "tool_mulib"),
      getString("muform_today", "tool_mulib"),
      getString("muform_clear", "tool_mulib"),
      getString("muform_apply", "tool_mulib"),
      getString("muform_hour", "tool_mulib"),
      getString("muform_minute", "tool_mulib"),
      getString("muform_monthprev", "tool_mulib"),
      getString("muform_monthnext", "tool_mulib"),
      getString("muform_yearprev", "tool_mulib"),
      getString("muform_yearnext", "tool_mulib"),
      getString("muform_rangestart", "tool_mulib"),
      getString("muform_rangeend", "tool_mulib"),
      getString("muform_duration", "tool_mulib")
    ]);
    this.strings = { pick, today, clear, apply, hour, minute, monthprev, monthnext, yearprev, yearnext };
    let durations = [];
    try {
      durations = JSON.parse(span.dataset.muformDatetimerangeDurations || "[]");
    } catch {
      durations = [];
    }
    const defaultDuration = Number(this.input.dataset.muformDatetimerangeDefaultduration) || null;
    this.range = { durations, defaultDuration, strings: { start, end, duration } };
    this.renderPicker();
  }
  /**
   * Mount or remount the picker with the current disabled state.
   */
  renderPicker() {
    const span = this.wrapper.querySelector("[data-muform-datetime-picker]");
    if (!span || !this.input || !this.strings) {
      return;
    }
    this.unmountPicker?.();
    this.pickerDisabled = this.state.disabled;
    this.unmountPicker = mountReactApp(span, DateTimePicker, {
      input: this.input,
      step: Number(span.dataset.muformDatetimeStep) || 5,
      firstDay: span.dataset.muformDatetimeFirstday === void 0 ? null : Number(span.dataset.muformDatetimeFirstday),
      hour12: span.dataset.muformDatetimeHour12 === "1",
      disabled: this.state.disabled,
      strings: this.strings,
      apply: /* @__PURE__ */ __name((text) => this.applyText(text), "apply"),
      range: this.range
    }, { id: `muform-datetimerange-${this.name}` });
  }
}
export {
  datetimerange_default as default
};
//# sourceMappingURL=datetimerange.dev.js.map
