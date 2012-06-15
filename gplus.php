<?php
/*
 * J. Eckman - editing for use with WPBOok
 */

// Based on:
//   Dmitry Sandalov
//   Twitter 2 Google Plus CrossPost PHP script
//   v0.2

// Credits: 
// Luka Pusic luka@pusic.si
// Vladimir Smirnoff http://orl.sumy.ua mail@smirnoff.sumy.ua
// Kichrum http://Kichrum.org.ua

// (!) Works only with Google 2-step auth turned off AND mobile terms of service accepted

$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';

function wpgplus_safe_post_google($post_id) {
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running, post_id is " . $post_id ."\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
	wpgplus_login(wpgplus_login_data());
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running,  past log in\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
	//sleep(5);
	//wpgplus_update_profile_status($post_id);
	//sleep(5);
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running,  past update\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
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
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
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
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : just requested the login info\n";
	$debug_string .= "\nBuffer is\n". print_r($buf,true) . "\n";
	$my_cookie = $buf['cookies'][0]->value; // this is a WP_Http_cookie object
	$my_cookie2 = $buf['cookies'][1]->value; 
	set_transient('wpgplus_cookies',$my_cookie,60*60); // set for 1 hr
	set_transient('wpgplus_expires',$buf['cookies'][0]->expires,60*60);
	set_transient('wpgplus_cookie2',$my_cookie2,60*60); 
	$debug_string .= "\nCookie was ". $my_cookie . "\n";
	$debug_string .= "\nCookie2 was ". $my_cookie2 . "\n";


	if($fp) {
		fwrite($fp, $debug_string);	
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
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : Did not get a form to post login to\n";
		if($fp) {
			fwrite($fp, $debug_string);	
		}
		wp_die('WPGPlus did not get a form in the response from google+'); 
	}	
}

// POST login: https://accounts.google.com/ServiceLoginAuth
function wpgplus_login($postdata) {
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
	$debug_string = "\n[+] POSTing username and pass to: " . $postdata[1] . "\n\n";
	$my_cookie = new WP_Http_Cookie('GAPS');  // recreate cookie object
	$my_cookie->name = 'GAPS';
	$my_cookie->value = get_transient('wpgplus_cookies');
	$my_cookie->expires = get_transient('wpgplus_expires');
	$my_cookie->path = '/';
	$my_cookie->domain = '';
	$my_cookie->secure = '';
	$my_cookie->httponly = '';
	$cookies[] = $my_cookie; 	
	$my_cookie2 = new WP_Http_Cookie('GALX');  // recreate cookie object
	$my_cookie2->name = 'GALX';
	$my_cookie2->value = get_transient('wpgplus_cookie2');
	$my_cookie2->expires = '';
	$my_cookie2->path = '/';
	$my_cookie2->domain = '';
	$my_cookie2->secure = '';
	$cookies[] = $my_cookie2; 	
	
	$my_args = array('method' => 'POST',
					 'timeout' => '45',
					 'redirection' => '5',
					 'user-agent' => 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)',
					 'blocking' => true,
					 'compress' => false,
					 'decompress' => true,
					 'ssl-verify' => false,
					 'body' => $postdata[0],
					 'cookies' => $cookies,
					);					    
	$buf = wp_remote_post($postdata[1],$my_args);     
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string .=date("Y-m-d H:i:s",time())." : login data posted\n";
	$debug_string .= "\n cookies were \n" . print_r($cookies,true) ."\n";
	$debug_string .= "\n Postdata was \n" . print_r($postdata[0],true) ."\n";


	//$debug_string .= date("Y-m-d H:i:s",time())." : status code was ". curl_getinfo($ch, CURLINFO_HTTP_CODE) ."\n";
	$debug_string .= "\n Response from Google was \n" . print_r($buf['body'],true) ."\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
}

function wpgplus_update_profile_status($post_id) {
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
	global $more; 
	$more = 0; //only the post teaser please 
	$my_post = get_post($post_id); 
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : Inside update_profile_states with post_id ". $post_id ." \n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
	if(!empty($my_post->post_password)) { // post is password protected, don't post
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : Post is password protected\n";
		if($fp) {
			fwrite($fp, $debug_string);
		}
		return;
	}
	if(get_post_type($my_post->ID) != 'post') { // only do this for posts
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : Post type is ". get_post_type($my_post->ID) ."\n";
		if($fp) {
			fwrite($fp, $debug_string);
		}
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
	
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : Getting form for posting\n";
	$debug_string .= date("Y-m-d H:i:s",time())." : Post text is ". $my_post_text ."\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
	
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
    curl_setopt($ch, CURLOPT_URL, 'https://m.google.com/app/plus/?v=compose&group=m1c&hideloc=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if(!(curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1))) {
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : WPGPlus could NOT set CURLOPT_FOLLOWLOCATION\n";
		$debug_string .= "\nThis must be enabled for WPGPlus to work. Ask your hosting provider.\n";
		if($fp) {
			fwrite($fp, $debug_string);	
		}	
		wp_die('WPGPlus could not set required curl option for follow redirects'); 
	};
    $buf = utf8_decode(html_entity_decode(str_replace('&', '', curl_exec($ch))));
    $header = curl_getinfo($ch);
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : Got form, status was ". curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
	$debug_string .= date("Y-m-d H:i:s",time())." : Response was:\n". $buf ."\n\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
    curl_close($ch);
    
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
	
    //$baseurl = $doc->getElementsByTagName('base')->item(0)->getAttribute('href');
    $baseurl = 'https://m.google.com' . parse_url($header['url'], PHP_URL_PATH);

	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : Going to publish, params is ". $params ."\n";
	$debug_string=date("Y-m-d H:i:s",time())." : and base url is ". $baseurl ."\n";
	if($fp) {
		fwrite($fp, $debug_string);
	}
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
	/* group=m1c is 'your circles', group=b0 is 'public' */ 
    curl_setopt($ch, CURLOPT_URL, $baseurl . '?v=compose&group=m1c&group=b0&hideloc=1&a=post');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if(!(curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1))) {
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : WPGPlus could NOT set CURLOPT_FOLLOWLOCATION\n";
		$debug_string .= "\nThis must be enabled for WPGPlus to work. Ask your hosting provider.\n";
		if($fp) {
			fwrite($fp, $debug_string);	
		}	
		wp_die('WPGPlus could not set required curl option for follow redirects'); 
	};
    curl_setopt($ch, CURLOPT_REFERER, $baseurl . '?v=compose&group=m1c&group=b0&hideloc=1');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $buf = curl_exec($ch);
    $header = curl_getinfo($ch);
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : Posted form, status was ". curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
	$debug_string .= date("Y-m-d H:i:s",time())." : Post text was ". $my_post_text ."\n";	
	$debug_string .= date("Y-m-d H:i:s",time())." : Header of response was ". $header ."\n";
	$debug_string .= date("Y-m-d H:i:s",time())." : Body was ". $buf ."\n";
	
	if($fp) {
		fwrite($fp, $debug_string);
	}
    curl_close($ch);
}

// GET logout: Just logout to look more human like and reset cookie :)
function wpgplus_logout() { 
    echo "\n[+] GET Logging out: \n\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
    if(!(curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1))) {
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : WPGPlus could NOT set CURLOPT_FOLLOWLOCATION\n";
		$debug_string .= "\nThis must be enabled for WPGPlus to work. Ask your hosting provider.\n";
		if($fp) {
			fwrite($fp, $debug_string);	
		}	
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

?>
