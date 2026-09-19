# Adobe Commerce & Cloud Compatibility Guide

This document details the architectural compatibility, operational behavior, and integration characteristics of the **`ClusterifyAI_ChatBot`** extension on **Adobe Commerce (formerly Magento Enterprise Edition)** and **Adobe Commerce on Cloud (ECE)**.

---

## 1. Executive Compatibility Matrix

| Architectural Subsystem | Compatibility Status | Implementation Details |
| :--- | :---: | :--- |
| **Content Staging & `row_id`** | **Fully Compatible** | Official Repositories & DataPatch APIs resolve `MetadataPool` versioning automatically. |
| **Multi-Source Inventory (MSI)** | **Compatible** | Supports single-source & multi-source stock resolution with `$product->isSalable()`. |
| **Cloud Read-Only Filesystem** | **100% Compliant** | Zero runtime file generation; all artifacts build during the Cloud build phase. |
| **Managed RabbitMQ on Cloud** | **100% Compliant** | Uses Magento standard `connection="amqp"`, routing to Cloud's provisioned cluster. |
| **Fastly CDN & Full Page Cache** | **100% Compliant** | Fully cacheable storefront layouts; zero cache-busting on catalog and CMS pages. |
| **Split Database Architecture** | **100% Compliant** | Connects via `resource="default"` and standard ORM connection routers. |
| **Customer Segments** | **100% Compliant** | Visibility evaluated via `fullActionName`, independent of customer segment rules. |

---

## 2. Content Staging & `row_id` Architecture (`Magento_Staging`)

### Architectural Difference
In Adobe Commerce, the `Magento_Staging` module adds campaign scheduling and versioning to Products, Categories, CMS Pages, and CMS Blocks:
- **Magento Open Source (CE)**: Primary key is `entity_id` (or `page_id`). EAV value tables (`_varchar`, `_int`, `_text`, `_decimal`) store a foreign key pointing directly to `entity_id`.
- **Adobe Commerce (EE)**: Primary key is `row_id`. EAV tables link to `row_id`. The `entity_id` column remains, but represents the persistent logical entity across multiple scheduled campaign versions (`created_in` / `updated_in`).

### How `ClusterifyAI_ChatBot` Handles Staging:

1. **EAV Attributes (`Setup/Patch/Data/AddChatbotKnowledgeAttributes.php`)**:
   - The data patch uses Magento's official `EavSetupFactory::create()->addAttribute(Product::ENTITY, ...)`.
   - On Adobe Commerce, `EavSetup` automatically inspects `\Magento\Framework\EntityManager\MetadataPool` and binds the attribute to `row_id` on Enterprise and `entity_id` on Open Source.
   - **Result**: Zero raw SQL or hardcoded column mappings; attributes install cleanly across all editions.

2. **CMS Page Schema (`etc/db_schema.xml`)**:
   - The declarative schema adds column `clusterify_chatbot_knowledge` (`mediumtext`, nullable) to table `cms_page`.
   - Because this is a standalone nullable column without foreign keys, MariaDB adds it cleanly on both CE and EE.
   - In Adobe Commerce, when a staging campaign clones a CMS page row into a new `row_id`, our knowledge column is copied along with the rest of the page data.

3. **Entity Loading in Data Providers (`ProductDataProvider`, `CategoryDataProvider`, `CmsDataProvider`)**:
   - All three providers load entities through Magento's official repository layer:
     - `ProductRepositoryInterface::getById($id, false, $storeId)`
     - `CategoryRepositoryInterface::get($id, $storeId)`
     - `PageRepositoryInterface::getById($id)`
   - In Adobe Commerce, these repositories are plugged by `Magento_CatalogStaging` and `Magento_CmsStaging`, which automatically resolve the currently active staged version via `MetadataPool`.
   - **Result**: The extension never executes raw SQL `SELECT * FROM ... WHERE entity_id = ...`, preventing stale or inactive campaign data from being synchronized.

4. **Dual-Layer Change Tracking (Mview Triggers + Save Commit Observers)**:
   - **Mview Base Subscriptions (`etc/mview.xml`)**: Subscribes strictly to base entity tables (`catalog_product_entity`, `catalog_category_entity`, `cms_page`) and inventory tables (`cataloginventory_stock_item`, `cataloginventory_stock_status`) using `entity_id` (and `page_id`/`product_id`). This completely avoids MySQL trigger compilation errors (`Unknown column 'entity_id' in 'NEW'`) that occur on Adobe Commerce when subscribing to EAV value tables where foreign keys point to `row_id`.
   - **Save Commit Observers & Plugins (`etc/events.xml` & `etc/di.xml`)**: To guarantee that attribute updates (names, descriptions, custom AI training context `clusterify_chatbot_knowledge`, pricing, and status) are immediately captured, the extension pairs Mview with:
     - `ProductSaveObserver` on `catalog_product_save_commit_after`
     - `CategorySaveObserver` on `catalog_category_save_commit_after`
     - `CmsSaveObserver` on `cms_page_save_commit_after`
     - `ProductActionPlugin` on `Magento\Catalog\Model\Product\Action::updateAttributes` (mass attribute updates)
   - **Result**: Zero MySQL trigger conflicts on Adobe Commerce Staging, zero duplicate trigger firings on Magento Open Source, and 100% changelog capture across both editions.

---

## 3. Multi-Source Inventory (MSI) & Stock Availability

### Architectural Difference
Adobe Commerce / Cloud almost universally runs with **Multi-Source Inventory (MSI)** enabled (`Magento_Inventory*` modules), where multiple physical sources (warehouses, retail stores) are grouped into custom Stocks assigned to specific Website sales channels.

### How `ClusterifyAI_ChatBot` Handles Stock:

1. **Stock Resolution in `ProductDataProvider.php`**:
   - The provider inspects `$product->isSalable()` (with fallback to `CatalogInventory` legacy stock item).
   - In MSI environments, `$product->isSalable()` delegates to MSI's composite salable resolver (`IsProductSalableInterface`), evaluating the product's true availability against the website's assigned stock channel.

2. **Out-of-Stock Quota Optimization (`in_stock_only`)**:
   - When **Sync In-Stock Products Only** is enabled, any product evaluated as out-of-stock emits `SyncItem::ACTION_DELETE`.
   - Smart consumers execute `$client->knowledgeUrl()->bulkDelete([url])`, purging unavailable products from Clusterify to preserve the merchant's URL quota.
   - **Switching from No &rarr; Yes**: Toggling this switch in configuration requires running a product reindex (`bin/magento indexer:reindex clusterify_chatbot_product`) and processing the queue to discover and purge previously synchronized out-of-stock items.

---

## 4. Adobe Commerce Cloud (ECE) Read-Only Filesystem

### Architectural Constraint
Adobe Commerce on Cloud (ECE) enforces an **immutable, read-only filesystem at runtime**:
- Only `/var`, `/tmp`, `/pub/media`, and `/pub/static` are writable.
- Any attempt to write code, templates, or cache files under `/app`, `/vendor`, or `/generated` during web requests or cron jobs will trigger a filesystem fatal error.

### How `ClusterifyAI_ChatBot` Complies (100% Compliant):
- **Zero Runtime Code Generation**: The extension does not generate proxies, factories, or interceptors at runtime. All compilation occurs during the Cloud build phase (`setup:di:compile`).
- **Zero Runtime File Creation**: The extension does not create temporary files or store caches on disk.
- **Database Configuration**: All settings are read from and written to `core_config_data`.
- **Centralized Logging**: All logging routes through PSR-3 `LoggerInterface`, writing directly to `/var/log/system.log` and `/var/log/debug.log`.
- **In-Memory & Redis Caching**: Plan metadata and quota stats use Magento's `CacheInterface`, backed by Redis/Valkey on Cloud.

---

## 5. Cloud Infrastructure & Service Integration

### 1. Managed RabbitMQ Service
- Adobe Commerce Cloud provisions dedicated, high-availability RabbitMQ clusters.
- All topologies (`etc/queue_topology.xml`), topics (`etc/communication.xml`), and consumers (`etc/queue_consumer.xml`) use Magento's standard `connection="amqp"`.
- On Cloud, `connection="amqp"` maps automatically to Cloud's provisioned RabbitMQ service without custom connection parameters.

### 2. Queue Consumer Execution on Cloud
- Cloud environments utilize `cron_consumers_runner` configured in `env.php` or `.magento.env.yaml`.
- The extension supports four execution workflows:
  1. **Automated Magento Cron**: Runs `clusterify_chatbot_sync_process_queue` every minute via standard Cloud cron.
  2. **Dedicated CLI**: `bin/magento clusterify:chatbot:sync:consume` (ideal for post-deployment hooks and manual drains).
  3. **On-Demand Admin Button**: **⚡ Process Pending Tasks Now** on the Admin Status Dashboard (*CHATBOT > Dashboard & Status*).
  4. **Supervisor Workers**: Continuous background daemon processes via `bin/magento queue:consumers:start`.

### 3. Fastly CDN & Full Page Caching (FPC)
- Adobe Commerce Cloud includes Fastly CDN as the default reverse proxy and edge cache.
- The storefront chatbot snippet (`view/frontend/layout/default.xml`) is injected into `before.body.end` using `ChatbotSnippet` block.
- **Cache Friendly**: The block does not disable full page cache (it does not declare `cacheable="false"`).
- The HTML loader snippet references the static Public UUID, and the assistant bundle loads asynchronously (`defer`/`async`), ensuring **100% Fastly cache hit rates** on catalog and CMS pages.

### 4. Split Database Support
- Adobe Commerce supports split database architecture (separate connections for checkout, order management, and catalog).
- The extension declares `resource="default"` in `db_schema.xml`, ensuring all tables map to the primary catalog/core database without routing conflicts.

---

## 6. Cloud Deployment Checklist

When deploying `ClusterifyAI_ChatBot` to Adobe Commerce Cloud:

1. **Build Phase (`.magento.app.yaml`)**:
   Ensure standard compilation steps are included in your build hook:
   ```yaml
   hooks:
     build: |
       set -e
       php ./bin/magento setup:di:compile
   ```
2. **Deploy Phase**:
   Ensure schema updates run during deployment:
   ```yaml
   hooks:
     deploy: |
       set -e
       php ./bin/magento setup:upgrade --keep-generated
       php ./bin/magento cache:flush
   ```
3. **Post-Deployment Knowledge Sync (Optional)**:
   To populate the Knowledge Base immediately after deploying:
   ```bash
   php ./bin/magento clusterify:chatbot:sync:run --entity=all
   php ./bin/magento clusterify:chatbot:sync:consume --limit=200
   ```
