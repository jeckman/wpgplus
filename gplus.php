<?php
/*
 * J. Eckman - editing for use with WPBOok
 */

// Based on: Dmitry Sandalov's Twitter 2 Google Plus CrossPost PHP script
// Credits for original script: 
// Luka Pusic luka@pusic.si
// Vladimir Smirnoff http://orl.sumy.ua mail@smirnoff.sumy.ua
// Kichrum http://Kichrum.org.ua

// (!) Works only with Google 2-step auth turned off AND mobile terms of service accepted

function wpgplus_safe_post_google($post_id) {
	wpgplus_debug(date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running, post_id is " . $post_id ."\n");
	wpgplus_login(wpgplus_login_data());
	wpgplus_debug(date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running,  past log in\n");
	sleep(5);
	wpgplus_update_profile_status($post_id);
	sleep(5);
	wpgplus_debug(date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running,  past update\n");
	wpgplus_logout(); 
	return true;
}

// GET: http://plus.google.com/, Parse the webpage and collect form data
// returns the login data to be used in the wpgplus_login function
function wpgplus_login_data() { 
    $wpgplusOptions = wpgplus_getAdminOptions();
	if (!empty($wpgplusOptions)) {
		foreach ($wpgplusOptions as $key => $option)
		$wpgplusOptions[$key] = $option;
	}
	$my_args = array('method' => 'GET',
					 'timeout' => '5',
					 'redirection' => '0',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'compress' => false,
					 'decompress' => true,
					 'ssl-verify' => false
					);
	$buf = wp_remote_get('https://plus.google.com/',$my_args);
	wpgplus_debug(date("Y-m-d H:i:s",time())." : just requested the login info\n");
	wpgplus_debug("\nBuffer is\n". print_r($buf,true) . "\n");
	wpgplus_debug("\nWriting cookies from login get\n");
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	foreach($buf['cookies'] as $cookie) {
		//wpgplus_debug("\nThis cookie is ". print_r($cookie,true) . "\n");
		wpgplus_set_cookie($cookie); 
	} 
    $toreturn = '';
    $doc = new DOMDocument;
    @$doc->loadHTML($buf['body']);
    $inputs = $doc->getElementsByTagName('input');
    foreach ($inputs as $input) {
		switch ($input->getAttribute('name')) {
			case 'Email': $toreturn .= 'Email=' . urlencode($wpgplusOptions['wpgplus_username']) . '&'; break;
			case 'Passwd':$toreturn .= 'Passwd=' . urlencode($wpgplusOptions['wpgplus_password']) . '&'; break;
			default: $toreturn .= $input->getAttribute('name') . '=' . urlencode($input->getAttribute('value')) . '&';
		}
    }
    // return array (string postdata, string postaction)
	$my_form = $doc->getElementsByTagName('form');
	if($my_form) {
		$my_action_url = $my_form->item(0)->getAttribute('action');	
		return array(wpgplus_tidy($toreturn), $doc->getElementsByTagName('form')->item(0)->getAttribute('action'));
	} else {
		wpgplus_debug(date("Y-m-d H:i:s",time())." : Did not get a form to post login to\n");
		wp_die('WPGPlus did not get a form in the response from google+'); 
	}	
}

// POST login: https://accounts.google.com/ServiceLoginAuth
// Follow a series of redirects and set cookies
function wpgplus_login($postdata) {
	wpgplus_debug("\nPOSTing username and pass to: " . $postdata[1] . "\n\n");
	$cookies = array();
	$my_cookie = wpgplus_get_cookie('GAPS');   // recreate cookie object
	if($my_cookie) {
		$cookies[] = $my_cookie; 	
	}
	$my_cookie2 = wpgplus_get_cookie('GALX');  // recreate cookie object
	if($my_cookie2) {
		$cookies[] = $my_cookie2; 
	}
	$my_args = array('method' => 'POST',
					 'timeout' => '45',
					 'redirection' => 0,
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'compress' => false,
					 'decompress' => true,
					 'ssl-verify' => false,
					 'body' => $postdata[0],
					 'cookies' => $cookies,
					);					    
	// First POST, to ServiceLoginAuth
	$buf = wp_remote_post($postdata[1],$my_args);
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	// need to check to see what cookies are new or updated
	// $new_cookies = array of cookies returned by post
	// $cookies = array of existing cookies sent
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 120, My Redirect was ". $my_redirect ."\n");
	$my_args = array('method' => 'GET',
					 'timeout' => '45',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'ssl-verify' => false,
					 'redirection' => 0,
					 'cookies' => $cookies,
					);
	/* POST to https://accounts.google.com/ServiceLoginAuth
	*   = 302 redirect to https://accounts.google.com/CheckCookie
	*/ 
	$buf = wp_remote_get($my_redirect,$my_args); 
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	// need to check to see what cookies are new or updated
	// $new_cookies = array of cookies returned by post
	// $cookies = array of existing cookies sent
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 154, My Redirect was ". $my_redirect ."\n");

	$my_args = array('method' => 'GET',
					 'timeout' => '45',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'ssl-verify' => false,
					 'cookies' => $cookies,
					 'redirection' => 0,
	);
	/* * GET to https://accounts.google.com/CheckCookie
	 *   = 302 redirect to https://plus.google.com/app/plus/x/?login
	 */ 
	$buf = wp_remote_get($my_redirect,$my_args);
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	// need to check to see what cookies are new or updated
	// $new_cookies = array of cookies returned by post
	// $cookies = array of existing cookies sent
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 189, My Redirect was ". $my_redirect ."\n");

	$my_args = array('method' => 'GET',
					 'timeout' => '45',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'ssl-verify' => false,
					 'cookies' => $cookies,
					 'redirection' => 0,
	);
	/* GET to https://plus.google.com/app/plus/x/?login
	 *   = 302 redirect to https://plus.google.com/app/plus/x/?login=1 
	 */ 
	$buf = wp_remote_get($my_redirect,$my_args);
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	// need to check to see what cookies are new or updated
	// $new_cookies = array of cookies returned by post
	// $cookies = array of existing cookies sent
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 224, My Redirect was ". $my_redirect ."\n");

	$my_args = array('method' => 'GET',
					 'timeout' => '45',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'ssl-verify' => false,
					 'cookies' => $cookies,
					 'redirection' => 0,
	);
	/* GET to https://plus.google.com/app/plus/x/?login=1
	 *   = 302 redirect to https://plus.google.com/app/plus/x/#/?login=1
	 */ 
	$buf = wp_remote_get($my_redirect,$my_args);
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	// need to check to see what cookies are new or updated
	// $new_cookies = array of cookies returned by post
	// $cookies = array of existing cookies sent
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
	$my_redirect = 'https://plus.google.com' . $buf['headers']['location']; // for some reason this time the url is relative
	wpgplus_debug("\nLine 259, My Redirect was ". $my_redirect ."\n");
	
	$my_args = array('method' => 'GET',
					 'timeout' => '45',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'ssl-verify' => false,
					 'cookies' => $cookies,
					 'redirection' => 0,
	);
	/* GET to https://plus.google.com/app/plus/x/#/?login=1
	 *   = 302 redirect to https://plus.google.com/app/plus/x/?v=stream
	 */
	$buf = wp_remote_get($my_redirect,$my_args);
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	// need to check to see what cookies are new or updated
	// $new_cookies = array of cookies returned by post
	// $cookies = array of existing cookies sent
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 294, My Redirect was ". $my_redirect ."\n");

	$my_args = array('method' => 'GET',
					 'timeout' => '45',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'ssl-verify' => false,
					 'cookies' => $cookies,
					 'redirection' => 0,
	);
	/* GET to https://plus.google.com/app/plus/x/?v=stream
	 *   = 302 redirect to https://plus.google.com/app/plus/x/code/?v=stream
	 */
	$buf = wp_remote_get($my_redirect,$my_args); 
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	// need to check to see what cookies are new or updated
	// $new_cookies = array of cookies returned by post
	// $cookies = array of existing cookies sent
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
    
	wpgplus_debug(date("Y-m-d H:i:s",time())." : login data posted\n");
	//wpgplus_debug("\n cookies were \n" . print_r($cookies,true) ."\n");
	//wpgplus_debug("\n Postdata was \n" . print_r($postdata[0],true) ."\n");
	wpgplus_debug("\n Response from Google was \n" . print_r($buf['body'],true) ."\n");
}
// Prepare the update, get the right form, post the update
function wpgplus_update_profile_status($post_id) {	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
	/* Set up the post */ 
	global $more; 
	$more = 0; //only the post teaser please 
	$my_post = get_post($post_id); 
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Inside update_profile_states with post_id ". $post_id ." \n");
	if(!empty($my_post->post_password)) { // post is password protected, don't post
		wpgplus_debug(date("Y-m-d H:i:s",time())." : Post is password protected\n");
		return;
	}
	if(get_post_type($my_post->ID) != 'post') { // only do this for posts
		wpgplus_debug(date("Y-m-d H:i:s",time())." : Post type is ". get_post_type($my_post->ID) ."\n");
		return;
	}
	$my_post_text = get_post_meta($post_id,'wpgplus_message',true);  // if we have a post_message
	if ($my_post_text == '') {
		if(($my_post->post_excerpt) && ($my_post->post_excerpt != '')) {
			$my_post_text = stripslashes(wp_filter_nohtml_kses(apply_filters('the_content',$my_post->post_excerpt)));
		} else { 
			$my_post_text = stripslashes(wp_filter_nohtml_kses(apply_filters('the_content',$my_post->post_content)));
		}
	}
    if(strlen($my_post_text) >= 995) {
		$space_index = strrpos(substr($my_post_text, 0, 995), ' ');
		$short_desc = substr($my_post_text, 0, $space_index);
		$short_desc .= '...';
		$my_post_text = $short_desc;
	}
	
	/* Now let's go get the form */ 
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Getting form for posting\n");
	//wpgplus_debug(date("Y-m-d H:i:s",time())." : Post text is ". $my_post_text ."\n");
	
	// These are the cookies I know of - not sure all are needed
	$cookies = array(); 
	$my_cookies = array('GAPS','GALX','NID','SID','LSID','HSID','SSID','APISID','SAPISID'); 
	foreach ($my_cookies as $name) {
		$new_cookie = wpgplus_get_cookie($name);
		if($new_cookie) {
			$cookies[] = wpgplus_get_cookie($name); 
		}
	}
	//wpgplus_debug("\nAbout to get form, cookies are ". print_r($cookies,true) ."\n"); 
	$my_args = array('method' => 'GET',
					 'timeout' => '45',
					 'redirection' => 0,
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'compress' => false,
					 'decompress' => true,
					 'ssl-verify' => false,
					 'cookies' => $cookies,
					);					    	
	// Need to get from this URL and follow redirects				
	$buf = wp_remote_request('https://m.google.com/app/plus/x/?v=compose&group=b0&hideloc=1',$my_args); 
	if(is_wp_error($buf)) {
		wp_die($buf);
	}

	// need to check to see what cookies are new or updated
	// $new_cookies = array of cookies returned by post
	// $cookies = array of existing cookies sent
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				//wpgplus_debug("\nUnsetting cookie for ". $cookies[$x]->name); 
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
	// form gets redirected to a new base url
	$my_redirect = $buf['headers']['location']; 
	//wpgplus_debug("\nShould be past redirect for form, response was ". print_r($buf,true) ."\n");
    wpgplus_debug("\nLine 415, My Redirect was ". $my_redirect ."\n");	
	//wpgplus_debug("\nGetting form at redirected url, cookies are ". print_r($cookies,true) ."\n"); 
	// need to reget the form at the new url
	$my_args = array('method' => 'GET',
					 'timeout' => '45',
					 'redirection' => 0,
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'compress' => false,
					 'decompress' => true,
					 'ssl-verify' => false,
					 'cookies' => $cookies,
					);					    	
	$buf = wp_remote_request($my_redirect, $my_args); 
	if(is_wp_error($buf)) {
		wp_die($buf);
	}
	$new_cookies = $buf['cookies'];
	for($x = count($cookies); $x>0; $x--) {
		// if cookie is already in $cookies array, we remove old version		
		foreach($new_cookies as $new_cookie) {
			if($new_cookie->name == $cookies[$x]->name) {
				//wpgplus_debug("\nUnsetting cookie for ". $cookies[$x]->name); 
				unset($cookies[$x]);
			}
		}		
	}
	// now that existing cookies are out of the array, add all back in
	foreach($new_cookies as $cookie) {
		wpgplus_set_cookie($cookie);
		$cookies[] = $cookie; 
	}
	//wpgplus_debug("\nGot new form at new url, cookies are now ". print_r($cookies,true) ."\n");
	//wpgplus_debug("\nForm being parsed is in ". $buf['body'] ."\n");
	// now we get the form inputs, including hidden ones
	$params = array();
    $doc = new DOMDocument;
    $doc->loadHTML($buf['body']);
    $inputs = $doc->getElementsByTagName('input');
    foreach ($inputs as $input) {
	    if (($input->getAttribute('type') == 'hidden')) {
			$params[$input->getAttribute('name')] =  $input->getAttribute('value');
	    }
	}
    $params['newcontent'] = $my_post_text . ' ' . get_permalink($my_post);
	$params['post'] = ' Post ';  // input type="submit" important too? 
	
	// need to determine baseul from the last get, then add the right query string
	sleep(6); 
	$baseurl = $my_redirect;
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Going to publish, params is ". print_r($params,true) ."\n");
	wpgplus_debug(date("Y-m-d H:i:s",time())." : and base url is ". $baseurl ."\n");
	
	// This last post should NOT follow redirects
   	$my_args = array('method' => 'POST',
					 'timeout' => 45,
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'redirection' => 0,
					 'blocking' => true,
					 'compress' => false,
					 'decompress' => true,
					 'ssl-verify' => false,
					 'body' => $params,
					 'cookies' => $cookies,
					 'headers' => array('Referer' => $baseurl),
					);
	$buf = wp_remote_post($baseurl . '&a=post',$my_args);
	if(is_wp_error($buf)) {
		wp_die($buf);
	}	
	$header = $buf['headers'];
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Posted form, status was ". $buf['response']['code']	. "\n");
	//wpgplus_debug(date("Y-m-d H:i:s",time())." : Post text was ". $my_post_text ."\n");	
	//wpgplus_debug(date("Y-m-d H:i:s",time())." : Header of response was ". print_r($header,true) ."\n");
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Body was ". $buf['body'] ."\n");
	// seems like the item still isn't posted at this point? 	
}

// GET logout: Just logout to look more human like and reset cookie :)
function wpgplus_logout() { 
    wpgplus_debug("\nLogging out: \n");
	// do we need to send cookies on logout?
	$my_args = array('method' => 'GET',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => false,
					 'redirection' => 0,
					);
	$buf = wp_remote_request('https://www.google.com/m/logout',$my_args); 
	if(is_wp_error($buf)) {
		wp_die($buf);
	}// should we kill cookies here or just let the transients expire?
}

function wpgplus_tidy($str) {
    return rtrim($str, "&");
}

// Expects an WP_Http_cookie object
function wpgplus_set_Cookie($my_cookie) {
	// cookies which have an expiration date and it is past should not be set
	if((!empty($my_cookie->expires)) && ($my_cookie->expires < time())) {
		//wpgplus_debug("\nNot setting expired cookie for " . $my_cookie->name . "\n");
		return false; 
	}
	if(get_transient('wpgplus_cookie_' . $my_cookie->name)) {
		//wpgplus_debug("\nUpdating cookie for " . $my_cookie->name . "\n");
	} else {
		//wpgplus_debug("\nSetting cookie for " . $my_cookie->name . "\n");
	}
	set_transient('wpgplus_cookie_'. $my_cookie->name,$my_cookie,60*60);
}

// Returns an WP_Http_Cookie object
function wpgplus_get_cookie($name) {
	$my_cookie = get_transient('wpgplus_cookie_'. $name); 
	// wpgplus_debug("\nCookie is " . print_r($my_cookie,true) . "\n");
	// Cookies which have an expiration date and it is passed should not be returned
	if(!$my_cookie || ((!empty($my_cookie->expires)) && ($my_cookie->expires < time()))) {
		//wpgplus_debug("\nNo cookies found for ". $name . "\n");
		return false; 
	} else {
		//wpgplus_debug("\nGetting cookie for ". $my_cookie->name . "\n");
		return $my_cookie;
	}
}

function wpgplus_debug($string) {
	$wpgplusOptions = wpgplus_getAdminOptions();
	if (!empty($wpgplusOptions)) {
		foreach ($wpgplusOptions as $key => $option)
		$wpgplusOptions[$key] = $option;
	}
	if($wpgplusOptions['wpgplus_debug'] == true) {
		$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/wpgplus_debug.txt';
		$fp = @fopen($wpgplus_debug_file, 'a');
		if	($fp) {
			fwrite($fp, $string);
			fclose($fp);
		} 
	} else {
		return false;
	}
}
?>