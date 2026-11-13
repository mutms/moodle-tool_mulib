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

import {compactPages, downloadFormats, rangeText} from '../../src/reportbuilder/pager';

test('compactPages', () => {
    expect(compactPages(1, 1)).toEqual([1]);
    expect(compactPages(1, 2)).toEqual([1, 2]);
    expect(compactPages(2, 3)).toEqual([1, 2, 3]);
    expect(compactPages(1, 26)).toEqual([1, 2, null, 26]);
    expect(compactPages(2, 26)).toEqual([1, 2, 3, null, 26]);
    // One hidden page is shown instead of an ellipsis.
    expect(compactPages(3, 26)).toEqual([1, 2, 3, 4, null, 26]);
    expect(compactPages(4, 26)).toEqual([1, 2, 3, 4, 5, null, 26]);
    expect(compactPages(5, 26)).toEqual([1, null, 4, 5, 6, null, 26]);
    expect(compactPages(23, 26)).toEqual([1, null, 22, 23, 24, 25, 26]);
    expect(compactPages(26, 26)).toEqual([1, null, 25, 26]);
    expect(compactPages(1, 5)).toEqual([1, 2, null, 5]);
    expect(compactPages(3, 5)).toEqual([1, 2, 3, 4, 5]);
    // Lists of members have thousands of pages.
    expect(compactPages(500, 2500)).toEqual([1, null, 499, 500, 501, null, 2500]);
});

test('rangeText', () => {
    expect(rangeText('Showing {$a->from}–{$a->to} of {$a->total}', 31, 40, 257)).toBe('Showing 31–40 of 257');
    expect(rangeText('Showing {$a->from}–{$a->to} of {$a->total} matching filters', 1, 6, 6))
        .toBe('Showing 1–6 of 6 matching filters');
    // Word order differs in translations.
    expect(rangeText('{$a->total}: {$a->from}–{$a->to}', 1, 10, 57)).toBe('57: 1–10');
});

test('downloadFormats', () => {
    const container = document.createElement('div');
    container.innerHTML = `
        <form method="get" action="https://example.com/reportbuilder/download.php" class="dataformatselector m-1">
            <input type="hidden" name="sesskey" value="abc">
            <label for="downloadtype_download">Download table data as</label>
            <select name="download" id="downloadtype_download">
                <option value="csv">Comma separated values (.csv)</option>
                <option value="excel"> Microsoft Excel (.xlsx) </option>
            </select>
            <button type="submit">Download</button>
            <input type="hidden" name="id" value="5">
            <input type="hidden" name="parameters" value='{"programid":"7"}'>
        </form>`;
    const form = container.querySelector('form') as HTMLFormElement;
    const base = 'https://example.com/reportbuilder/download.php?sesskey=abc&id=5&parameters=%7B%22programid%22%3A%227%22%7D';
    expect(downloadFormats(form)).toEqual([
        {label: 'Comma separated values (.csv)', url: base + '&download=csv'},
        {label: 'Microsoft Excel (.xlsx)', url: base + '&download=excel'},
    ]);

    form.setAttribute('action', '/download.php?x=1');
    expect(downloadFormats(form)[0].url)
        .toBe('/download.php?x=1&sesskey=abc&id=5&parameters=%7B%22programid%22%3A%227%22%7D&download=csv');

    form.querySelector('select')?.remove();
    expect(downloadFormats(form)).toEqual([]);
});
