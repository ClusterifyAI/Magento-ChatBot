<?php
/**
 * ClusterifyAI ChatBot HTML to Markdown converter utility
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Sync;

use Throwable;

/**
 * Class HtmlToMarkdown
 *
 * Converts rich HTML content from Magento descriptions, CMS blocks, and pages
 * into clean, readable Markdown optimized for Clusterify AI knowledge ingestion.
 * Built with defensive multi-layer error handling and fallback guarantees.
 */
class HtmlToMarkdown
{
    /**
     * Convert HTML markup string into clean Markdown.
     * Guaranteed never to throw an exception or halt execution.
     *
     * @param string|null $html Raw HTML content
     * @return string Formatted Markdown text
     */
    public function convert(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        try {
            // Strip null bytes and sanitize UTF-8 encoding
            $cleaned = str_replace("\0", '', $html);
            if (!mb_check_encoding($cleaned, 'UTF-8')) {
                $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
            }

            // 1. Strip HTML comments (e.g. <!-- PageBuilder / comments -->)
            $cleaned = preg_replace('#<!--.*?-->#s', '', $cleaned) ?? $cleaned;

            // 2. Remove container tags and their inner contents
            $cleaned = preg_replace(
                '#<(script|style|svg|iframe|form|button|textarea|select|noscript)[^>]*?>.*?</\\1>#si',
                '',
                $cleaned
            ) ?? $cleaned;

            // 3. Remove void tags like <input>
            $cleaned = preg_replace('#<input[^>]*?/?>#si', '', $cleaned) ?? $cleaned;

            // 4. Remove Magento template directives (e.g. {{widget ...}}, {{media ...}})
            $cleaned = preg_replace('#\{\{[^}]*?\}\}#s', '', $cleaned) ?? $cleaned;

            // 5. Convert Headings (h1 - h6) with trimmed single-line text
            for ($i = 6; $i >= 1; $i--) {
                $prefix = str_repeat('#', $i) . ' ';
                $cleaned = preg_replace_callback(
                    '#<h' . $i . '[^>]*?>(.*?)</h' . $i . '>#si',
                    static function (array $m) use ($prefix): string {
                        $headingText = trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1])));
                        return $headingText !== '' ? "\n\n" . $prefix . $headingText . "\n\n" : "\n\n";
                    },
                    $cleaned
                ) ?? $cleaned;
            }

            // 6. Convert bold and strong
            $cleaned = preg_replace('#<(strong|b)[^>]*?>(.*?)</(strong|b)>#si', '**$2**', $cleaned) ?? $cleaned;

            // 7. Convert italic and em
            $cleaned = preg_replace('#<(em|i)[^>]*?>(.*?)</(em|i)>#si', '*$2*', $cleaned) ?? $cleaned;

            // 8. Convert links [text](href) with case-insensitive protocol sanitization
            $cleaned = preg_replace_callback(
                '#<a\s+[^>]*?href=["\']([^"\']*)["\'][^>]*?>(.*?)</a>#si',
                static function (array $matches): string {
                    $url = trim($matches[1]);
                    $text = trim(strip_tags($matches[2]));
                    $lowerUrl = strtolower($url);

                    if (
                        $text === ''
                        || str_starts_with($lowerUrl, 'javascript:')
                        || str_starts_with($lowerUrl, 'vbscript:')
                        || str_starts_with($lowerUrl, 'data:')
                    ) {
                        return $text;
                    }

                    return sprintf('[%s](%s)', $text, $url);
                },
                $cleaned
            ) ?? $cleaned;

            // 9. Convert list items <li> to Markdown bullets
            $cleaned = preg_replace('#<li[^>]*?>(.*?)</li>#si', "\n- $1", $cleaned) ?? $cleaned;

            // 10. Convert table cells and rows to clean readable text
            $cleaned = preg_replace('#</t[hd]>#si', ' | ', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('#</tr>#si', "\n", $cleaned) ?? $cleaned;

            // 11. Convert block tags (<p>, <div>, <section>, <blockquote>) into natural spacing
            $cleaned = preg_replace('#<br\s*/?>#si', "\n", $cleaned) ?? $cleaned;
            $cleaned = preg_replace('#<(p|div|section|article|blockquote|aside)[^>]*?>#si', "\n\n", $cleaned) ?? $cleaned;
            $cleaned = preg_replace('#</(p|div|section|article|blockquote|aside)>#si', "\n\n", $cleaned) ?? $cleaned;

            // 12. Strip any remaining HTML tags
            $cleaned = strip_tags($cleaned);

            // 13. Decode HTML entities safely with ENT_SUBSTITUTE
            $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            // 14. Normalize whitespace-only lines, spaces, and excessive line breaks
            $cleaned = preg_replace('/^[ \t]+$/m', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/[ \t]+/', ' ', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned) ?? $cleaned;

            return trim($cleaned);
        } catch (Throwable) {
            // Absolute fallback: strip tags directly to guarantee sync never breaks
            try {
                $fallback = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html));
                $fallback = html_entity_decode($fallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return trim((string) preg_replace('/\s+/', ' ', $fallback));
            } catch (Throwable) {
                return trim(strip_tags($html));
            }
        }
    }
}
