<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon

namespace tool_mulib\local;

use stdClass;

/**
 * User account helpers.
 *
 * Capabilities are not checked here, callers decide which capability applies in which context.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_util {
    /**
     * Is the account protected from status changes: deleted, guest, remote, site admin or current user?
     *
     * @param stdClass $user with id, deleted, mnethostid
     * @return bool
     */
    private static function is_protected_account(stdClass $user): bool {
        global $CFG, $USER;
        if ($user->deleted || isguestuser($user) || (int)$user->mnethostid !== (int)$CFG->mnet_localhost_id) {
            return true;
        }
        return (int)$user->id === (int)$USER->id || is_siteadmin($user);
    }

    /**
     * Is the account in a state that allows suspending?
     *
     * @param stdClass $user with id, deleted, mnethostid, suspended
     * @return bool
     */
    public static function is_suspendable(stdClass $user): bool {
        return !$user->suspended && !self::is_protected_account($user);
    }

    /**
     * Suspend account and terminate its sessions.
     *
     * @param stdClass $user
     */
    public static function suspend(stdClass $user): void {
        if (!self::is_suspendable($user)) {
            throw new \core\exception\coding_exception('Account cannot be suspended');
        }
        $record = (object)['id' => $user->id, 'suspended' => 1];
        \core\session\manager::destroy_user_sessions($user->id);
        \core\user::update_user($record, false);
    }

    /**
     * Is the account in a state that allows unsuspending?
     *
     * @param stdClass $user with id, deleted, mnethostid, suspended
     * @return bool
     */
    public static function is_unsuspendable(stdClass $user): bool {
        return $user->suspended && !self::is_protected_account($user);
    }

    /**
     * Unsuspend account.
     *
     * @param stdClass $user
     */
    public static function unsuspend(stdClass $user): void {
        if (!self::is_unsuspendable($user)) {
            throw new \core\exception\coding_exception('Account cannot be unsuspended');
        }
        $record = (object)['id' => $user->id, 'suspended' => 0];
        \core\user::update_user($record, false);
    }
}
