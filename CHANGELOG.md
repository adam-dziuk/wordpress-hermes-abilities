# Changelog

All notable changes to this project are documented in this file.

## [1.0.0] - First public release

- Seven Abilities for an SEO agent: `create-draft`, `update-draft`, `list-categories`, `list-tags`, `search-posts`, `list-drafts`, `get-draft`.
- Post status is hard-coded to `draft` — no ability can publish, delete, or change settings.
- Narrow Yoast SEO integration: SEO title, meta description, focus keyword only.
- Translation loading (`load_plugin_textdomain`) and a `languages/` directory with a `.pot` file.
- Dashboard notice for activation without Abilities API support (WordPress < 6.9).
