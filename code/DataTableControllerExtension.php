<?php
class DataTableControllerExtension extends Extension {
	
	public static $allowed_actions = array('jsonlist', 'index', 'add', 'edit', 'view', 'doSave', 'doDelete');
	
	public function onAfterInit() {
		Session::set('DataTableDataObject.modelClass', $this->owner->modelClass);
	}
	
	public function index($request) {
		Requirements::css('datatable/media/css/bootstrap.min.css');
		//Requirements::css('datatable/media/css/jquery.dataTables.css');
		Requirements::css('datatable/media/css/dataTables.bootstrap.css');
		Requirements::javascript('framework/thirdparty/jquery/jquery.js');
		Requirements::javascript('datatable/media/js/jquery.dataTables.min.js');
		Requirements::javascript('datatable/media/js/dataTables.bootstrap.js');
		Requirements::javascript('datatable/js/datatable-custom.js');
		Requirements::customScript("
				$.dataTableCustom('#{$this->idSelector()}', {
			        'sAjaxSource': '" . $this->owner->ajaxSourceLink() . "',
			        'aoColumns':  " . DataTable::renderAOColumns(singleton($this->owner->modelClass)->getDTColumns()) . " 
			    });"
		);
		
		$templates = $this->possibleTemplates();
		$templates[] = 'DataTableList';
		
		return $this->owner->renderWith($templates);
	}
	
	
	public function ajaxSourceLink() {
		return $this->owner->Link('jsonlist');
	}
	
	
	public function jsonlist($request) {
		$dataTable = new DataTable($this->owner->modelClass);
		$rows = $dataTable->execute($request);
		$records = array();
		foreach($rows as $row) {
			$links = $this->owner->listLinks($row);
			
			$record = array();
			foreach($row->getDTFields() as $field) {
				array_push($record, $row->getDTFieldValue($field));
			}
			array_push($record, '<div class="btn-group1"> '. implode('', $links) . '</div>');
			array_push($records, $record);
			
		}
		$this->owner->getResponse()->addHeader("Content-Type", "application/json; charset=utf-8");
		return $dataTable->toJSON($records);
	}	
	
	public function listLinks(DataObject $row) {
		$links = array();
		if($row->canView(Member::currentUser())) {
			$links[] = "<a class='btn btn-small'  rel='tooltip' href='" . Controller::join_links($this->owner->Link(), 'view', $row->ID) . "' id='{$row->ID}'>View</a>";
		}
		if($row->canEdit(Member::currentUser())) {
			$links[] = "<a class='btn btn-small'  rel='tooltip' href='" . Controller::join_links($this->owner->Link(), 'edit', $row->ID) . "' id='{$row->ID}'>Edit</a>";
		}
		
		return $links;
	}

	public function view($request) {
		$form = $this->owner->DTForm();
		$form->makeReadonly();
		
		$templates = $this->possibleTemplates();
		$templates[] = 'DataTableList_crud';
		
		if($request->isAjax()) {
			$templates = array_filter($templates, function($var){
				return preg_match('/_(view|crud)$/', $var);
			});
			return $this->owner->renderWith($templates, array('Form' => $form));
		}
		
		
		return $this->owner->renderWith($templates, array(
			'Form' => $form,
		));
	}
	
	
	public function edit($request) {
		$templates = $this->possibleTemplates();
		$templates[] = 'DataTableList_crud';
		
		$form = $this->owner->DTForm();
		if($request->isAjax()) {
			$templates = array_filter($templates, function($var){
				return preg_match('/_(edit|crud)$/', $var);
			});
			return $this->owner->renderWith($templates, array('Form' => $form));
		}
		
		return $this->owner->renderWith($templates, array(
			'Form' => $form,
		));
	}
	
	
	public function add($request) {
		$templates = $this->possibleTemplates();
		$templates[] = 'DataTableList_crud';
		
		$form = $this->owner->DTForm();
		if($request->isAjax()) {
			$templates = array_filter($templates, function($var){
				return preg_match('/_(add|crud)$/', $var);
			});
			
			return $this->owner->renderWith($templates, array('Form' => $form));
		}
		
		return $this->owner->renderWith($templates, array(
			'Form' => $form,
		));
	}
	
	
	/**
	 * Add/Edit/Delete form
	 * 
	 * @param SS_HTTRequest $request
	 */
	public function DTForm() {
		$id = $this->owner->request->param('ID');
		$action = $this->owner->request->param('Action');
		$accessDenied = false;		
		
		$form = null;
		if(!$id) {
			$record = new $this->owner->modelClass();	
		} else {
			$record = singleton($this->owner->modelClass)->get()->byID((int) $id);
		}
		if($record==false) {
			return $this->owner->httpError(404);
		}
		
		if($action == 'add') {
			if(!$record->canCreate()) {
				$accessDenied = true;
			}
		} elseif($action == 'view') {
			if(!$record->canView()) {
				$accessDenied = true;
			}
		} elseif($action == 'edit') {
			if($id && !$record->canEdit()) {
				$accessDenied = true;
			}
		} 
		
		
		if($accessDenied) {
			return $this->owner->httpError(403);
		}
		
		$validator = $this->owner->getDTValidator(); 
		
		$fields = $record->getFrontendFields();
		$actions = new FieldList(
			$save = new FormAction('doSave', 'Save'),
			$cancel = new CancelFormAction($this->owner->Link(), 'Cancel')
		);
		
		if($record->ID) {
			$actions->push($delete = new FormAction('doDelete', 'Delete'));
			$delete->addExtraClass('btn btn-danger action_doDelete');
		}
		
		//$form = new Form($this->owner, $this->owner->modelClass.'Form', $fields, $actions, $validator);
		
		$form = BootstrapForm::create($this->owner, $this->owner->modelClass.'Form', $fields, $actions, $validator);
		
		$form->loadDataFrom($record);
		
		
		$form->setAttribute('dtvalidation', FormUtil::validatorSchemeAsJson($form, $validator));
		FormUtil::addJSValidation($form, $validator);
		
		return $form;
	}	

	
	/**
	 * Action invoked at Form
	 * 
	 * @param array $data
	 * @param Form $form
	 */
	public function doSave($data, $form) {
		if(isset($data['ID']) && !empty($data['ID'])) {
			$model = singleton($this->owner->modelClass)->get()->byID((int) $data['ID']);
		} else {
			$model = new $this->owner->modelClass($data);
		}
		
		$form->saveInto($model);
		$model->write();
		
		if($this->owner->request->isAjax() ) {
	    	return FormUtil::ajaxMessage('success', 'Saved record');
        }
		
		
		$this->owner->savedMessage($data, $form);
		$this->owner->redirect($this->owner->Link('edit/' . $model->ID));
	}	

	public function doDelete($data, $form) {
		DataObject::delete_by_id($this->owner->modelClass, (int) $data['ID']);
		
		if($this->owner->request->isAjax()) {
	    	return FormUtil::ajaxMessage('success', 'Deleted record');
        }
		$form->sessionMessage('Deleted record', 'good');
		$this->owner->redirect($this->owner->Link());
	}		
	
	
	public function idSelector() {
		return singleton('SiteTree')->generateURLSegment($this->owner->modelClass);
	}
	
	public function possibleTemplates() {
		// Add action-specific templates for inheritance chain
		$templates = array();
		$parentClass = $this->owner;
		$action = $this->owner->getAction();
		
		if($action && $action != 'index') {
			$parentClass = $this->owner;
			while($parentClass != "Controller") {
				$templates[] = strtok($parentClass,'_') . '_' . $action;
				$parentClass = get_parent_class($parentClass);
			}
		}
		// Add controller templates for inheritance chain
		$parentClass = $this->owner;
		while($parentClass != "Controller") {
			$templates[] = strtok($parentClass,'_');
			$parentClass = get_parent_class($parentClass);
		}

		$templates[] = 'Controller';

		// remove duplicates
		$templates = array_unique($templates);
		
		return $templates;
	}

	public function getDTValidator() {
		return ClassInfo::exists($this->owner->modelClass.'_Validator') ? singleton($this->owner->modelClass.'_Validator') : null;
	}
	
	public function savedMessage($data, $form) {
		
		$msg = 'Saved record';
		if(!isset($data['ID']) || empty($data['ID'])) {
			$msg .= '<br /><br /> <a class="btn btn-success" id="" name="" href="' . $this->owner->Link('add') . '">Add Another Record</a> ';
		}
		$form->sessionMessage($msg, 'good');
		
	}
	
}
