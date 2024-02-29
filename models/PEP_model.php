<?php

class PEP_model extends DB_Model
{

	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_kategorie';
		$this->pk = array('kategorie_id');
		$this->hasSequence = true;
	}
	
	public function getStundenByMitarbeiter($mitarbeiter, $studiensemester)
	{
		$query = 'SELECT
					pk.kategorie_id,
					pk.bezeichnung,
					pk.bezeichnung_mehrsprachig,
					COALESCE(pkm.stunden, pk.default_stunden) AS stunden,
					COALESCE(pkm.mitarbeiter_uid, pk.insertvon) AS mitarbeiter_uid,
					COALESCE(pkm.studiensemester_kurzbz, pk.gueltig_ab_studiensemester) AS studiensemester_kurzbz
				FROM
					extension.tbl_pep_kategorie pk
					LEFT JOIN
					extension.tbl_pep_kategorie_mitarbeiter pkm
					ON pk.kategorie_id = pkm.kategorie_id
						AND pkm.studiensemester_kurzbz = ?
						AND pkm.mitarbeiter_uid = ?
				WHERE
					(pk.gueltig_ab_studiensemester IN (
						SELECT tbl_studiensemester.studiensemester_kurzbz
						FROM tbl_studiensemester
						WHERE tbl_studiensemester.start <= (SELECT start FROM tbl_studiensemester startstudiensemester WHERE startstudiensemester.studiensemester_kurzbz = ?)
					)
					AND
					(
						pk.gueltig_bis_studiensemester IN (
							SELECT tbl_studiensemester.studiensemester_kurzbz
							FROM tbl_studiensemester
							WHERE tbl_studiensemester.ende >= (SELECT start FROM tbl_studiensemester bisstudiensemester WHERE bisstudiensemester.studiensemester_kurzbz = ?)
						)
						OR
							gueltig_bis_studiensemester IS NULL
					))
					OR (pkm.mitarbeiter_uid = ? AND pkm.studiensemester_kurzbz = ?)
					ORDER BY kategorie_id';
		return $this->execQuery($query, array($studiensemester, $mitarbeiter, $studiensemester, $studiensemester, $mitarbeiter, $studiensemester));
	}
	
	public function checkDefaultStunden($studiensemester, $kategorie, $stunden)
	{
		$query = 'SELECT *
				FROM extension.tbl_pep_kategorie kategorie
				WHERE kategorie_id = ?
					AND kategorie.aktiv
					AND
					kategorie.gueltig_ab_studiensemester IN (
						SELECT tbl_studiensemester.studiensemester_kurzbz
						FROM tbl_studiensemester
						WHERE tbl_studiensemester.start <= (SELECT start FROM tbl_studiensemester startstudiensemester WHERE startstudiensemester.studiensemester_kurzbz = ?)
					)
					AND
					(
						kategorie.gueltig_bis_studiensemester IN (
							SELECT tbl_studiensemester.studiensemester_kurzbz
							FROM tbl_studiensemester
							WHERE tbl_studiensemester.ende >= (SELECT start FROM tbl_studiensemester bisstudiensemester WHERE bisstudiensemester.studiensemester_kurzbz = ?)
						)
						OR
						gueltig_bis_studiensemester IS NULL
					)
					AND default_stunden = ?
		';
		return $this->execQuery($query, array($kategorie, $studiensemester, $studiensemester, $stunden));
	}

	public function checkStunden($studiensemester, $kategorie, $mitarbeiter_uid)
	{
		$query = 'SELECT *
				FROM extension.tbl_pep_kategorie_mitarbeiter kategorie
				WHERE kategorie_id = ?
					AND studiensemester_kurzbz = ?
					AND mitarbeiter_uid = ?
		';
		return $this->execQuery($query, array($kategorie, $studiensemester, $mitarbeiter_uid));
	}
	
	public function addStundenForMitarbeiter($studiensemester, $kategorie, $stunden, $mitarbeiter_uid)
	{
		$query = 'INSERT INTO extension.tbl_pep_kategorie_mitarbeiter
    				(kategorie_id, mitarbeiter_uid, studiensemester_kurzbz, stunden)
    			VALUES (?, ?, ?, ?)';
		
		return $this->execQuery($query, array($kategorie,$mitarbeiter_uid, $studiensemester, $stunden));
	}
	
	public function updateStundenForMitarbeiter($studiensemester, $kategorie, $stunden, $mitarbeiter_uid)
	{
		$query = 'UPDATE extension.tbl_pep_kategorie_mitarbeiter
					SET stunden = ?
					WHERE mitarbeiter_uid = ? AND kategorie_id = ? AND studiensemester_kurzbz = ?';
		
		return $this->execQuery($query, array($stunden,$mitarbeiter_uid, $kategorie, $studiensemester));
	}
}
