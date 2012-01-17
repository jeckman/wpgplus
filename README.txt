=== WPGPlus ===
Contributors: johneckman
Tags: google plus, platform, application, blog
Stable tag: 0.5
Tested up to: 3.3
Requires at least: 2.9

WPGPlus posts your blog posts to Google+ when published on WordPress blog. 

Posts are made to "Public" circle. If you include an excerpt (using the
post excerpt box, not just the "teaser" before the <more> tag) it will
be used, otherwise post-content is used. 

Still working on cleaning up the output, working on getting
Google+ to recognize the links posted, show images, etc. 

But it is working at Proof of concept stage.  

== Description ==

WPGPlus posts your blog posts to Google+ when published on WordPress blog. 

Posts are made to "Public" circle. 

This plugin requires PHP 5. 

This plugin is based on Dmitry Sandalov's standalone PHP script for 
publishing to Google+ from php. 

See https://github.com/DmitrySandalov/twitter2gplus

== Installation ==

1. Copy the entire wpgplus directory into your wordpress plugins folder,
   /wp-content/plugins/

2. Login to Wordpress Admin and activate the plugin

3. Using the WPGPlus menu, (Dashboard->Settings->WPGPlus) fill 
   in the appropriate information including the email address you
   use to sign in to Google+ and your Password   

== Frequently Asked Questions ==


== Changelog ==

= 0.5 =
* Initial release

== To-do ==
* Add "google+ message" meta-box rather than excerpt, allowing markdown style
* Get google+ to recognize URL of permalink and treat it as it does from
  web client
* Explore full web interface not mobile one, which is limited in functionality
