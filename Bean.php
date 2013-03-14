<?php

abstract class Bean extends RedBean_SimpleModel {

	protected static $columns;

	protected function columns() {
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