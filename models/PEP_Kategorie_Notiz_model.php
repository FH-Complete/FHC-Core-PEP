<?php

class PEP_Kategorie_Notiz_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_kategorie_notiz';
		$this->pk = array('notiz_id', 'kategorie_mitarbeiter_id');
	}
}
