import r from"@moodle/lms/core/fetch";import a from"@moodle/lms/core/pending";/**
 * Client of the datetime normalisation endpoint, the browser never interprets dates itself.
 *
 * The input carries the timezone, language and default time it was rendered with
 * as data attributes, the endpoint answers with the three representations of the date.
 *
 * @module     tool_mulib/muform/datetimeapi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */async function l(e,t){const o=new a("tool_mulib/muform:datetime");try{const m={text:t,timezone:e.dataset.muformDatetimeTimezone??"",lang:e.dataset.muformDatetimeLang??"",defaulthour:Number(e.dataset.muformDatetimeDefaulthour)||0,defaultminute:Number(e.dataset.muformDatetimeDefaultminute)||0},n=await(await r.performPost("tool_mulib","muform/datetime",{body:m})).json();return n.valid&&(e.dataset.muformDatetimeTimestamp=n.timestamp===null?"":String(n.timestamp),e.dataset.muformDatetimeComponents=n.components?JSON.stringify(n.components):""),n}finally{o.resolve()}}function f(e){const t=e.dataset.muformDatetimeComponents;if(!t)return null;try{return JSON.parse(t)}catch{return null}}function d(e){const t=o=>String(o).padStart(2,"0");return`${e.year}-${t(e.month)}-${t(e.day)} ${t(e.hour)}:${t(e.minute)}:${t(e.second)}`}export{d as formatComponents,l as normalise,f as readComponents};
