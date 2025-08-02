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

namespace tool_mulib\output\dialog_form;

/**
 * Action to open legacy form in modal dialog.
 *
 * @package     tool_mulib
 * @copyright   2022 Open LMS (https://www.openlms.net/)
 * @copyright   2025 Petr Skoda
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class action implements \core\output\named_templatable, \core\output\renderable, \core\output\templatable {
    /** @var string reload the current page */
    public const AFTER_SUBMIT_RELOAD = 'reload';
    /** @var string go to page that the legacy form would redirect to */
    public const AFTER_SUBMIT_REDIRECT = 'redirect';
    /** @var string do nothing, this is for special cases that override onSubmitSuccess in template */
    public const AFTER_SUBMIT_NOTHING = 'nothing';

    /** @var string name of action */
    protected $title;
    /** @var string heading of dialog, defaults to action title */
    protected $dialogname = null;
    /** @var \moodle_url legacy form URL */
    protected $formurl;
    /** @var bool false means use redirection URL from form page, true means just reload current page on submission */
    protected $aftersubmit = self::AFTER_SUBMIT_RELOAD;
    /** @var bool set to true to use redirect to full page form */
    public $legacyformtest = false;
    /** @var bool is this action disabled? */
    protected $disabled = false;
    /** @var string extra CSS classes */
    protected $class = '';
    /** @var \core\output\pix_icon */
    protected $pixicon;
    /** @var string allowed options are '', 'xl' and 'lg' */
    protected $dialogsize = 'lg';

    /**
     * Constructor.
     *
     * @param \moodle_url $formurl
     * @param string $title
     */
    public function __construct(\moodle_url $formurl, string $title) {
        $this->formurl = $formurl;
        $this->title = (string)$title;
        $this->dialogname = (string)$title;
    }

    /**
     * Specify what happens after dialog form is submitted.
     *
     * @param string $value
     * @return void
     */
    public function set_after_submit(string $value): void {
        $this->aftersubmit = $value;
    }

    /**
     * Return action after form submission.
     *
     * @return string
     */
    public function get_after_submit(): string {
        return $this->aftersubmit;
    }

    /**
     * Returns dialog form URL.
     *
     * @return \moodle_url
     */
    public function get_form_url(): \moodle_url {
        return $this->formurl;
    }

    /**
     * Return form title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Set dialog name.
     *
     * @param string $name
     * @return void
     */
    public function set_dialog_name(string $name): void {
        $this->dialogname = $name;
    }

    /**
     * Returns dialog name.
     *
     * @return string
     */
    public function get_dialog_name(): string {
        return $this->dialogname;
    }

    /**
     * Is action disabled?
     *
     * @return bool
     */
    public function is_disabled(): bool {
        return $this->disabled;
    }

    /**
     * Set action disabled state.
     *
     * @param bool $value
     * @return void
     */
    public function set_disabled(bool $value): void {
        $this->disabled = $value;
    }

    /**
     * Set action icon.
     *
     * @param string $pix
     * @param string $component
     * @return void
     */
    public function set_icon(string $pix, string $component): void {
        $this->pixicon = new \core\output\pix_icon($pix, '', $component, ['aria-hidden' => 'true']);
    }

    /**
     * Get action icon.
     *
     * @return \pix_icon|null
     */
    public function get_icon(): ?\pix_icon {
        return $this->pixicon;
    }

    /**
     * Add additional classes to html element.
     *
     * @param string $class html class attribute
     */
    public function set_class(string $class): void {
        $this->class = $class;
    }

    /**
     * Returns additional element classes.
     *
     * @return string
     */
    public function get_class(): string {
        return $this->class;
    }

    /**
     * Set dialog size.
     *
     * @param string $size allowed values are '', 'sm', 'lg or 'xl'
     * @return void
     */
    public function set_dialog_size(string $size): void {
        if ($size === 'sm') {
            $size = '';
        }
        if ($size !== '' && $size !== 'lg' && $size !== 'xl') {
            throw new \core\exception\invalid_parameter_exception('invalid dialog size');
        }
        $this->dialogsize = $size;
    }

    /**
     * Returns dialog size.
     *
     * @return string
     */
    public function get_dialog_size(): string {
        return $this->dialogsize;
    }
}
