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
define('IN_PHPBB', true);
define('IN_JQUERY_BASE', true);
$phpbb_root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.'.$phpEx);
include($phpbb_root_path . 'jquery_base/jquery_functions.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('posting');
$user->add_lang('mods/jquery_base');

// create new instance of jQuery Base class
$jquery_base = new phpbb_jquery_base();

// run PHP code for jQuery
$jquery_base->run_actions();

// finish it!!!
$jquery_base->page_footer();
