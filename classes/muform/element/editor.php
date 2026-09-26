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

use core\context;
use core\context\user as user_context;
use core\exception\coding_exception;
use core\output\core_renderer;
use core\param;
use tool_mulib\muform\element;
use tool_mulib\muform\util\file_area;

/**
 * Text editor element, the value is the text, "<name>format" and "<name>draftitemid" are returned next to it.
 *
 * The site's preferred editor for the text format is used (Tiny for HTML, textarea for
 * other formats with a format selector). HTML and Moodle-auto text is cleaned with
 * clean_text() when loaded and when submitted, unless allow_unsafe_rawhtml() was called;
 * plain text is escaped at display time and Markdown cannot be cleaned.
 *
 * Files are optional: with $maxfiles other than 0 the element keeps a draft area the
 * editor uploads into, current data key "<name>filearea" or set_file_area() gives the
 * permanent area, get_value() returns the text with @@PLUGINFILE@@ links and save_area()
 * stores the files.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor extends element {
    /** @var int[] text formats that may be submitted, FORMAT_* constants are strings in Moodle */
    private const array FORMATS = [1, 0, 2, 4];

    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'rows' => param::INT,
    ];

    /** @var int maximum number of files, 0 means no files, -1 unlimited */
    private int $maxfiles;
    /** @var bool subfolders allowed */
    private bool $allowsubdirs;
    /** @var bool skip cleaning of HTML */
    private bool $unsafe = false;
    /** @var int text format */
    private int $format = 1;
    /** @var int|null draft item id when files are enabled */
    private ?int $draftitemid = null;
    /** @var file_area|null permanent area */
    private ?file_area $filearea = null;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param int $maxfiles 0 means text only, -1 unlimited
     * @param bool $allowsubdirs
     * @param array $attributes
     */
    public function __construct(
        string $name,
        string $label,
        int $maxfiles = 0,
        bool $allowsubdirs = false,
        array $attributes = [],
    ) {
        global $CFG;
        require_once("$CFG->libdir/filelib.php");
        require_once("$CFG->libdir/editorlib.php");
        parent::__construct($name);
        $this->label = $label;
        $this->maxfiles = ($maxfiles < 0) ? -1 : $maxfiles;
        $this->allowsubdirs = $allowsubdirs;
        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
    }

    /**
     * Do not clean submitted or loaded HTML, the caller takes responsibility for the content.
     *
     * @return $this
     */
    public function allow_unsafe_rawhtml(): static {
        if ($this->get_form()) {
            throw new coding_exception('Cleaning cannot be changed after attaching to a form');
        }
        $this->unsafe = true;
        return $this;
    }

    /**
     * Set the permanent file area, files are loaded from it when the form is not submitted.
     *
     * @param file_area $filearea
     * @return $this
     */
    public function set_file_area(file_area $filearea): static {
        if ($this->get_form()) {
            throw new coding_exception('File area cannot be changed after attaching to a form');
        }
        $this->filearea = $filearea;
        return $this;
    }

    /**
     * Returns the permanent file area if known.
     *
     * @return file_area|null
     */
    public function get_file_area(): ?file_area {
        return $this->filearea;
    }

    #[\Override]
    protected function parse_value(): void {
        $form = $this->get_form();
        if (!$form) {
            throw new coding_exception('Do not call parse_value() manually');
        }
        $name = $this->get_name();
        $currentdata = $form->get_current_data();
        if (($currentdata[$name . 'filearea'] ?? null) instanceof file_area) {
            $this->filearea = $currentdata[$name . 'filearea'];
        }
        $filesenabled = ($this->maxfiles !== 0 && isloggedin() && !isguestuser());

        $postdata = $form->get_post_data();
        if (!$this->is_frozen() && $postdata !== null && array_key_exists($name, $postdata)) {
            $posted = $postdata[$name];
            if (!is_array($posted)) {
                $posted = ['text' => $posted];
            }
            $text = $posted['text'] ?? '';
            $format = $posted['format'] ?? FORMAT_HTML;
            $validformat = is_string($format) && preg_match('/^\d$/D', $format) && in_array((int)$format, self::FORMATS, true);
            if (!is_string($text) || !$validformat) {
                $this->value = '';
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $this->format = (int)$format;
            $this->value = $this->clean($text);
            if ($filesenabled) {
                $itemid = $posted['itemid'] ?? '';
                if (!is_string($itemid) || !preg_match('/^\d+$/D', $itemid) || (int)$itemid <= 0) {
                    $this->errors[] = $this->get_invalid_hint();
                    return;
                }
                $this->draftitemid = (int)$itemid;
            }
            return;
        }

        $text = $currentdata[$name] ?? $this->get_default() ?? '';
        if ($text === null || is_array($text) || is_bool($text)) {
            $text = '';
        }
        $format = $currentdata[$name . 'format'] ?? editors_get_preferred_format();
        $this->format = in_array((int)$format, self::FORMATS, true) ? (int)$format : (int)FORMAT_HTML;
        $text = $this->clean((string)$text);

        if ($filesenabled) {
            if ($this->filearea && $this->filearea->is_valid()) {
                $draftid = null;
                $text = (string)file_prepare_draft_area(
                    $draftid,
                    $this->filearea->get_contextid(),
                    $this->filearea->get_component(),
                    $this->filearea->get_filearea(),
                    $this->filearea->get_itemid(),
                    ['subdirs' => $this->allowsubdirs],
                    $text
                );
                $this->draftitemid = $draftid;
            } else {
                $this->draftitemid = file_get_unused_draft_itemid();
            }
        }
        $this->value = $text;
    }

    /**
     * Clean text according to its format unless unsafe content was allowed.
     *
     * @param string $text
     * @return string
     */
    private function clean(string $text): string {
        if ($this->unsafe) {
            return $text;
        }
        if ($this->format === (int)FORMAT_HTML || $this->format === (int)FORMAT_MOODLE) {
            return clean_text($text, $this->format);
        }
        return $text;
    }

    #[\Override]
    public function get_value(): mixed {
        $text = parent::get_value();
        if ($this->draftitemid) {
            // Storable text, the draft links are only for editing.
            return file_rewrite_urls_to_pluginfile($text, $this->draftitemid);
        }
        return $text;
    }

    #[\Override]
    public function get_additional_data(): array {
        // The draft item id lets wizards and handlers keep or inspect the files without the element.
        return [
            $this->get_name() . 'format' => $this->format,
            $this->get_name() . 'draftitemid' => $this->draftitemid,
        ];
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        if ($this->format === (int)FORMAT_HTML || $this->format === (int)FORMAT_MOODLE) {
            // Media counts as content, entities such as &nbsp; do not.
            $text = strip_tags($this->value, '<img><video><audio><iframe><object>');
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim($text, " \t\n\r\0\x0B\xC2\xA0") !== '';
        }
        return trim($this->value) !== '';
    }

    /**
     * Save draft files to the element file area, the value already holds the text to store.
     *
     * Independent of the form submission state, the handler decides when to call it.
     */
    public function save_area(): void {
        if (!$this->filearea) {
            throw new coding_exception('File area was not provided');
        }
        $this->export_to_file_area($this->filearea);
    }

    /**
     * Save draft files to the given file area, the value already holds the text to store.
     *
     * Without files this does nothing, so handlers may always call it.
     *
     * @param file_area $filearea
     */
    public function export_to_file_area(file_area $filearea): void {
        global $DB, $USER;

        if (!$this->draftitemid) {
            return;
        }
        if (!$filearea->is_valid()) {
            throw new coding_exception('File area is invalid');
        }

        $usercontext = user_context::instance($USER->id);
        $draftexists = $DB->record_exists('files', [
            'contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft', 'itemid' => $this->draftitemid,
        ]);
        if (!$draftexists) {
            $targetexists = $DB->record_exists_select(
                'files',
                "contextid = :contextid AND component = :component AND filearea = :filearea
                     AND itemid = :itemid AND filename <> '.'",
                [
                    'contextid' => $filearea->get_contextid(), 'component' => $filearea->get_component(),
                    'filearea' => $filearea->get_filearea(), 'itemid' => $filearea->get_itemid(),
                ]
            );
            if ($targetexists) {
                // The draft area was cleaned up, saving it would delete all existing files.
                debugging('Draft area ' . $this->draftitemid . ' does not exist any more, files were not saved', DEBUG_DEVELOPER);
                return;
            }
        }

        file_save_draft_area_files(
            $this->draftitemid,
            $filearea->get_contextid(),
            $filearea->get_component(),
            $filearea->get_filearea(),
            $filearea->get_itemid(),
            ['subdirs' => $this->allowsubdirs, 'maxfiles' => $this->maxfiles, 'maxbytes' => 0],
            $this->value
        );
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        global $CFG, $PAGE;
        require_once("$CFG->dirroot/repository/lib.php");

        $context = parent::get_template_data($output, $allerrors);
        $context['format'] = $this->format;
        $context['has_formats'] = false;
        $context['formats'] = [];
        $context['has_files'] = ($this->draftitemid !== null);
        $context['draftitemid'] = (string)(int)$this->draftitemid;
        $context['rows'] = $this->get_attribute('rows') ?? 10;
        $context['contenthtml'] = '';
        $context['is_unsafe'] = $this->unsafe;

        $areacontext = null;
        if ($this->filearea && $this->filearea->is_valid()) {
            $areacontext = context::instance_by_id($this->filearea->get_contextid());
        }
        $areacontext = $areacontext ?? $PAGE->context;

        if ($this->is_frozen()) {
            $context['contenthtml'] = format_text($this->value, $this->format, ['context' => $areacontext, 'overflowdiv' => true]);
            return $context;
        }

        $editor = editors_get_preferred_editor((string)$this->format);
        $supported = $editor->get_supported_formats();
        if (count($supported) > 1) {
            $context['has_formats'] = true;
            foreach (format_text_menu() as $value => $label) {
                if (!isset($supported[$value])) {
                    continue;
                }
                $context['formats'][] = [
                    'value' => (int)$value,
                    'label' => clean_string($label),
                    'selected' => ((int)$value === $this->format),
                ];
            }
        }

        $options = [
            'context' => $areacontext,
            'maxfiles' => $this->draftitemid ? $this->maxfiles : 0,
            'maxbytes' => get_user_max_upload_file_size($areacontext, $CFG->maxbytes),
            'areamaxbytes' => FILE_AREA_MAX_BYTES_UNLIMITED,
            'subdirs' => $this->allowsubdirs,
            'return_types' => FILE_INTERNAL,
            'autosave' => false,
            'changeformat' => 0,
        ];
        $fpoptions = [];
        if ($this->draftitemid) {
            $types = ['image' => ['web_image'], 'media' => ['video', 'audio'], 'link' => '*', 'subtitle' => ['.vtt']];
            if (has_capability('moodle/h5p:deploy', $areacontext)) {
                $types['h5p'] = ['.h5p'];
            }
            foreach ($types as $key => $accepted) {
                $args = new \stdClass();
                $args->accepted_types = $accepted;
                $args->return_types = FILE_INTERNAL;
                $args->context = $areacontext;
                $args->env = 'filepicker';
                $fpoption = initialise_filepicker($args);
                $fpoption->context = $areacontext;
                $fpoption->client_id = uniqid();
                $fpoption->maxbytes = $options['maxbytes'];
                $fpoption->areamaxbytes = $options['areamaxbytes'];
                $fpoption->env = 'editor';
                $fpoption->itemid = $this->draftitemid;
                $fpoptions[$key] = $fpoption;
            }
        }
        $editor->set_text($this->value);
        $editor->use_editor($context['id'], $options, $fpoptions);

        return $context;
    }
}
