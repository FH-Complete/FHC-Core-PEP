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
				ROW_NUMBER() OVER () AS row_index,
				kategorie_mitarbeiter_id,
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
					LEFT JOIN extension.tbl_pep_kategorie_studienjahr default_pk ON
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
				ORDER BY vorname, nachname
		';

		return $this->execQuery($query, array($studienjahr, $category_id, $category_id, $studienjahr, $studienjahr, $mitarbeiter_uids));

	}
	
	public function getStundenByMitarbeiter($mitarbeiter, $studiensemester)
	{
		$query = 'SELECT
					pk.kategorie_id,
					pk.bezeichnung,
					pk.bezeichnung_mehrsprachig,
					COALESCE(pkm.stunden, pks.default_stunden) AS stunden,
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
						WHERE tbl_studiensemester.start <= (SELECT start FROM tbl_studiensemester startstudiensemester WHERE startstudiensemester.studiensemester_kurzbz = ? ORDER by start LIMIT 1)
					)
					AND
					(
						pks.gueltig_bis_studienjahr IN (
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

	public function getMitarbeiter($org, $studiensemester, $recursive)
	{
		$query = "SELECT
					DISTINCT (ma.uid)
					lektor,
					vorname,
					nachname,
					ma.uid
				FROM campus.vw_mitarbeiter ma
				JOIN hr.tbl_dienstverhaeltnis dv ON ma.uid = dv.mitarbeiter_uid
				JOIN hr.tbl_vertragsart vertragsart USING(vertragsart_kurzbz)
				JOIN hr.tbl_vertragsbestandteil bestandteil ON dv.dienstverhaeltnis_id = bestandteil.dienstverhaeltnis_id
				JOIN hr.tbl_vertragsbestandteil_funktion vbstfunktion ON bestandteil.vertragsbestandteil_id = vbstfunktion.vertragsbestandteil_id
				LEFT JOIN public.tbl_benutzerfunktion funktion ON vbstfunktion.benutzerfunktion_id = funktion.benutzerfunktion_id
				WHERE funktion_kurzbz IN('kstzuordnung', 'oezuordnung')
				AND (
					bestandteil.von <= (
						SELECT MAX(ende)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR bestandteil.von IS NULL
				)
				AND
				(
					bestandteil.bis >= (
						SELECT MIN(start)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR bestandteil.bis IS NULL
				)
		";

		if ($recursive)
		{
			$additionalQuery = "
				AND funktion.oe_kurzbz IN
				(
					WITH RECURSIVE oes(oe_kurzbz, oe_parent_kurzbz) as
					(
						SELECT oe_kurzbz, oe_parent_kurzbz FROM public.tbl_organisationseinheit
						WHERE oe_kurzbz = ?
						UNION ALL
						SELECT o.oe_kurzbz, o.oe_parent_kurzbz FROM public.tbl_organisationseinheit o, oes
						WHERE o.oe_parent_kurzbz=oes.oe_kurzbz
					)
					SELECT oe_kurzbz
					FROM oes
					GROUP BY oe_kurzbz
				)
			";
		}
		else
			$additionalQuery = " AND funktion.oe_kurzbz = ?";

		$query .= $additionalQuery;
		$query .= " ORDER by vorname, nachname";
		return  $this->execReadOnlyQuery($query, array($studiensemester, $studiensemester, $org));
	}

	public function getMitarbeiterLehre($org, $studiensemester, $recursive, $mitarbeiter_uids)
	{
		/*TODO SQL optimieren*/

		$query = "
			SELECT
			(
				WITH RECURSIVE meine_oes(oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz) as
				(
					SELECT
						oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz
					FROM
						public.tbl_organisationseinheit
					WHERE
						oe_kurzbz = tbl_lehrveranstaltung.oe_kurzbz
						AND aktiv = true
					UNION ALL
					SELECT
						o.oe_kurzbz, o.oe_parent_kurzbz, o.organisationseinheittyp_kurzbz
					FROM
						public.tbl_organisationseinheit o, meine_oes
					WHERE
						o.oe_kurzbz=meine_oes.oe_parent_kurzbz
						AND aktiv = true
				)
				SELECT
					tbl_organisationseinheit.bezeichnung
				FROM
					meine_oes
					JOIN public.tbl_organisationseinheit USING(oe_kurzbz)
			    WHERE (
					(
						(meine_oes.organisationseinheittyp_kurzbz)::TEXT = 'Fakultaet'::TEXT)
						OR ((meine_oes.oe_kurzbz)::TEXT = 'lehrgang'::TEXT)
						OR ((meine_oes.oe_kurzbz)::TEXT = 'Spezialfaelle'::TEXT)
			    )
				LIMIT 1
			) as Fakultaet,
			(
				SELECT
					COALESCE(
						string_agg(
							tbl_lehreinheitgruppe.gruppe_kurzbz, ', '
						) FILTER (WHERE NOT direktinskription),
						string_agg(
							COALESCE(
								upper(tbl_studiengang.typ::varchar(1) ||
										tbl_studiengang.kurzbz) ||
										COALESCE(tbl_lehreinheitgruppe.semester::varchar, '') ||
										COALESCE(tbl_lehreinheitgruppe.verband::varchar, ''),
										tbl_lehreinheitgruppe.gruppe), ', '
							)
						) AS gruppen
				FROM
					lehre.tbl_lehreinheitgruppe
					LEFT JOIN public.tbl_studiengang USING(studiengang_kz)
					LEFT JOIN public.tbl_gruppe USING(gruppe_kurzbz)
				WHERE tbl_lehreinheitgruppe.lehreinheit_id = tbl_lehreinheit.lehreinheit_id) as gruppe,
			(
				SELECT upper(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) as stg_kuerzel
				FROM lehre.tbl_lehrveranstaltung
					JOIN tbl_studiengang ON tbl_lehrveranstaltung.studiengang_kz = tbl_studiengang.studiengang_kz
				WHERE tbl_lehrveranstaltung.lehrveranstaltung_id = tbl_lehreinheit.lehrveranstaltung_id
			) as stg_kuerzel,
			(
				SELECT tbl_studiengang.email
				FROM lehre.tbl_lehrveranstaltung
					JOIN tbl_studiengang ON tbl_lehrveranstaltung.studiengang_kz = tbl_studiengang.studiengang_kz
				WHERE tbl_lehrveranstaltung.lehrveranstaltung_id = tbl_lehreinheit.lehrveranstaltung_id
			) as stg_email,
			tbl_lehreinheitmitarbeiter.mitarbeiter_uid as uid,
			TO_CHAR(tbl_lehreinheitmitarbeiter.insertamum, 'DD.MM.YYYY HH24:mm:ss') as insertamum,
			TO_CHAR(tbl_lehreinheitmitarbeiter.updateamum, 'DD.MM.YYYY HH24:mm:ss') as updateamum,
			tbl_lehreinheitmitarbeiter.anmerkung,
			tbl_mitarbeiter.kurzbz as lektor,
			tbl_person.vorname as lektor_vorname,
			tbl_person.nachname as lektor_nachname,
			tbl_lehreinheit.lehreinheit_id,
			tbl_lehreinheit.studiensemester_kurzbz,
			tbl_lehreinheitmitarbeiter.stundensatz as le_stundensatz,
			tbl_lehreinheitmitarbeiter.semesterstunden AS lektor_stunden,
			tbl_lehreinheit.lehrform_kurzbz,
			lv_org.oe_kurzbz,
			tbl_lehrveranstaltung.bezeichnung as lv_bezeichnung,
			tbl_lehrveranstaltung.lehrveranstaltung_id as lv_id,
			lv_org.bezeichnung as lv_oe,
			(
				SELECT
					NOT EXISTS(
						SELECT 1
						FROM
							lehre.tbl_stundenplandev as stpl
								JOIN lehre.tbl_lehreinheit le USING(lehreinheit_id)
								JOIN lehre.tbl_lehrveranstaltung as lehrfach ON(le.lehrfach_id = lehrfach.lehrveranstaltung_id)
						WHERE stpl.lehreinheit_id = tbl_lehreinheit.lehreinheit_id
						  AND stpl.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid
					)
					AND
					NOT EXISTS(
						SELECT 1
						FROM
							lehre.tbl_stundenplan as stpl
								JOIN lehre.tbl_lehreinheit le USING(lehreinheit_id)
								JOIN lehre.tbl_lehrveranstaltung as lehrfach ON(le.lehrfach_id = lehrfach.lehrveranstaltung_id)
						WHERE stpl.lehreinheit_id = tbl_lehreinheit.lehreinheit_id
						  AND stpl.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid
					)
					AND
					NOT EXISTS(
						SELECT 1
						FROM
							lehre.tbl_lehreinheitmitarbeiter lema
						WHERE lema.lehreinheit_id = tbl_lehreinheit.lehreinheit_id
						  AND lema.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid
							AND vertrag_id is not null
					)
					 AND (
						SELECT now() <= start
						FROM tbl_studiensemester
						WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz               
					)
			) as editable, 
			(tbl_benutzer.uid || '@".DOMAIN."') AS email,
			CASE WHEN (
				SELECT
					va.vertragsart_kurzbz
				FROM hr.tbl_dienstverhaeltnis dv
						 JOIN hr.tbl_vertragsart va USING (vertragsart_kurzbz)
				WHERE (dv.von <= ( SELECT ende
								   FROM public.tbl_studiensemester
								   WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz) OR dv.von IS NULL)
				  AND (dv.bis >= ( SELECT start
								   FROM public.tbl_studiensemester
								   WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz) OR dv.bis IS NULL)
				  AND dv.mitarbeiter_uid = tbl_lehreinheitmitarbeiter.mitarbeiter_uid
				ORDER BY von DESC LIMIT 1
			) = 'echterdv' THEN (
				SELECT faktor
				FROM lehre.tbl_lehrveranstaltung_faktor
					LEFT JOIN public.tbl_studiensemester vonstsem
						ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_von = vonstsem.studiensemester_kurzbz
					LEFT JOIN public.tbl_studiensemester bisstem
						ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_bis = bisstem.studiensemester_kurzbz
				WHERE tbl_lehrveranstaltung_faktor.lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id
					AND (
						bisstem.ende >= (
							SELECT start
							FROM public.tbl_studiensemester
							WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz
						)
						OR bisstem.ende IS NULL
					)
				  AND
					(vonstsem.start <= (
						SELECT ende
						FROM public.tbl_studiensemester
						WHERE studiensemester_kurzbz =  tbl_lehreinheit.studiensemester_kurzbz
					))
				ORDER BY vonstsem.start DESC
				LIMIT 1
			) ELSE 0 END as faktor,
			CASE WHEN (
				SELECT
					va.vertragsart_kurzbz
				FROM hr.tbl_dienstverhaeltnis dv
						 JOIN hr.tbl_vertragsart va USING (vertragsart_kurzbz)
				WHERE (dv.von <= ( SELECT ende
								   FROM public.tbl_studiensemester
								   WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz) OR dv.von IS NULL)
				  AND (dv.bis >= ( SELECT start
								   FROM public.tbl_studiensemester
								   WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz) OR dv.bis IS NULL)
				  AND dv.mitarbeiter_uid = tbl_lehreinheitmitarbeiter.mitarbeiter_uid
				ORDER BY von DESC LIMIT 1
			) = 'echterdv' THEN (COALESCE(
				(
					 SELECT
						  tbl_lehreinheitmitarbeiter.semesterstunden * COALESCE(tbl_lehrveranstaltung_faktor.faktor, 1)
					 FROM lehre.tbl_lehrveranstaltung_faktor
								 LEFT JOIN public.tbl_studiensemester vonstsem
											  ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_von = vonstsem.studiensemester_kurzbz
								 LEFT JOIN public.tbl_studiensemester bisstem
											  ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_bis = bisstem.studiensemester_kurzbz
					 WHERE tbl_lehrveranstaltung_faktor.lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id
						AND (
						  bisstem.ende >= (
								SELECT start
								FROM public.tbl_studiensemester
								WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz
						  )
								OR bisstem.ende IS NULL
						  )
						AND
						  (vonstsem.start <= (
								SELECT ende
								FROM public.tbl_studiensemester
								WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz
						  )
								OR vonstsem.start IS NULL
								)
					 ORDER BY vonstsem.start DESC
					 LIMIT 1
				), tbl_lehreinheitmitarbeiter.semesterstunden
			)) ELSE tbl_lehreinheitmitarbeiter.semesterstunden END AS faktorstunden
		FROM
			lehre.tbl_lehreinheit
			JOIN lehre.tbl_lehrveranstaltung USING (lehrveranstaltung_id)
			JOIN lehre.tbl_lehrveranstaltung lehrfach ON tbl_lehreinheit.lehrfach_id = lehrfach.lehrveranstaltung_id
			JOIN lehre.tbl_lehreinheitmitarbeiter USING (lehreinheit_id)
			JOIN tbl_mitarbeiter USING (mitarbeiter_uid)
			LEFT JOIN lehre.tbl_lehreinheitgruppe USING (lehreinheit_id)
			LEFT JOIN tbl_studiengang ON tbl_lehreinheitgruppe.studiengang_kz = tbl_studiengang.studiengang_kz
			JOIN tbl_benutzer ON tbl_mitarbeiter.mitarbeiter_uid = tbl_benutzer.uid
			JOIN tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
			JOIN tbl_organisationseinheit lv_org ON lv_org.oe_kurzbz = lehrfach.oe_kurzbz
		WHERE
			tbl_lehreinheit.studiensemester_kurzbz IN ?
		AND (
			lv_org.oe_kurzbz";

		if ($recursive)
		{
			$additionalQuery = "
				IN (
					WITH RECURSIVE oes(oe_kurzbz, oe_parent_kurzbz) AS (
						SELECT oe_kurzbz,
								oe_parent_kurzbz
						FROM PUBLIC.tbl_organisationseinheit
						WHERE oe_kurzbz = ?
	
						UNION ALL
	
						SELECT o.oe_kurzbz,
								o.oe_parent_kurzbz
						FROM PUBLIC.tbl_organisationseinheit o,
							oes
						WHERE o.oe_parent_kurzbz = oes.oe_kurzbz
					)
					SELECT oe_kurzbz
					FROM oes
					GROUP BY oe_kurzbz
				)
			";
		}
		else
			$additionalQuery = " = ?";

		$query .= $additionalQuery;

		$query .= "
			OR tbl_lehreinheitmitarbeiter.mitarbeiter_uid IN ?
					)
			GROUP BY
				tbl_lehreinheitmitarbeiter.mitarbeiter_uid,
				tbl_mitarbeiter.kurzbz,
				tbl_mitarbeiter.mitarbeiter_uid,
				tbl_benutzer.uid,
				tbl_person.vorname,
				tbl_person.nachname,
				tbl_lehreinheit.lehreinheit_id,
				tbl_lehreinheit.lehrveranstaltung_id,
				tbl_lehrveranstaltung.bezeichnung,
				tbl_lehrveranstaltung.oe_kurzbz,
				tbl_lehrveranstaltung.lehrveranstaltung_id,
				tbl_lehreinheitgruppe.semester,
				tbl_lehreinheit.studiensemester_kurzbz,
				tbl_lehreinheitmitarbeiter.semesterstunden,
				tbl_lehreinheitmitarbeiter.stundensatz,
				tbl_lehreinheit.lehrform_kurzbz,
				lv_org.oe_kurzbz,
				tbl_lehreinheitmitarbeiter.insertamum,
				tbl_lehreinheitmitarbeiter.updateamum,
				tbl_lehreinheitmitarbeiter.anmerkung
			ORDER BY tbl_lehreinheit.lehrveranstaltung_id
		";

		return $this->execReadOnlyQuery($query, array($studiensemester, $org, $mitarbeiter_uids));
	}

	public function getLehrauftraegeStundenWithFaktor($uid, $studiensemester)
	{
		$query = "SELECT COALESCE(SUM(
							tbl_lehreinheitmitarbeiter.semesterstunden * COALESCE(tbl_lehrveranstaltung_faktor.faktor, 1)
					), 0)as stunden
				FROM lehre.tbl_lehreinheitmitarbeiter
					JOIN lehre.tbl_lehreinheit ON tbl_lehreinheitmitarbeiter.lehreinheit_id = tbl_lehreinheit.lehreinheit_id
					JOIN lehre.tbl_lehrveranstaltung ON tbl_lehreinheit.lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id
					LEFT JOIN (
						SELECT DISTINCT ON (tbl_lehrveranstaltung_faktor.lehrveranstaltung_id)
							tbl_lehrveranstaltung_faktor.lehrveranstaltung_id,
							tbl_lehrveranstaltung_faktor.faktor,
							vonstsem.start AS von_start,
							bisstem.ende AS bis_ende
						FROM lehre.tbl_lehrveranstaltung_faktor
							LEFT JOIN public.tbl_studiensemester vonstsem
								ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_von = vonstsem.studiensemester_kurzbz
							LEFT JOIN public.tbl_studiensemester bisstem
								ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_bis = bisstem.studiensemester_kurzbz
						WHERE
							(
								bisstem.ende >= (SELECT start
												 FROM public.tbl_studiensemester
												 WHERE studiensemester_kurzbz = ?)
									OR bisstem.ende IS NULL
								)
						  AND (vonstsem.start <= (SELECT ende
												 FROM public.tbl_studiensemester
												 WHERE studiensemester_kurzbz = ?)
								OR vonstsem.start IS NULL
							  )
						ORDER BY tbl_lehrveranstaltung_faktor.lehrveranstaltung_id, von_start DESC
					) AS tbl_lehrveranstaltung_faktor
					ON tbl_lehrveranstaltung.lehrveranstaltung_id = tbl_lehrveranstaltung_faktor.lehrveranstaltung_id
				WHERE
				  tbl_lehreinheit.studiensemester_kurzbz = ?
				  AND tbl_lehreinheitmitarbeiter.mitarbeiter_uid = ?;";
		return $this->execReadOnlyQuery($query, array($studiensemester, $studiensemester, $studiensemester, $uid));
	}

	public function getLehrauftraegeStundenWithoutFaktor($uid, $studiensemester)
	{

		$query = "SELECT COALESCE(SUM(tbl_lehreinheitmitarbeiter.semesterstunden), 0) as stunden
					FROM lehre.tbl_lehreinheitmitarbeiter 
					JOIN lehre.tbl_lehreinheit USING(lehreinheit_id)
					JOIN lehre.tbl_lehrveranstaltung USING(lehrveranstaltung_id)
					WHERE studiensemester_kurzbz = ?
						AND mitarbeiter_uid = ? 
		";
		return $this->execReadOnlyQuery($query, array($studiensemester, $uid));
	}

	public function getDVForSemester($uid, $studiensemester)
	{
		$query = "
				SELECT
					va.vertragsart_kurzbz
				FROM hr.tbl_dienstverhaeltnis dv
						 JOIN hr.tbl_vertragsart va USING (vertragsart_kurzbz)
				WHERE (dv.von <= ((SELECT ende
													FROM public.tbl_studiensemester
													WHERE public.tbl_studiensemester.studiensemester_kurzbz = ?)) OR dv.von IS NULL)
				  AND (dv.bis >= ((SELECT start
													 FROM public.tbl_studiensemester
													 WHERE public.tbl_studiensemester.studiensemester_kurzbz = ? )) OR dv.bis IS NULL)
				  AND dv.mitarbeiter_uid = ?
				ORDER BY von DESC LIMIT 1;
		";

		return $this->execReadOnlyQuery($query, array($studiensemester,$studiensemester, $uid));
	}
}
