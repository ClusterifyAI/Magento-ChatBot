# Clusterify.AI URL Knowledge Base Synchronization Manual

This document details the architectural design, process flow, lifecycle rules, CLI commands, and extension points for the **URL-Based Knowledge Base Synchronization** system in `ClusterifyAI_ChatBot`.

---

## 1. Architecture & Process Flow

To guarantee that synchronizing thousands of catalog and CMS pages **never overloads the Magento database or slows down customer checkout during peak traffic**, the system uses an asynchronous, decoupled pipeline:

```
 ┌────────────────────────┐
 │ Entity Changes         │ (Admin saves, REST/GraphQL APIs, ERP imports, mass updates)
 └───────────┬────────────┘
             │ 1. Direct Commit Observers & Plugins push modified IDs immediately
             │    (ProductSaveObserver, CategorySaveObserver, CmsSaveObserver, ProductActionPlugin)
             │ 2. Database triggers record base changes into Mview changelog tables (_cl)
             ▼
 ┌────────────────────────┐
 │ Dual-Layer Producers   │ FAST NON-BLOCKING PRODUCERS (0 external network wait)
 │ • 3 Dedicated Indexers │ Chunks unique positive entity IDs in batches of 100
 │ • Save Commit Observers│ Publishes lightweight messages to RabbitMQ
 └───────────┬────────────┘
             │ AMQP message dispatch
             ▼
 ┌────────────────────────┐
 │ RabbitMQ Topics/Queues │ BUFFERS WORKLOAD SAFELY
 │ • sync.cms             │ Messages queue up safely without consuming PHP-FPM
 │ • sync.category        │ worker threads or web server resources
 │ • sync.product         │
 └───────────┬────────────┘
             │ Consumed in background
             ▼
 ┌────────────────────────┐
 │ Smart Queue Consumers  │ RATE-LIMIT & QUOTA AWARE WORKERS
 │ • CmsConsumer          │ 1. Extracts metadata via DataProviderPool
 │ • CategoryConsumer     │ 2. Converts HTML to clean, concise Markdown
 │ • ProductConsumer      │ 3. Applies 300ms throttling to stay within 120 req/min
 └───────────┬────────────┘ 4. Catches RateLimitExceededException (dynamic backoff)
             │
             ▼ Live API Calls (upsert or bulkDelete)
 ┌────────────────────────┐
 │ api.clusterify.ai      │ Deep URL-Based Assistant Knowledge Base
 └────────────────────────┘
```

---

## 2. Phase 1 Entity Scope & Markdown Structure

Phase 1 focuses on the primary content and catalog pages visited by e-commerce shoppers:

### A. CMS Pages (`cms`)
- **Extracted By**: `ClusterifyAI\ChatBot\Model\Sync\Provider\CmsDataProvider`
- **Canonical URL Truth**:
  - Home Page: Resolves strictly to the store root (`https://domain.com/`).
  - Standard CMS Pages: Resolves to `https://domain.com/identifier` (omitting `.html` suffixes).
  - All query parameters (`?SID=...`, tracking tags) and fragment hashes (`#...`) are stripped.
- **Content**: Title, store-scoped canonical URL, and sanitized Markdown converted from page HTML.
- **Excluded**: System utility pages (`no-route`, `enable-cookies`).

### B. Category Pages (`category`)
- **Extracted By**: `ClusterifyAI\ChatBot\Model\Sync\Provider\CategoryDataProvider`
- **Canonical URL Truth**:
  - Resolves via `$category->getUrl()` matching `\Magento\Catalog\Block\Category\View::getCanonicalUrl()`.
  - Automatically strips all pagination (`?p=2`), sorting, layered navigation filter parameters (`?color=...`), session parameters, and hashes.
- **Content**: Category name, breadcrumb path, category description, and active subcategories.
- **Excluded**: Root anchor categories without catalog URLs (`level <= 1`).

### C. Product Pages (`product`)
- **Extracted By**: `ClusterifyAI\ChatBot\Model\Sync\Provider\ProductDataProvider`
- **Canonical URL Truth**:
  - Resolves via `$product->getUrlModel()->getUrl($product, ['_ignore_category' => true])`, mirroring the exact logic Magento core uses to generate `<link rel="canonical">` tags in the HTML `<head>` (`\Magento\Catalog\Helper\Product\View::initProductLayout()`).
  - Completely eliminates category path prefixes regardless of store settings (`catalog/seo/product_use_categories`), ensuring every product URL is absolute and canonical (e.g. `https://domain.com/product-url-key.html`).
  - Automatically strips query parameters and fragment hashes.
- **Content**:
  - Name, SKU.
  - Formatted Price (optional, controlled by `sync_product_price`, default: No).
  - Stock Availability (optional, controlled by `sync_product_availability`, default: No).
  - Short description and detailed overview in Markdown (or omitted in favor of dedicated AI knowledge when `custom_knowledge_only = Yes`).
  - Configurable variant options summary (e.g. `Sizes: S, M, L; Colors: Black, Blue`).
- **Excluded**: Child simple products set to `visibility = 1` (*Not Visible Individually*). Their attributes are indexed under the parent configurable product.

### D. Custom AI Knowledge Context (`clusterify_chatbot_knowledge`) & Synchronization Modes
- Available across **Products**, **Categories**, and **CMS Pages**.
- Managed in Admin under the dedicated **`Clusterify AI ChatBot`** fieldset with tailored guidance boxes and max 20,000 character validation.
- Controlled by the setting **`custom_knowledge_only`** (*Sync Custom AI Knowledge (Instead of Core Descriptions)*, default: Yes):
  - **When Yes (Recommended / Default / Fallback)**:
    - **Products**: Synchronizes URL, Title/Name, SKU, Price, Availability, Configurable Options, and `## AI Knowledge & Context`. Standard storefront HTML descriptions are **omitted**. If custom knowledge is blank for a product, it safely falls back to standard descriptions.
    - **Categories**: Synchronizes Title, Subcategories, and `## AI Knowledge & Context`. Standard category descriptions are **omitted**. If blank, falls back to standard descriptions.
    - **CMS Pages**: Synchronizes Title and `## AI Knowledge & Context`. Standard page HTML content is **omitted**. If blank, falls back to standard page content.
  - **When No**:
    - Synchronizes URL, Title/Name, SKU, Price, Availability, and standard storefront HTML descriptions (Summary and Description). If custom knowledge is populated, it is also appended.
  > **⚠️ Operational Notice**: Changing this setting requires running a full reindex across all three indexers (`bin/magento indexer:reindex clusterify_chatbot_cms clusterify_chatbot_category clusterify_chatbot_product`) and processing queue tasks to update existing entries with the new content format.

---

## 3. Entity Lifecycle & State Handling

The synchronization system dynamically handles all three entity lifecycle events:

| Event | Trigger Condition | System Action | Clusterify API Call |
| :--- | :--- | :--- | :--- |
| **Item Added** | New CMS page, category, or product created and enabled. | Extracts metadata and converts HTML to Markdown. | `$client->knowledgeUrl()->upsert(url, content, isEnabled=true)` |
| **Item Updated** | Description, price, stock, or knowledge modified. | Re-generates Markdown with updated details. | `$client->knowledgeUrl()->upsert(url, updatedContent, isEnabled=true)` |
| **Item Disabled** | Status set to Disabled (`status = 2`) or inactive (`is_active = 0`). | Detects disabled flag. Emits delete action to purge URL. | `$client->knowledgeUrl()->bulkDelete(urls: [url])` |
| **Item Hidden** | Product visibility changed to *Not Visible Individually* (`visibility = 1`). | Detects hidden state. Emits delete action to purge URL. | `$client->knowledgeUrl()->bulkDelete(urls: [url])` |
| **Item Deleted** | Entity completely removed from Magento database. | Indexer or mass action dispatches delete action. | `$client->knowledgeUrl()->bulkDelete(urls: [url])` |
| **Out of Stock** | In-stock filter enabled (`in_stock_only = 1`) and product is out of stock. | Detects out-of-stock state. Emits delete action to purge URL. | `$client->knowledgeUrl()->bulkDelete(urls: [url])` |

> **⚠️ Note on "Sync In-Stock Products Only"**: Switching this setting from `No` &rarr; `Yes` in configuration requires running a full product reindex (`bin/magento indexer:reindex clusterify_chatbot_product`) and processing queue tasks to discover and purge previously synchronized out-of-stock items. Because synchronization is asynchronous, product removal progresses as background tasks are consumed.

---

## 4. How to Manage & Trigger Synchronization

### 1. Via Magento Admin Panel
- **Configuration Settings** (*Stores > Configuration > Clusterify.AI > ChatBot & Assistant > URL Knowledge Base Synchronization*):
  - *Enable Knowledge Base Sync* (Master switch, requires Professional Plan).
  - *Synchronize CMS Pages*, *Categories*, *Products* (Yes/No).
  - *(Divider Line)*
  - *Sync Custom AI Knowledge (Instead of Core Descriptions)* (Yes/No, Default: Yes).
  - *Sync Product Price to ChatBot* (Yes/No, Default: No).
  - *Sync Product Availability (Stock Status) to ChatBot* (Yes/No, Default: No).
  - *Sync In-Stock Products Only* (Yes/No, Default: No).
  - *(Divider Line)*
  - *Automated Background Queue Processing (Cron)* (Yes/No, Default: Yes).
  - *Queue Batch Size Per Run* (Default: 50).
- **Index Management** (*CHATBOT > Index Management* or *System > Index Management*):
  - `Clusterify AI: CMS Pages` (`clusterify_chatbot_cms`)
  - `Clusterify AI: Categories` (`clusterify_chatbot_category`)
  - `Clusterify AI: Products` (`clusterify_chatbot_product`)
- **Admin Dashboard On-Demand Drain**:
  - Click **⚡ Process Pending Tasks Now** on the Admin Status Dashboard (*CHATBOT > Dashboard & Status*) to immediately drain up to 50 tasks per queue on-demand.

### 2. Via CLI Commands
```bash
# 1. View Sync Configuration, Indexer Status, and live Clusterify API Quota
bin/magento clusterify:chatbot:sync:status

# 2. Trigger Full Reindex / Sync for all entities (queues to RabbitMQ)
bin/magento clusterify:chatbot:sync:run

# 3. Trigger Selective Sync for specific entities
bin/magento clusterify:chatbot:sync:run --entity=cms
bin/magento clusterify:chatbot:sync:run --entity=category
bin/magento clusterify:chatbot:sync:run --entity=product

# 4. Preview extracted Markdown locally without queueing or API calls
bin/magento clusterify:chatbot:sync:run --entity=product --dry-run

# 5. Process and drain pending queue tasks immediately (clean exit upon completion)
bin/magento clusterify:chatbot:sync:consume
bin/magento clusterify:chatbot:sync:consume --entity=product --limit=100
```

### 3. Queue Consumer Execution Options
Pending RabbitMQ tasks can be processed via:
1. **Automated Magento Cron**: Runs `clusterify_chatbot_sync_process_queue` every minute (`* * * * * bin/magento cron:run`).
2. **Dedicated CLI Command**: `bin/magento clusterify:chatbot:sync:consume`.
3. **On-Demand Admin Dashboard Button**: **⚡ Process Pending Tasks Now**.
4. **Persistent Daemon Workers** (for high-volume sites with supervisor/systemd):
   ```bash
   bin/magento queue:consumers:start clusterify.chatbot.sync.cms
   bin/magento queue:consumers:start clusterify.chatbot.sync.category
   bin/magento queue:consumers:start clusterify.chatbot.sync.product
   ```

---

## 5. Security & Rate-Limiting Safeguards

1. **Unified Short-Circuit Execution Guard (`PlanService::canSyncEntity`)**:
   To prevent code redundancy and eliminate unnecessary database queries or external API calls, all sync operations (Indexers, Queue Consumers, Pre-Deletion Observers, and CLI runners) invoke a single authoritative guard:
   `PlanService::canSyncEntity(string $entityType, ?int $storeId): bool`
   
   This method enforces a **strict 5-step short-circuit evaluation order**:
   - **Step 1 (Zero Network)**: Checks master extension toggle (`isEnabled`). If disabled, exits immediately (`false`).
   - **Step 2 (Zero Network)**: Checks master URL Knowledge Base sync toggle (`isSyncEnabled`). If disabled, exits immediately (`false`).
   - **Step 3 (Zero Network)**: Checks entity-specific toggle (`isCmsSyncEnabled`, `isCategorySyncEnabled`, `isProductSyncEnabled`). If disabled, exits immediately (`false`).
   - **Step 4 (Zero Network)**: Checks that API credentials are non-empty (`getPublicKey`, `getSecretKey`). If missing, exits immediately (`false`).
   - **Step 5 (Plan Gate)**: Only if all 4 local checks pass does it verify Clusterify plan eligibility (`isUrlKnowledgeAllowed`).
   
   *Result*: When the extension or sync is disabled, the system executes **zero database collection queries, zero RabbitMQ publishing, and zero external profile API calls**.

2. **Throttling & Burst Protection**:
   `AbstractSyncConsumer` enforces a 300ms throttle delay between API dispatches to maintain a steady throughput well below the 120 req/min rate limit.
3. **Dynamic Rate-Limit Backoff**:
   If the API responds with HTTP 429 (`RateLimitExceededException`), the consumer dynamically parses `$e->getRetryAfter()`, logs the event, pauses execution, and safely retries.
4. **Starter Plan Safeguard**:
   URL-Based Knowledge Base synchronization is strictly reserved for accounts on the **PROFESSIONAL Plan** or higher. If the current account is on the **STARTER Plan**:
   - In the Magento Admin Panel, all synchronization switches are forced to Disabled (`0`) and locked with a clear upgrade notice.
   - All 3 indexers (`Cms`, `Category`, `Product`) verify plan eligibility via `PlanService::isUrlKnowledgeAllowed()` and **exit immediately (`return;`)**, querying zero database tables and queueing zero messages to RabbitMQ.
   - The CLI command `clusterify:chatbot:sync:run` intercepts execution, outputs an upgrade notice with a direct link to `https://dashboard.clusterify.ai/billing`, and aborts without executing indexers.
   - To avoid redundant API requests, plan verification is cached for 10 minutes in Magento cache storage (`clusterify_plan_cache_<storeId>`).
5. **Dual-Layer Change Tracking & Trigger Safety**:
   `etc/mview.xml` subscribes strictly to base entity tables (`catalog_product_entity`, `catalog_category_entity`, `cms_page`) and inventory tables (`cataloginventory_stock_item`, `cataloginventory_stock_status`) using `entity_id` and `product_id`. Paired with commit observers (`ProductSaveObserver`, `CategorySaveObserver`, `CmsSaveObserver`) and `ProductActionPlugin`, this guarantees complete changelog capture without trigger compilation errors on Adobe Commerce Staging where EAV tables link via `row_id`.
6. **Zero Web Traffic Impact**:
   Web workers serving store visitors never make external HTTP requests during customer checkout or browsing. Storefront environment emulation (`App\Emulation`) is only activated within background consumer workers.

---

## 6. Extendability: Adding Custom Attributes or New Entity Types

The data extraction layer uses a decoupled **Provider Pool** pattern (`ClusterifyAI\ChatBot\Model\Sync\ProviderPool`).

### Adding Custom Attributes to Products
To include additional product attributes (e.g., custom specifications, warranty, materials), inject a custom plugin or extend `ProductDataProvider`:
```php
namespace MyVendor\MyModule\Plugin;

use ClusterifyAI\ChatBot\Model\Sync\Provider\ProductDataProvider;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;

class CustomProductAttributesPlugin
{
    public function afterExtract(ProductDataProvider $subject, ?SyncItem $result, int $entityId, int $storeId): ?SyncItem
    {
        if ($result === null || $result->action === SyncItem::ACTION_DELETE) {
            return $result;
        }

        $customMarkdown = $result->content . "\n- **Care Instructions**: Machine wash cold\n";

        return new SyncItem(
            entityType: $result->entityType,
            entityId: $result->entityId,
            storeId: $result->storeId,
            url: $result->url,
            content: $customMarkdown,
            isEnabled: $result->isEnabled,
            action: $result->action
        );
    }
}
```

### Adding a Brand New Entity Type (e.g. Blog Posts)
1. Implement `DataProviderInterface` (e.g. `BlogPostDataProvider`).
2. Add your provider to `ProviderPool` via `etc/di.xml`:
   ```xml
   <type name="ClusterifyAI\ChatBot\Model\Sync\ProviderPool">
       <arguments>
           <argument name="providers" xsi:type="array">
               <item name="blog" xsi:type="object">MyVendor\MyModule\Model\Sync\Provider\BlogPostDataProvider</item>
           </argument>
       </arguments>
   </type>
   ```

---

## 7. Monitoring & Queue Inspection

To monitor the RabbitMQ queue backlog in this Docker environment:

```bash
# Check queue message counts via RabbitMQ CLI
docker compose --env-file .env -f docker/infrastructure/compose.yml exec rabbitmq rabbitmqctl list_queues | grep clusterify

# Or view through RabbitMQ Web Management UI
# URL: http://127.0.0.1:15672 (User: magento, Pass: magento)
```
