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
	var $submit = false;
	
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
		
		// include needed files
		switch($this->mode)
		{
			case 'quickreply':
			case 'quickedit':
				include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
				include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
			break;
			case 'markread_forum':
				// check what files we need
			break;
			default:
				$error[] = 'NO_MODE';
		}
	}
	
	/*
	* Run actions for the specified mode
	*
	* @param: none
	*/
	function run_actions()
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
		}
	}
	
	/*
	*
	*/
	function quickreply()
	{
	
	}

	/*
	*
	*/
	function quickedit()
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