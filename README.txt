=== WPGPlus ===
Contributors: johneckman
Tags: google plus, platform, application, blog
Stable tag: 0.7.2
Tested up to: 3.3.2
Requires at least: 2.9

WPGPlus posts your blog posts to Google+ when published on WordPress blog. 

== Description ==

WPGPlus posts your blog posts to Google+ when published on WordPress blog. 

Posts are made to "Public" circle. If you include an excerpt (using the
post excerpt box, not just the "teaser" before the <more> tag) it will
be used, otherwise post-content is used. 

This plugin requires PHP 5 and the cURL library.

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


== Changelog ==

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
