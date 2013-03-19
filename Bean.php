<?php

abstract class Bean extends RedBean_SimpleModel {

	protected static $columns;

	protected function columns() {
		if (is_null(self::$columns)) {
			$name = $this->bean->getMeta('type');
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

}