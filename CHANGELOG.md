# Changelog

All notable changes to AI Suite are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the versions follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Security fixes are listed under **Security** and published as
[GitHub security advisories](https://github.com/autodudesde/ai-suite/security/advisories); see
[SECURITY.md](SECURITY.md) for supported versions and how to report a vulnerability.

This file lists the releases of every maintained major: 14.x for TYPO3 14, 13.x for TYPO3 13 and
12.x for TYPO3 12. It is the same file on every major, newest version first.

## [14.8.0] - 2026-11-15

### Added

- Added a "View suggestions" link to finished rows in the Workflow Manager, which opens the TaskEngine with exactly that task expanded (page metadata, file references, file metadata and file metadata translations)
- Added site-wide global instructions without defined scopes

### Changed

- Simplified the global instruction form: each overview section opens the form in its own context, so records no longer move between sections by accident
- Show a missing route permission as an "Access denied" warning instead of an unexpected error

### Fixed

- Fixed translated records in every table keeping the slug of the source language. Inherited slugs are now regenerated after a translation, while slugs that editors set by hand stay untouched
- Fixed automatic translations of hidden content elements never being applied in the page module
- Fixed process-tasks failing on finished file metadata translations, which are now written like a manual acceptance
- Fixed the background generation button missing on the first view of the file workflows (thx @christophbee)
- Fixed pages below a menu separator, link pages, mount points and custom page types not being selectable in global instructions (fixed #45, thx @christophbee)
- Fixed the duplicate check of global instructions when editing an existing record

### Upgrade notes

- Please clear all caches after updating and compare database.

## [14.7.0] - 2026-10-28

### Added

- Added a comparison of the AI result with the original text in both CKEditor plugins (AI plugin and Easy Language)
- Offered workflow type, AI model and column of the "ai-suite:execute-workflow" scheduler task as dropdowns instead of free text fields (thx @cbolda)

### Changed

- Framed every section of the global settings and added a hint that the automatic generation on upload only applies to newly uploaded files (thx @cbolda)
- Enforced the feature permissions on every backend route and refused AI models the user's groups have not granted on every request, not only in the model lists
- Restricted translations to the text fields and records they were made for and only accepted safe link targets
- Scoped the glossary to the site of the translated content, also for the page translation workflow and the automatic translation on save
- Translated only the title of link fields and kept their target
- Prevented localizations based on a workspace version, which produced translations nobody could find
- Let the workflows process only targets the editor is allowed to edit and checked page access in the audits
- Hardened the fetching of external URLs (audits, media)
- Applied page global instructions from the root down to the page
- Applied the folder's global instructions to file references and gave file metadata translations their own global instructions scope
- Kept global instructions off the root level, preset their context and named context and scope in the duplicate warning
- Showed the global instructions badge next to the "Use your own prompt" toggle
- Sent each page batch of the translation workflow only once and refused a page localization without selection or whole page mode
- Rendered the AI Suite modules with the core module layout and styled the audit result tables like the core lists
- Improved labels, progress texts and German wording across the settings, audit module and workflow manager

### Upgrade notes

- Please clear all caches after updating.
- The markup of "Plain HTML" content elements is no longer translated automatically. Enable the new extension setting translatePlainHtml to translate it as before.

## [14.6.2] - 2026-10-02

### Added

- Added Midjourney 8.2 as the new default version and removed the outdated 5.x versions

### Changed

- Optimized and regrouped the extension settings tabs

### Fixed

- Fixed RTE content being lost when a record was saved before the AI plugins in CKEditor had finished loading
- Fixed the record edit dialog closing and aborting the conversion when converting text to plain language
- Fixed the configured system domain being overridden by per-site domains in bulk workflows, which caused license errors on multi-site installations
- Fixed DeepL glossary synchronization ignoring entries stored on the site root; the sync now reports an error when nothing was synchronized
- Fixed failed translation tasks being reset to pending when the server could not re-queue them
- Fixed an "is not valid JSON" error after saving an AI suggestion in the file metadata form
- Fixed the metadata wizard silently doing nothing when no news detail plugin was selected
- Fixed double-escaped record titles in the AI content list

### Upgrade notes

- Please clear all caches after updating.

## [14.6.1] - 2026-09-26

### Changed

- Revised the German backend translations

### Fixed

- Fixed translating inline child records (e.g. content elements inside a news record) (fixed #43, thx @d-haythem)

### Upgrade notes

- Please clear all caches after updating.

## [14.6.0] - 2026-09-22

### Added

- Added EU AI Act transparency register
- Added dashboard cards for the AI Audit, AI Statistics and AI content modules
- Improved the audit result view

### Changed

- Audits and metadata generation keep the preview URL on the frontend inside a workspace.
- A finished translation task left without an answer is synced again so it recovers its answer.
- Prompt template and global instruction lists resolve scope and type labels from the TCA
- Removed the updateDeeplGlossaries extension setting, which isn't used anymore

### Fixed

- Fixed the metadata suggestion buttons on unsaved records, which wrote "No site configuration found for page uid 0" to the error log on every new page form (fixed issue #41).
- Fixed the disableTranslationFunctionality setting being read differently in two places, which brought back "Attempt to modify table localization without permission" with the translation functionality switched off (fixed issue #42).

### Upgrade notes

- Please clear all caches after updating and compare database.

## [14.5.0] - 2026-09-02

### Added

- Added a new "Audit" backend module with six audit types: SEO, accessibility, questions/FAQ coverage, content gap, topic clusters and a competitor comparison, run per page URL or as a batch over the whole page tree with a cost estimate up front.
- Added a "Fix with AI" wizard, AI advice per issue, an expandable more-info accordion and a CSV export on the audit result.
- Added an audit score with a page module tile, per-language results and a dashboard widget showing the score distribution.
- Added keyword candidates with search volume and difficulty, the detected search intent (which now also steers metadata generation) and an explicit search market collected from the site configurations.
- Added a shared element browser page picker used by the audit and the page tree generator, with context menu entry, result cache and one-click audit.
- Added a configurable WCAG conformance level, a default audit model and a settings screen grouped by category and subcategory.

### Fixed

- Fixed translation write-back defects: language-synchronized fields and the AI marker leaking into the DataHandler command map
- Fixed the preview URL ignoring the requested language, and vision models being recognised by their label instead of their identifier.
- Fixed unattended CLI runs, which now work and logs information

### Upgrade notes

- Please clear all caches after updating and compare database.

## [14.4.1] - 2026-08-10

### Changed

- Extended the TCA compatibility layer
- Outsourced the entries of the button bar into a single service

### Fixed

- Fixed the page tree scope being cut off below a hidden page

### Upgrade notes

- Please clear all caches after updating and compare database.

## [14.4.0] - 2026-08-04

### Added

- Added free prompt field to the metadata generation. Prompt templates gained the new scope "metadata", so templates can be maintained per metadata field.
- File metadata and file metadata translation workflows now work on several folders at once, picked with the core folder browser and combined with a depth of up to five sub levels.
- Added the pages translation workflow to the page tree context menu, gated by the same permission that guards the workflow module.

### Changed

- Page translation workflow list shows the real page status overlays and doktype icons, so hidden or scheduled pages are no longer translated by mistake.
- Single records of tables other than "pages" can now be localized through the AI Suite localization on TYPO3 v14.

### Fixed

- Fixed content elements created through the content generation missing type specific default values that Content Blocks stores in `columnsOverrides`.

### Upgrade notes

- Please clear all caches after updating.

## [14.3.0] - 2026-07-22

### Added

- Added a new statistics module.
- Added automatic translation with a sync mode and support for updating already translated content elements.
- Added page tree to the AI Suite web module and improved cross-module navigation.

### Changed

- Adjusted the field control TCA registration to prevent duplicate registrations (closes #40, thx @Teddytrombone).
- Adjusted the credit display.
- Improved dark mode styling for global instruction info boxes and the CLI module.

### Fixed

- Fixed the file list not keeping the selected folder when a file was chosen.
- Fixed RTE plugin settings not being inherited correctly in inline elements.
- Fixed the visibility of RecordLocalizeSummaryModifier (closes #39, thx @thomasrawiel).

### Upgrade notes

- Please clear all caches after updating.

## [14.1.2] - 2026-06-01

### Changed

- Added TCA functionality for AI Suite MCP tools

### Upgrade notes

- Please clear all caches after updating.

## [14.1.1] - 2026-05-27

### Changed

- Optimized "Click and Save" in TaskEngine
- Optimized session handling in AI Suite Media backend module
- Handle "Translate whole page" in one request to avoid side effects (thx @BlueChip)
- Display "Generate with AI and auto-fill in background" button in WorkflowManager only when scheduler is active and scheduler task is configured

### Fixed

- Fixed missing relation of translated elements if only child elements (IRRE) need translation (thx @dake)

### Upgrade notes

- Please clear all caches after updating.

## [14.1.0] - 2026-05-13

### Added

- Added GDPR mode to force DSGVO-compliant AI models
- Added console commands for running AI Suite operations from the CLI together with a new "CLI Overview" backend module
- Added autocheck of first image title during image generation
- Extended workspace support (e.g. for translation services)

### Changed

- Adjusted handling of missing permissions and missing sys_file records during metadata generation of sys_file_reference, thx to @helmutstrasser
- Added further functionality for MCP support

### Fixed

- Fixed save process of basic auth settings and keys within the "Global settings" tab

### Upgrade notes

- Please clear all caches after updating and compare database.

## [14.0.0] - 2026-04-23

### Changed

- TYPO3 v14 compatibility

## [13.20.0] - 2026-11-15

### Added

- Added a "View suggestions" link to finished rows in the Workflow Manager, which opens the TaskEngine with exactly that task expanded (page metadata, file references, file metadata and file metadata translations)
- Added site-wide global instructions without defined scopes

### Changed

- Simplified the global instruction form: each overview section opens the form in its own context, so records no longer move between sections by accident
- Show a missing route permission as an "Access denied" warning instead of an unexpected error

### Fixed

- Fixed translated records in every table keeping the slug of the source language. Inherited slugs are now regenerated after a translation, while slugs that editors set by hand stay untouched
- Fixed automatic translations of hidden content elements never being applied in the page module
- Fixed process-tasks failing on finished file metadata translations, which are now written like a manual acceptance
- Fixed the background generation button missing on the first view of the file workflows (thx @christophbee)
- Fixed pages below a menu separator, link pages, mount points and custom page types not being selectable in global instructions (fixed #45, thx @christophbee)
- Fixed the duplicate check of global instructions when editing an existing record

### Upgrade notes

- Please clear all caches after updating and compare database.

## [13.19.0] - 2026-10-28

### Added

- Added a comparison of the AI result with the original text in both CKEditor plugins (AI plugin and Easy Language)
- Offered workflow type, AI model and column of the "ai-suite:execute-workflow" scheduler task as dropdowns instead of free text fields (thx @cbolda)

### Changed

- Framed every section of the global settings and added a hint that the automatic generation on upload only applies to newly uploaded files (thx @cbolda)
- Enforced the feature permissions on every backend route and refused AI models the user's groups have not granted on every request, not only in the model lists
- Restricted translations to the text fields and records they were made for and only accepted safe link targets
- Scoped the glossary to the site of the translated content, also for the page translation workflow and the automatic translation on save
- Translated only the title of link fields and kept their target
- Prevented localizations based on a workspace version, which produced translations nobody could find
- Let the workflows process only targets the editor is allowed to edit and checked page access in the audits
- Hardened the fetching of external URLs (audits, media)
- Applied page global instructions from the root down to the page
- Applied the folder's global instructions to file references and gave file metadata translations their own global instructions scope
- Kept global instructions off the root level, preset their context and named context and scope in the duplicate warning
- Showed the global instructions badge next to the "Use your own prompt" toggle
- Sent each page batch of the translation workflow only once and refused a page localization without selection or whole page mode
- Rendered the AI Suite modules with the core module layout and styled the audit result tables like the core lists
- Improved labels, progress texts and German wording across the settings, audit module and workflow manager

### Upgrade notes

- Please clear all caches after updating.
- The markup of "Plain HTML" content elements is no longer translated automatically. Enable the new extension setting translatePlainHtml to translate it as before.

## [13.18.2] - 2026-10-02

### Added

- Added Midjourney 8.2 as the new default version and removed the outdated 5.x versions

### Changed

- Optimized and regrouped the extension settings tabs

### Fixed

- Fixed RTE content being lost when a record was saved before the AI plugins in CKEditor had finished loading
- Fixed the record edit dialog closing and aborting the conversion when converting text to plain language
- Fixed the configured system domain being overridden by per-site domains in bulk workflows, which caused license errors on multi-site installations
- Fixed DeepL glossary synchronization ignoring entries stored on the site root; the sync now reports an error when nothing was synchronized
- Fixed failed translation tasks being reset to pending when the server could not re-queue them
- Fixed an "is not valid JSON" error after saving an AI suggestion in the file metadata form
- Fixed the metadata wizard silently doing nothing when no news detail plugin was selected
- Fixed double-escaped record titles in the AI content list

### Upgrade notes

- Please clear all caches after updating.

## [13.18.1] - 2026-09-26

### Changed

- Revised the German backend translations

### Fixed

- Fixed translating inline child records (e.g. content elements inside a news record) (fixed #43, thx @d-haythem)

### Upgrade notes

- Please clear all caches after updating.

## [13.18.0] - 2026-09-22

### Added

- Added EU AI Act transparency register
- Added dashboard cards for the AI Audit, AI Statistics and AI content modules
- Improved the audit result view

### Changed

- Audits and metadata generation keep the preview URL on the frontend inside a workspace.
- A finished translation task left without an answer is synced again so it recovers its answer.
- Prompt template and global instruction lists resolve scope and type labels from the TCA
- Removed the updateDeeplGlossaries extension setting, which isn't used anymore

### Fixed

- Fixed the metadata suggestion buttons on unsaved records, which wrote "No site configuration found for page uid 0" to the error log on every new page form (fixed issue #41).
- Fixed the disableTranslationFunctionality setting being read differently in two places, which brought back "Attempt to modify table localization without permission" with the translation functionality switched off (fixed issue #42).

### Upgrade notes

- Please clear all caches after updating and compare database.

## [13.17.0] - 2026-09-02

### Added

- Added a new "Audit" backend module with six audit types: SEO, accessibility, questions/FAQ coverage, content gap, topic clusters and a competitor comparison, run per page URL or as a batch over the whole page tree with a cost estimate up front.
- Added a "Fix with AI" wizard, AI advice per issue, an expandable more-info accordion and a CSV export on the audit result.
- Added an audit score with a page module tile, per-language results and a dashboard widget showing the score distribution.
- Added keyword candidates with search volume and difficulty, the detected search intent (which now also steers metadata generation) and an explicit search market collected from the site configurations.
- Added a shared element browser page picker used by the audit and the page tree generator, with context menu entry, result cache and one-click audit.
- Added a configurable WCAG conformance level, a default audit model and a settings screen grouped by category and subcategory.

### Fixed

- Fixed translation write-back defects: language-synchronized fields and the AI marker leaking into the DataHandler command map
- Fixed the preview URL ignoring the requested language, and vision models being recognised by their label instead of their identifier.
- Fixed unattended CLI runs, which now work and logs information

### Upgrade notes

- Please clear all caches after updating and compare database.

## [13.16.1] - 2026-08-10

### Changed

- Extended the TCA compatibility layer
- Outsourced the entries of the button bar into a single service

### Fixed

- Fixed the page tree scope being cut off below a hidden page

### Upgrade notes

- Please clear all caches after updating and compare database.

## [13.16.0] - 2026-08-04

### Added

- Added free prompt field to the metadata generation. Prompt templates gained the new scope "metadata", so templates can be maintained per metadata field.
- File metadata and file metadata translation workflows now work on several folders at once, picked with the core folder browser and combined with a depth of up to five sub levels.
- Added the pages translation workflow to the page tree context menu, gated by the same permission that guards the workflow module.

### Changed

- Page translation workflow list shows the real page status overlays and doktype icons, so hidden or scheduled pages are no longer translated by mistake.
- Single records of tables other than "pages" can now be localized through the AI Suite localization on TYPO3 v14.

### Fixed

- Fixed content elements created through the content generation missing type specific default values that Content Blocks stores in `columnsOverrides`.

### Upgrade notes

- Please clear all caches after updating.

## [13.15.0] - 2026-07-22

### Added

- Added a new statistics module.
- Added automatic translation with a sync mode and support for updating already translated content elements.
- Added page tree to the AI Suite web module and improved cross-module navigation.

### Changed

- Adjusted the field control TCA registration to prevent duplicate registrations (closes #40, thx @Teddytrombone).
- Adjusted the credit display.
- Improved dark mode styling for global instruction info boxes and the CLI module.

### Fixed

- Fixed the file list not keeping the selected folder when a file was chosen.
- Fixed RTE plugin settings not being inherited correctly in inline elements.
- Fixed the visibility of RecordLocalizeSummaryModifier (closes #39, thx @thomasrawiel).

### Upgrade notes

- Please clear all caches after updating.

## [13.13.2] - 2026-06-01

### Changed

- Added TCA functionality for AI Suite MCP tools

### Upgrade notes

- Please clear all caches after updating.

## [13.13.1] - 2026-05-27

### Changed

- Optimized "Click and Save" in TaskEngine
- Optimized session handling in AI Suite Media backend module
- Handle "Translate whole page" in one request to avoid side effects (thx @BlueChip)
- Display "Generate with AI and auto-fill in background" button in WorkflowManager only when scheduler is active and scheduler task is configured

### Fixed

- Fixed missing relation of translated elements if only child elements (IRRE) need translation (thx @dake)

### Upgrade notes

- Please clear all caches after updating.

## [13.12.0] - 2026-04-20

### Added

- Added global settings area for managing AI Suite related configuration settings
- Added GPT Image model (1-on-1 replacement for deprecated DALL-E model)

### Changed

- Adjusted file permissions check on specific record (thx @HelmutStrasser)
- Avoided PHP warning when response does not contain expected data (fixes #36, thx @thomasrawiel)
- Replaced hardcoded path logic in PageContentFactory (fixes #35, thx @Julian93MI)
- Split and cleanup language files
- Improved controller classes structure
- Prepared extension for MCP usage

### Upgrade notes

- Please clear all caches after updating.

## [13.11.2] - 2026-03-23

### Changed

- Added AI Suite locales mapping for specific language handling (thx @BluechipZellAmSee)
- Migrated TCA deprecations and removed unused starttime/endtime fields (fixes #33, thx @georgringer)

### Fixed

- Fixed textTable not being considered as a render type (fixes #34, thx @tm-hdaoud)
- Fixed missing global instruction selection in bulk translation of pages
- Fixed global instructions incorrectly showing for DeepL and Google Translate in bulk translation of pages

### Upgrade notes

- Please clear all caches after updating.

## [13.11.1] - 2026-03-01

### Changed

- Added descriptive hints to packs and plans to clarify their scope and intended usage

### Fixed

- Fixed translation of deeply nested content elements, particularly those containing FlexForm fields (thx to @eberns)
- Fixed handling of FlexForm content embedded via TCA type flex (thx to @helsner)
- Fixed incorrect payload structure when translating content elements exclusively via WorkflowManager
- Fixed context issue causing incorrect behavior when toggling Global Instructions in the List Module (thx to @chesio, fixes #32)

### Upgrade notes

- Please clear all caches and run a database compare after updating.

## [13.11.0] - 2026-02-19

### Added

- Added possibility to generate description suggestions for file metadata
- Added description field translation support
- Extended file metadata translation to support additional file types (including videos and PDFs)
- Prepared Mittwald AI Ministral model

### Fixed

- Prevented infinite loop when reusing tt_content inline records, using depth tracker with max 5 (thx @Manfred Egger)
- Added nullable return type to getBackendUser() to prevent TypeError in AfterFileAddedEvent when executing without logged in backend user (thx @garfieldius and @chesio #29)
- Added missing description for module overview (thx @helsner, #28)
- Prevented errors in CKEditorController when no AI Suite key is available (thx @Florian Weiss)
- Solved issue with missing type cast for pageId in PagesController (thx @Stewy82, closes #30)
- Prevented submitting form if no page is selected when generating page trees

## [13.10.0] - 2026-01-19

### Added

- Generate metadata for files during file upload in filelist
- Configure amount of displayed metadata suggestions
- Consider filename (if suitable) for metadata generation
- Add records for DeepL glossaries to separate external and internal managed glossaries

### Fixed

- Fix problem with possible missing parameter “pageId” during translation of records in list module

### Upgrade notes

- Please clear all caches and compare the database after the update.
- If you get the error “Incorrect table definition; there can be only one auto column and it must be defined
- as a key on tx_aisuite_domain_model_deepl.sql“ please execute “ALTER TABLE tx_aisuite_domain_model_deepl ADD COLUMN `uid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`uid`);“ to fix the problem.

## [13.9.0] - 2025-11-22

### Changed

- Add possibility to translate file metadata for existing files (including glossary support)
- Add FieldControl for abstract field of page properties

### Fixed

- Fix correct handling of special characters during xlf translation (thx @EnesHasani) fixes #25 and #26
- Fix correct handling of nested related tables during content element generation with AI (thx @Andi Steiger)
- Ignore log entry requests in tracking handling (thx @Andi Steiger)

### Upgrade notes

- Please remember to clear all caches (also clear /var/cache/code folder) after the update!
- If you already have created DeepL glossaries, please sync them once after the update!

## [13.8.1] - 2025-10-17

### Fixed

- Check for existing selected directories in GlobalInstructionHook

## [13.8.0] - 2025-10-16

### Changed

- New feature Global Instructions
- Optimize Usability when switching page while are in Prompt Templates list view

### Fixed

- Show correct blank metadata label for newly generated records in TaskEngine (thx @jonaseberle)
- Fix error when trying to access TaskEngine in "Bring your own keys" mode (thx @piijai)
- Improve batch handling in WorkflowManager (thx to @helmutstrasser and @ajanos)

## [13.7.0] - 2025-09-22

### Changed

- Add possibility to generate metadata for non translated files
- Add Flux (Image AI) support
- Extend translation permission options

### Fixed

- Fix problem when opening translation wizard for whole page through page context menu (thx @attila)
- Fix permission for showing/hiding "Translate whole page with AI" buttons for editors (thx @attila)

## [13.6.2] - 2025-09-12

No release notes were published for this version.

## [13.6.1] - 2025-08-04

No release notes were published for this version.

## [13.6.0] - 2025-07-27

### Changed

- Look and feel adapted to TYPO3 design
- Translate whole pages including metadata and content elements
- BETA: Bulk translation of whole pages with different modes (all, only content, only metadata)

### Fixed

- Fix AI Suite CKEditor plugin integration to be compatible with latest TYPO3 13.4.15 release
- Consider special characters and add encoding to basic authentication data (thx to @Oliver Perle)
- Add missing type cast in special cases while content translation (thanks @Dirk Koritnik)

## [13.5.2] - 2025-06-09

### Changed

- Bugfix-Release Notes: v13.5.2
- Set event listener identifiers to 'tx-ai-suite' prefix so that they will be called later (e.g. to solve an issue with EXT:content_defender) (thx to @thomasrawiel and @helsner)
- Enhance translation field fetching to consider different types in elements (thx to @dld-mind, fixes #16)
- Update language handling in SiteService to consider disabled languages in frontend (thx to @geoffroycochard, fixes #14)
- Remove default values for text columns to ensure compatibility with MySQL < 8.0.13 (thanks to @thomasrawiel, fixes #15)

## [13.5.1] - 2025-05-23

### Changed

- Add TaskEngine statistics
- Automatic filter reminder in TaskEngine

### Fixed

- Add handling of large images and notifications
- Prevent generation and add notification for invalid URL input of Midjourney style reference option
- Detect api key in backend user groups if backend user group with custom key is not on first position (thanks @Dirk Koritnik)
- Add additional check for empty palettes (thanks @Robert Krämer)
- Extend validation of metadata results (thanks @helsner)

## [13.5.0] - 2025-04-26

### Added

- Glossar functionality for translations (with OpenAI, Anthropic and DeepL)
- Keep filter selection in WorkflowManager
- improve WorkflowManager and TaskEngine UI/UX

### Fixed

- Add compatibility with b13/container version 3.1.8
- Fetch content from files with special characters in WorkflowManager
- Add additional checks in ModifyNewContentElementWizardItemsEventListener to support special use cases (thx to @twojtylak)
- Find files with option "Show only in content included files" in WorkflowManager which are included in FlexForm wihtin a group field (e.g. links)
- Sort libraries before return to prevent gaps in array key numbering which leads to an error
- Don’t forget to clear all caches and update the database schema after the update!

## [13.4.1] - 2025-03-13

### Changed

- Bugfix Release: v13.4.1
- Image meta model mapping in FormEngine

## [13.4.0] - 2025-03-12

### Changed

- New Features
- Added Meta Llama-3.3 (70B Instruct)
- Refresh button for TaskEngine
- Retry function for individual tasks in TaskEngine
- Delete all buttons in TaskEngine
- Bug Fixes & Improvements
- Properly identifying and excluding unsupported image types in WorkflowManager
- Ensured proper loading of file references from record tables in TaskEngine
- Improved TaskEngine handling for a smoother experience
- Fallback to default language in CKEditor plugin when sys_language_uid = -1
- Don’t forget to clear all caches and update the database schema after the update!

### Fixed

- Fixed correct saving of translations in file metadata in TaskEngine

## [13.3.1] - 2025-02-18

### Changed

- Bugfix Release: v13.3.1
- Add FieldControl buttons for sys_file_reference fields
- Bug in TaskEngine handling
- Naming conflicts, thx to @thomasrawiel
- Don’t forget to clear all caches and update the database schema after the update!

## [13.3.0] - 2025-02-12

### Changed

- Introducing CKEditor Plugin for "simple language"
- Improving usability of TaskEngine (adding "Click and save")
- Adding compatibility with new TYPO3 version 13.4.5
- Don’t forget to clear all caches and update the database schema after the update!

## [13.2.0] - 2025-02-10

### Changed

- Introducing Flexform support for translation handling
- PHP 8.4 compatibility
- Improve language handling of WorkflowManager & TaskEngine
- Don’t forget to clear all caches and database compare!

## [13.1.2] - 2025-01-21

### Changed

- Bugfix Release: v13.1.2
- XLIFF for language german

## [13.1.1] - 2025-01-21

### Changed

- Bugfix Release: v13.1.1
- set correct reference field for fetching sys_file_metadata relations

## [13.1.0] - 2025-01-18

### Changed

- Mass actions and background task handling
- Allow HTTP Basic Auth, thx to @thomasrawiel
- Extend permission handling
- CKEditor AI plugin compatibility in terms of TYPO3 core changes
- Please remember to clear all caches after the update and update database schema!

## [13.0.2] - 2024-11-20

### Changed

- Bugfix Release: v13.0.2
- Add missing caret version range, thx to @SchmidMaxi

## [13.0.1] - 2024-11-08

### Changed

- Bugfix Release: v13.0.1
- Broken editor handling for sys_language_uid -1, thx to @d-s-codappix

## [13.0.0] - 2024-11-04

### Changed

- TYPO3 v13 compatibility
- Permission Handling via backend user groups

## [12.26.0] - 2026-11-15

### Added

- Added a "View suggestions" link to finished rows in the Workflow Manager, which opens the TaskEngine with exactly that task expanded (page metadata, file references, file metadata and file metadata translations)
- Added site-wide global instructions without defined scopes

### Changed

- Simplified the global instruction form: each overview section opens the form in its own context, so records no longer move between sections by accident
- Show a missing route permission as an "Access denied" warning instead of an unexpected error

### Fixed

- Fixed translated records in every table keeping the slug of the source language. Inherited slugs are now regenerated after a translation, while slugs that editors set by hand stay untouched
- Fixed automatic translations of hidden content elements never being applied in the page module
- Fixed process-tasks failing on finished file metadata translations, which are now written like a manual acceptance
- Fixed the background generation button missing on the first view of the file workflows (thx @christophbee)
- Fixed pages below a menu separator, link pages, mount points and custom page types not being selectable in global instructions (fixed #45, thx @christophbee)
- Fixed the duplicate check of global instructions when editing an existing record

### Upgrade notes

- Please clear all caches after updating and compare database.

## [12.25.0] - 2026-10-28

### Added

- Added a comparison of the AI result with the original text in both CKEditor plugins (AI plugin and Easy Language)
- Offered workflow type, AI model and column of the "ai-suite:execute-workflow" scheduler task as dropdowns instead of free text fields (thx @cbolda)

### Changed

- Framed every section of the global settings and added a hint that the automatic generation on upload only applies to newly uploaded files (thx @cbolda)
- Enforced the feature permissions on every backend route and refused AI models the user's groups have not granted on every request, not only in the model lists
- Restricted translations to the text fields and records they were made for and only accepted safe link targets
- Scoped the glossary to the site of the translated content, also for the page translation workflow and the automatic translation on save
- Translated only the title of link fields and kept their target
- Prevented localizations based on a workspace version, which produced translations nobody could find
- Let the workflows process only targets the editor is allowed to edit and checked page access in the audits
- Hardened the fetching of external URLs (audits, media)
- Applied page global instructions from the root down to the page
- Applied the folder's global instructions to file references and gave file metadata translations their own global instructions scope
- Kept global instructions off the root level, preset their context and named context and scope in the duplicate warning
- Showed the global instructions badge next to the "Use your own prompt" toggle
- Sent each page batch of the translation workflow only once and refused a page localization without selection or whole page mode
- Rendered the AI Suite modules with the core module layout and styled the audit result tables like the core lists
- Improved labels, progress texts and German wording across the settings, audit module and workflow manager

### Upgrade notes

- Please clear all caches after updating.
- The markup of "Plain HTML" content elements is no longer translated automatically. Enable the new extension setting translatePlainHtml to translate it as before.

## [12.24.2] - 2026-10-02

### Added

- Added Midjourney 8.2 as the new default version and removed the outdated 5.x versions

### Changed

- Optimized and regrouped the extension settings tabs

### Fixed

- Fixed RTE content being lost when a record was saved before the AI plugins in CKEditor had finished loading
- Fixed the record edit dialog closing and aborting the conversion when converting text to plain language
- Fixed the configured system domain being overridden by per-site domains in bulk workflows, which caused license errors on multi-site installations
- Fixed DeepL glossary synchronization ignoring entries stored on the site root; the sync now reports an error when nothing was synchronized
- Fixed failed translation tasks being reset to pending when the server could not re-queue them
- Fixed an "is not valid JSON" error after saving an AI suggestion in the file metadata form
- Fixed the metadata wizard silently doing nothing when no news detail plugin was selected
- Fixed double-escaped record titles in the AI content list

### Upgrade notes

- Please clear all caches after updating.

## [12.24.1] - 2026-09-26

### Changed

- Revised the German backend translations

### Fixed

- Fixed translating inline child records (e.g. content elements inside a news record) (fixed #43, thx @d-haythem)

### Upgrade notes

- Please clear all caches after updating.

## [12.24.0] - 2026-09-22

### Added

- Added EU AI Act transparency register
- Added dashboard cards for the AI Audit, AI Statistics and AI content modules
- Improved the audit result view

### Changed

- Audits and metadata generation keep the preview URL on the frontend inside a workspace.
- A finished translation task left without an answer is synced again so it recovers its answer.
- Prompt template and global instruction lists resolve scope and type labels from the TCA
- Removed the updateDeeplGlossaries extension setting, which isn't used anymore

### Fixed

- Fixed the metadata suggestion buttons on unsaved records, which wrote "No site configuration found for page uid 0" to the error log on every new page form (fixed issue #41).
- Fixed the disableTranslationFunctionality setting being read differently in two places, which brought back "Attempt to modify table localization without permission" with the translation functionality switched off (fixed issue #42).

### Upgrade notes

- Please clear all caches after updating and compare database.

## [12.23.0] - 2026-09-02

### Added

- Added a new "Audit" backend module with six audit types: SEO, accessibility, questions/FAQ coverage, content gap, topic clusters and a competitor comparison, run per page URL or as a batch over the whole page tree with a cost estimate up front.
- Added a "Fix with AI" wizard, AI advice per issue, an expandable more-info accordion and a CSV export on the audit result.
- Added an audit score with a page module tile, per-language results and a dashboard widget showing the score distribution.
- Added keyword candidates with search volume and difficulty, the detected search intent (which now also steers metadata generation) and an explicit search market collected from the site configurations.
- Added a shared element browser page picker used by the audit and the page tree generator, with context menu entry, result cache and one-click audit.
- Added a configurable WCAG conformance level, a default audit model and a settings screen grouped by category and subcategory.

### Fixed

- Fixed translation write-back defects: language-synchronized fields and the AI marker leaking into the DataHandler command map
- Fixed the preview URL ignoring the requested language, and vision models being recognised by their label instead of their identifier.
- Fixed unattended CLI runs, which now work and logs information

### Upgrade notes

- Please clear all caches after updating and compare database.

## [12.22.1] - 2026-08-10

### Changed

- Extended the TCA compatibility layer
- Outsourced the entries of the button bar into a single service

### Fixed

- Fixed the page tree scope being cut off below a hidden page

### Upgrade notes

- Please clear all caches after updating and compare database.

## [12.22.0] - 2026-08-04

### Added

- Added free prompt field to the metadata generation. Prompt templates gained the new scope "metadata", so templates can be maintained per metadata field.
- File metadata and file metadata translation workflows now work on several folders at once, picked with the core folder browser and combined with a depth of up to five sub levels.
- Added the pages translation workflow to the page tree context menu, gated by the same permission that guards the workflow module.

### Changed

- Page translation workflow list shows the real page status overlays and doktype icons, so hidden or scheduled pages are no longer translated by mistake.
- Single records of tables other than "pages" can now be localized through the AI Suite localization on TYPO3 v14.

### Fixed

- Fixed content elements created through the content generation missing type specific default values that Content Blocks stores in `columnsOverrides`.

### Upgrade notes

- Please clear all caches after updating.

## [12.21.0] - 2026-07-22

### Added

- Added a new statistics module.
- Added automatic translation with a sync mode and support for updating already translated content elements.
- Added page tree to the AI Suite web module and improved cross-module navigation.

### Changed

- Adjusted the field control TCA registration to prevent duplicate registrations (closes #40, thx @Teddytrombone).
- Adjusted the credit display.
- Improved dark mode styling for global instruction info boxes and the CLI module.

### Fixed

- Fixed the file list not keeping the selected folder when a file was chosen.
- Fixed RTE plugin settings not being inherited correctly in inline elements.
- Fixed the visibility of RecordLocalizeSummaryModifier (closes #39, thx @thomasrawiel).

### Upgrade notes

- Please clear all caches after updating.

## [12.19.2] - 2026-06-01

### Changed

- Added TCA functionality for AI Suite MCP tools

### Upgrade notes

- Please clear all caches after updating.

## [12.19.1] - 2026-05-27

### Changed

- Optimized "Click and Save" in TaskEngine
- Optimized session handling in AI Suite Media backend module
- Handle "Translate whole page" in one request to avoid side effects (thx @BlueChip)
- Display "Generate with AI and auto-fill in background" button in WorkflowManager only when scheduler is active and scheduler task is configured

### Fixed

- Fixed missing relation of translated elements if only child elements (IRRE) need translation (thx @dake)

### Upgrade notes

- Please clear all caches after updating.

## [12.19.0] - 2026-05-12

### Added

- Added GDPR mode to force DSGVO-compliant AI models
- Added console commands for running AI Suite operations from the CLI together with a new "CLI Overview" backend module
- Added autocheck of first image title during image generation
- Extended workspace support (e.g. for translation services)

### Changed

- Adjusted handling of missing permissions and missing sys_file records during metadata generation of sys_file_reference, thx to @helmutstrasser
- Added further functionality for MCP support

### Fixed

- Fixed save process of basic auth settings and keys within the "Global settings" tab

### Upgrade notes

- Please clear all caches after updating and compare database.

## [12.18.0] - 2026-04-20

### Added

- Added global settings area for managing AI Suite related configuration settings
- Added GPT Image model (1-on-1 replacement for deprecated DALL-E model)

### Changed

- Adjusted file permissions check on specific record (thx @HelmutStrasser)
- Avoided PHP warning when response does not contain expected data (fixes #36, thx @thomasrawiel)
- Replaced hardcoded path logic in PageContentFactory (fixes #35, thx @Julian93MI)
- Split and cleanup language files
- Improved controller classes structure
- Prepared extension for MCP usage

### Upgrade notes

- Please clear all caches after updating.

## [12.17.1] - 2026-03-01

### Changed

- Added descriptive hints to packs and plans to clarify their scope and intended usage

### Fixed

- Fixed translation of deeply nested content elements, particularly those containing FlexForm fields (thx to @eberns)
- Fixed handling of FlexForm content embedded via TCA type flex (thx to @helsner)
- Fixed incorrect payload structure when translating content elements exclusively via WorkflowManager
- Fixed context issue causing incorrect behavior when toggling Global Instructions in the List Module (thx to @chesio, fixes #32)

### Upgrade notes

- Please clear all caches and run a database compare after updating.

## [12.17.0] - 2026-02-19

### Added

- Added possibility to generate description suggestions for file metadata
- Added description field translation support
- Extended file metadata translation to support additional file types (including videos and PDFs)
- Prepared Mittwald AI Ministral model

### Fixed

- Prevented infinite loop when reusing tt_content inline records, using depth tracker with max 5 (thx @Manfred Egger)
- Added nullable return type to getBackendUser() to prevent TypeError in AfterFileAddedEvent when executing without logged in backend user (thx @garfieldius and @chesio #29)
- Added missing description for module overview (thx @helsner, #28)
- Prevented errors in CKEditorController when no AI Suite key is available (thx @Florian Weiss)
- Solved issue with missing type cast for pageId in PagesController (thx @Stewy82, closes #30)
- Prevented submitting form if no page is selected when generating page trees

## [12.16.0] - 2026-01-19

### Added

- Generate metadata for files during file upload in filelist
- Configure amount of displayed metadata suggestions
- Consider filename (if suitable) for metadata generation
- Add records for DeepL glossaries to separate external and internal managed glossaries

### Fixed

- Fix problem with possible missing parameter “pageId” during translation of records in list module

### Upgrade notes

- Please clear all caches and compare the database after the update.
- If you get the error “Incorrect table definition; there can be only one auto column and it must be defined
- as a key on tx_aisuite_domain_model_deepl.sql“ please execute “ALTER TABLE tx_aisuite_domain_model_deepl ADD COLUMN `uid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`uid`);“ to fix the problem.

## [12.15.0] - 2025-11-22

### Changed

- Add possibility to translate file metadata for existing files (including glossary support)
- Add FieldControl for abstract field of page properties

### Fixed

- Fix correct handling of special characters during xlf translation (thx @EnesHasani) fixes #25 and #26
- Fix correct handling of nested related tables during content element generation with AI (thx @Andi Steiger)
- Ignore log entry requests in tracking handling (thx @Andi Steiger)

### Upgrade notes

- Please remember to clear all caches (also clear /var/cache/code folder) after the update!
- If you already have created DeepL glossaries, please sync them once after the update!

## [12.14.1] - 2025-10-17

### Fixed

- Check for existing selected directories in GlobalInstructionHook

## [12.14.0] - 2025-10-16

### Changed

- New feature Global Instructions
- Optimize Usability when switching page while are in Prompt Templates list view

### Fixed

- Show correct blank metadata label for newly generated records in TaskEngine (thx @jonaseberle)
- Fix error when trying to access TaskEngine in "Bring your own keys" mode (thx @piijai)
- Improve batch handling in WorkflowManager (thx to @helmutstrasser and @ajanos)

## [12.13.0] - 2025-09-22

### Changed

- Add possibility to generate metadata for non translated files
- Add Flux (Image AI) support
- Extend translation permission options

### Fixed

- Fix problem when opening translation wizard for whole page through page context menu (thx @attila)
- Fix permission for showing/hiding "Translate whole page with AI" buttons for editors (thx @attila)

## [12.12.3] - 2025-09-12

No release notes were published for this version.

## [12.12.2] - 2025-08-04

### Changed

- Adjust language detection and add corresponding site domains in WorkflowManager filters

### Fixed

- Set correct pid of inline elements while creating content elements
- Fix file permissions check in WorkflowManager and TaskEngine (thx @helsner fixes #23)
- Adjust dark mode design (AI plugin modal and page selection field) (bearbeitet)

## [12.12.1] - 2025-07-27

### Changed

- Look and feel adapted to TYPO3 design
- Translate whole pages including metadata and content elements
- BETA: Bulk translation of whole pages with different modes (all, only content, only metadata)

### Fixed

- Fix AI Suite CKEditor plugin integration to be compatible with latest TYPO3 13.4.15 release
- Consider special characters and add encoding to basic authentication data (thx to @Oliver Perle)
- Add missing type cast in special cases while content translation (thanks @Dirk Koritnik)

## [12.12.0] - 2025-07-27

### Changed

- Bugfix-Release Notes: v12.12.0
- Look and feel adapted to TYPO3 design
- Translate whole pages including metadata and content elements
- BETA: Bulk translation of whole pages with different modes (all, only content, only metadata)

### Fixed

- Fix AI Suite CKEditor plugin integration to be compatible with latest TYPO3 13.4.15 release
- Consider special characters and add encoding to basic authentication data (thx to @Oliver Perle)
- Add missing type cast in special cases while content translation (thanks @Dirk Koritnik)

## [12.11.2] - 2025-06-09

### Changed

- Bugfix-Release Notes: v12.11.2
- Set event listener identifiers to 'tx-ai-suite' prefix so that they will be called later (e.g. to solve an issue with EXT:content_defender) (thx to @thomasrawiel and @helsner)
- Enhance translation field fetching to consider different types in elements (thx to @dld-mind, fixes #16)
- Update language handling in SiteService to consider disabled languages in frontend (thx to @geoffroycochard, fixes #14)
- Remove default values for text columns to ensure compatibility with MySQL < 8.0.13 (thanks to @thomasrawiel, fixes #15)

## [12.11.1] - 2025-05-23

### Changed

- Add TaskEngine statistics
- Automatic filter reminder in TaskEngine

### Fixed

- Add handling of large images and notifications
- Prevent generation and add notification for invalid URL input of Midjourney style reference option
- Detect api key in backend user groups if backend user group with custom key is not on first position (thanks @Dirk Koritnik)
- Add additional check for empty palettes (thanks @Robert Krämer)
- Extend validation of metadata results (thanks @helsner)

## [12.11.0] - 2025-04-26

### Added

- Glossar functionality for translations (with OpenAI, Anthropic and DeepL)
- Keep filter selection in WorkflowManager
- improve WorkflowManager and TaskEngine UI/UX

### Fixed

- Add compatibility with b13/container version 3.1.8
- Fetch content from files with special characters in WorkflowManager
- Add additional checks in ModifyNewContentElementWizardItemsEventListener to support special use cases (thx to @twojtylak)
- Find files with option "Show only in content included files" in WorkflowManager which are included in FlexForm wihtin a group field (e.g. links)
- Sort libraries before return to prevent gaps in array key numbering which leads to an error
- Don’t forget to clear all caches and update the database schema after the update!

## [12.10.1] - 2025-03-13

### Changed

- Bugfix Release: v12.10.1
- Image meta model mapping in FormEngine

## [12.10.0] - 2025-03-12

### Changed

- New Features
- Added Meta Llama-3.3 (70B Instruct)
- Refresh button for the TaskEngine
- Retry function for individual tasks in the TaskEngine
- Delete all buttons in the TaskEngine
- Bug Fixes & Improvements
- Properly identifying and excluding unsupported image types
- Ensured proper loading of file references from record tables
- Improved TaskEngine handling for a smoother experience
- Fallback to default language in CKEditor plugin when sys_language_uid = -1
- Don’t forget to clear all caches and update the database schema after the update!

### Fixed

- Fixed correct saving of translations in file metadata

## [12.9.1] - 2025-02-18

### Changed

- Bugfix Release: v12.9.1
- Add FieldControl buttons for sys_file_reference fields
- Bug in TaskEngine handling
- Naming conflicts, thx to @thomasrawiel
- Don’t forget to clear all caches and update the database schema after the update!

## [12.9.0] - 2025-02-12

### Changed

- Introducing CKEditor Plugin for "simple language"
- Improving usability of TaskEngine (adding "Click and save")
- Adding compatibility with new TYPO3 versions 13.4.5 and 12.4.27
- Don’t forget to clear all caches and update the database schema after the update!

## [12.8.0] - 2025-02-10

### Changed

- Introducing Flexform support for translation handling
- PHP 8.4 compatibility
- Improve language handling of WorkflowManager & TaskEngine
- Don’t forget to clear all caches and database compare!

## [12.7.2] - 2025-01-21

### Changed

- Bugfix Release: v12.7.2
- XLIFF for language german

## [12.7.1] - 2025-01-21

### Changed

- Bugfix Release: v12.7.1
- set correct reference field for fetching sys_file_metadata relations

## [12.7.0] - 2025-01-18

### Changed

- Mass actions and background task handling
- Allow HTTP Basic Auth, thx to @thomasrawiel
- Extend permission handling
- CKEditor AI plugin compatibility in terms of TYPO3 core changes
- Please remember to clear all caches after the update and update database schema!

## [12.6.1] - 2024-11-08

### Changed

- Bugfix Release: v12.6.1
- Broken editor handling for sys_language_uid -1, thx to @d-s-codappix

## [12.6.0] - 2024-11-04

### Changed

- Permission Handling via backend user groups
- Please remember to clear all PHP caches after updating.

## [12.5.2] - 2024-10-14

### Changed

- Bugfix Release: v12.5.2

## [12.5.1] - 2024-09-13

### Changed

- Bugfix Release: v12.5.1

## [12.5.0] - 2024-08-24

### Changed

- add new pricing model "Subscriptions"
- add Midjourney 6.1 support
- optimize translation handling

## [12.4.0] - 2024-07-29

### Changed

- Modify & generate text within CKEditor
- New APIs: DeepL, Anthropic
- Translate Content with AI, Deepl, Google Translate
- CKEditor available in content generation process

## [12.3.1] - 2024-06-05

### Changed

- Improve push notification for EXT:news meta generation when setup is not correct configured

## [12.3.0] - 2024-06-03

### Changed

- Generate news records of EXT:news
- Preparations for new subscription models
- Several improvements and optimizations
- Please consider to clear PHP caches and browser caches.

## [12.1.0] - 2024-04-17

### Changed

- Integrate Midjourney for image generation
- Add support for PHP 8.3
- Add status updates during generation
- Add searchable dropdown to page selection for page tree generation
- Add configurable storage for generated images
- Add additional field for custom image title field
- Several fixes and optimazations
- Please consider to clear /var/cache/code directory and update database schema
