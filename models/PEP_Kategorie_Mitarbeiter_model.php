<?php

class PEP_Kategorie_Mitarbeiter_model extends DB_Model
{


	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_kategorie_mitarbeiter';
		$this->pk = array('kategorie_mitarbeiter_id');
		$this->hasSequence = true;
	}

	public function vorruecken($oldStudienjahr, $newStudienjahr, $category_id, $uids, $insertvon = "vorgerueckt")
	{

		$query = "INSERT INTO extension.tbl_pep_kategorie_mitarbeiter
				(
					kategorie_id, mitarbeiter_uid, studienjahr_kurzbz, stunden, anmerkung, oe_kurzbz, insertvon
				)
				SELECT kategorie_id, mitarbeiter_uid, ?, stunden, anmerkung, oe_kurzbz, ?
				FROM extension.tbl_pep_kategorie_mitarbeiter
				WHERE kategorie_id = ? 
				  AND mitarbeiter_uid IN ?
				  AND studienjahr_kurzbz = ?";

		return $this->execQuery($query, array($newStudienjahr, $insertvon, $category_id, $uids, $oldStudienjahr));
	}



}
