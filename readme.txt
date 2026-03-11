=== Royal Links ===
Contributors: royalpluginsteam
Tags: links, affiliate, short links, link management, click tracking
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful WordPress link management plugin for shortening, tracking, and organizing your links.

== Description ==

Royal Links is a comprehensive link management solution for WordPress that allows you to create branded short links, track clicks, and organize your affiliate and marketing links efficiently.

= Key Features =

* **Link Shortening** - Create clean, branded short URLs using your own domain
* **Multiple Redirect Types** - Support for 301, 302, and 307 redirects
* **Click Tracking** - Detailed analytics including browser, device, and referrer data
* **Link Categories & Tags** - Organize your links with categories and tags
* **Nofollow/Sponsored Attributes** - Easy compliance with search engine guidelines
* **Broken Link Detection** - Automatic monitoring for broken destination URLs
* **Import/Export** - Easily backup and migrate your links
* **Editor Integration** - Gutenberg block and Classic Editor button

= Use Cases =

* Affiliate marketers managing commission links
* Bloggers shortening long URLs for social sharing
* Businesses tracking marketing campaign performance
* Content creators organizing resource links

== Installation ==

1. Upload the `royal-links` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Royal Links in the admin menu to start creating links

== Frequently Asked Questions ==

= How do I create a short link? =

Go to Royal Links > Add New in your WordPress admin. Enter a title, destination URL, and optionally customize the slug.

= What redirect type should I use? =

For permanent redirects (most affiliate links), use 301. For temporary redirects or testing, use 302 or 307.

= Does this work with Amazon Associates? =

Yes, but Amazon requires uncloaked links. Consider uncloaking Amazon links or use the nofollow attribute only.

= Can I import links from Pretty Links or ThirstyAffiliates? =

Yes! Go to Royal Links > Import/Export and use the migration tool to import from other plugins.

== Screenshots ==

1. Link management dashboard
2. Create/edit link screen
3. Analytics dashboard
4. Link health monitoring
5. Settings page

== Changelog ==

= 1.1.3 =
* New: Redesigned dashboard widget with period-over-period comparison (30d vs previous 30d)
* New: Change badges showing click trends, new links, and unique links clicked
* New: Broken links warning bar with direct link to health checker

= 1.1.2 =
* Security: Improved sanitization of $_GET and $_FILES superglobals
* Fix: Moved all inline CSS to external stylesheet (WP.org compliance)
* Fix: Inline JavaScript now uses wp_add_inline_script() properly
* Fix: PHP limits (set_time_limit, ini_set) now scoped to batch processing only
* Updated: Chart.js upgraded to v4.5.1 (from v4.4.0)
* Updated: Contributors field corrected for WP.org username

= 1.1.1 =
* Fixed remaining "WP Links" text in comments, Gutenberg block descriptions, and admin notices
* All code references now correctly use "Royal Links" branding

= 1.1.0 =
* Rebranded internal references from wp_link to royal_link
* Updated post type slug for consistency with Royal Links Pro
* Updated all CSS classes and JS handles to use royal-links prefix

= 1.0.6 =
* Security: Additional output escaping (intval) for numeric values
* Security: Changed wp_redirect to wp_safe_redirect for safer redirects
* Security: Proper SQL query preparation with single prepare() call
* Fix: Bundled Chart.js locally (WP.org disallows external scripts)
* Fix: Moved documentation link to plugin row meta
* Fix: Removed deprecated load_plugin_textdomain (WordPress handles automatically)
* Compatibility: Tested up to WordPress 6.9

= 1.0.5 =
* Fix: Redirect 404 issue - rewrite rules now properly registered on activation
* Fix: Admin menu now displays "Royal Links" instead of "WP Links"
* Fix: Added documentation link to plugins page
* Added: Import limits info (500 links per batch) to Import/Export page

= 1.0.4 =
* Security: Fixed SQL injection vulnerabilities in analytics queries using proper $wpdb->prepare()
* Security: Added proper output escaping throughout plugin (esc_html, intval, wp_kses_post)
* Code quality: Added PHPCS ignore comments for valid file operations

= 1.0.3 =
* Added dismissible admin notices for broken link warnings
* Improved import error handling with specific error messages
* Added validation for required CSV columns on import
* Added skipped count display for import results
* Added BOM handling for Excel-exported CSV files

= 1.0.2 =
* Fixed table formatting issues on All Links page
* Improved column alignment

= 1.0.1 =
* Bug fixes and improvements

= 1.0.0 =
* Initial release
* Link shortening with custom slugs
* 301, 302, 307 redirect support
* Click tracking and analytics
* Categories and tags
* Nofollow/sponsored attributes
* Gutenberg block
* Classic Editor integration
* Import/Export functionality
* Broken link detection

== Upgrade Notice ==

= 1.0.4 =
Security hardening release with proper SQL escaping and output sanitization.

= 1.0.3 =
Improved import error handling and dismissible admin notices.

= 1.0.0 =
Initial release of Royal Links.
