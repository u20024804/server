<?php
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Accessibility\AppInfo;

use OCP\AppFramework\App;
use OCP\IConfig;
use OCP\IUserSession;

class Application extends App {

	/** @var string */
	protected $appName = 'accessibility';

	/** @var IConfig */
	private $config;

	/** @var IUserSession */
	private $userSession;

	public function __construct() {
		parent::__construct($this->appName);
		$this->config      = \OC::$server->getConfig();
		$this->userSession = \OC::$server->getUserSession();

		// Inject the fake css on all pages
		$cssMd5 = $this->config->getUserValue($this->userSession->getUser()->getUID(), $this->appName, 'generated', 0);
		\OCP\Util::addStyle($this->appName, 'user-' . md5($cssMd5), true);
	}
}
