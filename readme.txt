=== Fast Results ===
Author URI: https://github.com/OllieJones
Plugin URI: https://plumislandmedia.net/wordpress-plugins/fast-results/
Donate link: 
Contributors:  Ollie Jones
Tags: cache, performance, pagination
Requires at least: 5.9
Tested up to: 6.4
Requires PHP: 5.6
Stable tag: 0.1.2
License: GPLv2
Github Plugin URI: https://github.com/OllieJones/fast-results
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates results pages (product pages, archive pages) fast for large sites.

== Description ==

It takes time to generate results pages, especially the pagination UI, on large sites and stores. This plugin makes that operation faster.

It requires a [persistent object cache](https://developer.wordpress.org/reference/classes/wp_object_cache/#persistent-cache-plugins) to do anything useful.

Thanks to Jetbrains for the use of their software development tools, especially [PhpStorm](https://www.jetbrains.com/phpstorm/). It's hard to imagine how a plugin like this one could be developed without PhpStorm's tools for exploring epic code bases like WordPress's.

== Frequently Asked Questions ==

= How can this plugin keep accurate track of which archive page navigation sequence is in use? =

It uses the same techniques used in WordPress core for the [WP_Query cache](https://github.com/WordPress/wordpress-develop/blob/6.3/src/wp-includes/class-wp-query.php#L3173).


== Installation ==

1. Go to `Plugins` in the Admin menu
2. Click on the button `Add new`
3. Click on Upload Plugin
4. Find `fast-results.zip` and upload it
4. Click on `Activate plugin`


== Changelog ==

= 0.1.2 October 30, 2023
Cleanup.

= 0.1.1 October 13, 2023
Birthday of Fast Results.
