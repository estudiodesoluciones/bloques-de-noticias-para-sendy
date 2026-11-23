=== Estudio de Soluciones – Bloques de noticias para Sendy ===
Contributors: estudiodesoluciones
Tags: newsletter, sendy, email, news
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Select WordPress posts, customize their layout and create a draft in Sendy, or export the HTML to use in your newsletter.

== Description ==

Build a newsletter from up to 100 published posts. Filter by category or date, reorder stories and choose the images, excerpts and buttons to include. Preview the result and copy or download the HTML. To create a draft directly, connect your own Sendy installation over HTTPS. Campaign delivery stays under your control in Sendy.

An independent integration created by [Estudio de Soluciones](https://estudiodesoluciones.com). It is not affiliated with or endorsed by Sendy.

== Installation ==

1. Upload the plugin ZIP under Plugins > Add New and activate it.
2. Open Estudio de Soluciones – Bloques de noticias para Sendy in the admin menu. Select public posts, arrange them and generate a preview.
3. Copy the story fragment or download the whole email as HTML.
4. To create a draft directly, enter your Sendy HTTPS URL, API key, brand ID, sender and reply-to address in the settings. Save and test the connection.
5. Create the draft and open it in Sendy. In Sendy 7, choose Drag & Drop to continue editing.

== Frequently Asked Questions ==

= Where can I install Sendy? =

Use a publicly reachable HTTPS installation, including one hosted on another server. The plugin verifies certificates and does not follow redirects with your API key.

= Which posts can I select? =

Select up to 100 public posts per newsletter. Private, scheduled and password-protected posts are excluded. Previews expire after one hour. The interface warns when the email exceeds 95 KB.

= What if the connection fails? =

HTML export remains available. Check your drafts before trying again because a timeout may occur after Sendy creates the draft. Generate a fresh preview for a new attempt.

= How is the API key stored? =

The plugin uses OpenSSL and AES-256-GCM with a key derived from WordPress salts. Enter the API key again after changing the salts. Alternatively, define ES_SN_API_KEY in wp-config.php.

= What happens on uninstall? =

Deactivation preserves settings. Uninstalling removes the settings, stored key, saved design and temporary plugin data for this site. Drafts remain in Sendy. Activate the plugin per site.

== External services ==

HTML export works without an external service. Testing the connection sends the API key to the Sendy installation configured by the administrator. Creating a draft also sends the brand ID, sender and reply-to details, subject, HTML and plain text. The plugin does not request subscriber lists or send campaigns. Images are referenced by URL and may be loaded from their host when previewed. The administrator chooses and operates the Sendy endpoint. [Sendy API documentation](https://sendy.co/api).

== Changelog ==

= 1.0.0 =
Initial release with post selection, filters, layout controls, HTML export and draft creation via the Sendy API.
