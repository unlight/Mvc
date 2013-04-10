<?php

abstract class BeanModel extends RedBean_SimpleModel {

	protected $validation;
	protected static $columns;
	protected static $tables;

	public function validation() {
		if (is_null($this->validation)) {
			$this->validation = new Validation();
		}
		return $this->validation;
	}

	public function setProperties($data) {
		$this->columns();
		$args = func_get_args();

		$values = array();
		foreach ($args as $v) {
			if (!is_array($v)) $v = (array) $v;
			$values = array_merge($values, $v);
		}
		$values = array_intersect_key($values, self::$columns);
		$this->bean->import($values);
	}

	protected function columns() {
		$name = $this->bean->getMeta('type');
		// TODO: Add check for exists table (R::$redbean->tableExists())
		if (!isset(self::$columns[$name])) {
			self::$columns[$name] = R::getColumns($name);
		}
		return self::$columns[$name];
	}

	public function save() {
		$valid = true;
		$result = false;
		if ($this->validation) {
			$values = $this->bean->export();
			$valid = $this->validation->validate($values);
		}
		if ($valid) {
			$result = R::store($this->bean);
		}
		return $result;
	}

	public function update() {
		$bean = $this->bean;
		$columns = self::columns();
		if (!$bean->id) {
			if (array_key_exists('date_inserted', $columns)) {
				$this->date_inserted = R::isoDateTime();
			}
			if (array_key_exists('insert_user_id', $columns)) {
				$session = application('session.handler');
				if ($session->isValid()) {
					$this->insert_user_id = $session->userId();	
				}
			}
		}
		if (array_key_exists('date_updated', $columns)) {
			$this->date_updated = R::isoDateTime();
		}
		if (array_key_exists('update_user_id', $columns)) {
			$session = application('session.handler');
			if ($session->isValid()) {
				$this->update_user_id = $session->userId();
			}
		}
	}

	public function validationResults() {
		$result = array();
		if ($this->validation) {
			$result = $this->validation->results();
		}
		return $result;
	}
}