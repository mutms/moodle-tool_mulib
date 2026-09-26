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

namespace tool_mulib\muform\util;

use core\context;
use core\exception\coding_exception;

/**
 * Permanent file area a muform element loads files from and saves files to.
 *
 * Context and item id may be unknown while a record is being created,
 * set them once the record exists and call the element's save_area().
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_area {
    /** @var string component owning the area */
    private string $component;
    /** @var string file area name */
    private string $filearea;
    /** @var int|null context id, null until known */
    private ?int $contextid;
    /** @var int|null item id, null until known */
    private ?int $itemid;

    /**
     * Constructor.
     *
     * @param int|context|null $contextid
     * @param string $component
     * @param string $filearea
     * @param int|null $itemid
     */
    public function __construct(int|context|null $contextid, string $component, string $filearea, ?int $itemid) {
        if ($component === '' || clean_param($component, PARAM_COMPONENT) !== $component) {
            throw new coding_exception('Invalid component');
        }
        if ($filearea === '' || clean_param($filearea, PARAM_AREA) !== $filearea) {
            throw new coding_exception('Invalid filearea');
        }
        $this->component = $component;
        $this->filearea = $filearea;
        $this->contextid = ($contextid instanceof context) ? $contextid->id : $contextid;
        $this->itemid = $itemid;
    }

    /**
     * Set context once known.
     *
     * @param int|context $contextid
     * @return $this
     */
    public function set_contextid(int|context $contextid): static {
        $this->contextid = ($contextid instanceof context) ? $contextid->id : $contextid;
        return $this;
    }

    /**
     * Set item id once known, usually after the record was inserted.
     *
     * @param int $itemid
     * @return $this
     */
    public function set_itemid(int $itemid): static {
        $this->itemid = $itemid;
        return $this;
    }

    /**
     * Returns component owning the area.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Returns file area name.
     *
     * @return string
     */
    public function get_filearea(): string {
        return $this->filearea;
    }

    /**
     * Returns context id.
     *
     * @return int|null
     */
    public function get_contextid(): ?int {
        return $this->contextid;
    }

    /**
     * Returns item id.
     *
     * @return int|null
     */
    public function get_itemid(): ?int {
        return $this->itemid;
    }

    /**
     * Is the file area complete and does its context exist?
     *
     * @return bool
     */
    public function is_valid(): bool {
        if ($this->contextid === null || $this->itemid === null) {
            return false;
        }
        return (bool)context::instance_by_id($this->contextid, IGNORE_MISSING);
    }
}
