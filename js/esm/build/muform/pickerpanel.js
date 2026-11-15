/**
 * Shell of picker panels: a native modal dialog over the right half of the screen, the page stays visible on the left.
 *
 * It is not a muform dialog, so it also works over forms shown in muform dialogs: modal dialogs are in the
 * top layer and only the top one closes with Escape. Families of pickers render their panel component into it,
 * see tool_mulib\muform\picker\base.
 *
 * @module     tool_mulib/muform/pickerpanel
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const r="tool_mulib-picker-panel",l=t=>{const e=document.createElement("dialog");e.className=r,e.setAttribute("aria-label",t.label),document.body.append(e);let n=null;return e.addEventListener("close",()=>{n?.(),e.remove(),t.onClosed?.()}),e.addEventListener("mousedown",o=>{o.target===e&&e.close()}),n=t.mount(e,()=>e.close()),e.showModal(),e},i=t=>{const e=document.createElement("div");return e.innerHTML=t,(e.textContent??"").replace(/\s+/g," ").trim()};export{r as PANEL_CLASS,i as labelText,l as openPanel};
