# Hermes SEO Abilities

A minimal set of [Abilities](https://developer.wordpress.org/apis/abilities-api/) for an SEO agent running on WordPress. The agent can only create and update **drafts**, plus read supporting data (categories, tags, existing posts). There is no "publish", "delete", or "change settings" ability — because none were written.

## Design principle

> The agent can only do what is registered here.

- The status of a new or updated post is **hard-coded to `draft` in the code** and is not accepted from input — the agent has no way to publish anything.
- `update-draft` **refuses to act** if the post is already published — existing live content is untouchable through this plugin.
- Taxonomies (categories, tags) are only assigned from **existing** terms — the agent never creates new categories/tags.
- The Yoast SEO integration deliberately covers only three fields: **SEO title, meta description, focus keyword**. No canonical, robots, or schema — the agent should not be able to de-index a post or redirect it off-domain.
- Permissions are checked per post (`current_user_can( 'edit_post', ... )`), so a limited role (e.g. Contributor) can only see and edit their own drafts.

## Requirements

- WordPress **6.9+** — the [Abilities API](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/) has been part of core since that version; no extra plugin is required.
- PHP **7.4+**
- Optional: [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) — if inactive, SEO fields are still saved to post meta and picked up once Yoast is activated.

## Installation

1. Copy the `hermes-seo-abilities` directory into `wp-content/plugins/`.
2. Activate the plugin under **Plugins → Installed Plugins**.
3. If WordPress is older than 6.9, the plugin shows a dashboard notice instead of activating.

The plugin creates no options or database tables of its own — deactivation and removal are stateless.

## Registered abilities

| Ability | Description | Permission |
|---|---|---|
| `hermes-seo/create-draft` | Creates a new post always as a draft, with optional Yoast fields, categories, and tags. | `edit_posts` |
| `hermes-seo/update-draft` | Updates an existing draft. Refuses for published posts. | `edit_post` (per post) |
| `hermes-seo/list-categories` | Returns categories with IDs and post counts. | `edit_posts` |
| `hermes-seo/list-tags` | Returns tags with IDs and post counts. | `edit_posts` |
| `hermes-seo/search-posts` | Searches published posts by keyword — for internal linking. | `edit_posts` |
| `hermes-seo/list-drafts` | Returns drafts visible to the current account — duplicate check before creating a new post. | `edit_posts` |
| `hermes-seo/get-draft` | Returns the full data of a draft (content, SEO, taxonomies) — to verify a save. | `edit_post` (per post) |

Every ability is flagged `public` and visible through both the REST API and MCP (`mcp.public`), so it works regardless of which integration layer the agent uses.

## Yoast SEO integration

The plugin registers three Yoast post meta keys in the REST API (`wp/v2/posts`):

- `_yoast_wpseo_title`
- `_yoast_wpseo_metadesc`
- `_yoast_wpseo_focuskw`

Deliberately left out: canonical, robots (`noindex`/`nofollow`), schema — see "Design principle" above.

## License

[GPL-2.0-or-later](LICENSE)

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
