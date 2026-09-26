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

namespace tool_mulib\muform\tagarea;

use core\context;
use core_tag_area;
use core_tag_tag;

/**
 * Tag area of a tags element.
 *
 * An area owns the access control: the constructor takes scalar arguments only and checks
 * capabilities, because the same class is instantiated in the form definition and again in
 * the tool_mulib tags endpoint from get_args(). Subclasses live in <component>\muform\tagarea
 * namespace, the endpoint refuses anything else. The context never changes while a form is used,
 * moving an item to another context is a separate action.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Constructor arguments as a list of scalars, used to reinstantiate the area in the endpoint.
     *
     * @return array
     */
    abstract public function get_args(): array;

    /**
     * Component of the core tag area.
     *
     * @return string
     */
    abstract public function get_component(): string;

    /**
     * Item type of the core tag area.
     *
     * @return string
     */
    abstract public function get_itemtype(): string;

    /**
     * Context of tag instances.
     *
     * @return context
     */
    abstract public function get_context(): context;

    /**
     * Id of the tagged item, null for new items.
     *
     * @return int|null
     */
    abstract public function get_itemid(): ?int;

    /**
     * Is tagging enabled for this area?
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool)core_tag_tag::is_enabled($this->get_component(), $this->get_itemtype());
    }

    /**
     * Standard tags mode of the area.
     *
     * @return int one of core_tag_tag::BOTH_STANDARD_AND_NOT, STANDARD_ONLY or HIDE_STANDARD
     */
    public function get_showstandard(): int {
        return (int)core_tag_area::get_showstandard($this->get_component(), $this->get_itemtype());
    }

    /**
     * Tag collection of the area.
     *
     * @return int
     */
    public function get_collection(): int {
        return (int)core_tag_area::get_collection($this->get_component(), $this->get_itemtype());
    }

    /**
     * Current tag names of the item.
     *
     * @return string[]
     */
    public function get_tags(): array {
        $itemid = $this->get_itemid();
        if (!$itemid || !$this->is_enabled()) {
            return [];
        }
        return array_values(core_tag_tag::get_item_tags_array($this->get_component(), $this->get_itemtype(), $itemid));
    }

    /**
     * Standard tags of the area collection matching the names, compared case insensitively.
     *
     * @param string[] $names
     * @return string[] lowercase names of standard tags
     */
    public function get_standard(array $names): array {
        global $DB;

        $names = array_map('core_text::strtolower', $names);
        if (!$names) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED);
        $params['tagcollid'] = $this->get_collection();
        return array_values($DB->get_fieldset_select(
            'tag',
            'name',
            "tagcollid = :tagcollid AND isstandard = 1 AND name $insql",
            $params
        ));
    }

    /**
     * Suggest standard tags, nothing is suggested when standard tags are hidden in the area.
     *
     * @param string $query
     * @param int $maxitems
     * @param string[] $exclude names already entered
     * @return string[]|null tag names, null when more than $maxitems match
     */
    public function search(string $query, int $maxitems, array $exclude): ?array {
        global $DB;

        if (!$this->is_enabled() || $this->get_showstandard() == core_tag_tag::HIDE_STANDARD) {
            return [];
        }
        $params = ['tagcollid' => $this->get_collection()];
        $select = "tagcollid = :tagcollid AND isstandard = 1";
        $query = trim($query);
        if ($query !== '') {
            $select .= ' AND ' . $DB->sql_like('name', ':query', false);
            $params['query'] = '%' . $DB->sql_like_escape(\core_text::strtolower($query)) . '%';
        }
        $exclude = array_map('core_text::strtolower', $exclude);
        if ($exclude) {
            [$notin, $excludeparams] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'ex', false);
            $select .= " AND name $notin";
            $params += $excludeparams;
        }
        $names = $DB->get_fieldset_select('tag', 'rawname', $select . ' ORDER BY name', $params, 0, $maxitems + 1);
        if (count($names) > $maxitems) {
            return null;
        }
        return array_values($names);
    }

    /**
     * Maximum number of suggestions.
     *
     * @return int
     */
    public function get_maxitems(): int {
        return 50;
    }

    /**
     * Store tags of an item, does nothing when tagging is disabled.
     *
     * @param int $itemid
     * @param string[] $names
     */
    public function save(int $itemid, array $names): void {
        if (!$this->is_enabled()) {
            return;
        }
        core_tag_tag::set_item_tags($this->get_component(), $this->get_itemtype(), $itemid, $this->get_context(), $names);
    }
}
