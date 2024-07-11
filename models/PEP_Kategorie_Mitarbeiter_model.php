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
					kategorie_id, mitarbeiter_uid, studienjahr_kurzbz, stunden, anmerkung, insertvon
				)
				SELECT kategorie_id, mitarbeiter_uid, ?, stunden, anmerkung, ?
				FROM extension.tbl_pep_kategorie_mitarbeiter
				WHERE kategorie_id = ? 
				  AND mitarbeiter_uid IN ?
				  AND studienjahr_kurzbz = ?";

		return $this->execQuery($query, array($newStudienjahr, $insertvon, $category_id, $uids, $oldStudienjahr));
	}

	public function getStundenByMitarbeiter($mitarbeiter, $studiensemester)
	{
		$query = 'SELECT
					pk.kategorie_id,
					pk.bezeichnung,
					pk.bezeichnung_mehrsprachig,
					COALESCE(SUM(pkm.stunden), pks.default_stunden) AS stunden,
					COALESCE(pkm.mitarbeiter_uid, pk.insertvon) AS mitarbeiter_uid,
					COALESCE(pkm.studienjahr_kurzbz, pks.gueltig_ab_studienjahr) AS studiensemester_kurzbz
				FROM
					extension.tbl_pep_kategorie pk
					LEFT JOIN extension.tbl_pep_kategorie_studienjahr pks USING(kategorie_id)
					LEFT JOIN
					extension.tbl_pep_kategorie_mitarbeiter pkm
					ON pk.kategorie_id = pkm.kategorie_id
					AND pkm.studienjahr_kurzbz = (SELECT studienjahr_kurzbz FROM tbl_studiensemester WHERE tbl_studiensemester.studiensemester_kurzbz = ?)
						AND pkm.mitarbeiter_uid = ?
				WHERE
					(pks.gueltig_ab_studienjahr IN (
						SELECT tbl_studiensemester.studienjahr_kurzbz
						FROM tbl_studiensemester
						WHERE tbl_studiensemester.start <= (SELECT ende FROM tbl_studiensemester startstudiensemester WHERE startstudiensemester.studiensemester_kurzbz = ?)
					)
					AND
					(
						pks.gueltig_bis_studienjahr IN (
							SELECT tbl_studiensemester.studienjahr_kurzbz
							FROM tbl_studiensemester
							WHERE tbl_studiensemester.ende >= (SELECT start FROM tbl_studiensemester bisstudiensemester WHERE bisstudiensemester.studiensemester_kurzbz = ?)
						)
						OR
							gueltig_bis_studienjahr IS NULL
					))
					OR (pkm.mitarbeiter_uid = ? AND pkm.studienjahr_kurzbz = (SELECT studienjahr_kurzbz FROM tbl_studiensemester WHERE studiensemester_kurzbz = ?))
				GROUP BY
					pk.kategorie_id,
					pk.bezeichnung,
					pk.bezeichnung_mehrsprachig,
					pks.default_stunden,
					pkm.mitarbeiter_uid,
					pk.insertvon,
					pks.gueltig_ab_studienjahr,
					pkm.studienjahr_kurzbz;';
		return $this->execQuery($query, array($studiensemester, $mitarbeiter, $studiensemester, $studiensemester, $mitarbeiter, $studiensemester));
	}

}
