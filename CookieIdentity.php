<?php

class CookieIdentity {

	protected $userid = null;
	protected $cookieName;
	protected $cookiePath;
	protected $cookieDomain;
	protected $cookieHashMethod;
	protected $cookieSalt;

	public function __construct($configuration = null) {
		loadfunctions('general');
		loadfunctions('string');
		loadfunctions('request');
		$this->initialize($configuration);
	}

	protected function initialize($configuration) {
		$this->cookieName = getValue('name', $configuration, 'silex');
		$this->cookiePath = getValue('path', $configuration, '/');
		$this->cookieDomain = getValue('domain', $configuration, null);
		$this->cookieSalt = getValue('salt', $configuration, '');
		$this->cookieHashMethod = getValue('hashmethod', $configuration, 'md5');
	}

	protected function getHash($userid) {
		$data = $this->getUserData($userid);
		$id = getValue('id', $data);
		$password =  getValue('password', $data);
		$hashmethod = $this->cookieHashMethod;
		return $hashmethod($userid.$password.$this->cookieSalt);
	}

	protected function getUserData($userid) {
		$user = R::load('user', $userid);
		if (!$user->id) return false;
		return array(
			'id' => $user->id,
			'password' => $user->password,
			'identity_hash' => $user->identity_hash
		);
	}

	protected function updateHash($userid, $identity_hash) {
		$user = R::load('user', $userid);
		if (!$user->identity_hash || $user->identity_hash != $identity_hash) {
			$user->identity_hash = $identity_hash;
			R::store($user);
		}
	}

	public function setIdentity($userid, $persist = true) {
		$expire = 0;
		if ($persist) {
			$expire = strtotime('+1 year');
		}
		$identity_hash = $this->getHash($userid);
		$value = implode('-', array($userid, $identity_hash));
		$_COOKIE[$this->cookieName] = $value;
		setcookie($this->cookieName, $value, $expire, $this->cookiePath, $this->cookieDomain);

		$this->updateHash($userid, $identity_hash);

		$this->userid = $userid;
		return $this->userid;
	}

	public function getIdentity() {
		if (!is_null($this->userid)) {
			return $this->userid;
		}
		$cookie = explode('-', getValue($this->cookieName, $_COOKIE));
		if (count($cookie) < 2) {
			$this->userid = 0;
			return $this->userid;
		}
		$userid = getValue(0, $cookie);
		$cookie_identity_hash = getValue(1, $cookie);

		$userData = $this->getUserData($userid);
		$identity_hash = getValue('identity_hash', $userData);

		if ($identity_hash && $cookie_identity_hash == $identity_hash) {
			$this->userid = $userid;
			// $this->setIdentity($userid, true);
		} else {
			$this->clearIdentity();
		}
		
		return $this->userid;
	}

	public function clearIdentity() {
		$this->userid = 0;
		$this->deleteCookie();
	}

	protected function deleteCookie() {
		$expiry = strtotime('-1 day');
		setcookie($this->cookieName, '', $expiry, $this->cookiePath, $this->cookieDomain);
		unset($_COOKIE[$this->cookieName]);
	}
}