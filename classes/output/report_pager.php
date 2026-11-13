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

namespace tool_mulib\output;

use core_reportbuilder\system_report;

/**
 * Pager of system reports with the number of records, page size, download menu and page numbers,
 * replaces the core paging bar and the download form.
 *
 * The number of records is shown always, also when all records fit one page.
 * It is presentation only: the core paging bar is hidden, pages and page size are changed
 * through the dynamic table of the report. The page size is remembered in the browser tab only.
 * The download menu has the formats of the core download form, it downloads all records matching the filters.
 *
 * Must be rendered right after the report:
 *
 *     echo $report->output();
 *     echo $OUTPUT->render(new report_pager($report));
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_pager implements \core\output\named_templatable, \core\output\renderable {
    /** @var int[] page sizes offered to users, the default of the report is added */
    public const PAGE_SIZES = [10, 25, 50, 100];

    /** @var int report persistent id */
    private int $reportid;

    /** @var int[] page sizes */
    private array $sizes;

    /** @var bool true if any filter is applied */
    private bool $filtered;

    /** @var bool true if the core download form is replaced with a menu in the pager */
    private bool $download;

    /**
     * Constructor.
     *
     * @param system_report $report
     * @param bool $download false keeps the core download form below the report
     */
    public function __construct(system_report $report, bool $download = true) {
        $this->download = $download;
        $this->reportid = (int)$report->get_report_persistent()->get('id');
        $this->filtered = ($report->get_applied_filter_count() > 0);

        $sizes = self::PAGE_SIZES;
        $default = $report->get_default_per_page();
        if ($default > 0 && !in_array($default, $sizes, true)) {
            $sizes[] = $default;
            sort($sizes);
        }
        $this->sizes = $sizes;
    }

    #[\Override]
    public function export_for_template(\core\output\renderer_base $output): array {
        return [
            'reportid' => $this->reportid,
            'sizes' => json_encode($this->sizes),
            'filtered' => $this->filtered,
            'download' => $this->download,
            'uniqid' => \html_writer::random_id('tool_mulib_report_pager'),
        ];
    }

    #[\Override]
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'tool_mulib/report_pager';
    }
}
