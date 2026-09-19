<?php
/**
 * ClusterifyAI ChatBot category knowledge instruction notice block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\Category\Helper;

use Magento\Framework\View\Element\AbstractBlock;

/**
 * Class KnowledgeNotice
 *
 * Renders guidance banner explaining how to structure category AI context for the chatbot.
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
                <strong style="color: #0369a1; font-size: 14px;">Category Training Knowledge for AI ChatBot &amp; Assistant</strong>
                <span style="background: #e0f2fe; color: #0284c7; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase;">Max 20,000 Chars</span>
            </div>
            <div style="color: #334155; margin-bottom: 10px;">
                This field provides dedicated, category-level context directly to the <strong>Clusterify.AI Knowledge Base</strong>. When shoppers ask category-wide questions or browse this catalog section, the AI assistant uses this knowledge to guide visitors, explain selections, and recommend suitable products.
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 14px; margin-bottom: 8px;">
                <strong style="color: #0f172a; display: block; margin-bottom: 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">💡 Recommended Structure &amp; Advice:</strong>
                <ul style="margin: 0; padding-left: 20px; color: #475569; font-size: 12px; line-height: 1.6;">
                    <li><strong>Category Overview &amp; Buyer\'s Guide:</strong> What kind of products are in this category and who are they designed for? Key criteria customers should consider when choosing.</li>
                    <li><strong>Use Cases &amp; Scenarios:</strong> Common customer situations (e.g. <em>"Best options for beginners vs. professionals"</em>, <em>"Summer vs. winter apparel"</em>).</li>
                    <li><strong>Top Recommendations &amp; Key Items:</strong> Which products in this category stand out and why? Guide customers to top-performing subcategories or key items.</li>
                    <li><strong>Frequently Asked Questions (Category FAQs):</strong> Common questions customers ask before choosing a product in this category (e.g. warranty coverage, return policies, compatibility).</li>
                </ul>
            </div>
            <div style="font-size: 11px; color: #64748b;">
                ✍️ <em>Tip: Supports plain text and Markdown. Changes are synchronized to Clusterify automatically upon saving.</em>
            </div>
        </div>';
    }
}
