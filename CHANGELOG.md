# Change log

Plugin versioning is derived from Moodle releases, it does not comply with the semantic versioning standard.

The format of this change log follows the advice given at [Keep a CHANGELOG](https://keepachangelog.com).

## [Unreleased]

### Added

- Czech translation and AI-produced previews of German, French, Italian, Portuguese and Spanish translations
- sql::rename_params() for APIs that accept only their own parameter names, for example report builder base conditions
- sql::for_db() selecting MariaDB/MySQL or PostgreSQL (all other databases) variant of SQL fragment, optionally for an external database
- mulib::is_mutms_plugin() and mulib::MUTMS_PLUGINS list of all MuTMS plugin components
- Cache of custom profile field values with references of persistent usage, moved from tool_mucohort
- reportbuilder_util::is_downloading() and reportbuilder_util::format_badge() helpers for report builder reports
- import_util for parsing of uploaded or pasted CSV data with automatic delimiter detection
- mulib::is_mucohort_available() and cohort_util with links to cohort management
- mulib::uuid7() generator of time ordered UUIDs
- mulib::json_encode() for consistent encoding of stored JSON data
- course_trait with course search helpers for autocomplete sources
- mulib::TRANSLATIONS list of languages shipped by all MuTMS plugins, with a test for missing strings and docs/translations.md
- report_filter_bar, always visible filter bar of system reports with text, select, boolean and date filters
- pinned filters of report_filter_bar that stay visible with "Any" when they do not filter
- default filter values of report_filter_bar, stored for users without stored filters and after "Reset all"
- boolean_yes report builder filter that filters only when set to "Yes"
- checkboxes report builder filter matching any checked option, edited in report_filter_bar, replaces select filters without losing stored values
- report_pager, footer of system reports with the number of records, page size, download menu and compact page numbers
- expandable_text, descriptions collapsed to one line of text that expand to the full text
- picker and pickermany muform elements, pills with a family panel over the right half of the screen
- userpicker and userpickermany muform elements, users picked in a panel with identity columns and paging
- get_current_userids() hook of users picker sources for current values that stay valid whatever the candidate and tenant rules say
- picker\filter, picker\filter_set and the pickerfilters component: filters of picker panels with default values and pinned filters,
  pills like in report_filter_bar, reusable in all picker families
- filters of the users picker panel, suspended users are hidden by the default value of the pinned "Active" filter
- cohortpicker and cohortpickermany muform elements, cohorts picked in a panel with the tree of course categories
- get_restriction_sql() hook of cohort picker sources for extra conditions of cohorts that may be picked
- are_allowed() of picker sources for checking of values in form validation
- filter::autocomplete() picker filters with values searched in autocomplete sources, "Cohort" filter of the users picker panel
- cohort_util::get_view_members_sql() and cohort_util::can_view_members() for cohorts whose members the user may list,
  member_cohorts autocomplete source
- one autocomplete source base and namespace muform\autocomplete for both autocomplete elements, label_value() and validate_value() hooks
- mudb::insert_record_if_missing() for concurrent inserts into tables with unique index, safe inside transactions
- notification type hooks add_update_form_elements(), prepare_update_form_data(), normalise_aux_data() and get_aux_details()
  for type specific settings stored in notification aux fields, aux data of types without support is discarded
- dropdown::set_align_start() opening the menu to the right of the button, for buttons near the left edge of the page
- options::set_disabled() for options shown but not selectable, rendered disabled and rejected when submitted by checkboxes
- element::set_reload_on_change() reloading the form in browser when the value changes, for definitions built from submitted values
- set_min_column_width() of radiocards and checkboxcards for two columns of short cards in small dialogs too
- user_util with account status rules (suspendable, unsuspendable) and suspend() / unsuspend() helpers

### Changed

- remove mulib::clean_string(), standard clean_string() is used instead
- remove duplicate mulib::upsert_record(), use mudb::upsert_record() instead
- mudb::upsert_record_pgsql() is protected, use mudb::upsert_record()
- new forms library
- new integration of vendor/ plugin dependencies
- cohort_util::get_use_sql() and cohort_util::can_use() decide which cohorts may be selected in MuTMS plugins: visible cohorts,
  cohorts with moodle/cohort:view capability and advanced cohorts with an access record that allows using of the cohort;
  they replace search_util::is_cohort_visible()

### Fixed

- entity_details replaces empty values with a non-breaking space to keep the row height
- notifications table uses the same striped, hover and bordered styling as other management tables
