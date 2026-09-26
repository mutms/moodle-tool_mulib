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

use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\output\core_renderer;
use core\param;
use core\router\util;
use core_tag_tag;
use tool_mulib\muform\element;
use tool_mulib\muform\tagarea\base;

/**
 * Tags element, the value is a list of tag names.
 *
 * The tag area given to the constructor owns access control, the standard tags mode and storage.
 * Current tags of an existing item are loaded from the area unless current data has the element key,
 * save() stores them. Names are trimmed and spaces collapsed, names that core would change further,
 * or that contain commas, are invalid; with standard tags only, unknown names are invalid too.
 * When tagging is disabled the element is hidden and the value is always empty. Not available to guests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tags extends element {
    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'placeholder' => param::TEXT,
    ];

    /** @var base tag area */
    private base $area;
    /** @var bool tagging enabled in the area */
    private bool $enabled;
    /** @var array why submitted names were refused, indexed by name */
    private array $nameerrors = [];

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param base $area
     * @param array $attributes
     */
    public function __construct(string $name, string $label, base $area, array $attributes = []) {
        parent::__construct($name);
        $this->label = $label;
        $this->area = $area;
        $this->enabled = $area->is_enabled();
        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
        if ($this->get_attribute('placeholder') === null) {
            $this->set_attribute('placeholder', get_string('entertags', 'tag'));
        }
    }

    #[\Override]
    protected function parse_value(): void {
        if (isguestuser() || !isloggedin()) {
            throw new moodle_exception('noguest');
        }
        $form = $this->get_form();
        if (!$this->enabled) {
            $this->value = [];
            return;
        }

        $postdata = $form->get_post_data();
        $posted = !$this->is_frozen() && $postdata !== null && array_key_exists($this->get_name(), $postdata);
        if (!$posted && !array_key_exists($this->get_name(), $form->get_current_data()) && $this->area->get_itemid()) {
            $this->value = $this->area->get_tags();
            return;
        }

        parent::parse_value();
        $value = $this->value;
        if ($value === null || $value === '' || $value === []) {
            $this->value = [];
            return;
        }
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            $this->value = [];
            $this->errors[] = $this->get_invalid_hint();
            return;
        }

        $names = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                $this->value = [];
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $item = trim(preg_replace('/\s+/u', ' ', $item));
            if ($item === '') {
                continue;
            }
            // Case insensitive duplicates are one tag in core.
            $key = \core_text::strtolower($item);
            if (!isset($names[$key])) {
                $names[$key] = $item;
            }
        }
        $this->value = array_values($names);

        foreach ($this->value as $item) {
            if (str_contains($item, ',') || clean_param($item, PARAM_TAG) !== $item) {
                $this->nameerrors[$item] = $this->get_invalid_hint();
            }
        }
        if ($this->area->get_showstandard() == core_tag_tag::STANDARD_ONLY) {
            $standard = $this->area->get_standard($this->value);
            foreach ($this->value as $item) {
                if (!isset($this->nameerrors[$item]) && !in_array(\core_text::strtolower($item), $standard, true)) {
                    $this->nameerrors[$item] = get_string('muform_tagnotstandard', 'tool_mulib');
                }
            }
        }
        foreach ($this->nameerrors as $item => $error) {
            $this->errors[] = $item . ': ' . $error;
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return $this->value !== [];
    }

    /**
     * Store the tags of an item, does nothing when tagging is disabled.
     *
     * @param int $itemid id of the item, required also for new items
     */
    public function save(int $itemid): void {
        $form = $this->get_form();
        if (!$form || !$form->is_valid()) {
            throw new coding_exception('Tags can be saved only from valid forms');
        }
        $current = $this->area->get_itemid();
        if ($itemid <= 0 || ($current && $current !== $itemid)) {
            throw new coding_exception('Invalid tags item id');
        }
        $this->area->save($itemid, $this->value);
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        if (!$this->enabled) {
            $context['is_hidden'] = true;
        }
        $selected = [];
        $selectedjs = [];
        foreach ($this->value as $item) {
            $error = $this->nameerrors[$item] ?? null;
            $selected[] = ['name' => clean_string($item), 'error' => ($error === null) ? null : clean_string($error)];
            // The island renders names as text and posts them back unchanged.
            $selectedjs[] = ['name' => $item, 'error' => $error];
        }
        $showstandard = $this->area->get_showstandard();
        $context['enabled'] = $this->enabled;
        $context['url'] = util::get_path_for_callable([\tool_mulib\route\api\muform\tags::class, 'search'])->out(false);
        $area = ['class' => get_class($this->area), 'args' => $this->area->get_args()];
        $context['areajson'] = json_encode($area, JSON_UNESCAPED_SLASHES);
        $context['suggest'] = ($showstandard != core_tag_tag::HIDE_STANDARD);
        $context['manageurl'] = null;
        if ($context['suggest'] && has_capability('moodle/tag:manage', \core\context\system::instance())) {
            $context['manageurl'] = (new \core\url('/tag/manage.php', ['tc' => $this->area->get_collection()]))->out(false);
        }
        $context['standardonly'] = ($showstandard == core_tag_tag::STANDARD_ONLY);
        $context['selected'] = $selected;
        $context['has_selected'] = (bool)$selected;
        $context['selectedjson'] = json_encode($selectedjs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $context['names'] = clean_string(implode(', ', $this->value));
        return $context;
    }
}
