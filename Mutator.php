<?php

class Mutator {

	protected $name = '';
	protected $nextMutator;
	protected $owner;

	public function __construct($owner) {
		$this->owner = $owner;
	}

	// Pluggable.

	final public function addMutator(Mutator $mutator) {
		if ($this->nextMutator === null) {
			$this->nextMutator = $mutator;
		} else {
			$this->nextMutator->addMutator($mutator);
		}
	}

	// Controller.

	public function cssClass() {
		$result = '';
		if ($this->nextMutator) {
			$result = $this->nextMutator->cssClass();
		}
		return $result;
	}

	public function initialize() {
		if ($this->nextMutator) {
			$this->nextMutator->initialize();
		}
	}

	// Model.

	public function save() {
		$result = true;
		if ($this->nextMutator) {
			$result = $this->nextMutator->save();
		}
		return $result;
	}
}