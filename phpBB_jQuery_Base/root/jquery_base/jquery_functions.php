<?php
/** 
*
* @package phpBB jQuery Base
* @copyright (c) 2011 Marc Alexander(marc1706) www.m-a-styles.de
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
* @ignore
*/
if(!defined('IN_PHPBB') || !defined('IN_JQUERY_BASE'))
{
	exit;
}


class phpbb_jquery_base
{
	/*
	* initial vars
	*/
	var $error = array(); // save errors in here (i.e. when quickediting posts)
	var $mode;
	var $post_id;
	var $location;
	var $forum_id;
	var $submit = false;
	var $load_tpl = false;
	var $return = array();
	var $tpl_file;
	
	/* 
	* function definitions below
	*/
	
	/*
	* initialise variables for following actions
	*/
	function init()
	{
		global $user, $phpbb_root_path, $phpEx;
		
		$this->post_id = request_var('post_id', 0);
		$this->mode = request_var('mode', '');
		$this->location = request_var('location', '');
		$this->submit = request_var('submit', false);

		// provide a valid location (need for some functions)
		$this->location = utf8_normalize_nfc(request_var('location', '', true));
		$this->forum_id = strstr($this->location, 'f=');
		$first_loc = strpos($this->forum_id, '&');
		$this->forum_id = ($first_loc) ? substr($this->forum_id, 2, $first_loc) : substr($this->forum_id, 2);
		$this->forum_id = (int) $this->forum_id; // make sure it's an int
		
		/* 
		* if somebody previews a style using the style URL parameter, we need to do this
		* @todo: find out if this is enough
		*/
		if($this->forum_id < 1)
		{
			$this->forum_id = request_var('f', 0);
		}
		
		// include needed files
		switch($this->mode)
		{
			case 'quickreply':
			case 'quickedit':
				$this->include_file('includes/functions_display', 'display_forums');
				$this->include_file('includes/message_parser', 'bbcode_firstpass', true);
			break;
			case 'markread_forum':
				// check what files we need
			break;
			case 'markread_all':
				// same as above
			break;
			default:
				$error[] = array('error' => 'NO_MODE', 'action' => 'cancel');
		}
	}
	
	/*
	* Run actions for the specified mode
	*
	* @param: none
	*/
	function run_actions()
	{
		// don't do anything if we already have an error because it can only be a "NO_MODE" error
		if(empty($error))
		{
			switch($this->mode)
			{
				case 'quickreply':
					$this->quickreply;
				break;
				case 'quickedit':
					$this->quickedit;
				break;
				case 'markread_forum':
					$this->mark_read('forum');
				break;
				case 'markread_forum':
					$this->mark_read('all');
			}
		}
	}


	/*
	* Decide what functions we need to run after run_actions()
	* @param: none
	*/
	function page_footer()
	{
		global $template;

		if(!empty($this->error))
		{
			$this->return['error_action'] = 'return';
			foreach($this->error as $cur_error)
			{
				if($cur_error['action'] == 'cancel')
				{
					$this->load_tpl = false; // make sure we don't load any template
					$this->return['error_action'] = 'cancel';
				}
				$this->return['error'][] = $cur_error['error'];
			}
		}

		$template->set_filenames(array(
					'body' => 'quickedit/quickedit.html')
				);
		page_footer();
	}
	
	

	/*
	* include a file if its functions or class does not exist yet
	* always use this function for including files
	* 
	* @param <string> $file The path to the file that needs to be included (relative to the phpbb root path & just the filename, i.e. 'index' for index.php)
	* @param <string> $check The function or class that shouldn't exist if the file hasn't been included yet
	* @param <bool> $class Set to true if you would like to check for a class and false if you would like to check for a function
	*/
	function include_file($file, $check, $class = false)
	{
		global $phpbb_root_path, $phpEx;
		
		if($class)
		{
			if(!class_exists($check))
			{
				include($phpbb_root_path . $file . '.' . $phpEx);
			}
		}
		else
		{
			if(!function_exists($check_function))
			{
				include($phpbb_root_path . $file . '.' . $phpEx);
			}
		}
	}


	/* 
	* Add variables or arrays to the JSON return array
	* 
	* @param: <array> $return_ary The array of variables -- the array needs to be structured like: array('varname' => 'value')
	* @param: <bool> $force Set to true if you want to overwrite already existing values
	*/
	function add_return($return_ary, $force = false)
	{
		foreach($return_ary as $key => $value)
		{
			if(!isset($this->return_ary[$key]) || $force)
			{
				$this->return_ary[$key] = $value;
			}
		}
	}


	/*
	* Quickedit posts
	* @param none
	*/
	function quickedit()
	{
		global $db, $config, $auth, $template;

		// the first post is 1, so any post_id below 1 isn't possible
		if($this->$post_id < 1)
		{
			$this->error[] = array('error' => $user->lang['NO_MODE'], 'action' => 'cancel');
			$mode = '';
			//$this->tpl_load = false; // page_footer already does this
		}
		else
		{
			$qe_mode = request_var('qe_mode', '');
		}

		switch($mode)
		{
			case 'init':
				$sql = 'SELECT p.*, f.*, t.*
						FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f
						WHERE p.post_id = ' . (int) $this->post_id . ' AND p.topic_id = t.topic_id';	
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);
				
				// Give a valid forum_id for image, smilies, etc. status
				if(!isset($row['forum_id']) || $row['forum_id'] <= 0)
				{
					$forum_id = $this->forum_id;
				}
				else
				{
					$forum_id = $row['forum_id'];
				}
				
				// HTML, BBCode, Smilies, Images and Flash status
				$bbcode_status	= ($config['allow_bbcode'] && ($auth->acl_get('f_bbcode', $forum_id))) ? true : false;
				$smilies_status	= ($bbcode_status && $config['allow_smilies'] && $auth->acl_get('f_smilies', $forum_id)) ? true : false;
				$img_status		= ($bbcode_status && $auth->acl_get('f_img', $forum_id)) ? true : false;
				$url_status		= ($config['allow_post_links']) ? true : false;
				$flash_status	= ($bbcode_status && $auth->acl_get('f_flash', $forum_id) && $config['allow_post_flash']) ? true : false;
				$quote_status	= ($auth->acl_get('f_reply', $forum_id)) ? true : false;
				
				// check if the user is registered and if he is able to edit posts
				if (!$user->data['is_registered'] && !$auth->acl_gets('f_edit', 'm_edit', $forum_id))
				{
					$this->error[] = array('error' => $user->lang['USER_CANNOT_EDIT'], 'action' => 'cancel');
				}
				
				if ($row['forum_status'] === ITEM_LOCKED)
				{
					// forum locked
					$this->error[] = array('error' => $user->lang['FORUM_LOCKED'], 'action' => 'cancel');
				}
				elseif ((isset($row['topic_status']) && $row['topic_status'] === ITEM_LOCKED) && !$auth->acl_get('m_edit', $forum_id))
				{
					// topic locked
					$this->error[] = array('error' => $user->lang['TOPIC_LOCKED'], 'action' => 'cancel');
				}
				// @todo: start working from here
				// check if the user is allowed to edit the selected post
				if (!$auth->acl_get('m_edit', $forum_id))
				{
					if ($user->data['user_id'] != $row['poster_id'])
					{
						// user is not allowed to edit this post
						$this->error[] = array('error' => $user->lang['USER_CANNOT_EDIT'], 'action' => 'cancel');
					}
				
					if (($row['post_time'] < time() - ($config['edit_time'] * 60)) && $config['edit_time'] > 0)
					{
						// user can no longer edit the post (exceeded edit time)
						$this->error[] = array('error' => $user->lang['CANNOT_EDIT_TIME'], 'action' => 'cancel');
					}
				
					if ($row['post_edit_locked'])
					{
						// post has been locked in order to prevent editing
						$this->error[] = array('error' => $user->lang['CANNOT_EDIT_POST_LOCKED'], 'action' => 'cancel');
					}
				}
				
				// now normalize the post text
				$text = utf8_normalize_nfc($row['post_text']);
				$text = generate_text_for_edit($text, $row['bbcode_uid'], '');
				
				// Build custom bbcodes array
				display_custom_bbcodes();
				
				// Assign important template vars
				$return_ary = array(
					'POST_TEXT'   			=> ($qe_action != '') ? '' : $text['text'], // Don't show the text if there was a permission error
					'S_LINKS_ALLOWED'       => $url_status,
					'S_BBCODE_IMG'          => $img_status,
					'S_BBCODE_FLASH'		=> $flash_status,
					'S_BBCODE_QUOTE'		=> $quote_status,
					'S_BBCODE_ALLOWED'		=> $bbcode_status,
					'MAX_FONT_SIZE'			=> (int) $config['max_post_font_size'],
				);
				
				$this->add_return($return_ary);
				
				$this->load_tpl = true;
			break;
			
			case 'submit':
				/* 
				* only include functions_posting if we actually need it
				* make sure we don't include it if it already has been included by some other MOD
				*/
				$this->include_file('includes/functions_posting', 'submit_post');

				$sql = 'SELECT p.*, f.*, t.*, u.*, p.icon_id AS post_icon_id
						FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f, ' . USERS_TABLE . ' u
						WHERE p.post_id = ' . (int)$post_id . ' 
							AND p.topic_id = t.topic_id
							AND p.poster_id = u.user_id';	
				$result = $db->sql_query($sql);
				$post_data = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				// Give a valid forum_id for image, smilies, etc. status
				if(!isset($post_data['forum_id']) || $post_data['forum_id'] <= 0)
				{
					$post_data['forum_id'] = $this->forum_id;
				}

				// HTML, BBCode, Smilies, Images and Flash status
				$bbcode_status	= ($config['allow_bbcode'] && ($auth->acl_get('f_bbcode', $post_data['forum_id']) || $post_data['forum_id'] == 0) && $post_data['enable_bbcode']) ? true : false;
				$smilies_status	= ($bbcode_status && $config['allow_smilies'] && $auth->acl_get('f_smilies', $post_data['forum_id']) && $post_data['enable_smilies']) ? true : false;
				$img_status	= ($bbcode_status && $auth->acl_get('f_img', $post_data['forum_id'])) ? true : false;
				$url_status	= ($config['allow_post_links'] && $post_data['enable_magic_url']) ? true : false;
				$flash_status	= ($bbcode_status && $auth->acl_get('f_flash', $post_data['forum_id']) && $config['allow_post_flash']) ? true : false;
				$quote_status	= ($auth->acl_get('f_reply', $post_data['forum_id'])) ? true : false;
				
				// check if the user is registered and if he is able to edit posts
				if (!$user->data['is_registered'] && !$auth->acl_gets('f_edit', 'm_edit', $post_data['forum_id']))
				{
					$this->error[] = array('error' => $user->lang['USER_CANNOT_EDIT'], 'action' => 'cancel');
				}
				
				if ($post_data['forum_status'] === ITEM_LOCKED)
				{
					// forum locked
					$this->error[] = array('error' => $user->lang['FORUM_LOCKED'], 'action' => 'cancel');
				}
				elseif ((isset($post_data['topic_status']) && $post_data['topic_status'] === ITEM_LOCKED) && !$auth->acl_get('m_edit', $post_data['forum_id']))
				{
					// topic locked
					$this->error[] = array('error' => $user->lang['TOPIC_LOCKED'], 'action' => 'cancel');
				}
				
				// check if the user is allowed to edit the selected post
				if (!$auth->acl_get('m_edit', $post_data['forum_id']))
				{
					if ($user->data['user_id'] != $post_data['poster_id'])
					{
						// user is not allowed to edit this post
						$this->error[] = array('error' => $user->lang['USER_CANNOT_EDIT'], 'action' => 'cancel');
					}
				
					if (($post_data['post_time'] < time() - ($config['edit_time'] * 60)) && $config['edit_time'] > 0)
					{
						// user can no longer edit the post (exceeded edit time)
						$this->error[] = array('error' => $user->lang['CANNOT_EDIT_TIME'], 'action' => 'cancel');
					}
				
					if ($post_data['post_edit_locked'])
					{
						// post has been locked in order to prevent editing
						$this->error[] = array('error' => $user->lang['CANNOT_EDIT_POST_LOCKED'], 'action' => 'cancel');
					}
				}
				
				// Add moderator edit to the moderator log
				if (($user->data['user_id'] != $post_data['poster_id']) && ($auth->acl_get('m_edit', $post_data['forum_id'])))
				{
					add_log('mod', $post_data['forum_id'], $post_data['topic_id'], 'LOG_POST_EDITED', $post_data['topic_title'], (!empty($post_data['username'])) ? $post_data['username'] : $user->lang['GUEST']);
				}
				
				$post_text = utf8_normalize_nfc(request_var('contents', '', true));	
				$uid = $bitfield = '';
				
				// start parsing the text for the database
				$message_parser = new parse_message();
				
				$message_parser->message = $post_text;
				
				// Always check if the submitted attachment data is valid and belongs to the user.
				// Further down (especially in submit_post()) we do not check this again.
				$message_parser->get_submitted_attachment_data($post_data['poster_id']);

				if ($post_data['post_attachment'])
				{
					// Do not change to SELECT *
					$sql = 'SELECT attach_id, is_orphan, attach_comment, real_filename
						FROM ' . ATTACHMENTS_TABLE . '
						WHERE post_msg_id = ' . (int)$post_id . '
							AND in_message = 0
							AND is_orphan = 0
						ORDER BY filetime DESC';
					$result = $db->sql_query($sql);
					$message_parser->attachment_data = array_merge($message_parser->attachment_data, $db->sql_fetchrowset($result));
					$db->sql_freeresult($result);
				}
				
				if(isset($post_data['bbcode_uid']) && $post_data['bbcode_uid'] > 0)
				{
					$message_parser->bbcode_uid = $post_data['bbcode_uid'];
				}

				// this will tell us if there are any errors with the post
				$message_parser->parse($bbcode_status, ($url_status) ? $post_data['enable_magic_url'] : false, $smilies_status, $img_status, $flash_status, $quote_status, $config['allow_post_links'], true);
				
				// insert info into the sql_ary
				$uid = $message_parser->bbcode_uid;
				$bitfield = $message_parser->bbcode_bitfield;
				
				//now check if we need to set the edit time and edit count
				if (!$auth->acl_get('m_edit', $post_data['forum_id']))
				{
					$edit_time = time();
					$edit_count = $post_data['post_edit_count'] + 1;
					$edit_user = $user->data['user_id'];
				}
				elseif ($auth->acl_get('m_edit', $post_data['forum_id']) && $post_data['post_edit_reason'] && $post_data['post_edit_user'] == $user->data['user_id'])
				{
					$edit_time = time();
					$edit_count = $post_data['post_edit_count'] + 1;
					$edit_user = $user->data['user_id'];
				}
				else 
				{
					$edit_time = (isset($post_data['post_edit_time'])) ? $post_data['post_edit_time'] : 0;
					$edit_user = (isset($post_data['post_edit_user'])) ? $post_data['post_edit_user'] : 0;
					$edit_count = (isset($post_data['post_edit_count'])) ? $post_data['post_edit_count'] : 0; 
				}
				
				// Create the data array for submit_post
				$data = array(
				    // General Posting Settings
				    'forum_id'          	=> $post_data['forum_id'],
				    'topic_id'          	=> $post_data['topic_id'],
				    'icon_id'           	=> $post_data['post_icon_id'],
				    'post_id'			=> $post_data['post_id'],
				    'poster_id'			=> $post_data['poster_id'],
				    'topic_replies'		=> $post_data['topic_replies'],
				    'topic_replies_real'	=> $post_data['topic_replies_real'],
				    'topic_first_post_id'	=> $post_data['topic_first_post_id'],
				    'topic_last_post_id'	=> $post_data['topic_last_post_id'],
				    'post_edit_user'		=> $edit_user,
				    'forum_parents'		=> $post_data['forum_parents'],
				    'forum_name'		=> $post_data['forum_name'],
				    'topic_poster'		=> $post_data['topic_poster'],
				
				    // Defining Post Options
				    'enable_bbcode' 	=> $post_data['enable_bbcode'],
				    'enable_smilies'    => $post_data['enable_smilies'],
				    'enable_urls'       => $post_data['enable_magic_url'],
				    'enable_sig'        => $post_data['enable_sig'],
				    'topic_attachment'	=> (isset($post_data['topic_attachment'])) ? (int) $post_data['topic_attachment'] : 0,
				    'poster_ip'			=> (isset($post_data['poster_ip'])) ? $post_data['poster_ip'] : $user->ip,
				    'attachment_data'	=> $message_parser->attachment_data,
				    'filename_data'		=> $message_parser->filename_data,
				
				    // Message Body
				    'message'           => $message_parser->message,
				    'message_md5'   	=> md5($message_parser->message),
				
				    // Values from generate_text_for_storage()
				    'bbcode_bitfield'   => $bitfield,
				    'bbcode_uid'        => $uid,
				
				    // Other Options
				    'post_edit_locked'  => $post_data['post_edit_locked'],
				    'post_edit_reason'	=> ($post_data['post_edit_reason']) ? $post_data['post_edit_reason'] : '',
				    'topic_title'       => $post_data['topic_title'],
				    'topic_time_limit'	=> ($post_data['topic_time_limit']) ? $post_data['topic_time_limit'] : 0,
				
				    // Email Notification Settings
				    'notify_set'        => false,
				    'notify'            => false,
				    'post_time'         => 0,
				    'forum_name'        => $post_data['forum_name'],
				
				    // Indexing
				    'enable_indexing'   => true,
				
				    // 3.0.6
				    'force_approved_state'  => true, // post has already been approved
				);

				$poll = array(
				    'poll_title'	=> $post_data['poll_title'],
				    'poll_length'	=> $post_data['poll_length'],
				    'poll_start'	=> $post_data['poll_start'],
				    'poll_max_options'	=> $post_data['poll_max_options'],
				    'poll_vote_change'	=> $post_data['poll_vote_change'],
				);

				// Get Poll Data
				if ($poll['poll_start'])
				{
					$sql = 'SELECT poll_option_text
						FROM ' . POLL_OPTIONS_TABLE . "
						WHERE topic_id = {$data['topic_id']}
						ORDER BY poll_option_id";
					$result = $db->sql_query($sql);

					while ($row = $db->sql_fetchrow($result))
					{
						$poll['poll_options'][] = trim($row['poll_option_text']);
					}
					$db->sql_freeresult($result);
				}
				
				// Always check if the submitted attachment data is valid and belongs to the user.
				// Further down (especially in submit_post()) we do not check this again.
				$message_parser->get_submitted_attachment_data($post_data['poster_id']);

				if ($post_data['post_attachment'])
				{
					// Do not change to SELECT *
					$sql = 'SELECT attach_id, is_orphan, attach_comment, real_filename
						FROM ' . ATTACHMENTS_TABLE . '
						WHERE post_msg_id = ' . (int)$post_id . '
							AND in_message = 0
							AND is_orphan = 0
						ORDER BY filetime DESC';
					$result = $db->sql_query($sql);
					$message_parser->attachment_data = array_merge($message_parser->attachment_data, $db->sql_fetchrowset($result));
					$db->sql_freeresult($result);
				}

				foreach($message_parser->warn_msg as $cur_error)
				{
					$this->error[] = array('error' => $cur_error, 'action' => 'return'); // by default we have a return error
				}
				
				
				// Don't execute all that if we already have errors
				if(!empty($message_parser->warn_msg))
				{
					/**
					* Start parsing the message for displaying the post
					* we only do this if there is no error or else we might just do useless database queries
					* Pull attachment data
					* @copyright (c) 2005 phpBB Group
					*/
					if ($post_data['post_attachment'] && $config['allow_attachments'])
					{
						$attach_list[] = (int) $post_data['post_id'];
					}
					else
					{
						$attach_list = array();
					}
					
					if (sizeof($attach_list))
					{
						if ($auth->acl_get('u_download') && (empty($post_data['forum_id']) || $auth->acl_get('f_download', $post_data['forum_id'])))
						{
							$sql = 'SELECT *
								FROM ' . ATTACHMENTS_TABLE . '
								WHERE ' . $db->sql_in_set('post_msg_id', $attach_list) . '
									AND in_message = 0
								ORDER BY filetime DESC, post_msg_id ASC';
							$result = $db->sql_query($sql);

							while ($row = $db->sql_fetchrow($result))
							{
								$attachments[$row['post_msg_id']][] = $row;
							}
							$db->sql_freeresult($result);

							// No attachments exist, but post table thinks they do so go ahead and reset post_attach flags
							if (!sizeof($attachments))
							{
								$sql = 'UPDATE ' . POSTS_TABLE . '
									SET post_attachment = 0
									WHERE ' . $db->sql_in_set('post_id', $attach_list);
								$db->sql_query($sql);

							}
						}
					}
					// Add up the flag options...
					$bbcode_options = (($post_data['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) + (($post_data['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) + (($post_data['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
					// Parse the post
					$text = generate_text_for_display($data['message'], $data['bbcode_uid'], $data['bbcode_bitfield'], $bbcode_options);

					// Parse attachments
					if (!empty($attachments[$post_data['post_id']]))
					{
						parse_attachments($post_data['forum_id'], $text, $attachments[$post_data['post_id']], $update_count);
					}
					
					if (!$auth->acl_get('m_edit', $post_data['forum_id']))
					{
						$user->add_lang('viewtopic');
						
						$display_username = get_username_string('full', $post_data['poster_id'], $post_data['username'], $post_data['user_colour'], $post_data['post_username']);

						$l_edit_time_total = ($post_data['post_edit_count'] == 1) ? $user->lang['EDITED_TIME_TOTAL'] : $user->lang['EDITED_TIMES_TOTAL'];
						$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($post_data['post_edit_time'], false, true), $edit_count);
						if($post_data['post_edit_reason'])
						{
							$l_edited_by .= '<br /><strong>' . $user->lang['REASON'] . ':</strong> <em>' . $post_data['post_edit_reason'] . '</em>';
						}
					}
					elseif ($auth->acl_get('m_edit', $post_data['forum_id']) && $post_data['post_edit_reason'] && $post_data['post_edit_user'] == $user->data['user_id'])
					{
						$user->add_lang('viewtopic');
						
						$display_username = get_username_string('full', $user->data['user_id'], $user->data['username'], $user->data['user_colour'], $user->data['username']);

						$l_edit_time_total = ($post_data['post_edit_count'] == 1) ? $user->lang['EDITED_TIME_TOTAL'] : $user->lang['EDITED_TIMES_TOTAL'];
						$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($post_data['post_edit_time'], false, true), $edit_count);
						if($post_data['post_edit_reason'])
						{
							$l_edited_by .= '<br /><strong>' . $user->lang['REASON'] . ':</strong> <em>' . $post_data['post_edit_reason'] . '</em>';
						}
					}
					else
					{
						$l_edited_by = '0';
					}
				
					/* 
					* {/qe_seperator} seperates the values for javascript
					* qe_error{/qe_seperator}qe_action{/qe_seperator}edited_by_info{/qe_seperator}message
					* since qe_error is empty, qe_action is also set to empty in the return variable
					*/
					$this->add_return(array(
						'EDITED_BY'	=> $l_edited_by,
						'TEXT'		=> $text,
					));
					$this->load_tpl = false;
					/* 
					* Don't run submit_post before we checked for errors
					* $mode is always edit as we just edit a post with this MOD
					* $username is set to $user->data['username'] as we don't need the clean username for the logs
					*/
					submit_post('edit', $post_data['post_subject'], $post_data['username'], $post_data['topic_type'], $poll, $data);
				}
				
			break;
			
			case 'advanced_edit':
				/* 
				* Since we only pass on the post text and this won't be entered into the database, we shouldn't need to worry about checking for permissions.
				* But we don't want to user to end up on an error page.
				* Therefore we will check if the user able to actually edit this post.
				* If the user is not authorized to edit the post, we will close the quickedit but we still need it to look good.
				*/
				$sql = 'SELECT p.*, f.*, t.*
						FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f
						WHERE p.post_id = ' . (int)$post_id . ' AND p.topic_id = t.topic_id';	
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);
				
				// HTML, BBCode, Smilies, Images and Flash status
				$bbcode_status	= ($config['allow_bbcode'] && ($auth->acl_get('f_bbcode', $row['forum_id']) || $row['forum_id'] == 0)) ? true : false;
				$smilies_status	= ($bbcode_status && $config['allow_smilies'] && $auth->acl_get('f_smilies', $row['forum_id'])) ? true : false;
				$img_status		= ($bbcode_status && $auth->acl_get('f_img', $row['forum_id'])) ? true : false;
				$url_status		= ($config['allow_post_links']) ? true : false;
				$flash_status	= ($bbcode_status && $auth->acl_get('f_flash', $row['forum_id']) && $config['allow_post_flash']) ? true : false;
				$quote_status	= ($auth->acl_get('f_reply', $row['forum_id'])) ? true : false;
				
				// check if the user is registered and if he is able to edit posts
				if (!$user->data['is_registered'] && !$auth->acl_gets('f_edit', 'm_edit', $row['forum_id']))
				{
					$qe_error = $user->lang['USER_CANNOT_EDIT'];
					$qe_action = 'cancel';
				}
				
				if ($row['forum_status'] == ITEM_LOCKED)
				{
					// forum locked
					$qe_error = $user->lang['FORUM_LOCKED'];
					$qe_action = 'cancel';
				}
				elseif ((isset($row['topic_status']) && $row['topic_status'] == ITEM_LOCKED) && !$auth->acl_get('m_edit', $row['forum_id']))
				{
					// topic locked
					$qe_error = $user->lang['TOPIC_LOCKED'];
					$qe_action = 'cancel';
				}
				
				// check if the user is allowed to edit the selected post
				if (!$auth->acl_get('m_edit', $row['forum_id']))
				{
					if ($user->data['user_id'] != $row['poster_id'])
					{
						// user is not allowed to edit this post
						$qe_error = $user->lang['USER_CANNOT_EDIT'];
						$qe_action = 'cancel';
					}
				
					if (($row['post_time'] < time() - ($config['edit_time'] * 60)) && $config['edit_time'] > 0)
					{
						// user can no longer edit the post (exceeded edit time)
						$qe_error = $user->lang['CANNOT_EDIT_TIME'];
						$qe_action = 'cancel';
					}
				
					if ($row['post_edit_locked'])
					{
						// post has been locked in order to prevent editing
						$qe_error = $user->lang['CANNOT_EDIT_POST_LOCKED'];
						$qe_action = 'cancel';
					}
				}
				
				/* 
				* now fetch and normalize the post text
				* we don't need to run generate_text_for_edit again, since we already did this once with the post text(at least if nobody tries to hack this script)
				*/
				$post_text = utf8_normalize_nfc(request_var('contents', '', true));	
				
				// this is just for the attachment_data
				$message_parser = new parse_message();
				
				$message_parser->message = $post_text;
				
				// Always check if the submitted attachment data is valid and belongs to the user.
				// Further down (especially in submit_post()) we do not check this again.
				$message_parser->get_submitted_attachment_data($row['poster_id']);

				if ($row['post_attachment'])
				{
					// Do not change to SELECT *
					$sql = 'SELECT attach_id, is_orphan, attach_comment, real_filename
						FROM ' . ATTACHMENTS_TABLE . '
						WHERE post_msg_id = ' . (int)$post_id . '
							AND in_message = 0
							AND is_orphan = 0
						ORDER BY filetime DESC';
					$result = $db->sql_query($sql);
					$message_parser->attachment_data = array_merge($message_parser->attachment_data, $db->sql_fetchrowset($result));
					$db->sql_freeresult($result);
				}
				
				// Give a valid forum_id for image, smilies, etc. status
				if(!isset($row['forum_id']) || $row['forum_id'] <= 0)
				{
					$location = utf8_normalize_nfc(request_var('location', '', true));
					$location = strstr($location, 'f=');
					$first_loc = strpos($location, '&');
					$location = ($first_loc) ? substr($location, 2, $first_loc) : substr($location, 2);
					$forum_id = (int) $location; // don't remove (int)
					
					// if somebody previews a style using the style URL parameter, we need to do this
					if($forum_id < 1)
					{
						$location = request_var('f', 0);
						$forum_id = (int) $location;
					}
				}
				else
				{
					$forum_id = $row['forum_id'];
				}
				
				// Why should we waste any time if we already have an error?
				if($qe_error == '')
				{
					$s_hidden_fields = '<input type="hidden" name="lastclick" value="' . time() . '" />';
					$s_hidden_fields .= build_hidden_fields(array(
						'edit_post_message_checksum'	=> $row['post_checksum'],
						'edit_post_subject_checksum'	=> (isset($post_data['post_subject'])) ? md5($row['post_subject']) : '',
						'message'						=> $post_text,
						'full_editor' 					=> true,
						'subject'						=> $row['post_subject'],
						'attachment_data' 				=> $message_parser->attachment_data,
					));
					
					$template->assign_vars(array(
						'U_ADVANCED_EDIT' 	=> append_sid("{$phpbb_root_path}posting.$phpEx", 'mode=edit&amp;f=' . $forum_id . "&amp;t={$row['topic_id']}&amp;p={$row['post_id']}"),
						'S_HIDDEN_FIELDS' 	=> $s_hidden_fields,
					));
				}
				
				// Build custom bbcodes array
				display_custom_bbcodes();
				
				// Assign important template vars
				$template->assign_vars(array(
					'POST_TEXT'   			=> ($qe_action != '') ? '' : $post_text, // Don't show the text if there was a permission error
					'S_LINKS_ALLOWED'       => $url_status,
					'S_BBCODE_IMG'          => $img_status,
					'S_BBCODE_FLASH'		=> $flash_status,
					'S_BBCODE_QUOTE'		=> $quote_status,
					'S_BBCODE_ALLOWED'		=> $bbcode_status,
					'MAX_FONT_SIZE'			=> (int) $config['max_post_font_size'],
				));
				$assign_template = true;
			
			break;
			
			default:
		}
	}

	/*
	* Quickly reply to posts
	*/
	function quickreply()
	{
	
	}

	/*
	* mark forums read
	*
	* @param <string> $type The kind of "mark" this function should run (i.e. mark all forums read)
	*/
	function mark_read($type)
	{
	
	}
}

?>