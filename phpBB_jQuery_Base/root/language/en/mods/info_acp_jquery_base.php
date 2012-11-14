<?php
/**
*
* @package phpBB jQuery Base
* @copyright (c) 2011 Marc Alexander(marc1706) www.m-a-styles.de
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	// ACP Settings
	'ACP_JQUERY_BASE_INFO'				=> 'phpBB jQuery Base',
	'ACP_JQUERY_BASE_INFO_EXP'			=> 'You can manage your phpBB jQuery Base here.',
	'ACP_JQUERY_GENERAL_INFO'			=> 'General settings',
	'ACP_JQUERY_BASE_ENABLE'			=> 'Enable phpBB jQuery Base',
	'ACP_JQUERY_QUICKREPLY_ENABLE'		=> 'Enable Quickreply',
	'ACP_JQUERY_QUICKEDIT_ENABLE'		=> 'Enable Quickedit',
	'ACP_JQUERY_MARKREAD_ENABLE'		=> 'Enable marking topics & forums read',
	'ACP_JQUERY_LOGIN_ENABLE'			=> 'Enable login logout using jQuery',
	'ACP_JQUERY_POPUP_ENABLE'			=> 'Enable popup “success“ messages',
	'ACP_JQUERY_POPUP_ENABLE_EXP'		=> 'This will show popup messages after you have run any action, i.e. after a quickreply you will see a popup message stating that your quickreply was successful.',
	'ACP_JQUERY_UPDATE_INTERVAL'		=> 'Update interval for checking new PMs',
	'ACP_JQUERY_UPDATE_INTERVAL_EXP'	=> 'Define the time in minutes after which the PM check should check for new PMs. Setting this to 0 will disable this feature.',
	
	// Logs
	'LOG_JQUERY_BASE_CONFIG'			=> '<strong>Altered phpBB jQuery Base settings</strong><br />&raquo; %s',
	
	/**
	* A copy of Handyman` s MOD version check, to view it on the portal overview
	*/
	'ANNOUNCEMENT_TOPIC'	=> 'Release Announcement',
	'CURRENT_VERSION'		=> 'Current Version',
	'DOWNLOAD_LATEST'		=> 'Download Latest Version',
	'LATEST_VERSION'		=> 'Latest Version',
	'NO_INFO'				=> 'Version server could not be contacted',
	'NOT_UP_TO_DATE'		=> '%s is not up to date',
	'RELEASE_ANNOUNCEMENT'	=> 'Annoucement Topic',
	'UP_TO_DATE'			=> '%s is up to date',
	'VERSION_CHECK'			=> 'MOD Version Check',
));
