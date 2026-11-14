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

namespace tool_mulib\phpunit\output;

use tool_mulib\output\expandable_text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Expandable text rendering tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\output\expandable_text::class)]
#[Group('MuTMS')]
final class expandable_text_test extends \advanced_testcase {
    public function test_explicit_short(): void {
        global $OUTPUT;

        $this->assertSame(
            '<details class="tool_mulib-expandable-text"><summary class="text-truncate"><span>A &#38; B</span></summary>'
                . '<div class="tool_mulib-expandable-text-full"><p>Full</p></div></details>',
            trim($OUTPUT->render(new expandable_text('<p>Full</p>', 'A & B')))
        );
        // Short text is used even when the full text is short.
        $this->assertStringContainsString('<summary', $OUTPUT->render(new expandable_text('Full', 'Short')));
        $this->assertStringContainsString('<span>Short</span>', $OUTPUT->render(new expandable_text('Full', ' Short ')));

        // Blank short text is the same as none.
        $this->assertSame('Full', trim($OUTPUT->render(new expandable_text('Full', ''))));
        $this->assertSame('Full', trim($OUTPUT->render(new expandable_text('Full', ' '))));
        $this->assertStringContainsString(
            '<span>One two</span>',
            $OUTPUT->render(new expandable_text('<p>One</p><p>two</p>', ' '))
        );

        $this->assertSame('&nbsp;', trim($OUTPUT->render(new expandable_text('', 'Short'))));
        $this->assertSame('&nbsp;', trim($OUTPUT->render(new expandable_text(null, 'Short'))));
    }

    public function test_cleaning(): void {
        global $OUTPUT;

        // Full text is always cleaned.
        $html = '<p onclick="alert(1)">Hello <b>world</b><script>alert(2)</script></p><p data-x="1">more</p>';
        $clean = '<p>Hello <b>world</b></p><p>more</p>';
        $this->assertSame(
            '<details class="tool_mulib-expandable-text"><summary class="text-truncate"><span>Short</span></summary>'
                . '<div class="tool_mulib-expandable-text-full">' . $clean . '</div></details>',
            trim($OUTPUT->render(new expandable_text($html, 'Short')))
        );
        $this->assertStringContainsString(
            '<span>Hello world more</span></summary><div class="tool_mulib-expandable-text-full">' . $clean . '</div>',
            $OUTPUT->render(new expandable_text($html))
        );
        $this->assertSame('&nbsp;', trim($OUTPUT->render(new expandable_text('<script>alert(2)</script>'))));

        // Unsafe html is not cleaned.
        $raw = '<div data-x="1"><button type="button" onclick="x()">Hello</button></div>';
        $this->assertSame(
            '<details class="tool_mulib-expandable-text"><summary class="text-truncate"><span>Short</span></summary>'
                . '<div class="tool_mulib-expandable-text-full">' . $raw . '</div></details>',
            trim($OUTPUT->render((new expandable_text($raw, 'Short'))->allow_unsafe_rawhtml()))
        );
        $this->assertStringContainsString(
            '<span>Hello</span></summary><div class="tool_mulib-expandable-text-full">' . $raw . '</div>',
            $OUTPUT->render((new expandable_text($raw))->allow_unsafe_rawhtml())
        );
        $this->assertSame('&nbsp;', trim($OUTPUT->render((new expandable_text('', 'Short'))->allow_unsafe_rawhtml())));
    }

    public function test_is_empty(): void {
        global $OUTPUT;

        $empty = [null, '', " \n", '<p></p>', '<p><br></p>', '<p><br /></p>', '<p>&nbsp;</p>', "<p>\u{00a0}</p>",
            '<p>&#160; &#xA0;</p>', '<div><span></span></div>', '<ul><li></li></ul>', '<!-- comment -->',
            "<p>&nbsp;</p>\n<p dir=\"ltr\" style=\"text-align: left;\"><br></p>"];
        foreach ($empty as $html) {
            $this->assertTrue(expandable_text::is_empty($html), (string)$html);
            $this->assertSame('&nbsp;', trim($OUTPUT->render(new expandable_text($html))), (string)$html);
            $this->assertSame('&nbsp;', trim($OUTPUT->render(new expandable_text($html, 'Short'))), (string)$html);
        }

        $this->assertFalse(expandable_text::is_empty('0'));
        $this->assertFalse(expandable_text::is_empty('<p>.</p>'));
        $this->assertFalse(expandable_text::is_empty('<p>&amp;</p>'));
        foreach (['<p><img src="x.png" alt=""></p>', '<video src="x.mp4"></video>', '<audio src="x.mp3"></audio>',
                '<iframe src="https://example.com/"></iframe>', '<object data="x.pdf"></object>', '<embed src="x.pdf">',
                '<svg><circle r="1"/></svg>'] as $html) {
            $this->assertFalse(expandable_text::is_empty($html), $html);
            $this->assertSame($html, trim($OUTPUT->render((new expandable_text($html))->allow_unsafe_rawhtml())), $html);
        }
    }

    public function test_is_short_plain(): void {
        $max = expandable_text::MIN_LENGTH - 1;

        $this->assertTrue(expandable_text::is_short_plain(''));
        $this->assertTrue(expandable_text::is_short_plain('Hello world'));
        $this->assertTrue(expandable_text::is_short_plain(" <p>Hello world</p>\n"));
        $this->assertTrue(expandable_text::is_short_plain('<P dir="ltr" style="text-align: left;">Hello world</P>'));
        $this->assertTrue(expandable_text::is_short_plain("<p>Hello\nworld</p>"));
        $this->assertTrue(expandable_text::is_short_plain('<p>&lt;b&gt;Hello&lt;/b&gt;</p>'));

        // Length is counted in characters of the shown text.
        $this->assertTrue(expandable_text::is_short_plain(str_repeat('ž', $max)));
        $this->assertFalse(expandable_text::is_short_plain(str_repeat('ž', $max + 1)));
        $this->assertTrue(expandable_text::is_short_plain('<p>' . str_repeat('&amp;', $max) . '</p>'));
        $this->assertFalse(expandable_text::is_short_plain('<p>' . str_repeat('&amp;', $max + 1) . '</p>'));
        $this->assertTrue(expandable_text::is_short_plain(str_repeat('x', $max) . str_repeat(' ', 20)));
        $this->assertTrue(expandable_text::is_short_plain(str_repeat('x ', 40) . str_repeat('&nbsp; ', 40)));

        // Any other tags.
        $this->assertFalse(expandable_text::is_short_plain('<p>Hello <strong>world</strong></p>'));
        $this->assertFalse(expandable_text::is_short_plain('Hello <em>world</em>'));
        $this->assertFalse(expandable_text::is_short_plain('<p>Hello <a href="https://example.com/">world</a></p>'));
        $this->assertFalse(expandable_text::is_short_plain('<p>Hello<br>world</p>'));
        $this->assertFalse(expandable_text::is_short_plain('<p>Hello</p><p>world</p>'));
        $this->assertFalse(expandable_text::is_short_plain('<div>Hello world</div>'));
        $this->assertFalse(expandable_text::is_short_plain('<ul><li>Hello world</li></ul>'));
        $this->assertFalse(expandable_text::is_short_plain('<p><img src="x.png" alt=""></p>'));
        $this->assertFalse(expandable_text::is_short_plain('<p>Hello world</p><!-- comment -->'));
    }

    public function test_guessed_short(): void {
        global $OUTPUT;

        // Short plain text is not collapsed, also as one paragraph from the editor.
        $this->assertSame('Hello world', trim($OUTPUT->render(new expandable_text('Hello world'))));
        $this->assertSame('Hello &#38; world', trim($OUTPUT->render(new expandable_text('<p>Hello &amp; world</p>'))));
        $this->assertSame('Hello world', trim($OUTPUT->render(new expandable_text('<p dir="ltr">Hello world</p>'))));
        $text = str_repeat('ž', expandable_text::MIN_LENGTH - 1);
        $this->assertSame($text, trim($OUTPUT->render(new expandable_text($text))));
        $this->assertSame('&nbsp;', trim($OUTPUT->render(new expandable_text(' '))));
        $this->assertSame('&nbsp;', trim($OUTPUT->render(new expandable_text(null))));

        // Long texts.
        $text = str_repeat('ž', expandable_text::MIN_LENGTH);
        $this->assertSame(
            '<details class="tool_mulib-expandable-text tool_mulib-expandable-text-guessed">'
                . '<summary class="text-truncate"><span>' . $text . '</span></summary>'
                . '<div class="tool_mulib-expandable-text-full">' . $text . '</div></details>',
            trim($OUTPUT->render(new expandable_text($text)))
        );

        // Any other tags.
        $html = '<p>One &amp; two</p><ul><li>three</li><li>four</li></ul>';
        $this->assertSame(
            '<details class="tool_mulib-expandable-text tool_mulib-expandable-text-guessed">'
                . '<summary class="text-truncate"><span>One &#38; two three four</span></summary>'
                . '<div class="tool_mulib-expandable-text-full">' . $html . '</div></details>',
            trim($OUTPUT->render(new expandable_text($html)))
        );
        foreach (['<p>Hello <strong>world</strong></p>', 'Hello <a href="https://example.com/">world</a>', 'Hello<br>world',
                '<p>Hello</p><p>world</p>', '<div>Hello world</div>'] as $html) {
            $this->assertStringContainsString('<span>Hello world</span>', $OUTPUT->render(new expandable_text($html)), $html);
        }

        // Long texts with markup.
        $long = str_repeat('x', expandable_text::MIN_LENGTH);
        $html = '<p>One &amp; two</p><ul><li>three</li><li>' . $long . '</li></ul>';
        $this->assertSame(
            '<details class="tool_mulib-expandable-text tool_mulib-expandable-text-guessed">'
                . '<summary class="text-truncate"><span>One &#38; two three ' . $long . '</span></summary>'
                . '<div class="tool_mulib-expandable-text-full">' . $html . '</div></details>',
            trim($OUTPUT->render(new expandable_text($html)))
        );

        // Encoded markup in the full text never becomes real markup in plain text.
        $html = '<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp;lt;b&amp;gt; "x" \'y\'</p><p>more</p>';
        $plain = '&#60;script&#62;alert(1)&#60;/script&#62; &#38;lt;b&#38;gt; &#34;x&#34; &#39;y&#39; more';
        $this->assertStringContainsString('<span>' . $plain . '</span>', $OUTPUT->render(new expandable_text($html)));
        $this->assertSame('&#60;b&#62;', trim($OUTPUT->render(new expandable_text('<p>&lt;b&gt;</p>'))));
        $this->assertStringContainsString(
            '<span>' . $plain . ' ' . $long . '</span>',
            $OUTPUT->render(new expandable_text($html . $long))
        );
        $this->assertStringContainsString(
            '<span>&#60;script&#62;alert(1)&#60;/script&#62;</span>',
            $OUTPUT->render(new expandable_text('Full', '<script>alert(1)</script>'))
        );

        // Entities do not count as more characters.
        $text = str_repeat('&amp;', expandable_text::MIN_LENGTH - 1);
        $this->assertSame(str_replace('&amp;', '&#38;', $text), trim($OUTPUT->render(new expandable_text($text))));
        $this->assertSame('One two', trim($OUTPUT->render(new expandable_text('<p>One&nbsp;&nbsp;two</p>'))));

        // Nothing to show on one line.
        $html = '<p><img src="x.png" alt="" /></p>';
        $this->assertSame($html, trim($OUTPUT->render(new expandable_text($html))));
    }
}
