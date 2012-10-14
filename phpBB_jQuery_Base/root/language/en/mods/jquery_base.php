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

// Common
$lang = array_merge($lang, array(
	'QUICKEDIT_POST'					=> 'Quickedit post',
	'ADVANCED_EDIT'						=> 'Advanced Edit',
	'PJB_MARKREAD_FORUMS_SUCCESS_MSG'	=> 'You have successfully marked your forums read.',
	'PJB_MARKREAD_FORUMS_SUCCESS'		=> '“Mark forums read“ successful',
	'PJB_MARKREAD_TOPICS_SUCCESS_MSG'	=> 'You have successfully marked your topics in forum “%1$s“ read.',
	'PJB_MARKREAD_TOPICS_SUCCESS'		=> '“Mark topics read“ successful',
	'PJB_QUICKREPLY_SUCCESS_MSG'		=> 'Your Quickreply to this topic was successful.',
	'PJB_QUICKREPLY_SUCCESS'			=> 'Quickreply successful',
	'PJB_QUICKEDIT_SUCCESS_MSG'			=> 'Your Quickedit to this post was successful.',
	'PJB_QUICKEDIT_SUCCESS'				=> 'Quickedit successful',
));
