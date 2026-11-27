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

use core\output\core_renderer;
use tool_mulib\muform\element;

/**
 * Button bar of wizard stages, see docs/muform.md.
 *
 * Sticks to the bottom of the page while the stage scrolls: Back on the left, stage progress
 * in the middle, the other buttons on the right with submit buttons last. The wizard page sets
 * the progress and adds the draft button when drafts can be saved, stage forms add only
 * their own buttons (wizardback, wizardcontinue or the final submit buttons, cancel).
 *
 * Submit buttons come first in the HTML, browsers use the first submit button in the form
 * for implicit submission with Enter key.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wizardbuttons extends element {
    /** @var string element name, the wizard page finds the bar by it */
    public const string NAME = 'wizardbuttons';
    /** @var string name of the draft button rendered by the bar */
    public const string DRAFT = 'wizarddraft';
    /** @var string name of the reset button rendered by the bar */
    public const string RESET = 'wizardreset';

    /** @var string|null progress text, such as "Step 2 of 5" */
    private ?string $progress = null;
    /** @var string|null label of the draft button, null means no draft button */
    private ?string $draftlabel = null;
    /** @var array|null label and confirmation question of the reset button, null means no reset button */
    private ?array $reset = null;

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(self::NAME);
    }

    /**
     * Progress text shown in the middle of the bar, set by the wizard page.
     *
     * @param string|null $progress
     * @return $this
     */
    public function set_progress(?string $progress): static {
        $this->progress = $progress;
        return $this;
    }

    /**
     * Show the draft button, set by the wizard page when drafts can be saved.
     *
     * @param string|null $label null means no draft button
     * @return $this
     */
    public function set_draft_label(?string $label): static {
        $this->draftlabel = $label;
        return $this;
    }

    /**
     * Show the reset button on the left, set by the wizard page on locked stages of wizards that allow it.
     *
     * @param string $label
     * @param string $confirm confirmation question asked in the browser
     * @return $this
     */
    public function set_reset(string $label, string $confirm): static {
        $this->reset = [$label, $confirm];
        return $this;
    }

    /**
     * Was the reset button pressed?
     *
     * @return bool
     */
    public function is_reset_requested(): bool {
        $postdata = $this->get_form()->get_post_data();
        return $postdata !== null && array_key_exists(self::RESET, $postdata);
    }

    /**
     * Was the draft button pressed?
     *
     * @return bool
     */
    public function is_draft_requested(): bool {
        return (bool)$this->get_value();
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
        $postdata = $this->get_form()->get_post_data();
        $this->value = ($postdata !== null && array_key_exists(self::DRAFT, $postdata));
    }

    #[\Override]
    public function is_submitting(): bool {
        // The draft is saved from valid data of the stage only.
        return true;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $form = $this->get_form();
        $back = [];
        $submits = [];
        $others = [];
        foreach ($context['elements'] as $item) {
            $child = $form->get_element($item['name']);
            if ($child instanceof wizardback) {
                $back[] = $item;
            } else if ($child->is_submitting()) {
                $submits[] = $item;
            } else {
                $others[] = $item;
            }
        }
        // The right group is shown in reverse order: the first submit button is the first one in HTML
        // and the last one on the right, the draft button left of it, other buttons such as Cancel leftmost.
        $context['submits'] = $submits;
        $context['others'] = $others;
        $context['back'] = $back;
        $context['progress'] = $this->progress === null ? '' : clean_string($this->progress);
        $context['has_draft'] = $this->draftlabel !== null;
        $context['draftname'] = self::DRAFT;
        $context['draftlabel'] = $this->draftlabel === null ? '' : clean_string($this->draftlabel);
        $context['has_reset'] = $this->reset !== null;
        $context['resetname'] = self::RESET;
        $context['resetlabel'] = $this->reset ? clean_string($this->reset[0]) : '';
        $context['resetconfirm'] = $this->reset ? clean_string($this->reset[1]) : '';
        return $context;
    }
}
