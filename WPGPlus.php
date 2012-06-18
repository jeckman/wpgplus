<?php
/*
Plugin Name: WPGplus
Date: 2012, June 18th
Description: Plugin to cross-post WordPress blog posts to Google+ 
Author: John Eckman
Author URI: http://johneckman.com
Version: 0.8.1
Stable tag: 0.8.1
*/
  
/*
 * Note: This plugin draws heavily on Dmitry Sandalov's standalone
 * script for publishing to Google+ from php. 
 * See https://github.com/DmitrySandalov/twitter2gplus
 *
 * I've just converted it for WordPress
 */

/*  
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
  
// this function checks for admin pages
if (!function_exists('is_admin_page')) {
  function is_admin_page() {
    if (function_exists('is_admin')) {
      return is_admin();
    }
		if (function_exists('check_admin_referer')) {
			return true;
		}
		else {
			return false;
		}
  }
}

function wpgplus_is_authorized() {
  global $user_level;
	if (function_exists("current_user_can")) {
		return current_user_can('activate_plugins');
	} else {
		return $user_level > 5;
	}
}

function wpgplus_getAdminOptions() {
	$wpgplusOptions = get_option('wpgplusOptions');
	if (!empty($wpgplusOptions)) {
		foreach ($wpgplusOptions as $key => $option)
			$wpgplusOptions[$key] = $option;  
	}
	return $wpgplusOptions;
}
  
function wpgplus_setAdminOptions($wpgplus_username,$wpgplus_password,$wpgplus_debug) {
  $wpgplusOptions = array('wpgplus_username' => $wpgplus_username,
                          'wpgplus_password' => $wpgplus_password,
						  'wpgplus_debug' => $wpgplus_debug,	  
                              );
  update_option('wpgplusOptions', $wpgplusOptions);
}
  
add_action('admin_menu', 'wpgplus_options_page');						   
function wpgplus_options_page() {
	if (function_exists('add_options_page')) {
		$wpgplus_plugin_page = add_options_page('WPGPlus', 'WPGPlus', 8, basename(__FILE__), 'wpgplus_subpanel');
	}
} 

function wpgplus_subpanel() {
	if (wpgplus_is_authorized()) {
		global $current_user;
		get_currentuserinfo(); 
		$wpgplusOptions = wpgplus_getAdminOptions();
		
		// if we're posting
		if (isset($_POST['wpgplus_username']) && isset($_POST['wpgplus_password'])  
				&& (!empty($_POST['wpgplus_username']))  && (!empty($_POST['wpgplus_password']))) { 
			$wpgplus_username = $_POST['wpgplus_username'];
			$wpgplus_password = $_POST['wpgplus_password'];
			$wpgplus_debug = $_POST['wpgplus_debug'];
			wpgplus_setAdminOptions($wpgplus_username, $wpgplus_password, $wpgplus_debug);
			$flash = "Your settings have been saved. ";
		} elseif (($wpgplusOptions['wpgplus_username'] != "") && ($wpgplusOptions['wpgplus_password'] != "")){
			$flash = "";
		} else {
			$flash = "Please complete all necessary fields";
		} // end of posting complete
	} else {
		$flash = "You don't have enough access rights.";
	}  // end of is first is_authorized  
  
	if (wpgplus_is_authorized()) {
		$wpgplusOptions = wpgplus_getAdminOptions();
		if ($flash != '')
			echo '<div id="message"class="updated fade"><p>' . $flash . '</p></div>'; 
		$plugin_url = WP_PLUGIN_URL.'/wpgplus';
		?>
		<div class="wrap">
			<div class="icon32" id="icon-plugins"><br/></div>
				<h2>WPGPlus Setup</h2>
					<p>This plugin cross posts new blog posts to Google+</p>
					<!-- START Required Options --> 
				<?php 
				echo '<form action="'. $_SERVER["REQUEST_URI"] .'" method="post">'; 
				echo '<p>Google Plus Email Address: <input type="text" name="wpgplus_username" value="';
				echo htmlentities($wpgplusOptions['wpgplus_username']) .'" size="35" /></p>';
				echo '<p>Google Plus Password: ';
				echo '<input type="password" name="wpgplus_password" value="';
				echo htmlentities($wpgplusOptions['wpgplus_password']) .'" size="35" /></p>';
			    echo '<p>Enable debug file: <input type="checkbox" name="wpgplus_debug" value="true" ';
				if( htmlentities($wpgplusOptions['wpgplus_debug']) == "true") {
					echo("checked");
				}
				echo ' id="wpgplus_debug" ></p>';
				echo '<p><input type="submit" value="Save" class="button-primary"';
				echo ' name="wpgplus_save_button" /></form></p>';
	} else {
			echo '<div class="wrap"><p>Sorry, you are not allowed to access ';
			echo 'this page.</p></div>';
	}
}  // end 	
  
/*
 * This function handles publish to Google+. 
 */
function wpgplus_publish_to_gplus($post) {
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
	if (!version_compare(PHP_VERSION, '5.0.0', '<')) {
		include_once(WP_PLUGIN_DIR .'/wpgplus/gplus.php');
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : publish_to_gplus running, included wpgplus.php\n";
		if($fp) {
			fwrite($fp, $debug_string);
		} 
	} else {
		wp_die("Sorry, but you can't run this plugin, it requires PHP 5 or higher.");
	}	
	$publish_meta = get_post_meta($post->ID,'wpgplus_publish',true); 
	if(($publish_meta == 'no')) { // user chose not to post this one
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : Post_meta was set to not publish, postID is " . $my_post_id ."\n";
		if($fp) {
			fwrite($fp, $debug_string);
		}
		return;
	}
	$my_post_id = $post->ID;
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : publish_to_gplus running, postID is " . $my_post_id ."\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
	if($my_post_id && ($my_post_id != '')) {
		wpgplus_safe_post_google($my_post_id);
	}
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : publish_to_gplus done running\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
	
} // end of function wpgplus_publish_to_gplus
  
/*
 * Use postmeta to enable users to turn off streaming on case-by-case basis
 * Based on how Alex King's Twitter Tools handles the same case for pushing
 * posts to twitter
 */
function wpgplus_meta_box() {
	global $post;
	$wpgplus_publish = get_post_meta($post->ID, 'wpgplus_publish', true);
	$wpgplus_message = get_post_meta($post->ID, 'wpgplus_message', true); 
	if ($wpgplus_publish == '') {
		$wpgplus_publish = 'yes';
	}
	echo '<p>'.__('Publish this post to Google Plus?', 'wpgplus').'<br/>';
	echo '<input type="radio" name="wpgplus_publish" id="wpgplus_publish_yes" value="yes" ';
	checked('yes',$wpgplus_publish);
	echo ' /> <label for="wpgplus_publish_yes">'.__('Yes', 'wpgplus').'</label> &nbsp;&nbsp;';
	echo '<input type="radio" name="wpgplus_publish" id="wpgplus_publish_no" value="no" ';
	checked('no',$wpgplus_publish);
	echo ' /> <label for="wpgplus_publish_no">'.__('No', 'wpgplus').'</label>';
	echo '</p>';
	echo '<p>'.__('Message for Google+ post: (use google+ markup)','wpgplus').'<br/>';
	echo '<p><textarea cols="60" rows="4" style="width:95%" name="wpgplus_message" id="wpgplus_message">';
	echo $wpgplus_message;
	echo '</textarea></p>';
  
	do_action('wpgplus_store_post_options');
}
  
function wpgplus_add_meta_box() {
	global $wp_version;
	if (version_compare($wp_version, '2.7', '>=')) {
		add_meta_box('wpgplus_post_form','WPGPlus', 'wpgplus_meta_box', 'post', 'side');
	} else {
		add_meta_box('wpgplus_post_form','WPGPlus', 'wpgplus_meta_box', 'post', 'normal');
	}
}
  
function wpgplus_store_post_options($post_id, $post = false) {  
	$wpgplusOptions = wpgplus_getAdminOptions();
	$post = get_post($post_id);
	$stored_meta = get_post_meta($post_id, 'wpgplus_publish', true);
	$posted_meta = $_POST['wpgplus_publish'];
	$wpgplus_message = $_POST['wpgplus_message'];
  
	$save = false;
	/* if there is $posted_meta, that takes priority over stored */
	if (!empty($posted_meta)) { 
		$posted_meta == 'yes' ? $meta = 'yes' : $meta = 'no';
		$save = true;
	}
	/* if no posted meta, check stored meta */ 
	else if (empty($stored_meta)) {
		$meta = 'yes';
		$save = true;
		/* if there is stored meta, and user didn't touch it, don't save */ 
	} else {
		$save = false;
	}
    
	update_post_meta($post_id, 'wpgplus_message',$wpgplus_message); 
	if ($save) {
		update_post_meta($post_id, 'wpgplus_publish', $meta);
	}
}


add_action('draft_post', 'wpgplus_store_post_options', 1, 2);
add_action('publish_post', 'wpgplus_store_post_options', 1, 2);
add_action('save_post', 'wpgplus_store_post_options', 1, 2);
 
/**
  * Thanks Otto - http://lists.automattic.com/pipermail/wp-hackers/2009-July/026759.html
  */
function wpgplus_activation_check(){
  global $wp_version;
  if (version_compare(PHP_VERSION, '5.0.0', '<')) {
    deactivate_plugins(basename(__FILE__)); // Deactivate ourself
    wp_die("Sorry, but you can't run this plugin, it requires PHP 5 or higher.");
  }
  if (version_compare($wp_version, '2.9', '<')) {
    wp_die("This plugin requires WordPress 2.6 or greater.");
  }
}
  
// thanks http://wpengineer.com/35/wordpress-plugin-deinstall-data-automatically/ 
 /**
 * Check for uninstall hook
 */
if ( function_exists('register_uninstall_hook') )
	register_uninstall_hook(__FILE__, 'wpgplus_deinstall');

/**
 * Delete options in database
 */
function wpgplus_deinstall() {
	delete_option('wpgplusOptions');
}  
 

add_action('admin_menu', 'wpgplus_options_page');
add_action('admin_menu', 'wpgplus_add_meta_box');
  
// these capture new posts, not edits of previous posts	
add_action('future_to_publish','wpgplus_publish_to_gplus');	
add_action('new_to_publish','wpgplus_publish_to_gplus');
add_action('draft_to_publish','wpgplus_publish_to_gplus');  
add_action('pending_to_publish','wpgplus_publish_to_gplus');

?>
