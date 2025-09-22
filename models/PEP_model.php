<?php

class PEP_model extends DB_Model
{

	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_kategorie';
		$this->pk = array('kategorie_id');
		$this->hasSequence = true;
		$this->load->config('extensions/FHC-Core-PEP/pep');
	}

	public function isProjectAssignedToOrganization($org, $project)
	{
		$query = "SELECT 1
					FROM sync.tbl_sap_projects_timesheets timesheetsproject
					JOIN sync.tbl_sap_organisationsstruktur ON timesheetsproject.responsible_unit = tbl_sap_organisationsstruktur.oe_kurzbz_sap
					WHERE tbl_sap_organisationsstruktur.oe_kurzbz IN ". $this->_getRecursiveOE() ."
					AND project_id = ?";

		return $this->execQuery($query, array($org, $project));
	}

	public function getProjectRow($studienjahr, $project_employee_id, $withZeiterfassung = true)
	{
		$where = " pep_projects_employees_id = ? ";

		$query = $this->getProjectDataSql($where);

		return $this->execQuery($query, array($studienjahr, '', $withZeiterfassung, $withZeiterfassung, $withZeiterfassung, $this->config->item('excluded_project_status'), $studienjahr, $project_employee_id));

	}
	private function getProjectDataSql($where)
	{
		$query = "
			". $this->_getStartCTE() . ",
			". $this->_getAktuelleDaten() .",
			". $this->_getStudienjahrDates() .", 
			organisationseinheiten AS " . $this->_getRecursiveOE() . ",
			zeiterfassung AS (
				SELECT
					uid,
					projekt_kurzbz,
					dates.ende,
					(EXTRACT(EPOCH FROM (tbl_zeitaufzeichnung.ende - tbl_zeitaufzeichnung.start)) / 3600) AS gearbeitete_stunden,
					tbl_zeitaufzeichnung.ende as zeitaufzeichnungende
				FROM campus.tbl_zeitaufzeichnung JOIN semester_datum dates ON tbl_zeitaufzeichnung.start >= dates.start
				WHERE ?
			 ),
			stichtage AS (
				SELECT
					zeitaufzeichnung.uid,
					zeitaufzeichnung.projekt_kurzbz,

					SUM(CASE WHEN zeitaufzeichnung.ende <= date_trunc('year', semester_datum.ende) + INTERVAL '0' DAY
							THEN EXTRACT(EPOCH FROM (zeitaufzeichnung.ende - zeitaufzeichnung.start)) / 3600 ELSE 0 END) AS erster,

					SUM(CASE WHEN zeitaufzeichnung.ende <= date_trunc('year', semester_datum.ende) + INTERVAL '5 month'
							THEN EXTRACT(EPOCH FROM (zeitaufzeichnung.ende - zeitaufzeichnung.start)) / 3600 ELSE 0 END) AS zweiter,

					SUM(CASE WHEN zeitaufzeichnung.ende <= CURRENT_DATE
							THEN EXTRACT(EPOCH FROM (zeitaufzeichnung.ende - zeitaufzeichnung.start)) / 3600 ELSE 0 END) AS aktuell

				FROM campus.tbl_zeitaufzeichnung zeitaufzeichnung
					JOIN semester_datum ON zeitaufzeichnung.start >= semester_datum.start
				WHERE ?
				GROUP BY zeitaufzeichnung.projekt_kurzbz, zeitaufzeichnung.uid
			),
			aktuellges AS (
				SELECT
					SUM(EXTRACT(EPOCH FROM (tbl_zeitaufzeichnung.ende - tbl_zeitaufzeichnung.start)) / 3600) AS gearbeitete_stunden,
					projekt_kurzbz,
					uid
				FROM campus.tbl_zeitaufzeichnung
				WHERE tbl_zeitaufzeichnung.ende <= CURRENT_DATE AND ?
				GROUP BY projekt_kurzbz, uid
			 )
			SELECT
				ROW_NUMBER() OVER () AS row_index,
				pep_projects_employees_id,
				timesheetsproject.project_id,
				timesheetsprojectinfos.name,
				MIN(timesheetsprojectinfos.start_date) as start_date,
				MAX(timesheetsprojectinfos.end_date) as end_date,
				COALESCE(sapprojects.mitarbeiter_uid, pepprojects.mitarbeiter_uid) AS mitarbeiter_uid,
				SUM(COALESCE(sapprojects.planstunden, null)) AS summe_planstunden,

				ROUND(EXTRACT(YEAR FROM age(MAX(timesheetsprojectinfos.end_date), MIN(timesheetsprojectinfos.start_date))) * 12 +
					EXTRACT(MONTH FROM age(MAX(timesheetsprojectinfos.end_date), MIN(timesheetsprojectinfos.start_date))) + 
					EXTRACT(DAY FROM age(MAX(timesheetsprojectinfos.end_date), MIN(timesheetsprojectinfos.start_date))) / 30.0) AS laufzeit,

				ROUND(LEAST(
					(EXTRACT(YEAR FROM age(MAX(timesheetsprojectinfos.end_date), MIN(timesheetsprojectinfos.start_date))) * 12 +
					EXTRACT(MONTH FROM age(MAX(timesheetsprojectinfos.end_date), MIN(timesheetsprojectinfos.start_date))) + 
					EXTRACT(DAY FROM age(MAX(timesheetsprojectinfos.end_date), MIN(timesheetsprojectinfos.start_date))) / 30.0),

					ROUND(EXTRACT(YEAR FROM age(CURRENT_DATE,  MIN(timesheetsprojectinfos.start_date))) * 12 +
					EXTRACT(MONTH FROM age(CURRENT_DATE, MIN(timesheetsprojectinfos.start_date)))  +
					EXTRACT(DAY FROM age(CURRENT_DATE, MIN(timesheetsprojectinfos.start_date))) / 30.0)
				)) AS verbrauchte_zeit,
				ROUND(CASE
					WHEN (
						EXTRACT(YEAR FROM age(MAX(timesheetsprojectinfos.end_date), CURRENT_DATE)) * 12 +
						EXTRACT(MONTH FROM age(MAX(timesheetsprojectinfos.end_date), CURRENT_DATE)) +
						EXTRACT(DAY FROM age(MAX(timesheetsprojectinfos.end_date), CURRENT_DATE)) / 30.0
						) < 0.5
					THEN 0
				ELSE (
						EXTRACT(YEAR FROM age(MAX(timesheetsprojectinfos.end_date), CURRENT_DATE)) * 12 +
						EXTRACT(MONTH FROM age(MAX(timesheetsprojectinfos.end_date), CURRENT_DATE)) +
						EXTRACT(DAY FROM age(MAX(timesheetsprojectinfos.end_date), CURRENT_DATE)) / 30.0
					)
				END) AS restlaufzeit,
				pepprojects.stunden AS stunden,
				pepprojects.anmerkung,
				tbl_sap_projects_status.description as status,
				ROUND(stichtage.erster, 2) as erster,
				ROUND(stichtage.zweiter, 2) as zweiter,
				ROUND(stichtage.aktuell, 2) as aktuellestunden,
				ROUND(aktuellges.gearbeitete_stunden, 2) as aktuellestundengesamt,
				CASE WHEN av.vertragsart_kurzbz = 'echterdv' THEN av.orgbezeichnung ELSE av.oeorgbezeichnung END as akt_orgbezeichnung,
				CASE WHEN av.vertragsart_kurzbz = 'echterdv' THEN av.parentbezeichnung ELSE av.oeorgparentbezeichnung END as akt_parentbezeichnung,
				av.bezeichnung as akt_bezeichnung,
				person.vorname,
				person.nachname,
				STRING_AGG(DISTINCT leitungsperson.vorname || ' ' || leitungsperson.nachname,  E'\n') as leitung,
				tbl_sap_projects_status_intern.description as status_sap_intern,
				ROUND(SUM(COALESCE(sapprojects.planstunden, 0)) - COALESCE(aktuellges.gearbeitete_stunden, 0), 2) as offenestunden,
				CASE 
					WHEN pepprojects.stunden IS NOT NULL
						AND NOT (
							(timesheetsprojectinfos.start_date <= dates.ende OR timesheetsprojectinfos.start_date IS NULL)
							AND (timesheetsprojectinfos.end_date >= dates.start OR timesheetsprojectinfos.end_date IS NULL)
							AND (tbl_sap_projects_status_intern.status NOT IN ? OR tbl_sap_projects_status_intern.status IS NULL)
						)
				THEN true
				ELSE false
			  END AS only_pep
			FROM semester_datum as dates,
				sync.tbl_sap_projects_timesheets timesheetsproject
				LEFT JOIN sync.tbl_projects_employees sapprojects ON timesheetsproject.project_task_id = sapprojects.project_task_id
				LEFT JOIN extension.tbl_pep_projects_employees pepprojects ON timesheetsproject.project_id = pepprojects.projekt_id
					AND (sapprojects.mitarbeiter_uid = pepprojects.mitarbeiter_uid OR sapprojects.mitarbeiter_uid IS NULL OR pepprojects.mitarbeiter_uid IS NULL)
					AND pepprojects.studienjahr_kurzbz = ?
				JOIN public.tbl_mitarbeiter mitarbeiter ON
					(mitarbeiter.mitarbeiter_uid = COALESCE(sapprojects.mitarbeiter_uid, pepprojects.mitarbeiter_uid))
				LEFT JOIN aktVertrag av ON av.mitarbeiter_uid = COALESCE(sapprojects.mitarbeiter_uid, pepprojects.mitarbeiter_uid) AND av.rn = 1
				JOIN public.tbl_benutzer benutzer ON mitarbeiter.mitarbeiter_uid = benutzer.uid
				JOIN public.tbl_person person ON benutzer.person_id = person.person_id
				
				LEFT JOIN (SELECT DISTINCT ON (project_id) *, COALESCE(tbl_sap_projects_timesheets.custom_fields->>'Status_KUT', '') as internstatus
							FROM sync.tbl_sap_projects_timesheets
							WHERE project_task_id IS NULL
							ORDER BY project_id, deleted, start_date DESC
				) timesheetsprojectinfos ON timesheetsproject.project_id = timesheetsprojectinfos.project_id
				
				LEFT JOIN sync.tbl_projects_timesheets_project  ON timesheetsprojectinfos.projects_timesheet_id = tbl_projects_timesheets_project.projects_timesheet_id
				LEFT JOIN sync.tbl_sap_projects_status ON timesheetsprojectinfos.status = tbl_sap_projects_status.status
				LEFT JOIN sync.tbl_sap_organisationsstruktur ON  timesheetsprojectinfos.responsible_unit = tbl_sap_organisationsstruktur.oe_kurzbz_sap
				LEFT JOIN fue.tbl_projekt ON tbl_projects_timesheets_project.projekt_id = tbl_projekt.projekt_id

				LEFT JOIN stichtage ON tbl_projekt.projekt_kurzbz = stichtage.projekt_kurzbz AND stichtage.uid = COALESCE(sapprojects.mitarbeiter_uid, pepprojects.mitarbeiter_uid)
				LEFT JOIN aktuellges ON tbl_projekt.projekt_kurzbz = aktuellges.projekt_kurzbz AND aktuellges.uid = COALESCE(sapprojects.mitarbeiter_uid, pepprojects.mitarbeiter_uid)

				LEFT JOIN tbl_benutzer leitungsbenutzer ON timesheetsprojectinfos.project_leader = leitungsbenutzer.uid
				LEFT JOIN public.tbl_person leitungsperson ON leitungsbenutzer.person_id = leitungsperson.person_id
				LEFT JOIN sync.tbl_sap_projects_status_intern
				ON timesheetsprojectinfos.internstatus = tbl_sap_projects_status_intern.status::text

			WHERE
				timesheetsproject.deleted is false AND 
				" . $where ."
			GROUP BY
				COALESCE(sapprojects.mitarbeiter_uid, pepprojects.mitarbeiter_uid),
				person.vorname,
				person.nachname,
				av.vertragsart_kurzbz,
				av.oe_kurzbz,
				av.orgbezeichnung,
				av.parentbezeichnung,
				av.oeorgbezeichnung,
				av.oeorgparentbezeichnung,
				av.bezeichnung,
				dates.start,
				dates.ende,
				pep_projects_employees_id,
				timesheetsproject.project_id,
				timesheetsprojectinfos.project_id,
				timesheetsprojectinfos.name,
				timesheetsprojectinfos.start_date,
				timesheetsprojectinfos.end_date,
				timesheetsproject.deleted,
				pepprojects.stunden,
				stichtage.erster,
				stichtage.zweiter,
				stichtage.aktuell,
				aktuellges.gearbeitete_stunden,
				tbl_sap_projects_status.description,
				tbl_sap_projects_status_intern.status
			ORDER BY timesheetsproject.project_id;
		";

		return $query;

	}
	public function getProjectData($mitarbeiter_uids, $studienjahr, $org, $withZeiterfassung = true)
	{
		$where = " (
					COALESCE(sapprojects.mitarbeiter_uid, pepprojects.mitarbeiter_uid) IN ?
					OR tbl_sap_organisationsstruktur.oe_kurzbz IN (SELECT oe_kurzbz FROM organisationseinheiten)
				)
			AND (
					(
						(timesheetsprojectinfos.start_date <= dates.ende OR timesheetsprojectinfos.start_date IS NULL)
						AND (timesheetsprojectinfos.end_date >= dates.start OR timesheetsprojectinfos.end_date IS NULL)
						AND ((tbl_sap_projects_status_intern.status NOT IN ? OR tbl_sap_projects_status_intern.status IS NULL))
					)
					OR pepprojects.stunden IS NOT NULL
				)";

		$query = $this->getProjectDataSql($where);


		return $this->execQuery($query, array($studienjahr, $org, $withZeiterfassung, $withZeiterfassung, $withZeiterfassung, $this->config->item('excluded_project_status'), $studienjahr, $mitarbeiter_uids, $this->config->item('excluded_project_status')));

	}

	private function _getRecursiveOE()
	{
		return "(WITH RECURSIVE oes(oe_kurzbz, oe_parent_kurzbz) AS (
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
				GROUP BY oe_kurzbz)";
	}

	public function getCategoryStundenByMitarbeiter($mitarbeiter, $studiensemester = null, $studienjahr = null)
	{
		$dates =  (is_null($studienjahr)) ? $studiensemester : $studienjahr;

		foreach ($this->config->item('annual_hours') as $case)
		{
			$caseStatements[] = "WHEN zv.oe_kurzbz ='" . $case['condition'] . "' THEN pks.default_stunden/" . $case['base_value'] . " * zv.einzelnejahresstunden";
		};


		$params = [];
		$query = "
			". $this->_getStartCTE() .",
			". (is_null($studienjahr) ? $this->_getStudiensemesterDates() : $this->_getStudienjahrDates()) .",
			". $this->_getZeitraumDaten() . "
				SELECT
					pk.kategorie_id,
					pk.bezeichnung,
					pk.bezeichnung_mehrsprachig,
					CASE WHEN zv.relevante_vertragsart IN ('echterdv', 'dummy', 'externerlehrender') THEN (
						ROUND (COALESCE(SUM(pkm.stunden),
							  CASE ". implode(" ", $caseStatements) . " END
								), 2)
					) END AS stunden,

					COALESCE(pkm.mitarbeiter_uid, pk.insertvon) AS mitarbeiter_uid,
					COALESCE(pkm.studienjahr_kurzbz, pks.gueltig_ab_studienjahr) AS studiensemester_kurzbz";

		if (is_null($studienjahr))
			$this->getCategoryStundenBySemester($query, $params, $dates, $mitarbeiter);
		else
			$this->getCategoryStundenByJahr($query, $params, $dates, $mitarbeiter);

		$query .= "	GROUP BY
					pk.kategorie_id,
					pk.bezeichnung,
					pk.bezeichnung_mehrsprachig,
					pks.default_stunden,
					pkm.mitarbeiter_uid,
					pk.insertvon,
					pks.gueltig_ab_studienjahr,
					zv.oe_kurzbz,
					zv.einzelnejahresstunden,
					pkm.studienjahr_kurzbz, relevante_vertragsart";

		return $this->execQuery($query, $params);
	}

	private function getCategoryStundenByJahr(&$query, &$params, $dates, $mitarbeiter)
	{

		$query .= "
			FROM extension.tbl_pep_kategorie pk
					LEFT JOIN extension.tbl_pep_kategorie_studienjahr pks USING(kategorie_id)
					LEFT JOIN
						extension.tbl_pep_kategorie_mitarbeiter pkm
						ON pk.kategorie_id = pkm.kategorie_id
						AND pkm.studienjahr_kurzbz = ?
							AND pkm.mitarbeiter_uid = ?
					LEFT JOIN zeitraumVertrag zv ON zv.mitarbeiter_uid = ? AND zv.rn = 1
				WHERE
					(pks.gueltig_ab_studienjahr IN (
						SELECT tbl_studiensemester.studienjahr_kurzbz
						FROM tbl_studiensemester
						WHERE tbl_studiensemester.start <= (SELECT MAX(ende) FROM tbl_studiensemester startstudiensemester WHERE startstudiensemester.studienjahr_kurzbz = ?)
					)
					AND
					(
						pks.gueltig_bis_studienjahr IN (
							SELECT tbl_studiensemester.studienjahr_kurzbz
							FROM tbl_studiensemester
							WHERE tbl_studiensemester.ende >= (SELECT MIN(start) FROM tbl_studiensemester bisstudiensemester WHERE bisstudiensemester.studienjahr_kurzbz = ?)
						)
						OR
							gueltig_bis_studienjahr IS NULL
					))
					OR (pkm.mitarbeiter_uid = ? AND pkm.studienjahr_kurzbz = ?)";
		$params = array($dates, $dates, $mitarbeiter, $mitarbeiter, $dates, $dates, $mitarbeiter, $dates);
	}

	private function getCategoryStundenBySemester(&$query, &$params, $dates, $mitarbeiter)
	{
		$query .= "
			FROM extension.tbl_pep_kategorie pk
					LEFT JOIN extension.tbl_pep_kategorie_studienjahr pks USING(kategorie_id)
					LEFT JOIN
						extension.tbl_pep_kategorie_mitarbeiter pkm
						ON pk.kategorie_id = pkm.kategorie_id
						AND pkm.studienjahr_kurzbz = (SELECT studienjahr_kurzbz FROM tbl_studiensemester WHERE tbl_studiensemester.studiensemester_kurzbz = ?)
							AND pkm.mitarbeiter_uid = ?
					LEFT JOIN zeitraumVertrag zv ON zv.mitarbeiter_uid = ? AND zv.rn = 1
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
					OR (pkm.mitarbeiter_uid = ? AND pkm.studienjahr_kurzbz = (SELECT studienjahr_kurzbz FROM tbl_studiensemester WHERE studiensemester_kurzbz = ?))";
		$params = array(array($dates), $dates, $mitarbeiter, $mitarbeiter, $dates, $dates, $mitarbeiter, $dates);
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

	private function getSemesterDatesTmpQry()
	{
		return "dates AS (
				SELECT MAX(ende) ende, MIN(start) as start
				FROM public.tbl_studienjahr
						 JOIN public.tbl_studiensemester USING(studienjahr_kurzbz)
				WHERE studienjahr_kurzbz = ?
			)";
	}
	public function getMitarbeiter($org, $studiensemester, $recursive)
	{

		$query = "SELECT
					DISTINCT (ma.uid)

				FROM campus.vw_mitarbeiter ma
				JOIN hr.tbl_dienstverhaeltnis dv ON ma.uid = dv.mitarbeiter_uid
				JOIN hr.tbl_vertragsart vertragsart USING(vertragsart_kurzbz)
				JOIN hr.tbl_vertragsbestandteil bestandteil ON dv.dienstverhaeltnis_id = bestandteil.dienstverhaeltnis_id
				JOIN hr.tbl_vertragsbestandteil_funktion vbstfunktion ON bestandteil.vertragsbestandteil_id = vbstfunktion.vertragsbestandteil_id
				LEFT JOIN public.tbl_benutzerfunktion funktion ON vbstfunktion.benutzerfunktion_id = funktion.benutzerfunktion_id
				WHERE funktion_kurzbz IN ?
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

		if (is_array($org))
		{
			$additionalQuery = " AND funktion.oe_kurzbz IN ?";
		}
		else if ($recursive)
		{
			$additionalQuery = "
				AND funktion.oe_kurzbz IN
				". $this->_getRecursiveOE() ."
			";
		}
		else
			$additionalQuery = " AND funktion.oe_kurzbz = ?";

		$query .= $additionalQuery;

		return  $this->execReadOnlyQuery($query, array($this->config->item('relevant_function_types'), $studiensemester, $studiensemester, $org));
	}

	public function getMitarbeiterLehre($org, $studiensemester, $recursive, $mitarbeiter_uids)
	{
		$query = "
			". $this->_getStartCTE() .",
			". $this->_getStudiensemesterDates() .",
			". $this->_getZeitraumDaten() ."
			SELECT
			ROW_NUMBER() OVER () AS row_index,
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
										'-'||
										COALESCE(tbl_lehreinheitgruppe.semester::varchar, '') ||
										COALESCE(tbl_lehreinheitgruppe.verband::varchar, '')||
										COALESCE(tbl_lehreinheitgruppe.gruppe, '')), ', '
							)
						) AS gruppen
				FROM
					lehre.tbl_lehreinheitgruppe
					LEFT JOIN public.tbl_studiengang USING(studiengang_kz)
					LEFT JOIN public.tbl_gruppe USING(gruppe_kurzbz)
				WHERE tbl_lehreinheitgruppe.lehreinheit_id = tbl_lehreinheit.lehreinheit_id
			) as gruppe,
			(
				SELECT upper(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) as stg_kuerzel
				FROM lehre.tbl_lehrveranstaltung
					JOIN tbl_studiengang ON tbl_lehrveranstaltung.studiengang_kz = tbl_studiengang.studiengang_kz
				WHERE tbl_lehrveranstaltung.lehrveranstaltung_id = tbl_lehreinheit.lehrveranstaltung_id
			) as stg_kuerzel,
			tbl_lehrveranstaltung.orgform_kurzbz as lv_orgform,
			(
				SELECT tbl_studiengang.email
				FROM lehre.tbl_lehrveranstaltung
					JOIN tbl_studiengang ON tbl_lehrveranstaltung.studiengang_kz = tbl_studiengang.studiengang_kz
				WHERE tbl_lehrveranstaltung.lehrveranstaltung_id = tbl_lehreinheit.lehrveranstaltung_id
			) as stg_email,
			tbl_lehreinheitmitarbeiter.mitarbeiter_uid as uid,
			tbl_lehreinheitmitarbeiter.insertamum as insertamum,
			tbl_lehreinheitmitarbeiter.updateamum as updateamum,
			(lehreinheitperson.vorname || ' ' || lehreinheitperson.nachname || ' ' || '(' || lehreinheitbenutzer.uid || ')') as lehreinheitupdatevon,
			tbl_lehreinheitmitarbeiter.anmerkung,
			tbl_mitarbeiter.kurzbz as lektor,
			tbl_person.vorname as vorname,
			tbl_person.nachname as lektor_nachname,
			tbl_lehreinheit.lehreinheit_id,
			tbl_lehreinheit.studiensemester_kurzbz,
			tbl_lehreinheitmitarbeiter.stundensatz as le_stundensatz,
			tbl_lehreinheitmitarbeiter.semesterstunden AS lektor_stunden,
			tbl_lehreinheit.lehrform_kurzbz,
			lv_org.oe_kurzbz,
			tbl_lehrveranstaltung.kurzbz as lv_kurzbz,
			tbl_lehrveranstaltung.bezeichnung as lv_bezeichnung,
			tbl_lehrveranstaltung.bezeichnung_english as lv_bezeichnung_eng,
			tbl_lehrveranstaltung.lehrveranstaltung_id as lv_id,
			tbl_lehrveranstaltung.semester as lv_semester,
			tbl_lehreinheit.sprache as le_unterrichtssprache,
			tbl_lehreinheit.anmerkung as lv_anmerkung,
			lv_org.bezeichnung as lv_oe,
			(
				SELECT
					(
				    (NOT EXISTS(
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
					)
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
			CASE WHEN zv.relevante_vertragsart = 'echterdv' THEN (
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
				  AND (
					lehrform_kurzbz = tbl_lehreinheit.lehrform_kurzbz
						OR (
							lehrform_kurzbz IS NULL
								AND NOT EXISTS (
									SELECT 1
									FROM lehre.tbl_lehrveranstaltung_faktor lvfaktor2
									LEFT JOIN public.tbl_studiensemester vonstsem2
										ON lvfaktor2.studiensemester_kurzbz_von = vonstsem2.studiensemester_kurzbz
									LEFT JOIN public.tbl_studiensemester bisstem2
										ON lvfaktor2.studiensemester_kurzbz_bis = bisstem2.studiensemester_kurzbz
									WHERE lvfaktor2.lehrveranstaltung_id = tbl_lehrveranstaltung_faktor.lehrveranstaltung_id
									  AND lvfaktor2.lehrform_kurzbz = tbl_lehreinheit.lehrform_kurzbz
		
									 AND (
										bisstem2.ende >= (
											SELECT start
											FROM public.tbl_studiensemester
											WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz
										)
										OR bisstem2.ende IS NULL
									)
					
									 AND vonstsem2.start <= (SELECT ende
										FROM public.tbl_studiensemester
										WHERE studiensemester_kurzbz =  tbl_lehreinheit.studiensemester_kurzbz
									)
							)
						)
					)
				ORDER BY vonstsem.start DESC
				LIMIT 1
			) ELSE 0 END as faktor,
			CASE WHEN zv.relevante_vertragsart = 'echterdv'
			THEN (COALESCE(
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
						  AND (
							lehrform_kurzbz = tbl_lehreinheit.lehrform_kurzbz
								OR (
									lehrform_kurzbz IS NULL
										AND NOT EXISTS (
											SELECT 1
											FROM lehre.tbl_lehrveranstaltung_faktor lvfaktor2
											LEFT JOIN public.tbl_studiensemester vonstsem2
												ON lvfaktor2.studiensemester_kurzbz_von = vonstsem2.studiensemester_kurzbz
											LEFT JOIN public.tbl_studiensemester bisstem2
												ON lvfaktor2.studiensemester_kurzbz_bis = bisstem2.studiensemester_kurzbz
											WHERE lvfaktor2.lehrveranstaltung_id = tbl_lehrveranstaltung_faktor.lehrveranstaltung_id
											  AND lvfaktor2.lehrform_kurzbz = tbl_lehreinheit.lehrform_kurzbz
				
											 AND (
												bisstem2.ende >= (
													SELECT start
													FROM public.tbl_studiensemester
													WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz
												)
												OR bisstem2.ende IS NULL
											)
							
											 AND vonstsem2.start <= (SELECT ende
												FROM public.tbl_studiensemester
												WHERE studiensemester_kurzbz =  tbl_lehreinheit.studiensemester_kurzbz
											)
									)
								)
							)
					 ORDER BY vonstsem.start DESC
					 LIMIT 1
				), tbl_lehreinheitmitarbeiter.semesterstunden
			)) ELSE tbl_lehreinheitmitarbeiter.semesterstunden END AS faktorstunden,
			 (
			SELECT
				string_agg(DISTINCT vorname || ' ' || nachname, ', ')
			FROM lehre.tbl_lehreinheit slehreinheit
					 JOIN lehre.tbl_lehrveranstaltung slehrfach ON slehreinheit.lehrfach_id = slehrfach.lehrveranstaltung_id
					 JOIN lehre.tbl_lehreinheitmitarbeiter slehreinheitmitarbeiter USING(lehreinheit_id)
					 JOIN tbl_mitarbeiter smitarbeiter USING (mitarbeiter_uid)
					 JOIN tbl_benutzer sbenutzer ON smitarbeiter.mitarbeiter_uid = sbenutzer.uid
					 JOIN tbl_person sperson ON sbenutzer.person_id = sperson.person_id
			WHERE slehrfach.lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id
			 AND studiensemester_kurzbz = (SELECT studiensemester_kurzbz
                                        FROM public.tbl_studiensemester
                                        WHERE ende < (SELECT start FROM public.tbl_studiensemester WHERE tbl_studiensemester.studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz) ORDER BY ende DESC LIMIT 1 OFFSET 1)
		   ) as vorjahreslektoren,
			(SELECT bezeichnung
				FROM lehre.tbl_vertrag_vertragsstatus
				JOIN lehre.tbl_vertragsstatus USING(vertragsstatus_kurzbz)
				WHERE tbl_vertrag_vertragsstatus.vertrag_id = tbl_vertrag.vertrag_id
				ORDER BY datum DESC LIMIT 1
			) as lehrauftrag_status,
			tbl_lehreinheit.raumtyp,
			tbl_lehreinheit.raumtypalternativ,
			tbl_lehreinheit.wochenrythmus,
			tbl_lehreinheit.start_kw,
			tbl_lehreinheit.stundenblockung,
			tbl_lehreinheitmitarbeiter.lehrfunktion_kurzbz,
			tbl_lehreinheitmitarbeiter.planstunden AS lv_plan_stunden,
			zv.relevante_vertragsart,
			array_to_json(array_agg(DISTINCT(tag_data))) AS tags,
			array_to_json(array_agg(DISTINCT(tag_status_data))) AS tagstatus
		FROM
			lehre.tbl_lehreinheit
			JOIN lehre.tbl_lehrveranstaltung USING (lehrveranstaltung_id)
			JOIN lehre.tbl_lehrveranstaltung lehrfach ON tbl_lehreinheit.lehrfach_id = lehrfach.lehrveranstaltung_id
			JOIN lehre.tbl_lehreinheitmitarbeiter USING (lehreinheit_id)
			LEFT JOIN lehre.tbl_vertrag USING(vertrag_id)
			JOIN tbl_mitarbeiter USING (mitarbeiter_uid)
			JOIN tbl_benutzer ON tbl_mitarbeiter.mitarbeiter_uid = tbl_benutzer.uid
			JOIN tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
			JOIN tbl_organisationseinheit lv_org ON lv_org.oe_kurzbz = lehrfach.oe_kurzbz
			LEFT JOIN zeitraumVertrag zv ON tbl_mitarbeiter.mitarbeiter_uid = zv.mitarbeiter_uid AND zv.rn = 1
			LEFT JOIN
			(
				SELECT
				 DISTINCT ON (tbl_notiz.notiz_id)
					tbl_notiz.notiz_id AS id,
					typ_kurzbz,
					array_to_json(tbl_notiz_typ.bezeichnung_mehrsprachig)->>0 AS beschreibung,
					lehreinheit_id,
					tbl_notiz.text as notiz,
					tbl_notiz_typ.style,
					tbl_notiz.erledigt as done
				FROM
					public.tbl_notizzuordnung
					JOIN public.tbl_notiz ON tbl_notizzuordnung.notiz_id = tbl_notiz.notiz_id
					JOIN public.tbl_notiz_typ ON tbl_notiz.typ = tbl_notiz_typ.typ_kurzbz
				WHERE typ_kurzbz NOT IN ? 
			) AS tag_data ON tbl_lehreinheit.lehreinheit_id = tag_data.lehreinheit_id
			LEFT JOIN
			(
				SELECT
				 DISTINCT ON (tbl_notiz.notiz_id)
					tbl_notiz.notiz_id AS id,
					typ_kurzbz,
					array_to_json(tbl_notiz_typ.bezeichnung_mehrsprachig)->>0 AS beschreibung,
					lehreinheit_id,
					tbl_notiz.text as notiz,
					tbl_notiz_typ.style,
					tbl_notiz.erledigt as done
				FROM
					public.tbl_notizzuordnung
					JOIN public.tbl_notiz ON tbl_notizzuordnung.notiz_id = tbl_notiz.notiz_id
					JOIN public.tbl_notiz_typ ON tbl_notiz.typ = tbl_notiz_typ.typ_kurzbz
				WHERE typ_kurzbz IN ?
			) AS tag_status_data ON tbl_lehreinheit.lehreinheit_id = tag_status_data.lehreinheit_id
			LEFT JOIN public.tbl_benutzer lehreinheitbenutzer ON lehreinheitbenutzer.uid = tbl_lehreinheitmitarbeiter.updatevon
			LEFT JOIN public.tbl_person lehreinheitperson ON lehreinheitperson.person_id = lehreinheitbenutzer.person_id
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
				tbl_lehrveranstaltung.orgform_kurzbz,
				tbl_lehrveranstaltung.lehrveranstaltung_id,
				tbl_lehreinheit.studiensemester_kurzbz,
				tbl_lehreinheitmitarbeiter.semesterstunden,
				tbl_lehreinheitmitarbeiter.stundensatz,
				tbl_lehreinheit.lehrform_kurzbz,
				lv_org.oe_kurzbz,
				tbl_lehreinheitmitarbeiter.insertamum,
				tbl_lehreinheitmitarbeiter.updateamum,
				lehreinheitupdatevon,
				tbl_lehreinheitmitarbeiter.anmerkung,
				tbl_lehreinheitmitarbeiter.lehrfunktion_kurzbz,
				tbl_lehreinheitmitarbeiter.planstunden,
				relevante_vertragsart,
				tbl_vertrag.vertrag_id
			ORDER BY tbl_lehreinheit.lehrveranstaltung_id
		";

		$tags = $this->config->item('planungsstatus_tags');
		return $this->execReadOnlyQuery($query, array($studiensemester, $tags, $tags, $studiensemester, $org, $mitarbeiter_uids));
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
						SELECT 
							tbl_lehrveranstaltung_faktor.lehrveranstaltung_id,
							tbl_lehrveranstaltung_faktor.faktor,
							vonstsem.start AS von_start,
							bisstem.ende AS bis_ende,
							lehrform_kurzbz
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
					AND (
					   tbl_lehrveranstaltung_faktor.lehrform_kurzbz = tbl_lehreinheit.lehrform_kurzbz
						OR (
							tbl_lehrveranstaltung_faktor.lehrform_kurzbz IS NULL
						       AND NOT EXISTS (
									SELECT 1
									FROM lehre.tbl_lehrveranstaltung_faktor lvfaktor2
									LEFT JOIN public.tbl_studiensemester vonstsem2
										ON lvfaktor2.studiensemester_kurzbz_von = vonstsem2.studiensemester_kurzbz
									LEFT JOIN public.tbl_studiensemester bisstem2
										ON lvfaktor2.studiensemester_kurzbz_bis = bisstem2.studiensemester_kurzbz
									WHERE lvfaktor2.lehrveranstaltung_id = tbl_lehrveranstaltung_faktor.lehrveranstaltung_id
									  AND lvfaktor2.lehrform_kurzbz = tbl_lehreinheit.lehrform_kurzbz
		
									 AND (
										bisstem2.ende >= (
											SELECT start
											FROM public.tbl_studiensemester
											WHERE studiensemester_kurzbz = tbl_lehreinheit.studiensemester_kurzbz
										)
										OR bisstem2.ende IS NULL
									)
					
									 AND vonstsem2.start <= (SELECT ende
										FROM public.tbl_studiensemester
										WHERE studiensemester_kurzbz =  tbl_lehreinheit.studiensemester_kurzbz
									)
							)
						   )
					   )
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

	private function _getProjectHoursEmpStudienjahr()
	{
		return "projecstunden AS (
					SELECT SUM(stunden) as stunden, mitarbeiter_uid
					FROM extension.tbl_pep_projects_employees
					WHERE studienjahr_kurzbz = ?
					GROUP BY mitarbeiter_uid
				)";
	}

	private function _getProjectHoursEmpSemester()
	{
		return "projecstunden AS (
					SELECT SUM(stunden) as stunden, mitarbeiter_uid
					FROM extension.tbl_pep_projects_employees
					WHERE studienjahr_kurzbz IN ?
					GROUP BY mitarbeiter_uid
				)";
	}


	public function getProjectStundenByEmployee($uid, $studiensemester, $studienjahr = null)
	{
		$timeslot = is_null($studienjahr) ?  $studiensemester : $studienjahr;
		$query = "
			SELECT COALESCE(SUM(stunden)::int, 0) as stunden
			FROM extension.tbl_pep_projects_employees
			WHERE
				mitarbeiter_uid = ? AND studienjahr_kurzbz =
		";

		if (!is_null($studienjahr))
			$query .= " ?";
		else
			$query .= " (SELECT tbl_studiensemester.studienjahr_kurzbz FROM tbl_studiensemester WHERE studiensemester_kurzbz = ?)";

		return $this->execReadOnlyQuery($query, array($uid, $timeslot));
	}

	public function getDVForSemester($uid, $studiensemester)
	{
		$query = "

				WITH semester_daten AS (
					SELECT studiensemester_kurzbz, start, ende
					FROM public.tbl_studiensemester
					WHERE studiensemester_kurzbz IN ?
			),
			semester_combined AS (
				SELECT
					dv.mitarbeiter_uid,
					va.vertragsart_kurzbz,
					sd.studiensemester_kurzbz,
					ROW_NUMBER() OVER (PARTITION BY dv.mitarbeiter_uid, sd.studiensemester_kurzbz ORDER BY dv.von DESC, dv.bis DESC NULLS FIRST) AS rn
				FROM hr.tbl_dienstverhaeltnis dv
				JOIN hr.tbl_vertragsart va USING (vertragsart_kurzbz)
				RIGHT JOIN semester_daten sd ON
					(dv.von <= sd.ende OR dv.von IS NULL)
					AND (dv.bis >= sd.start OR dv.bis IS NULL)
					AND dv.mitarbeiter_uid = ?
				ORDER BY sd.start
			)
			SELECT studiensemester_kurzbz,
					vertragsart_kurzbz
			FROM semester_combined
			WHERE rn = 1;
		";

		return $this->execReadOnlyQuery($query, array($studiensemester, $uid));
	}


	public function _getDVs($uid, $studienjahr = null, $studiensemester = null)
	{
		if (is_null($studienjahr))
			$dates = $studiensemester;
		else
			$dates = $studienjahr;


		$dbModel = new DB_Model();

		$qry = "
			". $this->_getStartCTE() .",
			". $this->_getAktuelleDaten() . ",
			". (is_null($studienjahr) ? $this->_getStudiensemesterDates() : $this->_getStudienjahrDates()) .",
			". $this->_getZeitraumDaten() . ",
				akt_lehre_stundensatz AS (
						SELECT stundensatz,
								uid,
								ROW_NUMBER() OVER (PARTITION BY uid ORDER BY gueltig_von DESC, gueltig_bis DESC NULLS FIRST) AS rn
						FROM hr.tbl_stundensatz
						JOIN hr.tbl_stundensatztyp ON tbl_stundensatz.stundensatztyp = tbl_stundensatztyp.stundensatztyp
						AND (tbl_stundensatz.gueltig_von <= NOW() OR tbl_stundensatz.gueltig_von IS NULL)
						AND (tbl_stundensatz.gueltig_bis >= NOW() OR tbl_stundensatz.gueltig_bis IS NULL)
						AND tbl_stundensatz.stundensatztyp = 'lehre'
				),
				 lehre_stundensatz AS (
					SELECT
						ARRAY_TO_STRING(ARRAY_AGG(stundensatz) OVER (PARTITION BY uid), E'\n') AS stunden,
							uid,
							ROW_NUMBER() OVER (PARTITION BY uid ORDER BY gueltig_von DESC, gueltig_bis DESC NULLS FIRST) AS rn
					FROM hr.tbl_stundensatz
					JOIN hr.tbl_stundensatztyp ON tbl_stundensatz.stundensatztyp = tbl_stundensatztyp.stundensatztyp
						AND tbl_stundensatz.stundensatztyp = 'lehre'
					 WHERE
						(gueltig_von <= ( SELECT ende FROM semester_datum) OR gueltig_von IS NULL)
						AND (gueltig_bis >= (SELECT start FROM semester_datum) OR gueltig_bis IS NULL)
					ORDER BY gueltig_von DESC
				 ),
				karenz AS (
					SELECT hr.tbl_vertragsbestandteil.*,
						dv.mitarbeiter_uid,
						ROW_NUMBER() OVER (PARTITION BY mitarbeiter_uid ORDER BY tbl_vertragsbestandteil.von DESC, tbl_vertragsbestandteil.bis DESC NULLS FIRST) AS rn
					FROM hr.tbl_dienstverhaeltnis dv
						JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
					WHERE (tbl_vertragsbestandteil.von <= NOW() OR tbl_vertragsbestandteil.von > NOW())
						AND (tbl_vertragsbestandteil.bis >= NOW() OR tbl_vertragsbestandteil.bis IS NULL)
						AND vertragsbestandteiltyp_kurzbz = 'karenz'
					ORDER BY tbl_vertragsbestandteil.von DESC NULLS LAST
				),
				tag_data AS (
					SELECT
						DISTINCT ON (tbl_notiz.notiz_id)
						tbl_notiz.notiz_id AS id,
						typ_kurzbz,
						array_to_json(tbl_notiz_typ.bezeichnung_mehrsprachig)->>0 AS beschreibung,
						tbl_notiz.text as notiz,
						tbl_notiz_typ.style,
						tbl_notiz.erledigt as done,
						mitarbeiter_uid
					FROM
						extension.tbl_pep_notiz_mitarbeiter
						JOIN public.tbl_notiz ON tbl_pep_notiz_mitarbeiter.notiz_id = tbl_notiz.notiz_id
						JOIN public.tbl_notiz_typ ON tbl_notiz.typ = tbl_notiz_typ.typ_kurzbz
					ORDER BY tbl_notiz.notiz_id
				)
			SELECT
				COALESCE((
				SELECT array_to_json(array_agg(DISTINCT tag_data))
					FROM tag_data
					WHERE tag_data.mitarbeiter_uid = ma.mitarbeiter_uid),
					'[]'::json
				) AS tags,
				zv.dienstverhaeltnis_id,
				zv.von AS von,
				zv.bis AS bis,
				ma.mitarbeiter_uid,
				zv.bezeichnung AS bezeichnung,
				zv.relevante_vertragsart as releavante_vertragsart,
				zv.oe_kurzbz as oe_kurzbz,
				zv.alle_vertraege as zrm_vertraege,
				zv.wochenstunden as zrm_wochenstunden,
				zv.jahresstunden as zrm_jahresstunden,
				zv.einzelnejahresstunden as zrm_einzeljahresstunden,
				lehre_stundensatz.stunden as zrm_stundensatz_lehre,
				
				
				av.oe_kurzbz,
				av.bezeichnung as akt_bezeichnung,
				CASE WHEN av.vertragsart_kurzbz = 'echterdv' THEN av.orgbezeichnung ELSE av.oeorgbezeichnung END as akt_orgbezeichnung,
				CASE WHEN av.vertragsart_kurzbz = 'echterdv' THEN av.parentbezeichnung ELSE av.oeorgparentbezeichnung END as akt_parentbezeichnung,
				akt_lehre_stundensatz.stundensatz as akt_stundensaetze_lehre,
				av.wochenstunden as akt_stunden,
				karenz.von as karenzvon,
				karenz.bis as karenzbis,
				vorname,
				nachname,
				tbl_benutzer.uid,
				(tbl_benutzer.uid || '@".DOMAIN."') AS email
			FROM tbl_mitarbeiter ma
				JOIN tbl_benutzer ON ma.mitarbeiter_uid = tbl_benutzer.uid
				JOIN tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
				LEFT JOIN aktVertrag av ON ma.mitarbeiter_uid = av.mitarbeiter_uid AND av.rn = 1
				LEFT JOIN zeitraumVertrag zv ON ma.mitarbeiter_uid = zv.mitarbeiter_uid AND zv.rn = 1
				LEFT JOIN akt_lehre_stundensatz ON ma.mitarbeiter_uid = akt_lehre_stundensatz.uid AND akt_lehre_stundensatz.rn = 1
				LEFT JOIN lehre_stundensatz ON ma.mitarbeiter_uid = lehre_stundensatz.uid AND lehre_stundensatz.rn = 1
				LEFT JOIN karenz ON ma.mitarbeiter_uid = karenz.mitarbeiter_uid AND karenz.rn = 1
			WHERE ma.mitarbeiter_uid IN ?
		";
		return $dbModel->execReadOnlyQuery($qry, array($dates, $uid));
	}

	public function getCategoryData($mitarbeiter_uids, $category_id, $studienjahr)
	{
		foreach ($this->config->item('annual_hours') as $case)
		{
			$caseStatements[] = "WHEN zv.oe_kurzbz ='" . $case['condition'] . "' THEN default_pk.default_stunden/" . $case['base_value'] . " * zv.einzelnejahresstunden";
		};

		$query = "
				". $this->_getStartCTE() .",
				". $this->_getStudienjahrDates() . ",
				". $this->_getZeitraumDaten() . "
				SELECT
				ROW_NUMBER() OVER () AS row_index,
				kategorie_mitarbeiter_id,
				tbl_mitarbeiter.mitarbeiter_uid,
				tbl_person.person_id,
				tbl_person.vorname,
				tbl_person.nachname,
				ROUND
					(COALESCE(pkm.stunden,
						CASE ". implode(" ", $caseStatements) . " END
				), 2) AS stunden,
				pkm.anmerkung,
				pkm.oe_kurzbz as category_oe_kurzbz
				FROM tbl_mitarbeiter
					JOIN tbl_benutzer ON tbl_mitarbeiter.mitarbeiter_uid = tbl_benutzer.uid
					JOIN tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
					LEFT JOIN zeitraumVertrag zv ON tbl_mitarbeiter.mitarbeiter_uid = zv.mitarbeiter_uid AND zv.rn = 1
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
				ORDER BY vorname, nachname, kategorie_mitarbeiter_id
		";


		return $this->execQuery($query, array($studienjahr, $studienjahr, $category_id, $category_id, $studienjahr, $studienjahr, $mitarbeiter_uids));
	}

	public function getMitarbeiterData($mitarbeiter_uid, $studiensemester = null, $studienjahr = null)
	{
		if (is_null($studienjahr))
			$dates = $studiensemester;
		else
			$dates = $studienjahr;

		$query = "
			". $this->_getStartCTE() .",
			". $this->_getAktuelleDaten() . ",
			". (is_null($studienjahr) ? $this->_getStudiensemesterDates() : $this->_getStudienjahrDates()) .",
			". $this->_getZeitraumDaten() .

			",
			akt_lehre_stundensatz AS (
						SELECT stundensatz,
								uid,
								ROW_NUMBER() OVER (PARTITION BY uid ORDER BY gueltig_von DESC, gueltig_bis DESC NULLS FIRST) AS rn
						FROM hr.tbl_stundensatz
						JOIN hr.tbl_stundensatztyp ON tbl_stundensatz.stundensatztyp = tbl_stundensatztyp.stundensatztyp
						AND (tbl_stundensatz.gueltig_von <= NOW() OR tbl_stundensatz.gueltig_von IS NULL)
						AND (tbl_stundensatz.gueltig_bis >= NOW() OR tbl_stundensatz.gueltig_bis IS NULL)
						AND tbl_stundensatz.stundensatztyp = 'lehre'
				)
			SELECT 
				av.oe_kurzbz,
				av.bezeichnung as akt_bezeichnung,
				CASE WHEN av.vertragsart_kurzbz = 'echterdv' THEN av.orgbezeichnung ELSE av.oeorgbezeichnung END as akt_orgbezeichnung,
				CASE WHEN av.vertragsart_kurzbz = 'echterdv' THEN av.parentbezeichnung ELSE av.oeorgparentbezeichnung END as akt_parentbezeichnung,
				av.wochenstunden as akt_stunden,
				zv.bezeichnung AS bezeichnung,
				zv.relevante_vertragsart as releavante_vertragsart,
				zv.oe_kurzbz as oe_kurzbz,
				zv.alle_vertraege as zrm_vertraege,
				zv.alle_vertraege_kurzbz as zrm_vertraege_kurzbz,
				zv.wochenstunden as zrm_wochenstunden,
				zv.jahresstunden as zrm_jahresstunden,
				zv.einzelnejahresstunden as zrm_einzeljahresstunden,
				vorname,
				nachname,
				ma.mitarbeiter_uid,
				akt_lehre_stundensatz.stundensatz as akt_stundensaetze_lehre
			

			FROM tbl_mitarbeiter ma
				JOIN tbl_benutzer ON ma.mitarbeiter_uid = tbl_benutzer.uid
				JOIN tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
				LEFT JOIN aktVertrag av ON ma.mitarbeiter_uid = av.mitarbeiter_uid AND av.rn = 1
				LEFT JOIN zeitraumVertrag zv ON ma.mitarbeiter_uid = zv.mitarbeiter_uid AND zv.rn = 1
				LEFT JOIN akt_lehre_stundensatz ON ma.mitarbeiter_uid = akt_lehre_stundensatz.uid AND akt_lehre_stundensatz.rn = 1
			WHERE
				ma.mitarbeiter_uid IN ?";

		return $this->execReadOnlyQuery($query, array($dates, isEmptyArray($mitarbeiter_uid) ? array('') : $mitarbeiter_uid));
	}

	public function getRelevanteVertragsart($uid, $studiensemester)
	{
		$query = "
			". $this->_getStartCTE() .",
			". $this->_getStudiensemesterDates() .",
			". $this->_getZeitraumDaten() . "
			SELECT 
				zv.relevante_vertragsart as releavante_vertragsart,
				vorname,
				nachname,
				ma.mitarbeiter_uid,
				tbl_benutzer.uid
			FROM tbl_mitarbeiter ma
				JOIN tbl_benutzer ON ma.mitarbeiter_uid = tbl_benutzer.uid
				JOIN tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
				LEFT JOIN zeitraumVertrag zv ON ma.mitarbeiter_uid = zv.mitarbeiter_uid AND zv.rn = 1
			WHERE
				ma.mitarbeiter_uid IN ?";
		return $this->execReadOnlyQuery($query, array($studiensemester, isEmptyArray($uid) ? array('') : $uid));
	}

	private function _getStartCTE()
	{
		return "WITH tmp AS (SELECT true)";
	}

	private function _getJahresstunden()
	{
		foreach ($this->config->item('annual_hours') as $case)
		{
			$caseStatements[] = "WHEN dv.oe_kurzbz = '" . $case['condition'] . "' THEN ROUND(". $case['base_value'] . "/" . number_format($case['hour_divisor'],2,'.','') . "* stunden.wochenstunden, 2)";
		}

		return $caseStatements;
	}
	private function _getAktuelleDaten()
	{
		$caseStatements = $this->_getJahresstunden();

		return "aktVertrag AS (
				SELECT dv.von,
					dv.mitarbeiter_uid,
					va.bezeichnung,
					dv.oe_kurzbz,
					dv.vertragsart_kurzbz,
					dv.dienstverhaeltnis_id,
					funktion.orgbezeichnung,
					funktion.parentbezeichnung,
					oefunktion.orgbezeichnung as oeorgbezeichnung,
					oefunktion.parentbezeichnung as oeorgparentbezeichnung,
					stunden.wochenstunden AS wochenstunden,
					CASE ". implode(" ", $caseStatements) . " END as jahresstunden,
					ROW_NUMBER() OVER (PARTITION BY dv.mitarbeiter_uid ORDER BY dv.von DESC, dv.bis DESC NULLS FIRST) AS rn
				FROM hr.tbl_dienstverhaeltnis dv
					JOIN hr.tbl_vertragsart va ON dv.vertragsart_kurzbz = va.vertragsart_kurzbz
					LEFT JOIN (
						SELECT vb.dienstverhaeltnis_id, vb.von, org.bezeichnung as orgbezeichnung, parentorg.bezeichnung as parentbezeichnung
						FROM hr.tbl_vertragsbestandteil vb
							JOIN hr.tbl_vertragsbestandteil_funktion USING (vertragsbestandteil_id)
							JOIN public.tbl_benutzerfunktion ON tbl_vertragsbestandteil_funktion.benutzerfunktion_id = tbl_benutzerfunktion.benutzerfunktion_id
							JOIN tbl_organisationseinheit org ON tbl_benutzerfunktion.oe_kurzbz = org.oe_kurzbz
							JOIN tbl_organisationseinheit parentorg ON org.oe_parent_kurzbz = parentorg.oe_kurzbz
						WHERE vb.von <= NOW()
							AND (vb.bis >= NOW() OR vb.bis IS NULL)
							AND funktion_kurzbz = 'kstzuordnung'
						ORDER BY vb.von DESC
					) funktion ON funktion.dienstverhaeltnis_id = dv.dienstverhaeltnis_id
					LEFT JOIN (
						SELECT vb.dienstverhaeltnis_id, vb.von, org.bezeichnung as orgbezeichnung, parentorg.bezeichnung as parentbezeichnung
						FROM hr.tbl_vertragsbestandteil vb
							JOIN hr.tbl_vertragsbestandteil_funktion USING (vertragsbestandteil_id)
							JOIN public.tbl_benutzerfunktion ON tbl_vertragsbestandteil_funktion.benutzerfunktion_id = tbl_benutzerfunktion.benutzerfunktion_id
							JOIN tbl_organisationseinheit org ON tbl_benutzerfunktion.oe_kurzbz = org.oe_kurzbz
							JOIN tbl_organisationseinheit parentorg ON org.oe_parent_kurzbz = parentorg.oe_kurzbz
						WHERE vb.von <= NOW()
							AND (vb.bis >= NOW() OR vb.bis IS NULL)
							AND funktion_kurzbz = 'oezuordnung'
						ORDER BY vb.von DESC
					) oefunktion ON oefunktion.dienstverhaeltnis_id = dv.dienstverhaeltnis_id
					LEFT JOIN (
						SELECT vb.dienstverhaeltnis_id, vbs.wochenstunden, vb.von
						FROM hr.tbl_vertragsbestandteil vb
							JOIN hr.tbl_vertragsbestandteil_stunden vbs USING(vertragsbestandteil_id)
						WHERE vb.von <= NOW()
							AND (vb.bis >= NOW() OR vb.bis IS NULL)
						ORDER BY vb.von DESC
					) stunden ON stunden.dienstverhaeltnis_id = dv.dienstverhaeltnis_id
				WHERE (dv.von <= NOW() OR dv.von IS NULL)
					AND (dv.bis >= NOW() OR dv.bis IS NULL)
			)";
	}

	private function _getStudienjahrDates()
	{
		return "semester_datum AS (
					SELECT MIN(start) as start,
						MAX(ende) as ende
					FROM public.tbl_studiensemester
					WHERE public.tbl_studiensemester.studienjahr_kurzbz = ?
				 )";
	}

	private function _getStudiensemesterDates()
	{
		return "semester_datum AS (
					SELECT MIN(start) as start,
						MAX(ende) as ende
					FROM public.tbl_studiensemester
					WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
				 )";
	}
	private function _getZeitraumDaten()
	{
		$caseStatements = $this->_getJahresstunden();

		return "
				zeitraumVertrag AS (
					SELECT
						dv.dienstverhaeltnis_id,
						dv.von,
						dv.bis,
						dv.mitarbeiter_uid,
						va.bezeichnung,
						va.vertragsart_kurzbz AS relevante_vertragsart,
						dv.oe_kurzbz,
						ARRAY_TO_STRING(ARRAY_AGG(va.bezeichnung) OVER (PARTITION BY dv.mitarbeiter_uid), E'\n') AS alle_vertraege,
						ARRAY_TO_STRING(ARRAY_AGG(va.vertragsart_kurzbz) OVER (PARTITION BY dv.mitarbeiter_uid), E'\n') AS alle_vertraege_kurzbz,
						ARRAY_TO_STRING(ARRAY_AGG(stunden.wochenstunden) OVER (PARTITION BY dv.mitarbeiter_uid ORDER BY dv.von DESC, dv.bis DESC NULLS FIRST), E'\n') AS wochenstunden,
						ARRAY_TO_STRING(ARRAY_AGG(
								CASE ". implode(" ", $caseStatements) . " END) OVER (PARTITION BY dv.mitarbeiter_uid ORDER BY dv.von DESC, dv.bis DESC NULLS FIRST),
								 E'\n'
						) AS jahresstunden,
						(
							CASE ". implode(" ", $caseStatements) . " END
						) as einzelnejahresstunden,
						ROW_NUMBER() OVER (PARTITION BY dv.mitarbeiter_uid ORDER BY dv.von DESC, dv.bis DESC NULLS FIRST) AS rn
					 FROM hr.tbl_dienstverhaeltnis dv
							JOIN hr.tbl_vertragsart va ON dv.vertragsart_kurzbz = va.vertragsart_kurzbz
							LEFT JOIN (
									SELECT vb.dienstverhaeltnis_id, vbs.wochenstunden, vb.von
									FROM hr.tbl_vertragsbestandteil vb
											JOIN hr.tbl_vertragsbestandteil_stunden vbs USING(vertragsbestandteil_id)
									WHERE vb.von <= (SELECT ende FROM semester_datum)
										AND (vb.bis >= (SELECT start FROM semester_datum) OR vb.bis IS NULL)
									ORDER BY vb.von DESC
					) stunden ON stunden.dienstverhaeltnis_id = dv.dienstverhaeltnis_id
					WHERE (dv.von <= (SELECT ende FROM semester_datum) OR dv.von IS NULL)
						AND (dv.bis >= (SELECT start FROM semester_datum) OR dv.bis IS NULL)
				)";
	}
}
