# Clusterify.AI ChatBot & Assistant for Magento 2

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](composer.json)
[![Magento Open Source](https://img.shields.io/badge/Open_Source-2.4.4_--_2.4.9+-orange.svg?logo=magento&logoColor=white)](etc/module.xml)
[![Adobe Commerce](https://img.shields.io/badge/Adobe_Commerce-Cloud_%26_On--Premise-red.svg?logo=adobe&logoColor=white)](CLOUD-COMPATIBILITY.md)
[![PHP](https://img.shields.io/badge/php-8.2%20--%208.5-8892bf.svg)](composer.json)
[![Tests](https://img.shields.io/badge/tests-133%20passed-brightgreen.svg?logo=php&logoColor=white)](Test/Unit/)
[![Assertions](https://img.shields.io/badge/assertions-451%20verified-brightgreen.svg)](Test/Unit/)
[![Test Quality](https://img.shields.io/badge/suite-100%25%20passing%20%C2%B7%200%20warnings-success.svg)](Test/Unit/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](composer.json)

The official Magento 2 / Adobe Commerce integration for **[Clusterify.AI](https://clusterify.ai)**. Empower your online store with an intelligent, 24/7 AI shopping assistant that answers customer inquiries instantly, guides shoppers to relevant catalog products, and drives measurable conversion growth.

---

## 1. What is This Extension & Who is It For?

The **Clusterify.AI ChatBot & Assistant** extension seamlessly bridges your Magento storefront with Clusterify's cutting-edge conversational AI engine.

### Who is It For?
- **E-Commerce Store Owners & Merchants**: Looking to automate pre-sales consultations and customer care around the clock without hiring large support teams.
- **Marketing & Growth Teams**: Seeking to reduce bounce rates, increase session duration, and guide undecided visitors directly to checkout with personalized recommendations.
- **Customer Support Teams**: Aiming to eliminate repetitive support tickets regarding order status, returns, sizing, and shipping policies.

### Key Business Benefits
- **24/7 Intelligent Shopping Guidance**: Customers receive instant, conversational answers day or night.
- **Conversion Rate Optimization**: Proactively answers pre-purchase questions and removes hesitation right on the product page.
- **Multilingual & Multi-Store Ready**: Operates across different Magento Websites and Store Views, allowing separate language models and configurations per storefront.
- **Lightweight & High-Performance**: Loads asynchronously via a deferred script bundle (`clusterify-chatbot-react.bundle.min.js`), ensuring zero negative impact on Google Core Web Vitals or page load times.

---

## 2. Companion Documentation & Guides

- **[`CLI.md`](./CLI.md)**: Full command-line reference and examples for `clusterify:chatbot:config:*` and `clusterify:chatbot:sync:*`.
- **[`SYNC.md`](./SYNC.md)**: In-depth technical architecture, process flow, and extendability guide for URL knowledge base synchronization.
- **[`CLOUD-COMPATIBILITY.md`](./CLOUD-COMPATIBILITY.md)**: Adobe Commerce (Enterprise) & Cloud (ECE) compatibility guide covering Content Staging (`row_id`), Multi-Source Inventory (MSI), and read-only filesystems.

---

## 3. Safety First: Default Configuration & Security Standards

The extension is designed with a **strict "safe-by-default" security and stability model**:

### Safe by Default
- **Disabled Out of the Box**: Both the extension functionality and storefront display switches default to **No (Disabled)**. Installing or enabling the module in code will never display anything on your live storefront until you explicitly choose to turn it on in the Admin Panel.
- **Cart & Checkout Protection**: All Cart, One-Page Checkout, and payment redirect pages (`checkout_cart_index`, `checkout_index_index`, PayPal, etc.) are **disabled by default**. The assistant will not appear on payment funnels unless you deliberately enable it, preventing any distraction during customer transactions.

### Security & Credential Protection
- **Public UUID Architecture**: The client-side widget relies solely on your **ChatBot Public UUID**. This token is safely exposed in HTML; it only identifies the visual widget and authorized domain.
- **Zero Secret Key Exposure**: Your **API Secret Key** (`sk_live_...`) is encrypted in the Magento database using Magento's cryptographic encryption provider (`Magento\Config\Model\Config\Backend\Encrypted`). It is never sent to the browser or embedded into storefront HTML.
- **Tested Network Reliability**: All backend API communication utilizes the official PHP SDK, featuring automatic exponential backoff retries and RFC 7231 rate limit compliance.

---

## 4. Configuration Guide

In the Magento Admin Panel, navigate to:
**Stores &gt; Settings &gt; Configuration &gt; Clusterify.AI &gt; ChatBot & Assistant**

### Scope Selector (Store / Website Scoping)
In the top-left corner, you can switch between **Default Config**, specific **Websites**, or individual **Store Views**. You can maintain one global chatbot or configure distinct Public UUIDs and display rules per language/store view.

---

### Group 1: General Configuration
- **Enable Extension** *(Switch, Default: No)*: Master toggle for the entire module. When disabled, all storefront and background operations are completely shut down.
- **Show Chatbot on Storefront** *(Switch, Default: No)*: Storefront presentation toggle. When set to *No*, the chatbot widget is hidden from website visitors, but backend synchronization and administration tools remain active.

---

### Group 2: ChatBot/Assistant Public UUID
- **ChatBot Public UUID**: Enter the unique Public UUID found in your [Clusterify.AI Dashboard &gt; Chatbot](https://dashboard.clusterify.ai/chatbot) page.
- **Automatic Storefront Embed Code (Information / Preview Only)**: An informative read-only code display showing the exact loader script generated for your Public UUID:
  ```html
  <!-- Clusterify.AI ChatBot Loader - START -->
  <script id="clusterify-chatbot-script">
  (function () {
      window.__clusterify = window.__clusterify || {};
      window.__clusterify.public_uuid = "YOUR_PUBLIC_UUID";
      var script = document.createElement("script");
      script.src = "https://api.clusterify.ai/static/clusterify-chatbot-react.bundle.min.js";
      document.head.appendChild(script);
  })();
  </script>
  <noscript>Please enable JavaScript to access the Clusterify.AI ChatBot.</noscript>
  <!-- Clusterify.AI ChatBot Loader - END -->
  ```
  *(Note: You do NOT need to copy or paste this code manually. Magento automatically injects this snippet before `</body>` when enabled).*

---

### Group 3: ChatBot Visibility Per Page Type
*Fine-grained granular display control across every page on your store.*
- **Dynamic Discovery**: All page types are loaded dynamically via Magento's layout architecture (`etc/frontend/page_types.xml`), automatically discovering pages from core modules and third-party extensions.
- **Real-Time Search Filter**: Quickly find any page by name (e.g. "Cart", "Category") or technical layout handle (e.g. `catalog_product_view`).
- **Bulk Action Controls**: One-click **Enable All**, **Disable All**, or **Reset to Defaults** buttons.
- **Organized Visual Categories**:
  - **Landing & CMS Pages**: Homepage, about us, contact, custom CMS pages.
  - **Product & Catalog Pages**: Category views, product details, image galleries, product reviews.
  - **Checkout & Cart Pages** *(Default: Disabled)*: Shopping cart, onepage checkout, multi-shipping, and payment gateways.
  - **Customer Account Pages**: Customer registration, login, dashboard, order history, address book, wishlists.
  - **Search & Utility Pages**: Quick search results, advanced search, popular terms, RSS feeds.
  - **Custom Page Types**: Automatically lists any page types defined by third-party extensions. Displays *"There is no custom page type."* if none are installed.

---

### Group 4: Clusterify.AI API Authorization
*Required for merchants using the PROFESSIONAL Plan or higher to synchronize URL-based knowledge.*
- **Informative Plan Notice**: Highlights that deep URL-based knowledge base synchronization requires a **PROFESSIONAL Plan** or higher, while **STARTER Plan** customers enjoy full management directly in the [Clusterify.AI Dashboard](https://dashboard.clusterify.ai).
- **API Public Key**: Your `pk_live_...` credential from [Clusterify Dashboard &gt; API Keys](https://dashboard.clusterify.ai/api-key).
- **API Secret Key**: Your `sk_live_...` credential (encrypted in database).
- **API Base URL**: Defaults to `https://api.clusterify.ai`.
- **Test API Connection** Button: An interactive AJAX tool that validates your credentials against Clusterify's live healthcheck endpoint (`/v1/ping`) and provides immediate diagnostic feedback with direct dashboard recovery links if keys are inactive or invalid.

---

### Group 5: URL Knowledge Base Synchronization (Professional Plan)
*Automatic, asynchronous background synchronization of storefront pages to Clusterify.AI's deep URL knowledge base via RabbitMQ.*
- **Subscription Safeguard**: Synchronizing URL-based knowledge requires an active **PROFESSIONAL Plan** or higher (`plan_id >= 2`). If your account is on the **STARTER Plan** (`plan_id = 1`), an informative warning banner is displayed and all switches in this group are **locked and disabled** to prevent sync errors and conserve server resources.
- **Enable Knowledge Base Sync** *(Switch, Default: No)*: Master toggle for URL knowledge synchronization.
- **Synchronize CMS Pages** *(Switch, Default: Yes)*: Automatically extracts active CMS pages (Policies, About Us, Customer Service, etc.), converts content to clean Markdown, and resolves canonical URLs without `.html` suffixes.
- **Synchronize Category Pages** *(Switch, Default: Yes)*: Automatically syncs category descriptions and active subcategory hierarchies.
- **Synchronize Product Pages** *(Switch, Default: Yes)*: Synchronizes individually visible catalog products with SKUs, formatted prices, stock availability, descriptions, and configurable variant options (e.g. `Sizes: S, M; Colors: Black, Blue`).
*(Visual Divider Line)*
- **Sync Custom AI Knowledge (Instead of Core Descriptions)** *(Switch, Default: Yes)*: Controls whether to synchronize dedicated AI training context or standard storefront descriptions to the Clusterify Knowledge Base.  
  - **Yes (Recommended / Default / Fallback)**: Synchronizes URL, Title/Name, SKU, Configurable Options, Price/Availability (if enabled below), and the dedicated *ChatBot Knowledge & AI Context* attribute (`clusterify_chatbot_knowledge`) instead of standard storefront HTML descriptions. If custom knowledge is empty for an entity, it safely falls back to standard descriptions.
  - **No**: Synchronizes URL, Title/Name, SKU, Configurable Options, Price/Availability (if enabled below), and standard storefront HTML descriptions (Summary and Description).  
  > **⚠️ Operational Notice**: Changing this setting requires running a full reindex across all three indexers (`bin/magento indexer:reindex clusterify_chatbot_cms clusterify_chatbot_category clusterify_chatbot_product`) and processing queue tasks to update existing entries with the new content format. This is an asynchronous background process, and the chatbot's knowledge will update progressively as the queue tasks complete.
- **Sync Product Price to ChatBot** *(Switch, Default: No)*: When enabled, formatted product prices are synchronized into the ChatBot Knowledge Base. When set to *No* (default), prices are omitted from the AI context so shoppers are guided to view current live pricing directly on the storefront product page.
- **Sync Product Availability (Stock Status) to ChatBot** *(Switch, Default: No)*: When enabled, product stock availability (In Stock / Out of Stock) is synchronized into the ChatBot Knowledge Base. When set to *No* (default), availability status is omitted from the AI context.
- **Sync In-Stock Products Only** *(Switch, Default: No)*: When enabled, out-of-stock products are automatically excluded and purged from the Knowledge Base to conserve URL quotas.  
  > **⚠️ Operational Notice**: Changing this setting requires running a full product reindex (`bin/magento indexer:reindex clusterify_chatbot_product`) and processing queue tasks to discover and purge previously synchronized out-of-stock items. This is an asynchronous background process, and the chatbot's catalog knowledge will update progressively as the queue tasks complete. Consider whether your shoppers benefit from the chatbot answering questions about temporarily out-of-stock items before enabling.
*(Visual Divider Line)*
- **Automated Background Queue Processing (Cron)** *(Switch, Default: Yes)*: When enabled, Magento cron automatically drains pending RabbitMQ tasks every minute, ensuring synchronization progresses without manual CLI commands or persistent server daemons.
- **Queue Batch Size Per Run** *(Text, Default: 50)*: Maximum number of pending messages processed per entity queue during each background cron or on-demand execution.

---

### Group 6: Custom AI Knowledge Attributes (Products, Categories & CMS)

The extension installs a dedicated **`clusterify_chatbot_knowledge`** attribute across catalog products, category pages, and CMS pages, enabling merchants to feed high-priority custom context directly into the AI knowledge base:

- **Dedicated Attribute Group & Fieldset**: Displayed under a clean, collapsible **`Clusterify AI ChatBot`** section on the Product Edit, Category Edit, and CMS Page Edit screens.
- **Generous 20,000 Characters Capacity**: Backed by MariaDB `TEXT` / `mediumtext` storage with client-side length validation (`max_text_length: 20000`).
- **Interactive Training Guidance Banner**: Each edit form presents a styled instruction box featuring curated prompts and best practices:
  - *Products*: Value propositions, sales arguments ("Why buy?"), sizing & fit nuances, FAQs, and cross-sell pairing suggestions.
  - *Categories*: Category overviews, buyer's selection guides, common use cases, and top category recommendations.
  - *CMS Pages*: Shipping timeframes, return policies, customer service hours, and core brand values.
- **Automatic Markdown Inclusion**: Content saved in this field is automatically synchronized and appended under a dedicated `## AI Knowledge & Context` heading in the entity's generated Markdown documentation.

---

## 5. Admin Monitoring Dashboard (*CHATBOT > Dashboard & Status*)

In the Magento Admin Panel, click the **CHATBOT** primary sidebar menu item (featuring the prominent speech-bubble brand icon) and select **Dashboard & Status** (also accessible via *Marketing > Chatbot & Assistant > Dashboard & Status*):
- **Live Subscription & Plan Verification**: Displays real-time plan detection (`STARTER Plan`, `PROFESSIONAL Plan`), plan ID, and dynamic upgrade callouts to the billing portal.
- **Key Metrics Overview**: Real-time cards for Storefront Assistant status, API connection state, live URL Knowledge Base quota (`37 / 1,000 URLs`), and page type visibility coverage.
- **Live RabbitMQ Queue Monitor**: Real-time backlog tracking across `CMS Pages Queue`, `Categories Queue`, and `Products Queue`, showing pending tasks and active worker counts.
- **"⚡ Process Pending Tasks Now" Button**: An on-demand AJAX action button directly above the RabbitMQ table that drains up to 50 pending messages per queue and live-updates the counts on screen without requiring terminal access or page reload.
- **Direct Portal Links (`Clusterify.AI`)**: Submenu links directly to your Cloud Dashboard, Plan & Billing, ChatBot builder, and API Keys, each styled with an external link indicator icon (`↗`) and opening securely in a new browser tab.
- **Terminal Reference Card**: Quick copy-paste commands for running indexers, the new `clusterify:chatbot:sync:consume` command, and daemon workers.

---

## 6. Technical Architecture & PHP SDK Integration

### Official PHP SDK (`clusterify/chatbot-sdk`)
All programmatic communication with the Clusterify.AI platform is powered by the official enterprise-grade PHP SDK:
- **Package**: [`clusterify/chatbot-sdk`](https://packagist.org/packages/clusterify/chatbot-sdk)
- **Repository**: [ClusterifyAI/php-clusterify-sdk](https://github.com/ClusterifyAI/php-clusterify-sdk)
- **Standards**: Strictly typed PHP 8.2+, PSR-4, PSR-7, PSR-17, and PSR-18 compliance.
- **Factory Architecture**: The extension provides `ClusterifyAI\ChatBot\Service\ClientFactory` to instantiate configured, retry-resilient `Clusterify\ClusterifyClient` objects with automatic scope resolution (Store View &gt; Website &gt; Default) and strict SSRF protocol and IP validation.
- **Strict Invariant**: Direct HTTP requests (`curl_*`, Guzzle client instantiation, `file_get_contents`) are strictly prohibited in favor of the SDK.

### Decoupled Knowledge Base Sync Pipeline (RabbitMQ &amp; Indexers)
To guarantee zero database locking during flash sales, automated imports, or peak shopping hours:
- **Unified Short-Circuit Guard**: All indexers, queue consumers, observers, and CLI commands call `PlanService::canSyncEntity()` as their very first action. Local settings (`isEnabled`, `isSyncEnabled`, entity toggles, API keys) are checked first before any profile API call, guaranteeing zero database queries and zero network overhead when disabled or on Starter Plan (`plan_id = 1`).
- **3 Dedicated Indexers**: `clusterify_chatbot_cms`, `clusterify_chatbot_category`, and `clusterify_chatbot_product` track entity changes via Mview changelog database triggers.
- **Fast Non-Blocking Producers**: Indexers do not make external HTTP calls; they publish lightweight messages to RabbitMQ (`clusterify.chatbot.sync.*`) in batches of 100.
- **Rate-Limit Aware Consumers**: Background consumers (`CmsConsumer`, `CategoryConsumer`, `ProductConsumer`) process messages with store emulation, apply a 300ms throttle delay, and retry automatically on HTTP 429 rate limits using the SDK's `$e->getRetryAfter()`.
- **Deletion Purge Pipeline**: `EntityDeleteObserver` captures pre-deletion events (`*_delete_before`), resolves the exact storefront URL across active store views, and dispatches direct purge messages to remove deleted items from Clusterify instantly.
- *Detailed Architecture*: For full process flow diagrams and extendability guides, consult **[`SYNC.md`](./SYNC.md)**.

### Dynamic Page Discovery Pipeline
Rather than hardcoding page paths, the module injects Magento's core `Magento\Framework\View\Layout\PageType\Config` service via `ClusterifyAI\ChatBot\Service\PageVisibility`. This reads all `page_types.xml` declarations aggregated across the entire Magento system.

### Storefront Injection
- Rendered via `ClusterifyAI\ChatBot\Block\ChatbotSnippet` into the `before.body.end` container declared in `view/frontend/layout/default.xml`.
- The block runs a multi-step check:
  1. `$config->isEnabled($storeId)` === true
  2. `$config->isShowOnStorefront($storeId)` === true
  3. `$config->getPublicUuid($storeId)` !== ''
  4. `$pageVisibilityService->isPageAllowed($fullActionName, $storeId, $handles)` === true
- If any check fails, the block outputs an empty string, keeping your storefront HTML and DOM completely untouched.

---

## 7. Enterprise Automated Test Suite

The extension is thoroughly validated by an automated PHPUnit 12 test suite ensuring complete stability, zero regressions, and full compatibility across PHP 8.2 through 8.5:

| Test Suite Domain | Test Cases | Assertions | Coverage Scope |
| :--- | :---: | :---: | :--- |
| **Data Providers & Extraction** | 25 | 108 | CMS, Category, and Product Markdown generation, MSI stock resolution, configurable options, price/availability toggles, currency rate conversion, and 20k knowledge attributes. |
| **Indexers & Mview Operations** | 17 | 56 | Positive ID deduplication, chunking (`BATCH_SIZE = 100`), store iteration, and plan verification short-circuiting. |
| **Queue & Messaging Pipeline** | 20 | 69 | RabbitMQ publishing, non-positive ID validation, batch processing, connection resilience, and consumer dispatching. |
| **Observers & Action Plugins** | 16 | 45 | Save commit events, mass attribute update interception, and store ID sanitization. |
| **CLI Management Suite** | 26 | 88 | Full command coverage (`config:*`, `sync:*`, `test`, `consume`) across table and JSON modes. |
| **Admin UI & Controllers** | 29 | 85 | Status dashboard metrics, Important Information merchant guide, AJAX queue processing, and form modifiers. |
| **Total Test Suite** | **133 tests** | **451 assertions** | **100% Pass Rate (0 failures, 0 errors, 0 notices, 0 deprecations)** |

### Running the Test Suite:
```bash
make magento ARGS="vendor/bin/phpunit app/code/ClusterifyAI/ChatBot/Test/Unit/"
```

---

## 8. System Requirements & Compatibility

- **Magento Edition**: Magento Open Source or Adobe Commerce `2.4.4` through `2.4.9+`
- **PHP Version**: `8.2`, `8.3`, `8.4`, or `8.5`
- **Dependencies**:
  - `magento/framework`: `*`
  - `clusterify/chatbot-sdk`: `^1.0 || dev-main`

---

## 9. License

This module is licensed under the **MIT License**. Copyright (c) 2026 Clusterify AI.
For additional resources and documentation, visit [https://clusterify.ai](https://clusterify.ai).
