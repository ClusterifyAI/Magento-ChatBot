<?php
/**
 * ClusterifyAI ChatBot HTML to Markdown unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Sync;

use ClusterifyAI\ChatBot\Model\Sync\HtmlToMarkdown;
use PHPUnit\Framework\TestCase;

/**
 * Class HtmlToMarkdownTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Model\Sync\HtmlToMarkdown converter.
 */
class HtmlToMarkdownTest extends TestCase
{
    private HtmlToMarkdown $converter;

    /**
     * Set up test instance.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->converter = new HtmlToMarkdown();
    }

    /**
     * Test empty or null input returns empty string.
     *
     * @return void
     */
    public function testConvertHandlesEmptyInput(): void
    {
        $this->assertSame('', $this->converter->convert(null));
        $this->assertSame('', $this->converter->convert('   '));
    }

    /**
     * Test removal of dangerous script and style tags.
     *
     * @return void
     */
    public function testConvertStripsDangerousTags(): void
    {
        $html = '<p>Safe text</p><script>alert("xss")</script><style>.hidden{display:none}</style>';
        $markdown = $this->converter->convert($html);

        $this->assertSame('Safe text', $markdown);
        $this->assertStringNotContainsString('alert', $markdown);
        $this->assertStringNotContainsString('display:none', $markdown);
    }

    /**
     * Test conversion of headings, bold, lists, and links.
     *
     * @return void
     */
    public function testConvertFormatsMarkdownSyntax(): void
    {
        $html = '<h2>Section Title</h2><p>This is <strong>bold</strong> and <em>italic</em>.</p><ul><li>Feature A</li><li>Feature B</li></ul><p><a href="https://example.com">Visit Link</a></p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('## Section Title', $markdown);
        $this->assertStringContainsString('**bold**', $markdown);
        $this->assertStringContainsString('*italic*', $markdown);
        $this->assertStringContainsString('- Feature A', $markdown);
        $this->assertStringContainsString('- Feature B', $markdown);
        $this->assertStringContainsString('[Visit Link](https://example.com)', $markdown);
    }

    /**
     * Test conversion of HTML tables preserves spacing and separation.
     *
     * @return void
     */
    public function testConvertHandlesTablesWithSpacing(): void
    {
        $html = '<table><tr><th>Feature</th><th>Spec</th></tr><tr><td>Weight</td><td>12kg</td></tr></table>';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('Feature | Spec', $markdown);
        $this->assertStringContainsString('Weight | 12kg', $markdown);
    }

    /**
     * Test conversion handles malformed or unclosed HTML without errors.
     *
     * @return void
     */
    public function testConvertHandlesMalformedBrokenHtml(): void
    {
        $html = '<div><p>Unclosed paragraph<h1>Heading 1<b>bold text<i>italic';
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString('Unclosed paragraph', $markdown);
        $this->assertStringContainsString('Heading 1', $markdown);
        $this->assertStringContainsString('bold text', $markdown);
    }

    /**
     * Test conversion ignores malicious javascript, vbscript, and data links case-insensitively.
     *
     * @return void
     */
    public function testConvertStripsJavascriptLinks(): void
    {
        $html = '<p><a href="JavaScript:alert(1)">Mixed Case</a>, <a href="VBSCRIPT:msgbox(1)">VBS</a>, <a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">Data</a>, and <a href="https://example.com">Legit</a></p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringNotContainsString('JavaScript:', $markdown);
        $this->assertStringNotContainsString('VBSCRIPT:', $markdown);
        $this->assertStringNotContainsString('data:', $markdown);
        $this->assertStringContainsString('Mixed Case', $markdown);
        $this->assertStringContainsString('VBS', $markdown);
        $this->assertStringContainsString('Data', $markdown);
        $this->assertStringContainsString('[Legit](https://example.com)', $markdown);
    }

    /**
     * Test headings with inner newlines and whitespace format cleanly.
     *
     * @return void
     */
    public function testConvertNormalizesMultiLineHeadings(): void
    {
        $html = "<h2>\n  Section Title With Whitespace  \n</h2><p>Paragraph content</p>";
        $markdown = $this->converter->convert($html);

        $this->assertStringContainsString("## Section Title With Whitespace\n\nParagraph content", $markdown);
    }

    /**
     * Test void input elements are stripped without leaking attributes.
     *
     * @return void
     */
    public function testConvertStripsVoidInputElements(): void
    {
        $html = '<input type="text" name="user" value="leaked_text"><p>Visible</p>';
        $markdown = $this->converter->convert($html);

        $this->assertSame('Visible', $markdown);
        $this->assertStringNotContainsString('leaked_text', $markdown);
    }

    /**
     * Test conversion cleans null bytes and corrupt UTF-8 sequences.
     *
     * @return void
     */
    public function testConvertHandlesCorruptEncodingAndNullBytes(): void
    {
        $html = "<p>Clean\x00Text and invalid \xC3\x28 byte</p>";
        $markdown = $this->converter->convert($html);

        $this->assertStringNotContainsString("\0", $markdown);
        $this->assertStringContainsString('CleanText', $markdown);
    }

    /**
     * Test conversion strips HTML comments and Magento template directives.
     *
     * @return void
     */
    public function testConvertStripsCommentsAndDirectives(): void
    {
        $html = '<!-- PageBuilder Row -->{{widget type="Magento\Cms\Block\Widget\Block"}}<p>Visible Content</p>';
        $markdown = $this->converter->convert($html);

        $this->assertStringNotContainsString('PageBuilder', $markdown);
        $this->assertStringNotContainsString('widget', $markdown);
        $this->assertSame('Visible Content', $markdown);
    }
}
