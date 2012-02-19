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

// (!) Works only with Google 2-step auth turned off

$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';

function wpgplus_safe_post_google($post_id) {
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running, post_id is " . $post_id ."\n";
	fwrite($fp, $debug_string);
	
	@unlink(WP_PLUGIN_DIR .'/wpgplus/cookies.txt'); //delete previous cookie file if exists
	touch(WP_PLUGIN_DIR .'/wpgplus/cookies.txt'); //create a cookie file

	wpgplus_login(wpgplus_login_data());
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running,  past log in\n";
	fwrite($fp, $debug_string);
	sleep(5);
	wpgplus_update_profile_status($post_id);
	sleep(5);
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running,  past update\n";
	fwrite($fp, $debug_string);
	
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
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : wgplus_safe_post_google running, username is ". $wpgplusOptions['wpgplus_username'] ."\n";
	$debug_string .= "password is " .$wpgplusOptions['wpgplus_password'] . "\n";
	fwrite($fp, $debug_string);	
	
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR,WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
    curl_setopt($ch, CURLOPT_URL, "https://plus.google.com/");
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $buf = utf8_decode(html_entity_decode(curl_exec($ch)));
    curl_close($ch);

    $toreturn = '';
    $doc = new DOMDocument;
    @$doc->loadHTML($buf);
    $inputs = $doc->getElementsByTagName('input');
    foreach ($inputs as $input) {
		switch ($input->getAttribute('name')) {
			case 'Email': $toreturn .= 'Email=' . urlencode($wpgplusOptions['wpgplus_username']) . '&'; break;
			case 'Passwd':$toreturn .= 'Passwd=' . urlencode($wpgplusOptions['wpgplus_password']) . '&'; break;
			default: $toreturn .= $input->getAttribute('name') . '=' . urlencode($input->getAttribute('value')) . '&';
		}
    }
    // return array (string postdata, string postaction)
    return array(wpgplus_tidy($toreturn), $doc->getElementsByTagName('form')->item(0)->getAttribute('action'));
}

// POST login: https://accounts.google.com/ServiceLoginAuth
function wpgplus_login($postdata) {
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
    curl_setopt($ch, CURLOPT_URL, $postdata[1]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata[0]);
    $buf = curl_exec($ch); #this is not the g+ home page, because the b**** doesn't redirect properly
    
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : login data posted\n";
	$debug_string .= date("Y-m-d H:i:s",time())." : status code was ". curl_getinfo($ch, CURLINFO_HTTP_CODE) ."\n";
	fwrite($fp, $debug_string);
	
	curl_close($ch);

	//echo "\n[+] Sending POST request to: " . $postdata[1] . "\n\n";
}

function wpgplus_update_profile_status($post_id) {
	$wpgplus_debug_file= WP_PLUGIN_DIR .'/wpgplus/debug.txt';
	global $more; 
	$more = 0; //only the post teaser please 
	$my_post = get_post($post_id); 
	if(!empty($my_post->post_password)) { // post is password protected, don't post
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : Post is password protected\n";
		fwrite($fp, $debug_string);
		return;
	}
	if(get_post_type($my_post->ID) != 'post') { // only do this for posts
		$fp = @fopen($wpgplus_debug_file, 'a');
		$debug_string=date("Y-m-d H:i:s",time())." : Post type is ". get_post_type($my_post->ID) ."\n";
		fwrite($fp, $debug_string);
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
	fwrite($fp, $debug_string);

	$ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
    curl_setopt($ch, CURLOPT_URL, 'https://m.google.com/app/plus/?v=compose&group=m1c&hideloc=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $buf = utf8_decode(html_entity_decode(str_replace('&', '', curl_exec($ch))));
    $header = curl_getinfo($ch);
	$fp = @fopen($wpgplus_debug_file, 'a');
	$debug_string=date("Y-m-d H:i:s",time())." : Got form, status was ". curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
	$debug_string .= date("Y-m-d H:i:s",time())." : Response was:\n". $buf ."\n\n";
	fwrite($fp, $debug_string);
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
	fwrite($fp, $debug_string);
	
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
	/* group=m1c is 'your circles', group=b0 is 'public' */ 
    curl_setopt($ch, CURLOPT_URL, $baseurl . '?v=compose&group=m1c&group=b0&hideloc=1&a=post');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
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
	
	fwrite($fp, $debug_string);
    curl_close($ch);
}

// GET logout: Just logout to look more human like and reset cookie :)
function wpgplus_logout() { 
    echo "\n[+] GET Logging out: \n\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, WP_PLUGIN_DIR .'/wpgplus/cookies.txt');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.0; S60/3.0 NokiaN73-1/2.0(2.0617.0.0.7) Profile/MIDP-2.0 Configuration/CLDC-1.1)');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
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
