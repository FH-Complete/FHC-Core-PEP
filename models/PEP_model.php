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

	public function getCategoryData($mitarbeiter_uids, $category_id, $studienjahr)
	{
		$query = '
				SELECT
				tbl_mitarbeiter.mitarbeiter_uid,
				tbl_person.person_id,
				tbl_person.vorname,
				tbl_person.nachname,
				COALESCE(pkm.stunden, default_pk.default_stunden) AS stunden,
				pkm.anmerkung
				FROM tbl_mitarbeiter
					JOIN tbl_benutzer ON tbl_mitarbeiter.mitarbeiter_uid = tbl_benutzer.uid
					JOIN tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
					LEFT JOIN extension.tbl_pep_kategorie_mitarbeiter pkm ON tbl_mitarbeiter.mitarbeiter_uid = pkm.mitarbeiter_uid AND pkm.studienjahr_kurzbz = ? AND pkm.kategorie_id = ?
					LEFT JOIN extension.tbl_pep_kategorie pk ON pk.kategorie_id = pkm.kategorie_id
					LEFT JOIN extension.tbl_pep_kategorie default_pk ON
						default_pk.kategorie_id = ? AND (
							default_pk.gueltig_ab_studienjahr IN (
							SELECT tbl_studienjahr.studienjahr_kurzbz
							FROM tbl_studiensemester
									 JOIN tbl_studienjahr ON tbl_studiensemester.studienjahr_kurzbz = tbl_studienjahr.studienjahr_kurzbz
							WHERE start <= (SELECT start FROM tbl_studiensemester WHERE tbl_studiensemester.studienjahr_kurzbz = ? ORDER BY start LIMIT 1)
						)
							AND
						(default_pk.gueltig_bis_studienjahr IN (
							SELECT tbl_studienjahr.studienjahr_kurzbz
							FROM tbl_studiensemester
									 JOIN tbl_studienjahr ON tbl_studiensemester.studienjahr_kurzbz = tbl_studienjahr.studienjahr_kurzbz
							WHERE ende >= (SELECT start FROM tbl_studiensemester WHERE tbl_studiensemester.studienjahr_kurzbz = ? ORDER BY start LIMIT 1)
						)
							OR
						 default_pk.gueltig_bis_studienjahr IS NULL
							)
						)
				WHERE
					tbl_mitarbeiter.mitarbeiter_uid IN ?
		';
		return $this->execQuery($query, array($studienjahr, $category_id, $category_id, $studienjahr, $studienjahr, $mitarbeiter_uids));

	}
	
	public function getStundenByMitarbeiter($mitarbeiter, $studiensemester)
	{
		$query = 'SELECT
					pk.kategorie_id,
					pk.bezeichnung,
					pk.bezeichnung_mehrsprachig,
					COALESCE(pkm.stunden, pk.default_stunden) AS stunden,
					COALESCE(pkm.mitarbeiter_uid, pk.insertvon) AS mitarbeiter_uid,
					COALESCE(pkm.studienjahr_kurzbz, pk.gueltig_ab_studienjahr) AS studiensemester_kurzbz
				FROM
					extension.tbl_pep_kategorie pk
					LEFT JOIN
					extension.tbl_pep_kategorie_mitarbeiter pkm
					ON pk.kategorie_id = pkm.kategorie_id
					AND pkm.studienjahr_kurzbz = (SELECT studienjahr_kurzbz FROM tbl_studiensemester WHERE tbl_studiensemester.studiensemester_kurzbz = ?)
						AND pkm.mitarbeiter_uid = ?
				WHERE
					(pk.gueltig_ab_studienjahr IN (
						SELECT tbl_studiensemester.studienjahr_kurzbz
						FROM tbl_studiensemester
						WHERE tbl_studiensemester.start <= (SELECT start FROM tbl_studiensemester startstudiensemester WHERE startstudiensemester.studiensemester_kurzbz = ? ORDER by start LIMIT 1)
					)
					AND
					(
						pk.gueltig_bis_studienjahr IN (
							SELECT tbl_studiensemester.studienjahr_kurzbz
							FROM tbl_studiensemester
							WHERE tbl_studiensemester.ende >= (SELECT start FROM tbl_studiensemester bisstudiensemester WHERE bisstudiensemester.studiensemester_kurzbz = ? ORDER by start LIMIT 1)
						)
						OR
							gueltig_bis_studienjahr IS NULL
					))
					OR (pkm.mitarbeiter_uid = ? AND pkm.studienjahr_kurzbz = (SELECT studienjahr_kurzbz FROM tbl_studiensemester WHERE studiensemester_kurzbz = ?))
					ORDER BY kategorie_id';
		return $this->execQuery($query, array($studiensemester, $mitarbeiter, $studiensemester, $studiensemester, $mitarbeiter, $studiensemester));
	}
	
	public function checkDefaultStunden($studienjahr, $kategorie, $stunden)
	{
		$query = 'SELECT *
				FROM extension.tbl_pep_kategorie kategorie
				WHERE kategorie_id = ?
					AND kategorie.aktiv
					AND
					kategorie.gueltig_ab_studienjahr IN (
						SELECT tbl_studiensemester.studienjahr_kurzbz
						FROM tbl_studiensemester
						WHERE tbl_studiensemester.start <= (SELECT start FROM tbl_studiensemester startstudiensemester WHERE startstudiensemester.studienjahr_kurzbz = ? ORDER BY start LIMIT 1)
					)
					AND
					(
						kategorie.gueltig_bis_studienjahr IN (
							SELECT tbl_studiensemester.studienjahr_kurzbz
							FROM tbl_studiensemester
							WHERE tbl_studiensemester.ende >= (SELECT start FROM tbl_studiensemester bisstudiensemester WHERE bisstudiensemester.studienjahr_kurzbz = ? ORDER BY start LIMIT 1)
						)
						OR
						gueltig_bis_studienjahr IS NULL
					)
					AND default_stunden = ?
		';
		return $this->execQuery($query, array($kategorie, $studienjahr, $studienjahr, $stunden));
	}

	public function checkStunden($studienjahr, $kategorie, $mitarbeiter_uid)
	{
		$query = 'SELECT *
				FROM extension.tbl_pep_kategorie_mitarbeiter kategorie
				WHERE kategorie_id = ?
					AND studienjahr_kurzbz = ?
					AND mitarbeiter_uid = ?
		';
		return $this->execQuery($query, array($kategorie, $studienjahr, $mitarbeiter_uid));
	}
	
	public function addStundenForMitarbeiter($studiensemester, $kategorie, $stunden, $mitarbeiter_uid, $anmerkung)
	{
		$query = 'INSERT INTO extension.tbl_pep_kategorie_mitarbeiter
    				(kategorie_id, mitarbeiter_uid, studienjahr_kurzbz, stunden, anmerkung)
    			VALUES (?, ?, ?, ?, ?)';
		
		return $this->execQuery($query, array($kategorie,$mitarbeiter_uid, $studiensemester, $stunden, $anmerkung));
	}
	
	public function updateStundenForMitarbeiter($studienjahr, $kategorie, $stunden, $mitarbeiter_uid, $anmerkung)
	{
		$query = 'UPDATE extension.tbl_pep_kategorie_mitarbeiter
					SET stunden = ?, 
						anmerkung = ?,
						updateamum = now(),
						updatevon = ?
					WHERE mitarbeiter_uid = ? AND kategorie_id = ? AND studienjahr_kurzbz = ?';

		return $this->execQuery($query, array($stunden, $anmerkung, getAuthUID(), $mitarbeiter_uid, $kategorie, $studienjahr));
	}
}
