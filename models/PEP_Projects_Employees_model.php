<?php

class PEP_Projects_Employees_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_projects_employees';
		$this->pk = array('kategorie_mitarbeiter_id');
		$this->hasSequence = true;
	}

}
