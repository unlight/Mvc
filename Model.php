<?php

// Model. Repository pattern.
class Model extends Pluggable {

	protected $name;
	protected $columns;
	public $validation;

	public function __construct($name) {
		$this->name = $name;
		$this->validation = new Validation();
		parent::__construct();
	}

	protected function beans($rows) {
		if (is_string($rows)) {
			$rows = R::getAll($rows);
		}
		$redbean = R::getRedBean();
		$beans = $redbean->convertToBeans($this->name, $rows);
		return $beans;
	}

	public function validationResults() {
		return $this->validation->results();
	}

	public function getId($id) {
		$result = R::sql()
			->from($this->name)
			->where('id', $id)
			->limit(1)
			->select()
			->dataset()
			->firstRow();
		return $result;
	}

	public function getWhere($where) {
		$dataset = R::sql()
			->from($this->name)
			->where($where)
			->select()
			->dataset();
		return $dataset;
	}

	public function get($conditions = false, $offset = null, $limit = null, $orderBy = null)  {
		$queryCount = getValue('queryCount', $conditions, false, true);
		$sqlBuilder = R::sql();
		$sqlBuilder->from($this->name);
		
		$sqlBuilder->where($conditions);

		if ($queryCount) {
			$sqlBuilder->select('count(*) as count');
		} else {

			$sqlBuilder->offset($offset, $limit);

			if ($orderBy) {
				$field = explode(' ', $orderBy);
				if (count($field) > 1) {
					$sqlBuilder->orderBy($field[0], $field[1]);
				} else {
					$sqlBuilder->orderBy($orderBy, 'desc');
				}
			}
			$sqlBuilder->select();
		}

		$sql = $sqlBuilder->sql();

		if ($queryCount) {
			$result = R::getCell($sql);
		} else {
			$result = $sqlBuilder->dataset($sql);
		}
		return $result;
	}

	public function sql() {
		$sql = R::sql();
		$args = func_get_args();
		if (count($args) > 0) {
			$method = array_shift($args);
			call_user_func_array(array($sql, $method), $args);
			return $this;
		}
		return $sql;
	}

	public static function staticGetId($id) {
		$model = new self();
		$result = $model->getId($id);
		return $result;
	}
}