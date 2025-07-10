<?php

class PEP_LV_Entwicklung_Status_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_lv_entwicklung_status';
		$this->pk = array('status_kurzbz');
		$this->hasSequence = false;
	}
}
