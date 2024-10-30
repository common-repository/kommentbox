<?php

require_once(plugin_dir_path( __FILE__ ).'wp-plugin.php');
//require_once(dirname(__FILE__) . '/lib/wp-api.php');

$kb_last_error = '';

if (!class_exists('NLPCaptcha')) {
    class NLPCaptcha extends WPPlugin {
        // member variables
        private $saved_error;
       
        // php 4 constructor
        function NLPCaptcha($options_name) {
            $args = func_get_args();
            call_user_func_array(array(&$this, "__construct"), $args);
        }
        
        // php 5 constructor
        function __construct($options_name) {
            parent::__construct($options_name);
            
            $this->register_default_options();
            
            
            // register the hooks
            $this->register_actions();
			$this->publisher_key = $this->options['publisherkey'];

			//$this->kb_api = new KomentboxWordPressAPI('komentbox', $this->options['publisherkey']);
        }
        
        function register_actions() {
           
			// For front end
			if ($this->options['show_in_comments'])
				add_filter("comments_template", array(&$this, 'comments_template'));
			
			// For addintg css
			add_action('wp_head', array(&$this, 'comment_stylesheets')); // make unnecessary: instead, inform of classes for styling
			add_action('admin_head', array(&$this, 'comment_stylesheets')); // make unnecessary: shouldn't require styling in the options page
			// For admin
			add_action('admin_init', array(&$this, 'register_settings_group'));
			add_filter("plugin_action_links", array(&$this, 'show_settings_link'), 10, 2);
			add_action('admin_menu', array(&$this, 'add_settings_page'));
            add_action('admin_notices', array(&$this, 'missing_keys_notice'));
			
			// for adding js
			add_action( 'admin_enqueue_scripts', array(&$this, 'load_adm_scripts') );
        }
        
				
		function load_adm_scripts($hook) {

    		// Only load these scripts on the Komentbox admin page
			if ( 'settings_page_komentbox/komentbox' != $hook ) { 
				return;
			}

			$admin_vars = array(
				'indexUrl' => admin_url('index.php'),
			);

			wp_register_script( 'admin_script', plugins_url( '/js/komentbox.js', __FILE__ ) );
			wp_localize_script( 'admin_script', 'adminVars', $admin_vars );
                        
                    
	
			wp_enqueue_script( 'admin_script', plugins_url( '/js/komentbox.js', __FILE__ ), array( 'jQuery') );
		}


		// User this for show koment box
		function comments_template($value)
		{
			return dirname(__FILE__) . '/comments.php';
		}



        
        // set the default options
        function register_default_options() {
		
			//print_r($this->options);
            if ($this->options)
               return;
           
            $option_defaults = array();
           
            $old_options = WPPlugin::retrieve_options("komentbox_options");
           
            if($old_options)
			{
               $option_defaults['publisherkey'] = $old_options['publisherkey']; // the public key for NLPCaptcha
               $option_defaults['validatekey'] = $old_options['validatekey']; // the private key for NLPCaptcha
			   $option_defaults['privatekey'] = $old_options['privatekey']; // the private key for NLPCaptcha
			   $option_defaults['show_in_comments'] = $old_options['re_comments']; // whether or not to show NLPCaptcha on the comment post
			}
			else
			{
               $option_defaults['publisherkey'] = ''; // the public key for NLPCaptcha
               $option_defaults['validatekey']='';
               $option_defaults['privatekey'] = ''; // the private key for NLPCaptcha
			   $option_defaults['show_in_comments'] = 1; // whether or not to show NLPCaptcha on the comment post
			}
            
            // add the option based on what environment we're in
            WPPlugin::add_options($this->options_name, $option_defaults);
        }
        
        
        
        // register the settings
        function register_settings_group() {
            register_setting("komentbox_options_group", 'komentbox_options', array(&$this, 'validate_options'));
        }
        
        
        /* below function for error check ans show */
        function nlpcaptcha_enabled() {
            return ($this->options['show_in_comments']);
        }
        
        function keys_missing() {
            return (empty($this->options['publisherkey']) || empty($this->options['validatekey'])|| empty($this->options['privatekey']));
        }
        
        function create_error_notice($message, $anchor = '') {
            $options_url = admin_url('options-general.php?page=komentbox/komentbox.php') . $anchor;
            $error_message = sprintf(__($message . ' <a href="%s" title="WP-NLPCaptcha Options">Fix this</a>', 'nlpcaptcha'), $options_url);
            
            echo '<div class="error"><p><strong>' . $error_message . '</strong></p></div>';
        }
        
        function missing_keys_notice() {
            if ($this->nlpcaptcha_enabled() && $this->keys_missing()) {
                $this->create_error_notice('You enabled KomentBox, but some of the NLPCaptcha API Keys seem to be missing.');
            }
        }
        
        function validate_options($input) {
            // todo: keys seem to usually be 40 characters in length, verify and if confirmed, add to validation process
            $validated['publisherkey'] = trim($input['publisherkey']);
            $validated['validatekey'] = trim($input['validatekey']);
            $validated['privatekey'] = trim($input['privatekey']);
            
            $validated['show_in_comments'] = ($input['show_in_comments'] == 1 ? 1 : 0);
            return $validated;
        }
        
		/* above function for error check ans show */
		
		
        
        // add a settings link to the plugin in the plugin list
        function show_settings_link($links, $file) {
            if ($file == plugin_basename($this->path_to_plugin_directory() . '/wp-komentbox.php')) {
               $settings_title = __('Settings for this Plugin', 'komentbox');
               $settings = __('Settings', 'komentbox');
               $settings_link = '<a href="options-general.php?page=komentbox/komentbox.php" title="' . $settings_title . '">' . $settings . '</a>';
               array_unshift($links, $settings_link);
            }
            
            return $links;
        }
        
        // add the settings page
        function add_settings_page() {
            // add the options page
            if ($this->environment == Environment::WordPressMU && $this->is_authority())
                add_submenu_page('wpmu-admin.php', 'WP-NLPCaptcha', 'WP-NLPCaptcha', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));

            
            
            add_options_page('WP-NLPCaptcha', 'WP-NLPCaptcha', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));
        }
        
        // store the xhtml in a separate file and use include on it
        function show_settings_page() {
            include("settings.php");
        }
        
        
		function comment_stylesheets() {
            $path = WPPlugin::url_to_plugin_directory() . '/komentbox.css';
                
            echo '<link rel="stylesheet" type="text/css" href="' . $path . '" />';
        }
		
        
        
       
    } // end class declaration
	
	
	function komentbox_page_identifier($post) {
		return $post->ID;
		//return $post->ID . ' ' . $post->guid;
    }

	function komentbox_page_title($post) {
    $title = get_the_title($post);
    $title = strip_tags($title);
    return $title;
}

	function kb_request_handler() {  
		global $kb_response;
		global $post;
		global $wpdb;

		if (!empty($_GET['cf_action'])) {
			switch ($_GET['cf_action']) {
				/* case 'kb_import_comments':
				
				    die('under kb_import_comments');
					if( !( $post_id = $_GET['post_id'] ) ) {
						header("HTTP/1.0 400 Bad Request");
						die();
					}
					// schedule the event for 5 minutes from now in case they
					// happen to make a quick post
					kb_add_pending_post_id($post_id);

					if (KOMENTBOX_DEBUG) {
						$response = kb_import_forum();
						if (!$response) {
							//die('// error: '.$kb_api->get_last_error());
							die('// error: '.$msg);
						} else {
							list($last_comment_id, $comments) = $response;
							die('// imported '.$comments.' comments');
						}
					} else {
						$ts = time() + 300;
						wp_schedule_single_event($ts, 'kb_import_forum');
						die('// import scheduled');
					}
				break; */
				case 'kb_export_comments':
				//mail('rajesh.kumar@nlpcaptcha.com','export',print_r($_GET,1));
					if (current_user_can('manage_options') && KOMENTBOX_EXPORT_CAPABILITY) {
						$msg = '';
						$result = '';
						$response = null;

						$timestamp = intval($_GET['timestamp']);
						$post_id = intval($_GET['post_id']);
						if ( isset($_GET['_kbexport_wpnonce']) === false ) {
							$msg = _e('Unable to export comments. Make sure you are accessing this page from the Wordpress dashboard.');
							$result = 'fail';
						}
						else {

							// Check nonce
							check_admin_referer('kb-wpnonce_export', '_kbexport_wpnonce');

							global $wpdb;
							$post = $wpdb->get_results($wpdb->prepare("
								SELECT *
								FROM $wpdb->posts
								WHERE post_type != 'revision'
								AND post_status = 'publish'
								AND comment_count > 0
								AND ID > %d
								ORDER BY ID ASC
								LIMIT 1
							", $post_id));
							$post = $post[0];
							$post_id = $post->ID;
							$max_post_id = $wpdb->get_var("
								SELECT MAX(Id)
								FROM $wpdb->posts
								WHERE post_type != 'revision'
								AND post_status = 'publish'
								AND comment_count > 0
							");
							$eof = (int)($post_id == $max_post_id);
							if ($eof) {
								$status = 'complete';
								$msg = 'Your comments have been sent to Komentbox and queued for import!';
								/* $msg .= '<br/><a href="'.KOMENTBOX_IMPORTER_URL.'" target="_blank">';
								$msg .= 'See the status of your import at Komentbox' . '</a>'; */
							} else {
								$status = 'partial';
								$msg = 'Processed comments on post #'. $post_id . '&hellip;';
							}
							$result = 'fail';
							
							if ($post) {
								require_once(dirname(__FILE__) . '/export.php');
								$wxr_file_name = kb_export_wp($post);
								//$response = $kb_api->import_wordpress_comments($wxr, $timestamp, $eof);
								//echo "<pre>";print_r($wxr_file_name);die('import_wordpress_comments');
								if (!$wxr_file_name) {
									$result = 'fail';
									$msg = '<p class="status kb-export-fail">'; 
									$msg .= 'Sorry, something unexpected happened with the export. Please try again.';
									$msg .= '</p>';
								   
								}
								else {
									if ($eof) {
										$msg = 'Your comments have been sent to Komentbox and queued for import!';
									    /* $msg .= '<br/><a href="' . $wxr_file_name . '" target="_blank">';
										$msg .= 'See the status of your import at Komentbox';
										$msg .= '</a>'; */
									}
									$result = 'success';
								}
							}
						}
						
						// send AJAX response
						$response = compact('result', 'timestamp', 'status', 'post_id', 'msg', 'eof', 'response','wxr_file_name');
						header('Content-type: text/javascript');
						echo cf_json_encode($response);
						die();
					}
				break;
				case 'kb_import_comments':
					if (current_user_can('manage_options')) {
						$msg = '';
						$result = '';
						$response = null;

						if ( isset($_GET['_kbimport_wpnonce']) === false ) {
							$msg = 'Unable to import comments. Make sure you are accessing this page from the Wordpress dashboard.';
							$result = 'fail';
						}
						else
						{
							// Check nonce
							check_admin_referer('kb-wpnonce_import', '_kbimport_wpnonce');

							if (!isset($_GET['last_comment_id'])) $last_comment_id = false;
							else $last_comment_id = $_GET['last_comment_id'];
							
							if ($_GET['wipe'] == '1') {
								$wpdb->query("DELETE FROM `".$wpdb->prefix."commentmeta` WHERE meta_key IN ('kb_post_id', 'kb_parent_post_id')");
								$wpdb->query("DELETE FROM `".$wpdb->prefix."comments` WHERE comment_agent LIKE 'Komentbox/%%'");
							}

							ob_start();
							$response = kb_import_forum($last_comment_id, true);
							//echo "<pre>";print_r($response );die('here'.$last_comment_id);
							$debug = ob_get_clean();
							if (!$response) {
								$status = 'error';
								$result = 'fail';
								//$error = $kb_api->get_last_error();
								$error = $kb_last_error;
								$msg = '<p class="status kb-export-fail">There was an error downloading your comments from Komentbox.<br/>'.esc_attr($error).'</p>';
							} else {
								list($comments, $last_comment_id) = $response;
								if (!$comments) {
									$status = 'complete';
									$msg = 'Your comments have been downloaded from Komentbox and saved in your local database.';
								} else {
									$status = 'partial';
									$msg = 'Import in progress (last post id: '.$last_comment_id.') &hellip;';
								}
								$result = 'success';
							}
							$debug = explode("\n", $debug);
							$response = compact('result', 'status', 'comments', 'msg', 'last_comment_id', 'debug');
							header('Content-type: text/javascript');
							echo cf_json_encode($response);
							die();
						}
					}
				break;
			}
		} 
	}

	add_action('init', 'kb_request_handler');

	function kb_identifier_for_post($post) {
		return $post->ID . ' ' . $post->guid;
	}

	/**
	 * JSON ENCODE for PHP < 5.2.0
	 * Checks if json_encode is not available and defines json_encode
	 * to use php_json_encode in its stead
	 * Works on iteratable objects as well - stdClass is iteratable, so all WP objects are gonna be iteratable
	 */
	if(!function_exists('cf_json_encode')) {
		function cf_json_encode($data) {
			
			// json_encode is sending an application/x-javascript header on Joyent servers
			// for some unknown reason.
			return cfjson_encode($data);
		}

		function cfjson_encode_string($str) {
			if(is_bool($str)) {
				return $str ? 'true' : 'false';
			}

			return str_replace(
				array(
					'"'
					, '/'
					, "\n"
					, "\r"
				)
				, array(
					'\"'
					, '\/'
					, '\n'
					, '\r'
				)
				, $str
			);
		}

		function cfjson_encode($arr) {
			$json_str = '';
			if (is_array($arr)) {
				$pure_array = true;
				$array_length = count($arr);
				for ( $i = 0; $i < $array_length ; $i++) {
					if (!isset($arr[$i])) {
						$pure_array = false;
						break;
					}
				}
				if ($pure_array) {
					$json_str = '[';
					$temp = array();
					for ($i=0; $i < $array_length; $i++) {
						$temp[] = sprintf("%s", cfjson_encode($arr[$i]));
					}
					$json_str .= implode(',', $temp);
					$json_str .="]";
				}
				else {
					$json_str = '{';
					$temp = array();
					foreach ($arr as $key => $value) {
						$temp[] = sprintf("\"%s\":%s", $key, cfjson_encode($value));
					}
					$json_str .= implode(',', $temp);
					$json_str .= '}';
				}
			}
			else if (is_object($arr)) {
				$json_str = '{';
				$temp = array();
				foreach ($arr as $k => $v) {
					$temp[] = '"'.$k.'":'.cfjson_encode($v);
				}
				$json_str .= implode(',', $temp);
				$json_str .= '}';
			}
			else if (is_string($arr)) {
				$json_str = '"'. cfjson_encode_string($arr) . '"';
			}
			else if (is_numeric($arr)) {
				$json_str = $arr;
			}
			else if (is_bool($arr)) {
				$json_str = $arr ? 'true' : 'false';
			}
			else {
				$json_str = '"'. cfjson_encode_string($arr) . '"';
			}
			return $json_str;
		}
	}
	
		
	function kb_import_forum($last_comment_id=false, $force=false) {
		global $wpdb;

		set_time_limit(KOMENTBOX_IMPORT_TIMEOUT);

		if ($force) {
			$import_time = null;
		} else {
			$import_time = (int)get_option('_komentbox_import_lock');
		}
							
		// lock expires after 1 hour
		$kb_last_error = 'Import already in progress (lock found)';
		if ($import_time && $import_time > time() - 60*60) {
			$kb_last_error = 'Import already in progress (lock found)';
			return false;
		} else {
			update_option('_komentbox_import_lock', time());
		}

		// Import all pending posts
		/* $post_ids = kb_get_pending_post_ids();
		kb_clear_pending_post_ids($post_ids);

		foreach ($post_ids as $post_id) {
			kb_import_post($post_id);
		} */
//echo $last_comment_id;die;
		if ($last_comment_id === false) {
			$last_comment_id = get_option('komentbox_last_comment_id');
			if (!$last_comment_id) {
				$last_comment_id = 0;
			}
		}
		
		/* if ($last_comment_id) {
			$last_comment_id++;
		} */

		//$last_comment_id = 0;

		
		$kOptArr = get_option('komentbox_options');
		$publisher_key = $kOptArr['publisherkey'];
		$validate_key = $kOptArr['validatekey'];
		$domain = site_url() ;
		$kb_response = kb_api_call($publisher_key, $validate_key, $domain, $last_comment_id);
		//echo "<pre>";print_r($kb_response);die('its me');
							
		if( $kb_response < 0 || $kb_response === false ) {
			return false;
		}

		// Import comments with database.
		kb_import_comments($kb_response);
		$total = 0;
		
		if ($kb_response) {
			foreach ($kb_response as $comment) {
				$total += 1;
				if ($comment->item->comment->comment_id > $last_comment_id){
					$last_comment_id = $comment->item->comment->comment_id;
				}
			}
			//echo "<br>===".$last_comment_id ."===<br>";die('111');
			if ($last_comment_id > get_option('komentbox_last_comment_id')) {
				update_option('komentbox_last_comment_id', $last_comment_id);
			}
		}
		unset($comment);
		delete_option('_komentbox_import_lock');
		return array($total, $last_comment_id);
	}
	
/* function kb_add_pending_post_id($post_id) {
    update_post_meta($post_id, 'kb_needs_import', '1', $unique=true);
}
 */
/* function getWPPostId($comments, $results){
	$post_ID = 0;
	$page_id_arr = array();
	$postData = array();
	$postarr = array();
	if(count($comments) > 0){
		
		
		foreach($results as $result){
			if($result->post_id)
				$page_id_arr[] = $result->meta_value;
		}
		
		$i = 0;
		foreach($comments as $comment){
			$page_id = $comment->item->page_id;
			if(count($page_id_arr) > 0 && in_array($page_id, $page_id_arr)){
				continue;
			}else{
				$page_identifier = $comment->item->page_identifier;
				$postarr[$page_id]['post_author'] = 1;
				$postarr[$page_id]['post_title'] = wp_strip_all_tags($comment->item->title);
				$postarr[$page_id]['post_status'] = 'publish';
				$postarr[$page_id]['post_type'] = 'post';
				$postarr[$page_id]['comment_status'] = 'Open';
				$postarr[$page_id]['ping_status'] = 'Open';
				$postarr[$page_id]['guid'] = $comment->item->link;
				$postarr[$page_id]['comment_count'] += 1;
			}
		}
		
		if(count($postarr) > 0 ){
			
			foreach($postarr as $page_id=>$posts){
				$post_ID = wp_insert_post ( $posts );
				if($post_ID){
					$postData['post_ID'][$page_id] = $post_ID; 
				}
			}
		}
       
	}
	
	return $postData;
} */

function isPostExistById($identifier){
	$identifier = (int) $identifier;
	return get_post_status( $identifier ) ;
}

function kb_import_comments($comments) {
	
    if ( count($comments) < 1 ) {
        return;
    }

    global $wpdb;

    // make sure user is logged out during insertion
    wp_set_current_user(0);

    // we need the page_ids so we can map them to posts
    $page_map = array();
    foreach ( $comments as $comment ) {
        $page_map[$comment->item->page_id] = null;
    }

    $page_ids = array_keys($page_map);
    $pages_query = implode(', ', array_fill(0, count($page_ids), '%s'));

    // add as many placeholders as needed
    $sql = "
        SELECT post_id, meta_value 
        FROM $wpdb->postmeta 
        WHERE meta_key = 'kb_page_id' AND meta_value IN (" . $pages_query . ")
    ";
    $query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $page_ids));
    $results = $wpdb->get_results($query);

    foreach ( $results as $result ) {
        $page_map[$result->meta_value] = $result->post_id;
    }
    unset($result);
    
    foreach ( $comments as $comment ) {
        $ts = strtotime($comment->item->comment->comment_date);
		$isPostExist = isPostExistById($comment->item->page_identifier);
		if(!$isPostExist){
			continue;
		}
        if (!$page_map[$comment->item->page_id] && !empty($comment->item->page_identifier)) {
                if ($post_ID = $comment->item->page_identifier) {
                    $page_map[$comment->item->page_id] = $post_ID;
                    $cleaned_page_id = sanitize_meta( 'kb_page_id', $comment->item->page_id, 'post' );
                    update_post_meta($post_ID, 'kb_page_id', $cleaned_page_id);
                    if (KOMENTBOX_DEBUG) {
                        echo "updated post {$post_ID}: kb_page_id set to {$comment->item->page_id}\n";
                    }
                }
        }

        if (!$page_map[$comment->item->page_id]) {
            // it shouldn't ever happen, but we can't be certain
            if (KOMENTBOX_DEBUG) {
                if (!empty($comment->item->page_identifier)) {
                    $identifier = $comment->item->page_identifier;
                    echo "comment skipped {$comment->item->comment->comment_id}: missing page for identifiers ({$identifier})\n";
                } else {
                    echo "comment skipped {$comment->item->comment->comment_id}: missing page (no identifier)\n";
                }
            }
            continue;
        }
        $results = $wpdb->get_results($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'kb_post_id' AND meta_value = %s LIMIT 1", $comment->item->comment->comment_id));
		
		
        if (count($results)) {
            // comment is already exists
            if (KOMENTBOX_DEBUG) {
                echo "comment skipped {$comment->item->comment->comment_id}: comment is already exists\n";
            }
            if (count($results) > 1) {
                // cleaning duplicates: we need to fix an issue where a race condition allowed comments to be imported multiple times
                $results = array_slice($results, 1);
                foreach ($results as $result) {
                    $wpdb->prepare("DELETE FROM $wpdb->commentmeta WHERE comment_id = %s LIMIT 1", $result);
                }
            }
            continue;
        }

        $commentdata = false;

        if (!$commentdata) {
            $commentdata = $wpdb->get_row($wpdb->prepare( "SELECT comment_ID, comment_parent FROM $wpdb->comments WHERE comment_agent = %s LIMIT 1", 'Komentbox/1.0:'.$comment->item->comment->comment_id), ARRAY_A);
        }
		
		
        if (!$commentdata) {
            // Comment does not exist, so try to insert it
            if ($comment->item->comment->status == 'approved') {
                $approved = 1;
            } elseif ($comment->item->comment->status == 'spam') {
                $approved = 'spam';
            } else {
                $approved = 0;
            }
            $commentdata = array(
                'comment_post_ID' => $page_map[$comment->item->page_id],
                'comment_date' => date('Y-m-d\TH:i:s', strtotime($comment->item->comment->comment_date) + (get_option('gmt_offset') * 3600)),
                'comment_date_gmt' => $comment->item->comment->comment_date,
                'comment_content' => apply_filters('pre_comment_content', $comment->item->comment->comment_content),
                'comment_approved' => $approved,
                'comment_agent' => 'Komentbox/1.1('.KOMENTBOX_VERSION.'):'.intval($comment->item->comment->comment_id),
                'comment_type' => '',
            );

			$commentdata['comment_author'] = $comment->item->comment->comment_author;
			$commentdata['comment_author_email'] = $comment->item->comment->comment_author_email;
			$commentdata['comment_author_url'] = $comment->item->comment->comment_author_source;
			$commentdata['comment_author_IP'] = $comment->item->comment->comment_author_IP;
			
            $commentdata = wp_filter_comment($commentdata);
            if ($comment->item->comment->comment_parent) {
                $parent_id = $wpdb->get_var($wpdb->prepare( "SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'kb_post_id' AND meta_value = %s LIMIT 1", $comment->item->comment->comment_parent));
                if ($parent_id) {
                    $commentdata['comment_parent'] = $parent_id;
                }
            }

            // test again for comment existance.
            if ($wpdb->get_row($wpdb->prepare( "SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'kb_post_id' AND meta_value = %s LIMIT 1", $comment->item->comment->comment_id))) {
                // comment already exists
                if (KOMENTBOX_DEBUG) {
                    echo "comment skipped {$comment->item->comment->comment_id}: comment already exists (this is second check)\n";
                }
                continue;
            }

            $commentdata['comment_ID'] = wp_insert_comment($commentdata);
            if (KOMENTBOX_DEBUG) {
                echo "inserted {$comment->item->comment->comment_id}: id is ".$commentdata['comment_ID']."\n";
            }
        }
        if ((isset($commentdata['comment_parent']) && !$commentdata['comment_parent']) && $comment->item->comment->comment_parent) {
            $parent_id = $wpdb->get_var($wpdb->prepare( "SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = 'kb_post_id' AND meta_value = %s LIMIT 1", $comment->item->comment->comment_parent));
            if ($parent_id) {
                $wpdb->query($wpdb->prepare( "UPDATE $wpdb->comments SET comment_parent = %s WHERE comment_id = %s", $parent_id, $commentdata['comment_ID']));
                if (KOMENTBOX_DEBUG) {
                    echo "updated {$comment->item->comment->comment_id}: comment_parent changed to {$parent_id}\n";
                }

            }
        }
        $comment_id = $commentdata['comment_ID'];
        update_comment_meta($comment_id, 'kb_parent_post_id', $comment->item->comment->comment_parent);
        update_comment_meta($comment_id, 'kb_post_id', $comment->item->comment->comment_id);
    }
    unset($comment);

   // if( isset($_POST['kb_api_key']) && $_POST['kb_api_key'] == get_option('komentbox_api_key') ) {
        if( isset($_GET['kb_import_action']) && isset($_GET['kb_import_comment_id']) ) {
            $comment_parts = explode('=', $_GET['kb_import_comment_id']);

            if (!($comment_id = intval($comment_parts[1])) > 0) {
                return;
            }

            if( 'wp_id' != $comment_parts[0] ) {
                $comment_id = $wpdb->get_var($wpdb->prepare('SELECT comment_ID FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d AND comment_agent LIKE %s', intval($post->ID), 'Komentbox/1.0:' . $comment_id));
            }

            switch( $_GET['kb_import_action'] ) {
                case 'mark_spam':
                    wp_set_comment_status($comment_id, 'spam');
                    echo "<!-- kb_import: wp_set_comment_status($comment_id, 'spam') -->";
                    break;
                case 'mark_approved':
                    wp_set_comment_status($comment_id, 'approve');
                    echo "<!-- kb_import: wp_set_comment_status($comment_id, 'approve') -->";
                    break;
                case 'mark_killed':
                    wp_set_comment_status($comment_id, 'hold');
                    echo "<!-- kb_import: wp_set_comment_status($comment_id, 'hold') -->";
                    break;
            }
        }
    //}
}


/* function kb_get_pending_post_ids() {
    global $wpdb;

    $results = $wpdb->get_results( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'kb_needs_import'");
    $post_ids = array();
    foreach ($results as $result) {
        $post_ids[] = $result->post_id;
    }
    return $post_ids;
}
 */

/* function kb_clear_pending_post_ids($post_ids) {
    if ( count($post_ids) < 1 ) {
        return;
    }

    global $wpdb;

    $posts_query = implode(', ', array_fill(0, count($post_ids), '%s'));

    // add as many placeholders as needed
    $sql = "
        DELETE FROM {$wpdb->postmeta} 
        WHERE meta_key = 'kb_needs_import' AND post_id IN (" . $posts_query . ")
    ";

    // Call $wpdb->prepare passing the values of the array as separate arguments
    $query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $post_ids));

    $wpdb->query($query);

    update_meta_cache('kb_needs_import', $post_ids);
}
 */

/* function kb_import_post($post_id) {
    global $kb_api, $wpdb;

    $post = get_post($post_id);

    // Call update_page to ensure our permalink is up to date
    kb_update_permalink($post);
} */

/* function kb_update_permalink($post) {
    global $kb_api;

    if (KOMENTBOX_DEBUG) {
        echo "updating post on komentbox: {$post->ID}\n";
    }

    $response = $kb_api->api->update_page(null, array(
        'page_identifier'    => kb_identifier_for_post($post),
        'title' => kb_title_for_post($post),
        'url' => kb_link_for_post($post)
    ));

    $cleaned_page_id = sanitize_meta( 'kb_page_id', $response->id, 'post' );
    update_post_meta($post->ID, 'kb_page_id', $cleaned_page_id);

    return $response;
} */


function kb_api_call($publisher_key, $validate_key, $domain, $last_comment_id){
	global $wp_version;
	//$url = KOMENTBOX_API_URL.'load_comments/?publisher_key='.$publisher_key."&start_id=".$last_comment_id;
	$url = KOMENTBOX_API_URL.'load_comments';
	$response = curl($url, $publisher_key, $validate_key, $domain, $last_comment_id);
	$comments = json_decode($response);
	return $comments;
}

function curl( $url, $publisher_key, $validate_key, $domain, $last_comment_id ) {

   $params = array("publisher_key"=>$publisher_key,"validate_key"=>$validate_key,"domain"=>$domain,"start_id"=>$last_comment_id);
   $params = http_build_query($params, NULL, '&');
        
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
	//curl_setopt($curl, CURLOPT_POST, TRUE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	//curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	//curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', 'Content-length: ' . strlen($params)));
	
	$response = curl_exec($curl);
	
	if ($response === FALSE) {
		$error = curl_error($curl);
		//echo "<pre>";print_r($error);die('error');
		curl_close($curl);
		return FALSE;
	}
	//echo "<pre>";print_r($response);die('response');
	
	curl_close($curl);
	return $response;
	
		/* $cx = curl_init($url);
   // curl_setopt($cx, CURLOPT_URL,$url);
    //curl_setopt($cx, CURLOPT_POST, 5);
    curl_setopt($cx, CURLOPT_POSTFIELDS,$fields_string);
    curl_setopt($cx, CURLOPT_HTTPHEADER, $header);
    curl_setopt($cx, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($cx, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($cx, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cx, CURLOPT_FOLLOWLOCATION, FALSE);
    curl_setopt($cx, CURLOPT_TIMEOUT, 120);
    curl_setopt($cx, CURLOPT_HEADER, 1);
    curl_setopt($cx, CURLINFO_HEADER_OUT, TRUE);
$response = curl_exec($cx);
 $errors = curl_error($cx);
$errornos = curl_errno($cx);

$headcut2 = explode ("n/xml", $response);
$headstring2 = $headcut2[0]."n/xml";
$xmlstring2 = $headcut2[1]; 
echo "<pre>";print_r($response);print_r($errors);
		//echo "$url, $publisher_key";die('here');
		
		$httpCode  = curl_getinfo( $cx, CURLINFO_HTTP_CODE );
		print_r($httpCode );die;
		if( 0 !== curl_errno( $cx ) || 200 !== $httpCode  ) {
			$response = null;
		} // end if
		curl_close( $cx );

		return $response; */

	} // end curl

} // end of class exists clause

?>
