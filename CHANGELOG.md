# Changelog

All notable changes to this plugin are documented in this file.

## [0.13.1] - 2026-07-12

- Rejected suggestions are now automatically pruned when they can no longer be meaningfully restored: their target tag was deleted/merged away, or every one of their source tags was. Keeps the "Rejected suggestions" list free of orphaned entries.

## [0.13.0] - 2026-07-12

- Rejecting a suggestion now permanently suppresses that specific tag pairing (in either direction) from future analyses, as long as it stays in the "Rejected suggestions" list. Restoring a rejected suggestion lifts the suppression for that pair.

## [0.12.1] - 2026-07-12

- The "Merge into" selector on each suggestion now sits on its own line above the Approve/Reject buttons instead of being crammed inline with them, and never overflows its table column: long tag names are truncated with an ellipsis when closed, while the opened dropdown still shows the full name.

## [0.12.0] - 2026-07-12

- Analysis batches now always include the most-used tags ("anchors", up to 100) alongside a rotating slice of the rest, so low-usage/single-post tags are compared against real, popular candidates instead of whatever happens to sort near them alphabetically. Duplicate suggestions caused by anchor repetition across batches are automatically skipped.
- Each pending suggestion now has a "Merge into" selector listing every tag involved (source(s) + proposed target): pick a different one before clicking Approve to merge everything else into that tag instead of the one Claude proposed.

## [0.11.0] - 2026-07-12

- Starting a new analysis now clears any unreviewed pending suggestions and the batch log first, so results always reflect the current tag set instead of piling up on top of a stale previous run. Rejected suggestions and the applied-suggestions history are left untouched. A confirmation dialog was added before starting.
- Tightened the Claude system prompt for `semantic_overlap` and `low_usage_merge` suggestions (more prone to false positives than `near_duplicate`): now requires a confidence of 0.7+ and explicitly favors omitting a suggestion over a low-confidence one.

## [0.10.0] - 2026-07-12

- Added an "Applied suggestions (history)" table showing the most recent merges (last 50), read-only, with source/target tag names snapshotted at approval time since the source tags are deleted by the merge.
- Added stat tiles at the top of the main page: pending suggestions, merges applied, rejected suggestions, unused tags.
- Added a `wpto_db_version` upgrade check so existing installs pick up the new `applied_at`/`source_names`/`target_name` columns without reactivating the plugin.

## [0.9.0] - 2026-07-12

- Added a "Test API key" button on the Settings page, verifying the Anthropic API key against a minimal live request before saving.
- Added Git Updater support: `GitHub Plugin URI`, `GitHub Branch`, and `GitHub Languages` headers, plus `Requires at least` / `Requires PHP`, so the plugin can be updated directly from the GitHub repository.
- Added a "Settings" quick link on the Plugins list screen, and a credits/attribution footer (author + repo link, trademark notice) on the Settings page.
- Added `readme.txt` (WordPress.org style) and `README.md` / `README.it.md` (GitHub-facing docs).
- Enqueued admin assets on the Settings screen too (previously only loaded on the main Tools page).

## [0.8.0] - 2026-07-12

- Added multi-select checkboxes and bulk actions to the Suggestions tables: "Approve selected" / "Reject selected" per pending group (Near-duplicates, Semantic overlaps, Low-usage tags), and "Restore selected" for rejected suggestions. Each table has its own independent selection.
- Updated the Italian translation to cover the new bulk-action strings.

## [0.7.0] - 2026-07-12

- Made the uninstall cleanup (removing plugin data on uninstall) optional, enabled by default.

## [0.6.0] - 2026-07-12

- Added a setting for Claude's response language, independent from the plugin UI language.

## [0.5.0] - 2026-07-12

- Renamed the plugin to "AI Tags Optimizer for WordPress" (GitHub repo/folder and main plugin file renamed from `wp-tags-optimizer` to `ai-tags-optimizer`; text domain changed to `ai-tags-optimizer`).
- Switched all user-facing strings (admin UI, error messages, the Claude system prompt) to English as the source language.
- Added `load_plugin_textdomain()` and a full Italian translation, so the plugin shows in Italian on `it_IT` sites and English elsewhere.

## [0.4.0] - 2026-07-12

- Rejected suggestions are now kept instead of discarded, with a "Restore" action to bring them back to pending.

## [0.3.0] - 2026-07-12

- Added a live processing log during analysis.
- Added a stop button to interrupt an in-progress analysis.
- Fixed an issue where analysis could start twice concurrently.

## [0.2.0] - 2026-07-12

- Added a "Recount tag counts" button to fix per-tag post counts that drifted out of sync (e.g. after an import).

## [0.1.0] - 2026-07-11

- Initial implementation of the plugin (WP Tags Optimizer): tag analysis, merge suggestions, unused tag detection.
- Fixed a false-positive issue in unused tag detection.
