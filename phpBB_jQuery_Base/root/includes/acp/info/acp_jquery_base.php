<?php
/**
*
* @package phpBB jQuery Base
* @copyright (c) 2011 Marc Alexander(marc1706) www.m-a-styles.de
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB') || !defined('ADMIN_START'))
{
	exit;
}

/**
* @package module_install
*/
class acp_jquery_base_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_jquery_base',
			'title'		=> 'ACP_JQUERY_BASE_INFO',
			'version'	=> '0.0.2',
			'modes'		=> array(
				'general'			=> array('title' => 'ACP_JQUERY_GENERAL_INFO', 'auth' => 'acl_a_board', 'cat' => array('ACP_BOARD_CONFIGURATION')),
			),
		);
	}
}
