<?php
// This file is part of Additional tools library for Moodle™.
// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon

/**
 * Environment tests.
 *
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Prevent Oracle Database usage!
 *
 * @param environment_results $result
 * @return environment_results|null
 */
function tool_mulib_oracle_incompatible(environment_results $result): ?environment_results {
    global $DB;

    if ($DB->get_dbfamily() === 'oracle') {
        $result->setStatus(false);
        return $result;
    }

    return null;
}

/**
 * No official support for MS SQL Server because MS gave up on supporting PHP
 * drivers for their database.
 *
 * @param environment_results $result
 * @return environment_results|null
 */
function tool_mulib_mssql_unsupported(environment_results $result): ?environment_results {
    global $DB;

    if ($DB->get_dbfamily() === 'mssql') {
        $result->setStatus(false);
        return $result;
    }

    return null;
}

/**
 * muTMS is not tested with Oracle MySQL databases.
 *
 * @param environment_results $result
 * @return environment_results|null
 */
function tool_mulib_mysql_unsupported(environment_results $result): ?environment_results {
    global $DB;

    if ($DB->get_dbfamily() === 'mysql') {
        $result->setStatus(false);
        return $result;
    }

    return null;
}

/**
 * No official support for MS Windows because they stopped supporting PHP and their drivers.
 *
 * @param environment_results $result
 * @return environment_results|null
 */
function tool_mulib_windows_unsupported(environment_results $result): ?environment_results {
    if (DIRECTORY_SEPARATOR === '\\') {
        $result->setStatus(false);
        return $result;
    }

    return null;
}
