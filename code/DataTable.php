<?php
/**
 * Handles the records to be displayed using datatable.js jquery plugin 
 */

class DataTable {
	protected $modelClass, 
		$modelObject,
		$dataList,
	 	$totalRecords,
	 	$totalDisplay;
	 	
	
	public function __construct(  $modelClass ) {
		$this->modelClass = $modelClass;
		$this->modelObject = singleton($this->modelClass);
		
		if($this->modelObject->hasExtension('DataTableDataObject') !== true) {
			throw new InvalidArgumentException('Extend ' . $modelClass .  ' in _config.php <code>Object::add_extension("' . $modelClass .  '", "DataTableDataObject");</code>');			
		}
	}
	
	
	/**
	 * Sets custom DataList|SQLQuery object
	 * 
	 * @param $dataList
	 */
	public function setDataList($dataList) {
		$this->dataList = $dataList;
	}
	
	/**
	 * Renders the records
	 * 
	 * @param SS_HTTPRequest $request
	 * @todo search/where clause
	 */	
	public function execute(SS_HTTPRequest $request) {
		$fields = $this->modelObject->getDTFields();
		$start = $request->getVar('iDisplayStart');
		// set limit
		$limit = ($request->getVar('iDisplayLength')) ? $request->getVar('iDisplayLength') : $this->modelObject->getDTLimit();
		if( $request->getVar('iSortCol_0') > max(array_keys($fields)) || $request->getVar('iSortCol_0') == '' || $request->getVar('iSortCol_0') < 0) {
			$sorder = $this->modelObject->getDTDefaultOrder();
			// set sort direction
			$sort = $this->modelObject->getDTDefaultDirection();
		} else {
			// set sort direction
			$sorder = $fields[$request->getVar('iSortCol_0')];
			$sort = ($request->getVar('sSortDir_0')) ? $request->getVar('sSortDir_0') : 'DESC';
		}

		if(!is_null($this->dataList)) {
			$list = $this->dataList;
		} else {
			$list = $this->modelObject->getDTDataList();
		}
			
		
		$dt = DataTable_List_Factory::set($list, $this->modelClass);
		$this->setDataList($dt);
		
		$dt = $this->_search($request, $dt);
		
		$this->totalDisplay = (int) $dt->count();
		
		$dt->limit($limit, $start)->sort($sorder, $sort);
		$result = $dt->getList();
		
		$this->totalRecords = (int) $result->count();
		return $result;
	}
	
	
	protected function _search(SS_HTTPRequest $request, DataTable_List $dt) {
		
		$fieldsToSearch = $this->modelObject->getDTFields();
		$searchString = '';
		$indexes = $searchStrings = array();
		
		if ($request->getVar('sSearch')) {
			$searchString = Convert::raw2sql($request->getVar('sSearch'));
			//$searchStrings[] = $searchString;
			$indexes = DataTableDataObject_ZendLuceneSearch::findLuceneIndexForQuery($searchString, $this->modelObject->luceneIndexName());
		}
		
		foreach($fieldsToSearch as $i => $field) {
			
			if($field === 'ID') {
				continue;
			}
			
			if ($request->getVar('sSearch_' . $i)) {
				$_searchString = Convert::raw2sql($request->getVar('sSearch_' . $i));
				$searchStrings[$field] = $_searchString;
				
				$_indexes = DataTableDataObject_ZendLuceneSearch::findLuceneIndexForQuery($searchString, $this->modelObject->luceneIndexName());
				if(!empty($_indexes)) {
					$indexes = array_merge($indexes, $_indexes);
				}
			}
		}
		
		
		
		
		if(!empty($indexes)) {
			$dt->whereAny(array('ID' => $indexes));
			//var_dump($dt->sql(), $indexes); exit;
		} else {
			$whereAny = array();
			
			if(!empty($searchString)) {
				foreach($fieldsToSearch as $field) {
					if($field === 'ID') {
						continue;
					}
					$whereAny[$field] = "%$searchString%";
				}
			}
			
			if(!empty($searchStrings)) {
				foreach($searchStrings as $field => $searchString) {
					$whereAny[$field] = "%$searchString%";
				}
				
			}
			
			if(!empty($whereAny)) {
				$dt->whereAny($whereAny, 'LIKE');
				//var_dump($dt->sql(), $whereAny); exit;
			}
		}
		
		
		return $dt;
	}
	
	
	public function toJSON($records) {
		return Convert::array2json(
			array('iTotalRecords' => $this->totalRecords, 'iTotalDisplayRecords' => $this->totalDisplay, 'aaData' => $records)
		);
	}
	

	public static function renderAOColumns($cols) {
		$columns = null;
		foreach($cols as $col) {
			$sort = '';
			if(isset($col['bSortable'])) {
				if($col['bSortable']==1){
					$sort = '"bSortable": true ';
				}else{
					$sort = '"bSortable": false';
				}
			}
			if (!$col['bVisible']) {
				if($columns) {
					$columns .= ', {"bVisible":0}';
				} else {
					$columns .= '{"bVisible":0}';
				}
			} else {
				if($columns) {
					$columns .= ', { '.$sort.' }';
				} else {
					$columns .= '{ '.$sort.' }';
				}
			}
			
			
		}
		return '['.$columns.']';
	}
	
	
	public function __toString() {
		return $this->dataList->sql();
	}
	
	
}



class DataTable_DataListExtension extends Extension {
	
	public function whereAny($filterArray, $operator = '=') {
		$SQL_Statements = array();
		foreach($filterArray as $field => $value) {
			if(is_array($value)) {
				$customQuery = 'IN (\''.implode('\',\'',Convert::raw2sql($value)).'\')';
			} else {
				$customQuery = $operator . ' \''.Convert::raw2sql($value).'\'';
			}
				
			if(stristr($field,':')) {
				$fieldArgs = explode(':',$field);
				$field = array_shift($fieldArgs);
				foreach($fieldArgs as $fieldArg){
					$comparisor = $this->owner->applyFilterContext($field, $fieldArg, $value);
				}
			} else {
				if($field == 'ID') {
					$field = sprintf('"%s"."ID"', ClassInfo::baseDataClass($this->owner->dataClass));
				} else {
					$field = '"' . Convert::raw2sql($field) . '"';
				}
	
				$SQL_Statements[] = $field . ' ' . $customQuery;
			}
		}
		if(!count($SQL_Statements)) return $this;
	
		return $this->_alterDataQuery(function($query) use ($SQL_Statements){
			$query->whereAny($SQL_Statements);
		});
	}
	
	
	private function _alterDataQuery($callback) {
		if ($this->owner->inAlterDataQueryCall) {
			$res = $callback($this->dataQuery, $this);
			if ($res) $this->dataQuery = $res;

			return $this->owner;
		}
		else {
			$this->owner->inAlterDataQueryCall = true;

			try {
				$res = $callback($this->owner->dataQuery, $this);
				if ($res) $this->owner->dataQuery = $res;
			}
			catch (Exception $e) {
				$this->owner->inAlterDataQueryCall = false;
				throw $e;
			}

			$this->owner->inAlterDataQueryCall = false;
			return $this->owner;
		}
     }
	
	
	
}





abstract class DataTable_List {
	protected $list, $modelClass;
	

	public function __construct($list, $modelClass = null) {
		$this->list = $list;
		$this->modelClass = $modelClass;
	}
	
	
	public abstract function limit($limit, $start);
	public abstract function sort($sorder, $sort);
	public abstract function whereAny($whereAnyFilters, $operator = '=');
	
	public function sql() {
		return $this->list->sql();
	}
	
	public function count() {
		return $this->list->count();
	}
	
	public function getList() {
		return $this->list;
	}
}


class DataTable_DataList extends DataTable_List {
	
	public function __construct(DataList $list, $modelClass = null) {
		parent::__construct($list, $modelClass);
		$this->list = $list;
	}
	
	public function limit($limit, $start) {
		$this->list->limit($limit, $start);
		return $this;
	}
	
	public function sort($sorder, $sort) {
		$this->list->sort($sorder, $sort);
		return $this;
	}

	
	public function whereAny($whereAnyFilters, $operator = '=') {
		$this->list->whereAny($whereAnyFilters, $operator);
	}
	
}


class DataTable_SQLQuery extends DataTable_List {
	public function __construct(SQLQuery $list, $modelClass = null) {
		parent::__construct($list, $modelClass);
		$this->list = $list;
	}
	
	public function limit($limit, $start) {
		$this->list->setLimit($limit, $start);
		return $this;
	}
	
	public function sort($sorder, $sort) {
		$this->list->setOrderBy($sorder, $sort);
		return $this;
	}
	
	
	public function whereAny($whereFilters, $operator = '=') {
		$this->list->setWhereAny($whereFilters, $operator);
	}
	
	
	public function getList() {
		$newList = array();
		foreach($this->list->execute() as $row) {
			$class = $this->modelClass;
			$newList[] = new $class($row);
		}
		return new ArrayList($newList);
	}
	
	
}


class DataTable_List_Factory {
	private function __construct() {}
	public static function set($list, $modelClass = null) {
		if($list instanceof DataList) {
			return new DataTable_DataList($list, $modelClass);
		} if($list instanceof SQLQuery) {
			return new DataTable_SQLQuery($list, $modelClass);
		} else {
			throw new InvalidArgumentException('Invalid list type');
		}
	}
	
	
	
	
}


/**
 * 
 * Custom validator designed to work w/ jquery.validate plugin
 *
 */
class DataTable_FormValidator extends RequiredFields {
	
	protected static $schemeKeys = array('rules', 'messages');
	
	
	/**
	 
// validate signup form on keyup and submit
 * 
 * 
 * 
	$("#Form_DynamicGroupForm").validate({
		rules:{
			Type:"required",
			Name:"required"
		}
	});
	
	$("#signupForm").validate({
		rules: {
			firstname: "required",
			lastname: "required",
			username: {
				required: true,
				minlength: 2
			},
			password: {
				required: true,
				minlength: 5
			},
			confirm_password: {
				required: true,
				minlength: 5,
				equalTo: "#password"
			},
			email: {
				required: true,
				email: true
			},
			topic: {
				required: "#newsletter:checked",
				minlength: 2
			},
			agree: "required"
		},
		messages: {
			firstname: "Please enter your firstname",
			lastname: "Please enter your lastname",
			username: {
				required: "Please enter a username",
				minlength: "Your username must consist of at least 2 characters"
			},
			password: {
				required: "Please provide a password",
				minlength: "Your password must be at least 5 characters long"
			},
			confirm_password: {
				required: "Please provide a password",
				minlength: "Your password must be at least 5 characters long",
				equalTo: "Please enter the same password as above"
			},
			email: "Please enter a valid email address",
			agree: "Please accept our policy"
		}
	});
	 */
	protected $validationScheme = array(
	);
	
	
	
	
	public function __construct() {
		$required = func_get_args();
		if(isset($required[0]) && is_array($required[0])) {
			$required = $required[0];
		}
		$required = array_merge($required, $this->requiredFields());
	
		parent::__construct($required);
	}

	
	public function setValidationScheme($scheme) {
		$this->validationScheme = $scheme;
	}
	
	
	protected function requiredFields() {
		$fields = array();
		$scheme = $this->getValidationScheme();
		
		if(!empty($scheme)) {
			$rules = $scheme['rules'];
			foreach($rules as $field => $rule) {
				if(!is_array($rule)) {
					if($rule === 'required') {
						$fields[] = $field;
					}
				} else {
					if(isset($rule['required']) && $rule['required'] === true) {
						$fields[] = $field;
					}
				}
			}
		}
		return $fields;
	}
	
	public function getValidationScheme() {
		//var_dump($this->form->getName());
		
		return $this->validationScheme;
	}
	

	/**
	 * Changes scheme fields appended w/ form id name
	 * 
	 * eg. Form_DynamicGroupForm_$field
	 * 
	 * @param Form $form
	 * @param array $scheme
	 */
	public static function formValidationScheme(Form $form, $scheme) {
		
		$newScheme = array();
		
		foreach(static::$schemeKeys as $key) {
			if(!isset($scheme[$key])) {
				continue;
			}
			
			$result = array();
			array_walk($scheme[$key], function($item, $key) use ($form, &$result) {
				$key = $form->FormName() . '_' . $key;
				$result[$key] = $item;
			});
			$newScheme[$key] = $result;
		}
		return $newScheme;
		
	}
	
	
	
}



