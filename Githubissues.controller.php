<?php

/**
 * Github Issues
 *
 * @author  emanuele
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.0.1
 */

class GithubIssues_Controller
{
	public function action_index()
	{
		$id_msg = isset($_REQUEST['id_msg']) ? (int) $_REQUEST['id_msg'] : 0;

		$this->_create_issue($id_msg);

		redirectexit('msg=' . $id_msg);
	}

	public function action_index_api()
	{
		global $context;

		$id_msg = isset($_REQUEST['id_msg']) ? (int) $_REQUEST['id_msg'] : 0;

		$context['json_data'] = $this->_create_issue($id_msg);

		loadTemplate('json');
		$context['sub_template'] = 'send_json';
	}

	protected function _create_issue($id_msg)
	{
		global $modSettings, $user_info;

		require_once(SUBSDIR . '/Githubber.class.php');

		$githubber = new Githubber($id_msg, $user_info['id'], array(
			'access_token' => $modSettings['ghissues_access_token'],
			'url' => 'https://api.github.com/',
			'repository_url' => $modSettings['ghissues_repository'],
			'body_template' => $modSettings['ghissues_bodytemplate'],
		), database());

		loadLanguage('GithubIssues');
		isAllowedTo('ghissues_push');

		$return = array();

		if ($githubber->noIssue())
		{
			$board = $githubber->getBoard();
			if (GithubIssues_Integrate::isBoardEnabled($board))
			{
				$return = $githubber->createIssue();
			}
		}

		return $return;
	}
}