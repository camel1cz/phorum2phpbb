<?php
/**
*
* @package install
* @copyright (c) 2006 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

define("PHORUM5_USERS_OFFSET", 100);

if(isset($src_db))
    $src_db->sql_query("set names 'utf8'");
if(isset($db))
    $db->sql_query("set names 'utf8'");

/**
* Helper functions for Phorum 5.0.x to phpBB 3.0.x conversion
*/

/**
* Insert/Convert forums
*/
function phorum5_insert_forums()
{
	global $db, $src_db, $same_db, $convert, $user, $config;

	$src_db->sql_query("set names 'utf8'");
	$db->sql_query("set names 'utf8'");
	$db->sql_query($convert->truncate_statement . FORUMS_TABLE);

	// TODO: find out, if pruning is enabled
	$prune_enabled = 0;
	
	// Insert the forums
	$sql = 'SELECT forum_id, name, parent_id, description FROM ' . $convert->src_table_prefix . 'forums
		ORDER BY parent_id, forum_id';

	$result = $src_db->sql_query($sql);

	// starting value for left ID
	while ($row = $src_db->sql_fetchrow($result))
	{
		// Define the new forums sql ary
		$sql_ary = array(
			'forum_id'			=> (int) $row['forum_id'],
			'forum_name'		=> htmlspecialchars(phorum5_set_default_encoding($row['name']), ENT_COMPAT, 'UTF-8'),
			'parent_id'			=> (int) $row['parent_id'],
			'forum_parents'		=> '',
			'forum_desc'		=> htmlspecialchars(phorum5_set_default_encoding($row['description']), ENT_COMPAT, 'UTF-8'),
			'forum_type'		=> FORUM_POST,
			'forum_status'		=> ITEM_UNLOCKED, // TODO: is forum locked?
			'enable_prune'		=> ($prune_enabled) ? 1 : 0, // TODO: never goes to true statement
			'prune_next'		=> 0, // TODO: otiginal: (int) null_to_zero($row['prune_next']),
			'prune_days'		=> 0, // TODO: otiginal: (int) null_to_zero($row['prune_days']),
			'prune_viewed'		=> 0,
			'prune_freq'		=> 0, // TODO: otiginal: (int) null_to_zero($row['prune_freq']),
			'forum_flags'		=> 32, // TODO: original: phpbb_forum_flags(),

			// Default values
			'forum_desc_bitfield'		=> '',
			'forum_desc_options'		=> 7,
			'forum_desc_uid'			=> '',
			'forum_link'				=> '',
			'forum_password'			=> '',
			'forum_style'				=> 0,
			'forum_image'				=> '',
			'forum_rules'				=> '',
			'forum_rules_link'			=> '',
			'forum_rules_bitfield'		=> '',
			'forum_rules_options'		=> 7,
			'forum_rules_uid'			=> '',
			'forum_topics_per_page'		=> 0,
			'forum_posts'				=> 0,
			'forum_topics'				=> 0,
			'forum_topics_real'			=> 0,
			'forum_last_post_id'		=> 0,
			'forum_last_poster_id'		=> 0,
			'forum_last_post_subject'	=> '',
			'forum_last_post_time'		=> 0,
			'forum_last_poster_name'	=> '',
			'forum_last_poster_colour'	=> '',
			'display_on_index'			=> 1,
			'enable_indexing'			=> 1,
			'enable_icons'				=> 0,
		);
		
		// if it's root forum
		if($sql_ary['parent_id'] == 0) {
			$sql = 'SELECT max(right_id) as max_right_id
				FROM ' . FORUMS_TABLE;
			$result2 = $db->sql_query($sql);
			$sql_ary['left_id'] = (int) $db->sql_fetchfield('max_right_id') + 1;
			$sql_ary['right_id'] = $sql_ary['left_id'] + 1;
			$db->sql_freeresult($result2);
			//print_r($sql_ary);// $sql_ary['parent_id'];
		} else {
			// no
			// we need to get new left_id and right_id...
			
			$sql = 'SELECT left_id, right_id, forum_type
				FROM ' . FORUMS_TABLE . '
				WHERE forum_id = ' . $sql_ary['parent_id'];
			$result2 = $db->sql_query($sql);
			$row2 = $db->sql_fetchrow($result2);
			$db->sql_freeresult($result2);
			//print_r($row);// $sql_ary['parent_id'];
			$sql = 'UPDATE ' . FORUMS_TABLE . '
				SET left_id = left_id + 2, right_id = right_id + 2
				WHERE left_id > ' . $row2['right_id'];
			$db->sql_query($sql);

			$sql = 'UPDATE ' . FORUMS_TABLE . '
				SET right_id = right_id + 2
				WHERE ' . $row2['left_id'] . ' BETWEEN left_id AND right_id';
			$db->sql_query($sql);

			$sql_ary['left_id'] = $row2['right_id'];
			$sql_ary['right_id'] = $sql_ary['left_id'] + 1;
		}
		
		//$sql_ary['left_id'] = 1;
		//$sql_ary['right_id'] = 2;
		
		// Now add the forum
		$sql = 'INSERT INTO ' . FORUMS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
		$db->sql_query($sql);
		
		// and grant default access rights
		mass_auth('group_role', (int) $row['forum_id'], 'ADMINISTRATORS', 'FORUM_FULL');
		mass_auth('group_role', (int) $row['forum_id'], 'REGISTERED', 'FORUM_STANDARD');
		mass_auth('group_role', (int) $row['forum_id'], 'GUESTS', 'FORUM_READONLY');
		mass_auth('group_role', (int) $row['forum_id'], 'BOTS', 'FORUM_BOT');
	}
	$src_db->sql_freeresult($result);

	switch ($db->sql_layer)
	{
		case 'postgres':
			$db->sql_query("SELECT SETVAL('" . FORUMS_TABLE . "_seq',(select case when max(forum_id)>0 then max(forum_id)+1 else 1 end from " . FORUMS_TABLE . '));');
		break;

		case 'mssql':
		case 'mssql_odbc':
			$db->sql_query('SET IDENTITY_INSERT ' . FORUMS_TABLE . ' OFF');
		break;

		case 'oracle':
			$result = $db->sql_query('SELECT MAX(forum_id) as max_id FROM ' . FORUMS_TABLE);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$largest_id = (int) $row['max_id'];

			if ($largest_id)
			{
				$db->sql_query('DROP SEQUENCE ' . FORUMS_TABLE . '_seq');
				$db->sql_query('CREATE SEQUENCE ' . FORUMS_TABLE . '_seq START WITH ' . ($largest_id + 1));
			}
		break;
	}
}

/**
* Function for recoding text with the default language
*
* @param string $text text to recode to utf8
* @param bool $grab_user_lang if set to true the function tries to use $convert_row['user_lang'] (and falls back to $convert_row['poster_id']) instead of the boards default language
*/
function phorum5_set_encoding($text, $grab_user_lang = true)
{
	global $lang_enc_array, $convert_row;
	global $convert, $phpEx;

	// TODO: do nothing for now
	return $text;
}

/**
* Same as phorum5_set_encoding, but forcing boards default language
*/
function phorum5_set_default_encoding($text)
{
	return phorum5_set_encoding($text, false);
}

/**
* Return correct user id value
* Everyone's id will be by (max_id_of default_phpbb_install + PHORUM5_USERS_OFFSET) higher to allow
* system accounts guest/anonymous/bot to stay
*/
function phorum5_user_id($user_id)
{
	global $config;

	// anonymous poster
	if ($user_id == 0) {
	    return 1;
	}

	// Increment user id if the old forum is having a user with the id 1
	if (!isset($config['increment_user_id']))
	{
		global $db, $convert;

		// Try to get the first free user id...
		$sql = "SELECT MAX(user_id) AS max_user_id
			FROM " . USERS_TABLE;
		$result = $db->sql_query($sql);
		$max_id = (int) $db->sql_fetchfield('max_user_id');
		$db->sql_freeresult($result);

		set_config('increment_user_id', ($max_id + PHORUM5_USERS_OFFSET), true);
		$config['increment_user_id'] = $max_id + PHORUM5_USERS_OFFSET;
	}

	return (int) $user_id + $config['increment_user_id'];
}

/**
* Obtain the path to uploaded files
*/
function phorum5_get_files_dir()
{
	global $src_db, $same_db, $convert, $user, $config, $cache;
	return 'attach';
}

/**
* Reparse the message fixing incompatible bbcodes
*/
function phorum5_prepare_message($message)
{
	// [code] tag in Phorum is converted to <pre>
	$message = str_replace('[code]', '[pre]', $message);
	$message = str_replace('[/code]', '[/pre]', $message);

	return $message;
}

/**
* Add special BB Codes used in Phorum and N/A in phpBB
*/
function phorum5_bbcodes_add()
{
	global $db;

	/* Row we need to insert:
	*
	*| bbcode_id | bbcode_tag | bbcode_helpline                   | display_on_posting | bbcode_match      | bbcode_tpl        | first_pass_match          | first_pass_replace                                                                                                                     | second_pass_match                 | second_pass_replace |
	*+-----------+------------+-----------------------------------+--------------------+-------------------+-------------------+---------------------------+----------------------------------------------------------------------------------------------------------------------------------------+-----------------------------------+---------------------+
	*|        13 | pre        | Preformated text: [pre]text[/pre] |                  1 | [pre]{TEXT}[/pre] | <pre>{TEXT}</pre> | !\[pre\](.*?)\[/pre\]!ies | '[pre:$uid]'.str_replace(array("\r\n", '\"', '\'', '(', ')'), array("\n", '"', '&#39;', '&#40;', '&#41;'), trim('${1}')).'[/pre:$uid]' | !\[pre:$uid\](.*?)\[/pre:$uid\]!s | <pre>${1}</pre>
	*/
	$sql = 'SELECT max(bbcode_id) + 1 as next_bbcode_id
		FROM' . BBCODES_TABLE;
	$result = $db->sql_query($sql);
	$next_bbcode_id = (int) $db->sql_fetchfield('next_bbcode_id');
	$db->sql_freeresult($result);

	$first_pass_replace = '\'[pre:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/pre:$uid]\'';
	$second_pass_match = '!\\[pre:$uid\\](.*?)\\[/pre:$uid\\]!s';

	$sql = 'INSERT INTO BBCODES_TABLE(bbcode_id, bbcode_tag, bbcode_helpline, display_on_posting, bbcode_match, bbcode_tpl, first_pass_match, first_pass_replace, second_pass_match, second_pass_replace)
		VALUES(' . $next_bbcode_id . ',\'pre\',\'Preformated text: [pre]text[/pre]\', 1, \'[pre]{TEXT}[/pre]\', \'<pre>{TEXT}</pre>\', \'!\[pre\](.*?)\[/pre\]!ies\', \'' . $first_pass_replace . '\',\'' . $second_pass_match . '\',\'<pre>${1}</pre>\')';
	$result = $db->sql_query($sql);
	$db->sql_freeresult($result);
}

function phorum5_update_attachment_flag() {
	global $db;
	
	$sql = 'UPDATE ' . POSTS_TABLE . '
		SET post_attachment = 1
		WHERE post_id in (
					SELECT post_msg_id
					FROM ' . ATTACHMENTS_TABLE . '
					WHERE in_message = 0
				)';
	$db->sql_query($sql);
}

?>