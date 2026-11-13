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

namespace tool_mulib\muform\element;

use core_renderer;
use tool_mulib\muform\element;

/**
 * Collapsible form section (accordion item), children are rendered inside.
 *
 * Rendered as details element with the label in the summary, expanded by default. Collapsing is not hiding:
 * collapsed elements are submitted and validated. A section collapsed by the user stays collapsed while the page
 * uses the same state key (browser session storage); sections with errors are always expanded.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class collapsible extends element {
    /** @var string|null key of the remembered collapsed state */
    private ?string $statekey = null;

    /** @var string summary text shown after the label */
    private string $summary = '';

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param string|null $statekey remember the state collapsed by the user under this key in the browser session,
     *      for example per wizard instance and record; null means the state is not remembered
     */
    public function __construct(string $name, string $label, ?string $statekey = null) {
        parent::__construct($name);
        $this->label = $label;
        $this->statekey = $statekey;
    }

    /**
     * Set plain text summary shown after the label, useful when collapsed.
     *
     * @param string $summary
     * @return static
     */
    public function set_summary(string $summary): static {
        $this->summary = $summary;
        return $this;
    }

    #[\Override]
    public function accepts_children(): bool {
        return true;
    }

    #[\Override]
    public function returns_data(): bool {
        return false;
    }

    #[\Override]
    protected function parse_value(): void {
        $this->value = null;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['statekey'] = $this->statekey ?? '';
        $context['summary'] = clean_string($this->summary);
        $context['has_child_errors'] = $this->has_child_errors($this, $allerrors);
        return $context;
    }

    /**
     * Do any descendants of the element have errors?
     *
     * @param element $element
     * @param array $allerrors
     * @return bool
     */
    private function has_child_errors(element $element, array $allerrors): bool {
        foreach ($element->get_children() as $elname) {
            if (!empty($allerrors[$elname])) {
                return true;
            }
            if ($this->has_child_errors($this->get_form()->get_element($elname), $allerrors)) {
                return true;
            }
        }
        return false;
    }
}
