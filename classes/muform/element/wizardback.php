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

/**
 * Back button of a wizard stage, see docs/muform.md.
 *
 * Going back must not lose what the user entered and wizards never store data that were not validated,
 * so the button works in two ways: when nothing was changed in the browser it reloads the form
 * without validation, when something was changed it submits the form for validation like Continue
 * and the wizard stores the stage before going back.
 *
 * The browser decides which way is used, the server only sees a reload or a submission:
 * a reload stores nothing and a submission is always validated. Without JavaScript the button always reloads.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wizardback extends button {
    /** @var string element name */
    public const string NAME = 'back';
    /** @var string value sent by the browser when the form was changed */
    public const string VALUE_VALIDATE = 'validate';

    /** @var bool was the form submitted for validation with this button? */
    private bool $validating = false;

    /**
     * Constructor.
     *
     * @param bool $validate false means the button always reloads, for stages where going back must never
     *      store anything, such as the last stage whose submission finishes the wizard
     * @param string|null $label null means "Back"
     */
    public function __construct(
        /** @var bool validate and submit the form when it was changed */
        private bool $validate = true,
        ?string $label = null
    ) {
        parent::__construct(self::NAME, $label ?? get_string('muform_back', 'tool_mulib'));
    }

    #[\Override]
    public function get_role(): string {
        return 'reload';
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        if ($this->value && $this->validate) {
            $postdata = $this->get_form()->get_post_data();
            $this->validating = ($postdata[$this->get_name()] === self::VALUE_VALIDATE);
        }
    }

    #[\Override]
    public function is_reloading(): bool {
        return !$this->validating;
    }

    #[\Override]
    public function is_submitting(): bool {
        return $this->validating;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['is_submitifchanged'] = $this->validate;
        return $context;
    }
}
