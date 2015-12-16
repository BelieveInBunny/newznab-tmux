<?php
require_once SMARTY_DIR . 'Autoloader.php';

Smarty_Autoloader::register();
require_once NN_LIB . 'utility' . DS . 'SmartyUtils.php';

use newznab\db\Settings;
use newznab\Captcha;
use newznab\Users;
use newznab\SABnzbd;

class BasePage
{
	/**
	 * @var \newznab\db\Settings
	 */
	public $settings = null;

	/**
	 * @var newznab\Users
	 */
	public $users = null;

	/**
	 * @var Smarty
	 */
	public $smarty = null;


	public $title = '';
	public $content = '';
	public $head = '';
	public $body = '';
	public $meta_keywords = '';
	public $meta_title = '';
	public $meta_description = '';
	public $secure_connection = false;
	public $show_desktop_mode = false;

	/**
	 * Current page the user is browsing. ie browse
	 * @var string
	 */
	public $page = '';

	public $page_template = '';

	/**
	 * User settings from the MySQL DB.
	 * @var array|bool
	 */
	public $userdata = [];

	/**
	 * URL of the server. ie http://localhost/
	 * @var string
	 */
	public $serverurl = '';

	/**
	 * Whether to trim white space before rendering the page or not.
	 * @var bool
	 */
	public $trimWhiteSpace = true;

	/**
	 * Is the current session HTTPS?
	 * @var bool
	 */
	public $https = false;

	/**
	 * Public access to Captcha object for error checking.
	 *
	 * @var \newznab\Captcha
	 */
	public $captcha;

	/**
	 * User's theme
	 *
	 * @var string
	 */
	private $theme = 'Omicron';


	/**
	 * Set up session / smarty / user variables.
	 */
	public function __construct()
	{
		$this->https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? true : false);

		session_set_cookie_params(0, '/', '', $this->https, true);
		@session_start();

		if (NN_FLOOD_CHECK) {
			$this->floodCheck();
		}

		// Buffer settings/DB connection.
		$this->settings = new Settings();
		$this->smarty = new Smarty();

		$this->smarty->setCompileDir(SMARTY_DIR . 'templates_c/');
		$this->smarty->setConfigDir(SMARTY_DIR . 'configs/');
		$this->smarty->setCacheDir(SMARTY_DIR . 'cache/');
		$this->smarty->error_reporting = ((NN_DEBUG ? E_ALL : E_ALL - E_NOTICE));

		if (isset($_SERVER['SERVER_NAME'])) {
			$this->serverurl = (
				($this->https === true ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] .
				(($_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') ? ':' . $_SERVER['SERVER_PORT'] : '') .
				WWW_TOP . '/'
			);
			$this->smarty->assign('serverroot', $this->serverurl);
		}

		$this->page = (isset($_GET['page'])) ? $_GET['page'] : 'content';

		$this->users = new Users(['Settings' => $this->settings]);
		if ($this->users->isLoggedIn()) {
			$this->setUserPreferences();
		} else {
			$this->theme = $this->settings->getSetting('style');

			$this->smarty->assign('isadmin', 'false');
			$this->smarty->assign('ismod', 'false');
			$this->smarty->assign('loggedin', 'false');
		}

		// Tell Smarty which directories to use for templates
		$this->smarty->setTemplateDir(
			[
				'user_frontend' => NN_THEMES . $this->theme . '/templates/frontend',
				'frontend'      => NN_THEMES . 'Omicron/templates/frontend'
			]
		);

		$this->smarty->assign('theme', $this->theme);
		$this->smarty->assign('site', $this->settings);
		$this->smarty->assign('page', $this);
	}

	/**
	 * Unquotes quoted strings recursively in an array.
	 *
	 * @param $array
	 */
	private function stripSlashes(array &$array)
	{
		foreach ($array as $key => $value) {
			$array[$key] = (is_array($value) ? array_map('stripslashes', $value) : stripslashes($value));
		}
	}

	/**
	 * Check if the user is flooding.
	 */
	public function floodCheck()
	{
		$waitTime = (NN_FLOOD_WAIT_TIME < 1 ? 5 : NN_FLOOD_WAIT_TIME);
		// Check if this is not from CLI.
		if (empty($argc)) {
			// If flood wait set, the user must wait x seconds until they can access a page.
			if (isset($_SESSION['flood_wait_until']) && $_SESSION['flood_wait_until'] > microtime(true)) {
				$this->showFloodWarning($waitTime);
			} else {
				// If user not an admin, they are allowed three requests in FLOOD_THREE_REQUESTS_WITHIN_X_SECONDS seconds.
				if (!isset($_SESSION['flood_check_hits'])) {
					$_SESSION['flood_check_hits'] = 1;
					$_SESSION['flood_check_time'] = microtime(true);
				} else {
					if ($_SESSION['flood_check_hits'] >= (NN_FLOOD_MAX_REQUESTS_PER_SECOND < 1 ? 5 : NN_FLOOD_MAX_REQUESTS_PER_SECOND)) {
						if ($_SESSION['flood_check_time'] + 1 > microtime(true)) {
							$_SESSION['flood_wait_until'] = microtime(true) + $waitTime;
							unset($_SESSION['flood_check_hits']);
							$this->showFloodWarning($waitTime);
						} else {
							$_SESSION['flood_check_hits'] = 1;
							$_SESSION['flood_check_time'] = microtime(true);
						}
					} else {
						$_SESSION['flood_check_hits']++;
					}
				}
			}
		}
	}

	/**
	 * Done in html here to reduce any smarty processing burden if a large flood is underway.
	 *
	 * @param int $seconds
	 */
	public function showFloodWarning($seconds = 5)
	{
		header('Retry-After: ' . $seconds);
		$this->show503(
			sprintf(
				'Too many requests!</p><p>You must wait <b>%s seconds</b> before trying again.',
				$seconds
			)
		);
	}

	//
	// Inject content into the html head
	//
	public function addToHead($headcontent)
	{
		$this->head = $this->head."\n".$headcontent;
	}

	//
	// Inject js/attributes into the html body tag
	//
	public function addToBody($attr)
	{
		$this->body = $this->body." ".$attr;
	}

	/**
	 * @return bool
	 */
	public function isPostBack()
	{
		return (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST');
	}

	/**
	 * Show 404 page.
	 */
	public function show404()
	{
		header('HTTP/1.1 404 Not Found');
		exit(
		sprintf("
				<html>
					<head>
						<title>404 - File not found.</title>
					</head>
					<body>
						<h1>404 - File not found.</h1>
						<p>%s%s</p>
						<p>We could not find the above page or file on our servers.</p>
					</body>
				</html>",
			$this->serverurl,
			$this->page
		)
		);
	}

	public function show403($from_admin = false)
	{
		$redirect_path = ($from_admin) ? str_replace('/admin', '', WWW_TOP) : WWW_TOP;
		header("Location: $redirect_path/login?redirect=".urlencode($_SERVER["REQUEST_URI"]));
		die();
	}

	/**
	 * Show 503 page.
	 *
	 * @param string $message Message to display.
	 */
	public function show503($message = 'Your maximum api or download limit has been reached for the day.')
	{
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		exit(
		sprintf("
				<html>
					<head>
						<title>Service Unavailable.</title>
					</head>
					<body>
						<h1>Service Unavailable.</h1>
						<p>%s</p>
					</body>
				</html>",
			$message
		)
		);
	}

	public function show429($retry='')
	{
		header('HTTP/1.1 429 Too Many Requests');
		if ($retry != '')
			header('Retry-After: '.$retry);

		echo "
			<html>
			<head>
				<title>Too Many Requests</title>
			</head>

			<body>
				<h1>Too Many Requests</h1>

				<p>Wait ".(($retry != '') ? ceil($retry/60).' minutes ' : '')."or risk being temporarily banned.</p>

			</body>
			</html>";
		die();
	}

	public function render()
	{
		$this->smarty->display($this->page_template);
	}

	protected function setUserPreferences()
	{
		$this->userdata = $this->users->getById($this->users->currentUserId());
		$this->userdata["categoryexclusions"] = $this->users->getCategoryExclusion($this->users->currentUserId());

		// Change the theme to user's selected theme if they selected one, else use the admin one.
		if ($this->settings->getSetting('userselstyle') == 1) {
			$this->theme = isset($this->userdata['style']) ? $this->userdata['style'] : 'None';
			if ($this->theme == 'None') {
				$this->theme = $this->settings->getSetting('style');
			}

			if (lcfirst($this->theme) === $this->theme) {
				// TODO add redirect to error page telling the user their theme name is invalid (after SQL patch to update current users is added).
				$this->theme = ucfirst($this->theme);
			}
		}

		// Update last login every 15 mins.
		if ((strtotime($this->userdata['now']) - 900) >
			strtotime($this->userdata['lastlogin'])
		) {
			$this->users->updateSiteAccessed($this->userdata['id']);
		}

		$this->smarty->assign('userdata',$this->userdata);
		$this->smarty->assign('loggedin',"true");

		if ($this->userdata['nzbvortex_api_key'] != '' && $this->userdata['nzbvortex_server_url'] != '') {
			$this->smarty->assign('weHasVortex', true);
		} else {
			$this->smarty->assign('weHasVortex', false);
		}

		$sab = new SABnzbd($this);
		$this->smarty->assign('sabintegrated', $sab->integratedBool);
		if ($sab->integratedBool !== false && $sab->url != '' && $sab->apikey != '') {
			$this->smarty->assign('sabapikeytype', $sab->apikeytype);
		}
		switch ((int)$this->userdata['role']) {
			case Users::ROLE_ADMIN:
				$this->smarty->assign('isadmin', 'true');
				break;
			case Users::ROLE_MODERATOR:
				$this->smarty->assign('ismod', 'true');
		}

		if ($this->userdata["hideads"] == "1")
		{
			$this->settings->setSetting(['adheader', '']);
			$this->settings->setSetting(['adbrowse', '']);
			$this->settings->setSetting(['addetail', '']);
		}
	}
}
