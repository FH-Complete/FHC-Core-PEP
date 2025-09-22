<?php

class PEP_LV_Entwicklung_Notiz_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_lv_entwicklung_notiz';
		$this->pk = array('notiz_id', 'pep_lv_entwicklung_id');
	}
}
