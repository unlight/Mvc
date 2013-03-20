<?php

abstract class Bean extends RedBean_SimpleModel {

	public $validation;
	protected static $columns;

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
		// TODO: Add check for exists table (R::$redbean->tableExists())
		if (is_null(self::$columns)) {
			$name = $this->bean->getMeta('type');
			self::$columns = R::getColumns($name);
		}
		return self::$columns;
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

	public function delete() {
		$result = R::trash($this->bean);
		return $result;
	}

	public function update() {
		$bean = $this->bean;
		$columns = self::columns();
		if (!$bean->id) {
			if (array_key_exists('date_inserted', $columns)) {
				$this->date_inserted = R::isoDateTime();
			}
		}
		if (array_key_exists('date_updated', $columns)) {
			$this->date_updated = R::isoDateTime();
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