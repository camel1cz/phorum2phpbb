<?php
/**
*
* @package install
* @copyright (c) 2006 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*	author:  Kamil Srot a.k.a. camel or camel1cz
*	company: nLogy s.r.o.
*	email:   kamil.srot@nlogy.com
*
*/

/**
* NOTE this code is based on phpBB 2.0.x to phpBB 3.0.x convertor from stock
* instalation of phpBB 3.0.5
*
* !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!!
* !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!!
* !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!!
*
* This code is alpha quality! Use at your own risk and DO BACKUP everythin, this
* code may affect / at least the target database. Some tables are truncated before
* import!
*
* It implements only the minimal set of features. PMs, avatars, user permissions
* and a lot of other settings is silently ignored.
*
* Try it out, improve it, share it :-)
*
* It would be nice to know if this code helped ;)
*
* !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!!
* !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!!
* !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!! WARNING !!!
*
*/

/*
* Change Log:
*
* 2009/07/12 First alpha release. Functions renamed to phorum5_ prefix.
*
* 2009/07/10 Start of development.
*
*
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

include($phpbb_root_path . 'config.' . $phpEx);
unset($dbpasswd);

if(isset($src_db))
    $src_db->sql_query("set names 'utf8'");
if(isset($db)) {
    $db->sql_query("set names 'utf8'");
}

/**
* $convertor_data provides some basic information about this convertor which is
* used on the initial list of convertors and to populate the default settings
*/
$convertor_data = array(
	'forum_name'	=> 'Phorum 5.x',
	'version'		=> '0.0.1',
	'phpbb_version'	=> '3.0.5',
	'author'		=> '<a href="http://www.nlogy.com/">nLogy</a>',
	'dbms'			=> $dbms,
	'dbhost'		=> $dbhost,
	'dbport'		=> $dbport,
	'dbuser'		=> $dbuser,
	'dbpasswd'		=> '',
	'dbname'		=> $dbname,
	'table_prefix'	=> 'phorum_',
	'forum_path'	=> '../phorum',
	'author_notes'	=> '',
);

/**
* $tables is a list of the tables (minus prefix) which we expect to find in the
* source forum. It is used to guess the prefix if the specified prefix is incorrect
*/
$tables = array(
	'banlists',
	'files',
	'forum_group_xref',
	'forums',
	'groups',
	'messages',
	'private_messages',
	'settings',
	'subscribers',
	'user_group_xref',
	'user_newflags',
	'user_permissions',
	'users'
);

/**
* $config_schema details how the board configuration information is stored in the source forum.
*
* 'table_format' can take the value 'file' to indicate a config file. In this case array_name
* is set to indicate the name of the array the config values are stored in
* 'table_format' can be an array if the values are stored in a table which is an assosciative array
* (as per phpBB 2.0.x)
* If left empty, values are assumed to be stored in a table where each config setting is
* a column (as per phpBB 1.x)
*
* In either of the latter cases 'table_name' indicates the name of the table in the database
*
* 'settings' is an array which maps the name of the config directive in the source forum
* to the config directive in phpBB3. It can either be a direct mapping or use a function.
* Please note that the contents of the old config value are passed to the function, therefore
* an in-built function requiring the variable passed by reference is not able to be used. Since
* empty() is such a function we created the function is_empty() to be used instead.
*/


$config_schema = array(
	'table_name'	=>	'settings',
	'table_format'	=>	array('name' => 'data'),
	'settings'		=>	array(),
);

/**
* $test_file is the name of a file which is present on the source
* forum which can be used to check that the path specified by the
* user was correct
*/
$test_file = 'post.php';

/**
* If this is set then we are not generating the first page of information but getting the conversion information.
*/
if (!$get_info)
{
	// Test to see if the attachment MOD is installed on the source forum
	// If it is, we will convert this data as well
	$src_db->sql_return_on_error(true);

	$sql = "SELECT config_value
		FROM {$convert->src_table_prefix}attachments_config
		WHERE config_name = 'upload_dir'";
	$result = $src_db->sql_query($sql);

	if ($result && $row = $src_db->sql_fetchrow($result))
	{
		// Here the constant is defined
		define('MOD_ATTACHMENT', true);

		// Here i add more tables to be checked in the old forum
		$tables += array(
			'attachments',
			'attachments_desc',
			'extensions',
			'extension_groups'
		);

		$src_db->sql_freeresult($result);
	}
	else if ($result)
	{
		$src_db->sql_freeresult($result);
	}


	/**
	* Tests for further MODs can be included here.
	* Please use constants for this, prefixing them with MOD_
	*/

	$src_db->sql_return_on_error(false);

	// Now let us set a temporary config variable for user id incrementing
	$sql = "SELECT user_id
		FROM {$convert->src_table_prefix}users
		WHERE user_id = 1";
	$result = $src_db->sql_query($sql);
	$user_id = (int) $src_db->sql_fetchfield('user_id');
	$src_db->sql_freeresult($result);

	// Overwrite maximum avatar width/height
	@define('DEFAULT_AVATAR_X_CUSTOM', get_config_value('avatar_max_width'));
	@define('DEFAULT_AVATAR_Y_CUSTOM', get_config_value('avatar_max_height'));

	// additional table used only during conversion
	@define('USERCONV_TABLE', $table_prefix . 'userconv');

/**
*	Description on how to use the convertor framework.
*
*	'schema' Syntax Description
*		-> 'target'			=> Target Table. If not specified the next table will be handled
*		-> 'primary'		=> Primary Key. If this is specified then this table is processed in batches
*		-> 'query_first'	=> array('target' or 'src', Query to execute before beginning the process
*								(if more than one then specified as array))
*		-> 'function_first'	=> Function to execute before beginning the process (if more than one then specified as array)
*								(This is mostly useful if variables need to be given to the converting process)
*		-> 'test_file'		=> This is not used at the moment but should be filled with a file from the old installation
*
*		// DB Functions
*		'distinct'	=> Add DISTINCT to the select query
*		'where'		=> Add WHERE to the select query
*		'group_by'	=> Add GROUP BY to the select query
*		'left_join'	=> Add LEFT JOIN to the select query (if more than one joins specified as array)
*		'having'	=> Add HAVING to the select query
*
*		// DB INSERT array
*		This one consist of three parameters
*		First Parameter:
*							The key need to be filled within the target table
*							If this is empty, the target table gets not assigned the source value
*		Second Parameter:
*							Source value. If the first parameter is specified, it will be assigned this value.
*							If the first parameter is empty, this only gets added to the select query
*		Third Parameter:
*							Custom Function. Function to execute while storing source value into target table.
*							The functions return value get stored.
*							The function parameter consist of the value of the second parameter.
*
*							types:
*								- empty string == execute nothing
*								- string == function to execute
*								- array == complex execution instructions
*
*		Complex execution instructions:
*		@todo test complex execution instructions - in theory they will work fine
*
*							By defining an array as the third parameter you are able to define some statements to be executed. The key
*							is defining what to execute, numbers can be appended...
*
*							'function' => execute function
*							'execute' => run code, whereby all occurrences of {VALUE} get replaced by the last returned value.
*										The result *must* be assigned/stored to {RESULT}.
*							'typecast'	=> typecast value
*
*							The returned variables will be made always available to the next function to continue to work with.
*
*							example (variable inputted is an integer of 1):
*
*							array(
*								'function1'		=> 'increment_by_one',		// returned variable is 2
*								'typecast'		=> 'string',				// typecast variable to be a string
*								'execute'		=> '{RESULT} = {VALUE} . ' is good';', // returned variable is '2 is good'
*								'function2'		=> 'replace_good_with_bad',				// returned variable is '2 is bad'
*							),
*
*/

	$convertor = array(
		'test_file'				=> 'post.php',

		'avatar_path'			=> get_config_value('avatar_path') . '/',
		'avatar_gallery_path'	=> get_config_value('avatar_gallery_path') . '/',
		'smilies_path'			=> get_config_value('smilies_path') . '/',
		'upload_path'			=> phorum5_get_files_dir() . '/',
		'thumbnails'			=> array('thumbs/', 't_'),
		'ranks_path'			=> false, // phpBB 2.0.x had no config value for a ranks path

		// We empty some tables to have clean data available
		'query_first'			=> array(
			array('target', $convert->truncate_statement . POSTS_TABLE),
			array('target', $convert->truncate_statement . SEARCH_RESULTS_TABLE),
			array('target', $convert->truncate_statement . SEARCH_WORDLIST_TABLE),
			array('target', $convert->truncate_statement . SEARCH_WORDMATCH_TABLE),
			array('target', $convert->truncate_statement . LOG_TABLE),
		),

//	with this you are able to import all attachment files on the fly. For large boards
//	this is not an option, therefore commented out by default.
//	Instead every file gets copied while processing the corresponding attachment entry.
//		if (defined("MOD_ATTACHMENT")) { import_attachment_files(); phpbb_copy_thumbnails(); }

		// phpBB2 allowed some similar usernames to coexist which would have the same
		// username_clean in phpBB3 which is not possible, so we'll give the admin a list
		// of user ids and usernames and let him deicde what he wants to do with them
		'execute_first'	=> '
			phorum5_insert_forums();
		',

		'execute_last'	=> array(),

		'schema' => array(

			array(
				'target'		=> TOPICS_TABLE,
				'primary'		=> 'messages.message_id',
				'autoincrement'	=> 'topic_id',

				array('topic_id',				'messages.message_id',					''),
				array('forum_id',				'messages.forum_id',					''),
				array('icon_id',				0,									''),
				array('topic_poster',			'messages.user_id AS poster_id',	'phorum5_user_id'),
				array('topic_attachment',		'',''), // TODO: import attachements ((defined('MOD_ATTACHMENT')) ? 'topics.topic_attachment' : 0), ''
				array('topic_title',			'messages.subject',				'phorum5_set_encoding'),
				array('topic_time',				'messages.datestamp',				''),
				array('topic_views',			'messages.viewcount',				''),
				array('topic_replies',			'messages.thread_count',				''),
				array('topic_replies_real',		'messages.thread_count',				''),
				array('topic_last_post_id',		'0',		''),
				array('topic_status',			ITEM_UNLOCKED,							''),
				array('topic_moved_id',			'0',			''),
				array('topic_type',				POST_NORMAL,				''),
				array('topic_first_post_id',	'messages.message_id',		''),
				// TODO: import pools
				array('poll_title',				'',				array('function1' => 'null_to_str', 'function2' => 'phorum5_set_encoding', 'function3' => 'utf8_htmlspecialchars')),
				array('poll_start',				'0',				'null_to_zero'),
				array('poll_length',			'0',			'null_to_zero'),
				array('poll_max_options',		1,									''),
				array('poll_vote_change',		0,									''),

//				'left_join'		=> 'topics',
				'where'			=> 'message_id = thread and message_id <> 0',
			),

			array(
				'target'		=> POSTS_TABLE,
				'primary'		=> 'messages.message_id',
				'autoincrement'	=> 'post_id',
				'execute_last'	=> '
					phorum5_bbcodes_add();
				',
				array('post_id',				'messages.message_id',					''),
				array('topic_id',				'messages.thread',					''),
				array('forum_id',				'messages.forum_id',					''),
				array('poster_id',				'messages.user_id',					'phorum5_user_id'),
				array('icon_id',				0,									''),
				array('poster_ip',				'messages.ip',					''),
				array('post_time',				'messages.datestamp',					''),
				array('enable_bbcode',			1,				''),
				array('',						1,				''),
				array('enable_smilies',			1,				''),
				array('enable_sig',				1,					''),
				array('enable_magic_url',		1,									''),
				array('post_username',			'messages.author',				'phorum5_set_encoding'),
				array('post_subject',			'messages.subject',			'phorum5_set_encoding'),
				array('post_attachment',		0, ''),
				array('post_edit_time',			'messages.modifystamp',				''),
				array('post_edit_count',		0,			''),
				array('post_edit_reason',		'',									''),
				array('post_edit_user',			'',									''),

				array('bbcode_uid',				'messages.datestamp',					'make_uid'),
				array('post_text',				'messages.body',				'phorum5_prepare_message'),
				array('bbcode_bitfield',		'',									''),
				array('post_checksum',			'',									''),

				// Commented out inline search indexing, this takes up a LOT of time. :D
				// @todo We either need to enable this or call the rebuild search functionality post convert

//				'where'			=>	''
			),

			array(
				'target'		=> ATTACHMENTS_TABLE,
				'primary'		=> 'files.file_id',
				'autoincrement'	=> 'attach_id',
				'execute_last'	=> '
					phorum5_update_attachment_flag();
				',

				array('attach_id',			'files.file_id',				''),
				array('post_msg_id',			'files.message_id',					''),
				array('topic_id',			'files.message_id',						''),
				array('in_message',			0,										''),
				array('is_orphan',			0,										''),
				array('poster_id',			'files.user_id',	'phorum5_user_id'),
				array('physical_filename',		'files.filename',	'import_attachment'),
				array('real_filename',			'files.file_data',		''),
				array('download_count',			0,		''),
				array('attach_comment',			'',				''),
				array('extension',			'',			''),
				array('mimetype',			'',			''),
				array('filesize',			'files.filesize',			''),
				array('filetime',			'files.add_datetime',			''),
				array('thumbnail',			'',			''),

#				'where'			=> 'posts.post_id = files.message_id',
#				'group_by'		=> 'files.message_id'
			),

			array(
				'target'		=> USERS_TABLE,
				'primary'		=> 'users.user_id',
				'autoincrement'	=> 'user_id',
/*
				'query_first'	=> array(
					array('target', 'DELETE FROM ' . USERS_TABLE . ' WHERE user_id <> ' . ANONYMOUS),
					array('target', $convert->truncate_statement . BOTS_TABLE)
				),
*/
				'execute_last'	=> '
					remove_invalid_users();
					user_group_auth(\'registered\', \'SELECT user_id, {REGISTERED} FROM ' . USERS_TABLE . ' WHERE user_id > ' . $config['increment_user_id'] . '\', true);
				',

				array('user_id',				'users.user_id',					'phorum5_user_id'),
				array('user_type',				2,				''), // normal users TODO: active?
				array('group_id',				2,					'str_to_primary_group'), // TODO: relays on default return value of registered users group id: all are members of "Registered users"
				array('user_regdate',			'users.date_added',				''),
				array('username',				'users.username',					'phorum5_set_default_encoding'), // recode to utf8 with default lang
				array('username_clean',			'users.username',					array('function1' => 'phorum5_set_default_encoding', 'function2' => 'utf8_clean_string')),
				array('user_password',			'users.password',				'phpbb_hash'),
				array('user_pass_convert',		1,									''),
				array('user_posts',				'users.posts',					'intval'),
				array('user_email',				'users.email',					'strtolower'),
				array('user_email_hash',		'users.email',					'gen_email_hash'),
				array('user_birthday',			'',	''),
				array('user_lastvisit',			'users.date_last_active',				'intval'),
				array('user_lastmark',			'users.date_last_active',				'intval'),
				array('user_lang',				'',			''),
				array('user_timezone',			'',				''),
				array('user_dateformat',		'',			''),
				array('user_inactive_reason',	0,									''),
				array('user_inactive_time',		0,									''),

				array('user_interests',			'',				''),
				array('user_occ',				'',					''),
				array('user_website',			'',				''),
				array('user_jabber',			'',									''),
				array('user_msnm',				'',					''),
				array('user_yim',				'',					''),
				array('user_aim',				'',					''),
				array('user_icq',				'',					''),
				array('user_from',				'',					''),
				array('user_rank',				0,					'intval'),
				array('user_permissions',		'',									''),

				array('user_avatar',			'',				''),
				array('user_avatar_type',		'',			''),
				array('user_avatar_width',		'',				''),
				array('user_avatar_height',		'',				''),

				array('user_new_privmsg',		0,			''),
				array('user_unread_privmsg',	0,									''), //'users.user_unread_privmsg'
				array('user_last_privmsg',		0,			'intval'),
				array('user_emailtime',			0,				'null_to_zero'),
				array('user_notify',			0,				'intval'),
				array('user_notify_pm',			0,				'intval'),
				array('user_notify_type',		NOTIFY_EMAIL,						''),
				array('user_allow_pm',			1,				'intval'),
				array('user_allow_viewonline',		1,		'intval'),
				array('user_allow_viewemail',		1,				'intval'),
				array('user_actkey',			'',				''),
				array('user_newpasswd',			'',									''), // Users need to re-request their password...
				array('user_style',			1,			''),

				array('user_options',			'0',									''),

				array('user_sig_bbcode_uid',		'',							''),
				array('user_sig',			'users.signature',								''),
				array('user_sig_bbcode_bitfield',	'',									''),

				'where'			=> 'users.user_id <> -1',
			),
		),
	);
}

?>