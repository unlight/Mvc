<?php

abstract class Bean extends RedBean_SimpleModel {

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
			$name = $this->getMeta('type');
			self::$columns = R::getColumns($name);
		}
		return self::$columns;
	}

	public function save() {
		$id = R::store($this->bean);
		return $id;
	}

	public function delete() {
		$result = R::trash($this->bean);
		return $result;
	}

}