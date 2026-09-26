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

namespace tool_mulib\muform\autocomplete;

use tool_mulib\muform\util\autocomplete\category_context_trait;

/**
 * Category context of an external database query: the system context or a course category
 * where the user has the site configuration capability.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extdb_query_context extends base {
    use category_context_trait;

    /** @var string capability required in the selected context */
    private const string CAPABILITY = 'moodle/site:config';

    /**
     * Constructor.
     *
     * @param int $currentcontextid context id stored in the query, it stays selectable without the capability
     */
    public function __construct(
        /** @var int context id stored in the query */
        private int $currentcontextid,
    ) {
        require_capability(self::CAPABILITY, \core\context\system::instance());
    }

    #[\Override]
    public function get_args(): array {
        return [$this->currentcontextid];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        return $this->search_category_contexts(self::CAPABILITY, $query, $maxitems);
    }

    #[\Override]
    public function label(string $value): ?string {
        return $this->category_context_label(self::CAPABILITY, $value, $this->currentcontextid);
    }
}
