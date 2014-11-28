<?php
class DataTableDataObject extends DataExtension implements IDataTable, TemplateGlobalProvider{

	public $dtColumns, 
		$dtFields, 
		$dtDefaultOrder,
		
		$dtLimit = '10', 
		$dtDefaultDirection = 'ASC';	
	

	public function updateFrontEndFields(FieldList $fields) {
		$fields->push(new HiddenField('ID', 'ID', $this->owner->ID));
		return $fields;
	}	
		
		
	public function getDTColumns() {
		if(is_null($this->owner->dtColumns)) {
			return new UnexpectedValueException("Must override \$dtColumns property in " . get_class($this->owner));
		}
		
		return $this->owner->dtColumns;
	}
	
	public function getDTFields() {
		if(is_null($this->owner->dtFields)) {
			return new UnexpectedValueException("Must override \$dtFields property in " . get_class($this->owner));
		}
		
		return $this->owner->dtFields;
	}
	
	public function getDTDefaultOrder() {
		if(is_null($this->owner->dtDefaultOrder)) {
			return new UnexpectedValueException("Must override \$dtDefaultOrder property in " . get_class($this->owner));
		}
		return $this->owner->dtDefaultOrder;
	}
	
	public function getDTDefaultDirection() {
		if(property_exists($this->owner, 'dtDefaultDirection')) {
			return $this->owner->dtDefaultDirection;
		} else {
			return $this->dtDefaultDirection;
		}	
	}

	public function getDTLimit() {
		if(property_exists($this->owner, 'dtLimit')) {
			return $this->owner->dtLimit;
		} else {
			return $this->dtLimit;
		}
	}
	
	
	public function getDTDataList() {
		return $this->owner->get();
	}

	
	
	public static function getDTColumnsAsList() {
		$fields = singleton(Session::get('DataTableDataObject.modelClass'))->getDTColumns() ; //new ArrayList();
		$list = array();
		if(!empty($fields)) {
			foreach($fields as $field => $attr) {
				$row = new ViewableData();
				$row->Label = $attr['label'];
				$list[] = $row;
			}
		}
		return new ArrayList($list);
	}
	
	public static function get_template_global_variables() {
		return array(
			'DTColumns' => 'getDTColumnsAsList',

		);
	}
		
	
	public function getDTFieldValue($field) {
		$method = $field . 'DTRowValue';
		if(method_exists($this->owner, $method)) {
			return $this->owner->$method();
		}
		return $this->owner->$field;
	} 
	
	
	public function onAfterWrite() {
		parent::onAfterWrite();
		$this->updateLuceneIndex();
	}
	

	public function onBeforeDelete() {
		parent::onBeforeDelete();
		$this->deleteLuceneIndex();
	}
	
	
	
	public function luceneIndexName() {
		return get_class($this->owner) . '.index';	
	}
	
	
	protected function deleteLuceneIndex() {
		$index = DataTableDataObject_ZendLuceneSearch::getLuceneIndex($this->luceneIndexName());
		DataTableDataObject_ZendLuceneSearch::deleteLuceneIndex($index, $this->owner->ID);
	}
	
	protected function updateLuceneIndex() {
		
		$index = DataTableDataObject_ZendLuceneSearch::getLuceneIndex($this->luceneIndexName());
		// delete index
		DataTableDataObject_ZendLuceneSearch::deleteLuceneIndex($index, $this->owner->ID);
		$doc = new Zend_Search_Lucene_Document();
		// store job primary key to identify it in the search results
		$doc->addField(Zend_Search_Lucene_Field::Keyword('pk', $this->owner->ID));
		
		// index fields
		$fieldsToIndex = $this->owner->getDTFields();
		
		foreach($fieldsToIndex as $field) {
			if($field === 'ID') {
				continue;
			}
			
			$doc->addField(Zend_Search_Lucene_Field::UnStored($field, $this->owner->$field, 'utf-8'));
		}
		
		// add to the index
		$index->addDocument($doc);
		$index->commit();
	}
	
}





final class DataTableDataObject_ZendLuceneSearch
{
	public static function getLuceneIndex($index_name = null) {
		
		require_once 'Zend/Search/Lucene.php';

		Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('utf-8');
		Zend_Search_Lucene_Analysis_Analyzer::setDefault(
			new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive ()
		);


		if (file_exists($index_file = self::getLuceneIndexFile($index_name))) {
			try	{
				$index = Zend_Search_Lucene::open($index_file);
			} catch(Zend_Search_Lucene_Exception $e)	{
				if($e->getMessage() == 'Index is under processing now')	{
					//index is corrupt - no choice but to delete it and create it from scratch
					foreach(glob($index.'/*') as $v) {
						unlink($v);
					}
					$index = Zend_Search_Lucene::create($index_file);
				} else	{
					throw $e;
				}
			}
		} else {
			$index = Zend_Search_Lucene::create($index_file);
		}
		return $index;
	}

	public static function getLuceneIndexFile($index_name = null) {
		return TEMP_FOLDER . '/lucene-index/'.$index_name;
	}

	public static function findLuceneIndexForQuery($query, $index_name) {
		if ($query)	{
			//$test=self::getLuceneIndex($index_name);
			//print_r($test);
			$hits = self::getLuceneIndex($index_name)->find($query);
			//print_r($hits);
			$pks = array();
			foreach ($hits as $hit)	{
				$pks[] = $hit->pk;
			}

			if (empty($pks)){
				return array();
			}

			return $pks;
		} else {
			return null;
		}
	}

	public static function deleteLuceneIndex($index = null, $id = null) 	{
		if ($index === null && $id == null) {
			return;
		} else {
			foreach ($index->find('pk:'.$id) as $hit) {
				$index->delete($hit->id);
			}
		}
	}


}


