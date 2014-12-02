<?php
interface IDataTable {
	
	public function getDTColumns();
	public function getDTFields();
	public function getDTLimit();
	public function getDTDefaultOrder();
	public function getDTDefaultDirection();
	public function getDTDataList();
	
}