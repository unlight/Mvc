<?php

abstract class Controller extends Pluggable {
	
	protected $app;
	public $selfUrl;
	protected $assets = array();
	protected $view = '';
	protected $masterView = '';
	public $data = array();
	protected $cssFiles = array();
	protected $jsFiles = array();
	public $head;
	protected $controllerName;
	protected $requestMethodName;
	protected $json = array();
	protected $deliveryType = 'ALL';
	protected $deliveryMask = 0;
	protected $definitions = array();
	protected $cssIdentifier;
	protected $cssClass;

	const DELIVERY_VIEW = 1;
	const DELIVERY_JSON = 2;
	const DELIVERY_DATA = 4;
	const DELIVERY_RESOURCE = 8;

	public function __construct() {
		$this->app = application();
		parent::__construct();
		$this->initialize();
		$this->selfUrl = StaticRequest('RequestUri');
	}

	public function initialize() {
		$this->mutator->initialize();
	}

	public function addJsFile($file) {
		$info = array(
			'file' => $file
		);
		$this->jsFiles[] = $info;
	}

	public function addCssFile($file) {
		$info = array(
			'file' => $file
		);
		$this->cssFiles[] = $info;
	}

	public function controllerName() {
		if ($this->controllerName === null) {
			$name = get_class($this);
			$name = substr($name, 0, -10);
			$this->controllerName = strtolower($name);
		}
		return $this->controllerName;
	}

	public function requestMethodName($requestMethodName = null) {
		if ($requestMethodName !== null) {
			$this->requestMethodName = $requestMethodName;
		}
		return $this->requestMethodName;
	}

	public function addAsset($asset, $container = 'content', $name = '') {
		if ($name) {
			$this->assets[$container][$name] = $asset;
		} else {
			$this->assets[$container][] = $asset;
		}
	}

	public function addModule($module, $assetTarget = '') {
		if (is_string($module)) {
			if (property_exists($this, $module)) {
				$module = $this->$module;
			}
			$module = new $module($this);
		}
		$assetTarget = $assetTarget ?: $module->assetTarget();
		$this->addAsset($module, $assetTarget, $module->name());
	}

	private function getAsset($name) {
		$result = '';
		$collection =& $this->assets[$name];
		if ($collection) {
			foreach ($collection as $asset) {
				if ($asset instanceof Module) {
					$result .= $asset->toString();
				} else {
					$result .= $asset;
				}
			}
		}
		return $result;
	}

	private function renderAsset($name) {
		echo $this->getAsset($name);
	}

	public function config($name, $default = false) {
		return $this->app['config']($name, $default);
	}

	public function render() {
		// var_dump($this->deliveryType);
		// var_dump($this->deliveryMask);
		// die;
		if ($this->deliveryType == 'ALL' || isSetBit($this->deliveryMask, self::DELIVERY_VIEW)) {
			$content = $this->fetchView($this->view, $this->controllerName);
			$this->addAsset($content, 'content');
		}
		if ($this->isXmlHttpRequest()) {
			if (isSetBit($this->deliveryMask, self::DELIVERY_DATA)) {
				// $this->json = $this->data;
				$this->json = array_merge($this->json, $this->data);
				// $this->setJson('data', $this->data);
			} elseif (isSetBit($this->deliveryMask, self::DELIVERY_VIEW)) {
				$this->setJson('content', $this->getAsset('content'));
			} else {
				// JSON.
			}

			$result = $this->app->json($this->json);
		} else {
			$this->addAsset($this->definitionList(), 'foot');
			if (!$this->masterView) $this->masterView = 'default.master.php';
			$masterView = 'views/' . $this->masterView;
			$result = $this->renderMaster($masterView);
		}
		return $result;
	}

	protected function findJsFile($fileInfo) {
		$file = $fileInfo['file'];
		if (strpos($file, '//') === false) {
			$paths = array('js', 'js/library');
			foreach ($paths as $path) {
				$jsPath = $path . '/' . $file;
				if (file_exists($jsPath)) {
					$file = $jsPath;
					break;
				}
			}
		}
		return $file;
	}

	protected function findCssFile($fileInfo) {
		$file = $fileInfo['file'];
		if (substr($file, 0, 1) == '~') {
			$file = substr($file, 1);
		} else {
			$file = "design/$file";
		}
		return $file;
	}

	protected function renderMaster($masterViewPath) {
		if (!$this->head) {
			$this->head = new HeadModule($this);
		}
		foreach ($this->cssFiles as $fileInfo) {
			$file = $this->findCssFile($fileInfo);
			$this->head->addCss($file);
		}
		foreach ($this->jsFiles as $fileInfo) {
			$file = $this->findJsFile($fileInfo);
			$this->head->addScript($file);
		}
		$this->addAsset($this->head, 'head');
		
		ob_start();
		include $masterViewPath;
		$html = ob_get_clean();

		return $html;
	}

   /**
    * Fetches the location of a view into a string and returns it. Returns
    * false on failure.
    */
	public function fetchViewLocation($view = '', $controllerName = '') {
		if (!$view) $view = $this->view;
		if (!$view) $view = $this->requestMethodName();
		if (strpos($view, '/') === false) {
			if (!$controllerName) $controllerName = $this->controllerName();
			$view = strtolower($controllerName . '/' . $view);
		}
		$view = suffixString($view, '.php');
		$viewPath = 'views/' . $view;

		if (!file_exists($viewPath)) {
			trigger_error("Could not find a '$view' view for the '$controllerName' controller.", E_USER_ERROR);
			$viewPath = false;
		}

		return $viewPath;
	}

	/**
	* Fetches the contents of a view into a string and returns it. Returns
	* false on failure.
	*
	* @param string $view The name of the view to fetch. If not specified, it will use the value
	* of $this->view. If $this->view is not specified, it will use the value
	* of $this->RequestMethod (which is defined by the dispatcher class).
	* @param string $controllerName The name of the controller that owns the view if it is not $this.
	*/
	protected function fetchView($view = '', $controllerName = '') {
		$viewPath = $this->fetchViewLocation($view , $controllerName);
		extract(get_object_vars($this));
		// Check to see if there is a handler for this particular extension.
		ob_start();
		// Parse the view and place it into the asset container if it was found.
		include $viewPath;
		$viewContents = ob_get_clean();

		return $viewContents;
	}

	/**
	* If this object has a "Head" object as a property, this will set it's Title value.
	* 
	* @param string $title The value to pass to $this->Head->Title().
	*/
	public function Title($title, $subtitle = null) {
		$this->setData('title', $title);
		if ($subtitle !== null) $this->setData('_subtitle', $subtitle);
	}

	/**
	* Set data from a method call.
	*
	* @param string $key The key that identifies the data.
	* @param mixed $value The data.
	* @param mixed $addproperty Whether or not to also set the data as a property of this object.
	* @return mixed The $value that was set.
	*/
	public function setData($key, $value = null, $addproperty = false) {
		if (is_array($key)) {
			$this->data = array_merge($this->data, $key);
			if ($addproperty === true) {
				foreach ($key as $name => $value) {
					$this->$name = $value;
				}
			}
			return;
		}
		
		$this->data[$key] = $value;
		if ($addproperty === true) {
			$this->$key = $value;
		}
		return $value;
	}

	/** Get a value out of the controller's data array.
	*
	* @param string $path The path to the data.
	* @param mixed $default The default value if the data array doesn't contain the path.
	* @return mixed
	* @see getValueR()
	*/
	public function data($path, $default = '') {
		$Result = getValueR($path, $this->data, $default);
		return $Result;
	}

	public function redirect($url, $code = 302) {
		return $this->app->redirect($url, $code);
	}

	public function cssIdentifier() {
		if ($this->cssIdentifier === null) {
			$this->cssIdentifier = strtolower($this->controllerName() . '_' . $this->requestMethodName());
		}
		return $this->cssIdentifier;
	}

	public function cssClass() {
		$components = array($this->controllerName(), $this->requestMethodName());
		if ($this->cssClass) $components[] = $this->cssClass;
		$components = array_map('ucfirst', $components);
		$mutatorCssClass = $this->mutator->cssClass();
		if ($mutatorCssClass) $components[] = $mutatorCssClass;
		$result = implode(' ', $components);
		return $result;
	}


	public function jsonTarget($target, $data, $type = 'html') {
		$item = array('target' => $target, 'data' => $data, 'type' => $type);
		if (!isset($this->json['targets'])) $this->json['targets'] = array();
		$this->json['targets'][] = $item;
	}

	/**
	* If JSON is going to be sent to the client, this method allows you to add
	* extra values to the JSON array.
	*
	* @param string $key The name of the array key to add.
	* @param string $value The value to be added. If empty, nothing will be added.
	*/
	public function setJson($key, $value = '') {
		$this->json[$key] = $value;
	}

	public function isXmlHttpRequest() {
		return $this->app['request']->isXmlHttpRequest();
	}

	public function deliveryType($deliveryType = null) {
		if ($deliveryType !== null) {
			if (strpos($deliveryType, 'VIEW') !== false) setBit($this->deliveryMask, self::DELIVERY_VIEW);
			if (strpos($deliveryType, 'DATA') !== false) setBit($this->deliveryMask, self::DELIVERY_DATA);
			if (strpos($deliveryType, 'JSON') !== false) setBit($this->deliveryMask, self::DELIVERY_JSON);
			$this->deliveryType = $deliveryType;
		}
		return $this->deliveryType;
	}


	public function addDefinition($term, $definition = null) {
		if ($definition !== null) {
			// Make sure the term is a valid id.
			if (!preg_match('/[a-z][\w\-]*/i', $term)) {
				throw new Exception('Definition term must start with a letter or an underscore and consist of alphanumeric characters.');
			}
			$this->definitions[$term] = $definition;
		}
		return ArrayValue($term, $this->definitions);
	}


	/**
	* Undocumented method.
	*
	* @todo Method definitionList() needs a description.
	*/
	public function definitionList() {
		$this->definitions['webRoot'] = getWebRoot();
		if (!array_key_exists('transientKey', $this->definitions)) {
			$this->definitions['transientKey'] = $this->app['session.handler']->transientKey();
		}
		$return = '<!-- Various definitions for Javascript //--><div id="definitions" style="display:none">';
		foreach ($this->definitions as $term => $definition) {
			$value = htmlspecialchars($definition);
			$return .= "\n<input type=\"hidden\" id=\"{$term}\" value=\"{$value}\" />";
		}
		return $return . '</div>';
	}

}