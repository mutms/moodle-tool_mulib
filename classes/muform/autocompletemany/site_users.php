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

namespace tool_mulib\muform\autocompletemany;

use core\context;
use tool_mulib\muform\util\autocomplete\user_trait;

/**
 * Any site users, for those who may view all user details in the given context.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_users extends base {
    use user_trait;

    /** @var context */
    private context $context;

    /**
     * Constructor.
     *
     * @param int $contextid
     */
    public function __construct(int $contextid) {
        $this->context = context::instance_by_id($contextid);
        require_capability('moodle/user:viewalldetails', $this->context);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->context->id];
    }

    #[\Override]
    public function search(string $query, int $maxitems, array $exclude): ?array {
        return $this->search_users($this->context, $query, $maxitems, $exclude);
    }

    #[\Override]
    public function labels(array $values): array {
        return $this->user_labels($this->context, $values);
    }

    #[\Override]
    public function validate(array $values): array {
        return $this->validate_users($values);
    }
}
