<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:
 *
 * @link      <http://www.gnu.org/licenses/>.
 * @author    ruhllatio
 * @copyright 2016 nZEDb
 */
namespace nntmux\http;

use app\extensions\util\Versions;
use nntmux\Category;
use nntmux\db\Settings;
use nntmux\utility\Utility;

/**
 * Class Output -- abstract class for printing web requests outside of Smarty
 *
 * @package nntmux\http
 */
abstract class Capabilities
{
	/**
	 * @var Settings
	 */
	public $pdo;


	/**
	 * @var string The type of Capabilities request
	 */
	protected $type;

	/**
	 * Construct.
	 *
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null,
		];
		$options += $defaults;
		$this->pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());
	}

	/**
	 * Print XML or JSON output.
	 *
	 * @param array  $data   Data to print.
	 * @param array  $params Additional request parameters
	 * @param bool   $xml    True: Print as XML False: Print as JSON.
	 * @param string $type   What type of API query to format if XML
	 */
	public function output($data, $params, $xml = true, $type = '')
	{
		$this->type = $type;

		$options = [
			'Parameters' => $params,
			'Data'       => $data,
			'Server'     => $this->getForMenu(),
			'Type'       => $type
		];

		// Generate the XML Response
		$response = (new XML_Response($options))->returnXML();

		if ($xml) {
			header('Content-type: text/xml');
		} else {
			// JSON encode the XMLWriter response
			$response = json_encode(
			// Convert SimpleXMLElement response from XMLWriter
			//into array with namespace preservation
				Utility::xmlToArray(
				// Load the XMLWriter response
					@simplexml_load_string($response),
					[
						'attributePrefix' => '_',
						'textContent'     => 'text',
					]
				)
				// Strip the RSS+XML info from the JSON response by selecting enclosed data only
				['rss']['channel'],
				JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES
			);
			header('Content-type: application/json');
		}
		if ($response === false) {
			Utility::showApiError(201);
		} else {
			header('Content-Length: ' . strlen($response));
			echo $response;
		}
	}

	/**
	 * Collect and return various capability information for usage in API
	 *
	 * @return array
	 */
	public function getForMenu()
	{
		$serverroot = '';
		$https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? true : false);

		if (isset($_SERVER['SERVER_NAME'])) {
			$serverroot = (
				($https === true ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] .
				(($_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') ? ':' . $_SERVER['SERVER_PORT'] : '') .
				WWW_TOP . '/'
			);
		}

		return [
			'server' => [
				'appversion' => (new Versions())->getGitTagInRepo(),
				'version'    => '0.1',
				'title'      => $this->pdo->getSetting('title'),
				'strapline'  => $this->pdo->getSetting('strapline'),
				'email'      => $this->pdo->getSetting('email'),
				'meta'       => $this->pdo->getSetting('metakeywords'),
				'url'        => $serverroot,
				'image'      => $serverroot . 'themes/shared/images/logo.png'
			],
			'limits' => [
				'max'     => 100,
				'default' => 100
			],
			'registration' => [
				'available' => 'yes',
				'open'      => $this->pdo->getSetting('registerstatus') == 0 ? 'yes' : 'no'
			],
			'searching' => [
				'search'       => ['available' => 'yes', 'supportedParams' => 'q'],
				'tv-search'    => ['available' => 'yes', 'supportedParams' => 'q,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep'],
				'movie-search' => ['available' => 'yes', 'supportedParams' => 'q,imdbid'],
				'audio-search' => ['available' => 'no',  'supportedParams' => '']
			],
			'categories' =>
				($this->type === 'caps'
					? (new Category(['Settings' => $this->pdo]))->getForMenu()
					: null
				)
		];
	}
}
