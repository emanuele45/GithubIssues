<?php

/**
 * Github Issues
 *
 * @author  emanuele
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.0.1
 */

class GithubIssues_Integrate
{
	protected static $boardsList = null;
	protected static $id_issue = null;
	protected static $id_first_msg = null;
	protected static $push_issues = false;

	public static function integrate_general_mod_settings(&$config_vars)
	{
		global $txt, $modSettings;

		loadLanguage('GithubIssues');
		self::prepareModSettingsSelectBoardsList();
		$select = self::formatSelectBoardsList(!empty($modSettings['recycle_enable']) ? array($modSettings['recycle_board']) : null);

		$config_vars[] = '';
		$config_vars[] = array('text', 'ghissues_access_token');
		$config_vars[] = array('select', 'ghissues_select_boards', $select, 'multiple' => true);
		$config_vars[] = array('text', 'ghissues_repository');
		$config_vars[] = array('large_text', 'ghissues_bodytemplate', 'subtext' => $txt['ghissues_bodytemplate_desc']);
	}

	public static function integrate_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
	{
		loadLanguage('GithubIssues');
		$permissionList['board']['ghissues_push'] = array(false, 'topic');
	}

	public static function integrate_prepare_display_context(&$output, &$message)
	{
		global $context, $txt, $scripturl, $board;

		if (self::isBoardEnabled($board) === false || self::$push_issues === false)
		{
			return;
		}

		// First message and already pushed: no button
		if ($output['id'] == self::$id_first_msg)
		{
			if (!empty(self::$id_issue))
			{
				return;
			}
		}
		else
		{
			// If the message is already a comment, no button.
			if (!empty($message['id_comment']))
			{
				unset($context['additional_drop_buttons']['ghissues_push']);

				return;
			}
		}

		loadLanguage('GithubIssues');

		if (!isset($context['additional_drop_buttons']))
		{
			$context['additional_drop_buttons'] = array();
		}

		$context['additional_drop_buttons']['ghissues_push'] = array(
			'href' => $scripturl . '?action=githubissues;id_msg=' . $output['id'] . ';board=' . $board,
			'text' => $txt['ghissues_push']
		);
	}

	public static function integrate_message_query(&$msg_selects, &$msg_tables)
	{
		$msg_selects[] = 'i.id_comment';
		$msg_tables[] = 'LEFT JOIN {db_prefix}messages_to_issue AS i ON (i.id_msg = m.id_msg)';
	}

	public static function integrate_topic_query(&$topic_selects)
	{
		$topic_selects[] = 'id_issue';
	}

	public static function integrate_display_topic($topicinfo)
	{
		self::$id_issue = $topicinfo['id_issue'];
		self::$id_first_msg = $topicinfo['id_first_msg'];
		self::$push_issues = allowedTo('ghissues_push');
	}

	public static function integrate_display_buttons()
	{
		global $context, $txt, $modSettings;

		if (empty(self::$id_issue))
		{
			return;
		}

		$txt['ghissues_btn_text'] = '#' . self::$id_issue;
		$context['normal_buttons']['github'] = array('text' => 'ghissues_btn_text', 'image' => 'github.png', 'lang' => false, 'url' => $modSettings['ghissues_repository'] . '/issues/' . self::$id_issue, 'active' => true);
	}

	public static function integrate_save_general_mod_settings()
	{
		if (!empty($_POST['ghissues_select_boards']))
		{
			$result = array();
			foreach ($_POST['ghissues_select_boards'] as $selected)
			{
				if ($selected[0] == 'b')
				{
					$b = explode('_', $selected);
					$result[] = (int) $b[1];
				}
			}
			$result = array_filter($result);
			updateSettings(array('ghissues_select_boards' => serialize($result)));
			unset($_POST['ghissues_select_boards']);
		}
	}

	public static function integrate_action_githubissues_before()
	{
		// dummy
	}

	public static function isBoardEnabled($board)
	{
		global $modSettings;

		if (self::$boardsList === null)
		{
			if (empty($modSettings['ghissues_select_boards']))
			{
				self::$boardsList = array();
			}
			else
			{
				self::$boardsList = unserialize($modSettings['ghissues_select_boards']);
			}
		}
		return in_array($board, self::$boardsList);
	}

	protected static function prepareModSettingsSelectBoardsList()
	{
		global $modSettings;

		if (empty($modSettings['ghissues_select_boards']))
			$modSettings['ghissues_select_boards'] = serialize(array());
		elseif (!is_array($modSettings['ghissues_select_boards']))
		{
			$tmp = unserialize($modSettings['ghissues_select_boards']);
			$tmpr = array();
			foreach ($tmp as $b)
				$tmpr[] = 'b_' . $b;
			$modSettings['ghissues_select_boards'] = serialize($tmpr);
		}
	}

	protected static function formatSelectBoardsList($recycle = null)
	{
		require_once(SUBSDIR . '/Boards.subs.php');
		$boardListOpt = array(
			'access' => '-1',
			'override_permissions' => true,
			'not_redirection' => true,
			'ignore' => $recycle
		);
		$boards_structure = getBoardList($boardListOpt);
		$select = array();

		foreach ($boards_structure['categories'] as $category)
		{
			if (empty($category['boards']))
				continue;
			$select_tmp = array();
			foreach ($category['boards'] as $board)
			{
				if ($board['allow'])
				{
					$select_tmp['b_' . $board['id']] = ($board['child_level'] > 0 ? str_repeat('=', $board['child_level']) . '> ' : '') . $board['name'];
				}
			}

			if (!empty($select_tmp))
			{
				$select['c1_' . $category['id']] = '----------';
				$select['c2_' . $category['id']] = $category['name'];
				$select['c3_' . $category['id']] = '----------';
				$select += $select_tmp;
			}
		}

		return $select;
	}
}