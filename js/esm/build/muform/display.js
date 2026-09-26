/**
 * Display manager: hides and disables elements according to rules from the server.
 *
 * The logic mirrors tool_mulib\muform\util\display_manager exactly. Rules depend on
 * element values only, they are evaluated in a single pass in rule order, results of
 * rules for the same target are ORed, and the state of a section or button row cascades
 * to the elements inside it. Hiding and disabling are cosmetic, the server ignores them.
 *
 * @module     tool_mulib/muform/display
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */function c(o,e,t){switch(o){case"eq":return Array.isArray(e)?e.includes(String(t)):e===String(t);case"neq":return!c("eq",e,t);case"in":{const r=(Array.isArray(t)?t:[t]).map(String);return Array.isArray(e)?e.some(n=>r.includes(n)):e!==null&&r.includes(e)}case"notin":return!c("in",e,t);case"checked":return e==="1";case"notchecked":return e!=="1";case"empty":return e===null||e===""||Array.isArray(e)&&e.length===0;case"notempty":return!c("empty",e,null);default:throw new Error(`Invalid display rule operator: ${o}`)}}class d{elements;rules;dependencies;constructor(e,t){this.elements=e,this.rules=t,this.dependencies=new Set(t.map(r=>r.dep))}dependsOn(e){return this.dependencies.has(e)}apply(){const e=new Set,t=new Set;for(const n of this.rules){const s=this.elements.get(n.dep);!s||!this.elements.has(n.target)||c(n.op,s.getValue(),n.value)&&(n.action==="hide"?e:t).add(n.target)}const r=[];for(const[n,s]of this.elements){let a=!1,l=!1,i=n;for(;i!==void 0;)a=a||e.has(i),l=l||t.has(i),i=this.parentOf(i);(s.state.hidden!==a||s.state.disabled!==l)&&(s.state.hidden=a,s.state.disabled=l,s.syncUI(),r.push(n))}return r}parentOf(e){return this.elements.get(e)?.wrapper.parentElement?.closest("[data-muform-element]")?.dataset.muformName}}export{d as default,c as matches};
