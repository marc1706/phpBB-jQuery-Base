<?php
/** 
*
* @package phpBB jQuery Base
* @copyright (c) 2012 Marc Alexander(marc1706) www.m-a-styles.de
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
	private $error = array(); // save errors in here (i.e. when quickediting posts)
	private $mode;
	private $post_id;
	private $location;
	private $forum_id;
	private $submit = false;
	private $load_tpl = false;
	private $return = array();
	private $tpl_file;
	private $return_ary = array();
	public $config = array();
	
	/* 
	* function definitions below
	*/
	
	/*
	* initialise variables for following actions
	*/
	public function __construct()
	{
		global $user, $phpbb_root_path, $phpEx, $auth, $config;
		
		$this->post_id = request_var('post_id', 0);
		$this->mode = request_var('mode', '');
		$this->location = request_var('location', '');
		$this->submit = request_var('submit', false);
		$this->obtain_config();
		$set_no_auth = false;

		// provide a valid location (need for some functions)
		$this->location = utf8_normalize_nfc(request_var('location', '', true));
		$this->forum_id = strstr($this->location, 'f=');
		$first_loc = strpos($this->forum_id, '&');
		$this->forum_id = ($first_loc) ? substr($this->forum_id, 2, $first_loc) : substr($this->forum_id, 2);
		$this->forum_id = (int) $this->forum_id; // make sure it's an int
		
		/* 
		* if somebody previews a style using the style URL parameter, we need to do this
		* this will not work for other cases
		*/
		if ($this->forum_id < 1)
		{
			$this->forum_id = request_var('f', 0);
		}
		
		// include needed files
		switch ($this->mode)
		{
			case 'quickreply':
				// do nothing if quickreply is disabled
				if ($config['pjb_quickreply_enable'])
				{
					if ($auth->acl_get('u_quickreply'))
					{
						$this->include_file('includes/functions_posting', 'submit_post');
					}
					else
					{
						$set_no_auth = true;
					}
				}
			case 'quickedit':
				// do nothing if quickedit is disabled
				if ($config['pjb_quickedit_enable'])
				{
					if ($auth->acl_get('u_quickedit'))
					{
						$this->include_file('includes/functions_display', 'display_forums');
						$this->include_file('includes/message_parser', 'bbcode_firstpass', true);
					}
					else
					{
						$set_no_auth = true;
					}
				}
			break;
			case 'markread_forums':
			case 'markread_topics':
			case 'check_pm':
				// don't need any
			break;
			case 'login':
				if ($config['pjb_login_enable'])
				{
					if (!class_exists('phpbb_captcha_factory'))
					{
						include($phpbb_root_path . 'includes/captcha/captcha_factory.' . $phpEx);
					}
				}
			break;
			default:
				$this->error[] = array('error' => 'NO_MODE', 'action' => 'cancel');
		}
		
		if ($set_no_auth == true)
		{
			$this->error[] = array('error' => 'NO_AUTH_OPERATION', 'action' => 'cancel');
		}
	}
	
	/*
	* Get config data from $config
	* We just use the cached array $config
	*
	* __construct() will run this function
	*/
	private function obtain_config()
	{
		global $config;
		
		foreach($config as $key => $value)
		{
			$jquery_config_name = 'pjb_'; // all config variables of phpBB jQuery Base start with this
			$cur_pos = strpos($key, $jquery_config_name);
			if($cur_pos !== false)
			{
				$this->config[$key] = $value;
			}
		}
	}
	
	/*
	* Run actions for the specified mode
	*
	* @param: none
	*/
	public function run_actions()
	{
		global $config, $auth;

		// don't do anything if we already have an error because it can only be a "NO_MODE" error
		if(empty($error))
		{
			switch($this->mode)
			{
				case 'quickreply':
					if ($config['pjb_quickreply_enable'] && $auth->acl_get('u_quickreply'))
					{
						$this->quickreply();
					}
				break;
				case 'quickedit':
					if ($config['pjb_quickedit_enable'] && $auth->acl_get('u_quickedit'))
					{
						$this->quickedit();
					}
				break;
				case 'markread_forums':
					if ($config['pjb_markread_enable'])
					{
						$this->mark_read('forums');
					}
				break;
				case 'markread_topics':
					if ($config['pjb_markread_enable'])
					{
						$this->mark_read('topics');
					}
				break;
				case 'login':
					if ($config['pjb_login_enable'])
					{
						$this->login();
					}
				break;
				case 'check_pm':
					// add check ... maybe
					$this->check_pm();
				break;
			}
		}
	}


	/*
	* Decide what functions we need to run after run_actions()
	* @param: none
	*/
	public function page_footer()
	{
		global $template, $user;

		if(!empty($this->error))
		{
			$this->return_ary['ERROR_ACTION'] = 'return';
			foreach($this->error as $cur_error)
			{
				if($cur_error['action'] == 'cancel')
				{
					$this->load_tpl = false; // make sure we don't load any template
					$this->return_ary['ERROR_ACTION'] = 'cancel';
				}
				// replace lang vars if possible
				$this->return_ary['ERROR'][] = (isset($user->lang[$cur_error['error']])) ? $user->lang[$cur_error['error']] : $cur_error['error'];
			}
			$this->return_ary['ERROR_COUNT'] = sizeof($this->return_ary['ERROR']);
		}
		
		if($this->load_tpl)
		{
			$template->set_filenames(array(
						'body' => $this->tpl_file)
					);
			page_footer();
		}
		elseif(isset($this->return_ary))
		{
			echo json_encode($this->return_ary);
		}
	}
	
	

	/*
	* include a file if its functions or class does not exist yet
	* always use this function for including files
	* 
	* @param <string> $file The path to the file that needs to be included (relative to the phpbb root path & just the filename, i.e. 'index' for index.php)
	* @param <string> $check The function or class that shouldn't exist if the file hasn't been included yet
	* @param <bool> $class Set to true if you would like to check for a class and false if you would like to check for a function
	*/
	private function include_file($file, $check, $class = false)
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
			if(!function_exists($check))
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
	private function add_return($return_ary, $force = false)
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
	private function quickedit()
	{
		global $db, $config, $auth, $template, $user;

		// the first post is 1, so any post_id below 1 isn't possible
		if($this->post_id < 1)
		{
			$this->error[] = array('error' => $user->lang['NO_MODE'], 'action' => 'cancel');
			$mode = '';
			//$this->tpl_load = false; // page_footer already does this
		}
		else
		{
			$qe_mode = request_var('qe_mode', '');
		}

		switch($qe_mode)
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
				
				// generate hidden fields for form
				global $phpbb_root_path, $phpEx;
				
				// this is just for the attachment_data
				$message_parser = new parse_message();
				
				$message_parser->message = $text['text'];
				
				// Always check if the submitted attachment data is valid and belongs to the user.
				// Further down (especially in submit_post()) we do not check this again.
				$message_parser->get_submitted_attachment_data($row['poster_id']);

				if ($row['post_attachment'])
				{
					// Do not change to SELECT *
					$sql = 'SELECT attach_id, is_orphan, attach_comment, real_filename
						FROM ' . ATTACHMENTS_TABLE . '
						WHERE post_msg_id = ' . (int) $this->post_id . '
							AND in_message = 0
							AND is_orphan = 0
						ORDER BY filetime DESC';
					$result = $db->sql_query($sql);
					$message_parser->attachment_data = array_merge($message_parser->attachment_data, $db->sql_fetchrowset($result));
					$db->sql_freeresult($result);
				}
					
				$s_hidden_fields = '<input type="hidden" name="lastclick" value="' . time() . '" />';
				$s_hidden_fields .= build_hidden_fields(array(
					'edit_post_message_checksum'	=> $row['post_checksum'],
					'edit_post_subject_checksum'	=> (isset($post_data['post_subject'])) ? md5($row['post_subject']) : '',
					'message'						=> $text['text'],
					'full_editor' 					=> true,
					'subject'						=> $row['post_subject'],
					'attachment_data' 				=> $message_parser->attachment_data,
				));
				
				$template->assign_vars(array(
					'U_ADVANCED_EDIT' 	=> append_sid("{$phpbb_root_path}posting.$phpEx", 'mode=edit&amp;f=' . $forum_id . "&amp;t={$row['topic_id']}&amp;p={$row['post_id']}"),
					'S_HIDDEN_FIELDS' 	=> $s_hidden_fields,
				));
				
				// Build custom bbcodes array
				display_custom_bbcodes();
				
				// Assign important template vars
				$template->assign_vars(array(
					'POST_TEXT'   			=> (!empty($this->error)) ? '' : $text['text'], // Don't show the text if there was a permission error
					'S_LINKS_ALLOWED'       => $url_status,
					'S_BBCODE_IMG'          => $img_status,
					'S_BBCODE_FLASH'		=> $flash_status,
					'S_BBCODE_QUOTE'		=> $quote_status,
					'S_BBCODE_ALLOWED'		=> $bbcode_status,
					'MAX_FONT_SIZE'			=> (int) $config['max_post_font_size'],
				));

				$this->load_tpl = false;
				
				$template->set_filenames(array(
						'body' =>'jquery_base/quickedit.html')
					);

				// get parsed template
				$tpl_content = $template->assign_display('body');
				
				$this->add_return(array(
					'TPL_BODY'				=> $tpl_content,
				));
				
			break;
			
			case 'submit':
				/* 
				* only include functions_posting if we actually need it
				* make sure we don't include it if it already has been included by some other MOD
				*/
				$this->include_file('includes/functions_posting', 'submit_post');

				$sql = 'SELECT p.*, f.*, t.*, u.*, p.icon_id AS post_icon_id
						FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f, ' . USERS_TABLE . ' u
						WHERE p.post_id = ' . (int) $this->post_id . ' 
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
						WHERE post_msg_id = ' . (int) $this->post_id . '
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
						WHERE post_msg_id = ' . (int) $this->post_id . '
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
				if(!sizeof($this->error))
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
				

					$this->add_return(array(
						'EDITED_BY'	=> $l_edited_by,
						'TEXT'		=> $text,
					));
					$this->load_tpl = false;
					
					$this->add_return(array(
						'SUCCESS_MESSAGE' => $user->lang['PJB_QUICKEDIT_SUCCESS_MSG'],
						'SUCCESS_TITLE' => $user->lang['PJB_QUICKEDIT_SUCCESS'],
					));
					/* 
					* Don't run submit_post before we checked for errors
					* $mode is always edit as we just edit a post with this MOD
					* $username is set to $user->data['username'] as we don't need the clean username for the logs
					*/
					submit_post('edit', $post_data['post_subject'], $post_data['username'], $post_data['topic_type'], $poll, $data);
				}
				else
				{
					$this->load_tpl = false;
				}
				
			break;
			
			default:
		}
	}

	/*
	* Quickly reply to posts
	*
	* posting and viewtopic code from phpBB 3.0.8:
	* @copyright (c) 2005 phpBB Group
	*/
	private function quickreply()
	{
		global $db, $config, $auth, $template, $user;
		
		$i = 1;
		$reply_data = $data = array();
		
		$current_time = time();
		
		// get post data from jQuery
		while(isset($_POST["reply_data$i"]))
		{
			$cur_name = utf8_normalize_nfc(request_var("reply_data$i", ''));
			$cur_val = utf8_normalize_nfc(request_var("reply_data_val$i", ''));
			
			$reply_data[$cur_name] = $cur_val;
			++$i;
		}
		
		$reply_data['subject'] = utf8_normalize_nfc(request_var('subject', '', true));
		
		// set basic data
		if (!$reply_data['topic_id'] || empty($reply_data['topic_id']))
		{
			$this->error[] = array('error' => 'NO_TOPIC', 'action' => 'cancel');
		}
		else
		{
			$reply_data['topic_id'] = (int) $reply_data['topic_id'];
		}
		
		// Force forum id
		$sql = 'SELECT forum_id
			FROM ' . TOPICS_TABLE . '
			WHERE topic_id = ' . $reply_data['topic_id'];
		$result = $db->sql_query($sql);
		$f_id = (int) $db->sql_fetchfield('forum_id');
		$db->sql_freeresult($result);

		$reply_data['forum_id'] = (!$f_id) ? (int) $reply_data['forum_id'] : $f_id;
		
		// find out which page we are on
		$page_start = request_var('start', 0); // cut everything before start
		
		$sql = 'SELECT f.*, t.*
			FROM ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . " f
			WHERE t.topic_id = {$reply_data['topic_id']}
				AND (f.forum_id = t.forum_id
					OR f.forum_id = {$reply_data['forum_id']})" .
			(($auth->acl_get('m_approve', $reply_data['forum_id'])) ? '' : 'AND t.topic_approved = 1');
		$result = $db->sql_query($sql);
		$post_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// Not able to reply to unapproved posts/topics
		if ($auth->acl_get('m_approve', $reply_data['forum_id']) && (!$post_data['topic_approved'] || (isset($post_data['post_approved']) && !$post_data['post_approved'])))
		{
			trigger_error('TOPIC_UNAPPROVED');
		}
		
		$user->add_lang(array('posting', 'viewtopic'));
		
		// Check permissions
		if ($user->data['is_bot'])
		{
			$this->error[] = array(append_sid("{$phpbb_root_path}index.$phpEx"));
			$this->error[] = array('error' => 'RULES_REPLY_CANNOT', 'action' => 'cancel');
		}

		// Is the user able to read within this forum?
		if (!$auth->acl_get('f_read', $reply_data['forum_id']))
		{
			if ($user->data['user_id'] != ANONYMOUS)
			{
				$this->error[] = array('error' => 'USER_CANNOT_READ', 'action' => 'cancel');
			}
		}
		
		if (!$user->data['is_registered'] || !$auth->acl_gets('f_edit', 'm_edit', $reply_data['forum_id']))
		{
			$this->error[] = array('error' => 'USER_CANNOT_REPLY', 'action' => 'cancel');
		}
		
		// Is the user able to post within this forum?
		if ($post_data['forum_type'] != FORUM_POST)
		{
			$this->error[] = array('error' => 'USER_CANNOT_FORUM_POST', 'action' => 'cancel');
		}
		
		// Forum/Topic locked?
		if (($post_data['forum_status'] == ITEM_LOCKED || (isset($post_data['topic_status']) && $post_data['topic_status'] == ITEM_LOCKED)) && !$auth->acl_get('m_edit', $reply_data['forum_id']))
		{
			$this->error[] = array('error' => (($post_data['forum_status'] == ITEM_LOCKED) ? 'FORUM_LOCKED' : 'TOPIC_LOCKED'), 'action' => 'cancel');
		}
		
		// Determine some vars
		if (isset($post_data['poster_id']) && $post_data['poster_id'] == ANONYMOUS)
		{
			$post_data['quote_username'] = (!empty($post_data['post_username'])) ? $post_data['post_username'] : $user->lang['GUEST'];
		}
		else
		{
			$post_data['quote_username'] = isset($post_data['username']) ? $post_data['username'] : '';
		}
		
		$post_data['post_edit_locked']	= 0;
		$post_data['post_subject']		= (isset($post_data['topic_title'])) ? $post_data['topic_title'] : '';
		$post_data['topic_time_limit']	= (isset($post_data['topic_time_limit'])) ? (($post_data['topic_time_limit']) ? (int) $post_data['topic_time_limit'] / 86400 : (int) $post_data['topic_time_limit']) : 0;
		$post_data['poll_length']		= (!empty($post_data['poll_length'])) ? (int) $post_data['poll_length'] / 86400 : 0;
		$post_data['poll_start']		= (!empty($post_data['poll_start'])) ? (int) $post_data['poll_start'] : 0;
		$post_data['icon_id']			= 0;
		$post_data['poll_options']		= array();
		
		$orig_poll_options_size = sizeof($post_data['poll_options']);

		$message_parser = new parse_message();

		// Set some default variables
		$uninit = array('post_attachment' => 0, 'poster_id' => $user->data['user_id'], 'enable_magic_url' => 0, 'topic_status' => 0, 'topic_type' => POST_NORMAL, 'post_subject' => '', 'topic_title' => '', 'post_time' => 0, 'post_edit_reason' => '', 'notify_set' => 0);
		
		foreach ($uninit as $var_name => $default_value)
		{
			if (!isset($post_data[$var_name]))
			{
				$post_data[$var_name] = $default_value;
			}
		}
		unset($uninit);
		
		// Always check if the submitted attachment data is valid and belongs to the user.
		// Further down (especially in submit_post()) we do not check this again.
		$message_parser->get_submitted_attachment_data($post_data['poster_id']);
		
		$post_data['username'] = '';
		
		$post_data['enable_urls'] = $post_data['enable_magic_url'];
		$post_data['enable_sig']		= ($config['allow_sig'] && $user->optionget('attachsig')) ? true: false;
		$post_data['enable_smilies']	= ($config['allow_smilies'] && $user->optionget('smilies')) ? true : false;
		$post_data['enable_bbcode']		= ($config['allow_bbcode'] && $user->optionget('bbcode')) ? true : false;
		$post_data['enable_urls']		= true;
		$post_data['enable_magic_url'] = $post_data['drafts'] = false;
		
		$check_value = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);
		
		// Check if user is watching this topic
		if ($config['allow_topic_notify'] && $user->data['is_registered'])
		{
			$sql = 'SELECT topic_id
				FROM ' . TOPICS_WATCH_TABLE . '
				WHERE topic_id = ' . $reply_data['topic_id'] . '
					AND user_id = ' . $user->data['user_id'];
			$result = $db->sql_query($sql);
			$post_data['notify_set'] = (int) $db->sql_fetchfield('topic_id');
			$db->sql_freeresult($result);
		}
		
		// HTML, BBCode, Smilies, Images and Flash status
		$bbcode_status	= ($config['allow_bbcode'] && $auth->acl_get('f_bbcode', $reply_data['forum_id'])) ? true : false;
		$smilies_status	= ($config['allow_smilies'] && $auth->acl_get('f_smilies', $reply_data['forum_id'])) ? true : false;
		$img_status		= ($bbcode_status && $auth->acl_get('f_img', $reply_data['forum_id'])) ? true : false;
		$url_status		= ($config['allow_post_links']) ? true : false;
		$flash_status	= ($bbcode_status && $auth->acl_get('f_flash', $reply_data['forum_id']) && $config['allow_post_flash']) ? true : false;
		$quote_status	= true;
		
		
		// now begin preparing for submitting this reply
		$post_data['topic_cur_post_id']	= request_var('topic_cur_post_id', 0);
		$post_data['post_subject']		= $reply_data['subject'];
		$message_parser->message		= utf8_normalize_nfc(request_var('message', '', true));

		$post_data['post_edit_reason']	= '';

		$post_data['orig_topic_type']	= $post_data['topic_type'];
		$post_data['topic_type']		= (int) $post_data['topic_type'];
		$post_data['topic_time_limit']	= (int) $post_data['topic_time_limit'];
		
		$post_data['enable_bbcode']		= (!$bbcode_status) ? false : true;
		$post_data['enable_smilies']	= (!$smilies_status) ? false : true;
		$post_data['enable_urls']		= 1;
		$post_data['enable_sig']		= (!$config['allow_sig'] || !$auth->acl_get('f_sigs', $reply_data['forum_id']) || !$auth->acl_get('u_sig')) ? false : ((isset($reply_data['attach_sig']) && $user->data['is_registered']) ? true : false);
		$notify = false; // defaults to 0 ( we can't select to be notified ;) )
		
		$topic_lock			= false;
		$post_lock			= false;
		$poll_delete		= false;
		
		$status_switch = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);
		$status_switch = ($status_switch != $check_value);
		
		// default values since we can't do anything with a poll in quickreply
		$post_data['poll_title']		= '';
		$post_data['poll_length']		= 0;
		$post_data['poll_option_text']	= '';
		$post_data['poll_max_options']	= 1;
		$post_data['poll_vote_change']	= 0;
		
		
		// If replying/quoting and last post id has changed
		// give user option to continue submit or return to post
		// notify and show user the post made between his request and the final submit
		if ($post_data['topic_cur_post_id'] && $post_data['topic_cur_post_id'] != $post_data['topic_last_post_id'])
		{
			// Only do so if it is allowed forum-wide
			if ($post_data['forum_flags'] & FORUM_FLAG_POST_REVIEW)
			{
				if (topic_review($reply_data['topic_id'], $reply_data['forum_id'], 'post_review', $post_data['topic_cur_post_id']))
				{
					$this->error[] = array('error' => 'POST_REVIEW_EXPLAIN', 'action' => 'return');
				}
			}
		}
		
		// Grab md5 'checksum' of new message
		$message_md5 = md5($message_parser->message);
		
		$update_message = ($status_switch) ? true : false;
		
		// let the message parser run
		$message_parser->parse($post_data['enable_bbcode'], ($config['allow_post_links']) ? $post_data['enable_urls'] : false, $post_data['enable_smilies'], $img_status, $flash_status, $quote_status, $config['allow_post_links']);
		
		if ($config['flood_interval'] && !$auth->acl_get('f_ignoreflood', $reply_data['forum_id']))
		{
			// Flood check
			$last_post_time = 0;

			if ($user->data['is_registered'])
			{
				$last_post_time = $user->data['user_lastpost_time'];
			}
			else
			{
				$sql = 'SELECT post_time AS last_post_time
					FROM ' . POSTS_TABLE . "
					WHERE poster_ip = '" . $user->ip . "'
						AND post_time > " . (time() - $config['flood_interval']);
				$result = $db->sql_query_limit($sql, 1);
				if ($row = $db->sql_fetchrow($result))
				{
					$last_post_time = $row['last_post_time'];
				}
				$db->sql_freeresult($result);
			}

			if ($last_post_time && (time() - $last_post_time) < intval($config['flood_interval']))
			{
				$this->error[] = array('error' => 'FLOOD_ERROR', 'action' => 'return');
			}
		}
		
		// Validate username
		if ($post_data['username'] && !$user->data['is_registered'])
		{
			$this->include_file('includes/functions_user', 'user_get_id_name');

			if (($result = validate_username($post_data['username'], (!empty($post_data['post_username'])) ? $post_data['post_username'] : '')) !== false)
			{
				$user->add_lang('ucp');
				$this->error[] = array('error' => $result . '_USERNAME', 'action' => 'return');
			}
		}
			
		// check form key
		
		// we enforce a minimum value of half a minute here.
		$timespan = ($config['form_token_lifetime'] == -1) ? -1 : max(30, $config['form_token_lifetime']);

		if (isset($reply_data['creation_time']) && isset($reply_data['form_token']))
		{
			$creation_time	= abs($reply_data['creation_time']);
			$token = $reply_data['form_token'];
			$form_name = 'posting';

			$diff = time() - $creation_time;

			// If creation_time and the time() now is zero we can assume it was not a human doing this (the check for if ($diff)...
			if ($diff && ($diff <= $timespan || $timespan === -1))
			{
				$token_sid = ($user->data['user_id'] == ANONYMOUS && !empty($config['form_token_sid_guests'])) ? $user->session_id : '';
				$key = sha1($creation_time . $user->data['user_form_salt'] . $form_name . $token_sid);

				if ($key === $token)
				{
					$correct_token = true;
				}
			}
		}
		
		if(!$correct_token)
		{
			$this->error[] = array('error' => 'FORM_INVALID', 'action' => 'return');
		}
		
		$post_data['poll_last_vote'] = 0;
		$poll = array();
		
		if (sizeof($message_parser->warn_msg))
		{
			foreach($message_parser->warn_msg as $cur_error)
			{
				$this->error[] = array('error' => $cur_error, 'action' => 'return');
			}
		}
		
		// DNSBL check
		if ($config['check_dnsbl'])
		{
			if (($dnsbl = $user->check_dnsbl('post')) !== false)
			{
				$this->error[] = array('error' => sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]), 'action' => 'return');
			}
		}
		
		if (!sizeof($this->error))
		{
			$data = array(
				'topic_title'			=> (empty($post_data['topic_title'])) ? $post_data['post_subject'] : $post_data['topic_title'],
				'topic_first_post_id'	=> (isset($post_data['topic_first_post_id'])) ? (int) $post_data['topic_first_post_id'] : 0,
				'topic_last_post_id'	=> (isset($post_data['topic_last_post_id'])) ? (int) $post_data['topic_last_post_id'] : 0,
				'topic_time_limit'		=> (int) $post_data['topic_time_limit'],
				'topic_attachment'		=> (isset($post_data['topic_attachment'])) ? (int) $post_data['topic_attachment'] : 0,
				'post_id'				=> 0,
				'topic_id'				=> (int) $reply_data['topic_id'],
				'forum_id'				=> (int) $reply_data['forum_id'],
				'icon_id'				=> (int) $post_data['icon_id'],
				'poster_id'				=> (int) $post_data['poster_id'],
				'enable_sig'			=> (bool) $post_data['enable_sig'],
				'enable_bbcode'			=> (bool) $post_data['enable_bbcode'],
				'enable_smilies'		=> (bool) $post_data['enable_smilies'],
				'enable_urls'			=> (bool) $post_data['enable_urls'],
				'enable_indexing'		=> (bool) $post_data['enable_indexing'],
				'message_md5'			=> (string) $message_md5,
				'post_time'				=> $current_time,
				'post_checksum'			=> (isset($post_data['post_checksum'])) ? (string) $post_data['post_checksum'] : '',
				'post_edit_reason'		=> $post_data['post_edit_reason'],
				'post_edit_user'		=> (isset($post_data['post_edit_user'])) ? (int) $post_data['post_edit_user'] : 0,
				'post_subject'			=> $post_data['post_subject'],
				'forum_parents'			=> $post_data['forum_parents'],
				'forum_name'			=> $post_data['forum_name'],
				'notify'				=> $notify,
				'notify_set'			=> $post_data['notify_set'],
				'poster_ip'				=> (isset($post_data['poster_ip'])) ? $post_data['poster_ip'] : $user->ip,
				'post_edit_locked'		=> (int) $post_data['post_edit_locked'],
				'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
				'bbcode_uid'			=> $message_parser->bbcode_uid,
				'message'				=> $message_parser->message,
				'attachment_data'		=> $message_parser->attachment_data,
				'filename_data'			=> $message_parser->filename_data,

				'topic_approved'		=> (isset($post_data['topic_approved'])) ? $post_data['topic_approved'] : false,
				'post_approved'			=> ($auth->acl_get('f_noapprove', $reply_data['forum_id'])) ? true : false,
				
				'force_approved_state'  => true, // post has already been approved
			);
			
			// The last parameter tells submit_post if search indexer has to be run
			$redirect_url = submit_post('reply', $post_data['post_subject'], $post_data['username'], $post_data['topic_type'], $poll, $data, $update_message, ($update_message) ? true : false);
			
			// redirect to redirect_url if we are not on the same page
			$cur_posts = (($auth->acl_get('m_approve', $data['forum_id'])) ? $post_data['topic_replies'] : $post_data['topic_replies_real']) + 1;
			$cur_page = floor($cur_posts / $config['posts_per_page']) * $config['posts_per_page'];
			$redirect = ($cur_page != $page_start) ? true : false;
			
			// check if we are maybe on a page without the start parameter but with the p parameter
			$cur_post_id = request_var('p', 0);
			$sort_dir	= request_var('sd', (!empty($user->data['user_post_sortby_dir'])) ? $user->data['user_post_sortby_dir'] : 'a');
			// This is for determining where we are (page)
			if ($cur_post_id)
			{
				// are we where we are supposed to be?
				if (!$data['post_approved'] && !$auth->acl_get('m_approve', $post_data['forum_id']))
				{
					// If post_id was submitted, we try at least to display the topic as a last resort...
					if ($data['topic_id'])
					{
						$redirect_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "t={$data['topic_id']}" . (($forum_id) ? "&amp;f={$data['forum_id']}" : ''));
					}
					else
					{
						$this->error[] = array('error' => 'NO_TOPIC', 'action' => 'return');
					}
				}
				if ($cur_post_id == $post_data['topic_first_post_id'] || $cur_post_id == $post_data['topic_last_post_id'])
				{
					$check_sort = ($cur_post_id == $post_data['topic_first_post_id']) ? 'd' : 'a';

					if ($sort_dir == $check_sort)
					{
						$post_data['prev_posts'] = ($auth->acl_get('m_approve', $data['forum_id'])) ? $post_data['topic_replies_real'] : $post_data['topic_replies'];
					}
					else
					{
						$post_data['prev_posts'] = 0;
					}
				}
				else
				{
					$sql = 'SELECT COUNT(p.post_id) AS prev_posts
						FROM ' . POSTS_TABLE . " p
						WHERE p.topic_id = {$post_data['topic_id']}
							" . ((!$auth->acl_get('m_approve', $data['forum_id'])) ? 'AND p.post_approved = 1' : '');

					if ($sort_dir == 'd')
					{
						$sql .= " AND (p.post_time > {$data['post_time']} OR (p.post_time = {$data['post_time']} AND p.post_id >= {$data['post_id']}))";
					}
					else
					{
						$sql .= " AND (p.post_time < {$data['post_time']} OR (p.post_time = {$data['post_time']} AND p.post_id <= {$data['post_id']}))";
					}

					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					$post_data['prev_posts'] = $row['prev_posts'] - 1;
				}
			}
			
			// no matter what $redirect is set to, make sure we don't do an unnecessary redirect
			if (isset($post_data['prev_posts']) && !empty($post_data['prev_posts']))
			{
				$cur_page = floor($cur_posts / $config['posts_per_page']) * $config['posts_per_page'];
				$page_start = floor($post_data['prev_posts'] / $config['posts_per_page']) * $config['posts_per_page'];
				$redirect = ($cur_page != $page_start) ? true : false;
			}
			
			// Now get the post id of the new post
			$post_id = $data['post_id'];
			
			// Now let's start with the viewtopic part of this function
			global $phpbb_root_path, $phpEx;
			
			$user_cache = array();
			$bbcode_bitfield = $view = '';
			
			// get $user_cache
			$sql = $db->sql_build_query('SELECT', array(
				'SELECT'	=> 'u.*, z.friend, z.foe, p.*',

				'FROM'		=> array(
					USERS_TABLE		=> 'u',
					POSTS_TABLE		=> 'p',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(ZEBRA_TABLE => 'z'),
						'ON'	=> 'z.user_id = ' . $user->data['user_id'] . ' AND z.zebra_id = p.poster_id'
					)
				),

				'WHERE'		=> $db->sql_in_set('p.post_id', array($post_id)) . '
					AND u.user_id = p.poster_id'
			));

			$result = $db->sql_query($sql);
			
			// Posts are stored in the $rowset array while $attach_list, $user_cache
			// and the global bbcode_bitfield are built
			while ($row = $db->sql_fetchrow($result))
			{
				// Set max_post_time
				$max_post_time = $data['post_time'];

				$poster_id = (int) $data['poster_id'];

				// Define the global bbcode bitfield, will be used to load bbcodes
				$bbcode_bitfield = $bbcode_bitfield | base64_decode($data['bbcode_bitfield']);

				// Is a signature attached? Are we going to display it?
				if ($data['enable_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
				{
					$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['user_sig_bbcode_bitfield']);
				}

				// Cache various user specific data ... so we don't have to recompute
				// this each time the same user appears on this page
				if (!isset($user_cache[$poster_id]))
				{
					if ($poster_id == ANONYMOUS)
					{
						$user_cache[$poster_id] = array(
							'joined'		=> '',
							'posts'			=> '',
							'from'			=> '',

							'sig'					=> '',
							'sig_bbcode_uid'		=> '',
							'sig_bbcode_bitfield'	=> '',

							'online'			=> false,
							'avatar'			=> ($user->optionget('viewavatars')) ? get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']) : '',
							'rank_title'		=> '',
							'rank_image'		=> '',
							'rank_image_src'	=> '',
							'sig'				=> '',
							'profile'			=> '',
							'pm'				=> '',
							'email'				=> '',
							'www'				=> '',
							'icq_status_img'	=> '',
							'icq'				=> '',
							'aim'				=> '',
							'msn'				=> '',
							'yim'				=> '',
							'jabber'			=> '',
							'search'			=> '',
							'age'				=> '',

							'username'			=> $row['username'],
							'user_colour'		=> $row['user_colour'],

							'warnings'			=> 0,
							'allow_pm'			=> 0,
						);

						get_user_rank($row['user_rank'], false, $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);
					}
					else
					{
						$user_sig = '';

						// We add the signature to every posters entry because enable_sig is post dependant
						if ($row['user_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
						{
							$user_sig = $row['user_sig'];
						}

						$id_cache[] = $poster_id;

						$user_cache[$poster_id] = array(
							'joined'		=> $user->format_date($row['user_regdate']),
							'posts'			=> $row['user_posts'],
							'warnings'		=> (isset($row['user_warnings'])) ? $row['user_warnings'] : 0,
							'from'			=> (!empty($row['user_from'])) ? $row['user_from'] : '',

							'sig'					=> $user_sig,
							'sig_bbcode_uid'		=> (!empty($row['user_sig_bbcode_uid'])) ? $row['user_sig_bbcode_uid'] : '',
							'sig_bbcode_bitfield'	=> (!empty($row['user_sig_bbcode_bitfield'])) ? $row['user_sig_bbcode_bitfield'] : '',

							'viewonline'	=> $row['user_allow_viewonline'],
							'allow_pm'		=> $row['user_allow_pm'],

							'avatar'		=> ($user->optionget('viewavatars')) ? get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']) : '',
							'age'			=> '',

							'rank_title'		=> '',
							'rank_image'		=> '',
							'rank_image_src'	=> '',

							'username'			=> $row['username'],
							'user_colour'		=> $row['user_colour'],

							'online'		=> false,
							'profile'		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u=$poster_id"),
							'www'			=> $row['user_website'],
							'aim'			=> ($row['user_aim'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=aim&amp;u=$poster_id") : '',
							'msn'			=> ($row['user_msnm'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=msnm&amp;u=$poster_id") : '',
							'yim'			=> ($row['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($row['user_yim']) . '&amp;.src=pg' : '',
							'jabber'		=> ($row['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=jabber&amp;u=$poster_id") : '',
							'search'		=> ($auth->acl_get('u_search')) ? append_sid("{$phpbb_root_path}search.$phpEx", "author_id=$poster_id&amp;sr=posts") : '',

							'author_full'		=> get_username_string('full', $poster_id, $row['username'], $row['user_colour']),
							'author_colour'		=> get_username_string('colour', $poster_id, $row['username'], $row['user_colour']),
							'author_username'	=> get_username_string('username', $poster_id, $row['username'], $row['user_colour']),
							'author_profile'	=> get_username_string('profile', $poster_id, $row['username'], $row['user_colour']),
						);

						get_user_rank($row['user_rank'], $row['user_posts'], $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);

						if ((!empty($row['user_allow_viewemail']) && $auth->acl_get('u_sendemail')) || $auth->acl_get('a_email'))
						{
							$user_cache[$poster_id]['email'] = ($config['board_email_form'] && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=email&amp;u=$poster_id") : (($config['board_hide_emails'] && !$auth->acl_get('a_email')) ? '' : 'mailto:' . $row['user_email']);
						}
						else
						{
							$user_cache[$poster_id]['email'] = '';
						}

						if (!empty($row['user_icq']))
						{
							$user_cache[$poster_id]['icq'] = 'http://www.icq.com/people/webmsg.php?to=' . $row['user_icq'];
							$user_cache[$poster_id]['icq_status_img'] = '<img src="http://web.icq.com/whitepages/online?icq=' . $row['user_icq'] . '&amp;img=5" width="18" height="18" alt="" />';
						}
						else
						{
							$user_cache[$poster_id]['icq_status_img'] = '';
							$user_cache[$poster_id]['icq'] = '';
						}

						if ($config['allow_birthdays'] && !empty($row['user_birthday']))
						{
							list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $row['user_birthday']));

							if ($bday_year)
							{
								$now = getdate(time() + $user->timezone + $user->dst - date('Z'));

								$diff = $now['mon'] - $bday_month;
								if ($diff == 0)
								{
									$diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
								}
								else
								{
									$diff = ($diff < 0) ? 1 : 0;
								}

								$user_cache[$poster_id]['age'] = (int) ($now['year'] - $bday_year - $diff);
							}
						}
					}
				}
			}
			$db->sql_freeresult($result);
			
			$edit_allowed = ($user->data['is_registered'] && ($auth->acl_get('m_edit', $data['forum_id']) || (
				$user->data['user_id'] == $poster_id &&
				$auth->acl_get('f_edit', $data['forum_id']) &&
				!$row['post_edit_locked'] &&
				($row['post_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
			)));

			$delete_allowed = ($user->data['is_registered'] && ($auth->acl_get('m_delete', $data['forum_id']) || (
				$user->data['user_id'] == $poster_id &&
				$auth->acl_get('f_delete', $data['forum_id']) &&
				$post_data['topic_last_post_id'] == $post_id &&
				($row['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
				// we do not want to allow removal of the last post if a moderator locked it!
				!$row['post_edit_locked']
			)));
			
			/*
			* Parse message
			*/
			
			// Add up the flag options...
			$bbcode_options = (($data['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) + (($data['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) + (($data['enable_urls']) ? OPTION_FLAG_LINKS : 0);
			// Parse the post
			$message = generate_text_for_display($data['message'], $data['bbcode_uid'], $data['bbcode_bitfield'], $bbcode_options);
			
			$postrow = array(
				'POST_AUTHOR_FULL'		=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_full'] : get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'POST_AUTHOR_COLOUR'	=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_colour'] : get_username_string('colour', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'POST_AUTHOR'			=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_username'] : get_username_string('username', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
				'U_POST_AUTHOR'			=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_profile'] : get_username_string('profile', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),

				'RANK_TITLE'		=> $user_cache[$poster_id]['rank_title'],
				'RANK_IMG'			=> $user_cache[$poster_id]['rank_image'],
				'RANK_IMG_SRC'		=> $user_cache[$poster_id]['rank_image_src'],
				'POSTER_JOINED'		=> $user_cache[$poster_id]['joined'],
				'POSTER_POSTS'		=> $user_cache[$poster_id]['posts'],
				'POSTER_FROM'		=> $user_cache[$poster_id]['from'],
				'POSTER_AVATAR'		=> $user_cache[$poster_id]['avatar'],
				'POSTER_WARNINGS'	=> $user_cache[$poster_id]['warnings'],
				'POSTER_AGE'		=> $user_cache[$poster_id]['age'],

				'POST_DATE'			=> $user->format_date($data['post_time'], false, ($view == 'print') ? true : false),
				'POST_SUBJECT'		=> $data['post_subject'],
				'MESSAGE'			=> $message,
				'SIGNATURE'			=> ($row['enable_sig']) ? $user_cache[$poster_id]['sig'] : '',
				'EDITED_MESSAGE'	=> '',
				'EDIT_REASON'		=> $row['post_edit_reason'],
				'BUMPED_MESSAGE'	=> '',

				'MINI_POST_IMG'			=> $user->img('icon_post_target_unread', 'UNREAD_POST'), // it's always unread if we are here
				'POST_ICON_IMG'			=> ($post_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['img'] : '',
				'POST_ICON_IMG_WIDTH'	=> ($post_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['width'] : '',
				'POST_ICON_IMG_HEIGHT'	=> ($post_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['height'] : '',
				'ICQ_STATUS_IMG'		=> $user_cache[$poster_id]['icq_status_img'],
				'ONLINE_IMG'			=> ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? '' : (($user_cache[$poster_id]['online']) ? $user->img('icon_user_online', 'ONLINE') : $user->img('icon_user_offline', 'OFFLINE')),
				'S_ONLINE'				=> ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? false : (($user_cache[$poster_id]['online']) ? true : false),

				'U_EDIT'			=> ($edit_allowed) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=edit&amp;f={$data['forum_id']}&amp;p=$post_id") : '',
				'U_QUOTE'			=> ($auth->acl_get('f_reply', $data['forum_id'])) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=quote&amp;f={$data['forum_id']}&amp;p=$post_id") : '',
				'U_INFO'			=> ($auth->acl_get('m_info', $data['forum_id'])) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "i=main&amp;mode=post_details&amp;f={$data['forum_id']}&amp;p=" . $post_id, true, $user->session_id) : '',
				'U_DELETE'			=> ($delete_allowed) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=delete&amp;f={$data['forum_id']}&amp;p=$post_id") : '',

				'U_PROFILE'		=> $user_cache[$poster_id]['profile'],
				'U_SEARCH'		=> $user_cache[$poster_id]['search'],
				'U_PM'			=> ($poster_id != ANONYMOUS && $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($user_cache[$poster_id]['allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'))) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;action=quotepost&amp;p=' . $post_id) : '',
				'U_EMAIL'		=> $user_cache[$poster_id]['email'],
				'U_WWW'			=> $user_cache[$poster_id]['www'],
				'U_ICQ'			=> $user_cache[$poster_id]['icq'],
				'U_AIM'			=> $user_cache[$poster_id]['aim'],
				'U_MSN'			=> $user_cache[$poster_id]['msn'],
				'U_YIM'			=> $user_cache[$poster_id]['yim'],
				'U_JABBER'		=> $user_cache[$poster_id]['jabber'],

				'U_REPORT'			=> ($auth->acl_get('f_report', $data['forum_id'])) ? append_sid("{$phpbb_root_path}report.$phpEx", 'f=' . $data['forum_id'] . '&amp;p=' . $post_id) : '',
				'U_MCP_REPORT'		=> ($auth->acl_get('m_report', $data['forum_id'])) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=reports&amp;mode=report_details&amp;f=' . $data['forum_id'] . '&amp;p=' . $post_id, true, $user->session_id) : '',
				'U_MCP_APPROVE'		=> ($auth->acl_get('m_approve', $data['forum_id'])) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=approve_details&amp;f=' . $data['forum_id'] . '&amp;p=' . $post_id, true, $user->session_id) : '',
				'U_MINI_POST'		=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $post_id) . (($post_data['topic_type'] == POST_GLOBAL) ? '&amp;f=' . $data['forum_id'] : '') . '#p' . $post_id,
				'U_NEXT_POST_ID'	=> '',
				'U_PREV_POST_ID'	=> $post_data['topic_last_post_id'],
				'U_NOTES'			=> ($auth->acl_getf_global('m_')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=notes&amp;mode=user_notes&amp;u=' . $poster_id, true, $user->session_id) : '',
				'U_WARN'			=> ($auth->acl_get('m_warn') && $poster_id != $user->data['user_id'] && $poster_id != ANONYMOUS) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=warn&amp;mode=warn_post&amp;f=' . $data['forum_id'] . '&amp;p=' . $post_id, true, $user->session_id) : '',

				'POST_ID'			=> $post_id,
				'POSTER_ID'			=> $poster_id,

				'S_HAS_ATTACHMENTS'	=> (!empty($attachments[$post_id])) ? true : false,
				'S_POST_UNAPPROVED'	=> ($data['post_approved']) ? false : true,
				'S_POST_REPORTED'	=> false,
				'S_DISPLAY_NOTICE'	=> false,
				'S_FRIEND'			=> ($row['friend']) ? true : false,
				'S_UNREAD_POST'		=> true,
				'S_FIRST_UNREAD'	=> true,
				'S_CUSTOM_FIELDS'	=> (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
				'S_TOPIC_POSTER'	=> ($post_data['topic_poster'] == $poster_id) ? true : false,

				'S_IGNORE_POST'		=> ($row['hide_post']) ? true : false,
				'L_IGNORE_POST'		=> ($row['hide_post']) ? sprintf($user->lang['POST_BY_FOE'], get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']), '<a href="' . $viewtopic_url . "&amp;p=$post_id&amp;view=show#p$post_id" . '">', '</a>') : '',
			);

			// Dump vars into template
			$template->assign_block_vars('postrow', $postrow);
			$this->load_tpl = false;

			$template->set_filenames(array(
						'body' =>'jquery_base/quickreply.html')
					);

			// get parsed template
			$tpl_content = $template->assign_display('body');
			
			$this->add_return(array(
				'SUCCESS_MESSAGE'		=> $user->lang['PJB_QUICKREPLY_SUCCESS_MSG'],
				'SUCCESS_TITLE'			=> $user->lang['PJB_QUICKREPLY_SUCCESS'],
				'SUCCESS_REDIRECT'		=> $redirect,
				'SUCCESS_REDIRECT_URL'	=> $redirect_url,
				'TPL_BODY'				=> $tpl_content,
			));
		}
	}

	/*
	* mark forums read
	*
	* @param <string> $type The kind of "mark" this function should run (i.e. mark all forums read)
	*/
	private function mark_read($type)
	{
		global $db, $template, $auth, $user, $config, $phpbb_root_path, $phpEx;
		
		$forum_ids = array();

		if($user->data['is_registered'] || $config['load_anon_lastread'])
		{
			switch($type)
			{
				case 'forums':
					// if we are on the index page, mark all forums read
					$this->add_return(array(
						'LOCATION'	=> $this->location,
					));
					
					if(strpos($this->location, 'index') !== false)
					{
						// mark all forums read
						markread('all');
						$redirect_url = reapply_sid($phpbb_root_path . 'index.' . $phpEx); // redirect back to index
						
						$this->add_return(array(
							'SUCCESS_MESSAGE' => $user->lang['PJB_MARKREAD_FORUMS_SUCCESS_MSG'],
							'SUCCESS_TITLE' => $user->lang['PJB_MARKREAD_FORUMS_SUCCESS'],
						));
					}
					else
					{	
						$sql_from = FORUMS_TABLE . ' f';
						$lastread_select = '';

						// Grab appropriate forum data
						if ($config['load_db_lastread'] && $user->data['is_registered'])
						{
							$sql_from .= ' LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (ft.user_id = ' . $user->data['user_id'] . '
								AND ft.forum_id = f.forum_id)';
							$lastread_select .= ', ft.mark_time';
						}

						if ($user->data['is_registered'])
						{
							$sql_from .= ' LEFT JOIN ' . FORUMS_WATCH_TABLE . ' fw ON (fw.forum_id = f.forum_id AND fw.user_id = ' . $user->data['user_id'] . ')';
							$lastread_select .= ', fw.notify_status';
						}
						$sql = "SELECT f.* $lastread_select
							FROM $sql_from
							WHERE f.forum_id = " . $this->forum_id;
						$result = $db->sql_query($sql);
						$forum_data = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);

						if (!$forum_data)
						{
							trigger_error('NO_FORUM');
						}
						
						// Display list of active topics for this category?
						$show_active = (isset($forum_data['forum_flags']) && ($forum_data['forum_flags'] & FORUM_FLAG_ACTIVE_TOPICS)) ? true : false;
						
						$sql_array = array(
							'SELECT'	=> 'f.*',
							'FROM'		=> array(
								FORUMS_TABLE		=> 'f'
							),
							'LEFT_JOIN'	=> array(),
						);
						
						if ($config['load_db_lastread'] && $user->data['is_registered'])
						{
							$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TRACK_TABLE => 'ft'), 'ON' => 'ft.user_id = ' . $user->data['user_id'] . ' AND ft.forum_id = f.forum_id');
							$sql_array['SELECT'] .= ', ft.mark_time';
						}
						else if ($config['load_anon_lastread'] || $user->data['is_registered'])
						{
							$tracking_topics = (isset($_COOKIE[$config['cookie_name'] . '_track'])) ? ((STRIP) ? stripslashes($_COOKIE[$config['cookie_name'] . '_track']) : $_COOKIE[$config['cookie_name'] . '_track']) : '';
							$tracking_topics = ($tracking_topics) ? tracking_unserialize($tracking_topics) : array();

							if (!$user->data['is_registered'])
							{
								$user->data['user_lastmark'] = (isset($tracking_topics['l'])) ? (int) (base_convert($tracking_topics['l'], 36, 10) + $config['board_startdate']) : 0;
							}
						}
						
						if ($show_active)
						{
							$sql_array['LEFT_JOIN'][] = array(
								'FROM'	=> array(FORUMS_ACCESS_TABLE => 'fa'),
								'ON'	=> "fa.forum_id = f.forum_id AND fa.session_id = '" . $db->sql_escape($user->session_id) . "'"
							);

							$sql_array['SELECT'] .= ', fa.user_id';
						}
						
						if (!$forum_data)
						{
							$root_data = array('forum_id' => 0);
							$sql_where = '';
						}
						else
						{
							$sql_where = 'left_id > ' . $forum_data['left_id'] . ' AND left_id < ' . $forum_data['right_id'];
						}

						$sql = $db->sql_build_query('SELECT', array(
							'SELECT'	=> $sql_array['SELECT'],
							'FROM'		=> $sql_array['FROM'],
							'LEFT_JOIN'	=> $sql_array['LEFT_JOIN'],

							'WHERE'		=> $sql_where,

							'ORDER_BY'	=> 'f.left_id',
						));

						$result = $db->sql_query($sql);
						
						while ($row = $db->sql_fetchrow($result))
						{
							$cur_forum_id = $row['forum_id'];

							if ($auth->acl_get('f_list', $cur_forum_id))
							{
								$forum_ids[] = $cur_forum_id;
							}
						}
						
						// Add 0 to forums array to mark global announcements correctly
						$forum_ids[] = 0;
						markread('topics', $forum_ids);
						
						$redirect_url = reapply_sid($this->location); // redirect to the same page
						
						$this->add_return(array(
							'SUCCESS_MESSAGE' => $user->lang['PJB_MARKREAD_FORUMS_SUCCESS_MSG'],
							'SUCCESS_TITLE' => $user->lang['PJB_MARKREAD_FORUMS_SUCCESS'],
						));
					}
				break;
				case 'topics':
					// Add 0 to forums array to mark global announcements correctly
					markread('topics', array($this->forum_id, 0));
					
					// get forum name
					$sql = 'SELECT forum_name FROM '. FORUMS_TABLE . ' WHERE forum_id = ' . (int) $this->forum_id;
					$result = $db->sql_query($sql);
					$forum_name = $db->sql_fetchfield('forum_name');
					
					$redirect_url = append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $this->forum_id);
					$this->add_return(array(
						'SUCCESS_MESSAGE' => sprintf($user->lang['PJB_MARKREAD_TOPICS_SUCCESS_MSG'], $forum_name),
						'SUCCESS_TITLE' => $user->lang['PJB_MARKREAD_TOPICS_SUCCESS'],
					));
				break;
			}

			$this->add_return(array(
				'MARK_REDIRECT'	=> reapply_sid($redirect_url),
			));
		}
		else
		{
			// user is not registered, so he shouldn't even be able to access this
			$this->error[] = array('error' => 'NO_ACCESS', 'action' => 'cancel');
		}
	}
	
	/*
	* Open login box
	*
	* if user is already logged in, still tell him that he successfully logged in
	*/
	private function login()
	{
		global $user, $auth, $config;
		
		$err = '';
		
		$user->add_lang('ucp');

		$get_template = request_var('get_template', 0);

		if($get_template)
		{
			global $template;

			$template->set_filenames(array(
						'body' =>'jquery_base/login.html')
					);

			// get parsed template
			$tpl_content = $template->assign_display('body');

			$this->add_return(array(
				'TPL_BODY'				=> $tpl_content,
			));

			return;
		}
		
		// only try to login if we aren't logged in yet
		if ($user->data['user_id'] == ANONYMOUS)
		{
			//login_box();
			
			$password	= request_var('password', '', true);
			$username	= request_var('username', '', true);
			$autologin	= (!empty($_POST['autologin'])) ? true : false;
			$viewonline = (!empty($_POST['viewonline'])) ? 0 : 1;
			$err = '';
			
			// try logging in
			$result = $auth->login($username, $password, $autologin, $viewonline, false);
			
			// The result parameter is always an array, holding the relevant information...
			if ($result['status'] == LOGIN_SUCCESS)
			{
				$message = $user->lang['LOGIN_REDIRECT'];
				
				// redirect to the same page we are currently on
				$redirect = reapply_sid($this->location);
				
				// Special case... the user is effectively banned, but we allow founders to login
				if (defined('IN_CHECK_BAN') && $result['user_row']['user_type'] != USER_FOUNDER)
				{
					$this->error[] = array('error' => 'NO_AUTH_OPERATION', 'action' => 'cancel');
				}
				$this->add_return(array(
					'SUCCESS'	=> $message,
					'LINK'		=> $redirect,
				));
			}
			else
			{
				// Assign admin contact to some error messages
				if ($result['error_msg'] == 'LOGIN_ERROR_USERNAME' || $result['error_msg'] == 'LOGIN_ERROR_PASSWORD')
				{
					$err = (!$config['board_contact']) ? sprintf($user->lang[$result['error_msg']], '', '') : sprintf($user->lang[$result['error_msg']], '<a href="mailto:' . htmlspecialchars($config['board_contact']) . '">', '</a>');
				}
				else
				{
					$err = $result['error_msg'];
				}

				$this->error[] = array('error' => $err, 'action' => 'cancel');
			}
		}
		else
		{
			$this->add_return(array(
				'SUCCESS'	=> $user->lang['LOGIN_REDIRECT'],
			));
		}
	}
	
	/**
	* check for new PMs
	*
	* return
	*/
	private function check_pm()
	{
		global $user, $db;
		
		$l_privmsgs_text = $l_privmsgs_text_unread = '';
		$s_privmsg_new = false;
		
		// Obtain number of new private messages if user is logged in
		if (!empty($user->data['is_registered']))
		{
			if ($user->data['user_new_privmsg'])
			{
				$l_message_new = ($user->data['user_new_privmsg'] == 1) ? $user->lang['NEW_PM'] : $user->lang['NEW_PMS'];
				$l_privmsgs_text = sprintf($l_message_new, $user->data['user_new_privmsg']);

				/*
				* DO NOT update the session table
				* While it is possible that this causes the user to see this message twice,
				* updating the session table could possibly cause the user to not see the
				* message at all, i.e. when the ajax is currently in progress and the user
				* refreshes the page or switches pages.
				* We will check if we need to update the shown message in the jQuery code
				* by checking if the number of unread PMs has changed. If we don't do this
				* the user will have endless number of pop-up messages or he will get a
				* pop-up message, stating that he has new PMs, everytime this is executed.
				*/
				if (!$user->data['user_last_privmsg'] || $user->data['user_last_privmsg'] > $user->data['session_last_visit'])
				{
					$s_privmsg_new = true;
				}
				else
				{
					$s_privmsg_new = false;
				}
			}
			else
			{
				$l_privmsgs_text = $user->lang['NO_NEW_PM'];
				$s_privmsg_new = false;
			}

			$l_privmsgs_text_unread = '';

			$this->add_return(array(
				'NEW_PM_COUNT'	=> $user->data['user_new_privmsg'],
				'NEW_PM_MSG'	=> $l_privmsgs_text,
				'NEW_PM'		=> $s_privmsg_new,
			));
		}
		// no else, we'll just ignore Anonymous ;)
	}
}
