<?php

class PEP_LV_Entwicklung_model extends DB_Model
{


	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'extension.tbl_pep_lv_entwicklung';
		$this->pk = 'pep_lv_entwicklung_id';
		$this->hasSequence = true;
	}

	public function getLVEntwicklung($lv_array, $plan_array, $mitarbeiter_uids, $org, $recursive)
	{
		$qry =
			"WITH studienplan AS (
						 SELECT tbl_studienplan.*, tbl_studienplan_lehrveranstaltung.lehrveranstaltung_id, studiensemester_kurzbz
						 FROM lehre.tbl_studienplan
								  JOIN lehre.tbl_studienordnung USING(studienordnung_id)
								  JOIN lehre.tbl_studienplan_semester ON tbl_studienplan.studienplan_id = tbl_studienplan_semester.studienplan_id
								  JOIN lehre.tbl_studienplan_lehrveranstaltung ON tbl_studienplan.studienplan_id = tbl_studienplan_lehrveranstaltung.studienplan_id AND tbl_studienplan_semester.semester = tbl_studienplan_lehrveranstaltung.semester
								  JOIN public.tbl_studiensemester USING(studiensemester_kurzbz)
						 WHERE studiensemester_kurzbz IN ?
					 ), ".

			$this->_getLVs() .",
					tag_data AS (
						SELECT
							DISTINCT ON (tbl_notiz.notiz_id)
							tbl_notiz.notiz_id AS id,
							typ_kurzbz,
							array_to_json(tbl_notiz_typ.bezeichnung_mehrsprachig)->>0 AS beschreibung,
							tbl_notiz.text as notiz,
							tbl_notiz_typ.style,
							tbl_notiz.erledigt as done,
							pep_lv_entwicklung_id
						FROM
							extension.tbl_pep_lv_entwicklung_notiz
								JOIN public.tbl_notiz ON tbl_pep_lv_entwicklung_notiz.notiz_id = tbl_notiz.notiz_id
								JOIN public.tbl_notiz_typ ON tbl_notiz.typ = tbl_notiz_typ.typ_kurzbz
						ORDER BY tbl_notiz.notiz_id
					),
					tags_lv_entwicklung AS (
						SELECT
							pep_lv_entwicklung_id,
							array_to_json(array_agg(DISTINCT tag_data)) AS tags_json
						FROM tag_data
						GROUP BY pep_lv_entwicklung_id
					)
				SELECT tbl_lehrveranstaltung.lehrveranstaltung_id,
						tbl_lehrveranstaltung.bezeichnung as lvbezeichnung,
						tbl_pep_lv_entwicklung.pep_lv_entwicklung_id,
						COALESCE(tbl_pep_lv_entwicklung.studiensemester_kurzbz, alleLVs_distinct.studiensemester_kurzbz) as studiensemester_kurzbz,
						tbl_pep_lv_entwicklung.mitarbeiter_uid,
						tbl_pep_lv_entwicklung.rolle_kurzbz,
						tbl_pep_lv_entwicklung.stunden,
						tbl_pep_lv_entwicklung.werkvertrag_ects,
						tbl_pep_lv_entwicklung.status_kurzbz,
						tbl_pep_lv_entwicklung.anmerkung,
						tbl_pep_lv_entwicklung.weiterentwicklung,
						tbl_lehrveranstaltung.semester as lv_semester,
						tbl_lehrveranstaltung.kurzbz as lv_kurzbz,
						tbl_lehrveranstaltung.sprache as lv_sprache,
						tbl_lehrveranstaltung.lehrform_kurzbz as lv_lehrform_kurzbz,
						tbl_lehrveranstaltung.ects as lv_ects,

						 ". $this->_getSTGSelect() .", ".
						$this->_getOEBezeichnungSelect() . ", 
						 
						COALESCE(tags_lv_entwicklung.tags_json, '[]'::json) AS tags,
						CASE WHEN tbl_lehrveranstaltung.lehrtyp_kurzbz = 'tpl' THEN true ELSE false END as istemplate,
						CASE WHEN  studienplan_lvs.lehrveranstaltung_id IS NULL THEN TRUE ELSE FALSE END as geloescht,
						alleLVs_distinct.lehrveranstaltung_id as allelvsid,
						module.bezeichnung as modulbezeichnung
				FROM
					alleLVs_distinct
						FULL JOIN  extension.tbl_pep_lv_entwicklung using(lehrveranstaltung_id)
						JOIN lehre.tbl_lehrveranstaltung using(lehrveranstaltung_id)
						LEFT JOIN module USING(lehrveranstaltung_id)
						LEFT JOIN studienplan_lvs using(lehrveranstaltung_id)
						LEFT JOIN tags_lv_entwicklung ON tags_lv_entwicklung.pep_lv_entwicklung_id = tbl_pep_lv_entwicklung.pep_lv_entwicklung_id
						JOIN public.tbl_organisationseinheit oelv ON tbl_lehrveranstaltung.oe_kurzbz = oelv.oe_kurzbz
			WHERE (tbl_pep_lv_entwicklung.studiensemester_kurzbz IN ? OR tbl_pep_lv_entwicklung.pep_lv_entwicklung_id IS NULL)
				  AND (tbl_pep_lv_entwicklung.mitarbeiter_uid IN ? OR
						(EXISTS
							(
								 SELECT 1
									FROM lehre.tbl_lehrveranstaltung lv
										JOIN tbl_organisationseinheit lv_org ON lv.oe_kurzbz = lv_org.oe_kurzbz
										JOIN oes ON oes.oe_kurzbz = lv_org.oe_kurzbz
									WHERE lv.lehrveranstaltung_id = alleLVs_distinct.lehrveranstaltung_id
									   OR lv.lehrveranstaltung_id = alleLVs_distinct.lvwithtemplate
							)
						)
					)
			";
		$dbModel = new DB_Model();
		return $dbModel->execReadOnlyQuery($qry, array($lv_array, $org, $recursive, $plan_array, $mitarbeiter_uids));
	}

	public function getLVEntwicklungStundenByEmployee($uid, $studiensemester, $studienjahr = null)
	{
		$timeslot = is_null($studienjahr) ?  $studiensemester : $studienjahr;
		$query = "
			SELECT COALESCE(SUM(stunden)::int, 0) as stunden
			FROM extension.tbl_pep_lv_entwicklung
			WHERE
				mitarbeiter_uid = ? AND studiensemester_kurzbz 
		";

		if (is_null($studienjahr))
			$query .= " = ?";
		else
			$query .= " IN (SELECT tbl_studiensemester.studiensemester_kurzbz FROM tbl_studiensemester WHERE studienjahr_kurzbz = ?)";

		return $this->execReadOnlyQuery($query, array($uid, $timeslot));
	}

	public function getFutureLvs($studiensemester_lv_array, $org, $recursive)
	{
		$qry =
			"WITH studienplan AS (
						 SELECT tbl_studienplan.*, tbl_studienplan_lehrveranstaltung.lehrveranstaltung_id, studiensemester_kurzbz
						 FROM lehre.tbl_studienplan
								  JOIN lehre.tbl_studienordnung USING(studienordnung_id)
								  JOIN lehre.tbl_studienplan_semester ON tbl_studienplan.studienplan_id = tbl_studienplan_semester.studienplan_id
								  JOIN lehre.tbl_studienplan_lehrveranstaltung ON tbl_studienplan.studienplan_id = tbl_studienplan_lehrveranstaltung.studienplan_id AND tbl_studienplan_semester.semester = tbl_studienplan_lehrveranstaltung.semester
								  JOIN public.tbl_studiensemester USING(studiensemester_kurzbz)
						 WHERE studiensemester_kurzbz IN (SELECT studiensemester_kurzbz FROM tbl_studiensemester WHERE start > (SELECT MAX(start) FROM tbl_studiensemester WHERE studiensemester_kurzbz IN ?) )
					 ), ".

			$this->_getLVs() ."
				
			SELECT ". $this->_getSTGSelect() .",
					tbl_lehrveranstaltung.lehrveranstaltung_id,
					tbl_lehrveranstaltung.bezeichnung as lvbezeichnung
			FROM alleLVs_distinct
				JOIN lehre.tbl_lehrveranstaltung USING(lehrveranstaltung_id)
			WHERE EXISTS
					(
						SELECT 1
						FROM lehre.tbl_lehreinheit
							JOIN lehre.tbl_lehrveranstaltung lv ON lv.lehrveranstaltung_id = tbl_lehreinheit.lehrfach_id
							JOIN tbl_organisationseinheit lv_org ON lv_org.oe_kurzbz = lv.oe_kurzbz
							JOIN oes ON oes.oe_kurzbz = lv_org.oe_kurzbz
						WHERE tbl_lehreinheit.lehrveranstaltung_id = alleLVs_distinct.lehrveranstaltung_id OR tbl_lehreinheit.lehrveranstaltung_id = alleLVs_distinct.lvwithtemplate
					)
		";

		return $this->execReadOnlyQuery($qry, array($studiensemester_lv_array, $org, $recursive));
	}

	public function getLVInfos($lehrveranstaltung_id)
	{
		$qry = "WITH
				alleLVs AS (
					SELECT
						lv.lehrveranstaltung_id,
						lv.bezeichnung
					FROM lehre.tbl_lehrveranstaltung lv
					WHERE lehrveranstaltung_id = ?
				),
				module AS (
					SELECT
						STRING_AGG(DISTINCT(tbl_lehrveranstaltung.bezeichnung), ' \n') as bezeichnung,
						alleLVs.lehrveranstaltung_id
					FROM lehre.tbl_studienplan_lehrveranstaltung splv
						JOIN alleLVs ON splv.lehrveranstaltung_id = alleLVs.lehrveranstaltung_id
						JOIN lehre.tbl_studienplan_lehrveranstaltung parentsplv ON splv.studienplan_lehrveranstaltung_id_parent = parentsplv.studienplan_lehrveranstaltung_id
						JOIN lehre.tbl_lehrveranstaltung ON parentsplv.lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id
					GROUP BY alleLVs.lehrveranstaltung_id
				)
				SELECT ". $this->_getSTGSelect() .", ".
						$this->_getOEBezeichnungSelect() . ", 
					tbl_lehrveranstaltung.lehrveranstaltung_id,
					tbl_lehrveranstaltung.bezeichnung as lvbezeichnung,
					CASE WHEN tbl_lehrveranstaltung.lehrtyp_kurzbz = 'tpl' THEN true ELSE false END as istemplate,
					module.bezeichnung as modulbezeichnung,
					tbl_lehrveranstaltung.kurzbz as lv_kurzbz,
					tbl_lehrveranstaltung.sprache as lv_sprache,
					tbl_lehrveranstaltung.lehrform_kurzbz as lv_lehrform_kurzbz,
					tbl_lehrveranstaltung.ects as lv_ects
				FROM
					alleLVs
					JOIN lehre.tbl_lehrveranstaltung using(lehrveranstaltung_id)
					LEFT JOIN module USING(lehrveranstaltung_id)
					JOIN public.tbl_organisationseinheit oelv ON tbl_lehrveranstaltung.oe_kurzbz = oelv.oe_kurzbz";

		return $this->execReadOnlyQuery($qry, array($lehrveranstaltung_id));
	}

	private function _getOEBezeichnungSelect()
	{
		return "CASE
					WHEN oelv.organisationseinheittyp_kurzbz = 'Kompetenzfeld' THEN ('KF ' || oelv.bezeichnung)
					WHEN oelv.organisationseinheittyp_kurzbz = 'Department' THEN ('DEP ' || oelv.bezeichnung)
				ELSE (oelv.organisationseinheittyp_kurzbz || ' ' || oelv.bezeichnung)
				END AS lv_oe_bezeichnung";
	}
	private function _getSTGSelect()
	{
		return "CASE WHEN (tbl_lehrveranstaltung.lehrtyp_kurzbz = 'tpl') THEN
					(
						SELECT STRING_AGG(DISTINCT(upper(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz)), ' ')
						FROM lehre.tbl_studienplan
							JOIN lehre.tbl_studienordnung USING(studienordnung_id)
							JOIN lehre.tbl_studienplan_semester ON tbl_studienplan.studienplan_id = tbl_studienplan_semester.studienplan_id
							JOIN lehre.tbl_studienplan_lehrveranstaltung ON tbl_studienplan.studienplan_id = tbl_studienplan_lehrveranstaltung.studienplan_id AND tbl_studienplan_semester.semester = tbl_studienplan_lehrveranstaltung.semester
							JOIN public.tbl_studiensemester USING(studiensemester_kurzbz)
							JOIN public.tbl_studiengang USING(studiengang_kz)
							WHERE lehrveranstaltung_id IN (SELECT lehrveranstaltung_id FROM lehre.tbl_lehrveranstaltung slehrveranstaltung WHERE slehrveranstaltung.lehrveranstaltung_template_id = tbl_lehrveranstaltung.lehrveranstaltung_id)
					)
					ELSE (
						SELECT STRING_AGG(DISTINCT(upper(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz)), ' ')
						FROM lehre.tbl_studienplan
							JOIN lehre.tbl_studienordnung USING(studienordnung_id)
							JOIN lehre.tbl_studienplan_semester ON tbl_studienplan.studienplan_id = tbl_studienplan_semester.studienplan_id
							JOIN lehre.tbl_studienplan_lehrveranstaltung ON tbl_studienplan.studienplan_id = tbl_studienplan_lehrveranstaltung.studienplan_id AND tbl_studienplan_semester.semester = tbl_studienplan_lehrveranstaltung.semester
							JOIN public.tbl_studiensemester USING(studiensemester_kurzbz)
							JOIN public.tbl_studiengang USING(studiengang_kz)
						WHERE lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id
					) END as stg_kuerzel";
	}
	private function _getLVs()
	{
		return "oes AS (
					WITH RECURSIVE oes(oe_kurzbz, oe_parent_kurzbz) AS (
						SELECT oe_kurzbz,
							oe_parent_kurzbz
						FROM PUBLIC.tbl_organisationseinheit
						WHERE oe_kurzbz = ?
				
							UNION ALL
				
							SELECT o.oe_kurzbz, o.oe_parent_kurzbz
							FROM public.tbl_organisationseinheit o
									 JOIN oes ON o.oe_parent_kurzbz = oes.oe_kurzbz
							WHERE ?
						)
						SELECT oe_kurzbz
						FROM oes
						GROUP BY oe_kurzbz
					),
					templates AS (
						SELECT
							tbl_lehrveranstaltung.*
						FROM
							lehre.tbl_lehrveranstaltung AS tpl_lv
								JOIN lehre.tbl_lehrveranstaltung ON tpl_lv.lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_template_id
						WHERE tpl_lv.lehrtyp_kurzbz = 'tpl'
					),
					 allWithoutTemplates AS (
						 SELECT *
						 FROM lehre.tbl_lehrveranstaltung
						 WHERE lehrveranstaltung_template_id IS NULL
						   AND lehrtyp_kurzbz = 'lv'
					 ),
					 templateslvs AS (
						 SELECT DISTINCT ON(tbl_lehrveranstaltung.lehrveranstaltung_id)
							 tbl_lehrveranstaltung.lehrveranstaltung_id,
							 tbl_lehrveranstaltung.bezeichnung,
							 studienplan.studiensemester_kurzbz,
							 templates.lehrveranstaltung_id as lvwithtemplate,
							 studienplan.studienplan_id
						 FROM
							 studienplan
								 JOIN templates ON studienplan.lehrveranstaltung_id = templates.lehrveranstaltung_id
								 JOIN lehre.tbl_lehrveranstaltung ON templates.lehrveranstaltung_template_id = tbl_lehrveranstaltung.lehrveranstaltung_id
					 ),
					pep_lvs AS (
						SELECT
							pep.lehrveranstaltung_id,
							pep.studiensemester_kurzbz
						FROM extension.tbl_pep_lv_entwicklung pep
						WHERE NOT EXISTS (
							SELECT 1 FROM studienplan sp
							WHERE sp.lehrveranstaltung_id = pep.lehrveranstaltung_id
						)
					),
					studienplan_lvs AS (
						SELECT allWithoutTemplates.lehrveranstaltung_id,
							allWithoutTemplates.bezeichnung,
							studienplan.studiensemester_kurzbz,
							null as lvwithtemplate,
							studienplan.studienplan_id
						FROM
							studienplan
								LEFT JOIN allWithoutTemplates USING(lehrveranstaltung_id)
						UNION
						SELECT templateslvs.lehrveranstaltung_id,
							templateslvs.bezeichnung,
							templateslvs.studiensemester_kurzbz,
							lvwithtemplate,
							studienplan_id
						FROM
							templateslvs
					),
					
					alleLVs AS (
						SELECT
							lv.lehrveranstaltung_id,
							lv.bezeichnung,
							pep_lvs.studiensemester_kurzbz,
							NULL AS lvwithtemplate,
							NULL AS studienplan_id
						 FROM pep_lvs
							JOIN lehre.tbl_lehrveranstaltung lv USING (lehrveranstaltung_id)
						
						UNION
						
						SELECT *
						FROM studienplan_lvs
					),
					alleLVs_distinct AS (
						SELECT DISTINCT ON (lehrveranstaltung_id)
							lehrveranstaltung_id,
							bezeichnung,
							studiensemester_kurzbz,
							lvwithtemplate
						FROM alleLVs
						ORDER BY lehrveranstaltung_id, studienplan_id
					),
					module AS (
						SELECT
							STRING_AGG(DISTINCT(tbl_lehrveranstaltung.bezeichnung), ' \n') as bezeichnung,
							alleLVs.lehrveranstaltung_id
						FROM lehre.tbl_studienplan_lehrveranstaltung splv
								 JOIN alleLVs ON splv.lehrveranstaltung_id = alleLVs.lehrveranstaltung_id OR splv.lehrveranstaltung_id = alleLVs.lvwithtemplate AND splv.studienplan_id = alleLVs.studienplan_id
								 JOIN lehre.tbl_studienplan_lehrveranstaltung parentsplv ON splv.studienplan_lehrveranstaltung_id_parent = parentsplv.studienplan_lehrveranstaltung_id
								 JOIN lehre.tbl_lehrveranstaltung ON parentsplv.lehrveranstaltung_id = tbl_lehrveranstaltung.lehrveranstaltung_id
						GROUP BY alleLVs.lehrveranstaltung_id
					)
		";
	}
}
