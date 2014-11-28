<?php
class FormUtil {
	
	/**
	 * Adds custom js validation requirements
	 * 
	 * @param Form $form
	 */
	public static function addJSValidation(Form $form, $validator = null) {
		Requirements::javascript('datatable/js/jquery.validate.min.js');
			
		if(is_null($validator)) {	
			Requirements::customScript('jQuery(function($) { 
					$("#' . $form->FormName() . '").validate(); 
				});'
			);
		} elseif($validator instanceof DataTable_FormValidator) {
			$options = static::validatorSchemeAsJson($form, $validator);
			Requirements::customScript('jQuery(function($) {
					$("#' . $form->FormName() . '").validate('.$options.');
				});'
			);
		} else {
			throw new InvalidArgumentException('Validator must be an instance of DataTable_FormValidator');
		}
	}
	
	public static function validatorSchemeAsJson(Form $form, DataTable_FormValidator $validator) {
		require_once 'Zend/Json.php';
		
		if(!$validator) {
			return;
		}
		
		
		$scheme = $validator->getValidationScheme();
		//$scheme = DataTable_FormValidator::formValidationScheme($form, $scheme);
		$options = Zend_Json::encode(
				$scheme,
				false,
				array('enableJsonExprFinder' => true)
		);
		
		
		return $options;
	}
	
	public static function ajaxMessage($status, $msg, $data = array()) {
		return json_encode(array_merge(array(
			'status' => $status,
			'message' => $msg
		), $data));
	}
	
	/**
	 * 
	 * 
	 */
	public static function requireTinyMCE() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-entwine/dist/jquery.entwine-dist.js');
		Requirements::javascript(FRAMEWORK_ADMIN_DIR . '/javascript/ssui.core.js');
		Requirements::javascript(FRAMEWORK_DIR ."/javascript/HtmlEditorField.js");
	}
	
	public static function detailsTab(FieldList $fields) {
		$tabs = new TabSet('DetailsTabSet',	$tabDetails = new Tab('Details'));

		foreach($fields as $field) {
			$fields->removeByName($field->getName());
			$tabDetails->push($field);
		}
		return $tabs;
	}
	
	public static function uploader($FILES, $name) {
		if(!isset($FILES['name'][$name])) return false;
		$fileClass = File::get_class_for_file_extension(pathinfo($FILES['name'][$name], PATHINFO_EXTENSION));
		$upload = new Upload();
		$file = new $fileClass();
		
		$tmpFile = array(
			'name' => $FILES['name'][$name],
			'type' => $FILES['type'][$name],
			'tmp_name' => $FILES['tmp_name'][$name],
			'error' => $FILES['error'][$name],
			'size' => $FILES['size'][$name],
		); 
		
		$upload->loadIntoFile($tmpFile, $file, 'Uploads');
		//if($upload->isError()) return false;
		return $upload;
	}

	
	
	
}