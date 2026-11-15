var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Shell of picker panels: a native modal dialog over the right half of the screen, the page stays visible on the left.
 *
 * It is not a muform dialog, so it also works over forms shown in muform dialogs: modal dialogs are in the
 * top layer and only the top one closes with Escape. Families of pickers render their panel component into it,
 * see tool_mulib\muform\picker\base.
 *
 * @module     tool_mulib/muform/pickerpanel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const PANEL_CLASS = "tool_mulib-picker-panel";
const openPanel = /* @__PURE__ */ __name((options) => {
  const dialog = document.createElement("dialog");
  dialog.className = PANEL_CLASS;
  dialog.setAttribute("aria-label", options.label);
  document.body.append(dialog);
  let unmount = null;
  dialog.addEventListener("close", () => {
    unmount?.();
    dialog.remove();
    options.onClosed?.();
  });
  dialog.addEventListener("mousedown", (event) => {
    if (event.target === dialog) {
      dialog.close();
    }
  });
  unmount = options.mount(dialog, () => dialog.close());
  dialog.showModal();
  return dialog;
}, "openPanel");
const labelText = /* @__PURE__ */ __name((html) => {
  const div = document.createElement("div");
  div.innerHTML = html;
  return (div.textContent ?? "").replace(/\s+/g, " ").trim();
}, "labelText");
export {
  PANEL_CLASS,
  labelText,
  openPanel
};
//# sourceMappingURL=pickerpanel.dev.js.map
