# Change log

Plugin versioning is derived from Moodle releases, it does not comply with the semantic versioning standard.

The format of this change log follows the advice given at [Keep a CHANGELOG](https://keepachangelog.com).

## [Unreleased]

### Added

- sql::rename_params() for APIs that accept only their own parameter names, for example report builder base conditions
- mulib::is_mutms_plugin() and mulib::MUTMS_PLUGINS list of all MuTMS plugin components
- reportbuilder_util::is_downloading() and reportbuilder_util::format_badge() helpers for report builder reports
- import_util for parsing of uploaded or pasted CSV data with automatic delimiter detection

### Changed

- remove mulib::clean_string(), standard clean_string() is used instead
- new forms library
- new integration of vendor/ plugin dependencies

### Fixed

- entity_details replaces empty values with a non-breaking space to keep the row height
- notifications table uses the same striped, hover and bordered styling as other management tables

