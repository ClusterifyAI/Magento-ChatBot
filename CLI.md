# ClusterifyAI_ChatBot CLI Reference & Automation Manual

This document provides a comprehensive command-line reference for managing the **Clusterify.AI ChatBot & Assistant** Magento 2 extension.

---

## 1. Purpose & Overview

The `clusterify:chatbot:config:*` CLI suite allows developers, DevOps pipelines, and AI agents to manage 100% of the extension's configuration and operational checks from the terminal without accessing the Magento Admin GUI.

### Key Capabilities
- **Full Configuration Parity**: Read and update all module settings (Master Toggle, Storefront Visibility, Public UUID, API Credentials, Base URL, Page Visibility).
- **Multi-Scope Awareness**: Seamlessly inspect and override settings across **Default Config**, **Website**, and **Store View** scopes.
- **Enterprise Security**:
  - API Secret Keys (`sk_live_...`) are automatically encrypted in `core_config_data` via Magento's cryptographic vault (`Magento\Framework\Encryption\EncryptorInterface`).
  - Terminal output masks sensitive keys by default (e.g. `sk_live_••••••••••••3d2e`).
- **Live SDK Ping**: Validate API keys against Clusterify's live endpoints using the official PHP SDK (`clusterify/chatbot-sdk`) directly from the terminal.
- **AI Agent & Scripting Support**: All read and test commands support `--format=json` with clean Linux exit codes (`0` for success, `1` for error), making automation and verification trivial.
- **Automatic Cache Invalidation**: Writing any configuration value automatically cleans the `config` cache type (`Magento\Framework\App\Cache\TypeListInterface`).

---

## 2. Execution Syntax

In this local Docker environment, run commands through `make` or directly through the `./scripts/magento` wrapper:

```bash
# Using Make
make magento ARGS="clusterify:chatbot:config:<action> [arguments] [options]"

# Direct Script Execution (No outer quotes required)
./scripts/magento clusterify:chatbot:config:<action> [arguments] [options]
```

---

## 3. Command Catalog & Examples

### 1. `clusterify:chatbot:config:show`

Displays the current configuration values in a formatted table or JSON readout.

#### Options:
- `--scope, -s`: `default` (default), `website`, or `store`
- `--scope-code, -c`: Code of the website (e.g. `base`) or store view (e.g. `default`, `french`)
- `--format, -f`: `table` (default) or `json`
- `--show-secrets`: Reveals the unmasked API Secret Key in output

#### Examples:
```bash
# 1. View Default Global Configuration
make magento ARGS="clusterify:chatbot:config:show"

# 2. View Configuration for a specific Store View
make magento ARGS="clusterify:chatbot:config:show --scope=store --scope-code=default"

# 3. View Configuration for a specific Website
make magento ARGS="clusterify:chatbot:config:show --scope=website --scope-code=base"

# 4. JSON Output for AI Agent Automation
make magento ARGS="clusterify:chatbot:config:show --format=json"

# 5. View with Unmasked API Secret Key
make magento ARGS="clusterify:chatbot:config:show --show-secrets"
```

---

### 2. `clusterify:chatbot:config:set`

Updates a specific configuration field, validates the input format, encrypts sensitive data, and automatically flushes the config cache.

#### Arguments:
- `field`: The configuration key to update:
  - `enabled`: Master switch (`1/0`, `yes/no`, `true/false`, `enable/disable`)
  - `show_on_storefront`: Storefront presentation switch (`1/0`, `yes/no`)
  - `public_uuid`: Chatbot Public UUID string
  - `public_key`: API Public Key (`pk_live_...`)
  - `secret_key`: API Secret Key (`sk_live_...`)
  - `api_base_url`: API Endpoint URL (default: `https://api.clusterify.ai`)
  - `sync_enabled`: Master URL Knowledge Base sync switch (`1/0`, `yes/no`)
  - `sync_cms`: CMS page sync toggle (`1/0`, `yes/no`)
  - `sync_categories`: Category page sync toggle (`1/0`, `yes/no`)
  - `sync_products`: Product page sync toggle (`1/0`, `yes/no`)
  - `custom_knowledge_only`: Prioritize custom AI knowledge attribute over core descriptions (`1/0`, `yes/no`)
  - `sync_product_price`: Synchronize product formatted price into knowledge base (`1/0`, `yes/no`, default: `0`)
  - `sync_product_availability`: Synchronize product stock availability into knowledge base (`1/0`, `yes/no`, default: `0`)
  - `sync_in_stock_only`: Only sync in-stock products (`1/0`, `yes/no`)
- `value`: The new value to set.

#### Options:
- `--scope, -s`: `default` (default), `website`, or `store`
- `--scope-code, -c`: Code of target website or store view

#### Examples:
```bash
# 1. Enable the Extension globally
make magento ARGS="clusterify:chatbot:config:set enabled 1"

# 2. Enable Storefront Display
make magento ARGS="clusterify:chatbot:config:set show_on_storefront 1"

# 3. Set the ChatBot Public UUID (Default Scope)
make magento ARGS="clusterify:chatbot:config:set public_uuid <YOUR_CHATBOT_PUBLIC_UUID>"

# 4. Override Public UUID for a specific Store View (e.g. Spanish Storefront)
make magento ARGS="clusterify:chatbot:config:set public_uuid <SPANISH_STORE_PUBLIC_UUID> --scope=store --scope-code=spanish"

# 5. Set API Authorization Keys
make magento ARGS="clusterify:chatbot:config:set public_key <YOUR_API_PUBLIC_KEY>"
make magento ARGS="clusterify:chatbot:config:set secret_key <YOUR_API_SECRET_KEY>"

# 6. Override API Base URL (Staging / Testing)
make magento ARGS="clusterify:chatbot:config:set api_base_url https://api.clusterify.ai"

# 7. Enable URL Knowledge Base Synchronization
make magento ARGS="clusterify:chatbot:config:set sync_enabled 1"

# 8. Configure Entity Sync Toggles
make magento ARGS="clusterify:chatbot:config:set sync_cms 1"
make magento ARGS="clusterify:chatbot:config:set sync_categories 1"
make magento ARGS="clusterify:chatbot:config:set sync_products 1"
make magento ARGS="clusterify:chatbot:config:set sync_in_stock_only 1"
```

---

### 3. `clusterify:chatbot:config:test` *(Alias: `clusterify:chatbot:test`)*

Tests network connectivity and authenticates credentials live against Clusterify.AI using the PHP SDK's `$client->ping()` resource.

#### Options:
- `--scope, -s`: Test credentials saved in `default`, `website`, or `store`
- `--scope-code, -c`: Scope code
- `--public-key`: Override Public Key for an ad-hoc test without saving
- `--secret-key`: Override Secret Key for an ad-hoc test without saving
- `--api-base-url`: Override API endpoint URL
- `--format, -f`: `text` (default) or `json`

#### Examples:
```bash
# 1. Test connection using currently saved configuration
make magento ARGS="clusterify:chatbot:config:test"

# 2. Test connection for a specific Store View
make magento ARGS="clusterify:chatbot:config:test --scope=store --scope-code=default"

# 3. Test new credentials before saving to the database
make magento ARGS="clusterify:chatbot:config:test --public-key=<YOUR_PUBLIC_KEY> --secret-key=<YOUR_SECRET_KEY>"

# 4. JSON output for CI/CD pipeline healthchecks
make magento ARGS="clusterify:chatbot:config:test --format=json"
```

---

### 4. `clusterify:chatbot:config:pagetype-visibility:list`

Lists all dynamically discovered page types across all installed modules, displaying their active visibility state for the specified scope.

#### Options:
- `--scope, -s`: `default` (default), `website`, or `store`
- `--scope-code, -c`: Scope code
- `--filter`: Filter page types by keyword or handle (e.g. `cart`, `product`, `checkout`)
- `--category`: Filter by category (`landing`, `product`, `checkout`, `customer`, `search`, `custom`)
- `--format, -f`: `table` (default) or `json`

#### Examples:
```bash
# 1. List all page types across all categories
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:list"

# 2. Filter page types matching "cart" or "checkout"
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:list --filter=cart"

# 3. View only Product & Catalog pages
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:list --category=product"

# 4. View Page Visibility for a specific Store View in JSON format
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:list --scope=store --scope-code=default --format=json"
```

---

### 5. `clusterify:chatbot:config:pagetype-visibility:set`

Enables or disables ChatBot widget visibility for a specific page type handle, an entire category of pages, or all pages at once.

#### Arguments:
- `pagetype`: Page type handle (e.g. `checkout_cart_index`), category key (when using `--by-category`), or `all`
- `state`: Desired state (`enable`/`disable`, `1`/`0`, `yes`/`no`)

#### Options:
- `--scope, -s`: `default` (default), `website`, or `store`
- `--scope-code, -c`: Scope code
- `--by-category`: Indicates the argument is a category (`landing`, `product`, `checkout`, `customer`, `search`, `custom`)
- `--all`: Applies the state to all page types

#### Examples:
```bash
# 1. Enable ChatBot on the Shopping Cart page
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:set checkout_cart_index enable"

# 2. Disable ChatBot on Product Detail pages for a specific Store View
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:set catalog_product_view disable --scope=store --scope-code=default"

# 3. Enable ChatBot across all Customer Account pages
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:set customer enable --by-category"

# 4. Disable ChatBot across all Cart & Checkout pages
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:set checkout disable --by-category"

# 5. Disable on ALL pages simultaneously
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:set all disable"
```

---

### 6. `clusterify:chatbot:config:pagetype-visibility:reset`

Resets page visibility rules back to default safe behaviors (Cart & Checkout pages disabled, all other pages enabled) or clears scope overrides.

#### Options:
- `--scope, -s`: `default` (default), `website`, or `store`
- `--scope-code, -c`: Scope code
- `--clear-override`: Deletes the custom scope override entirely from `core_config_data` to restore inheritance from the parent scope.

#### Examples:
```bash
# 1. Reset all page types to defaults (Checkout OFF, others ON)
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:reset"

# 2. Reset page types for a specific store view
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:reset --scope=store --scope-code=default"

# 3. Clear store-level override completely so it inherits from Default Config
make magento ARGS="clusterify:chatbot:config:pagetype-visibility:reset --scope=store --scope-code=default --clear-override"
```

---

### 7. `clusterify:chatbot:sync:status`

Displays active URL Knowledge Base synchronization settings, the state of the 3 dedicated indexers, and live Clusterify API URL quota usage.

#### Options:
- `--format, -f`: `table` (default) or `json`

#### Examples:
```bash
# 1. View Sync Status & Quota in Formatted Table
make magento ARGS="clusterify:chatbot:sync:status"

# 2. JSON Output for AI Agent Automation
make magento ARGS="clusterify:chatbot:sync:status --format=json"
```

---

### 8. `clusterify:chatbot:sync:run`

Triggers reindexing for CMS, Category, or Product sync indexers, publishing entity messages into the RabbitMQ queue.  
*Safety Guard*: If the current Clusterify account is on the **STARTER Plan**, this command immediately aborts with a link to the billing upgrade page to conserve resources.

#### Options:
- `--entity, -e`: Target entity to sync (`cms`, `category`, `product`, or `all` [default])
- `--dry-run`: Preview extracted Markdown content and URLs in the terminal without queueing to RabbitMQ or calling the API

#### Examples:
```bash
# 1. Queue all entities to RabbitMQ (CMS, Categories, and Products)
make magento ARGS="clusterify:chatbot:sync:run"

# 2. Queue CMS Pages only
make magento ARGS="clusterify:chatbot:sync:run --entity=cms"

# 3. Queue Products only
make magento ARGS="clusterify:chatbot:sync:run --entity=product"

# 4. Queue Categories only
make magento ARGS="clusterify:chatbot:sync:run --entity=category"

# 5. Preview extracted Markdown locally without queueing or API calls
make magento ARGS="clusterify:chatbot:sync:run --entity=cms --dry-run"
```

---

### 9. `clusterify:chatbot:sync:consume`

Immediately processes and drains pending RabbitMQ tasks into Clusterify.AI, exiting cleanly as soon as the batch is processed. Unlike standard daemon workers, this command will not block indefinitely waiting for future messages.

#### Options:
- `--entity, -e`: Target queue to process (`cms`, `category`, `product`, or `all` [default])
- `--limit, -l`: Maximum number of messages to process per queue (default: `50`)

#### Examples:
```bash
# 1. Drain up to 50 pending messages across all queues and exit
make magento ARGS="clusterify:chatbot:sync:consume"

# 2. Drain up to 100 messages from the Products queue specifically
make magento ARGS="clusterify:chatbot:sync:consume --entity=product --limit=100"

# 3. Drain pending CMS page tasks
make magento ARGS="clusterify:chatbot:sync:consume --entity=cms"
```

---

### 10. Background Consumer Execution Options

You can process pending RabbitMQ synchronization tasks using any of the following methods:

1. **Dedicated On-Demand CLI (Recommended for Development / Manual Runs)**:
   ```bash
   make magento ARGS="clusterify:chatbot:sync:consume"
   ```
2. **Automated Magento Background Cron (Recommended for Production)**:
   Runs automatically every minute via Magento's standard cron runner:
   ```bash
   make magento ARGS="cron:run"
   ```
   *(Executes the `clusterify_chatbot_sync_process_queue` job according to the batch size configured in Stores > Configuration).*
3. **On-Demand Admin Button**:
   Click **⚡ Process Pending Tasks Now** on the Admin Status Dashboard (*CHATBOT > Dashboard & Status*).
4. **Persistent Daemon Workers (Supervisor / Systemd)**:
   ```bash
   make magento ARGS="queue:consumers:start clusterify.chatbot.sync.cms"
   make magento ARGS="queue:consumers:start clusterify.chatbot.sync.category"
   make magento ARGS="queue:consumers:start clusterify.chatbot.sync.product"
   ```

---

## 4. AI Agent Guidelines for CLI Automation

When writing scripts or delegating tasks to AI agents:

1. **Prefer CLI over Admin GUI**: AI agents should use `make magento ARGS="clusterify:chatbot:config:..."` for all configuration changes.
2. **Inspect with JSON**: Use `--format=json` to parse configuration and page visibility programmatically:
   ```bash
   make magento ARGS="clusterify:chatbot:config:show --format=json"
   ```
3. **Verify Connection After Changing Keys**: Always verify API keys with `clusterify:chatbot:config:test` after running `config:set public_key` or `config:set secret_key`.
4. **Scope Safety**: Always specify `--scope=store --scope-code=<code_or_id>` when setting values intended for a specific storefront.
