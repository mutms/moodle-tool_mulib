# Additional MuTMS libraries plugin for Moodle™ LMS

[![MDL Shield](https://img.shields.io/endpoint?url=https%3A%2F%2Fmdlshield.com%2Fapi%2Fbadge%2Ftool_mulib)](https://mdlshield.com/plugins/tool_mulib) ![Moodle Plugin CI](https://github.com/mutms/moodle-tool_mulib/actions/workflows/moodle-ci.yml/badge.svg) [![camp](https://camp-registry.org/badge/tool_mulib.svg)](https://camp-registry.org/plugin/tool_mulib.html)

Shared library required by all MuTMS plugins — fully open source under GPL 3.0, with no
restrictions on commercial use. Part of the [MuTMS suite](https://github.com/mutms).

## Requirements

* Supported databases: PostgreSQL, MariaDB, and MySQL
* MS SQL Server is not compatible
* PHP for Windows is not supported — use a Linux VM (WSL or Hyper-V) if necessary

## For developers

Shared APIs used by all MuTMS plugins:

* **SQL based capability checks** – context map cache for fast capability filtering directly in SQL
  queries on system, tenant, category and course levels, instead of checking records one by one in PHP
* **[muform](docs/muform.md)** – forms library replacing moodleform: server-side validation and rendering,
  native dialogs, autocomplete, tags, custom fields, wizards and Behat steps
* **SQL helpers** – immutable SQL fragments with parameters, search queries for autocompletes and
  concurrent-safe upserts
* **External databases** – server connections and query types for synchronisation from other systems
* **Notifications** – configurable notifications with placeholders and delivery tracking
* **Page output** – header action buttons and menus, entity details, copy-to-clipboard URLs
* **Utilities** – dates, roles, custom fields, JSON schema validation and Composer vendor loading

An overview for developers and AI agents is in [AGENTS.md](AGENTS.md).

## AI disclosure

Parts of this plugin were written with the help of Claude (Anthropic). A human
maintainer reviewed, corrected and accepted everything before it was committed.
The design decisions and the final code are the maintainer's own.

---

> This plugin is a fork of [Open LMS local util plugin](https://github.com/open-lms-open-source/moodle-local_openlms),
> released under GPL 3.0. MuTMS is an independent open-source project, not affiliated with Moodle HQ or Open LMS.
