<?php

class PEP_LV_Entwicklung_Rolle_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_lv_entwicklung_rolle';
		$this->pk = array('rolle_kurzbz');
		$this->hasSequence = false;
	}
}
