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

	public function save($values = array()) {
		setNullValues($values);
		$this->defineColumns();
		$primaryKeyValue = getValue('id', $values);
		$insert = ($primaryKeyValue === false);
		$result = false;
		if ($this->validate($values, $insert) === true) {
			$this->importValues($values);
			$this->mutator->save();
			$result = R::store($this->bean);
		}
		return $result;
	}

	public function importValues($values) {
		$this->defineColumns();
		$args = func_get_args();
		// $snakeCaseValues = array();
		// foreach ($values as $key => $value) {
		// 	$key = toSnakeCase($key);
		// 	$snakeCaseValues[$key] = $value;
		// }
		$values = array();
		foreach ($args as $v) {
			if (!is_array($v)) $v = (array) $v;
			$values = array_merge($values, $v);
		}
		$values = array_intersect_key($values, $this->columns);
		$this->bean->import($values);
	}

	public function defineColumns() {
		if (is_null($this->columns)) {
			$this->columns = R::getColumns($this->name);
		}
		return $this->columns;
	}

	public function update() {
		$this->defineColumns();
		if (array_key_exists('date_updated', $this->columns)) {
			$this->bean->date_updated = date('Y-m-d H:i:s');
		}
		if ($this->bean->getID() == 0) {
			if (array_key_exists('date_inserted', $this->columns)) {
				$this->bean->date_inserted = date('Y-m-d H:i:s');
			}
			$session = application('session.handler');
			if ($session) {
				$sessionUserId = $session->userId();
				if (array_key_exists('insert_user_id', $this->columns)) {
					$this->bean->insert_user_id = $sessionUserId;
				}
				if (array_key_exists('update_user_id', $this->columns)) {
					$this->bean->update_user_id = $sessionUserId;
				}
			}
		}
	}

	public function validate($values, $insert = false) {
		$this->defineColumns();
		$result = $this->validation->validate($values, $insert);
		return $result;
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

	// Bean methods.
	
	public function saveBean() {
		$result = R::store($this->bean);
		return $result;
	}

	public function deleteBean() {
		R::trash($this->bean);
	}

	// RedBean_SimpleModel
	
	/**
	 * Contains the inner bean.
	 * @var RedBean_OODBBean
	 */
	protected $bean;

	/**
	 * Used by FUSE: the ModelHelper class to connect a bean to a model.
	 * This method loads a bean in the model.
	 *
	 * @param RedBean_OODBBean $bean bean
	 */
	public function loadBean(RedBean_OODBBean $bean) {
		$this->bean = $bean;
	}
	/**
	 * Magic Getter to make the bean properties available from
	 * the $this-scope.
	 *
	 * @param string $prop property
	 *
	 * @return mixed $propertyValue value
	 */
	public function __get($prop) {
		return $this->bean->$prop;
	}
	/**
	 * Magic Setter
	 *
	 * @param string $prop  property
	 * @param mixed  $value value
	 */
	public function __set($prop, $value) {
		$this->bean->$prop = $value;
	}
	/**
	 * Isset implementation
	 *
	 * @param  string $key key
	 *
	 * @return
	 */
	public function __isset($key) {
		return (isset($this->bean->$key));
	}
	/**
	 * Box the bean using the current model.
	 * 
	 * @return RedBean_SimpleModel $box a bean in a box
	 */
	public function box() {
		return $this;
	}
	/**
	 * Unbox the bean from the model.
	 * 
	 * @return RedBean_OODBBean $bean bean 
	 */
	public function unbox(){
		return $this->bean;
	}

}