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

class acp_jquery_base
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $db, $user, $template;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		$action = request_var('action', '');
		$submit = (isset($_POST['submit'])) ? true : false;
		
		$this->new_config = $config;

		/**
		*	Validation types are:
		*		string, int, bool,
		*		script_path (absolute path in url - beginning with / and no trailing slash),
		*		rpath (relative), rwpath (realtive, writeable), path (relative path, but able to escape the root), wpath (writeable)
		*/
		switch ($mode)
		{
			case 'general':
				$display_vars = array(
					'title'	=> 'ACP_JQUERY_BASE_INFO',
					'vars'	=> array(
						'legend1'							=> 'ACP_JQUERY_GENERAL_INFO',
						'pjb_enable'						=> array('lang' => 'ACP_JQUERY_BASE_ENABLE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false),
						'pjb_quickreply_enable'				=> array('lang' => 'ACP_JQUERY_QUICKREPLY_ENABLE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false),
						'pjb_quickedit_enable'				=> array('lang' => 'ACP_JQUERY_QUICKEDIT_ENABLE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false),
						'pjb_markread_enable'				=> array('lang' => 'ACP_JQUERY_MARKREAD_ENABLE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false),
						'pjb_login_enable'					=> array('lang' => 'ACP_JQUERY_LOGIN_ENABLE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false),
						'pjb_popups_enable'					=> array('lang' => 'ACP_JQUERY_POPUP_ENABLE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true),
						'pjb_pm_update_interval'			=> array('lang' => 'ACP_JQUERY_UPDATE_INTERVAL', 'validate' => 'int', 'type' => 'text:3:3', 'explain' => true),
					)
				);
			break;
			
			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}

		if (isset($display_vars['lang']))
		{
			$user->add_lang($display_vars['lang']);
		}

		$cfg_array = (isset($_REQUEST['config'])) ? utf8_normalize_nfc(request_var('config', array('' => ''), true)) : $this->new_config;
		$error = array();
		
		// We validate the complete config if whished
		if(isset($display_vars) && sizeof($display_vars) > 0)
		{
			validate_config_vars($display_vars['vars'], $cfg_array, $error);
			
			// Do not write values if there is an error
			if (sizeof($error))
			{
				$submit = false;
			}
			
			// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
			foreach ($display_vars['vars'] as $config_name => $null)
			{
				if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend'))
				{
					continue;
				}

				$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];

				if ($submit)
				{
					set_config($config_name, $config_value);
				}
			}
		}



		if ($submit)
		{
			add_log('admin', 'LOG_JQUERY_BASE_CONFIG', $user->lang['ACP_JQUERY_' . strtoupper($mode) . '_INFO']);
			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
		}

		$this->tpl_name = 'acp_board';
		$this->page_title = $display_vars['title'];

		$title_explain = $user->lang[$display_vars['title'] . '_EXP'];
		
		$template->assign_vars(array(
			'L_TITLE'			=> $user->lang[$display_vars['title']],
			'L_TITLE_EXPLAIN'	=> $title_explain,

			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),

			'U_ACTION'			=> $this->u_action)
		);

		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$template->assign_block_vars('options', array(
					'S_LEGEND'		=> true,
					'LEGEND'		=> (isset($user->lang[$vars])) ? $user->lang[$vars] : $vars)
				);

				continue;
			}

			$type = explode(':', $vars['type']);

			$l_explain = '';
			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($user->lang[$vars['lang_explain']])) ? $user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			}
			else if ($vars['explain'])
			{
				$l_explain = (isset($user->lang[$vars['lang'] . '_EXP'])) ? $user->lang[$vars['lang'] . '_EXP'] : '';
			}

			$template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> (isset($user->lang[$vars['lang']])) ? $user->lang[$vars['lang']] : $vars['lang'],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $l_explain,
				'CONTENT'		=> build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars),
				)
			);
			unset($display_vars['vars'][$config_key]);
		}
	}
	
	
}
