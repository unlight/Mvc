<?php

class SqlBuilder extends Sparrow {

	protected static $instance;
	protected $selects = array();

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function parseCondition($field, $value = null, $join = '', $escape = true) {
		if (is_string($value)) {
			if (strpos($value, '@') === 0) {
				$value = substr($value, 1);
				$result = parent::parseCondition($field, $value, $join, false);
				return $result;
			}
		}
		$result = parent::parseCondition($field, $value, $join, $escape);
		return $result;
	}

	public function reset() {
		parent::reset();
		$this->selects = array();
		return $this;
	}

	public function addselect($fields) {
		$this->selects[] = $fields;
		return $this;
	}

	public function select($fields = '*', $limit = null, $offset = null) {
		if (count($this->selects) > 0) {
			$result = parent::select($this->selects, $limit, $offset);
		} else {
			$result = parent::select($fields, $limit, $offset);
		}
		return $result;
	}

	public function dataset() {
		$name = $this->table;
		$sql = $this->sql();
		$rows = R::getAll($sql);
		$beans = R::getRedBean()->convertToBeans($name, $rows);
		$dataset = new DataSet($beans, 'object');
		// $pdo = R::$toolbox->getDatabaseAdapter()->getDatabase()->getPdo();
		// $dataset->PDOStatement($pdo);
		return $dataset;
	}
}