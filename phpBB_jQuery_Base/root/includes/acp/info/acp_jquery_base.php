<?php
/**
*
* @package phpBB Statistics
* @version $Id: acp_stats.php 171 2011-02-09 01:58:16Z marc1706 $
* @copyright (c) 2009 - 2010 Marc Alexander(marc1706) www.m-a-styles.de
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
* @based on: acp_stats.php included in the Board3 Portal package (www.board3.de)
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
			'title'		=> 'ACP_STATS_INFO',
			'version'	=> '1.0.3',
			'modes'		=> array(
				'settings'			=> array('title' => 'ACP_STATS_GENERAL_INFO', 'auth' => 'acl_a_board', 'cat' => array('ACP_BOARD_CONFIGURATION')),
				'addons'			=> array('title' => 'ACP_STATS_ADDONS', 'auth' => 'acl_a_board', 'cat' => array('ACP_BOARD_CONFIGURATION')),
			),
		);
	}
}

?>