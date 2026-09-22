# TYPO3 AI Suite

The AI Suite optimizes the workflow of project managers, agencies and freelancers by using the latest AI technologies. It seamlessly integrates a wide variety of AI providers and the AutoDudes open-source models directly into the TYPO3 backend, so editors generate, translate and optimize content without ever leaving TYPO3.

It covers the everyday editorial chores that scale badly by hand: image and page metadata (single records and in bulk), content translation, **Easy Language** rewrites for accessible copy, content creation and rewriting, AI image generation, and whole page-tree generation. Long-running jobs run asynchronously through the built-in **TaskEngine**, and access is gated per feature and per AI model through TYPO3 backend-group permissions. SEO and accessibility audits, translation on save, usage statistics and an overview of AI-generated content round it off.

Two optional extensions build on it: an [MCP server](#mcp-server-optional-sub-extension) that lets external AI clients work in TYPO3, and [ChEddi](#cheddi-optional-editor-assistant), an AI assistant inside the backend.

> 💬 Join us on Slack: [#ai-suite on TYPO3 Slack](https://typo3.slack.com/archives/C05QAN1KNVD) to follow development, raise issues or shape the roadmap.

> **Version-agnostic README.** AI Suite is maintained as parallel branches for the actively supported TYPO3 majors. This file intentionally avoids hardcoded version numbers so it down-merges unchanged. For the exact supported TYPO3 and PHP versions of the branch you are on, read `composer.json` (`require`) and `ext_emconf.php` (`constraints.depends`).

## What you can do with it

- 🏷️ **Generate page metadata** (SEO title, description, abstract, Open Graph / Twitter cards) for a single page or in bulk over a whole page subtree.
- 🖼️ **Generate file metadata** (alternative text, title, description) for images, single file or for every file in a folder. Optionally auto-generate on upload.
- ✍️ **Create & optimize content**: full content elements from a prompt in the new content element wizard, and AI rework of existing copy in the rich text editor (free prompt or prompt template).
- 🌍 **Translate anything**: single records, whole pages, file metadata or list selections, with DeepL glossary sync and FlexForm field translation.
- 🧱 **Generate complete page trees** from a single prompt, with page metadata and content inside the new pages.
- 🎨 **Generate AI images** straight into FAL, ready to be referenced as a real `sys_file`.
- 📰 **Generate news records** (when EXT:news is present) via a **News with AI** button in the list module.
- 🔎 **Audit pages**: SEO, accessibility (WCAG), question coverage, content gap, topic clusters and competitors, stored per page and language, reachable from the page-tree context menu, with a score tile in the page module, CSV export for SEO and accessibility and a dashboard widget.
- 🔁 **Translate on save**: content elements are translated into all site languages automatically when they are saved (`enableAutoTranslateOnSave`).
- 📊 **Statistics & AI transparency**: usage per month and model, an *AI content* overview of everything an AI feature wrote, and an *About this AI system* page (see [Transparency](#transparency-of-ai-generated-content-eu-ai-act-art-50)).
- 🧠 **CKEditor AI plugins**: an AI assistant and an Easy Language assistant right inside the RTE.
- 🧩 **Reusable prompt templates & global instructions**: capture house style once and apply it to the pages or folders it is meant for.
- ⏱️ **Background processing via TaskEngine**: long-running batches get an async task; results come back as suggestions you review and apply.
- ⚙️ **WorkflowManager**: run mass actions (bulk metadata / translation) across many records at once.
- 🖥️ **CLI & Scheduler**: kick off and process workflows headless for fully automated pipelines.
- 🔐 **Per-feature and per-model permissions**: every capability and every AI model is gated by TYPO3 backend-group flags.

## AI capabilities & available models

The set of models offered to a given backend user depends on the AI-model permissions configured on their backend group (see [Backend-group permissions](#backend-group-permissions)) and on what your license / API keys unlock. Model names below name provider and model; the labels in the backend can be shorter.

| Capability | Where in the backend | Models |
|---|---|---|
| **Page metadata** (SEO, OG, Twitter) | SEO field controls, page-tree context menu, WorkflowManager, TaskEngine | OpenAI (ChatGPT), Anthropic/Claude, Mittwald (Ministral-3-14B-Instruct-2512), Meta Llama-3.3 (70B-Instruct) |
| **File / image metadata** (alt, title, description) | File list, file metadata field controls, auto-on-upload | OpenAI Vision, Mittwald (Ministral-3-14B-Instruct-2512 Vision), plus the configured metadata model |
| **Content generation** (tt_content) | New content element wizard (AI Suite Content tab) | OpenAI (ChatGPT), Anthropic/Claude, Mittwald (Ministral-3-14B-Instruct-2512), Meta Llama-3.3 (70B-Instruct) |
| **Page-tree generation** | Backend module page-tree wizard | OpenAI (ChatGPT), Anthropic/Claude, Mittwald (Ministral-3-14B-Instruct-2512), Meta Llama-3.3 (70B-Instruct) |
| **Translation** (records, pages, file metadata) | Localization wizard, list wizard, file metadata | DeepL, Google Translate, OpenAI (ChatGPT), Anthropic/Claude, Mittwald (Ministral-3-14B-Instruct-2512) |
| **Easy Language** (accessibility rewrites) | CKEditor Easy Language plugin | OpenAI (ChatGPT), Anthropic/Claude, Meta Llama-3.3 (70B-Instruct) configurable via `easyLanguageLibrary` |
| **DeepL glossary sync** | Glossary sync button in the list module | DeepL |
| **Image generation** | Image generation buttons on image fields and in the file list | GPT Image (OpenAI), Midjourney, Flux |
| **Audits** (SEO, accessibility, GEO questions, content gap, topic clusters, competitors) | Audit module, page-tree context menu, page module tile | AutoDudes audit infrastructure; the text analysis runs on the model chosen per run or on `auditDefaultTextModel` |
| **Translation on save** | Page module, CLI or directly on save (`autoTranslateMode`) | OpenAI (ChatGPT), DeepL, Google Translate, Anthropic/Claude, Mittwald (Ministral-3-14B-Instruct-2512) via `autoTranslateModel` |
| **ChEddi chat** (optional extension) | Drawer on every backend page | OpenAI GPT-5.6 Luna, IONOS Qwen3.5-397B-A17B, Claude Haiku 4.5, Claude Sonnet 5, Claude Opus 5 |

You can run these models through the AutoDudes credit system (a single API key, see [Licensing & credits](#licensing--credits)) or bring your own provider keys (BYOK).

## Backend module

AI Suite adds a dedicated backend module whose dashboard gives access to the main areas (each gated by its own permission, except *About this AI system*):

| Area | Purpose |
|---|---|
| **WorkflowManager** | Run tasks instantly, e.g. metadata generation for pages and/or images as well as translations. |
| **TaskEngine** | Monitor and manage background tasks, e.g. the processing of bulk metadata generation or translations. |
| **Global instructions** | Instructions applied to the prompts for selected pages or folders (optionally their subtree). |
| **Prompt templates** | Reusable, editable prompt templates for recurring generation tasks. |
| **Page tree** | Generate a page tree (optionally including page metadata and content) from a prompt. |
| **Agencies** | Special-purpose helpers, currently translating, validating and writing XLF files. |
| **Settings** | Global extension configuration, e.g. AI Suite API Key, GDPR mode or directory protection. |
| **Audit** | Run and review SEO, accessibility and further page audits; export SEO and accessibility results as CSV. |
| **Statistics** | Usage per month and model, as reported by the AI Suite Server. |
| **AI content** | Everything an AI feature wrote, with its edit state since generation (see [Transparency](#transparency-of-ai-generated-content-eu-ai-act-art-50)). |
| **About this AI system** | Provider and roles under the EU AI Act. |

AI Suite also adds a module under **File** (TYPO3 v14: **Media**) for file metadata generation and translation in bulk and, for admins, a CLI overview under **Admin Tools**. A toolbar item (`enable_toolbar_stats_item`) shows the credits at a glance (**Pack** = one-off credits remaining, **Plan** = subscription credits used).

## Requirements

AI Suite is maintained as one branch per TYPO3 major. Pick the AI Suite line that matches your TYPO3 version:

| AI Suite | TYPO3 | PHP |
|---|---|---|
| **14.x** | 14.3.0 to 14.9.99 | 8.2 to 8.4 |
| **13.x** | 13.4.1 to 13.4.99 | 8.2 to 8.4 |
| **12.x** | 12.4.11 to 12.4.99 | 8.1 to 8.4 |

AI Suite also needs:

- TYPO3 system extensions: `backend`, `filelist`, `fluid`, `rte-ckeditor`, `seo`.
- An **AI Suite API key** (from [autodudes.de](https://www.autodudes.de/)) for the credit-based providers, **or** your own provider keys (BYOK).

**Suggested**

- `b13/container`: content elements inside containers are handled as first-class records.
- `typo3/cms-scheduler`: required for headless / scheduled background-task processing (see [CLI & Scheduler](#cli--scheduler)).
- `typo3/cms-dashboard`: adds the audit score widget.

**Conflicts**

- `passionweb/ai-seo-helper`: superseded by AI Suite's metadata features.

## Installation

```bash
composer require autodudes/ai-suite
vendor/bin/typo3 extension:setup
```

Or install via the **TYPO3 Extension Repository (TER)**, then run the database analyzer.

**Quickstart**

1. [Install the extension](https://www.autodudes.de/en/documentation/ai-suite/for-administrators/installation) via Composer or TER.
2. Get an API key at [autodudes.de](https://www.autodudes.de/) (or prepare your own provider keys for BYOK).
3. Add the key under **Web (TYPO3 v14: Content) → AI Suite → Settings** (or **Admin Tools → Settings → Extension Configuration → `ai_suite`**).
4. Update the database under **Admin Tools → Maintenance → Analyze Database Structure**.
5. Grant the relevant AI Suite feature and model permissions on the backend groups that should use it.
6. Enjoy the AI Suite.

The extension ships a page TSconfig that sets `TCEMAIN.translateToMessage` to an empty value, so a
localized record no longer carries TYPO3's `[Translate to <language>:]` prefix. Override it in your
own page TSconfig if you want the prefix back.

## Licensing & credits

AI Suite can drive AI providers two ways:

- **AutoDudes credits (single key).** One AI Suite API key unlocks every integrated provider; usage is billed against credits. Credits come as **Packs** (one-off, no subscription) and **Plans** (credits in a monthly/yearly subscription). The backend toolbar and dashboard show the remaining Pack credits and the Plan credits used.
- **Bring your own keys (BYOK).** Configure your own provider keys (OpenAI, Anthropic, IONOS AI Model Hub for Flux and the IONOS models, Mittwald AI Model Hub, DeepL, Google Translate, Midjourney, and Staan for ChEddi's web research under GDPR mode) in the extension configuration. Requests still go through the AI Suite Server, which uses your keys under your own contracts.

See [autodudes.de](https://www.autodudes.de/) for current packages and pricing.

## Configuration

All settings live under **Admin Tools → Settings → Extension Configuration → `ai_suite`** and are also reachable from the backend module's **Settings** area (when the `Global Settings` permission is granted). Sensitive values (API keys, passwords) are masked in the module UI.

`aiSuiteApiKey`, the provider keys under [Bring your own keys](#bring-your-own-keys) except `staanApiKey`, `deeplApiMode` and `mediaStorageFolder` can also be overridden per backend group, in the group record.

### AI Suite API

| Setting | Default | Description |
|---|---|---|
| `aiSuiteApiKey` | _(empty)_ | Your AutoDudes AI Suite license key. Required for the credit-based providers. |
| `aiSuiteServer` | `https://api.autodudes.de/` | AI Suite server endpoint. Change only for self-hosted / proxied setups. |
| `aiSuiteSystemDomain` | _(empty)_ | Domain this installation is licensed for. Needed for CLI and cron runs, where no HTTP host is available; empty detects it from the site configuration. |

### Translation

| Setting | Default | Description |
|---|---|---|
| `translateFlexFormFields` | `1` | Also translate FlexForm field values, not just regular columns. |
| `easyLanguageLibrary` | `ChatGPT` | Model used for Easy Language rewrites (`ChatGPT`, `Anthropic`, or `AiSuiteTextUltimate` / Meta Llama-3.3). |
| `disableTranslationFunctionality` | `0` | Master switch that unregisters the translation decorators (`DatabaseRecordList` and the core's page-localization handler) and the translation DataHandler hooks, translation on save included. |
| `enableAutoTranslateOnSave` | `0` | Translate content elements into all site languages automatically when they are saved. |
| `autoTranslateMode` | `pageModule` | `pageModule` applies finished translations when the page module is opened (status shown in the page tree), `cli` needs `ai-suite:process-tasks` running, `direct` translates synchronously on save (blocks the save, only for few languages). |
| `autoTranslateModel` | `ChatGPT` | Model used for translation on save (`ChatGPT`, `Deepl`, `GoogleTranslate`, `Anthropic`, `MittwaldMinistral14B`). |
| `autoTranslateMaxElementsPerSave` | `15` | Safety cap per save: a save that changes more elements skips automatic translation and notifies the editor. `0` = no limit. |

### Metadata

| Setting | Default | Description |
|---|---|---|
| `metadataSuggestionCount` | `5` | Number of suggestions generated per metadata request (1 to 5). |
| `metadataAutogenerateTitle` | _(off)_ | Auto-generate image titles on upload. |
| `metadataAutogenerateAlternative` | _(off)_ | Auto-generate alternative text on upload. |
| `metadataAutogenerateModel` | `Vision` | Model used for auto-generated file metadata. |
| `metadataAutogenerateApproach` | `taskEngine` | `taskEngine` queues suggestions for review; `saveDirectly` writes the first suggestion immediately. |
| `metadataAutogeneratePrompt` | _(empty)_ | Own prompt for file metadata generated on upload; replaces the predefined instruction. |
| `minPlausiblePageContentLength` | `100` | Minimum length in characters of the fetched page HTML, markup included. A shorter answer counts as no usable page content and is not sent for page metadata generation. |

### Media

| Setting | Default | Description |
|---|---|---|
| `mediaStorageFolder` | _(empty)_ | Target FAL folder for AI-generated images. Empty = an `ai-images` folder in the default upload folder. |

### GDPR

| Setting | Default | Description |
|---|---|---|
| `forceGdpa` | `0` | Offer only GDPR-compliant models; the flag is sent to the AI Suite Server with the request for the available models. |

### AI Act / provenance

| Setting | Default | Description |
|---|---|---|
| `provenanceTracking` | `1` | Record which records an AI feature wrote (the register behind the evidence and the backend badges). |
| `provenanceDiscloseTranslations` | `0` | Disclose AI translations as AI-generated as well. They are recorded either way; a faithful translation arguably preserves the semantics and is exempt. |
| `provenanceShowLabels` | `1` | Show the marking in the backend: the badge in the page module and the notice above the fields an AI feature filled. Off keeps the register and the machine-readable image marking; it only stops labelling what editors see. |

Generated images carry an IPTC `DigitalSourceType`, written by the AI Suite Server when it creates
the image: that marking is the provider's own obligation under Art. 50(2) and is not switchable. AI
Suite stores the bytes unchanged, so that marking and a C2PA manifest a provider embedded survive.

Four things to know about how these interact:

- `provenanceTracking = 0` switches everything off at once, including the register, so nothing is
  labelled anywhere. `provenanceShowLabels = 0` keeps the evidence and stops only the backend labels.
- Frontend labelling is not covered by either. It needs `autodudes/ai-suite-transparency` installed
  **and** its site set included; without that nothing is output on the website.
- A record stops being labelled once a human rewrites the fields the AI wrote. The register entry
  stays, and the *AI content* module still lists it as edited since generation.
- In an edit form the marking is per field. Every field AI Suite can fill carries a notice next to
  the generate button (a page's SEO fields and abstract, a file's title, alternative text and
  description, a file reference's title and alternative text, and the two news fields), and each
  notice ends when that field is rewritten. Correcting an alternative text leaves the notice on a
  title nobody touched.

The *AI content* module splits what it lists into three tabs, each with its own filters: **Pages**
(pages and their content elements), **Files & media** (generated files and their metadata) and
**Records** (everything else: news, and whatever the MCP tools wrote into an installation's own
tables). Within a tab the entries are grouped by where they belong (the page a record lives on, the
folder of a file, or the record it hangs off), and a file names the element it is used on rather than
being nested beneath it.

Two kinds of entry appear in no tab. A file reference stays in the register but out of the list: it
carries the AI-written alternative text and it is how a file is traced to its page, but it is no
record an editor manages. And a record that is part of another one (an accordion item, say) never
gets an entry of its own in the first place: it is recorded against the element it belongs to, which
is also what carries the marking in the frontend. Without all of that, one image generation filled
three rows with almost the same title.

Each tab keeps its filters in the URL, so the list can be shared and the back button works.

It lists live records only. A workspace draft is left out: publishing moves its entry to the live
record, discarding takes it along, and its edit form could not be saved from outside that workspace
anyway. An entry whose record was deleted outright is not listed either: it says nothing an
editor can act on. Those are counted across the whole register and can be removed with the cleanup
button.

Access to the *AI content* module is granted per backend group with
`tx_aisuite_features:enable_provenance_overview`; the route refuses the request without it, not just
the button. *About this AI system* carries no flag on purpose: under Art. 50(1) it is addressed at
whoever works with the system.

### Background Tasks

| Setting | Default | Description |
|---|---|---|
| `maxTasks` | `50` | Maximum number of CLI background tasks handled per `ai-suite:process-tasks` run. |

### Audit

| Setting | Default | Description |
|---|---|---|
| `auditWcagStandard` | `WCAG2AA` | WCAG level for accessibility audits (`WCAG2A`, `WCAG2AA`, `WCAG2AAA`). Steers the HTML_CodeSniffer runner only; the axe runner checks its own rule set. |
| `auditDefaultTextModel` | _(empty)_ | Model identifier for audit analyses (e.g. `Anthropic`). When set, the model selection on the audit form is skipped; empty lets editors choose per run. |

### HTTP Basic Authentication (directory protection)

| Setting | Default | Description |
|---|---|---|
| `basicAuth.enable` | `0` | Send HTTP Basic Auth credentials when AI Suite fetches this site's own frontend pages (for sites behind directory protection). |
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
| `aiModelHubApiKey` | _(empty)_ | Your IONOS AI Model Hub API key (IONOS models, also used for Flux). |
| `mittwaldAiModelHubApiKey` | _(empty)_ | Your Mittwald AI Model Hub API key (Mittwald models). |
| `staanApiKey` | _(empty)_ | Your Staan API key. Only needed for ChEddi's web research under GDPR mode. |

## Backend-group permissions

AI Suite gates access on two independent axes that both apply on every action: **per feature** and **per AI model**. Both are TYPO3 custom permission options set on each backend group (`be_groups`) under the **AI Suite Features** (`tx_aisuite_features`) and **AI Suite Models** (`tx_aisuite_models`) sections. A user needs the feature flag for the action **and** the model flag for the model that action uses.

### Features (`tx_aisuite_features:<flag>`)

| Flag | Unlocks |
|---|---|
| `enable_metadata_generation` | Page and file metadata generation |
| `enable_content_element_generation` | Content element generation in the new content element wizard |
| `enable_pages_generation` | Page-tree generation |
| `enable_news_generation` | News record generation (EXT:news) |
| `enable_image_generation` | AI image generation into FAL |
| `enable_translation` | AI translation options in the page content translation wizard |
| `enable_translation_whole_page` | Translate an entire page at once |
| `enable_translation_sys_file_metadata` | Translate file metadata |
| `enable_auto_translation` | Automatic translation of content elements on save |
| `enable_translation_list_wizard` | Translation wizard in record lists |
| `enable_translation_deepl_sync` | DeepL glossary synchronization |
| `enable_massaction_generation` | WorkflowManager mass actions |
| `enable_background_task_handling` | TaskEngine background-task management |
| `enable_cli_workflow_execution` | The "Generate with AI and auto-fill in background" button in the WorkflowManager (processed by `ai-suite:process-tasks`, needs EXT:scheduler) |
| `enable_rte_aiplugin` | CKEditor AI assistant plugin |
| `enable_rte_aieasylanguageplugin` | CKEditor Easy Language plugin |
| `enable_global_instructions_button` | Manage global instructions |
| `enable_prompt_template_button` | Manage prompt templates |
| `enable_toolbar_stats_item` | Credits toolbar item |
| `enable_global_settings` | Settings area inside the backend module |
| `enable_agency` | Agencies area |
| `enable_audit` | SEO and accessibility audits (Audit module, context menu, ChEddi and MCP audit tools) |
| `enable_statistics` | Statistics view |
| `enable_provenance_overview` | *AI content* overview, including marking entries as reviewed |

### Models (`tx_aisuite_models:<key>`)

| Key | Model |
|---|---|
| `ChatGPT` | OpenAI (ChatGPT) |
| `Anthropic` | Anthropic / Claude |
| `MittwaldMinistral14B` | Ministral-3-14B-Instruct-2512 (Mittwald) |
| `MittwaldMinistral14BVision` | Ministral-3-14B-Instruct-2512 (Vision, Mittwald) |
| `AiSuiteTextUltimate` | Meta Llama-3.3 (70B-Instruct) |
| `Vision` | Vision (image metadata) |
| `GPTImage` | GPT Image |
| `Midjourney` | Midjourney |
| `Flux` | Flux |
| `Deepl` | DeepL |
| `GoogleTranslate` | Google Translate |

> Flags and keys are registered in `ext_tables.php` (`customPermOptions`); their labels live in `Resources/Private/Language/locallang_tca.xlf` (`aiSuite.permissions.*`). The optional extensions add their own entries to both sections: `ai_suite_mcp` the flags `enable_mcp_access`, `enable_mcp_media_upload` and `enable_mcp_rendered_page_read`, ChEddi the flags `enable_cheddi_interface` and `enable_web_research` plus the chat models `OpenAiLuna`, `IonosQwen35`, `ClaudeHaiku45`, `ClaudeSonnet5` and `ClaudeOpus5`.

## Features in depth

### Metadata generation

Add AI-generated SEO metadata (title, description, abstract, Open Graph and Twitter fields) to pages, and alternative text / title / description to files. Available as **field controls** next to the relevant TCA fields for a single record, and as **bulk operations** through the WorkflowManager / TaskEngine for whole folders or page subtrees. File metadata can be **auto-generated on upload** (`metadataAutogenerate*`), either queued for review in the TaskEngine or saved directly.

Page abstracts are generated from the page's rendered frontend content. Custom doktypes that do not render meaningful content yield a thin or generic abstract, that is expected behaviour, not a bug. `minPlausiblePageContentLength` only rejects fetched pages shorter than the threshold; it does not make a thin abstract better.

### Content creation & optimization

Generate full `tt_content` elements from a prompt in the new content element wizard, and rework existing copy in the rich text editor with a free prompt or a prompt template. Generation respects your reusable **prompt templates** and **global instructions** for consistent house style.

### Translation & Easy Language

Translate single records, whole pages, file metadata or list selections. Translation runs through the localization wizard and dedicated list wizards. Highlights:

- **Easy Language** rewrites for accessible content, as a CKEditor plugin (model: `easyLanguageLibrary`).
- **FlexForm field translation** (`translateFlexFormFields`).
- **DeepL glossary sync** from the AI Suite glossary table, started with the button in the list module (`enable_translation_deepl_sync`).
- Decorators over the core `DatabaseRecordList` and over the core's page-localization handler add the AI translation entry points. Which core class that is differs by TYPO3 major. The whole translation surface can be switched off with `disableTranslationFunctionality`.

**Translations are created hidden.** That is TYPO3, not AI Suite: `tt_content` carries
`hideAtCopy` in the core TCA, so `DataHandler` hides every localized record whose source was
visible. Editors have to publish them, or the installation switches it off with
`TCEMAIN.disableHideAtCopy = 1` in page TSconfig. When a translated element looks missing, check
its hidden flag before anything else.

**Elements without translatable text are localized but not sent.** A content element whose header
and bodytext are empty (a slider carrying only images, for instance) has nothing to translate and
costs no credits, but it is still created in the target language so the page stays complete. With
a site language configured as `fallbackType: fallback` a missing element is invisible in the
frontend, which is why this matters. The run also warns, naming the content type: a CType without
its own `types` entry has no translatable field at all and would otherwise wait for a translation
that is never going to arrive.

**Records that could not be written are reported.** If a translation comes back but its target
record cannot be created (a table the page type does not allow, a missing permission), the run
names those records instead of claiming success, as a warning in the backend. The reason is in `var/log/typo3_*.log` under
`No target record for translation` or `Failed to translate content element` (with table and uid),
or `Failed to localize content element` (with the source uid).

### Translation on save

With `enableAutoTranslateOnSave`, a saved content element is translated into all languages of its
site. `autoTranslateMode` decides when the result lands: when the page module is opened (the page
tree shows the status), through `ai-suite:process-tasks`, or synchronously during the save.
`autoTranslateMaxElementsPerSave` keeps a bulk edit from starting a large translation run. It needs
`enable_auto_translation` and the permission for the `autoTranslateModel`, only targets languages
the page is already translated into and whose content is not in free mode, and never runs inside a
workspace. In the synchronous mode the edit form says so on submit, so the editor knows why the
save takes longer. A target language that fails or comes back empty is reported per language in
the backend, not only in the log. Writes over the MCP transport do not trigger it, because the
hook only runs inside a backend request.

### Audits

The **Audit** module runs six audit types on a page URL: SEO (on-page checks, Lighthouse, GEO
signals), accessibility against `auditWcagStandard`, question coverage, content gap, topic clusters
and competitors. An audit spends credits like a generation does. Results are stored per page and
language, a newer audit of the same type replaces the older one, results older than 14 days are
discarded automatically, and SEO and accessibility results can be exported as CSV. Audits start
from the module, from the **AI Suite** entry of the page-tree context menu, or through ChEddi or an
MCP client, and always measure the live page, also when the editor is inside a workspace. From a
result, an **AI fix wizard** proposes and applies corrections, and a batch run audits a whole page
subtree with a cost estimate beforehand. The page module shows a tile with the stored scores of the page, and with
`typo3/cms-dashboard` installed a dashboard widget shows the score distribution.

### Image generation

Generate AI images (GPT Image, Midjourney, Flux) directly into FAL. The result is stored as a real `sys_file` in the configured `mediaStorageFolder` (empty: `ai-images`), ready to reference anywhere. Generation starts from the buttons on image fields of content elements and in the file list.

### Page-tree generation

Generate a complete page tree from a prompt, optionally including page metadata and content inside the new pages. Review the generated structure in the backend module before it is persisted.

### CKEditor plugins

Two RTE plugins ship with the extension: an **AI assistant** (`AiPlugin`) for inline generation / rewriting, and an **Easy Language assistant** (`AiEasyLanguagePlugin`). Both are permission-gated (`enable_rte_aiplugin`, `enable_rte_aieasylanguageplugin`).

### Prompt templates & global instructions

- **Prompt templates**: reusable, editable templates for recurring generation tasks (custom and server-provided).
- **Global instructions**: instructions for selected pages or folders (optionally including their subtree), automatically applied to the prompts generated there, so house style, tone and constraints stay consistent. A **scope** limits an instruction to one generation context (metadata, content elements, the image wizard, translation, the page tree, news records), while `extend_previous_instructions` and `override_predefined_prompt` decide whether it adds to the instructions above it and whether it replaces the built-in prompt. A preview tooltip shows the effective combined prompt.

## Background processing

Long-running jobs (bulk metadata, large translations) do not block the backend. They are split into tasks and processed asynchronously by the **TaskEngine**; results come back as suggestions you review and apply. The **WorkflowManager** is the entry point for starting such mass actions.

### CLI & Scheduler

For fully automated pipelines, AI Suite ships console commands (requires `typo3/cms-scheduler` for scheduled runs; the backend trigger needs the `enable_cli_workflow_execution` permission and only appears while an active Scheduler task runs `ai-suite:process-tasks`):

```bash
# Start an AI Suite workflow (metadata generation or translation) and store tasks for later processing
vendor/bin/typo3 ai-suite:execute-workflow

# Poll the AI server for finished CLI background tasks and persist results
vendor/bin/typo3 ai-suite:process-tasks

# Retry failed CLI background tasks (status: task-error)
vendor/bin/typo3 ai-suite:retry-tasks
```

`ai-suite:execute-workflow` is driven entirely by options, so a scheduled run never prompts:
`--type`/`-t` and `--model`/`-m` pick workflow and AI model, `--start-from-pid`, `--depth`,
`--page-type`, `--column`, `--sys-language`, `--show-only-empty` and `--include-hidden` select the
pages for a metadata run, `--source-language`, `--target-language` and `--translation-scope` a
translation, `--directory` and `--show-only-used` a file metadata run, and `--prompt` overrides the
generated prompt. `ai-suite:retry-tasks` takes `--type`/`-t` and `--model`/`-m`, where the model
defaults to the one the failed task used. `ai-suite:process-tasks` takes no options.

Schedule `ai-suite:process-tasks` via the TYPO3 Scheduler or system cron so queued results get persisted automatically. It is also required when `autoTranslateMode = cli`.

## Transparency of AI-generated content (EU AI Act Art. 50)

Two obligations apply to different parties, and mixing them up is the usual source of confusion:

- **AutoDudes is the provider.** Art. 50(2) requires the outputs of a generative system to be marked
  in a machine-readable way. That obligation is ours, not yours.
- **You, running this website, are the deployer.** Art. 50(4) requires a *visible* disclosure for
  deepfakes and for AI-generated text published to inform the public on matters of public interest.
  Whether a given piece of content falls under it depends on the content and on how much editorial
  control a human exercised. The extension gives you the facts, it cannot make that call for you.

**What the extension does today.** Generated images carry an IPTC `DigitalSourceType` of
`trainedAlgorithmicMedia`, written by the AI Suite Server when it creates the image. AI Suite stores
the bytes unchanged, so that marking and a C2PA manifest a provider embedded survive into FAL. Every
generated image is additionally recorded in the provenance register.

Alongside that, AI Suite keeps a **provenance register** (`tx_aisuite_domain_model_provenance`): one
row per record an AI feature wrote, with mode, model, feature, backend user and time. It covers
generated content, page trees and images, page and file metadata, applied audit fixes, translations, results
the TaskEngine applies, and everything written through the MCP server or ChEddi.

**Where you see it in the backend.** Content elements an AI feature wrote carry a badge in the page
module, a generated file says so in its metadata form, and the module entry *About this AI system*
names the provider and both roles under Art. 50. Which models actually ran is a different question,
and the statistics module answers it better: from the usage the AI Suite Server reports, with volume and over time.

**A generated image and the words describing it are two different claims.** The image carries its
own marking in its bytes and is listed in the *AI content* module; the title and alternative text
written for it are text an editor reads and corrects, and they carry their own notice in the form.
Editing the caption does not make the image less AI-generated, and it no longer removes anything
that says so.

**One caveat that is not ours but will bite you.** TYPO3 strips image metadata when it creates
`_processed_` derivatives. If a marking has to survive into the delivered page, reference the
original file or configure your image processor to keep metadata. This applies to every TYPO3
installation, with or without AI Suite.

**Assistive functions are not labelled.** The CKEditor assistant correcting spelling, grammar or
phrasing does not create new content in the sense of Art. 50(2), and is treated as an assistive
function: an accepted suggestion is recorded in the register as `assisted`, but not labelled as
AI-generated. Generating a paragraph from a prompt is a different thing and is covered by the marking
described above.

## Database tables

AI Suite adds the following custom tables (schema in `ext_tables.sql`):

| Table | Purpose |
|---|---|
| `tx_aisuite_domain_model_custom_prompt_template` | User-defined prompt templates |
| `tx_aisuite_domain_model_server_prompt_template` | Server-provided prompt templates |
| `tx_aisuite_domain_model_global_instructions` | Project-wide global instructions |
| `tx_aisuite_domain_model_deepl` | DeepL language / configuration data |
| `tx_aisuite_domain_model_glossar` | Glossary entries (used for DeepL glossary sync) |
| `tx_aisuite_domain_model_requests` | Request and credit counters shown in the toolbar |
| `tx_aisuite_domain_model_backgroundtask` | TaskEngine background tasks |
| `tx_aisuite_audit_result` | Stored audit results per page, language and audit type |
| `tx_aisuite_domain_model_provenance` | Provenance register of the records an AI feature wrote |

It also adds columns to `be_groups` for the per-group overrides of the API keys (all but `staanApiKey`), `deeplApiMode` and `mediaStorageFolder`; the permissions themselves are TYPO3 custom permission options.

## MCP server (optional sub-extension)

AI Suite ships with an optional **MCP (Model Context Protocol) server** sub-extension, `ai_suite_mcp`, which exposes the same AI capabilities to MCP-compatible clients (Claude Desktop, Claude.ai, ChatGPT, MCP Inspector, …) so a model can drive your TYPO3 backend directly from a chat. It reuses AI Suite's providers, credit accounting and per-feature / per-model permissions, and adds OAuth 2.1 auth and workspace-aware writes.

The endpoint stays off until `enableMcp` is set. The extension requires the system extensions `workspaces` and `reports`, and it adds the backend-group flags `enable_mcp_access`, `enable_mcp_media_upload` and `enable_mcp_rendered_page_read`.

See the [`ai_suite_mcp` extension on the TYPO3 Extension Repository](https://extensions.typo3.org/extension/ai_suite_mcp) for installation, webserver setup, connector setup and the full tool catalogue.

## ChEddi (optional editor assistant)

<img src="Resources/Public/Icons/cheddi.png" alt="ChEddi" width="64" align="right">

ChEddi (`autodudes/cheddi`, extension key `cheddi`) puts a chat drawer on every backend page. Editors say in plain language what they need, and ChEddi works on the real records through the tools of `ai_suite_mcp`, run inside the backend as the logged-in user and with exactly that user's permissions. Read-only steps run on their own unless they cost credits, in which case they are confirmed like a write; every write needs a click and a destructive one a second click. By default the changes land in a workspace draft the editor can publish or discard from the drawer.

- **Dependencies:** `ai_suite`, `ai_suite_mcp`, `workspaces`, `reports` and `scheduler`; Composer pulls them in.
- **Access:** the backend-group flag `tx_aisuite_features:enable_cheddi_interface` plus at least one chat model (`OpenAiLuna`, `IonosQwen35`, `ClaudeHaiku45`, `ClaudeSonnet5`, `ClaudeOpus5`). The experimental web research has its own flag, `enable_web_research`.
- **AI Suite Server:** every turn goes through the server configured here (`aiSuiteServer`, `aiSuiteApiKey`), also on plans that run on your own keys.
- **Settings:** ChEddi's own settings are prefixed `chat*`. Most inherit the matching `ai_suite_mcp` setting (GDPR mode comes from `ai_suite`), table lists extend the MCP lists, and markup and GDPR settings can only tighten.

The ChEddi README (`autodudes/cheddi`) covers installation, permissions, web research and configuration.

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
