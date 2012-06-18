=== WPGPlus ===
Contributors: johneckman
Tags: google plus, platform, application, blog
Stable tag: 0.8
Tested up to: 3.3.2
Requires at least: 3.2

WPGPlus posts your blog posts to Google+ when published on WordPress blog. 

== Description ==

WPGPlus posts your blog posts to Google+ when published on WordPress blog. 

Posts are made to "Public" circle. If you include an excerpt (using the
post excerpt box, not just the "teaser" before the <more> tag) it will
be used, otherwise post-content is used. 

This plugin requires PHP 5 and a compatible transport layer for WordPress
HTTP API - most likely this means cURL. 

Still working on cleaning up the output, working on getting
Google+ to recognize the links posted, show images, etc. 

This plugin is based on Dmitry Sandalov's standalone PHP script for 
publishing to Google+ from php. 

== Installation ==

1. Copy the entire wpgplus directory into your wordpress plugins folder,
   /wp-content/plugins/

2. Login to Wordpress Admin and activate the plugin

3. Using the WPGPlus menu, (Dashboard->Settings->WPGPlus) fill 
   in the appropriate information including the email address you
   use to sign in to Google+ and your Password   

== Frequently Asked Questions ==

= Why aren't my custom post types or pages posted? =

WPGPlus is currently only set to post to Google+ when the post type is "post."

To enable other post types, open wpgplus.php and find the "if" statement in the
beginning of the wpgplus_update_profile_status() function, where the code checks
for if post_type is not equal to 'post' - comment that whole if() { } section out
to get all types, or add into the if condition all the post types you want to 
specifically include. 

= Why doesn't it work for me? =

Hard to say. Most likely this is due to either:
 * server config (no available HTTP transport that can POST to and GET from google+)
 * google+ account config (if your google+ account has two factor authentication
   set, or expects confirmation of your mobile phone number, or other security
   issues
 * google+ waiting for you to accept the mobile terms of service
You can enable debugging in the wpgplus settings page and see if that produces
useful output. 

== Changelog ==

= 0.8.1 =
* Left an outdated "echo" statement in the logout function for 0.8 - fixed

= 0.8 = 
* Extensive re-write to be more "WordPress Like"
* Replaced cURL calls with wp_remote_request, wp_remote_post, and wp_remote_head
* Storing cookies returned by Google+ in WordPress transients
* Debug is now an option 

= 0.7.3 = 
* Checked for login form being presented by google else it will fail
* Wrapped calls to curl_setopt() for FOLLOWLOCATION - now checks to see if that
  setting was successful and dies if it was not. 
* Wrapped fwrite calls for debug.txt in checks for valid file pointer first.

= 0.7.2 = 
* Adding further detection for cases where Google+ returns a client-side
  redirect in response to a login request, rather than returning the right form


= 0.7.1 =
* Fixed bug in which message and publish meta were being duplicated
* Turned off debug logging including password - not good for production environment

= 0.7 = 
* Added more debugging output
* Shifted to loadHTML, suppressed warnings from loading DOM

= 0.6 = 
* Added post-meta box for Google+ message

= 0.5 =
* Initial release

== To-do ==
* Add "google+ message" meta-box rather than excerpt, allowing markdown style
* Get google+ to recognize URL of permalink and treat it as it does from
  web client
* Explore full web interface not mobile one, which is limited in functionality
