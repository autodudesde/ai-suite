# TYPO3 AI Suite

The AI Suite optimizes the workflow of project managers, agencies and freelancers by using the latest AI technologies. It seamlessly integrates a wide variety of AI providers and the AutoDudes open-source models directly into the TYPO3 backend, so editors generate, translate and optimize content without ever leaving TYPO3.

It covers the everyday editorial chores that scale badly by hand: image and page metadata (single records and in bulk), content translation (including **Easy Language** for accessible copy), content creation and rewriting, AI image generation, and whole page-tree generation. Long-running jobs run asynchronously through the built-in **TaskEngine**, and access is gated per feature and per AI model through TYPO3 backend-group permissions.

> 💬 Join us on Slack: [#ai-suite on TYPO3 Slack](https://typo3.slack.com/archives/C05QAN1KNVD) to follow development, raise issues or shape the roadmap.

> **Version-agnostic README.** AI Suite is maintained as parallel branches for the actively supported TYPO3 majors. This file intentionally avoids hardcoded version numbers so it down-merges unchanged. For the exact supported TYPO3 and PHP versions of the branch you are on, read `composer.json` (`require`) and `ext_emconf.php` (`constraints.depends`).

## What you can do with it

- 🏷️ **Generate page metadata** (SEO title, description, keywords, Open Graph / Twitter cards) for a single page or in bulk over a whole page subtree.
- 🖼️ **Generate file metadata** (alternative text, title, description) for images, single file or for every file in a folder. Optionally auto-generate on upload.
- ✍️ **Create & optimize content** straight in the page module: full content elements from a prompt, plus rewrite / shorten / simplify on existing copy.
- 🌍 **Translate anything** single records, whole pages, file metadata or list selections, with DeepL glossary sync and FlexForm field translation. Includes **Easy Language** rewrites for accessible content.
- 🧱 **Generate complete page trees** from a single prompt, with page metadata and content inside the new pages.
- 🎨 **Generate AI images** straight into FAL, ready to be referenced as a real `sys_file`.
- 📰 **Generate news records** (when EXT:news is present) via dedicated field controls.
- 🧠 **CKEditor AI plugins** an AI assistant and an Easy Language assistant right inside the RTE.
- 🧩 **Reusable prompt templates & global instructions** capture house style once and apply it to every generation.
- ⏱️ **Background processing via TaskEngine** long-running batches get an async task; results come back as suggestions you review and apply.
- ⚙️ **WorkflowManager** run mass actions (bulk metadata / translation) across many records at once.
- 🖥️ **CLI & Scheduler** kick off and process workflows headless for fully automated pipelines.
- 🔐 **Per-feature and per-model permissions** every capability and every AI model is gated by TYPO3 backend-group flags.

## AI capabilities & available models

The set of models offered to a given backend user depends on the AI-model permissions configured on their backend group (see [Backend-group permissions](#backend-group-permissions)) and on what your license / API keys unlock. Model names below use the labels shown in the backend.

| Capability | Where in the backend | Models |
|---|---|---|
| **Page metadata** (SEO, OG, Twitter) | SEO field controls, WorkflowManager, TaskEngine | OpenAI (ChatGPT), Anthropic/Claude, Mittwald (Ministral), Meta Llama-3.3 (70B-Instruct) |
| **File / image metadata** (alt, title, description) | File list, file metadata field controls, auto-on-upload | OpenAI Vision, Mittwald Vision, plus the configured metadata model |
| **Content generation** (tt_content) | Page module content wizard | OpenAI (ChatGPT), Anthropic/Claude, Mittwald (Ministral), Meta Llama-3.3 (70B-Instruct) |
| **Page-tree generation** | Backend module page-tree wizard | OpenAI (ChatGPT), Anthropic/Claude, Mittwald (Ministral), Meta Llama-3.3 (70B-Instruct) |
| **Translation** (records, pages, file metadata) | Localization wizard, list wizard, file metadata | DeepL, Google Translate, OpenAI (ChatGPT), Anthropic/Claude, Mittwald (Ministral) |
| **Easy Language** (accessibility rewrites) | CKEditor Easy Language plugin, translation flow | OpenAI (ChatGPT), Anthropic/Claude, Meta Llama-3.3 (70B-Instruct) configurable via `easyLanguageLibrary` |
| **DeepL glossary sync** | Translation settings | DeepL |
| **Image generation** | Image generation wizard | GPT Image (OpenAI), Midjourney, Flux |

You can run these models through the AutoDudes credit system (a single API key, see [Licensing & credits](#licensing--credits)) or bring your own provider keys (BYOK).

## Backend module

AI Suite adds a dedicated backend module whose dashboard gives access to the main areas (each gated by its own permission):

| Area | Purpose |
|---|---|
| **WorkflowManager** | Run tasks instantly, e.g. metadata generation for pages and/or images as well as translations. |
| **TaskEngine** | Monitor and manage background tasks, e.g. the processing of bulk metadata generation or translations. |
| **Global instructions** | Project-wide instructions automatically prepended to every prompt. |
| **Prompt templates** | Reusable, editable prompt templates for recurring generation tasks. |
| **Page tree** | Generate a page tree (optionally including page metadata and content) from a prompt. |
| **Agencies** | Special-purpose helpers, e.g. translating an XLF file or importing page content from another system. |
| **Settings** | Global extension configuration, e.g. AI Suite API Key, GDPR mode or directory protection. |

A toolbar item shows the remaining credits at a glance (**Pack** = one-off credits, **Plan** = subscription credits).

## Requirements

AI Suite is maintained as one branch per TYPO3 major. Pick the AI Suite line that matches your TYPO3 version:

| AI Suite | TYPO3 | PHP |
|---|---|---|
| **14.x** | 14.0.0 to 14.9.99 | 8.2 to 8.4 |
| **13.x** | 13.4.1 to 13.4.99 | 8.2 to 8.4 |
| **12.x** | 12.4.11 to 12.4.99 | 8.1 to 8.4 |

AI Suite also needs:

- TYPO3 system extensions: `backend`, `filelist`, `fluid`, `rte-ckeditor`, `seo`.
- An **AI Suite API key** (from [autodudes.de](https://www.autodudes.de/)) for the credit-based providers, **or** your own provider keys (BYOK).

**Suggested**

- `b13/container` content elements inside containers are handled as first-class records.
- `typo3/cms-scheduler` required for headless / scheduled background-task processing (see [CLI & Scheduler](#cli--scheduler)).

**Conflicts**

- `passionweb/ai-seo-helper` superseded by AI Suite's metadata features.

## Installation

```bash
composer require autodudes/ai-suite
vendor/bin/typo3 extension:setup
```

Or install via the **TYPO3 Extension Repository (TER)**, then run the database analyzer.

**Quickstart**

1. [Install the extension](https://www.autodudes.de/documentation/ai-suite/en/for-administrators/installation) via Composer or TER.
2. Get an API key at [autodudes.de](https://www.autodudes.de/) (or prepare your own provider keys for BYOK).
3. Add the key under **Backend → AI Suite → Settings** (or **Admin Tools → Settings → Extension Configuration → `ai_suite`**).
4. Update the database under **Admin Tools → Maintenance → Analyze Database Structure**.
5. Grant the relevant AI Suite feature and model permissions on the backend groups that should use it.
6. Enjoy the AI Suite.

## Licensing & credits

AI Suite can drive AI providers two ways:

- **AutoDudes credits (single key).** One AI Suite API key unlocks every integrated provider; usage is billed against credits. Credits come as **Packs** (one-off, no subscription) and **Plans** (credits in a monthly/yearly subscription). The backend toolbar and dashboard show the remaining balance.
- **Bring your own keys (BYOK).** Configure your own provider keys (OpenAI, Anthropic, DeepL, Google Translate, Midjourney, Flux) in the extension configuration and call the providers directly under your own contracts.

See [autodudes.de](https://www.autodudes.de/) for current packages and pricing.

## Configuration

All settings live under **Admin Tools → Settings → Extension Configuration → `ai_suite`** and are also reachable from the backend module's **Settings** area (when the `Global Settings` permission is granted). Sensitive values (API keys, passwords) are masked in the module UI.

### AI Suite API

| Setting | Default | Description |
|---|---|---|
| `aiSuiteApiKey` | _(empty)_ | Your AutoDudes AI Suite license key. Required for the credit-based providers. |
| `aiSuiteServer` | `https://api.autodudes.de/` | AI Suite server endpoint. Change only for self-hosted / proxied setups. |

### Translation

| Setting | Default | Description |
|---|---|---|
| `translateFlexFormFields` | `1` | Also translate FlexForm field values, not just regular columns. |
| `updateDeeplGlossaries` | `1` | Keep DeepL glossaries in sync from the AI Suite glossary table. |
| `easyLanguageLibrary` | `ChatGPT` | Model used for Easy Language rewrites (`ChatGPT`, `Anthropic`, or `AiSuiteTextUltimate` / Meta Llama-3.3). |
| `disableTranslationFunctionality` | `0` | Master switch that unregisters the translation decorators (`LocalizationController`, `DatabaseRecordList`). |

### Metadata

| Setting | Default | Description |
|---|---|---|
| `metadataSuggestionCount` | `5` | Number of suggestions generated per metadata request (1 to 5). |
| `metadataAutogenerateTitle` | _(off)_ | Auto-generate image titles on upload. |
| `metadataAutogenerateAlternative` | _(off)_ | Auto-generate alternative text on upload. |
| `metadataAutogenerateModel` | `Vision` | Model used for auto-generated file metadata. |
| `metadataAutogenerateApproach` | `taskEngine` | `taskEngine` queues suggestions for review; `saveDirectly` writes the first suggestion immediately. |
| `minPlausiblePageContentLength` | `100` | Minimum rendered-content length (chars) below which a page is treated as too thin for a meaningful abstract. |

### Media

| Setting | Default | Description |
|---|---|---|
| `mediaStorageFolder` | _(empty)_ | Target FAL folder for AI-generated images. Empty = default storage. |

### GDPR

| Setting | Default | Description |
|---|---|---|
| `forceGdpa` | `0` | Enforce GDPR mode for provider routing / data handling. |

### Background Tasks

| Setting | Default | Description |
|---|---|---|
| `maxTasks` | `50` | Maximum number of background tasks processed per run. |

### HTTP Basic Authentication (directory protection)

| Setting | Default | Description |
|---|---|---|
| `basicAuth.enable` | `0` | Send HTTP Basic Auth credentials with outbound requests (for sites behind directory protection). |
| `basicAuth.user` | _(empty)_ | Basic Auth username. |
| `basicAuth.pass` | _(empty)_ | Basic Auth password. |

### Bring your own keys

| Setting | Default | Description |
|---|---|---|
| `openAiApiKey` | _(empty)_ | Your OpenAI API key. |
| `anthropicApiKey` | _(empty)_ | Your Anthropic API key. |
| `googleTranslateApiKey` | _(empty)_ | Your Google Translate API key. |
| `deeplApiKey` | _(empty)_ | Your DeepL API key. |
| `deeplApiMode` | _(off)_ | Use the free DeepL API endpoint instead of the pro endpoint. |
| `midjourneyApiKey` | _(empty)_ | Your Midjourney API key. |
| `midjourneyId` | _(empty)_ | Your Midjourney account ID. |
| `fluxApiKey` | _(empty)_ | Your Flux API key. |
| `fluxBaseUrl` | _(empty)_ | Custom Flux base URL. |

## Backend-group permissions

AI Suite gates access on two independent axes that both apply on every action: **per feature** and **per AI model**. Both are TYPO3 custom permission options set on each backend group (`be_groups`) under the **AI Suite Features** (`tx_aisuite_features`) and **AI Suite Models** (`tx_aisuite_models`) sections. A user needs the feature flag for the action **and** the model flag for the model that action uses.

### Features (`tx_aisuite_features:<flag>`)

| Flag | Unlocks |
|---|---|
| `enable_metadata_generation` | Page and file metadata generation |
| `enable_content_element_generation` | Content element generation in the page module |
| `enable_pages_generation` | Page-tree generation |
| `enable_news_generation` | News record generation (EXT:news) |
| `enable_image_generation` | AI image generation into FAL |
| `enable_translation` | Translation features (baseline) |
| `enable_translation_whole_page` | Translate an entire page at once |
| `enable_translation_sys_file_metadata` | Translate file metadata |
| `enable_translation_list_wizard` | Translation wizard in record lists |
| `enable_translation_deepl_sync` | DeepL glossary synchronization |
| `enable_massaction_generation` | WorkflowManager mass actions |
| `enable_background_task_handling` | TaskEngine background-task management |
| `enable_cli_workflow_execution` | CLI / Scheduler-based workflow execution |
| `enable_rte_aiplugin` | CKEditor AI assistant plugin |
| `enable_rte_aieasylanguageplugin` | CKEditor Easy Language plugin |
| `enable_global_instructions_button` | Manage global instructions |
| `enable_prompt_template_button` | Manage prompt templates |
| `enable_toolbar_stats_item` | Credits toolbar item |
| `enable_global_settings` | Settings area inside the backend module |
| `enable_agency` | Agencies area |

### Models (`tx_aisuite_models:<key>`)

| Key | Model |
|---|---|
| `ChatGPT` | OpenAI (ChatGPT) |
| `Anthropic` | Anthropic / Claude |
| `MittwaldMinistral14B` | Mittwald Ministral-3-14B |
| `MittwaldMinistral14BVision` | Mittwald Ministral Vision |
| `AiSuiteTextUltimate` | Meta Llama-3.3 (70B-Instruct) |
| `Vision` | Vision (image metadata) |
| `GPTImage` | GPT Image |
| `Midjourney` | Midjourney |
| `Flux` | Flux |
| `Deepl` | DeepL |
| `GoogleTranslate` | Google Translate |

> Flags and keys are registered in `ext_tables.php` (`customPermOptions`); their labels live in `Resources/Private/Language/locallang_tca.xlf` (`aiSuite.permissions.*`).

## Features in depth

### Metadata generation

Add AI-generated SEO metadata (title, description, keywords, Open Graph and Twitter fields) to pages, and alternative text / title / description to files. Available as **field controls** next to the relevant TCA fields for a single record, and as **bulk operations** through the WorkflowManager / TaskEngine for whole folders or page subtrees. File metadata can be **auto-generated on upload** (`metadataAutogenerate*`), either queued for review in the TaskEngine or saved directly.

Page abstracts are generated from the page's rendered frontend content. Custom doktypes that do not render meaningful content yield a thin or generic abstract, that is expected behaviour, not a bug (tune the threshold via `minPlausiblePageContentLength`).

### Content creation & optimization

Generate full `tt_content` elements from a prompt in the page module, and rewrite, shorten or simplify existing copy. Generation respects your reusable **prompt templates** and **global instructions** for consistent house style.

### Translation & Easy Language

Translate single records, whole pages, file metadata or list selections. Translation runs through the localization wizard and dedicated list wizards. Highlights:

- **Easy Language** rewrites for accessible content, in the translation flow and as a CKEditor plugin.
- **FlexForm field translation** (`translateFlexFormFields`).
- **DeepL glossary sync** from the AI Suite glossary table (`updateDeeplGlossaries`).
- Decorators over the core `LocalizationController` and `DatabaseRecordList` add the AI translation entry points. The whole translation surface can be switched off with `disableTranslationFunctionality`.

### Image generation

Generate AI images (GPT Image, Midjourney, Flux) directly into FAL. The result is stored as a real `sys_file` in the configured `mediaStorageFolder`, ready to reference anywhere.

### Page-tree generation

Generate a complete page tree from a prompt, optionally including page metadata and content inside the new pages. Review the generated structure in the backend module before it is persisted.

### CKEditor plugins

Two RTE plugins ship with the extension: an **AI assistant** (`AiPlugin`) for inline generation / rewriting, and an **Easy Language assistant** (`AiEasyLanguagePlugin`). Both are permission-gated (`enable_rte_aiplugin`, `enable_rte_aieasylanguageplugin`).

### Prompt templates & global instructions

- **Prompt templates** reusable, editable templates for recurring generation tasks (custom and server-provided).
- **Global instructions** project-wide instructions automatically applied to every prompt, so house style, tone and constraints are enforced everywhere. A preview tooltip shows the effective combined prompt.

## Background processing

Long-running jobs (bulk metadata, large translations) do not block the backend. They are split into tasks and processed asynchronously by the **TaskEngine**; results come back as suggestions you review and apply. The **WorkflowManager** is the entry point for starting such mass actions.

### CLI & Scheduler

For fully automated pipelines, AI Suite ships console commands (requires `typo3/cms-scheduler` for scheduled runs and the `enable_cli_workflow_execution` permission for the backend trigger):

```bash
# Start an AI Suite workflow (metadata generation or translation) and store tasks for later processing
vendor/bin/typo3 ai-suite:execute-workflow

# Poll the AI server for finished CLI background tasks and persist results
vendor/bin/typo3 ai-suite:process-tasks

# Retry failed CLI background tasks (status: task-error)
vendor/bin/typo3 ai-suite:retry-tasks
```

Schedule `ai-suite:process-tasks` via the TYPO3 Scheduler or system cron so queued results get persisted automatically.

## Database tables

AI Suite adds the following custom tables (schema in `ext_tables.sql`):

| Table | Purpose |
|---|---|
| `tx_aisuite_domain_model_custom_prompt_template` | User-defined prompt templates |
| `tx_aisuite_domain_model_server_prompt_template` | Server-provided prompt templates |
| `tx_aisuite_domain_model_global_instructions` | Project-wide global instructions |
| `tx_aisuite_domain_model_deepl` | DeepL language / configuration data |
| `tx_aisuite_domain_model_glossar` | Glossary entries (used for DeepL glossary sync) |

It also extends core tables (e.g. `be_groups` for the permission system) via TCA overrides.

## MCP server (optional sub-extension)

AI Suite ships with an optional **MCP (Model Context Protocol) server** sub-extension, `ai_suite_mcp`, which exposes the same AI capabilities to MCP-compatible clients (Claude Desktop, Claude.ai, ChatGPT, MCP Inspector, …) so a model can drive your TYPO3 backend directly from a chat. It reuses AI Suite's providers, credit accounting and per-feature / per-model permissions, and adds OAuth 2.1 auth and workspace-aware writes.

See the [`ai_suite_mcp` extension on the TYPO3 Extension Repository](https://extensions.typo3.org/extension/ai_suite_mcp) for installation, connector setup and the full tool catalogue.

## Documentation

Official documentation: <https://www.autodudes.de/en/documentation/ai-suite/introduction>

## Feedback

We actively develop AI Suite and would love your feedback, especially from real editorial workflows:

- 🐛 **Bugs / regressions** [open an issue](https://github.com/autodudesde/ai-suite/issues).
- 💡 **Feature ideas** tell us what the AI should have been able to do.
- 🔒 **Security findings** please contact us directly rather than opening a public issue.

The fastest way to reach us is the [#ai-suite channel on TYPO3 Slack](https://typo3.slack.com/archives/C05QAN1KNVD). You can also reach us at [service@autodudes.de](mailto:service@autodudes.de).

## License

GPL-2.0-or-later
