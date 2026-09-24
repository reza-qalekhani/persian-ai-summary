# Persian AI Summary ✨

Generate, refine, and publish AI-powered summaries directly from the WordPress block editor—while keeping editors in control of every change.

## Features

- **AI summaries on demand** — Generate summaries only when an authorized editor requests one.
- **OpenAI-compatible services** — Connect to any compatible Chat Completions endpoint.
- **Editorial control** — Review, edit, regenerate, or remove each stored summary.
- **Manual-work protection** — Confirmation and revision checks prevent accidental overwrites.
- **Saved-content accuracy** — Generation uses the latest saved post content and clearly warns about unsaved changes.
- **Frontend summary block** — Display stored summaries without contacting the AI service during page views.
- **Custom presentation** — Set an optional title, heading level, and scoped frontend CSS.
- **Safe failure handling** — Existing summaries remain intact when the provider times out or returns an error.
- **Useful administration tools** — Test the API connection, review recent calls, and see summary status in the Posts screen.
- **Translation ready** — Includes English and Persian (Farsi) language support.

## Requirements

- WordPress 6.8 or newer
- PHP 8.3 or newer
- An OpenAI-compatible Chat Completions endpoint, API key, and model

## Installation

1. Download the latest plugin ZIP.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Select the ZIP, install it, and activate **Persian AI Summary**.
4. Open **AI Summary → Settings**.
5. Enter your HTTPS endpoint, model, summarization instructions, and API key.
6. Save the settings and use **Test API connection** to confirm the configuration.

## Usage

1. Open a standard WordPress post in the block editor.
2. Save the post so its latest content is available.
3. Open the **AI Summary** panel and select **Generate summary**.
4. Edit and save the result, regenerate it when needed, or remove it explicitly.
5. Add the **Stored Post Summary** block wherever the summary should appear on the frontend.

The plugin never generates summaries automatically. Normal post editing, publishing, and frontend rendering do not make AI requests.

## License

GPL-2.0-or-later

Made with ❤️ by [Reza Qalekhani](https://byreza.net/wordpress/persian-ai-summary-plugin/)
