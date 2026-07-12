=== AI Tags Optimizer for WordPress ===
Contributors: gioxx
GitHub Plugin URI: https://github.com/gioxx/wp-ai-tags-optimizer
Tags: tags, ai, claude, taxonomy, cleanup
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Analyzes WordPress tags with the Claude API to suggest merges for duplicates/synonyms and flag unused tags, always with manual approval.

== Description ==
AI Tags Optimizer for WordPress sends your post tags to the Claude API (Anthropic) in batches and asks it to spot near-duplicates, semantic overlaps, and low-usage tags that could be merged into a broader existing tag. Nothing is changed automatically: every suggestion is queued for review and only applied when you approve it.

Features:
* Batch analysis of all tags via the Claude API, with a live processing log, progress tracking, and a stop button
* Merge suggestions grouped by type: near-duplicates, semantic overlaps, low-usage tags
* Per-suggestion Approve/Reject/Restore, plus multi-select bulk actions (bulk approve, bulk reject, bulk restore) on each suggestions table
* Rejected suggestions are kept (not discarded) and can be restored to pending at any time
* Unused tag detection (0 posts) with a bulk delete and a "recount tag counts" tool to fix drifted counts
* Configurable AI model, batch size, and response language for Claude's suggestion "reason" text, independent of the plugin's own UI language
* "Test API key" button to verify the Anthropic API key before saving
* English by default, with an included Italian translation
* Git Updater compatible for seamless updates from GitHub

== Installation ==
1. Copy the whole plugin folder to `wp-content/plugins/ai-tags-optimizer/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Tools → AI Tags Optimizer - Settings** and enter your Anthropic API key.
4. Go to **Tools → AI Tags Optimizer** and click "Start analysis".

== Frequently Asked Questions ==
= Does this change my tags automatically? =
No. Every merge suggestion requires manual approval before anything is changed.

= What happens to a rejected suggestion? =
It's kept in a "Rejected suggestions" list and can be restored to pending at any time, individually or in bulk.

= Can I delete tags with 0 posts? =
Yes, the "Unused tags" table lists them with a bulk delete option.

== Changelog ==
= 0.9.0 =
* Added a "Test API key" button on the Settings page to verify the Anthropic API key before saving.
* Added Git Updater support (GitHub Plugin URI/Branch/Languages headers) for direct updates from the GitHub repository.
* Added a "Settings" quick link on the Plugins list screen, and a credits/attribution footer on the Settings page.

= 0.8.0 =
* Added multi-select checkboxes and bulk actions (Approve/Reject/Restore) to the Suggestions tables.

= 0.7.0 =
* Made the uninstall cleanup optional, on by default.

= 0.6.0 =
* Added a setting for Claude's response language, independent of the plugin UI language.

= 0.5.0 =
* Renamed the plugin to "AI Tags Optimizer for WordPress"; switched to English as the source language with a full Italian translation.

= 0.4.0 =
* Rejected suggestions are now kept instead of discarded, with a "Restore" action.

= 0.3.0 =
* Added a live processing log, a stop button, and fixed duplicate analysis starts.

= 0.2.0 =
* Added a "Recount tag counts" button.

= 0.1.0 =
* Initial implementation: tag analysis, merge suggestions, unused tag detection.

== Upgrade Notice ==
= 0.5.0 =
Plugin, folder and text domain were renamed from wp-tags-optimizer to ai-tags-optimizer; reinstall from the new plugin folder if updating manually.
