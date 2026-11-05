import m from"@moodle/lms/core/fetch";import s from"@moodle/lms/core/pending";/**
 * Client of the datetime range normalisation endpoint, the browser never interprets dates itself.
 *
 * The input carries the timezone, language, default time and default duration it was rendered
 * with as data attributes, the endpoint answers with both timestamps, the text and a hint.
 *
 * @module     tool_mulib/muform/datetimerangeapi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */async function d(t,n){const a=new s("tool_mulib/muform:datetimerange");try{const o={text:n,timezone:t.dataset.muformDatetimeTimezone??"",lang:t.dataset.muformDatetimeLang??"",defaulthour:Number(t.dataset.muformDatetimeDefaulthour)||0,defaultminute:Number(t.dataset.muformDatetimeDefaultminute)||0},r=Number(t.dataset.muformDatetimerangeDefaultduration);r>0&&(o.defaultduration=r);const e=await(await m.performPost("tool_mulib","muform/datetimerange",{body:o})).json();return e.valid&&(t.dataset.muformDatetimerangeStart=e.start===null?"":String(e.start),t.dataset.muformDatetimerangeEnd=e.end===null?"":String(e.end),t.dataset.muformDatetimeComponents=e.startcomponents?JSON.stringify(e.startcomponents):"",t.dataset.muformDatetimerangeEndcomponents=e.endcomponents?JSON.stringify(e.endcomponents):""),e}finally{a.resolve()}}function f(t){const n=t.dataset.muformDatetimerangeEndcomponents;if(!n)return null;try{return JSON.parse(n)}catch{return null}}export{d as normaliseRange,f as readEndComponents};
