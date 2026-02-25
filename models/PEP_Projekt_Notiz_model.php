<?php

class PEP_Projekt_Notiz_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_projekt_notiz';
		$this->pk = array('notiz_id', 'pep_projects_employees_id');
	}
}
