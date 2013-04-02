<?php

namespace Unlight;

use Silex\Application;
use \CookieIdentity;
use \R;

class SessionHandler {

	protected $config;
	protected $userId;
	protected $transientKey;
	
	public function __construct($config) {
		$this->config = $config;
	}

	protected function getAuthenticator() {
		static $cookieIdentity;
		if ($cookieIdentity === null) {
			$configuration = $this->config->get('application.cookie', array());
			$configuration['salt'] = $this->config->get('application.secretkey', md5(__FILE__));
			$cookieIdentity = new CookieIdentity($configuration);
		}
		return $cookieIdentity;
	}

	public function end() {
		if ($this->isValid()) {
			$auth = $this->getAuthenticator();
			$auth->clearIdentity();
			$this->userId = 0;
		}
	}

	/**
	 * [start description]
	 * @param  mixed $userId [description]
	 * @return [type]          [description]
	 */
	public function start($userId = false, $setIdentity = false, $persist = true) {
		if ($userId === false) {
			$userId = $this->getAuthenticator()->getIdentity();
		}
		$this->userId = ($userId !== false) ? $userId : 0;
		if ($this->userId > 0) {
			if ($setIdentity) {
				$this->getAuthenticator()->setIdentity($this->userId, $persist);
			}
			$user = R::load('user', $this->userId);
			if ($user->getId()) {
				if (!$user->transient_key) {
					$user->transient_key = randomString(12);
					R::store($user);
				}
				$this->transientKey($user->transient_key);
			} else {
				$this->userId = 0;
			}
		}
	}

	/**
	 * [transientKey description]
	 * @param  [type] $newKey [description]
	 * @return [type]         [description]
	 */
	public function transientKey($newKey = null) {
		if ($newKey !== null) {
			$this->transientKey = $newKey;
		}
		if ($this->transientKey !== null) {
			return $this->transientKey;
		} else {
			return randomString(12);
		}
	}

	public function isValid() {
		return ($this->userId > 0);
	}


	public function userId() {
		return $this->userId;
	}

	public function getUser() {
		$user = R::load('user', $this->userId);
		if (!$user->id) $user = false;
		return $user;
	}
}