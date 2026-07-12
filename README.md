# AI Tags Optimizer for WordPress

*[README disponibile anche in italiano](README.it.md)*

A WordPress plugin that analyzes your post tags with the Claude API (Anthropic) to suggest merges for duplicates/synonyms and to flag unused tags. Nothing is changed automatically — every suggestion requires manual approval.

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- An Anthropic API key

## Installation

1. Copy the whole plugin folder to `wp-content/plugins/ai-tags-optimizer/` on your WordPress site.
2. Go to **Plugins** in the WordPress dashboard and activate "AI Tags Optimizer for WordPress".

## Language

The plugin interface is in English by default; if your WordPress is set to Italian (`it_IT`), the plugin automatically loads the included Italian translation (`languages/ai-tags-optimizer-it_IT.mo`). This is independent from the language Claude uses when writing the "reason" for each suggestion, which is configured separately in Settings.

## Configuration

Go to **Tools → AI Tags Optimizer - Settings** and fill in:

| Field | Description |
|---|---|
| **Anthropic API Key** | Your Claude API key. Use the **"Test API key"** button to verify it before saving. |
| **Model** | The Claude model used for analysis, e.g. `claude-haiku-4-5`. |
| **Batch size** | Number of tags sent per API call (10-500). |
| **AI response language** | Language Claude should use for the "reason" text on each suggestion. Leave blank to match the language of your tag names automatically. |
| **Full cleanup on uninstall** | When checked (default), deleting the plugin removes its settings and suggestion/batch history from the database. |

## Running an analysis

1. Go to **Tools → AI Tags Optimizer**.
2. Click **"Start analysis"**. Tags are processed in batches, with a live processing log and progress indicator; use **"Stop analysis"** to interrupt it.
3. Once batches complete, suggestions appear grouped by type:
   - **Near-duplicates** — textual near-duplicates (typos, plurals, casing, hyphens/spaces)
   - **Semantic overlaps** — different wording, overlapping meaning
   - **Low-usage tags** — very low usage tags that could merge into a broader existing tag

## Reviewing suggestions

Each suggestion can be **Approved** (merges the source tag(s) into the target tag), **Rejected**, or later **Restored** from the "Rejected suggestions" list back to pending. Every table also supports multi-select with a "select all" checkbox and bulk **Approve selected / Reject selected / Restore selected** actions — selection and bulk actions are scoped independently per table.

## Unused tags

The "Unused tags (0 posts)" table lists tags with no post associations, with a bulk delete option. If counts look wrong (e.g. after an import), use **"Recount tag counts"** to fix them.

## Updates

The plugin is compatible with [Git Updater](https://git-updater.com/), so it can be kept up to date directly from the [GitHub repository](https://github.com/gioxx/wp-ai-tags-optimizer) without going through WordPress.org.
