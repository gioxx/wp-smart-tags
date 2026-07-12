# Changelog

All notable changes to this plugin are documented in this file.

## [0.15.3] - 2026-07-12

- Fixed the release workflow still building the zip artifact under the old `ai-tags-optimizer` slug; it's now `smart-tags-for-wordpress` to match the plugin's current name. The main plugin file, text domain, and install path inside `wp-content/plugins/` are unaffected.

## [0.15.2] - 2026-07-12

- Added a "Quick sort" row of one-click presets (Name A→Z / Z→A, Most/Least used) above the "Manage Tags" tag table, as a shortcut to the existing sortable column headers.
- The chosen sort order is now remembered per user (stored in user meta) and used as the default the next time the tab is opened, instead of always resetting to "Most used".
- Fixed the Settings page/menu title dropping "for WordPress" after the plugin rename ("Smart Tags: Settings" → "Smart Tags for WordPress: Settings").

## [0.15.1] - 2026-07-12

- Renamed the plugin to "Smart Tags for WordPress" to reflect its broader scope (AI analysis plus manual tag management, not just AI-driven optimization). Display name only: the plugin folder, main file, text domain, and internal class/constant prefixes are unchanged.
- Renamed the GitHub repository from `wp-ai-tags-optimizer` to `wp-smart-tags` and updated the `GitHub Plugin URI`/`Plugin URI` headers accordingly. The plugin was not yet public, so no migration/redirect handling was needed.

## [0.15.0] - 2026-07-12

- Fixed the usage-distribution histogram always showing zero: `get_terms()` was called with the unsupported `fields => 'id=>count'`, silently dropping the `count` property; now uses `fields => 'all'`.
- Renamed the two tabs for clarity: "Optimizer" is now "AI Analysis" (purely AI-driven), "Tag Statistics" is now "Manage Tags" (all non-AI tag housekeeping). The "Unused tags (0 posts)" section moved from "AI Analysis" into "Manage Tags", alongside a new quick-stats row (tags in use / unused tags).
- Added tag deletion to "Manage Tags": a per-row "Delete" action and a "Delete" bulk action, both with confirmation.
- The tags-per-page count on "Manage Tags" is now configurable via the native WordPress "Screen Options" panel (default stays 20), instead of being hardcoded.

## [0.14.0] - 2026-07-12

- Added a "Tag Statistics" tab (no AI involved): a searchable, sortable, paginated table of tags in use (name, slug, assigned-post count linking to the filtered post list) plus a usage-distribution histogram for an at-a-glance read of the taxonomy.
- Added a manual merge workflow on the new tab: select any 2+ tags via checkbox regardless of name, confirm which one survives, and merge immediately — no need to give unrelated tags a common search term as required by WordPress's native Tags screen, and no AI involved.

## [0.13.4] - 2026-07-12

- Moved the operational "AI Tags Optimizer" page from Tools to Posts (where tags are managed), keeping the Settings page under Tools. The two pages now cross-link to each other.

## [0.13.3] - 2026-07-12

- Added `autocomplete="off"` to all suggestion/unused-tag checkboxes so the browser no longer restores their checked state after a page reload, which was misleading since the underlying rows change on every refresh.

## [0.13.2] - 2026-07-12

- Fixed a bug where Claude could propose the same tag pair in both directions ("A into B" and "B into A") as two separate suggestions; only one direction is now kept. Added an explicit prompt rule against this, plus a code-level guard against both directions coexisting as pending or against a pair the user already rejected.
- Pending suggestions whose target tag or every source tag no longer exists (e.g. left behind by approving the opposite-direction duplicate first) are now auto-pruned on page load, same as the existing rejected-suggestions cleanup.

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
