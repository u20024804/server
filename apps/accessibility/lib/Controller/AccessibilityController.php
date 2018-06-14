<?php
declare (strict_types = 1);
/**
 * @copyright Copyright (c) 2018 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Accessibility\Controller;

use Leafo\ScssPhp\Compiler;
use Leafo\ScssPhp\Exception\ParserException;
use Leafo\ScssPhp\Formatter\Crunched;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;

class AccessibilityController extends Controller {

	/** @var string */
	protected $appName;

	/** @var string */
	protected $serverRoot;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/** @var ILogger */
	private $logger;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ITimeFactory */
	protected $timeFactory;

	/** @var IUserSession */
	private $userSession;

	/**
	 * Account constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param ILogger $logger
	 * @param IURLGenerator $urlGenerator
	 * @param ITimeFactory $timeFactory
	 * @param IUserSession $userSession
	 */
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IUserManager $userManager,
								ILogger $logger,
								IURLGenerator $urlGenerator,
								ITimeFactory $timeFactory,
								IUserSession $userSession) {
		parent::__construct($appName, $request);
		$this->appName      = $appName;
		$this->config       = $config;
		$this->userManager  = $userManager;
		$this->logger       = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory  = $timeFactory;
		$this->userSession  = $userSession;

		$this->serverRoot = \OC::$SERVERROOT;
		$this->appRoot    = \OC_App::getAppPath($this->appName);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function getCss(): DataDisplayResponse {

		$css     = '';
		$imports = '';
		$scssFiles = $this->getUserValues();

		foreach ($scssFiles as $scssFile) {
			if ($scssFile !== false) {
				$imports .= '@import "' . $scssFile . '";';
			}
		}

		if ($imports !== '') {
			$scss = new Compiler();
			$scss->setImportPaths([
				$this->appRoot . '/css/',
				$this->serverRoot . '/core/css/'
			]);

			// Continue after throw
			$scss->setIgnoreErrors(true);
			$scss->setFormatter(Crunched::class);

			// Compile
			try {
				$css .= $scss->compile(
					$imports .
					'@import "variables.scss";' .
					'@import "css-variables.scss";'
				);
			} catch (ParserException $e) {
				$this->logger->error($e->getMessage(), ['app' => 'core']);
			}
		}

		// We don't want to override vars with url since path is different
		$css = $this->filterOutRule('/--[a-z-:]+url\([^;]+\)/mi', $css);

		$response = new DataDisplayResponse($css, Http::STATUS_OK, ['Content-Type' => 'text/css']);

		// Set cache control
		$ttl = 31536000;
		$response->addHeader('Cache-Control', 'max-age=' . $ttl . ', immutable');
		$expires = new \DateTime();
		$now = $this->timeFactory->getTime();
		$expires->setTimestamp($now);
		$expires->add(new \DateInterval('PT' . $ttl . 'S'));
		$response->addHeader('Expires', $expires->format(\DateTime::RFC1123));
		$response->addHeader('Pragma', 'cache');

		// Update last generation key
		$this->config->setUserValue($this->userSession->getUser()->getUID(), $this->appName, 'generated', implode('-', $scssFiles));

		return $response;
	}

	private function getUserValues() {
		$userTheme = $this->config->getUserValue($this->userSession->getUser()->getUID(), $this->appName, 'theme', false);
		$userFont  = $this->config->getUserValue($this->userSession->getUser()->getUID(), $this->appName, 'font', false);

		return [
			'theme' => $userTheme,
			'font'  => $userFont
		];
	}

	private function filterOutRule(string $rule, string $css) {
		return preg_replace($rule, '', $css);
	}

}
