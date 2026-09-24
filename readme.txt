=== Persian AI Summary ===
Contributors: Reza Qalekhani
Tags: ai, summaries, block editor, content, openai
Requires at least: 6.8
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 8.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate, edit, store, and display AI summaries for WordPress posts with an OpenAI-compatible service.

== Description ==

Persian AI Summary gives editors full control over one stored summary for each standard WordPress post.

Connect an OpenAI-compatible Chat Completions service, generate a summary from the latest saved post content, refine it in the block editor, and display it with the included dynamic block.

Features include:

* On-demand summary generation from saved post content.
* Manual editing, confirmed regeneration, and explicit removal.
* Revision protection that preserves newer editorial changes.
* A Stored Post Summary block with no frontend AI requests.
* Configurable instructions, model, input limit, frontend title, heading level, and scoped CSS.
* API connection testing and recent call logs for administrators.
* Summary status in the Posts screen.
* Clear handling of timeouts, rate limits, invalid credentials, and malformed responses.
* English and Persian (Farsi) translations.

Generation is always initiated by an authorized editor. Loading, saving, publishing, and viewing posts do not automatically contact the AI service.

== Installation ==

1. Upload the plugin ZIP through **Plugins > Add New Plugin > Upload Plugin**, or copy the `persian-ai-summary` folder to `/wp-content/plugins/`.
2. Activate **Persian AI Summary** from the Plugins screen.
3. Open **AI Summary > Settings**.
4. Enter the HTTPS Chat Completions endpoint, model, default instructions, and API key.
5. Save the settings and use **Test API connection** to confirm the service is available.
6. Open a standard post, save its latest content, and use the **AI Summary** panel to generate a summary.
7. Add the **Stored Post Summary** block where the summary should appear on the frontend.

== Frequently Asked Questions ==

= Which content types are supported? =

This release supports standard WordPress posts.

= Does the plugin generate summaries automatically? =

No. Generation and regeneration require an explicit action from a user who can edit the post.

= Does generation include unsaved editor changes? =

No. The plugin uses the latest saved post content and warns when unsaved changes are present.

= Can I edit an AI-generated summary? =

Yes. You can edit and save the summary as plain text. Replacing manually edited text requires confirmation.

= Does the frontend block contact the AI service? =

No. It reads the stored summary only and renders nothing when no summary exists.

= Which AI providers are supported? =

You can use a service that provides an OpenAI-compatible Chat Completions endpoint.

== External services ==

This plugin connects to the OpenAI-compatible endpoint configured by the site administrator.

When an authorized editor requests generation, the plugin sends the selected model, configured instructions, and latest saved post content to that service. The configured API key is sent in the authorization header. The **Test API connection** action sends a small connection-check request. No request is made during normal post editing, publishing, or frontend rendering.

Data handling, terms, and privacy policies depend on the service selected by the site administrator.

== Changelog ==

= 1.0.0 =

* Added on-demand generation through OpenAI-compatible services.
* Added manual editing, regeneration, removal, and conflict protection.
* Added administrator settings, connection testing, and recent API call logs.
* Added the dynamic Stored Post Summary block and frontend presentation controls.
* Added English and Persian translations.

== Upgrade Notice ==

= 1.0.0 =

Initial public release of Persian AI Summary.
