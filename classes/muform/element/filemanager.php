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

use core\context\user as user_context;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\output\core_renderer;
use core\url;
use core_form\filetypes_util;
use tool_mulib\muform\element;
use tool_mulib\muform\util\file_area;

/**
 * File manager element, the value is the draft item id holding the files.
 *
 * Files live in the user's draft area while the form is open, core's file manager
 * widget uploads and deletes them there. Current data may contain a file_area
 * (files are copied into a fresh draft), an existing draft item id, or nothing
 * (empty draft). After the record exists call save_area() or export_to_file_area()
 * to move the draft files into the permanent area.
 *
 * Validation enforces accepted types, maximum number of files and subfolders,
 * unlike core which silently drops files when saving.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filemanager extends element {
    /** @var int maximum number of files, -1 means unlimited */
    private int $maxfiles;
    /** @var string[] normalised accepted types, empty means any */
    private array $acceptedtypes;
    /** @var bool subfolders allowed */
    private bool $allowsubdirs;
    /** @var file_area|null permanent area */
    private ?file_area $filearea = null;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param int|null $maxfiles null means unlimited
     * @param array|null $acceptedtypes extensions, groups or mime types, null means any
     * @param bool $allowsubdirs
     */
    public function __construct(
        string $name,
        string $label,
        ?int $maxfiles = null,
        ?array $acceptedtypes = null,
        bool $allowsubdirs = false,
    ) {
        global $CFG;
        require_once("$CFG->libdir/filelib.php");
        require_once("$CFG->libdir/form/filemanager.php");
        parent::__construct($name);
        $this->label = $label;
        $this->maxfiles = ($maxfiles === null || $maxfiles < 0) ? -1 : $maxfiles;
        $types = (new filetypes_util())->normalize_file_types($acceptedtypes ?? []);
        $this->acceptedtypes = ($types === ['*']) ? [] : $types;
        $this->allowsubdirs = $allowsubdirs;
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
        if (isguestuser() || !isloggedin()) {
            throw new moodle_exception('noguest');
        }
        $form = $this->get_form();
        if (!$form) {
            throw new coding_exception('Do not call parse_value() manually');
        }
        $name = $this->get_name();

        $postdata = $form->get_post_data();
        if (!$this->is_frozen() && $postdata !== null && array_key_exists($name, $postdata)) {
            $value = $postdata[$name];
            if (!is_string($value) || !preg_match('/^\d+$/D', $value) || (int)$value <= 0) {
                $this->value = 0;
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $this->value = (int)$value;
            return;
        }

        $currentdata = $form->get_current_data();
        if (array_key_exists($name, $currentdata)) {
            $current = $currentdata[$name];
            if ($current instanceof file_area) {
                $this->filearea = $current;
            } else if (is_int($current) && $current > 0) {
                $this->value = $current;
                return;
            } else if (is_string($current) && preg_match('/^\d+$/D', $current) && (int)$current > 0) {
                $this->value = (int)$current;
                return;
            }
        }

        if ($this->filearea && $this->filearea->is_valid()) {
            $draftid = null;
            file_prepare_draft_area(
                $draftid,
                $this->filearea->get_contextid(),
                $this->filearea->get_component(),
                $this->filearea->get_filearea(),
                $this->filearea->get_itemid(),
                ['subdirs' => $this->allowsubdirs],
            );
            $this->value = $draftid;
        } else {
            $this->value = file_get_unused_draft_itemid();
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return (bool)$this->get_files();
    }

    #[\Override]
    public function validate(array &$allerrors): void {
        parent::validate($allerrors);
        if ($this->errors || !$this->value) {
            return;
        }
        $name = $this->get_name();
        $draftfiles = file_get_all_files_in_draftarea($this->value);

        if ($this->acceptedtypes) {
            $util = new filetypes_util();
            $wrongfiles = [];
            foreach ($draftfiles as $file) {
                if (!$util->is_allowed_file_type($file->filename, $this->acceptedtypes)) {
                    $wrongfiles[] = $file->filename;
                }
            }
            if ($wrongfiles) {
                $a = ['allowlist' => implode(', ', $this->acceptedtypes), 'wrongfiles' => implode(', ', $wrongfiles)];
                $allerrors[$name][] = get_string('err_wrongfileextension', 'core_form', $a);
            }
        }

        $info = file_get_draft_area_info($this->value);
        if ($this->maxfiles >= 0 && $info['filecount'] > $this->maxfiles) {
            $allerrors[$name][] = get_string('muform_toomanyfiles', 'tool_mulib', $this->maxfiles);
        }
        if (!$this->allowsubdirs) {
            $outside = $info['foldercount'] > 0;
            foreach ($draftfiles as $file) {
                if ($file->filepath !== '/') {
                    $outside = true;
                }
            }
            if ($outside) {
                $allerrors[$name][] = get_string('muform_nosubdirs', 'tool_mulib');
            }
        }
    }

    /**
     * Returns acceptable files currently in the draft area, folders excluded.
     *
     * Files of wrong type or in subfolders that are not allowed are left out,
     * validation reports them. Independent of the form submission state.
     *
     * @return \stored_file[]
     */
    public function get_files(): array {
        global $USER;
        $draftid = $this->get_value();
        if (!$draftid) {
            return [];
        }
        $usercontext = user_context::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'filepath, filename', false);
        $util = new filetypes_util();
        $result = [];
        foreach ($files as $file) {
            if (!$this->allowsubdirs && $file->get_filepath() !== '/') {
                continue;
            }
            if ($this->acceptedtypes && !$util->is_allowed_file_type($file->get_filename(), $this->acceptedtypes)) {
                continue;
            }
            $result[] = $file;
        }
        return $result;
    }

    /**
     * Save draft files to the element file area.
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
     * Save draft files to the given file area, replacing its contents.
     *
     * @param file_area $filearea
     */
    public function export_to_file_area(file_area $filearea): void {
        global $DB, $USER;

        if (!$filearea->is_valid()) {
            throw new coding_exception('File area is invalid');
        }
        $draftid = $this->get_value();
        if (!$draftid) {
            throw new coding_exception('Unknown draft area');
        }

        $usercontext = user_context::instance($USER->id);
        $draftexists = $DB->record_exists('files', [
            'contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid,
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
                debugging('Draft area ' . $draftid . ' does not exist any more, files were not saved', DEBUG_DEVELOPER);
                return;
            }
        }

        file_save_draft_area_files(
            $draftid,
            $filearea->get_contextid(),
            $filearea->get_component(),
            $filearea->get_filearea(),
            $filearea->get_itemid(),
            ['subdirs' => $this->allowsubdirs, 'maxfiles' => $this->maxfiles, 'maxbytes' => 0],
        );
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        global $CFG, $PAGE;

        $context = parent::get_template_data($output, $allerrors);
        $context['nolabelfor'] = true;
        $context['value'] = (string)(int)$this->value;
        $context['files'] = [];
        $context['filemanagerhtml'] = '';
        $context['has_filetypes'] = false;
        $context['filetypeshtml'] = '';
        if (!$this->value) {
            return $context;
        }

        if ($this->is_frozen()) {
            foreach ($this->get_files() as $file) {
                $context['files'][] = [
                    'name' => $file->get_filename(),
                    'url' => url::make_draftfile_url($file->get_itemid(), $file->get_filepath(), $file->get_filename())->out(false),
                ];
            }
            return $context;
        }

        $areacontext = null;
        if ($this->filearea && $this->filearea->is_valid()) {
            $areacontext = \core\context::instance_by_id($this->filearea->get_contextid());
        }
        $areacontext = $areacontext ?? $PAGE->context;

        $options = new \stdClass();
        $options->maxfiles = $this->maxfiles;
        $options->subdirs = $this->allowsubdirs;
        $options->accepted_types = $this->acceptedtypes ?: '*';
        $options->return_types = FILE_INTERNAL;
        $options->itemid = $this->value;
        $options->context = $areacontext;
        $options->target = $context['id'];
        $options->client_id = uniqid();
        $options->maxbytes = get_user_max_upload_file_size($areacontext, $CFG->maxbytes);
        $options->areamaxbytes = FILE_AREA_MAX_BYTES_UNLIMITED;

        $fm = new \form_filemanager($options);
        $context['filemanagerhtml'] = $PAGE->get_renderer('core', 'files')->render($fm);

        if ($this->acceptedtypes) {
            $descriptions = (new filetypes_util())->describe_file_types($this->acceptedtypes);
            $context['has_filetypes'] = true;
            $context['filetypeshtml'] = $output->render_from_template('core_form/filetypes-descriptions', $descriptions);
        }

        return $context;
    }
}
