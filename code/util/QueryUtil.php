<?php
class QueryUtil {
	
	
	public static function SQLQueryToArrayList(SQLQuery $query){
		$newList = array();
		foreach($query->execute() as $row) {
			$newList[] = $row;
		}
		return new ArrayList($newList);
		
	}
	
	
}