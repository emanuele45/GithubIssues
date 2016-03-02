<?php
/**
 * Github Issues
 *
 * @author  emanuele
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.0.1
 */

global $hooks, $mod_name;
$hooks = array(
	array(
		'integrate_general_mod_settings',
		'GithubIssues_Integrate::integrate_general_mod_settings',
		'SOURCEDIR/GithubIssues.integrate.php',
	),
	array(
		'integrate_save_general_mod_settings',
		'GithubIssues_Integrate::integrate_save_general_mod_settings',
		'SOURCEDIR/GithubIssues.integrate.php',
	),
	array(
		'integrate_prepare_display_context',
		'GithubIssues_Integrate::integrate_prepare_display_context',
		'SOURCEDIR/GithubIssues.integrate.php',
	),
	array(
		'integrate_action_githubissues_before',
		'GithubIssues_Integrate::integrate_action_githubissues_before',
		'SOURCEDIR/GithubIssues.integrate.php',
	),
	array(
		'integrate_topic_query',
		'GithubIssues_Integrate::integrate_topic_query',
		'SOURCEDIR/GithubIssues.integrate.php',
	),
	array(
		'integrate_display_topic',
		'GithubIssues_Integrate::integrate_display_topic',
		'SOURCEDIR/GithubIssues.integrate.php',
	),
	array(
		'integrate_message_query',
		'GithubIssues_Integrate::integrate_message_query',
		'SOURCEDIR/GithubIssues.integrate.php',
	),
	array(
		'integrate_display_buttons',
		'GithubIssues_Integrate::integrate_display_buttons',
		'SOURCEDIR/GithubIssues.integrate.php',
	),
);
$mod_name = 'Github Issues';

// ---------------------------------------------------------------------------------------------------------------------
define('ELK_INTEGRATION_SETTINGS', serialize(array(
	'integrate_menu_buttons' => 'install_menu_button',)));

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('ELK'))
	exit('<b>Error:</b> Cannot install - please verify you put this in the same place as ElkArte\'s index.php.');

if (ELK == 'SSI')
{
	// Let's start the main job
	install_mod($mod_name);
	// and then let's throw out the template! :P
	obExit(null, null, true);
}
else
{
	setup_hooks($hooks);
}

function install_mod($mod_name, $hooks)
{
	global $context;

	$context['mod_name'] = $mod_name;
	$context['sub_template'] = 'install_script';
	$context['page_title_html_safe'] = 'Install script of the mod: ' . $mod_name;
	if (isset($_GET['action']))
		$context['uninstalling'] = $_GET['action'] == 'uninstall' ? true : false;
	$context['html_headers'] .= '
	<style type="text/css">
    .buttonlist ul {
      margin:0 auto;
			display:table;
		}
	</style>';

	// Sorry, only logged in admins...
	isAllowedTo('admin_forum');

	if (isset($context['uninstalling']))
		setup_hooks($hooks);
}

function setup_hooks($hooks)
{
	global $context;

	$integration_function = empty($context['uninstalling']) ? 'add_integration_function' : 'remove_integration_function';
	foreach ($hooks as $hook)
		$integration_function($hook[0], $hook[1], $hook[2]);

	if (empty($context['uninstalling']))
	{
		updateSettings(array(
			'ghissues_bodytemplate' => '{body}

Reported by [url={poster_profile}]{poster_name}[/url] at [url={message_url}]{message_url}[/url]'
		));

		$db_table = db_table();

		$db_table->db_add_column(
			'{db_prefix}topics',
			array(
				'name' => 'id_issue',
				'type' => 'mediumint',
				'size' => 8,
				'default' => 0
			)
		);

		$db_table->db_create_table(
			'{db_prefix}messages_to_issue', 
			array(
				array(
					'name' => 'id_msg',
					'type' => 'int',
					'unsigned' => true,
					'default' => 0
				),
				array(
					'name' => 'id_issue',
					'type' => 'mediumint',
					'size' => 8,
					'default' => 0
				),
				array(
					'name' => 'id_comment',
					'type' => 'mediumint',
					'size' => 8,
					'default' => 0
				),
			),
			array(
				array(
					'name' => 'id_msg',
					'type' => 'key',
					'columns' => array('id_msg'),
				),
				array(
					'name' => 'id_issue',
					'type' => 'key',
					'columns' => array('id_issue'),
				),
			)
		);
	}

	$context['installation_done'] = true;
}

function install_menu_button (&$buttons)
{
	global $boardurl, $context;

	$context['sub_template'] = 'install_script';
	$context['current_action'] = 'install';

	$buttons['install'] = array(
		'title' => 'Installation script',
		'show' => allowedTo('admin_forum'),
		'href' => $boardurl . '/install.php',
		'active_button' => true,
		'sub_buttons' => array(
		),
	);
}

function template_install_script ()
{
	global $boardurl, $context;

	echo '
	<div class="tborder login"">
		<div class="cat_bar">
			<h3 class="catbg">
				Welcome to the install script of the mod: ' . $context['mod_name'] . '
			</h3>
		</div>
		<span class="upperframe"><span></span></span>
		<div class="roundframe centertext">';
	if (!isset($context['installation_done']))
		echo '
			<strong>Please select the action you want to perform:</strong>
			<div class="buttonlist">
				<ul>
					<li>
						<a class="active" href="' . $boardurl . '/install.php?action=install">
							<span>Install</span>
						</a>
					</li>
					<li>
						<a class="active" href="' . $boardurl . '/install.php?action=uninstall">
							<span>Uninstall</span>
						</a>
					</li>
				</ul>
			</div>';
	else
		echo '<strong>Database adaptation successful!</strong>';

	echo '
		</div>
		<span class="lowerframe"><span></span></span>
	</div>';
}
?>