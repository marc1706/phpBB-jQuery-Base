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
define('UMIL_AUTO', true);
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

include($phpbb_root_path . 'common.' . $phpEx);
$user->session_begin();
$auth->acl($user->data);
$user->setup();


if (!file_exists($phpbb_root_path . 'umil/umil_auto.' . $phpEx))
{
	trigger_error('Please download the latest UMIL (Unified MOD Install Library) from: <a href="http://www.phpbb.com/mods/umil/">phpBB.com/mods/umil</a>', E_USER_ERROR);
}

// The name of the mod to be displayed during installation.
$mod_name = 'phpBB jQuery Base';

/*
* The name of the config variable which will hold the currently installed version
* UMIL will handle checking, setting, and updating the version itself.
*/
$version_config_name = 'pjb_version';


// The language file which will be included when installing
$language_file = 'mods/info_acp_jquery_base';


/*
* Optionally we may specify our own logo image to show in the upper corner instead of the default logo.
* $phpbb_root_path will get prepended to the path specified
* Image height should be 50px to prevent cut-off or stretching.
*/
//$logo_img = 'styles/prosilver/imageset/site_logo.gif';

/*
* The array of versions and actions within each.
* You do not need to order it a specific way (it will be sorted automatically), however, you must enter every version, even if no actions are done for it.
*
* You must use correct version numbering.  Unless you know exactly what you can use, only use X.X.X (replacing X with an integer).
* The version numbering must otherwise be compatible with the version_compare function - http://php.net/manual/en/function.version-compare.php
*/
$versions = array(
	'0.1.0' => array(
		'permission_add' => array(
			array('u_quickedit', 1),
			array('u_quickreply', 1),
		),
		
		'permission_set' => array(
			array('REGISTERED', 'u_quickedit', 'group'),
			array('GLOBAL_MODERATORS', 'u_quickedit', 'group'),
			array('ADMINISTRATORS', 'u_quickedit', 'group'),
			array('REGISTERED', 'u_quickreply', 'group'),
			array('GLOBAL_MODERATORS', 'u_quickreply', 'group'),
			array('ADMINISTRATORS', 'u_quickreply', 'group'),
		),

		'config_add' => array(
			array('pjb_enable', '1', 0),
			array('pjb_quickreply_enable', '1', 0),
			array('pjb_quickedit_enable', '1', 0),
			array('pjb_markread_enable', '1', 0),
			array('pjb_login_enable', '1', 0),
			array('pjb_popups_enable', '0', 0),
		),
		
		'module_add' => array(
			array('acp', 'ACP_CAT_DOT_MODS', 'ACP_JQUERY_BASE_INFO'),

			array('acp', 'ACP_JQUERY_BASE_INFO', array(
				
					'module_basename'	=> 'jquery_base',
					'module_langname'	=> 'ACP_JQUERY_BASE_INFO',
					'module_mode' 		=> 'general',
					'module_auth'		=> 'acl_a_board',
				),
			),
		),
	),
	
	'1.0.0' => array(
		'config_add' => array(
			array('pjb_pm_update_interval', 2, 0), // default to 2 minutes
		),
	
		'cache_purge' => array(
			'imageset',
			'template',
			'theme',
			'',
		),
	),
);

// Include the UMIL Auto file, it handles the rest
include($phpbb_root_path . 'umil/umil_auto.' . $phpEx);