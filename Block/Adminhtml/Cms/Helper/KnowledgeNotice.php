<?php
/**
 * ClusterifyAI ChatBot CMS page knowledge instruction notice block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\Cms\Helper;

use Magento\Framework\View\Element\AbstractBlock;

/**
 * Class KnowledgeNotice
 *
 * Renders guidance banner explaining how to structure CMS page AI context for the chatbot.
 */
class KnowledgeNotice extends AbstractBlock
{
    /**
     * Render instructional HTML block.
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        return '<div style="background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 6px; padding: 16px 18px; margin-bottom: 16px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size: 13px; line-height: 1.5; color: #1e293b;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span style="font-size: 18px;">🤖</span>
                <strong style="color: #0369a1; font-size: 14px;">CMS Page Training Knowledge for AI ChatBot &amp; Assistant</strong>
                <span style="background: #e0f2fe; color: #0284c7; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase;">Max 20,000 Chars</span>
            </div>
            <div style="color: #334155; margin-bottom: 10px;">
                This field provides dedicated context directly to the <strong>Clusterify.AI Knowledge Base</strong> for this CMS page. While the main page HTML is indexed automatically, this section allows you to feed concise, high-priority store policies, company guidelines, and direct answers for your AI assistant.
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 14px; margin-bottom: 8px;">
                <strong style="color: #0f172a; display: block; margin-bottom: 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">💡 Recommended Structure &amp; Prompts:</strong>
                <ul style="margin: 0; padding-left: 20px; color: #475569; font-size: 12px; line-height: 1.6;">
                    <li><strong>Store Policies &amp; Direct Rules:</strong> For policy pages (Shipping, Returns, Privacy), summarize key rules clearly (e.g. <em>"Standard delivery is 2-4 business days"</em>, <em>"30-day hassle-free returns with prepaid labels"</em>).</li>
                    <li><strong>Company Identity &amp; Contact Details:</strong> For About Us or Contact pages, specify support hours, primary email/phone, physical locations, and core brand values.</li>
                    <li><strong>Frequently Asked Questions (FAQ):</strong> Preemptively answer common visitor doubts related to this page\'s topic with concise Q&amp;A pairs.</li>
                    <li><strong>Exceptions &amp; Special Conditions:</strong> Clearly list international restrictions, holiday shipping deadlines, or warranty terms.</li>
                </ul>
            </div>
            <div style="font-size: 11px; color: #64748b;">
                ✍️ <em>Tip: Supports plain text and Markdown headings/bullet points. Content is automatically synchronized to Clusterify on save.</em>
            </div>
        </div>';
    }
}
