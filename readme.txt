=== Persian AI Summary ===
Contributors: byreza
Tags: ai, summary, block-editor, posts
Requires at least: 6.8
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate, edit, store, and display plain-text AI summaries for standard WordPress posts.

== Description ==

Persian AI Summary gives authorized post editors explicit control over one stored summary per
standard post. Generation uses the latest saved post content through an administrator-configured
OpenAI-compatible Chat Completions endpoint. Loading, saving, publishing, and frontend rendering
never trigger generation.

Summaries remain plain text with paragraph breaks. Editors can revise, regenerate, or remove them,
with confirmations and revision checks protecting manual work from stale AI responses.

== Installation ==

1. Install the packaged plugin directory and activate Persian AI Summary.
2. Open AI Summary > Settings as an administrator.
3. Enter the full HTTPS Chat Completions endpoint, model, default instructions, and API key.
4. Optionally set a positive input-character limit and safe frontend CSS declarations.

The API key is stored separately in a non-autoloaded server-side option. The password field is
always blank: leaving it blank preserves the stored key, entering a value replaces it, and the
checkbox explicitly removes it. Only a masked preview is displayed in the administrator page.

== Usage ==

Open a standard post and use the AI Summary document settings panel. Generation always reads the
latest saved content; save the post first when current editor changes must be included. Generated
text can be edited and saved as a manual revision. Replacing manual text or removing any summary
requires explicit confirmation.

Insert the Stored Post Summary block where the current stored summary should appear. The dynamic
block reads storage at render time, escapes the text, preserves paragraphs, and renders nothing
when no valid summary exists. It never contacts the AI service.

The Posts screen shows whether each post has a stored summary. Administrators can review recent
provider calls under AI Summary > API Call Logs, including response codes and safe failure reasons.

== Failure behavior ==

Timeouts, invalid credentials, rate limits, unavailable services, invalid responses, oversized
saved content, concurrent generation, and stale revisions return safe editor messages. Existing
summary content is preserved, normal WordPress editing and viewing continue, and retries remain an
explicit editor action. Provider response bodies, prompts, request headers, and credentials are
not exposed to browser responses or routine logs.

== Frequently Asked Questions ==

= Which content types are supported? =

Only standard WordPress posts are supported in this release.

= Does the plugin generate summaries automatically? =

No. Generation and regeneration require an authorized editor action.

= What is outside this release? =

Automatic generation, background queues, bulk processing, analytics, command-line integration,
multiple summary variants, cost tracking, other post types, provider registries, and automatic
retries are intentionally excluded.

== Changelog ==

= 1.0.0 =

* Added administrator API call logs with response codes and safe failure reasons.
* Added an AI summary status column to the Posts screen.
* Added automated plugin ZIP builds for tagged releases.

= 0.9.0 =

* Initial release.
