<?php

/**
 * Github Issues
 *
 * @author  emanuele
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.0.1
 */

class Githubber
{
	protected $_id_msg = null;
	protected $_id_user = null;
	protected $_db = null;
	protected $_message_info = null;

	protected $_is_issue = false;
	protected $_id_issue = 0;
	protected $_is_comment = false;
	protected $_id_comment = 0;

	protected $_github = null;

	const API_URL = 'https://api.github.com/';
	const ISSUE_PATH_SCHEME = 'repos/:owner/:repo/issues';
	const COMMENT_PATH_SCHEME = 'repos/:owner/:repo/issues/:number/comments';

	public function __construct($id_msg, $id_user, $github, $db)
	{
		$this->_id_msg = $id_msg;
		$this->_id_user = $id_user;
		$this->_db = $db;

		require_once(SUBSDIR . '/GIValuesBag.class.php');

		$this->_github = new GIValuesBag($github);
		$this->_parseRepo();
	}

	protected function _parseRepo()
	{
		$path = parse_url($this->_github->repository_url, PHP_URL_PATH);
		$tmp = explode('/', trim($path, '/'));

		$this->_github->owner = $tmp[0];
		$this->_github->repo = $tmp[1];
	}

	public function noIssue()
	{
		$this->_basicMessageInfo($this->_id_msg);

		// Do we even have a message to speak of?
		if (empty($this->_message_info))
			return false;

		$this->_is_issue = $this->_message_info->id_msg == $this->_message_info->id_first_msg;
		$this->_is_comment = !$this->_is_issue;

		if ($this->_is_issue)
		{
			// The user can access this message if it's approved or they're owner
			if ($this->_message_info->topic_approved == 0 || $this->_message_info->approved == 0)
			{
				if ($this->_message_info->id_member != $this->_id_user)
				{
					return false;
				}
			}

			$this->_id_issue = $this->_message_info->id_issue;

			return empty($this->_id_issue);
		}
		else
		{
			// No issue? Sorry, without that there can't be comments...
			if (empty($this->_message_info->id_issue))
			{
				return false;
			}

			// The user can access this message if it's approved or they're owner
			if ($this->_message_info->approved == 0)
			{
				if ($this->_message_info->id_member != $this->_id_user)
				{
					return false;
				}
			}

			$this->_id_issue = $this->_message_info->id_issue;
			$this->_id_comment = $this->_message_info->id_comment;

			return empty($this->_id_comment);
		}
	}

	public function createIssue()
	{
		require_once(SUBSDIR . '/Package.subs.php');

		if ($this->_is_issue)
		{
			$response = $this->_createIssue();
			$this->_updateTopic($response->number);

			return $response;
		}
		elseif ($this->_is_comment)
		{
			$response = $this->_createComment();
			$this->_updateComment($response->id);

			return $response;
		}
		else
		{
			return false;
		}
	}

	protected function _updateTopic($issue)
	{
		$this->_db->query('', '
			UPDATE {db_prefix}topics
			SET id_issue = {int:new_issue}
			WHERE id_topic = {int:current_topic}',
			array(
				'new_issue' => $issue,
				'current_topic' => $this->_message_info->id_topic,
			)
		);
	}

	protected function _updateComment($id)
	{
		$this->_db->insert('',
			'{db_prefix}messages_to_issue',
			array(
				'id_msg' => 'int',
				'id_issue' => 'int',
				'id_comment' => 'int',
			),
			array(
				$this->_id_msg,
				$this->_id_issue,
				$id
			),
			array('id_msg')
		);
	}

	protected function _createIssue()
	{
		return $this->_fetch_web_data($this->_buildIssueUrl(), json_encode(array(
			'title' => $this->_message_info->subject,
			'body' => $this->_getBody(),
		)));
	}

	protected function _createComment()
	{
		return $this->_fetch_web_data($this->_buildCommentUrl(), json_encode(array(
			'title' => $this->_message_info->subject,
			'body' => $this->_getBody(),
		)));
	}

	protected function _getBody()
	{
		return parse_bbc(strtr($this->_github->body_template, array(
			'{body}' => $this->_message_info->body,
			'{poster_name}' => $this->_message_info->poster_name,
			'{poster_profile}' => replaceBasicActionUrl('{script_url}?action=profile;u=' . $this->_message_info->id_member),
			'{message_url}' => replaceBasicActionUrl('{script_url}?msg=' . $this->_id_msg),
			'{message_id}' => $this->_id_msg,
		)));
	}

	protected function _fetch_web_data($url, $post_data = '', $keep_alive = false, $redirection_level = 2)
	{
		// Include the file containing the Curl_Fetch_Webdata class.
		require_once(SOURCEDIR . '/CurlFetchWebdata.class.php');

		$fetch_data = new Curl_Fetch_Webdata(array(), $redirection_level);
		$fetch_data->get_url_data($url, $post_data);

		return json_decode($fetch_data->result('body'));
	}

	protected function _buildIssueUrl()
	{
		return Githubber::API_URL . $this->_urlReplacer(Githubber::ISSUE_PATH_SCHEME) . $this->_getAuth();
	}

	protected function _getAuth()
	{
		return '?access_token=' . $this->_github->access_token;
	}

	protected function _urlReplacer($scheme)
	{
		return strtr($scheme, 
			array(
				':owner' => $this->_github->owner,
				':repo' => $this->_github->repo,
				':number' => $this->_id_issue,
				':id' => $this->_id_comment,
			)
		);
	}

	protected function _buildCommentUrl()
	{
		return Githubber::API_URL . $this->_urlReplacer(Githubber::COMMENT_PATH_SCHEME) . $this->_getAuth();
	}

	public function getBoard()
	{
		return (int) $this->_message_info->id_board;
	}

	protected function _basicMessageInfo($id_msg)
	{
		if ($this->_message_info !== null)
			return;

		$request = $this->_db->query('', '
			SELECT
				m.id_member, m.id_topic, m.id_board, m.id_msg, m.body, m.subject,
				m.poster_name, m.poster_email, m.approved,
				t.id_first_msg, t.approved AS topic_approved, t.id_issue,
				i.id_comment
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				LEFT JOIN {db_prefix}messages_to_issue AS i ON (m.id_msg = i.id_msg)
			WHERE m.id_msg = {int:message}
				AND {query_see_board}
			LIMIT 1',
			array(
				'message' => $id_msg,
			)
		);

		$messageInfo = $this->_db->fetch_assoc($request);
		$this->_db->free_result($request);

		require_once(SUBSDIR . '/GIValuesBag.class.php');

		$this->_message_info = new GIValuesBag($messageInfo);
	}
}