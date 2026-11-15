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

namespace tool_mulib\phpunit\muform\fixtures;

use core\context;
use tool_mulib\local\sql;

/**
 * Fake users picker source: users without lastname "Hidden", no suspended users,
 * a stored user "Kept" stays valid, current users stay valid whatever they are.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fake_user_source extends \tool_mulib\muform\userpicker\base {
    /** @var int[] current users of the edited item */
    public array $currentuserids = [];

    #[\Override]
    public function get_args(): array {
        return [];
    }

    #[\Override]
    protected function get_context(): context {
        return \core\context\system::instance();
    }

    #[\Override]
    protected function get_candidates_sql(): ?sql {
        return new sql("u.lastname <> 'Hidden' AND u.lastname <> 'Kept'");
    }

    #[\Override]
    protected function get_selected_sql(): ?sql {
        return new sql("u.lastname <> 'Hidden'");
    }

    #[\Override]
    protected function get_current_userids(): array {
        return $this->currentuserids;
    }

    #[\Override]
    protected function allow_suspended_users(): bool {
        return false;
    }
}
