/**
 * Recently used dates of the browser tab, latest first, offered in the date panel.
 *
 * @module     tool_mulib/date/recent
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u=10,s="tool_mulib-date-recent",i="tool_mulib-date-recentrange",o=t=>[t.year,t.month,t.day,t.hour,t.minute].join("-"),r=t=>o(t.start)+(t.end?"/"+o(t.end):""),c=t=>{try{const e=JSON.parse(window.sessionStorage.getItem(t)??"[]");return Array.isArray(e)?e.filter(n=>n?.text&&n.start):[]}catch{return[]}},a=(t,e)=>{const n=r(e),m=[e,...c(t).filter(l=>r(l)!==n)].slice(0,10);try{window.sessionStorage.setItem(t,JSON.stringify(m))}catch{}};function p(t,e){t!==""&&e&&a(s,{text:t,start:e})}function d(t,e,n){t!==""&&e&&n&&a(i,{text:t,start:e,end:n})}function f(t=!1){return c(t?i:s).filter(e=>!t||e.end)}function g(t,e,n){return o(t.start)===o(e)&&(!t.end||!n||o(t.end)===o(n))}export{u as RECENT_LIMIT,g as isRecent,f as recentDates,p as rememberDate,d as rememberRange};
