# Profile field cache

One cache of custom profile field values for all MuTMS plugins, used for fast filtering, sorting and
searching (cohort rules now, reports later). Read this before using custom profile fields in SQL.

## Values

- `tool_mulib_profilefield`: one row per non-deleted user and cached field (unique `userid, fieldid`,
  indexes `fieldid, charvalue` and `fieldid, intvalue`).
- The value is the effective one: stored data, or the current field default for users without data.
- Each type fills exactly one column, the other one is NULL:
  - text and menu: `charvalue`, trimmed and cut to 255 characters;
  - checkbox: `intvalue`, 1 or 0;
  - datetime: `intvalue`, timestamp, 0 means not set.
- Field visibility is ignored, consumers apply their own visibility rules.
- The cache can always be rebuilt from core tables, it is never the source of truth.

## Which fields are cached

- Opt-out: every supported field is cached, admins may stop caching of fields nobody uses.
- `tool_mulib/profilefields` config: JSON fieldid => time populated, 0 = being prepared. A field missing
  from the list is not cached.
- Required fields cannot be removed from cache: fields with references plus fields shown as user identity
  (`showuseridentity`, recorded as references `core` / `showuseridentity` / 0).
- Supported types come from handlers `local\profilefield\{text,menu,checkbox,datetime}` registered via
  hook `hook\profilefield_handlers`. A handler returns its column (`get_cache_column()`) and the SQL
  expression of the value (`get_cache_sql()`, never NULL).

## Using the cache

- Read values only from `{tool_mulib_profilefield}`, never filter on `user_info_data`.
- Use only fields that are ready (`profilefield_cache::is_ready()`). Fail safe when a field is missing,
  not supported or not ready: wait, skip or report, never use partial data.
- Record persistent usage with `set_references(component, area, itemid, fieldids)`, it replaces the set
  of one item, an empty list clears it. Referenced fields are added to the cache automatically.
  Example: tool_mucohort rules use `tool_mucohort` / `rules` / mucohortid.
- Clear references when the item is deleted. Core field deletion removes values and references.
- Call `update_user(userid)` before decisions that must see the current value of one user, such as
  instant cohort sync on login.

## Keeping values current

- Observers: `user_created` and `user_updated` update the user, `user_deleted` removes the rows.
- Profile field created or updated: population is queued as adhoc task, current values stay readable
  meanwhile (core fires the update event also when fields are reordered, duplicate tasks are not queued).
  Field deleted: values and references are removed.
- `task\profilefield_cron` (hourly):
  1. deletes references to nonexistent fields and syncs `showuseridentity` references;
  2. adds required fields that are not cached;
  3. populates fields that are being prepared and refreshes fields older than `profilefieldlifetime`
     (default one day), this also catches profile data saved without events;
  4. deletes values of fields that are not cached.
- Synchronisation changes rows in place without transactions and writes only changed values
  (separate UPDATE for pgsql and MySQL/MariaDB), readers always see some value.

## Adhoc tasks and inline population

- Only admin UI actions queue adhoc tasks (profile field created or updated, cache action), so the UI
  is not sluggish.
- Install, upgrade and CLI populate inline. The upgrade step saves its savepoint first, an aborted
  population is not repeated, cron finishes the fields still being prepared.
- Other code only marks fields as being prepared (`add_field()`), cron populates them.

## Admin UI and CLI

- Site administration > Users > Accounts > Cached profile fields: `profilefieldlifetime` setting and
  table of all fields with type, status, usage and actions (cache, stop caching; stop caching is not
  offered for required fields).
- `cli/profilefield_cache.php`: `--list` shows the state, `--sync [--field=SHORTNAME]` synchronises
  values now and prints the duration of each field.

## Tests

- Data generators do not trigger profile field events, fields created by them are not cached.
- PHPUnit: `profilefield_cache::add_field()` or `add_supported_fields()`, then
  `populate_pending_fields(new \null_progress_trace())`.
- Behat: `Given all custom profile fields are cached`.

## Privacy

Cached values are personal data, the privacy provider exports them in system context and deletes them;
deleted rows are rebuilt from core data by observers or cron while the user exists.
