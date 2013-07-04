<?php 

// TODO: Keep uploaded files if POST fails.

/**
 * Form validation layer.
 * Modified by S.
 * 
 * Helps with the rendering of form controls that link directly to a data model.
 *
 * @author Mark O'Sullivan <markm@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Garden
 * @since 2.0
 */

class Form {
	/**
	 * @var string Action with which the form should be sent.
	 * @access public
	 */
	public $action = '';
	
	/**
	 * @var string Class name to assign to form elements with errors when inlineErrors is enabled.
	 * @since 2.0.18
	 * @access public
	 */
	public $errorClass = 'Error';
	
	/**
	 * @var array Associative array of hidden inputs with their "Name" attribute as the key.
	 * @access public
	 */
	public $hiddenInputs;

	/**
	 * @var string All form-related elements (form, input, select, textarea, [etc] will have
	 *    this value prefixed on their ID attribute. Default is "Form_". If the
	 *    id value is overridden with the Attribute collection for an element, this
	 *    value will not be used.
	 * @access public
	 */
	public $_idPrefix = 'form_';

	/**
	 * @var string All form-related elements (form, input, select, etc) will have
	 *    this value prefixed on their name attribute. Default is "Form".
	 *    If a model is assigned, the model name is used instead.
	 * @access public
	 */
	public $inputPrefix = '';

	/**
	 * @var string Form submit method. Options are 'post' or 'get'.
	 * @access public
	 */
	public $method = 'post';

	/**
	 * @var array Associative array containing the key => value pairs being placed in the
	 *    controls returned by this object. Assigned by $this->Open() or $this->SetData().
	 * @access protected
	 */
	protected $dataArray;
	
	/**
	 * @var bool Whether to display inline errors with form elements.
	 *    Set with $this->ShowErrors() and $this->HideErrors().
	 * @since 2.0.18
	 * @access protected
	 */
	protected $inlineErrors = false;

	/**
	 * @var object Model that enforces data rules on $this->dataArray.
	 * @access protected
	 */
	protected $model;
	
	/**
	 * @var array Associative array of $fieldName => $validationFunctionName arrays that
	 *    describe how each field specified failed validation.
	 * @access protected
	 */
	protected $validationResults = array();

	/**
	 * @var array $field => $value pairs from the form in the $_POST or $_GET collection 
	 *    (depending on which method was specified for sending form data in $this->method). 
	 *    Populated & accessed by $this->FormValues(). 
	 *    Values can be retrieved with $this->GetFormValue($fieldName).
	 * @access private
	 */
	public $formValues;
	
	/**
	 * @var array Collection of IDs that have been created for form elements. This
	 *    private property is used to record all IDs so that duplicate IDs are not
	 *    added to the screen.
	 * @access private
	 */
	private $_idCollection = array();

	protected $configuration;
	protected $sessionHandler;

	/**
	 * [__construct description]
	 */
	public function __construct($configuration, $sessionHandler, $tableName = '') {
		
		loadFunctions('render');
		loadFunctions('array-object');
		
		$this->configuration = $configuration;
		$this->sessionHandler = $sessionHandler;

		/**
		 * @todo REMOVE (Old code)
		 */
		if ($tableName != '') {
			$tableModel = new Gdn_Model($tableName);
			$this->SetModel($tableModel);
		}
		
		// Get custom error class.
		$this->errorClass = $this->config('application.forms.inlineErrorClass', 'Error');
	}

	/**
	 * [transientKey description]
	 * @return [type] [description]
	 */
	protected function transientKey() {
		return $this->sessionHandler->transientKey();
	}

	/**
	 * [config description]
	 * @param  [type]  $name    [description]
	 * @param  boolean $default [description]
	 * @return [type]           [description]
	 */
	protected function config($name, $default = false) {
		return $this->configuration->get($name, $default);
	}

	/**
	 * Gdn_Format::Form()
	 * @param mixed $Mixed
	 */
	protected function FormatForm($Mixed) {
		$charset = $this->config('charset', 'utf-8');
		return htmlspecialchars($Mixed, ENT_QUOTES, $charset);
	}

	/**
	* Removes all non-alpha-numeric characters (except for _ and -) from
	*
	* @param string $Mixed A string to be formatted.
	* @return string
	*/
	protected static function FormatAlphaNumeric($Mixed) {
		return preg_replace('/([^\w\d_-])/', '', $Mixed);
	}

	protected function translate($code, $default = false) {
		$result = $code;
		if ($default !== false) {
			$result = $default;
		}
		return $result;
	}

	public function TextField($code, $fieldName, $options = false) {
		if ($options) {
			$options = inflateArray($options);
		}
		$Result = '';
		$Result .= $this->Label($code, $fieldName, GetValueR('label', $options));
		$Result .= $this->TextBox($fieldName, GetValueR('textbox', $options));
		return Wrap($Result, 'fieldset', GetValueR('wrap', $options));
	}
	
	
	/// =========================================================================
	/// UI Components: Methods that return XHTML form elements.
	/// =========================================================================

	/**
	 * Add errorClass to Attributes['class'].
	 *
	 * @since 2.0.18
	 * @access public
	 *
	 * @param array $attributes Field attributes passed by reference (property => value).
	 */
	public function AddErrorClass(&$attributes) {
		if (isset($attributes['class']))
			$attributes['class'] .= ' '.$this->errorClass;
		else
			$attributes['class'] = $this->errorClass;
	}
	
	public function BodyBox($column = 'Body', $attributes = array()) {
		TouchValue('MultiLine', $attributes, true);
		TouchValue('format', $attributes, $this->GetValue('Format', $this->config('Garden.InputFormatter')));
		TouchValue('Wrap', $attributes, true);
		
		$this->SetValue('Format', $attributes['format']);
		
		$this->EventArguments['Table'] = GetValue('Table', $attributes);
		
		// $this->FireEvent('BeforeBodyBox');
		
		return $this->TextBox($column, $attributes).$this->Hidden('Format');
	}
	
	/**
	 * Returns XHTML for a button.
	 *
	 * @param string $buttonCode The translation code for the text on the button.
	 * @param array $attributes An associative array of attributes for the button. Here is a list of
	 * "special" attributes and their default values:
	 * Attribute  Options                        Default
	 * ------------------------------------------------------------------------
	 * Type       The type of submit button      'submit'
	 * Value      Ignored for $buttonCode        $buttonCode translated
	 *
	 * @return string
	 */
	public function Button($buttonCode, $attributes = false) {
		$type = ArrayValueI('type', $attributes);
		if ($type === false) $type = 'submit';

		$cssClass = ArrayValueI('class', $attributes);
		if ($cssClass === false) $attributes['class'] = 'Button';

		$return = '<input type="' . $type . '"';
		$return .= $this->_IDAttribute($buttonCode, $attributes);
		$return .= $this->_NameAttribute($buttonCode, $attributes);
		$return .= ' value="' . $this->translate($buttonCode, ArrayValue('value', $attributes)) . '"';
		$return .= $this->_AttributesToString($attributes);
		$return .= " />\n";
		return $return;
	}

	/**
	 * Returns XHTML for a standard calendar input control.
	 *
	 * @param string $fieldName The name of the field that is being displayed/posted with this input. It
	 * should related directly to a field name in $this->dataArray.
	 * @param array $attributes An associative array of attributes for the input. ie. onclick, class, etc
	 * @return string
	 * @todo Create calendar helper
	 */
	public function Calendar($fieldName, $attributes = false) {
		// TODO: CREATE A CALENDAR HELPER CLASS AND LOAD/REFERENCE IT HERE.
		// THE CLASS SHOULD BE DECLARED WITH:
		//  if (!class_exists('Calendar') {
		// AT THE BEGINNING SO OTHERS CAN OVERRIDE THE DEFAULT CALENDAR WITH ONE
		// OF THEIR OWN.
		$class = ArrayValueI('class', $attributes, false);
		if ($class === false) $attributes['class'] = 'DateBox';

		// IN THE MEANTIME...
		return $this->Input($fieldName, 'text', $attributes);
	}

	/**
	 * Returns XHTML for a select list containing categories that the user has
	 * permission to use.
	 *
	 * @param array $fieldName An array of category data to render.
	 * @param array $options An associative array of options for the select. Here
	 * is a list of "special" options and their default values:
	 *
	 *   Attribute     Options                        Default
	 *   ------------------------------------------------------------------------
	 *   Value         The ID of the category that    false
	 *                 is selected.
	 *   IncludeNull   Include a blank row?           true
	 *   CategoryData  Custom set of categories to    CategoryModel::Categories()
	 *                 display.
	 *
	 * @return string
	 */
	public function CategoryDropDown($fieldName = 'CategoryID', $options = false) {
		$value = ArrayValueI('Value', $options); // The selected category id
		$categoryData = GetValue('CategoryData', $options, CategoryModel::Categories());
		
		// Sanity check
		if (is_object($categoryData))
			$categoryData = (array)$categoryData;
		else if (!is_array($categoryData))
			$categoryData = array();

		// Respect category permissions (remove categories that the user shouldn't see).
		$safeCategoryData = array();
		foreach ($categoryData as $categoryID => $category) {
			if (!$category['PermsDiscussionsAdd']) continue;
			if ($value != $categoryID) {
				if ($category['CategoryID'] <= 0 || !$category['PermsDiscussionsView']) continue;
				if ($category['Archived']) continue;
			}
			$safeCategoryData[$categoryID] = $category;
		}  
		
		// Opening select tag
		$return = '<select';
		$return .= $this->_IDAttribute($fieldName, $options);
		$return .= $this->_NameAttribute($fieldName, $options);
		$return .= $this->_AttributesToString($options);
		$return .= ">\n";
		
		// Get value from attributes
		if ($value === false) 
			$value = $this->GetValue($fieldName);
		if (!is_array($value)) 
			$value = array($value);
			
		// Prevent default $value from matching key of zero
		$hasValue = ($value !== array(false) && $value !== array('')) ? true : false;
		
		// Start with null option?
		$includeNull = GetValue('IncludeNull', $options);
		if ($includeNull === true)
			$return .= '<option value="">'.$this->translate('Select a category...').'</option>';
		elseif ($includeNull)
			$return .= "<option value=\"\">$includeNull</option>\n";
		elseif (!$hasValue)
			$return .= '<option value=""></option>';
			
		// Show root categories as headings (ie. you can't post in them)?
		$doHeadings = $this->config('Vanilla.Categories.DoHeadings');
		
		// If making headings disabled and there was no default value for
		// selection, make sure to select the first non-disabled value, or the
		// browser will auto-select the first disabled option.
		$forceCleanSelection = ($doHeadings && !$hasValue && !$includeNull);
		
		// Write out the category options
		if (is_array($safeCategoryData)) {
			foreach($safeCategoryData as $categoryID => $category) {
				$depth = GetValue('Depth', $category, 0);
				$disabled = $depth == 1 && $doHeadings;
				$selected = in_array($categoryID, $value) && $hasValue;
				if ($forceCleanSelection && $depth > 1) {
					$selected = true;
					$forceCleanSelection = false;
				}

				$return .= '<option value="' . $categoryID . '"';
				if ($disabled)
					$return .= ' disabled="disabled"';
				else if ($selected)
					$return .= ' selected="selected"'; // only allow selection if NOT disabled
				
				$name = GetValue('Name', $category, 'Blank Category Name');
				if ($depth > 1) {
					$name = str_pad($name, strlen($name)+$depth-1, ' ', STR_PAD_LEFT);
					$name = str_replace(' ', '&#160;', $name);
				}
					
				$return .= '>' . $name . "</option>\n";
			}
		}
		return $return . '</select>';
	}

	/**
	 * Returns XHTML for a checkbox input element.
	 *
	 * Cannot consider all checkbox values to be boolean. (2009-04-02 mosullivan)
	 * Cannot assume checkboxes are stored in database as string 'true'. (2010-07-28 loki_racer)
	 *
	 * @param string $fieldName Name of the field that is being displayed/posted with this input. 
	 *    It should related directly to a field name in $this->dataArray.
	 * @param string $label Label to place next to the checkbox.
	 * @param array $attributes Associative array of attributes for the input. (e.g. onclick, class)\
	 *    Setting 'inlineErrors' to false prevents error message even if $this->inlineErrors is enabled.
	 * @return string
	 */
	public function CheckBox($fieldName, $label = '', $attributes = false) {
		$value = ArrayValueI('value', $attributes, true);
		$attributes['value'] = $value;

		if ($this->GetValue($fieldName) == $value) $attributes['checked'] = 'checked';
			
		// Show inline errors?
		$showErrors = ($this->inlineErrors && array_key_exists($fieldName, $this->validationResults));
		
		// Add error class to input element
		if ($showErrors) $this->AddErrorClass($attributes);

		$input = $this->Input($fieldName, 'checkbox', $attributes);
		if ($label != '') $input = '<label for="' . ArrayValueI('id', $attributes,
			$this->EscapeID($fieldName, false)) . '" class="CheckBoxLabel"'.Attribute('title', GetValue('title', $attributes)).'>' . $input . ' ' .
			 $this->translate($label) . '</label>';
			 
		// Append validation error message
		if ($showErrors && ArrayValueI('inlineErrors', $attributes, true))  
			$return .= $this->InlineError($fieldName);

		return $input;
	}

	/**
	 * Returns the XHTML for a list of checkboxes.
	 *
	 * @param string $fieldName Name of the field being posted with this input.
	 *
	 * @param mixed $dataSet Data to fill the checkbox list. Either an associative
	 * array or a database dataset. ex: RoleID, Name from GDN_Role.
	 *
	 * @param mixed $valueDataSet Values to be pre-checked in $dataSet. Either an associative array
	 * or a database dataset. ex: RoleID from GDN_UserRole for a single user.
	 *
	 * @param array $attributes  An associative array of attributes for the select. Here is a list of
	 * "special" attributes and their default values:
	 * Attribute   Options                        Default
	 * ------------------------------------------------------------------------
	 * ValueField  The name of the field in       'value'
	 *             $dataSet that contains the
	 *             option values.
	 * TextField   The name of the field in       'text'
	 *             $dataSet that contains the
	 *             option text.
	 *
	 * @return string
	 */
	public function CheckBoxList($fieldName, $dataSet, $valueDataSet = NULL, $attributes = false) {
		// Never display individual inline errors for these CheckBoxes
		$attributes['inlineErrors'] = false;
		
		$return = '';
		// If the form hasn't been posted back, use the provided $valueDataSet
		if ($this->IsPostBack() === false) {
			if ($valueDataSet === NULL) {
				$checkedValues = $this->GetValue($fieldName);
			} else {
				$checkedValues = $valueDataSet;
				if (is_object($valueDataSet)) {
					$checkedValues = ConsolidateArrayValuesByKey($valueDataSet->ResultArray(), $fieldName);
				}
			}
		} else {
			$checkedValues = $this->GetFormValue($fieldName, array());
		}
		$i = 1;
		if (is_object($dataSet)) {
			$valueField = ArrayValueI('ValueField', $attributes, 'value');
			$textField = ArrayValueI('TextField', $attributes, 'text');
			foreach ($dataSet->Result() as $data) {
				$instance = $attributes;
				$instance = RemoveKeyFromArray($instance, array('TextField', 'ValueField'));
				$instance['value'] = $data->$valueField;
				$instance['id'] = $fieldName . $i;
				if (is_array($checkedValues) && in_array($data->$valueField, $checkedValues)) {
					$instance['checked'] = 'checked';
				}

				$return .= '<li>' . $this->CheckBox($fieldName . '[]', $data->$textField, $instance) . "</li>\n";
				++$i;
			}
		} elseif (is_array($dataSet)) {
			foreach($dataSet as $text => $id) {
				// Set attributes for this instance
				$instance = $attributes;
				$instance = RemoveKeyFromArray($instance, array('TextField', 'ValueField'));
				
				$instance['id'] = $fieldName . $i;

				if (is_array($id)) {
					$valueField = ArrayValueI('ValueField', $attributes, 'value');
					$textField = ArrayValueI('TextField', $attributes, 'text');
					$text = GetValue($textField, $id, '');
					$id = GetValue($valueField, $id, '');
				} else {
					

					if (is_numeric($text))
						$text = $id;
				}
				$instance['value'] = $id;
				
				if (is_array($checkedValues) && in_array($id, $checkedValues)) {
					$instance['checked'] = 'checked';
				}

				$return .= '<li>' . $this->CheckBox($fieldName . '[]', $text, $instance) . "</li>\n";
				++$i;
			}
		}
		
		return '<ul class="'.ConcatSep(' ', 'CheckBoxList', GetValue('listclass', $attributes)).'">' . $return . '</ul>';
	}

	/**
	 * Returns the xhtml for a list of checkboxes; sorted into groups related to
	 * the TextField value of the dataset.
	 *
	 * @param string $fieldName The name of the field that is being displayed/posted with this input. It
	 * should related directly to a field name in a user junction table.
	 * ie. LUM_UserRole.RoleID
	 *
	 * @param mixed $dataSet The data to fill the options in the select list. Either an associative
	 * array or a database dataset. ie. RoleID, Name from LUM_Role.
	 *
	 * @param mixed $valueDataSet The data that should be checked in $dataSet. Either an associative array
	 * or a database dataset. ie. RoleID from LUM_UserRole for a single user.
	 *
	 * @param array $attributes An associative array of attributes for the select. Here is a list of
	 * "special" attributes and their default values:
	 *
	 * Attribute   Options                        Default
	 * ------------------------------------------------------------------------
	 * ValueField  The name of the field in       'value'
	 *             $dataSet that contains the
	 *             option values.
	 * TextField   The name of the field in       'text'
	 *             $dataSet that contains the
	 *             option text.
	 *
	 * @return string
	 */
	public function CheckBoxGrid($fieldName, $dataSet, $valueDataSet, $attributes) {
		// Never display individual inline errors for these CheckBoxes
		$attributes['inlineErrors'] = false;
		
		$return = '';
		$checkedValues = $valueDataSet;
		if (is_object($valueDataSet)) $checkedValues = ConsolidateArrayValuesByKey(
			$valueDataSet->ResultArray(), $fieldName);

		$i = 1;
		if (is_object($dataSet)) {
			$valueField = ArrayValueI('ValueField', $attributes, 'value');
			$textField = ArrayValueI('TextField', $attributes, 'text');
			$lastGroup = '';
			$group = array();
			$rows = array();
			$cols = array();
			$checkBox = '';
			foreach($dataSet->Result() as $data) {
				// Define the checkbox
				$instance = $attributes;
				$instance = RemoveKeyFromArray($instance, array('TextField', 'ValueField'));
				$instance['value'] = $data->$valueField;
				$instance['id'] = $fieldName . $i;
				if (is_array($checkedValues) && in_array($data->$valueField,
					$checkedValues)) {
					$instance['checked'] = 'checked';
				}
				$checkBox = $this->CheckBox($fieldName . '[]', '', $instance);

				// Organize the checkbox into an array for this group
				$currentTextField = $data->$textField;
				$aCurrentTextField = explode('.', $currentTextField);
				$aCurrentTextFieldCount = count($aCurrentTextField);
				$groupName = array_shift($aCurrentTextField);
				$colName = array_pop($aCurrentTextField);
				if ($aCurrentTextFieldCount >= 3) {
					$rowName = implode('.', $aCurrentTextField);
					if ($groupName != $lastGroup && $lastGroup != '') {
						// Render the last group
						$return .= $this->GetCheckBoxGridGroup(
							$lastGroup,
							$group,
							$rows,
							$cols);

						// Clean out the $group array & Rowcount
						$group = array();
						$rows = array();
						$cols = array();
					}

					if (array_key_exists($colName, $group) === false || is_array($group[$colName]) === false) {
						$group[$colName] = array();
						if (!in_array($colName, $cols))
							$cols[] = $colName;
							
					}

					if (!in_array($rowName, $rows))
						$rows[] = $rowName;

					$group[$colName][$rowName] = $checkBox;
					$lastGroup = $groupName;
				}
				++$i;
			}
		}
		/*elseif (is_array($dataSet)) {
			foreach ($dataSet as $text => $id) {
				$instance = $attributes;
				$instance = RemoveKeyFromArray($instance, array('TextField', 'ValueField'));
				$instance['id'] = $fieldName.$i;
				if (is_numeric($text))
					$text = $id;

				$instance['value'] = $id;
				if (in_array($id, $checkedValues))
					$instance['checked'] = 'checked';

				$return .= $this->CheckBox($fieldName.'[]', $text, $instance)."\n";
				$i++;
			}
		}
		*/
		return $return . $this->GetCheckBoxGridGroup($lastGroup, $group, $rows, $cols);
	}
	
	public function CheckBoxGridGroups($data, $fieldName) {
		$result = '';
		foreach($data as $groupName => $groupData) {
			$result .= $this->CheckBoxGridGroup($groupName, $groupData, $fieldName) . "\n";
		}
		return $result;
	}
	
	public function CheckBoxGridGroup($groupName, $data, $fieldName) {
		  // Never display individual inline errors for these CheckBoxes
		$attributes['inlineErrors'] = false;
		
		// Get the column and row info.
		$columns = $data['_Columns'];
		ksort($columns);
		$rows = $data['_Rows'];
		ksort($rows);
		unset($data['_Columns'], $data['_Rows']);
		
		if(array_key_exists('_Info', $data)) {
			$groupName = $data['_Info']['Name'];
			unset($data['_Info']);
		}
		
		$result = '<table class="CheckBoxGrid">';
		// Append the header.
		$result .= '<thead><tr><th>'.$this->translate($groupName).'</th>';
		$alt = true;
		foreach ($columns as $columnName => $x) {
			$result .=
				'<td'.($alt ? ' class="Alt"' : '').'>'
				. $this->translate($columnName)
				. '</td>';
				
			$alt = !$alt;
		}
		$result . '</tr></thead>';
		
		// Append the rows.
		$result .= '<tbody>';
		$checkCount = 0;
		foreach($rows as $rowName => $x) {
			$result .= '<tr><th>';
			
			// If the row name is still seperated by dots then put those in spans.
			$rowNames = explode('.', $rowName);
			for($i = 0; $i < count($rowNames) - 1; ++$i) {
				$result .= '<span class="Parent">'.$this->translate($rowNames[$i]).'</span>';
			}
			$result .= $this->translate($rowNames[count($rowNames) - 1]).'</th>';
			// Append the columns within the rows.
			$alt = true;
			foreach($columns as $columnName => $y) {
				$result .= '<td'.($alt ? ' class="Alt"' : '').'>';
				// Check to see if there is a row corresponding to this area.
				if(array_key_exists($rowName.'.'.$columnName, $data)) {
					$checkBox = $data[$rowName.'.'.$columnName];
					$attributes = array('value' => $checkBox['PostValue']);
					if($checkBox['Value'])
						$attributes['checked'] = 'checked';
//               $attributes['id'] = "{$groupName}_{$fieldName}_{$checkCount}";
					$checkCount++;
						
					$result .= $this->CheckBox($fieldName.'[]', '', $attributes);
				} else {
					$result .= ' ';
				}        
				$result .= '</td>';
					
				$alt = !$alt;
			}
			$result .= '</tr>';
		}
		$result .= '</tbody></table>';
		return $result;
	}
	
	/**
	 * Returns the closing of the form tag with an optional submit button.
	 *
	 * @param string $buttonCode
	 * @param string $xhtml
	 * @return string
	 */
	public function Close($buttonCode = '', $xhtml = '', $attributes = false) {
		$close = "</div>\n</form>";
		$button = '';
		if ($buttonCode != '') {
			$button =  '<div class="Buttons">'.$this->Button($buttonCode, $attributes).'</div>';
		}
		
		$fields = array_keys($this->_idCollection);
		$length = strlen($this->_idPrefix);
		$removePrefix = create_function('$s', "return substr(\$s, $length);");
		$names = array_map($removePrefix, $fields);
		$crc = $this->postCrcKey($names);

		$hidden = "<input".Attribute(array('type' => 'hidden', 'name' => '_crc', 'value' => $crc)) . " />";

		$result = $button . $xhtml . $hidden . $close;
		return $result;
	}


	
	/**
	 * Returns XHTML for a standard date input control.
	 *
	 * @param string $fieldName The name of the field that is being displayed/posted with this input. It
	 *    should related directly to a field name in $this->dataArray.
	 * @param array $attributes An associative array of attributes for the input, e.g. onclick, class.
	 *    Special attributes: 
	 *       YearRange, specified in yyyy-yyyy format. Default is 1900 to current year.
	 *       Fields, array of month, day, year. Those are only valid values. Order matters.
	 * @return string
	 */
	public function Date($fieldName, $attributes = false) {
		$return = '';
		$yearRange = ArrayValueI('yearrange', $attributes, false);
		$startYear = 0;
		$endYear = 0;
		if ($yearRange !== false) {
			if (preg_match("/^[\d]{4}-{1}[\d]{4}$/i", $yearRange) == 1) {
				$startYear = substr($yearRange, 0, 4);
				$endYear = substr($yearRange, 5);
			}
		}
		if ($yearRange === false || $startYear > $endYear) {
			$startYear = 1900;
			$endYear = date('Y');
		}

		$months = array_map('T',
			explode(',', 'Month,Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec'));

		$days = array();
		$days[] = $this->translate('Day');
		for($i = 1; $i < 32; ++$i) {
			$days[] = $i;
		}

		$years = array();
		$years[0] = $this->translate('Year');
		for($i = $endYear; $i >= $startYear; --$i) {
			$years[$i] = $i;
		}
		
		// Show inline errors?
		$showErrors = $this->inlineErrors && array_key_exists($fieldName, $this->validationResults);
		
		// Add error class to input element
		if ($showErrors) 
			$this->AddErrorClass($attributes);
		
		// Never display individual inline errors for these DropDowns
		$attributes['inlineErrors'] = false;

		$cssClass = ArrayValueI('class', $attributes, '');
		
		$submittedTimestamp = ($this->GetValue($fieldName) > 0) ? strtotime($this->GetValue($fieldName)) : false;
		
		// Allow us to specify which fields to show & order
		$fields = ArrayValueI('fields', $attributes, array('month', 'day', 'year'));
		if (is_array($fields)) {
			foreach ($fields as $field) {
				switch ($field) {
					case 'month':
						// Month select
						$attributes['class'] = trim($cssClass . ' Month');
						if ($submittedTimestamp)
							$attributes['Value'] = date('n', $submittedTimestamp);
						$return .= $this->DropDown($fieldName . '_Month', $months, $attributes);
						break;
					case 'day':
						// Day select
						$attributes['class'] = trim($cssClass . ' Day');
						if ($submittedTimestamp)
							$attributes['Value'] = date('j', $submittedTimestamp);
						$return .= $this->DropDown($fieldName . '_Day', $days, $attributes);
						break;
					case 'year':
						// Year select
						$attributes['class'] = trim($cssClass . ' Year');
						if ($submittedTimestamp)
							$attributes['Value'] = date('Y', $submittedTimestamp);
						$return .= $this->DropDown($fieldName . '_Year', $years, $attributes);
						break;
				}
			}
		}
		
		$return .= '<input type="hidden" name="DateFields[]" value="' . $fieldName . '" />';
			 
		// Append validation error message
		if ($showErrors)  
			$return .= $this->InlineError($fieldName);
			
		return $return;
	}
	
	/**
	 * Returns XHTML for a select list.
	 *
	 * @param string $fieldName The name of the field that is being displayed/posted with this input. It
	 *    should related directly to a field name in $this->dataArray. ie. RoleID
	 * @param mixed $dataSet The data to fill the options in the select list. Either an associative
	 *    array or a database dataset.
	 * @param array $attributes An associative array of attributes for the select. Here is a list of
	 *    "special" attributes and their default values:
	 *
	 *   Attribute   Options                        Default
	 *   ------------------------------------------------------------------------
	 *   ValueField  The name of the field in       'value'
	 *               $dataSet that contains the
	 *               option values.
	 *   TextField   The name of the field in       'text'
	 *               $dataSet that contains the
	 *               option text.
	 *   Value       A string or array of strings.  $this->dataArray->$fieldName
	 *   IncludeNull true to include a blank row    false
	 *               String to create disabled 
	 *               first option.
	 *   inlineErrors  Show inline error message?   true
	 *               Allows disabling per-dropdown
	 *               for multi-fields like Date()
	 *
	 * @return string
	 */
	public function DropDown($fieldName, $dataSet, $attributes = false) {
		// Show inline errors?
		$showErrors = ($this->inlineErrors && array_key_exists($fieldName, $this->validationResults));
		
		// Add error class to input element
		if ($showErrors) 
			$this->AddErrorClass($attributes);
		
		// Opening select tag
		$return = '<select';
		$return .= $this->_IDAttribute($fieldName, $attributes);
		$return .= $this->_NameAttribute($fieldName, $attributes);
		$return .= $this->_AttributesToString($attributes);
		$return .= ">\n";
		
		// Get value from attributes and ensure it's an array
		$value = ArrayValueI('Value', $attributes);
		if ($value === false) 
			$value = $this->GetValue($fieldName, GetValue('Default', $attributes));
		if (!is_array($value)) 
			$value = array($value);
			
		// Prevent default $value from matching key of zero
		$hasValue = ($value !== array(false) && $value !== array('')) ? true : false;
		
		// Start with null option?
		$includeNull = ArrayValueI('IncludeNull', $attributes, false);
		if ($includeNull === true) 
			$return .= "<option value=\"\"></option>\n";
		elseif ($includeNull)
			$return .= "<option value=\"\">$includeNull</option>\n";

		if (is_object($dataSet)) {
			$fieldsExist = false;
			$valueField = ArrayValueI('ValueField', $attributes, 'value');
			$textField = ArrayValueI('TextField', $attributes, 'text');
			$data = $dataSet->FirstRow();
			if (is_object($data) && property_exists($data, $valueField) && property_exists(
				$data, $textField)) {
				foreach($dataSet->Result() as $data) {
					$return .= '<option value="' . $data->$valueField .
						 '"';
					if (in_array($data->$valueField, $value) && $hasValue) $return .= ' selected="selected"';

					$return .= '>' . $data->$textField . "</option>\n";
				}
			}
		} elseif (is_array($dataSet)) {
			foreach($dataSet as $id => $text) {
				if (is_array($text)) {
					$attribs = $text;
					$text = GetValue('Text', $attribs, '');
					unset($attribs['Text']);
				} else {
					$attribs = array();
				}
				$return .= '<option value="' . $id . '"';
				if (in_array($id, $value) && $hasValue) $return .= ' selected="selected"';

				$return .= Attribute($attribs).'>' . $text . "</option>\n";
			}
		}
		$return .= '</select>';
		
		// Append validation error message
		if ($showErrors && ArrayValueI('inlineErrors', $attributes, true))  
			$return .= $this->InlineError($fieldName);
		
		return $return;
	}
	
	/**
	 * Returns the xhtml for a dropdown list with option groups.
	 * @param string $fieldName
	 * @param array $data
	 * @param string $groupField
	 * @param string $textField
	 * @param string $valueField
	 * @param array $attributes
	 * @return string
	 */
	public function DropDownGroup($fieldName, $data, $groupField, $textField, $valueField, $attributes = array()) {
		$return = '<select'
			. $this->_IDAttribute($fieldName, $attributes)
			. $this->_NameAttribute($fieldName, $attributes)
			. $this->_AttributesToString($attributes)
			. ">\n";
	  
		// Get the current value.
		$currentValue = GetValue('Value', $attributes, false);
		if ($currentValue === false) 
			$currentValue = $this->GetValue($fieldName, GetValue('Default', $attributes));
		
		// Add a null option?
		$includeNull = ArrayValueI('IncludeNull', $attributes, false);
		if ($includeNull === true) 
			$return .= "<option value=\"\"></option>\n";
		elseif ($includeNull)
			$return .= "<option value=\"\">$includeNull</option>\n";
		
		$lastGroup = NULL;
		
		foreach ($data as $row) {
			$group = $row[$groupField];
			
			// Check for a group header.
			if ($lastGroup !== $group) {
				// Close off the last opt group.
				if ($lastGroup !== NULL) {
					$return .= '</optgroup>';
				}
				
				$return .= '<optgroup label="'.htmlspecialchars($group)."\">\n";
				$lastGroup = $group;
			}
			
			$value = $row[$valueField];
			
			if ($currentValue == $value) {
				$selected = ' selected="selected"';
			} else
				$selected = '';
			
			$return .= '<option value="'.htmlspecialchars($value).'"'.$selected.'>'.htmlspecialchars($row[$textField])."</option>\n";
			
		}
		
		if ($lastGroup)
			$return .= '</optgroup>';
		
		$return .= '</select>';
		
		return $return;
	}
	
	/**
	 * Returns XHTML for all form-related errors that have occurred.
	 *
	 * @return string
	 */
	public function Errors() {
		$return = '';
		if (is_array($this->validationResults) && count($this->validationResults) > 0) {
			$return = "<div class=\"ErrorMessages\">\n<ul>\n";
			foreach($this->validationResults as $fieldName => $problems) {
				$count = count($problems);
				for($i = 0; $i < $count; ++$i) {
					if (substr($problems[$i], 0, 1) == '@')
						$return .= '<li>'.substr($problems[$i], 1)."</li>\n";
					else
						$return .= '<li>' . sprintf(
							$this->translate($problems[$i]),
							$this->translate($fieldName)) . "</li>\n";
				}
			}
			$return .= "</ul>\n</div>\n";
		}
		return $return;
	}
	
	public function ErrorString() {
		$return = '';
		if (is_array($this->validationResults) && count($this->validationResults) > 0) {
			foreach($this->validationResults as $fieldName => $problems) {
				$count = count($problems);
				for($i = 0; $i < $count; ++$i) {
					if (substr($problems[$i], 0, 1) == '@')
						$return .= rtrim(substr($problems[$i], 1), '.').'. ';
					else
						$return .= rtrim(sprintf(
							$this->translate($problems[$i]),
							$this->translate($fieldName)), '.').'. ';
				}
			}
		}
		return trim($return);
	}
	
	/**
	 * Encodes the string in a php-form safe-encoded format.
	 *
	 * @param string $string The string to encode.
	 * @return string
	 */
	public function EscapeString($string) {
		$array = false;
		if (substr($string, -2) == '[]') {
			$string = substr($string, 0, -2);
			$array = true;
		}
		$return = urlencode(str_replace(' ', '_', $string));
		if ($array === true) $return .= '[]';

		return str_replace('.', '-dot-', $return);
	}

	/**
	 * Returns a checkbox table.
	 *
	 * @param string $groupName The name of the checkbox table (the text that appears in the top-left
	 * cell of the table). This value will be passed through the T()
	 * function before render.
	 *
	 * @param array $group An array of $permissionName => $checkBoxXhtml to be rendered within the
	 * grid. This represents the final (third) part of the permission name
	 * string, as in the "Edit" part of "Garden.Roles.Edit".
	 * ie. 'Edit' => '<input type="checkbox" id="PermissionID"
	 * name="Role/PermissionID[]" value="20" />';
	 *
	 * @param array $rows An array of rows to appear in the grid. This represents the middle part
	 * of the permission name, as in the "Roles" part of "Garden.Roles.Edit".
	 *
	 * @param array $cols An array of columns to appear in the grid for each row. This (again)
	 * represents the final part of the permission name, as in the "Edit" part
	 * of "Garden.Roles.Edit".
	 * ie. Row1 = array('Add', 'Edit', 'Delete');
	 */
	public function GetCheckBoxGridGroup($groupName, $group, $rows, $cols) {
		$return = '';
		$headings = '';
		$cells = '';
		$rowCount = count($rows);
		$colCount = count($cols);
		for($j = 0; $j < $rowCount; ++$j) {
			$alt = 1;
			for($i = 0; $i < $colCount; ++$i) {
				$alt = $alt == 0 ? 1 : 0;
				$colName = $cols[$i];
				$rowName = $rows[$j];

				if ($j == 0) $headings .= '<td' . ($alt == 0 ? ' class="Alt"' : '') .
					 '>' . $this->translate($colName) . '</td>';

				if (array_key_exists($rowName, $group[$colName])) {
					$cells .= '<td' . ($alt == 0 ? ' class="Alt"' : '') .
						 '>' . $group[$colName][$rowName] .
						 '</td>';
				} else {
					$cells .= '<td' . ($alt == 0 ? ' class="Alt"' : '') .
						 '>&#160;</td>';
				}
			}
			if ($headings != '') $return .= "<thead><tr><th>" . $this->translate($groupName) . "</th>" .
				 $headings . "</tr></thead>\r\n<tbody>";

			$aRowName = explode('.', $rowName);
			$rowNameCount = count($aRowName);
			if ($rowNameCount > 1) {
				$rowName = '';
				for($i = 0; $i < $rowNameCount; ++$i) {
					if ($i < $rowNameCount - 1) $rowName .= '<span class="Parent">' .
						 $this->translate($aRowName[$i]) . '</span>';
					else $rowName .= $this->translate($aRowName[$i]);
				}
			} else {
				$rowName = $this->translate($rowName);
			}
			$return .= '<tr><th>' . $rowName . '</th>' . $cells . "</tr>\r\n";
			$headings = '';
			$cells = '';
		}
		return $return == '' ? '' : '<table class="CheckBoxGrid">'.$return.'</tbody></table>';
	}

	/**
	 * Returns XHTML for all hidden fields.
	 *
	 * @todo reviews damien's summary of this Form::GetHidden()
	 * @return string
	 */
	public function GetHidden() {
		$return = '';
		if (is_array($this->hiddenInputs)) {
			foreach($this->hiddenInputs as $name => $value) {
				$return .= $this->Hidden($name, array('value' => $value));
			}
			// Clean out the array
			// mosullivan - removed cleanout so that entry forms can all have the same hidden inputs added once on the entry/index view.
			// TODO - WATCH FOR BUGS BECAUSE OF THIS CHANGE.
			// $this->hiddenInputs = array();
		}
		return $return;
	}


	/**
	 * Returns the xhtml for a hidden input.
	 *
	 * @param string $fieldName The name of the field that is being hidden/posted with this input. It
	 * should related directly to a field name in $this->dataArray.
	 * @param array $attributes An associative array of attributes for the input. ie. maxlength, onclick,
	 * class, etc
	 * @return string
	 */
	public function Hidden($fieldName, $attributes = false) {
		$return = '<input type="hidden"';
		$return .= $this->_IDAttribute($fieldName, $attributes);
		$return .= $this->_NameAttribute($fieldName, $attributes);
		$return .= $this->_ValueAttribute($fieldName, $attributes);
		$return .= $this->_AttributesToString($attributes);
		$return .= ' />';
		return $return;
	}
	
	/**
	 * Returns XHTML of inline error for specified field.
	 *
	 * @since 2.0.18
	 * @access public
	 *
	 * @param string $fieldName The name of the field that is being displayed/posted with this input. It
	 *  should related directly to a field name in $this->dataArray.
	 * @return string
	 */
	public function InlineError($fieldName) {
		$appendError = '<p class="'.$this->errorClass.'">';
		foreach ($this->validationResults[$fieldName] as $validationError) {
			$appendError .= sprintf($this->translate($validationError),$this->translate($fieldName)).' ';
		}
		$appendError .= '</p>';
		
		return $appendError;
	}

	/**
	 * Returns the xhtml for a standard input tag.
	 *
	 * @param string $fieldName The name of the field that is being displayed/posted with this input. It
	 *  should related directly to a field name in $this->dataArray.
	 * @param string $type The type attribute for the input.
	 * @param array $attributes An associative array of attributes for the input. (e.g. maxlength, onclick, class)
	 *    Setting 'inlineErrors' to false prevents error message even if $this->inlineErrors is enabled.
	 * @return string
	 */
	public function Input($fieldName, $type = 'text', $attributes = false) {
		if ($type == 'text' || $type == 'password') {
			$cssClass = ArrayValueI('class', $attributes);
			if ($cssClass == false) $attributes['class'] = 'InputBox';
		}
		
		// Show inline errors?
		$showErrors = $this->inlineErrors && array_key_exists($fieldName, $this->validationResults);
		
		// Add error class to input element
		if ($showErrors) 
			$this->AddErrorClass($attributes);
		
		$return = '';
		$wrap = GetValue('Wrap', $attributes, false, true);
		if ($wrap) {
			$return .= '<div class="TextBoxWrapper">';
		}
		
		$return .= '<input type="' . $type . '"';
		$return .= $this->_IDAttribute($fieldName, $attributes);
		if ($type == 'file') $return .= Attribute('name',
			ArrayValueI('Name', $attributes, $fieldName));
		else $return .= $this->_NameAttribute($fieldName, $attributes);

		$return .= $this->_ValueAttribute($fieldName, $attributes);
		$return .= $this->_AttributesToString($attributes);
		$return .= ' />';
		if (strtolower($type) == 'checkbox') {
			if (substr($fieldName, -2) == '[]') $fieldName = substr($fieldName, 0, -2);

			$return .= '<input type="hidden" name="Checkboxes[]" value="' . $fieldName .
				 '" />';
		}
		
		// Append validation error message
		if ($showErrors && ArrayValueI('inlineErrors', $attributes, true))  
			$return .= $this->InlineError($fieldName);
		
		if ($wrap)
			$return .= '</div>';

		return $return;
	}

	/**
	 * Returns XHTML for a label element.
	 *
	 * @param string $translationCode Code to be translated and presented within the label tag.
	 * @param string $fieldName Name of the field that the label is for.
	 * @param array $attributes Associative array of attributes for the input that the label is for.
	 *    This is only available in case the related input has a custom id specified in the attributes array.
	 *
	 * @return string
	 */
	public function Label($translationCode, $fieldName = '', $attributes = false) {
		// Assume we always want a 'for' attribute because it's Good & Proper.
		// Precedence: 'for' attribute, 'id' attribute, $fieldName, $translationCode
		$defaultFor = ($fieldName == '') ? $translationCode : $fieldName;
		$for = ArrayValueI('for', $attributes, ArrayValueI('id', $attributes, $this->EscapeID($defaultFor, false)));

		return '<label for="' . $for . '"' . $this->_AttributesToString($attributes).'>' . $this->translate($translationCode) . "</label>\n";
	}
	
	/**
	 * Generate a friendly looking label translation code from a camel case variable name
	 * @param string|array $item The item to generate the label from.
	 *  - string: Generate the label directly from the item.
	 *  - array: Generate the label from the item as if it is a schema row passed to Gdn_Form::Simple().
	 * @return string 
	 */
	public static function LabelCode($item) {
		if (is_array($item)) {
			if (isset($item['LabelCode']))
				return $item['LabelCode'];

			$labelCode = $item['Name'];
		} else {
			$labelCode = $item;
		}
		
		
		if (strpos($labelCode, '.') !== false)
			$labelCode = trim(strrchr($labelCode, '.'), '.');

		// Split camel case labels into seperate words.
		$labelCode = preg_replace('`(?<![A-Z0-9])([A-Z0-9])`', ' $1', $labelCode);
		$labelCode = preg_replace('`([A-Z0-9])(?=[a-z])`', ' $1', $labelCode);
		$labelCode = trim($labelCode);

		return $labelCode;
	}

	/**
	 * Returns the xhtml for the opening of the form (the form tag and all
	 * hidden elements).
	 *
	 * @param array $attributes An associative array of attributes for the form tag. Here is a list of
	 *  "special" attributes and their default values:
	 *
	 *   Attribute  Options     Default
	 *   ----------------------------------------
	 *   method     get,post    post
	 *   action     [any url]   [The current url]
	 *   ajax       true,false  false
	 *
	 * @return string
	 *
	 * @todo check that missing DataObject parameter
	 */
	public function Open($attributes = array()) {
//      if ($this->inputPrefix)
//         Trace($this->inputPrefix, 'inputPrefix');
		
		if (!is_array($attributes))
			$attributes = array();
		
		$return = '<form';
		if ($this->inputPrefix != '' || array_key_exists('id', $attributes)) $return .= $this->_IDAttribute($this->inputPrefix,
			$attributes);

		// Method
		$methodFromAttributes = ArrayValueI('method', $attributes);
		$this->method = $methodFromAttributes === false ? $this->method : $methodFromAttributes;

		// Action
		$actionFromAttributes = ArrayValueI('action', $attributes);
		if ($this->action == '') $this->action = StaticRequest('RequestUri');
			
		$this->action = $actionFromAttributes === false ? $this->action : $actionFromAttributes;

		if (strcasecmp($this->method, 'get') == 0) {
			// The path is not getting passed on get forms so put them in hidden fields.
			$action = strrchr($this->action, '?');
			$exclude = GetValue('Exclude', $attributes, array());
			if ($action !== false) {
				$this->action = substr($this->action, 0, -strlen($action));
				parse_str(trim($action, '?'), $query);
				$hiddens = '';
				foreach ($query as $key => $value) {
					if (in_array($key, $exclude))
						continue;
					$key = $this->FormatForm($key);
					$value = $this->FormatForm($value);
					$hiddens .= "\n<input type=\"hidden\" name=\"$key\" value=\"$value\" />";
				}
			}
		}

		$return .= ' method="' . $this->method . '"'
			.' action="' . $this->action . '"'
			.$this->_AttributesToString($attributes)
			.">\n<div>\n";

		if (isset($hiddens))
			$return .= $hiddens;

		// Postback Key - don't allow it to be posted in the url (prevents csrf attacks & hijacks)
		if ($this->method != "get") {
			$return .= $this->Hidden('transientKey',
				array('value' => $this->transientKey()));
			// Also add a honeypot if Forms.HoneypotName has been defined
			$honeypotName = $this->config('application.forms.honeypotname');
			if ($honeypotName) $return .= $this->Hidden($honeypotName,
				array('Name' => $honeypotName, 'style' => "display: none;"));
		}

		// Render all other hidden inputs that have been defined
		$return .= $this->GetHidden();
		return $return;
	}
	
	/**
	 * Returns XHTML for a radio input element.
	 * 
	 * Provides way of wrapping Input() with a label.
	 *
	 * @param string $fieldName Name of the field that is being displayed/posted with this input. 
	 *    It should related directly to a field name in $this->dataArray.
	 * @param string $label Label to place next to the radio.
	 * @param array $attributes Associative array of attributes for the input (e.g. onclick, class).
	 *    Special values 'Value' and 'Default' (see RadioList).
	 * @return string
	 */
	public function Radio($fieldName, $label = '', $attributes = false) {
		$value = ArrayValueI('Value', $attributes, 'true');
		$attributes['value'] = $value;
		$formValue = $this->GetValue($fieldName, ArrayValueI('Default', $attributes));
		
		// Check for 'checked'
		if ($formValue == $value) 
			$attributes['checked'] = 'checked';
		
		// Never display individual inline errors for this Input
		$attributes['inlineErrors'] = false;
		
		// Get standard radio Input
		$input = $this->Input($fieldName, 'radio', $attributes);
		
		// Wrap with label
		if ($label != '') {
			$input = '<label for="' . ArrayValueI('id', $attributes, $this->EscapeID($fieldName, false)) . 
				'" class="RadioLabel">' . $input . ' ' . $this->translate($label) . '</label>';
		}
		
		return $input;
	}

	/**
	 * Returns XHTML for an unordered list of radio button elements.
	 *
	 * @param string $fieldName The name of the field that is being displayed/posted with this input. 
	 *    It should related directly to a field name in $this->dataArray. ie. RoleID
	 * @param mixed $dataSet The data to fill the options in the select list. Either an associative
	 *    array or a database dataset.
	 * @param array $attributes An associative array of attributes for the list. Here is a list of
	 *    "special" attributes and their default values:
	 *
	 *   Attribute   Options                        Default
	 *   ------------------------------------------------------------------------
	 *   ValueField  The name of the field in       'value'
	 *               $dataSet that contains the
	 *               option values.
	 *   TextField   The name of the field in       'text'
	 *               $dataSet that contains the
	 *               option text.
	 *   Value       A string or array of strings.  $this->dataArray->$fieldName
	 *   Default     The default value.             empty
	 *   inlineErrors  Show inline error message?   true
	 *               Allows disabling per-dropdown
	 *               for multi-fields like Date()
	 *
	 * @return string
	 */
	public function RadioList($fieldName, $dataSet, $attributes = false) {
		$list = GetValue('list', $attributes);
		$return = '';

		if ($list) {
			$return .= '<ul'.(isset($attributes['listclass']) ? " class=\"{$attributes['listclass']}\"" : '').'>';
			$liOpen = '<li>';
			$liClose = '</li>';
		} else {
			$liOpen = '';
			$liClose = ' ';
		}
		
		// Show inline errors?
		$showErrors = ($this->inlineErrors && array_key_exists($fieldName, $this->validationResults));
		
		// Add error class to input element
		if ($showErrors) 
			$this->AddErrorClass($attributes);

		if (is_object($dataSet)) {
			$valueField = ArrayValueI('ValueField', $attributes, 'value');
			$textField = ArrayValueI('TextField', $attributes, 'text');
			$data = $dataSet->FirstRow();
			if ((property_exists($data, $valueField) && property_exists($data, $textField))
				|| (isset($data->{$valueField}) && isset($data->{$textField}))
				) {
				foreach($dataSet->Result() as $data) {
					$attributes['value'] = $data->$valueField;

					$return .= $liOpen.$this->Radio($fieldName, $data->$textField, $attributes).$liClose;
				}
			}
		} elseif (is_array($dataSet)) {
			foreach($dataSet as $id => $text) {
				$attributes['value'] = $id;
				$return .= $liOpen.$this->Radio($fieldName, $text, $attributes).$liClose;
			}
		}

		if ($list)
			$return .= '</ul>';
		
		// Append validation error message
		if ($showErrors && ArrayValueI('inlineErrors', $attributes, true))  
			$return .= $this->InlineError($fieldName);

		return $return;
	}

	/**
	 * Returns the xhtml for a text-based input.
	 *
	 * @param string $fieldName The name of the field that is being displayed/posted with this input. It
	 *  should related directly to a field name in $this->dataArray.
	 * @param array $attributes An associative array of attributes for the input. ie. maxlength, onclick,
	 *  class, etc
	 * @return string
	 */
	public function TextBox($fieldName, $attributes = false) {
		if (!is_array($attributes))
			$attributes = array();
		
		$multiLine = ArrayValueI('MultiLine', $attributes);
		
		if ($multiLine) {
			$attributes['rows'] = ArrayValueI('rows', $attributes, '6'); // For xhtml compliance
			$attributes['cols'] = ArrayValueI('cols', $attributes, '100'); // For xhtml compliance
		}
		
		// Show inline errors?
		$showErrors = $this->inlineErrors && array_key_exists($fieldName, $this->validationResults);
		
		$cssClass = ArrayValueI('class', $attributes);
		if ($cssClass == false) $attributes['class'] = $multiLine ? 'TextBox' : 'InputBox';
		
		// Add error class to input element
		if ($showErrors) $this->AddErrorClass($attributes);
		
		$return = '';
		$wrap = GetValue('Wrap', $attributes, false, true);
		if ($wrap)
			$return .= '<div class="TextBoxWrapper">';
		
		$return .= $multiLine === true ? '<textarea' : '<input type="'.GetValue('type', $attributes, 'text').'"';
		$return .= $this->_IDAttribute($fieldName, $attributes);
		$return .= $this->_NameAttribute($fieldName, $attributes);
		$return .= $multiLine === true ? '' : $this->_ValueAttribute($fieldName, $attributes);
		$return .= $this->_AttributesToString($attributes);
		
		$value = ArrayValueI('value', $attributes, $this->GetValue($fieldName));
		
		$return .= $multiLine === true ? '>' . htmlentities($value, ENT_COMPAT, 'UTF-8') . '</textarea>' : ' />';
		
		// Append validation error message
		if ($showErrors)  
			$return .= $this->InlineError($fieldName);
		
		if ($wrap)
			$return .= '</div>';
		
		return $return;
	}
	

	/// =========================================================================
	/// Methods for interfacing with the model & db.
	/// =========================================================================
	
	/**
	 * Adds an error to the errors collection and optionally relates it to the
	 * specified FieldName. Errors added with this method can be rendered with
	 * $this->Errors().
	 *
	 * @param mixed $errorCode
	 *  - <b>string</b>: The translation code that represents the error to display.
	 *  - <b>Exception</b>: The exception to display the message for.
	 * @param string $fieldName The name of the field to relate the error to.
	 */
	public function AddError($error, $fieldName = '') {
		if(is_string($error))
			$errorCode = $error;
		elseif(is_a($error, 'Gdn_UserException')) {
			$errorCode = '@'.$error->getMessage();
		} elseif(is_a($error, 'Exception')) {
			// Strip the extra information out of the exception.
			$parts = explode('|', $error->getMessage());
			$message = $parts[0];
			if (count($parts) >= 3)
				$fileSuffix = ": {$parts[1]}->{$parts[2]}(...)";
			else
				$fileSuffix = "";

			if(Debug()) {
				$errorCode = '@<pre>'.
					$message."\n".
					'## '.$error->getFile().'('.$error->getLine().")".$fileSuffix."\n".
					$error->getTraceAsString().
					'</pre>';
			} else {
				$errorCode = '@'.strip_tags($error->getMessage());
			}
		}
		
		if ($fieldName == '') $fieldName = '<General Error>';

		if (!is_array($this->validationResults)) $this->validationResults = array();

		if (!array_key_exists($fieldName, $this->validationResults)) {
			$this->validationResults[$fieldName] = array($errorCode);
		} else {
			if (!is_array($this->validationResults[$fieldName])) $this->validationResults[$fieldName] = array(
				$this->validationResults[$fieldName],
				$errorCode);
			else $this->validationResults[$fieldName][] = $errorCode;
		}
	}

	/**
	 * Adds a hidden input value to the form.
	 *
	 * If the $forceValue parameter remains false, it will grab the value into the hidden input from the form
	 * on postback. Otherwise it will always force the assigned value to the
	 * input regardless of postback.
	 *
	 * @param string $fieldName The name of the field being added as a hidden input on the form.
	 * @param string $value The value being assigned in the hidden input. Unless $forceValue is
	 *  changed to true, this field will be retrieved from the form upon
	 *  postback.
	 * @param bool $forceValue
	 */
	public function AddHidden($fieldName, $value = NULL, $forceValue = false) {
		if ($this->IsPostBack() && $forceValue === false)
			$value = $this->GetFormValue($fieldName, $value);

		$this->hiddenInputs[$fieldName] = $value;
	}
	
	/**
	 * Returns a boolean value indicating if the current page has an
	 * authenticated postback. It validates the postback by looking at a
	 * transient value that was rendered using $this->Open() and submitted with
	 * the form. Ref: http://en.wikipedia.org/wiki/Cross-site_request_forgery
	 *
	 * @return bool
	 */
	public function AuthenticatedPostBack() {
		// S: 27.12.2012 13:30:54
		$KeyName = $this->EscapeFieldName('transientKey');
		$PostBackKey = GetPostValue($KeyName);

		// TODO: Validate user, if UserID <= 0 return FALSE
		$TransientKey = $this->transientKey();
		return ($TransientKey == $PostBackKey);
		


		// Commenting this out because, technically, a get request is not a "postback".
		// And since I typically use AuthenticatedPostBack to validate that a form has
		// been posted back a get request should not be considered an authenticated postback.
		//if ($this->method == "get") {
		// forms sent with "get" method do not require authentication.
		//   return true;
		//} else {
		$keyName = $this->EscapeFieldName('TransientKey');
		$postBackKey = Gdn::Request()->GetValueFrom(Gdn_Request::INPUT_POST, $keyName, false);
		
		// DEBUG:
		//$result .= '<div>KeyName: '.$keyName.'</div>';
		//echo '<div>PostBackKey: '.$postBackKey.'</div>';
		//echo '<div>TransientKey: '.$session->TransientKey().'</div>';
		//echo '<div>AuthenticatedPostBack: ' . ($session->ValidateTransientKey($postBackKey) ? 'Yes' : 'No');
		//die();
		return Gdn::Session()->ValidateTransientKey($postBackKey);
		//}
	}
	
	/**
	 * Checks $this->FormValues() to see if the specified button translation
	 * code was submitted with the form (helps figuring out what button was
	 *  pressed to submit the form when there is more than one button available).
	 *
	 * @param string $buttonCode The translation code of the button to check for.
	 * @return boolean
	 */
	public function ButtonExists($buttonCode) {
		$nameKey = $this->EscapeString($buttonCode);
		return array_key_exists($nameKey, $this->FormValues()) ? true : false;
	}
	
	/**
	 * Emptys the $this->formValues collection so that all form fields will load empty.
	 */
	public function ClearInputs() {
		$this->formValues = array();
	}

	/**
	 * Returns a count of the number of errors that have occurred.
	 *
	 * @return int
	 */
	public function ErrorCount() {
		if (!is_array($this->validationResults)) $this->validationResults = array();

		return count($this->validationResults);
	}

	/**
	 * Returns the provided fieldname with non-alpha-numeric values stripped.
	 *
	 * @param string $fieldName The field name to escape.
	 * @return string
	 */
	public function EscapeFieldName($fieldName) {
		$return = $this->inputPrefix;
		if ($return != '') $return .= '/';
		return $return . $this->EscapeString($fieldName);
	}

	/**
	 * Returns the provided fieldname with non-alpha-numeric values stripped and
	 * $this->_idPrefix prepended.
	 *
	 * @param string $fieldName
	 * @param bool $forceUniqueID
	 * @return string
	 */
	public function EscapeID($fieldName, $forceUniqueID = true) {
		$id = $fieldName;
		if (substr($id, -2) == '[]') $id = substr($id, 0, -2);

		$id = $this->_idPrefix . self::FormatAlphaNumeric(str_replace('.', '-dot-', $id));
		$tmp = $id;
		$i = 1;
		if ($forceUniqueID === true) {
			if (array_key_exists($id, $this->_idCollection)) {
				$tmp = $id.$this->_idCollection[$id];
				$this->_idCollection[$id]++;
			} else {
				$tmp = $id;
				$this->_idCollection[$id] = 1;
				
			}
		} else {
			// If not forcing unique (ie. getting the id for a label's "for" tag),
			// get the last used copy of the requested id.
			$found = false;
			$count = GetValue($id, $this->_idCollection, 0);
			if ($count <= 1)
				$tmp = $id;
			else
				$tmp = $id.($count - 1);
		}
		return $tmp;
	}
	
	/**
	 * 
	 *
	 * @return array
	 */
	public function FormDataSet() {
		if(is_null($this->formValues)) {
			$this->FormValues();
		}
		
		$result = array(array());
		foreach($this->formValues as $key => $value) {
			if(is_array($value)) {
				foreach($value as $rowIndex => $rowValue) {
					if(!array_key_exists($rowIndex, $result))
						$result[$rowIndex] = array($key => $rowValue);
					else
						$result[$rowIndex][$key] = $rowValue;
				}
			} else {
				$result[0][$key] = $value;
			}
		}
		
		return $result;
	}
	
	/**
	 * If the form has been posted back, this method return an associative
	 * array of $fieldName => $value pairs which were sent in the form.
	 *
	 * Note: these values are typically used by the model and it's validation object.
	 *
	 * @return array
	 */
	public function FormValues($newValue = NULL) {
		if($newValue !== NULL) {
			$this->formValues = $newValue;
			return;
		}
		
		$magicQuotes = get_magic_quotes_gpc();

		if (!is_array($this->formValues)) {
			$tableName = $this->inputPrefix;
			if(strlen($tableName) > 0)
				$tableName .= '/';
			$tableNameLength = strlen($tableName);
			$this->formValues = array();
			$collection = $this->method == 'get' ? $_GET : $_POST;
			$inputType = $this->method == 'get' ? INPUT_GET : INPUT_POST;
			
			
			
			foreach($collection as $field => $value) {
				$fieldName = substr($field, $tableNameLength);
				$fieldName = $this->_UnescapeString($fieldName);
				if (substr($field, 0, $tableNameLength) == $tableName) {
					if ($magicQuotes) {
						if (is_array($value)) {
							foreach ($value as $i => $v) {
								$value[$i] = stripcslashes($v);
							}
						} else {
							$value = stripcslashes($value);
						}
					}
					
					$this->formValues[$fieldName] = $value;
				}
			}
			
			// Make sure that unchecked checkboxes get added to the collection
			if (array_key_exists('Checkboxes', $collection)) {
				$uncheckedCheckboxes = $collection['Checkboxes'];
				if (is_array($uncheckedCheckboxes) === true) {
					$count = count($uncheckedCheckboxes);
					for($i = 0; $i < $count; ++$i) {
						if (!array_key_exists($uncheckedCheckboxes[$i], $this->formValues))
							$this->formValues[$uncheckedCheckboxes[$i]] = false;
					}
				}
			}
			
			// Make sure that Date inputs (where the day, month, and year are
			// separated into their own dropdowns on-screen) get added to the
			// collection as a single field as well...
			if (array_key_exists(
				'DateFields', $collection) === true) {
				$dateFields = $collection['DateFields'];
				if (is_array($dateFields) === true) {
					$count = count($dateFields);
					for($i = 0; $i < $count; ++$i) {
						if (array_key_exists(
							$dateFields[$i],
							$this->formValues) ===
							 false) // Saving dates in the format: YYYY-MM-DD
							$year = ArrayValue(
								$dateFields[$i] .
								 '_Year',
								$this->formValues,
								0);
						$month = ArrayValue(
							$dateFields[$i] .
								 '_Month',
								$this->formValues,
								0);
						$day = ArrayValue(
							$dateFields[$i] .
								 '_Day',
								$this->formValues,
								0);
						$month = str_pad(
							$month,
							2,
							'0',
							STR_PAD_LEFT);
						$day = str_pad(
							$day,
							2,
							'0',
							STR_PAD_LEFT);
						$this->formValues[$dateFields[$i]] = $year .
							 '-' .
							 $month .
							 '-' .
							 $day;
					}
				}
			}
		}
		
		// print_r($this->formValues);
		return $this->formValues;
	}

	/**
	 * [NulledFormValues description]
	 */
	public function NulledFormValues() {
		$formValues = $this->formValues();
		setNullValues($formValues);
		return $formValues;
	}

	/**
	 * Gets the value associated with $fieldName from the sent form fields.
	 * If $fieldName isn't found in the form, it returns $default.
	 *
	 * @param string $fieldName The name of the field to get the value of.
	 * @param mixed $default The default value to return if $fieldName isn't found.
	 * @return unknown
	 */
	public function GetFormValue($fieldName, $default = '') {
		return ArrayValue($fieldName, $this->FormValues(), $default);
	}

	/**
	 * Gets the value associated with $fieldName.
	 *
	 * If the form has been posted back, it will retrieve the value from the form.
	 * If it hasn't been posted back, it gets the value from $this->dataArray.
	 * Failing either of those, it returns $default.
	 *
	 * @param string $fieldName
	 * @param mixed $default
	 * @return mixed
	 *
	 * @todo check returned value type
	 */
	public function GetValue($fieldName, $default = false) {
		$return = '';
		// Only retrieve values from the form collection if this is a postback.
		if ($this->IsMyPostBack()) {
			$return = $this->GetFormValue($fieldName, $default);
		} else {
			$return = ArrayValue($fieldName, $this->dataArray, $default);
		}
		return $return;
	}
	
	/**
	 * Disable inline errors (this is the default).
	 */
	public function HideErrors() {
		$this->inlineErrors = false;
	}

	/**
	 * Examines the sent form variable collection to see if any data was sent
	 * via the form back to the server. Returns true on if anything is found.
	 *
	 * @return boolean
	 */
	public function IsPostBack() {
		/*
		2009-01-10 - $_GET should not dictate a "post" back.
		return count($_POST) > 0 ? true : false;
		
		2009-03-31 - switching back to "get" dictating a postback
		
		2012-06-27 - Using the request method to determine a postback.
		*/
		
		// switch (strtolower($this->method)) {
		// 	case 'get':
		// 		return count($_GET) > 0 || (is_array($this->FormValues()) && count($this->FormValues()) > 0) ? true : false;
		// 	default:
		// 		return Gdn::Request()->IsPostBack();
		// }
		
		$IsPostBack = IsPostBack();
		// S: 12.02.2013. Added checksum for fields
		// S: 13.02.2013 Problems in ajax.
		// if ($IsPostBack) {
		// 	$crc = $this->postCrcKey(array_keys($_POST));
		// 	if ($crc != GetValue('_crc', $_POST)) {
		// 		$IsPostBack = false;
		// 	}
		// }
		return $IsPostBack;
	}

	/**
	 * Calculate crc check sum  of posted fields.
	 * @param  [type] $fields [description]
	 * @return [type]         [description]
	 */
	protected function postCrcKey($fields) {
		if (($key = array_search('_crc', $fields)) !== false) unset($fields[$key]);
		if (($key = array_search('deliveryType', $fields)) !== false) unset($fields[$key]);
		sort($fields);
		$string = implode('', $fields);
		$salt = $this->config('application.secretkey');
		$crc = ucrc($string.$salt);
		return $crc;
	}

	/**
	 * Check if THIS particular form was submitted
	 * 
	 * Just like IsPostBack(), except auto populates FormValues and doesnt just check
	 * "was some data submitted lol?!".
	 * 
	 * @return boolean
	 */
	public function IsMyPostBack() {
		return IsPostBack();
		// switch (strtolower($this->method)) {
		// 	case 'get':
		// 		return count($_GET) > 0 || (is_array($this->FormValues()) && count($this->FormValues()) > 0) ? true : false;
		// 	default:
		// 		return IsPostBack();
		// }
	}
	
	/**
	 * This is a convenience method so that you don't have to code this every time
	 * you want to save a simple model's data.
	 *
	 * It uses the assigned model to save the sent form fields.
	 * If saving fails, it populates $this->validationResults with validation errors & related fields.
	 *
	 * @return unknown
	 */
	public function Save() {
		$saveResult = false;
		if ($this->ErrorCount() == 0) {
			if (!isset($this->_Model)) trigger_error(
				ErrorMessage(
					"You cannot call the form's save method if a model has not been defined.",
					"Form", "Save"), E_USER_ERROR);
			
			$data = $this->FormValues();
			if (method_exists($this->_Model, 'FilterForm'))
				$data = $this->_Model->FilterForm($this->FormValues());

			$args = array_merge(func_get_args(),
				array(
					NULL,
					NULL,
					NULL,
					NULL,
					NULL,
					NULL,
					NULL,
					NULL,
					NULL,
					NULL));
			$saveResult = $this->_Model->Save($data, $args[0], $args[1],
				$args[2], $args[3], $args[4], $args[5], $args[6], $args[7],
				$args[8], $args[9]);
			if ($saveResult === false) {
				// NOTE: THE VALIDATION FUNCTION NAMES ARE ALSO THE LANGUAGE
				// TRANSLATIONS OF THE ERROR MESSAGES. CHECK THEM OUT IN THE LOCALE
				// FILE.
				$this->SetValidationResults($this->_Model->ValidationResults());
			}
		}
		return $saveResult;
	}
	
	/**
	 * Assign a set of data to be displayed in the form elements.
	 *
	 * @param Ressource $data A result resource or associative array containing data to be filled in
	 */
	public function SetData($data) {
		if (is_object($data) === true) {
			// If this is a result object (/garden/library/database/class.dataset.php)
			// retrieve it's values as arrays
			if ($data instanceof DataSet) {
				$resultSet = $data->ResultArray();
				if (count($resultSet) > 0)
					$this->dataArray = $resultSet[0];
					
			} else {
				// Otherwise assume it is an object representation of a data row.
				$this->dataArray = (array) $data;
			}
		} else if (is_array($data)) {
			$this->dataArray = $data;
		}
	}
	
	/**
	 * Sets the value associated with $fieldName from the sent form fields.
	 * Essentially overwrites whatever was retrieved from the form.
	 *
	 * @param string $fieldName The name of the field to set the value of.
	 * @param mixed $value The new value of $fieldName.
	 */
	public function SetFormValue($fieldName, $value) {
		$this->FormValues();
		$this->formValues[$fieldName] = $value;
	}

	/**
	 * Set the name of the model that will enforce data rules on $this->dataArray.
	 *
	 * This value is also used to identify fields in the $_POST or $_GET
	 * (depending on the forms method) collection when the form is submitted.
	 *
	 * @param Gdn_Model $model The Model that will enforce data rules on $this->dataArray. This value
	 *  is passed by reference so any changes made to the model outside this
	 *  object apply when it is referenced here.
	 * @param Ressource $dataSet A result resource containing data to be filled in the form.
	 */
	public function SetModel($model, $dataSet = false) {
		$this->_Model = $model;
		
		if ($this->inputPrefix)
			$this->inputPrefix = $this->_Model->Name;
		if ($dataSet !== false) $this->SetData($dataSet);
	}
	
	/**
	 * @todo add documentation
	 */
	public function SetValidationResults($validationResults) {
		if (!is_array($this->validationResults)) $this->validationResults = array();

		$this->validationResults = array_merge_recursive($this->validationResults, $validationResults);
	}

	/**
	 * Sets the value associated with $fieldName.
	 *
	 * It sets the value in $this->dataArray rather than in $this->formValues.
	 *
	 * @param string $fieldName
	 * @param mixed $default
	 */
	public function SetValue($fieldName, $value) {
		if (!is_array($this->dataArray))
			$this->dataArray = array();
		
		$this->dataArray[$fieldName] = $value;
	} 

	/**
	 * Enable inline errors.
	 */
	public function ShowErrors() {
		$this->inlineErrors = true;
	}
	
	/**
	 * Generates a multi-field form from a schema.
	 * @param array $schema An array where each item of the array is a row that identifies a form field with the following information:
	 *  - Name: The name of the form field.
	 *  - Control: The type of control used for the field. This is one of the control methods on the Gdn_Form object.
	 *  - LabelCode: The translation code for the label. Optional.
	 *  - Description: An optional description for the field.
	 *  - Items: If the control is a list control then its items are specified here.
	 *  - Options: Additional options to be passed into the control.
	 * @param type $options Additional options to pass into the form.
	 *  - Wrap: A two item array specifying the text to wrap the form in.
	 *  - ItemWrap: A two item array specifying the text to wrap each form item in.
	 */
	public function Simple($schema, $options = array()) {
		$result = GetValueR('Wrap.0', $options, '<ul>');
		
		$itemWrap = GetValue('ItemWrap', $options, array("<li>\n  ", "\n</li>\n"));
		
		foreach ($schema as $index => $row) {
			if (is_string($row))
				$row = array('Name' => $index, 'Control' => $row);
			
			if (!isset($row['Name']))
				$row['Name'] = $index;
			if (!isset($row['Options']))
				$row['Options'] = array();
			
			$result .= $itemWrap[0];

			$labelCode = self::LabelCode($row);
			
			$description = GetValue('Description', $row, '');
			if ($description)
				$description = '<div class="Info">'.$description.'</div>';
			
			TouchValue('Control', $row, 'TextBox');

			switch (strtolower($row['Control'])) {
				case 'categorydropdown':
					$result .= $this->Label($labelCode, $row['Name'])
							  . $description
							  .$this->CategoryDropDown($row['Name'] = $row['Options']);
					break;
				case 'checkbox':
					$result .= $description
							  . $this->CheckBox($row['Name'], $this->translate($labelCode));
					break;
				case 'dropdown':
					$result .= $this->Label($labelCode, $row['Name'])
							  . $description
							  . $this->DropDown($row['Name'], $row['Items'], $row['Options']);
					break;
				case 'radiolist':
					$result .= $description
							  . $this->RadioList($row['Name'], $row['Items'], $row['Options']);
					break;
				case 'checkboxlist':
					$result .= $this->Label($labelCode, $row['Name'])
							  . $description
							  . $this->CheckBoxList($row['Name'], $row['Items'], NULL, $row['Options']);
					break;
				case 'textbox':
					$result .= $this->Label($labelCode, $row['Name'])
							  . $description
							  . $this->TextBox($row['Name'], $row['Options']);
					break;
				default:
					$result .= "Error a control type of {$row['Control']} is not supported.";
					break;
			}
			$result .= $itemWrap[1];
		}
		$result .= GetValueR('Wrap.1', $options, '</ul>');
		return $result;
	}

	/**
	 * If not saving data directly to the model, this method allows you to
	 * utilize a model's schema to validate a form's inputs regardless.
	 *
	 * ie. A sign-in form that just needs to compare data to the model and still
	 * enforce it's rules. Returns the number of errors that were recorded
	 * through validation.
	 *
	 * @return int
	 */
	public function ValidateModel() {
		$this->_Model->DefineSchema();
		if ($this->_Model->Validation->Validate($this->FormValues()) === false) $this->validationResults = $this->_Model->ValidationResults();
		return $this->ErrorCount();
	}

	/**
	 * Validates a rule on the form and adds its result to the errors collection.
	 *
	 * @param string $fieldName The name of the field to validate.
	 * @param string|array $rule The rule to validate against.
	 * @param string $customError A custom error string.
	 * @return bool Whether or not the rule succeeded.
	 *
	 * @see Gdn_Validation::ValidateRule()
	 */
	public function ValidateRule($fieldName, $rule, $customError = '') {
		$value = $this->GetFormValue($fieldName);
		$valid = Validation::ValidateRule($value, $fieldName, $rule, $customError);

		if ($valid === true)
			return true;
		else {
			$this->AddError('@'.$valid, $fieldName);
			return false;
		}
		
	}

	/**
	 * Gets the validation results in the form.
	 * @return array
	 */
	public function ValidationResults() {
		return $this->validationResults;
	}
	
	
	/**
	 * Takes an associative array of $attributes and returns them as a string of
	 * param="value" sets to be placed in an input, select, textarea, etc tag.
	 *
	 * @param array $attributes An associative array of attribute key => value pairs to be converted to a
	 *    string. A number of "reserved" keys will be ignored: 'id', 'name',
	 *    'maxlength', 'value', 'method', 'action', 'type'.
	 * @return string
	 */
	protected function _AttributesToString($attributes) {
		$reservedAttributes = array(
			'id',
			'name',
			'value',
			'method',
			'action',
			'type',
			'for',
			'multiline',
			'default',
			'textfield',
			'valuefield',
			'includenull',
			'yearrange',
			'fields',
			'inlineerrors');
		$return = '';
		
		// Build string from array
		if (is_array($attributes)) {
			foreach($attributes as $attribute => $value) {
				// Ignore reserved attributes
				if (!in_array(strtolower($attribute), $reservedAttributes)) 
					$return .= ' ' . $attribute . '="' . $value . '"';
			}
		}
		return $return;
	}

	/**
	 * Creates an ID attribute for a form input and returns it in this format: [ id="IDNAME"]
	 *
	 * @param string $fieldName The name of the field that is being converted to an ID attribute.
	 * @param array $attributes An associative array of attributes for the input. ie. maxlength, onclick,
	 *    class, etc. If $attributes contains an 'id' key, it will override the
	 *    one automatically generated by $fieldName.
	 * @return string
	 */
	protected function _IDAttribute($fieldName, $attributes) {
		// ID from attributes overrides the default.
		$id = ArrayValueI('id', $attributes, false);
		if (!$id)
			$id = $this->EscapeID($fieldName);
		
		return ' id="'.htmlspecialchars($id).'"';
	}

	/**
	 * Creates a NAME attribute for a form input and returns it in this format: [ name="NAME"]
	 *
	 * @param string $fieldName The name of the field that is being converted to a NAME attribute.
	 * @param array $attributes An associative array of attributes for the input. ie. maxlength, onclick,
	 *    class, etc. If $attributes contains a 'name' key, it will override the
	 *    one automatically generated by $fieldName.
	 * @return string
	 */
	protected function _NameAttribute($fieldName, $attributes) {
		// Name from attributes overrides the default.
		$name = $this->EscapeFieldName(ArrayValueI('name', $attributes, $fieldName));
		return (empty($name)) ? '' : ' name="' . $name . '"';
	}
	
	/**
	 * Decodes the encoded string from a php-form safe-encoded format to the
	 * format it was in when presented to the form.
	 *
	 * @param string $escapedString
	 * @return unknown
	 */
	protected function _UnescapeString($escapedString) {
		$return = str_replace('-dot-', '.', $escapedString);
		return urldecode($return);
	}

	/**
	 * Creates a VALUE attribute for a form input and returns it in this format: [ value="VALUE"]
	 *
	 * @param string $fieldName The name of the field that contains the value in $this->dataArray.
	 * @param array $attributes An associative array of attributes for the input. ie. maxlength, onclick,
	 *    class, etc. If $attributes contains a 'value' key, it will override the
	 *    one automatically generated by $fieldName.
	 * @return string
	 */
	protected function _ValueAttribute($fieldName, $attributes) {
		// Value from $attributes overrides the datasource and the postback.
		return ' value="' . $this->FormatForm(ArrayValueI('value', $attributes, $this->GetValue($fieldName))) . '"';
	}
}