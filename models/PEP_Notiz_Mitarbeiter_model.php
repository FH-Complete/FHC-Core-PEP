<?php

class PEP_Notiz_Mitarbeiter_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_notiz_mitarbeiter';
		$this->pk = array('notiz_id', 'mitarbeiter_uid');
	}

}
