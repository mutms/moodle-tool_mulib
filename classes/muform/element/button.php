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
use core\param;
use tool_mulib\muform\element;

/**
 * Base class for form buttons.
 *
 * Buttons do not return data, the value is true when the button was pressed.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class button extends element {
    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'primary' => param::BOOL,
        // Link style for minor actions inside the form such as adding or removing a row.
        'link' => param::BOOL,
        'small' => param::BOOL,
    ];

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     */
    public function __construct(string $name, string $label) {
        parent::__construct($name);
        $this->label = $label;
        // All buttons share one template.
        $this->set_template('tool_mulib/muform/element/button');
    }

    /** @var string|null confirmation question asked in the browser before the button submits */
    private ?string $confirm = null;

    /**
     * Ask for confirmation before the button submits the form, only in browsers with JavaScript.
     *
     * Use for reload buttons that throw away entered data, such as removing a filled row;
     * there is no confirmation without JavaScript, the server must not rely on it.
     *
     * @param string|null $message question shown in the confirmation dialog, null means no confirmation
     * @return static
     */
    public function set_confirm(?string $message): static {
        $this->confirm = $message;
        return $this;
    }

    /**
     * Returns button role used in browser: submit, cancel or reload.
     *
     * @return string
     */
    abstract public function get_role(): string;

    #[\Override]
    public function returns_data(): bool {
        return false;
    }

    #[\Override]
    protected function parse_value(): void {
        $postdata = $this->get_form()->get_post_data();
        $this->value = ($postdata !== null && array_key_exists($this->get_name(), $postdata));
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['role'] = $this->get_role();
        $context['is_primary'] = !empty($this->get_attribute('primary'));
        $context['is_link'] = !$context['is_primary'] && !empty($this->get_attribute('link'));
        $context['is_small'] = !empty($this->get_attribute('small'));
        $context['is_novalidate'] = ($this->get_role() !== 'submit');
        $context['confirm'] = $this->confirm === null ? '' : clean_string($this->confirm);
        return $context;
    }
}
