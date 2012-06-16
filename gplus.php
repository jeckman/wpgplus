<?php
/*
 * J. Eckman - editing for use with WPBOok
 */

// Based on:
//   Dmitry Sandalov
//   Twitter 2 Google Plus CrossPost PHP script
// Credits for original script: 
// Luka Pusic luka@pusic.si
// Vladimir Smirnoff http://orl.sumy.ua mail@smirnoff.sumy.ua
// Kichrum http://Kichrum.org.ua

// (!) Works only with Google 2-step auth turned off AND mobile terms of service accepted

function wpgplus_safe_post_google($post_id) {
	wpgplus_debug(date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running, post_id is " . $post_id ."\n");
	wpgplus_login(wpgplus_login_data());
	wpgplus_debug(date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running,  past log in\n");
	//sleep(5);
	//wpgplus_update_profile_status($post_id);
	//sleep(5);
	wpgplus_debug(date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running,  past update\n");
	//wpgplus_logout(); //optional - log out
	return true;
}

// GET: http://plus.google.com/, Parse the webpage and collect form data
function wpgplus_login_data() { 
    $wpgplusOptions = wpgplus_getAdminOptions();
	if (!empty($wpgplusOptions)) {
		foreach ($wpgplusOptions as $key => $option)
		$wpgplusOptions[$key] = $option;
	}
	$my_args = array('method' => 'GET',
					 'timeout' => '5',
					 'redirection' => '5',
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
function wpgplus_login($postdata) {
	wpgplus_debug("\nPOSTing username and pass to: " . $postdata[1] . "\n\n");
	$cookies = array();
	$my_cookie = wpgplus_get_cookie('GAPS');   // recreate cookie object
	$cookies[] = $my_cookie; 	
	$my_cookie2 = wpgplus_get_cookie('GALX');  // recreate cookie object
	$cookies[] = $my_cookie2; 		
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
	foreach($buf['cookies'] as $cookie) {
		wpgplus_set_cookie($cookie->name,$cookie->value,60*60);
		$cookies[] = $cookie;
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 105, My Redirect was ". $my_redirect ."\n");
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
	foreach($buf['cookies'] as $cookie) {
		wpgplus_set_cookie($cookie->name,$cookie->value,60*60);
		$cookies[] = $cookie;
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 126, My Redirect was ". $my_redirect ."\n");

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
	foreach($buf['cookies'] as $cookie) {
		wpgplus_set_cookie($cookie->name,$cookie->value,60*60);
		$cookies[] = $cookie;
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 148, My Redirect was ". $my_redirect ."\n");

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
	foreach($buf['cookies'] as $cookie) {
		wpgplus_set_cookie($cookie->name,$cookie->value,60*60);
		$cookies[] = $cookie;
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 170, My Redirect was ". $my_redirect ."\n");

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
	foreach($buf['cookies'] as $cookie) {
		wpgplus_set_cookie($cookie->name,$cookie->value,60*60);
		$cookies[] = $cookie;
	}
	$my_redirect = 'https://plus.google.com' . $buf['headers']['location']; // for some reason this time the url is relative
	wpgplus_debug("\nLine 191, My Redirect was ". $my_redirect ."\n");
	
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
	foreach($buf['cookies'] as $cookie) {
		wpgplus_set_cookie($cookie->name,$cookie->value,60*60);
		$cookies[] = $cookie;
	}
	$my_redirect = $buf['headers']['location'];
	wpgplus_debug("\nLine 212, My Redirect was ". $my_redirect ."\n");

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
	foreach($buf['cookies'] as $cookie) {
		wpgplus_set_cookie($cookie->name,$cookie->value,60*60);
		$cookies[] = $cookie;
	} 
    
	wpgplus_debug(date("Y-m-d H:i:s",time())." : login data posted\n");
	wpgplus_debug("\n cookies were \n" . print_r($cookies,true) ."\n");
	wpgplus_debug("\n Postdata was \n" . print_r($postdata[0],true) ."\n");
	wpgplus_debug("\n Response from Google was \n" . print_r($buf['body'],true) ."\n");
}

function wpgplus_update_profile_status($post_id) {	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
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
	
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Getting form for posting\n");
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Post text is ". $my_post_text ."\n");
	
	// what cookies exist at this point? how will I keep track?
	
	$my_args = array('method' => 'GET',
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
	$wp_remote_post('https://m.google.com/app/plus/?v=compose&group=m1c&hideloc=1',$my_args); 
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Got form, status was ". $buf['response']['code'] . "\n");
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Response was:\n". print_r($buf,true) ."\n\n");
    
    $params = '';
    $doc = new DOMDocument;
    @$doc->loadHTML($buf);
    $inputs = $doc->getElementsByTagName('input');
    foreach ($inputs as $input) {
	    if (($input->getAttribute('name') != 'editcircles')) {
		$params .= $input->getAttribute('name') . '=' . urlencode($input->getAttribute('value')) . '&';
	    }
    }
    $params .= 'newcontent=' . urlencode($my_post_text . ' ' . get_permalink($my_post) .' ');
	
    $baseurl = 'https://m.google.com/app/plus/?v=compose&group=m1c&hideloc=1';

	wpgplus_debug(date("Y-m-d H:i:s",time())." : Going to publish, params is ". $params ."\n");
	wpgplus_debug(date("Y-m-d H:i:s",time())." : and base url is ". $baseurl ."\n");
	
    
	// need to recreate cookie array here as well
	$my_args = array('method' => 'POST',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'redirection' => 0,
					 'body' => $params,
	
	
					);
	/* group=m1c is 'your circles', group=b0 is 'public' */ 			
	$buf = wp_remote_request($baseurl .'?v=compose&group=m1c&group=b0&hideloc=1&a=post',$my_args); 
	
	
	$header = $buf['headers'];
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Posted form, status was ". curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n");
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Post text was ". $my_post_text ."\n");	
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Header of response was ". print_r($header,true) ."\n");
	wpgplus_debug(date("Y-m-d H:i:s",time())." : Body was ". print_r($buf,true) ."\n");
}

// GET logout: Just logout to look more human like and reset cookie :)
function wpgplus_logout() { 
    echo "\n[+] GET Logging out: \n\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
    if(!(curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1))) {
		wpgplus_debug(date("Y-m-d H:i:s",time())." : WPGPlus could NOT set CURLOPT_FOLLOWLOCATION\n");
		wpgplus_debug("\nThis must be enabled for WPGPlus to work. Ask your hosting provider.\n");
		wp_die('WPGPlus could not set required curl option for follow redirects'); 
	};
    curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/m/logout');
    $buf = curl_exec	($ch);
    curl_close($ch);
    //if ($GLOBALS['debug']) {
	//	echo $buf;
    //}
}

function wpgplus_tidy($str) {
    return rtrim($str, "&");
}

// Expects an WP_Http_cookie object
function wpgplus_set_Cookie($my_cookie) {
	set_transient($my_cookie->name,$my_cookie,60*60);
}

function wpgplus_get_cookie($name) {
	$my_cookie = get_transient($name); 
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string .= "\nget_cookie, cookie is ". print_r($my_cookie,true) . "\n";
	if($fp) {
		fwrite($fp, $debug_string);	
	}
	return $my_cookie;
}

function wpgplus_debug($string) {
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
	$fp = @fopen($wpgplus_debug_file, 'a');
	if($fp) {
		fwrite($fp, $string);
	}
	fclose($fp); 
}


?>
