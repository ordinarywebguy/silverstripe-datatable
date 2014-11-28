<?php
/**
 * Silverstripe DataTable
 * 
 * This module for silverstripe project w/c requires for customize dashboard layout.
 * 
 * Features:
 * - Enables creation of CRUD stuff using DataTable::getFrontendFields
 * - It uses datatable.js as the primary presentation of listed data. 
 * - Form validation using jquery.validate.js
 * - Zend_Lucene for datatable search
 * 
 * @author ordinarywebguy 
 * @link http://www.ordinarywebguy.com
 */
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/thirdparty/');
Object::add_extension('DataList', 'DataTable_DataListExtension');