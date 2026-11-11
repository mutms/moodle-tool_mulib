/**
 * Element contract of muform.
 *
 * An element owns one wrapper element and a small cosmetic state. The orchestrator and
 * the display manager never touch the DOM inside the wrapper: they read values through
 * getValue(), write the state and call syncUI(), which projects the state onto the DOM.
 * Anything may be rendered inside the wrapper, custom elements override what they need.
 *
 * @module     tool_mulib/muform/element
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d='input:not([type="hidden"]), select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])';let r=null;document.addEventListener("mousedown",o=>{r=o.target instanceof window.Element?o.target.closest("button"):null},!0);document.addEventListener("mouseup",()=>{r=null},!0);class s{name;wrapper;state;form;constructor(e,a){this.wrapper=e,this.form=a,this.name=e.dataset.muformName??"",this.state={hidden:e.dataset.muformHidden==="1",disabled:e.dataset.muformDisabled==="1",errors:s.readErrors(e),touched:!1},e.addEventListener("focusout",n=>{this.state.touched||(this.state.touched=!0);const t=r??n.relatedTarget;t instanceof HTMLButtonElement&&t.form===e.closest("form")||this.form.touched(this.name)})}static readErrors(e){const n=Array.from(e.querySelectorAll(".invalid-feedback")).find(t=>t.closest("[data-muform-name]")===e);return n?Array.from(n.children).map(t=>(t.textContent??"").trim()).filter(t=>t!==""):[]}getValue(){return null}validate(){return[]}syncUI(){const{wrapper:e,state:a}=this;a.hidden?(e.hidden=!0,e.dataset.muformHidden="1"):(e.hidden=!1,delete e.dataset.muformHidden),a.disabled?e.dataset.muformDisabled="1":delete e.dataset.muformDisabled}focus(){this.wrapper.querySelector(d)?.focus()}destroy(){}emitChange(){const e={name:this.name,value:this.getValue()};this.wrapper.dispatchEvent(new CustomEvent("muform:change",{bubbles:!0,detail:e}))}}export{s as default};
