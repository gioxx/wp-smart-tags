# Changelog

All notable changes to this plugin are documented in this file.

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
