<?php

class PluginManager extends Pluggable {

	protected $Instances;
	protected $EventHandlerCollection;
	protected $DeclaredPlugins = array();
	protected $RegisteredPlugins = array();
	protected static $SelfInstance;

	public static function Instance() {
		if (is_null(self::$SelfInstance)) {
			self::$SelfInstance = new self();
		}
		return self::$SelfInstance;
	}

	public static function __callStatic($Name, $Arguments) {
		$Instance = self::Instance();
		if (stripos($Name, 'static') === 0) {
			$Name = substr($Name, 6);
		}
		// Schoolchild does not understand why not call_user_func_array() :)
		switch (count($Arguments)) {
			case 0: return $Instance->$Name();
			case 1: return $Instance->$Name($Arguments[0]);
			case 2: return $Instance->$Name($Arguments[0], $Arguments[1]);
			case 3: return $Instance->$Name($Arguments[0], $Arguments[1], $Arguments[2]);
			case 4: return $Instance->$Name($Arguments[0], $Arguments[1], $Arguments[2], $Arguments[3]);
			case 5: return $Instance->$Name($Arguments[0], $Arguments[1], $Arguments[2], $Arguments[3], $Arguments[4]);
			default: throw new Exception('', 1);
		}
	}

	public static function EnablePlugin($PluginClassName) {
		self::Instance()->DeclarePlugin($PluginClassName);
	}

	protected function GetPluginInstance($PluginClassName) {
		$Instance =& $this->Instances[$PluginClassName]; 
		if (is_null($Instance)) {
			$Instance = new $PluginClassName();
		}
		return $Instance;
	}

	public function RegisterPlugin($ClassName) {
		foreach (get_class_methods($ClassName) as $Method) {
			$MethodName = strtolower($Method);
			// Loop through their individual methods looking for event handlers and method overrides.
			if (isset($MethodName[15])) {
				$Tmp = explode('_', $MethodName);
				$Suffix = array_pop($Tmp);
				switch ($Suffix) {
					case 'handler':
					case 'before':
					case 'after':
						$this->RegisterHandler($ClassName, $MethodName);
						break;
					case 'override':
						$this->RegisterOverride($ClassName, $MethodName);
						break;
					case 'create':
						$this->RegisterNewMethod($ClassName, $MethodName);
						break;
				}
			}
		}
		$this->RegisteredPlugins[$ClassName] = TRUE;
	}

	public function DeclarePlugin($ClassName) {
		$this->DeclaredPlugins[] = $ClassName;
	}

	protected function RegisterPlugins() {
		if (!is_null($this->EventHandlerCollection)) return;
		$this->EventHandlerCollection = array();
		
		foreach ($this->DeclaredPlugins as $ClassName) {
			// Only register the plugin if it implements the Gdn_IPlugin interface.
			if (!in_array('PluginInterface', class_implements($ClassName))) continue;
			// Register this plugin's methods.
			$this->RegisterPlugin($ClassName);
		}
	}

	/**
	* Registers a plugin method name as a handler.
	* @param string $HandlerClassName The name of the plugin class that will handle the event.
	* @param string $HandlerMethodName The name of the plugin method being registered to handle the event.
	* @param string $EventClassName The name of the class that will fire the event.
	* @param string $EventName The name of the event that will fire.
	* @param string $EventHandlerType The type of event handler.
	*/
	public function RegisterHandler($HandlerClassName, $HandlerMethodName, $EventClassName = '', $EventName = '', $EventHandlerType = '') {
		$HandlerKey = $HandlerClassName.'.'.$HandlerMethodName;
		$EventKey = strtolower($EventClassName == '' ? $HandlerMethodName : $EventClassName.'_'.$EventName.'_'.$EventHandlerType);

		// Create a new array of handler class names if it doesn't exist yet.
		if (array_key_exists($EventKey, $this->EventHandlerCollection) === FALSE)
			$this->EventHandlerCollection[$EventKey] = array();

		// Specify this class as a handler for this method if it hasn't been done yet.
		if (in_array($HandlerKey, $this->EventHandlerCollection[$EventKey]) === FALSE)
			$this->EventHandlerCollection[$EventKey][] = $HandlerKey;
	}

	protected function CallEventHandler($Sender, $EventClassName, $EventName, $EventHandlerType, $Options = array()) {

		// First call. Register plugins.
		if (is_null($this->EventHandlerCollection)) {
			$this->RegisterPlugins();
		}

		// $this->Trace("CallEventHandler $EventClassName $EventName $EventHandlerType");
		$Return = FALSE;

		// Backwards compatible for event key.
		if (is_string($Options)) {
			$PassedEventKey = $Options;
			$Options = array();
		} else {
			$PassedEventKey = GetValue('EventKey', $Options, NULL);
		}

		$EventKey = strtolower($EventClassName.'_'.$EventName.'_'.$EventHandlerType);
		if (!array_key_exists($EventKey, $this->EventHandlerCollection)) {
			return FALSE;
		}

		if (is_null($PassedEventKey)) $PassedEventKey = $EventKey;

		// For "All" events, calculate the stack
		if ($EventName == 'All') {
			$Stack = debug_backtrace();
			// this call
			array_shift($Stack);

			// plural call
			array_shift($Stack);

			$EventCaller = array_shift($Stack);
			$Sender->EventArguments['wild_event_stack'] = $EventCaller;
		}

		// $this->Trace($this->EventHandlerCollection[$EventKey], 'Event Handlers');

		// Loop through the handlers and execute them
		foreach ($this->EventHandlerCollection[$EventKey] as $PluginKey) {
			$PluginKeyParts = explode('.', $PluginKey);
			if (count($PluginKeyParts) == 2) {
				list($PluginClassName, $PluginEventHandlerName) = $PluginKeyParts;
			}

			if (isset($Sender->Returns)) {
				if (array_key_exists($EventKey, $Sender->Returns) === FALSE || is_array($Sender->Returns[$EventKey]) === FALSE) {
					$Sender->Returns[$EventKey] = array();
				}
				$Return = $this->GetPluginInstance($PluginClassName)->$PluginEventHandlerName($Sender, $Sender->EventArguments, $PassedEventKey);

				$Sender->Returns[$EventKey][$PluginKey] = $Return;
				$Return = TRUE;
			} else {
				$this->GetPluginInstance($PluginClassName)->$PluginEventHandlerName($Sender, array(), $PassedEventKey);
			}
		}

		return $Return;
	}


	/**
	* Transfer control to the plugins
	*
	* Looks through $this->EventHandlerCollection for matching event
	* signatures to handle. If it finds any, it executes them in the order it
	* found them. It instantiates any plugins and adds them as properties to
	* this class (unless they were previously instantiated), and then calls
	* the handler in question.
	*
	* @param object The object that fired the event being handled.
	* @param string The name of the class that fired the event being handled.
	* @param string The name of the event being fired.
	* @param string The type of handler being fired (Handler, Before, After).
	* @return bool True if an event was executed.
	*/
	protected function CallEventHandlers($Sender, $EventClassName, $EventName, $EventHandlerType = 'Handler', $Options = array()) {
		$Return = FALSE;

		// Look through $this->EventHandlerCollection for relevant handlers
		if ($this->CallEventHandler($Sender, $EventClassName, $EventName, $EventHandlerType)) {
			$Return = TRUE;
		}

		// Look for "Base" (aka any class that has $EventName)
		if ($this->CallEventHandler($Sender, 'Base', $EventName, $EventHandlerType)) {
			$Return = TRUE;
		}

		// Look for Wildcard event handlers
		$WildEventKey = $EventClassName.'_'.$EventName.'_'.$EventHandlerType;
		if ($this->CallEventHandler($Sender, 'Base', 'All', $EventHandlerType, $WildEventKey)) {
			$Return = TRUE;
		}
		if ($this->CallEventHandler($Sender, $EventClassName, 'All', $EventHandlerType, $WildEventKey)) {
			$Return = TRUE;
		}

		return $Return;
	}

	// protected $DeclaredMutators = array();

	// public static function EnableMutator($MutatorClassName, $ForObject) {
	// 	self::Instance()->DeclareMutator($MutatorClassName, $ForObject);
	// }


	// public function DeclareMutator($ClassName, $ForObject) {
	// 	$this->DeclaredMutators[$ForObject][] = $ClassName;
	// }

	// public static function StaticGetEnabledMutators($Class) {
	// 	return self::Instance()->GetEnabledMutators($Class);
	// }

	// public function GetEnabledMutators($Class) {
	// 	$Result = array();

	// 	$implements = class_implements($Class, false);
	// 	$parents = class_parents($Class, false);
	// 	$implements = array_merge($implements, $parents);

	// 	foreach ($implements as $interface) {
	// 		if (array_key_exists($Class, $this->DeclaredMutators)) {
	// 			$Result = array_merge($Result, $this->DeclaredMutators[$Class]);
	// 		}			
	// 	}
		
	// 	return $Result;
	// }

}
