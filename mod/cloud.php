<?php
/**
 * @file mod/cloud.php
 * @brief Initialize Hubzilla's cloud (SabreDAV).
 *
 * Module for accessing the DAV storage area.
 */

use Sabre\DAV;
use RedMatrix\RedDAV;

// composer autoloader for SabreDAV
require_once('vendor/autoload.php');

/**
 * @brief Fires up the SabreDAV server.
 *
 * @param App &$a
 */

function cloud_init(&$a) {
	require_once('include/reddav.php');

	if (! is_dir('store'))
		os_mkdir('store', STORAGE_DEFAULT_PERMISSIONS, false);

	$which = null;
	if (argc() > 1)
		$which = argv(1);

	$profile = 0;

	$a->page['htmlhead'] .= '<link rel="alternate" type="application/atom+xml" href="' . $a->get_baseurl() . '/feed/' . $which . '" />' . "\r\n";

	if ($which)
		profile_load($a, $which, $profile);

	$auth = new RedDAV\RedBasicAuth();

	$ob_hash = get_observer_hash();

	if ($ob_hash) {
		if (local_channel()) {
			$channel = $a->get_channel();
			$auth->setCurrentUser($channel['channel_address']);
			$auth->channel_id = $channel['channel_id'];
			$auth->channel_hash = $channel['channel_hash'];
			$auth->channel_account_id = $channel['channel_account_id'];
			if($channel['channel_timezone'])
				$auth->setTimezone($channel['channel_timezone']);
		}
		$auth->observer = $ob_hash;
	}

	if ($_GET['davguest'])
		$_SESSION['davguest'] = true;

	$_SERVER['QUERY_STRING'] = str_replace(array('?f=', '&f='), array('', ''), $_SERVER['QUERY_STRING']);
	$_SERVER['QUERY_STRING'] = strip_zids($_SERVER['QUERY_STRING']);
	$_SERVER['QUERY_STRING'] = preg_replace('/[\?&]davguest=(.*?)([\?&]|$)/ism', '', $_SERVER['QUERY_STRING']);

	$_SERVER['REQUEST_URI'] = str_replace(array('?f=', '&f='), array('', ''), $_SERVER['REQUEST_URI']);
	$_SERVER['REQUEST_URI'] = strip_zids($_SERVER['REQUEST_URI']);
	$_SERVER['REQUEST_URI'] = preg_replace('/[\?&]davguest=(.*?)([\?&]|$)/ism', '', $_SERVER['REQUEST_URI']);

	$rootDirectory = new RedDAV\RedDirectory('/', $auth);

	// A SabreDAV server-object
	$server = new DAV\Server($rootDirectory);
	// prevent overwriting changes each other with a lock backend
	$lockBackend = new DAV\Locks\Backend\File('store/[data]/locks');
	$lockPlugin = new DAV\Locks\Plugin($lockBackend);

	$server->addPlugin($lockPlugin);

	$is_readable = false;

	if($_SERVER['REQUEST_METHOD'] === 'GET') {
		try { 
			$x = RedFileData('/' . $a->cmd, $auth);
		}
		catch(\Exception $e) {
			if($e instanceof Sabre\DAV\Exception\Forbidden) {
				http_status_exit(401, 'Permission denied.');
			}
		}
	}

	require_once('include/RedDAV/RedBrowser.php');
	// provide a directory view for the cloud in Hubzilla
	$browser = new RedDAV\RedBrowser($auth);
	$auth->setBrowserPlugin($browser);

	$server->addPlugin($browser);

	// Experimental QuotaPlugin
//	require_once('include/RedDAV/QuotaPlugin.php');
//	$server->addPlugin(new RedDAV\QuotaPlugin($auth));

	// All we need to do now, is to fire up the server
	$server->exec();

	killme();
}
