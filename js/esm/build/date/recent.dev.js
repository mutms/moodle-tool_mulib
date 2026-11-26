var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Recently used dates of the browser tab, latest first, offered in the date panel.
 *
 * @module     tool_mulib/date/recent
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const RECENT_LIMIT = 10;
const KEY_SINGLE = "tool_mulib-date-recent";
const KEY_RANGE = "tool_mulib-date-recentrange";
const partsKey = /* @__PURE__ */ __name((parts) => [parts.year, parts.month, parts.day, parts.hour, parts.minute].join("-"), "partsKey");
const entryKey = /* @__PURE__ */ __name((entry) => partsKey(entry.start) + (entry.end ? "/" + partsKey(entry.end) : ""), "entryKey");
const read = /* @__PURE__ */ __name((key) => {
  try {
    const list = JSON.parse(window.sessionStorage.getItem(key) ?? "[]");
    return Array.isArray(list) ? list.filter((entry) => entry?.text && entry.start) : [];
  } catch {
    return [];
  }
}, "read");
const remember = /* @__PURE__ */ __name((key, entry) => {
  const id = entryKey(entry);
  const list = [entry, ...read(key).filter((item) => entryKey(item) !== id)].slice(0, RECENT_LIMIT);
  try {
    window.sessionStorage.setItem(key, JSON.stringify(list));
  } catch {
  }
}, "remember");
function rememberDate(text, parts) {
  if (text !== "" && parts) {
    remember(KEY_SINGLE, { text, start: parts });
  }
}
__name(rememberDate, "rememberDate");
function rememberRange(text, start, end) {
  if (text !== "" && start && end) {
    remember(KEY_RANGE, { text, start, end });
  }
}
__name(rememberRange, "rememberRange");
function recentDates(range = false) {
  return read(range ? KEY_RANGE : KEY_SINGLE).filter((entry) => !range || entry.end);
}
__name(recentDates, "recentDates");
function isRecent(entry, start, end) {
  return partsKey(entry.start) === partsKey(start) && (!entry.end || !end || partsKey(entry.end) === partsKey(end));
}
__name(isRecent, "isRecent");
export {
  RECENT_LIMIT,
  isRecent,
  recentDates,
  rememberDate,
  rememberRange
};
//# sourceMappingURL=recent.dev.js.map
