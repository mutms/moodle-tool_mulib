import r from"../element";/**
 * Browser side of the collapsible section element, a details element with children of their own.
 *
 * Expanded is the default, a state collapsed by the user is kept in session storage under the state key
 * of the section. Sections rendered with errors stay expanded, the form expands sections with errors
 * after a failed submission.
 *
 * @module     tool_mulib/muform/element/collapsible
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s="tool_mulib-collapsed-";class a extends r{constructor(e,t){super(e,t),e instanceof HTMLDetailsElement&&(e.dataset.muformCollapsibleErrors!=="1"&&this.isStoredCollapsed()&&(e.open=!1),e.querySelector(":scope > summary")?.addEventListener("click",o=>{o.defaultPrevented||this.store(e.open)}),e.addEventListener("muform:collapsible",o=>{const n=o.detail.open;e.open=n,this.store(!n)}))}isStoredCollapsed(){const e=this.wrapper.dataset.muformStatekey;if(!e)return!1;try{return window.sessionStorage.getItem(s+e)==="1"}catch{return!1}}store(e){const t=this.wrapper.dataset.muformStatekey;if(t)try{e?window.sessionStorage.setItem(s+t,"1"):window.sessionStorage.removeItem(s+t)}catch{}}}export{a as default};
