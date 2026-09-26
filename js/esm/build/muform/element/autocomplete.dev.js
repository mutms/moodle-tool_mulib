var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of the autocomplete element: replaces the fallback text input
 * with the single value picker island; the island renders the hidden input the form posts.
 *
 * @module     tool_mulib/muform/element/autocomplete
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { mountReactApp } from "@moodle/lms/core/mount";
import { getString } from "@moodle/lms/core/stringUtils";
import Picker from "../autocompletepicker";
import NativeElement from "../native";
class autocomplete_default extends NativeElement {
  static {
    __name(this, "default");
  }
  /** Island container, null when frozen. */
  container;
  /** Field name posted. */
  fieldname;
  /** Field id for the label. */
  fieldid;
  /** Required flag of the server rendered input. */
  required;
  /** Placeholder of the server rendered input. */
  placeholder;
  /** Width class of the server rendered input. */
  widthclass;
  /** Current selection, kept across remounts. */
  selected = null;
  /** Unmount function of the island. */
  unmountPicker = null;
  /** Disabled state the island was mounted with. */
  pickerDisabled = null;
  /** Localised strings, loaded once. */
  strings = null;
  /**
   * Read the server rendered state and mount the island.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @param form the form API
   */
  constructor(wrapper, form) {
    super(wrapper, form);
    this.container = wrapper.querySelector("[data-muform-autocomplete]");
    const input = this.container?.querySelector('input[type="text"]') ?? null;
    this.fieldname = input?.name ?? "";
    this.fieldid = input?.id ?? "";
    this.required = input?.required ?? false;
    this.placeholder = input?.placeholder ?? "";
    this.widthclass = Array.from(input?.classList ?? []).find((name) => name.startsWith("muform-width-")) ?? "";
    if (!this.container || !input) {
      this.container = null;
      return;
    }
    try {
      const selected = JSON.parse(this.container.dataset.muformAutocompleteSelected ?? "[]");
      this.selected = selected[0] ?? null;
    } catch {
      this.selected = null;
    }
    void this.mountPicker();
  }
  /**
   * The island owns its inputs.
   *
   * @returns nothing
   */
  controls() {
    return [];
  }
  /**
   * Selected value.
   *
   * @returns the value or null
   */
  getValue() {
    if (!this.container) {
      return null;
    }
    return this.selected?.value ?? null;
  }
  /**
   * Required check, the server validates the value itself.
   *
   * @returns error messages, empty when valid
   */
  validate() {
    if (this.state.hidden || this.state.disabled || !this.required) {
      return [];
    }
    return this.getValue() === null ? [this.getHint("required")] : [];
  }
  /**
   * Remount the island when the disabled state changes.
   */
  syncUI() {
    super.syncUI();
    if (this.strings && this.pickerDisabled !== this.state.disabled) {
      this.renderPicker();
    }
  }
  /**
   * Unmount the island.
   */
  destroy() {
    this.unmountPicker?.();
    this.unmountPicker = null;
  }
  /**
   * Focus the combobox.
   */
  focus() {
    this.container?.querySelector('input[role="combobox"]')?.focus();
  }
  /**
   * Load strings, drop the fallback markup and mount.
   */
  async mountPicker() {
    const [noresults, searching, toomanyresults, clearselection, close] = await Promise.all([
      getString("muform_noresults", "tool_mulib"),
      getString("muform_searching", "tool_mulib"),
      getString("muform_toomanyresults", "tool_mulib"),
      getString("muform_clearselection", "tool_mulib"),
      getString("closebuttontitle", "core")
    ]);
    this.strings = { noresults, searching, toomanyresults, clearselection, close };
    this.container?.replaceChildren();
    this.renderPicker();
  }
  /**
   * Mount or remount the island with the current state.
   */
  renderPicker() {
    const container = this.container;
    if (!container || !this.strings) {
      return;
    }
    let source = { "class": "", args: [] };
    try {
      source = JSON.parse(container.dataset.muformAutocompleteSource ?? "{}");
    } catch {
    }
    this.unmountPicker?.();
    this.pickerDisabled = this.state.disabled;
    this.unmountPicker = mountReactApp(container, Picker, {
      url: container.dataset.muformAutocompleteUrl ?? "",
      source,
      initial: this.selected,
      name: this.fieldname,
      id: this.fieldid,
      placeholder: this.placeholder,
      widthclass: this.widthclass,
      disabled: this.state.disabled,
      strings: this.strings,
      onChange: /* @__PURE__ */ __name((value) => {
        if (value === null) {
          this.selected = null;
        } else if (this.selected?.value !== value) {
          this.selected = { value, label: value, error: null };
        }
        if (this.state.errors.length) {
          this.state.errors = [];
          this.syncUI();
        }
        this.emitChange();
      }, "onChange")
    }, { id: `muform-autocomplete-${this.name}` });
  }
}
export {
  autocomplete_default as default
};
//# sourceMappingURL=autocomplete.dev.js.map
