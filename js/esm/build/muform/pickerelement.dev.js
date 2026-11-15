var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Browser side of picker and pickermany elements.
 *
 * Selected values are pills with a remove button, the button after them opens the panel of the picker family
 * (see pickerpanel). The panel module is loaded on first use, it returns picked values with their labels.
 * The server validates the submitted values.
 *
 * @module     tool_mulib/muform/pickerelement
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { mountReactApp } from "@moodle/lms/core/mount";
import NativeElement from "./native";
import { labelText, openPanel } from "./pickerpanel";
let loadModule = /* @__PURE__ */ __name((specifier) => import(specifier), "loadModule");
function setPanelLoader(loader) {
  loadModule = loader;
}
__name(setPanelLoader, "setPanelLoader");
class PickerElement extends NativeElement {
  static {
    __name(this, "PickerElement");
  }
  /** Hidden input with the submitted value. */
  input;
  /** Container of pills and the button. */
  container;
  /** Panel module name. */
  panelModule = "";
  /** Source of values. */
  source = { "class": "", args: [] };
  /** Localised strings. */
  strings = null;
  /** May several values be picked? */
  multiple = false;
  /** Current selection. */
  selected = [];
  /** Open panel, null when closed. */
  panel = null;
  /** Panel is being loaded. */
  opening = false;
  /**
   * Read the server rendered data and render the pills.
   *
   * @param wrapper outer element with data-muform-* attributes
   * @param form the form API
   */
  constructor(wrapper, form) {
    super(wrapper, form);
    this.container = wrapper.querySelector("[data-picker-source]");
    this.input = wrapper.querySelector(`input[type="hidden"][name="${this.name}"]`);
    if (!this.container || !this.input) {
      return;
    }
    const data = this.container.dataset;
    try {
      this.source = JSON.parse(data.pickerSource ?? "");
      this.selected = JSON.parse(data.pickerSelected ?? "[]");
      this.strings = JSON.parse(data.pickerStrings ?? "{}");
    } catch {
      return;
    }
    this.panelModule = data.pickerPanel ?? "";
    this.multiple = data.pickerMultiple === "1";
    this.render();
  }
  /**
   * Selected values.
   *
   * @returns list of values when multiple, otherwise one value or null
   */
  getValue() {
    const values = this.selected.map((item) => item.value);
    if (this.multiple) {
      return values;
    }
    return values[0] ?? null;
  }
  /**
   * The server validates the values.
   *
   * @returns no errors
   */
  validate() {
    return [];
  }
  /**
   * Focus the button opening the panel.
   */
  focus() {
    this.container?.querySelector("[data-picker-open]")?.focus();
  }
  /**
   * Close the panel when the form goes away.
   */
  destroy() {
    this.panel?.close();
  }
  /**
   * Render pills and the button again.
   */
  render() {
    const { container, strings } = this;
    if (!container || !strings) {
      return;
    }
    const nodes = this.selected.map((item) => {
      const pill = document.createElement("span");
      pill.className = "badge d-inline-flex align-items-center gap-1 fs-6 text-wrap text-start " + (item.error ? "text-bg-danger" : "bg-secondary text-dark fw-normal");
      pill.dataset.pickerPill = item.value;
      if (item.error) {
        pill.title = item.error;
      }
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = item.error ? "btn-close btn-close-white" : "btn-close";
      remove.style.fontSize = ".6em";
      remove.setAttribute("aria-label", strings.pickerRemove + ": " + labelText(item.label));
      remove.title = strings.pickerRemove;
      remove.addEventListener("click", () => {
        this.selected = this.selected.filter((candidate) => candidate !== item);
        this.changed();
        this.focus();
      });
      const label = document.createElement("span");
      label.innerHTML = item.label;
      pill.append(remove, label);
      return pill;
    });
    const open = document.createElement("button");
    open.type = "button";
    open.className = "btn btn-sm btn-outline-secondary";
    open.dataset.pickerOpen = "1";
    if (!this.selected.length) {
      open.textContent = "+ " + strings.pickerSelect;
    } else {
      open.textContent = this.multiple ? "+ " + strings.pickerAdd : strings.pickerChange;
    }
    open.addEventListener("click", () => void this.open(open));
    nodes.push(open);
    container.replaceChildren(...nodes);
    this.syncUI();
  }
  /**
   * Store the selection in the hidden input and notify the form.
   */
  changed() {
    if (this.input) {
      this.input.value = this.selected.map((item) => item.value).join(",");
    }
    this.render();
    this.form.markDirty();
    this.onInput();
  }
  /**
   * Open the panel.
   *
   * @param trigger button that gets focus back
   */
  async open(trigger) {
    const strings = this.strings;
    if (this.panel || this.opening || !strings || !this.panelModule) {
      return;
    }
    this.opening = true;
    let Panel;
    try {
      const module = await loadModule(`@moodle/lms/${this.panelModule}`);
      Panel = module.default;
    } finally {
      this.opening = false;
    }
    this.panel = openPanel({
      label: strings.title,
      mount: /* @__PURE__ */ __name((dialog, close) => mountReactApp(dialog, Panel, {
        source: this.source,
        multiple: this.multiple,
        selected: this.selected.map((item) => item.value),
        strings,
        onPick: /* @__PURE__ */ __name((items) => {
          const picked = items.map((item) => ({ value: item.value, label: item.label, error: null }));
          close();
          if (!picked.length) {
            return;
          }
          this.selected = this.multiple ? [...this.selected, ...picked] : picked.slice(0, 1);
          this.changed();
        }, "onPick"),
        onClose: close
      }, { id: `tool_mulib-picker-${this.name}` }), "mount"),
      onClosed: /* @__PURE__ */ __name(() => {
        this.panel = null;
        if (trigger.isConnected) {
          trigger.focus();
        } else {
          this.focus();
        }
      }, "onClosed")
    });
  }
}
export {
  PickerElement as default,
  setPanelLoader
};
//# sourceMappingURL=pickerelement.dev.js.map
