var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the datetime element.
 *
 * The server renders a named text input. This module unhooks it from the form
 * and injects a hidden input with the same name carrying the timestamp, so the
 * server receives an integer whenever JavaScript ran. Typed text is normalised
 * by the server through the datetime endpoint, the React picker is an enhancement.
 *
 * @module     tool_mulib/muform/element/datetime
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { mountReactApp } from "@moodle/lms/core/mount";
import { normalise, readComponents } from "../../date/api";
import { rememberDate } from "../../date/recent";
import DatePicker, { getPickerStrings } from "../../date/panel";
import NativeElement from "../native";
class datetime_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /** Visible text input, null when frozen. */
  input;
  /** Hidden input submitting the timestamp. */
  carrier = null;
  /** The last normalisation said the text is invalid. */
  invalid = false;
  /** Unmount function of the picker island. */
  unmountPicker = null;
  /** Disabled state the picker was mounted with. */
  pickerDisabled = null;
  /** Localised picker strings, loaded once. */
  strings = null;
  /**
   * Unhook the text input, inject the timestamp carrier and mount the picker.
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
    const carrier = document.createElement("input");
    carrier.type = "hidden";
    carrier.name = input.name;
    carrier.value = input.dataset.muformDatetimeTimestamp || input.value;
    carrier.disabled = input.disabled;
    input.removeAttribute("name");
    input.insertAdjacentElement("beforebegin", carrier);
    this.carrier = carrier;
    input.addEventListener("input", () => {
      this.markEmpty();
      carrier.value = input.value;
      this.invalid = false;
    });
    input.addEventListener("change", () => {
      void this.applyText(input.value);
    });
    this.markEmpty();
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
   * The timestamp, or the raw text before normalisation.
   *
   * @returns null when there is no value
   */
  getValue() {
    if (!this.carrier) {
      return null;
    }
    return this.carrier.value === "" ? null : this.carrier.value;
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
    this.markEmpty();
    if (this.strings && this.pickerDisabled !== this.state.disabled) {
      this.renderPicker();
    }
  }
  /**
   * Mark empty values, the clear button is hidden for them.
   */
  markEmpty() {
    this.wrapper.querySelector(".muform-datetime")?.classList.toggle("muform-datetime-empty", !this.input?.value);
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
    const { input, carrier } = this;
    if (!input || !carrier) {
      return;
    }
    const answer = await normalise(input, text);
    if (answer.valid) {
      input.value = answer.text;
      carrier.value = answer.timestamp === null ? "" : String(answer.timestamp);
      this.invalid = false;
      rememberDate(answer.text, answer.components);
    } else {
      input.value = text;
      carrier.value = text;
      this.invalid = true;
    }
    const errors = this.invalid && this.state.touched ? [this.getHint("invalid")] : [];
    if (this.state.errors.length || errors.length) {
      this.state.errors = errors;
      this.syncUI();
    }
    this.markEmpty();
    this.emitChange();
  }
  /**
   * Load the strings and mount the picker island.
   */
  async mountPicker() {
    const span = this.wrapper.querySelector("[data-muform-datetime-picker]");
    if (!span) {
      return;
    }
    this.strings = await getPickerStrings();
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
    const { input } = this;
    this.unmountPicker = mountReactApp(span, DatePicker, {
      getValue: /* @__PURE__ */ __name(() => readComponents(input), "getValue"),
      timezone: input.dataset.muformDatetimeTimezone ?? "",
      defaultHour: Number(input.dataset.muformDatetimeDefaulthour) || 0,
      defaultMinute: Number(input.dataset.muformDatetimeDefaultminute) || 0,
      step: Number(span.dataset.muformDatetimeStep) || 5,
      firstDay: span.dataset.muformDatetimeFirstday === void 0 ? null : Number(span.dataset.muformDatetimeFirstday),
      hour12: span.dataset.muformDatetimeHour12 === "1",
      disabled: this.state.disabled,
      strings: this.strings,
      apply: /* @__PURE__ */ __name((text) => this.applyText(text), "apply"),
      clearable: span.dataset.muformDatetimeClearable === "1",
      title: this.wrapper.querySelector(`#${CSS.escape(input.id)}_label`)?.textContent?.trim() || void 0
    }, { id: `muform-datetime-${this.name}` });
  }
}
export {
  datetime_default as default
};
//# sourceMappingURL=datetime.dev.js.map
