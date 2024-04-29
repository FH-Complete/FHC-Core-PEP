<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class PEP extends FHC_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'extension/pep';
	
	public function __construct()
	{
		parent::__construct();
		
		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studiengang_model', 'StudiengangModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('person/Benutzerfunktion_model', 'BenutzerfunktionModel');
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		
		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_setAuthUID();
		
	}
	
	//------------------------------------------------------------------------------------------------------------------
	// Public methods
	public function getCategoryData()
	{
		$category = $this->_ci->input->get('category_id');
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		$recursive = $this->_ci->input->get('recursive');

		$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz');
		$studiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($studiensemestern))
			return $this->outputJsonSuccess('');
		$studiensemestern = array_column(getData($studiensemestern), 'studiensemester_kurzbz');

		$allMitarbeiter = $this->_getMitarbeiter($org, $studiensemestern, $recursive === "true");

		$mitarbeiter_uids = array();
		if (hasData($allMitarbeiter))
			$mitarbeiter_uids = array_column(getData($allMitarbeiter), 'uid');

		$categoryData = $this->_ci->PEPModel->getCategoryData($mitarbeiter_uids, $category, $studienjahr);

		$allMitarbeiterData = [];
		foreach (getData($categoryData) as $empCategoryData)
		{
			$mitarbeiterData = $empCategoryData;

			$dvs = getData($this->_getDVs($empCategoryData->mitarbeiter_uid, $studiensemestern))[0];
			$mitarbeiterData->vertraege = isset($dvs->vertraege) ? $dvs->vertraege : '-';
			$mitarbeiterData->wochenstundenstunden = isset($dvs->wochenstundenstunden) ? $dvs->wochenstundenstunden : '-';
			$mitarbeiterData->jahresstunden = isset($dvs->jahresstunden) ? $dvs->jahresstunden : '-';
			$allMitarbeiterData[] = $mitarbeiterData;
		}

		$this->outputJsonSuccess($allMitarbeiterData);
	}
	public function getStudienjahr()
	{
		$this->_ci->StudienjahrModel->addOrder('studienjahr_kurzbz', 'DESC');
		$semester = $this->_ci->StudienjahrModel->load();
		
		$this->outputJsonSuccess(getData($semester));
	}
	
	public function getStudiensemester()
	{
		$this->_ci->StudiensemesterModel->addOrder("start", "DESC");
		$studiensemester = $this->_ci->StudiensemesterModel->load();
		$this->outputJsonSuccess(getData($studiensemester));
	}

	public function getOrg()
	{
		$oeKurzbz = $this->_ci->permissionlib->getOE_isEntitledFor(self::BERECHTIGUNG_KURZBZ);
		$db_Model = new DB_Model();
		$result = $db_Model->execReadOnlyQuery("SELECT organisationseinheittyp_kurzbz, bezeichnung, oe_kurzbz
											FROM public.tbl_organisationseinheit
											WHERE oe_kurzbz IN ('". implode("','", $oeKurzbz) . "')
											ORDER BY organisationseinheittyp_kurzbz
											");
		$this->outputJsonSuccess(getData($result));
	}
	
	public function loadReport()
	{
		$org = $this->_ci->input->get('org');
		$studiensemester = $this->_ci->input->get('studiensemester');
		$recursive = $this->_ci->input->get('recursive');

		if (isEmptyString($org) || isEmptyString($studiensemester))
			$this->terminateWithJsonError('Bitte alle Felder ausfüllen');
		$allMitarbeiter = $this->_getMitarbeiter($org, $studiensemester, $recursive === "true");
		
		if (!hasData($allMitarbeiter))
			$this->terminateWithJsonError("Keine Daten gefunden");

		$mitarbeiterDataArray = array();
		
		foreach (getData($allMitarbeiter) as $mitarbeiter)
		{
			$mitarbeiterData = $mitarbeiter;

			$mitarbeiterData->dv = $this->_getDVs($mitarbeiter->uid, $studiensemester);

			$dienstverhaeltnis = getData($mitarbeiterData->dv)[0];
			$mitarbeiterData = $dienstverhaeltnis;
			$mitarbeiterData->vorname = $mitarbeiter->vorname;
			$mitarbeiterData->nachname = $mitarbeiter->nachname;
			$mitarbeiterData->uid = $mitarbeiter->uid;

			//$mitarbeiterData->vertraege = $dienstverhaeltnis->vertraege;

			//$mitarbeiterData->aktuelles_dv = $this->_getAktuellstesDV($mitarbeiter->uid);
			$karenz = $this->_getKarenz($mitarbeiter->uid);
			$mitarbeiterData->karenz = $karenz ? $karenz : false;

			if (isset($dienstverhaeltnis->jahresstunden))
			{
				$stunden = $dienstverhaeltnis->jahresstunden;
				if (count($studiensemester) === 1)
				{
					$mitarbeiterData->summe = round($stunden / 2, 2);

				}
				else
				{
					$mitarbeiterData->summe = $stunden;
				}

				$mitarbeiterData->summe = number_format($mitarbeiterData->summe, 2, '.','');
			}
			foreach ($studiensemester as $key => $ststem)
			{
				$lehrauftrag =  ($this->_getLehrauftraege($mitarbeiter->uid, $ststem));
				$keyname = "studiensemester_" . $key . "_lehrauftrag";


				$stunden = 0;
				if (hasData($lehrauftrag))
					$stunden = getData($lehrauftrag)[0]->stunden;
				$mitarbeiterData->$keyname = $stunden;
			}

			if (isset($dienstverhaeltnis->releavante_vertragsart))
			{
				foreach ($studiensemester as $key => $ststem)
				{
					$kategorien = $this->_ci->PEPModel->getStundenByMitarbeiter($mitarbeiter->uid, $ststem);

					if (hasData($kategorien))
					{
						foreach(getData($kategorien) as $kategorie)
						{
							$keyname = "studiensemester_" . $key . "_kategorie_" . $kategorie->kategorie_id;
							$mitarbeiterData->$keyname = ($kategorie->stunden/2);
						}
					}
				}
			}


			$mitarbeiterData->semester = $studiensemester;

			$mitarbeiterDataArray[] = $mitarbeiterData;
		}
		
		$this->_ci->PEPModel->addSelect('kategorie_id, array_to_json(bezeichnung_mehrsprachig::varchar[])->>0 as beschreibung');
		$config_kategorien = getData($this->_ci->PEPModel->load());
		$mitarbeiterDataArray[] = array('configs' => array('kategorien' => $config_kategorien, 'semester' => $studiensemester));

		$this->outputJsonSuccess($mitarbeiterDataArray);
	}

	private function _getAktuellenStundensatz($uid, $typ)
	{
		$dbModel = new DB_Model();
		$qry = "
		SELECT *
		FROM hr.tbl_stundensatz
		JOIN hr.tbl_stundensatztyp ON tbl_stundensatz.stundensatztyp = tbl_stundensatztyp.stundensatztyp
			AND (tbl_stundensatz.gueltig_von <= NOW() OR tbl_stundensatz.gueltig_von IS NULL)
			AND (tbl_stundensatz.gueltig_bis >= NOW() OR tbl_stundensatz.gueltig_bis IS NULL)
			AND tbl_stundensatz.stundensatztyp = '". $typ ."'
		WHERE uid = '". $uid ."'
		ORDER BY gueltig_von DESC NULLS LAST LIMIT 1
		";
		
		return $dbModel->execReadOnlyQuery($qry);
	}
	
	private function _getStundensatze($uid, $studiensemester, $typ)
	{
		$dbModel = new DB_Model();
		$qry = "
		SELECT *
		FROM hr.tbl_stundensatz
		JOIN hr.tbl_stundensatztyp ON tbl_stundensatz.stundensatztyp = tbl_stundensatztyp.stundensatztyp
			AND tbl_stundensatz.stundensatztyp = ?
		WHERE uid = ?
		AND (
					gueltig_von <= (
						SELECT MIN(start)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR gueltig_von IS NULL
				)
				AND
				(
					gueltig_bis >= (
						SELECT MAX(ende)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR gueltig_bis IS NULL
				)
		ORDER BY gueltig_von DESC NULLS LAST
		";
		
		return $dbModel->execReadOnlyQuery($qry, array($typ, $uid, $studiensemester, $studiensemester));
	}

	private function _getKarenz($uid)
	{
		$dbModel = new DB_Model();
		$qry = "
			SELECT hr.tbl_vertragsbestandteil.*
			FROM hr.tbl_dienstverhaeltnis
				JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
				WHERE mitarbeiter_uid = '". $uid ."'
					AND (tbl_vertragsbestandteil.von <= NOW() OR tbl_vertragsbestandteil.von IS NULL)
					AND (tbl_vertragsbestandteil.bis >= NOW() OR tbl_vertragsbestandteil.bis IS NULL)
			AND vertragsbestandteiltyp_kurzbz = 'karenz'
			ORDER BY tbl_vertragsbestandteil.von DESC NULLS LAST
				LIMIT 1
		";
		
		return getData($dbModel->execReadOnlyQuery($qry));
	}

	private function _getLehrauftraege($uid, $studiensemester)
	{
		$dbModel = new DB_Model();
		$qry = "
			SELECT SUM(tbl_lehreinheitmitarbeiter.semesterstunden) as stunden
			FROM lehre.tbl_lehreinheitmitarbeiter
				JOIN lehre.tbl_lehreinheit USING (lehreinheit_id)
				JOIN lehre.tbl_lehrveranstaltung USING(lehrveranstaltung_id)
			WHERE studiensemester_kurzbz = ?
				AND mitarbeiter_uid = ?
			";
		
		return $dbModel->execReadOnlyQuery($qry, array($studiensemester, $uid));
		
	}

	private function _getAktuellstesDV($uid)
	{
		$dbModel = new DB_Model();
		$qry = "
			SELECT *
			FROM hr.tbl_dienstverhaeltnis dv
			JOIN hr.tbl_vertragsart ON dv.vertragsart_kurzbz = tbl_vertragsart.vertragsart_kurzbz
			WHERE dv.mitarbeiter_uid = '". $uid ."'
				AND (dv.von <= NOW() OR dv.von IS NULL)
				AND (dv.bis >= NOW() OR dv.bis IS NULL)
			ORDER BY dv.von DESC NULLS LAST
				LIMIT 1
		";

		$aktuellstesDV = $dbModel->execReadOnlyQuery($qry);
		
		if (hasData($aktuellstesDV))
		{
			$aktuellstesDV = getData($aktuellstesDV)[0];
			$aktuellstesDV->kststelle = getData($this->_getLastFunktionFromDV($aktuellstesDV->dienstverhaeltnis_id, 'kstzuordnung'))[0];
			$aktuellstesDV->stunden = getData($this->_getLastStundenFromDV($aktuellstesDV->dienstverhaeltnis_id))[0];
		}
		
		return $aktuellstesDV;
	}

	private function _getLastFunktionFromDV($dv_id, $funktion)
	{
		$dbModel = new DB_Model();
		$qry = "
		SELECT parentorg.bezeichnung as parentbezeichnung,
				org.bezeichnung as orgbezeichnung,
				tbl_vertragsbestandteil.von,
				tbl_vertragsbestandteil.bis
		FROM hr.tbl_dienstverhaeltnis
			JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
			JOIN hr.tbl_vertragsbestandteil_funktion USING (vertragsbestandteil_id)
			JOIN public.tbl_benutzerfunktion ON tbl_vertragsbestandteil_funktion.benutzerfunktion_id = tbl_benutzerfunktion.benutzerfunktion_id
			JOIN tbl_organisationseinheit org ON tbl_benutzerfunktion.oe_kurzbz = org.oe_kurzbz
			JOIN tbl_organisationseinheit parentorg ON org.oe_parent_kurzbz = parentorg.oe_kurzbz
		WHERE dienstverhaeltnis_id = '". $dv_id ."'
			AND funktion_kurzbz = '" . $funktion . "'
			AND (tbl_vertragsbestandteil.von <= NOW() OR tbl_vertragsbestandteil.von IS NULL)
			AND (tbl_vertragsbestandteil.bis >= NOW() OR tbl_vertragsbestandteil.bis IS NULL)
		ORDER BY tbl_vertragsbestandteil.von desc NULLS LAST
		LIMIT 1
		";
		
		return $dbModel->execReadOnlyQuery($qry);
	}
	
	private function _getLastStundenFromDV($dv_id)
	{
		$dbModel = new DB_Model();
		$qry = "
			SELECT *
			FROM hr.tbl_dienstverhaeltnis
				JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
				JOIN hr.tbl_vertragsbestandteil_stunden USING (vertragsbestandteil_id)
			WHERE dienstverhaeltnis_id = '". $dv_id ."'
			  AND (tbl_vertragsbestandteil.von <= NOW() OR tbl_vertragsbestandteil.von IS NULL)
			  AND (tbl_vertragsbestandteil.bis >= NOW() OR tbl_vertragsbestandteil.bis IS NULL)
			ORDER BY tbl_vertragsbestandteil.von desc NULLS LAST
			LIMIT 1;
		";
		
		return $dbModel->execReadOnlyQuery($qry);
	}
	
	public function lehreReport()
	{
		$org = $this->_ci->input->get('org');
		$studiensemester = $this->_ci->input->get('studiensemester');
		$recursive = $this->_ci->input->get('recursive');


		if (isEmptyString($org) || isEmptyString($studiensemester))
			$this->terminateWithJsonError('Bitte alle Felder ausfüllen');
		
		$allMitarbeiter = $this->_getLehreMitarbeiter($org, $studiensemester, $recursive === "true");
		
		if (!hasData($allMitarbeiter))
			$this->terminateWithJsonError("Keine Daten gefunden");
		
		$mitarbeiterDataArray = array();
		
		$allMitarbeiter = getData($allMitarbeiter);
		$uniqueMitarbeiter = array_unique(array_column($allMitarbeiter, 'uid'));
		
		$mitarbeiterInfos = [];
		foreach ($uniqueMitarbeiter as $mitarbeiter)
		{
			$mitarbeiterInfos[$mitarbeiter] = new stdClass();
			$mitarbeiterInfos[$mitarbeiter]->dv = $this->_getDVs($mitarbeiter, $studiensemester);

			$dienstverhaeltnis = getData($this->_getDVs($mitarbeiter, $studiensemester))[0];
			//$mitarbeiterInfos[$mitarbeiter]->aktuelles_dv = $this->_getAktuellstesDV($mitarbeiter);
			$mitarbeiterInfos[$mitarbeiter]->vertraege = isset($dienstverhaeltnis->vertraege) ? $dienstverhaeltnis->vertraege : '-';
			$mitarbeiterInfos[$mitarbeiter]->wochenstundenstunden = isset($dienstverhaeltnis->wochenstundenstunden) ? $dienstverhaeltnis->wochenstundenstunden : '-';
			$mitarbeiterInfos[$mitarbeiter]->aktbezeichnung = isset($dienstverhaeltnis->aktbezeichnung) ? $dienstverhaeltnis->aktbezeichnung : '-';
			$mitarbeiterInfos[$mitarbeiter]->aktorgbezeichnung = isset($dienstverhaeltnis->aktorgbezeichnung) ? $dienstverhaeltnis->aktorgbezeichnung : '-';

			$mitarbeiterInfos[$mitarbeiter]->aktparentbezeichnung = isset($dienstverhaeltnis->aktparentbezeichnung) ? $dienstverhaeltnis->aktparentbezeichnung : '-';
			$mitarbeiterInfos[$mitarbeiter]->aktstunden = isset($dienstverhaeltnis->aktstunden) ? $dienstverhaeltnis->aktstunden : '-';

			//$mitarbeiterInfos[$mitarbeiter]->stundensaetze_lehre_aktuell = getData($this->_getAktuellenStundensatz($mitarbeiter, 'lehre'))[0];
			$mitarbeiterInfos[$mitarbeiter]->stundensaetze_lehre_aktuell = isset($dienstverhaeltnis->stundensaetze_lehre_aktuell) ? $dienstverhaeltnis->stundensaetze_lehre_aktuell : '-';
			$mitarbeiterInfos[$mitarbeiter]->stundensaetze_lehre = isset($dienstverhaeltnis->stundensaetze_lehre) ? $dienstverhaeltnis->stundensaetze_lehre : '-';
			/*$mitarbeiterInfos[$mitarbeiter]->stundensaetze_lehre = getData($this->_getStundensatze($mitarbeiter, $studiensemester, 'lehre'));*/
		}
		
		foreach ($allMitarbeiter as $mitarbeiter)
		{
			$mitarbeiterData = $mitarbeiter;
			$mitarbeiterData->vertraege = $mitarbeiterInfos[$mitarbeiter->uid]->vertraege;
			$mitarbeiterData->aktorgbezeichnung = $mitarbeiterInfos[$mitarbeiter->uid]->aktorgbezeichnung;
			$mitarbeiterData->aktparentbezeichnung = $mitarbeiterInfos[$mitarbeiter->uid]->aktparentbezeichnung;
			$mitarbeiterData->aktstunden = $mitarbeiterInfos[$mitarbeiter->uid]->aktstunden;
			$mitarbeiterData->wochenstundenstunden = $mitarbeiterInfos[$mitarbeiter->uid]->wochenstundenstunden;
			$mitarbeiterData->aktbezeichnung = $mitarbeiterInfos[$mitarbeiter->uid]->aktbezeichnung;
			$mitarbeiter->stundensaetze_lehre_aktuell = $mitarbeiterInfos[$mitarbeiter->uid]->stundensaetze_lehre_aktuell;
			$mitarbeiter->stundensaetze_lehre = $mitarbeiterInfos[$mitarbeiter->uid]->stundensaetze_lehre;
			$mitarbeiterDataArray[] = $mitarbeiterData;
		}
		$this->outputJsonSuccess($mitarbeiterDataArray);
	}

	private function _getLehreMitarbeiter($org, $studiensemester, $recursive)
	{
		$mitarbeiter_uids = $this->_getMitarbeiter($org, $studiensemester, $recursive);
		if (hasData($mitarbeiter_uids))
			$mitarbeiter_uids = array_column(getData($mitarbeiter_uids), 'uid');
		else
			$mitarbeiter_uids = array('');
		$dbModel = new DB_Model();
		$qry = "
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
			tbl_lehreinheitmitarbeiter.mitarbeiter_uid as uid,
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
			lv_org.bezeichnung as lv_oe
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
			lv_org.oe_kurzbz IN (
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
			OR tbl_lehreinheitmitarbeiter.mitarbeiter_uid IN ?
		)
		GROUP BY
			tbl_lehreinheitmitarbeiter.mitarbeiter_uid,
			tbl_mitarbeiter.kurzbz,
			tbl_mitarbeiter.mitarbeiter_uid,
			tbl_person.vorname,
			tbl_person.nachname,
			tbl_lehreinheit.lehreinheit_id,
			tbl_lehreinheit.lehrveranstaltung_id,
			tbl_lehrveranstaltung.bezeichnung,
			tbl_lehrveranstaltung.oe_kurzbz,
			tbl_lehreinheitgruppe.semester,
			tbl_lehreinheit.studiensemester_kurzbz,
			tbl_lehreinheitmitarbeiter.semesterstunden,
			tbl_lehreinheitmitarbeiter.stundensatz,
			tbl_lehreinheit.lehrform_kurzbz,
			lv_org.oe_kurzbz
		ORDER BY tbl_lehreinheit.lehrveranstaltung_id";
		return $dbModel->execReadOnlyQuery($qry, array($studiensemester, $org, $mitarbeiter_uids));
	}

	private function _getMitarbeiter($org, $studiensemester, $recursive)
	{
		$dbModel = new DB_Model();

		$qry = "SELECT
					DISTINCT ON (ma.uid)
					lektor,
					vorname,
					nachname,
					ma.uid/*,
				    (
						SELECT kontakt
						FROM PUBLIC.tbl_kontakt
						WHERE person_id = ma.person_id
							AND kontakttyp = 'email'
						ORDER BY zustellung ASC,
							insertamum DESC LIMIT 1
					) AS email*/
				FROM campus.vw_mitarbeiter ma
				JOIN hr.tbl_dienstverhaeltnis dv ON ma.uid = dv.mitarbeiter_uid
				JOIN hr.tbl_vertragsart vertragsart USING(vertragsart_kurzbz)
				JOIN hr.tbl_vertragsbestandteil bestandteil ON dv.dienstverhaeltnis_id = bestandteil.dienstverhaeltnis_id
				JOIN hr.tbl_vertragsbestandteil_funktion vbstfunktion ON bestandteil.vertragsbestandteil_id = vbstfunktion.vertragsbestandteil_id
				LEFT JOIN public.tbl_benutzerfunktion funktion ON vbstfunktion.benutzerfunktion_id = funktion.benutzerfunktion_id
				WHERE funktion_kurzbz IN('kstzuordnung', 'oezuordnung')
				AND (
					bestandteil.von <= (
						SELECT MIN(start)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR bestandteil.von IS NULL
				)
				AND
				(
					bestandteil.bis >= (
						SELECT MIN(ende)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR bestandteil.bis IS NULL
				)
		";

		if ($recursive)
		{
			$qry .= "
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
				)";
		}
		else
		{
			$qry .= " AND funktion.oe_kurzbz = ?";
		}
		
		$mitarbeiter = $dbModel->execReadOnlyQuery($qry, array($studiensemester, $studiensemester, $org));
		
		return ($mitarbeiter);
	}
	
	private function _getDVs($uid, $studiensemester)
	{
		$dbModel = new DB_Model();
		/*$qry = "SELECT dv.von,
						dv.bis,
						dv.dienstverhaeltnis_id,
						bezeichnung,
						vertragsart_kurzbz
				FROM hr.tbl_dienstverhaeltnis dv
				JOIN hr.tbl_vertragsart USING (vertragsart_kurzbz)
				WHERE (
					dv.von <= (
						SELECT MIN(start)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR dv.von IS NULL
				)
				AND
				(
					dv.bis >= (
						SELECT MAX(ende)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR dv.bis IS NULL
				)
				AND dv.mitarbeiter_uid = ?
		";*/

		$qry = "
			WITH semester_datum AS (
				SELECT MIN(start) as start,
						MAX(ende) as ende
				FROM public.tbl_studiensemester
				WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
			),
				relevante_dvs AS (
					SELECT dv.dienstverhaeltnis_id,
							dv.von, 
							dv.bis, 
							dv.mitarbeiter_uid,
							va.bezeichnung,
							va.vertragsart_kurzbz as releavante_vertragsart,
							oe_kurzbz
					FROM hr.tbl_dienstverhaeltnis dv
							 JOIN hr.tbl_vertragsart va USING (vertragsart_kurzbz)
					WHERE (dv.von <= (SELECT start FROM semester_datum) OR dv.von IS NULL)
					  AND (dv.bis >= (SELECT ende FROM semester_datum) OR dv.bis IS NULL)
					  AND dv.mitarbeiter_uid = ?
					ORDER BY von DESC
				),
				aggregated_relevante_dvs AS (
					 SELECT dv.mitarbeiter_uid, ARRAY_TO_STRING(ARRAY_AGG(dv.bezeichnung), E'\n') AS vertraege
					 FROM relevante_dvs dv
					 GROUP BY dv.mitarbeiter_uid
				 ),
				relevante_stunden AS (
					SELECT dv.mitarbeiter_uid,
						 ARRAY_TO_STRING(ARRAY_AGG((wochenstunden) ORDER BY tbl_vertragsbestandteil.von DESC), E'\n') AS wochenstundenstunden,
						   ARRAY_TO_STRING(
								   ARRAY_AGG(
										   CASE
											   WHEN oe_kurzbz = 'gst' THEN ROUND(1680/38.5 * wochenstunden, 2)
											   ELSE ROUND(1700/40 * wochenstunden, 2)
											   END ORDER BY tbl_vertragsbestandteil.von DESC
								   ),
								   E'\n'
						   ) AS jahresstunden
					FROM relevante_dvs dv
						JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
						JOIN hr.tbl_vertragsbestandteil_stunden USING(vertragsbestandteil_id)
					WHERE (tbl_vertragsbestandteil.von <= (SELECT start FROM semester_datum) OR tbl_vertragsbestandteil.von IS NULL)
					  AND (tbl_vertragsbestandteil.bis >= (SELECT ende FROM semester_datum) OR tbl_vertragsbestandteil.bis IS NULL)
					GROUP BY dv.mitarbeiter_uid
				),
    akt_vertrag AS (
      SELECT dv.mitarbeiter_uid,
             tbl_vertragsart.bezeichnung,
             dv.dienstverhaeltnis_id,
              dv.oe_kurzbz
       FROM hr.tbl_dienstverhaeltnis dv
                JOIN hr.tbl_vertragsart ON dv.vertragsart_kurzbz = tbl_vertragsart.vertragsart_kurzbz
                WHERE (dv.von <= NOW() OR dv.von IS NULL)
           AND (dv.bis >= NOW() OR dv.bis IS NULL)
           AND dv.mitarbeiter_uid = ?
         ORDER BY dv.von DESC NULLS LAST LIMIT 1
    ),
    akt_funktion AS (
        SELECT parentorg.bezeichnung as parentbezeichnung,
               org.bezeichnung as orgbezeichnung,
               tbl_vertragsbestandteil.von,
               tbl_vertragsbestandteil.bis,
               mitarbeiter_uid
        FROM akt_vertrag
                 JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
                 JOIN hr.tbl_vertragsbestandteil_funktion USING (vertragsbestandteil_id)
                 JOIN public.tbl_benutzerfunktion ON tbl_vertragsbestandteil_funktion.benutzerfunktion_id = tbl_benutzerfunktion.benutzerfunktion_id
                 JOIN tbl_organisationseinheit org ON tbl_benutzerfunktion.oe_kurzbz = org.oe_kurzbz
                 JOIN tbl_organisationseinheit parentorg ON org.oe_parent_kurzbz = parentorg.oe_kurzbz
        WHERE funktion_kurzbz = 'kstzuordnung'
          AND (tbl_vertragsbestandteil.von <= NOW() OR tbl_vertragsbestandteil.von IS NULL)
          AND (tbl_vertragsbestandteil.bis >= NOW() OR tbl_vertragsbestandteil.bis IS NULL)
        ORDER BY tbl_vertragsbestandteil.von desc NULLS LAST
        LIMIT 1
    ),
    akt_stunden AS (
        SELECT wochenstunden, mitarbeiter_uid,
        ( CASE
                     WHEN akt_vertrag.oe_kurzbz = 'gst' THEN ROUND(1680/38.5 * wochenstunden, 2)
                     ELSE ROUND(1700/40 * wochenstunden, 2)
                   END )  as stunden
        FROM akt_vertrag
                 JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
                 JOIN hr.tbl_vertragsbestandteil_stunden USING (vertragsbestandteil_id)
        WHERE (tbl_vertragsbestandteil.von <= NOW() OR tbl_vertragsbestandteil.von IS NULL)
          AND (tbl_vertragsbestandteil.bis >= NOW() OR tbl_vertragsbestandteil.bis IS NULL)
        ORDER BY tbl_vertragsbestandteil.von desc NULLS LAST
        LIMIT 1
    ),
     akt_lehre_stundensatz AS (
        SELECT stundensatz, uid
        FROM hr.tbl_stundensatz
            JOIN hr.tbl_stundensatztyp ON tbl_stundensatz.stundensatztyp = tbl_stundensatztyp.stundensatztyp
            AND (tbl_stundensatz.gueltig_von <= NOW() OR tbl_stundensatz.gueltig_von IS NULL)
            AND (tbl_stundensatz.gueltig_bis >= NOW() OR tbl_stundensatz.gueltig_bis IS NULL)
            AND tbl_stundensatz.stundensatztyp = ?
        WHERE uid = ?
        ORDER BY gueltig_von DESC NULLS LAST LIMIT 1
    ),
    lehre_stundensatz AS (
        SELECT ARRAY_TO_STRING(ARRAY_AGG((stundensatz) ORDER BY gueltig_von DESC), E'\n') AS stunden,
               uid
        FROM hr.tbl_stundensatz
            JOIN hr.tbl_stundensatztyp ON tbl_stundensatz.stundensatztyp = tbl_stundensatztyp.stundensatztyp
            AND tbl_stundensatz.stundensatztyp = ?
        WHERE uid = ?
          AND (
            gueltig_von <= (
                SELECT start FROM semester_datum
            )
                OR gueltig_von IS NULL
            )
          AND
            ((
                gueltig_bis >=
                    (SELECT ende FROM semester_datum)
                   
                ) OR gueltig_bis IS NULL)
      GROUP BY uid
    )
        
			SELECT
			 		relevante_dvs.*, 
					aggregated_relevante_dvs.vertraege, 
					relevante_stunden.wochenstundenstunden, 
					relevante_stunden.jahresstunden, 
					akt_vertrag.bezeichnung as aktbezeichnung,
					akt_stunden.stunden as aktjahresstunden,
        akt_funktion.orgbezeichnung as aktorgbezeichnung,
        akt_funktion.parentbezeichnung as aktparentbezeichnung,
        akt_lehre_stundensatz.stundensatz as stundensaetze_lehre_aktuell,
        akt_stunden.wochenstunden as aktstunden,
         lehre_stundensatz.stunden as stundensaetze_lehre
			FROM relevante_dvs
			LEFT JOIN aggregated_relevante_dvs ON relevante_dvs.mitarbeiter_uid = aggregated_relevante_dvs.mitarbeiter_uid
			LEFT JOIN relevante_stunden ON relevante_stunden.mitarbeiter_uid = aggregated_relevante_dvs.mitarbeiter_uid
LEFT JOIN akt_vertrag ON akt_vertrag.mitarbeiter_uid = relevante_dvs.mitarbeiter_uid
LEFT JOIN akt_funktion ON akt_funktion.mitarbeiter_uid = relevante_dvs.mitarbeiter_uid
LEFT JOIN akt_stunden ON akt_stunden.mitarbeiter_uid = relevante_dvs.mitarbeiter_uid
 LEFT JOIN akt_lehre_stundensatz ON akt_lehre_stundensatz.uid = relevante_dvs.mitarbeiter_uid
 LEFT JOIN lehre_stundensatz ON lehre_stundensatz.uid = relevante_dvs.mitarbeiter_uid



			;";
		$dienstverhaeltnis = $dbModel->execReadOnlyQuery($qry, array($studiensemester, $uid, $uid, 'lehre', $uid, 'lehre', $uid));
		
		if (hasData($dienstverhaeltnis))
		{
			/*$dienstverhaeltnis = getData($dienstverhaeltnis);
			foreach ($dienstverhaeltnis as $dv)
			{
				$dv->stunden = $this->_getStunden($dv->dienstverhaeltnis_id, $studiensemester);
			}*/
			
			return $dienstverhaeltnis;
		}
	}
	
	public function saveMitarbeiter()
	{
		$data = $this->getPostJson();

		foreach ($data as $mitarbeiter_uid => $mitarbeiter)
		{
			foreach ($mitarbeiter as $mitarbeiter_stunden)
			{
				if (property_exists($mitarbeiter_stunden, 'kategorie') &&
					property_exists($mitarbeiter_stunden, 'studienjahr') &&
					property_exists($mitarbeiter_stunden, 'stunden') &&
					property_exists($mitarbeiter_stunden, 'anmerkung'))
				{
					$kategorie = $this->_ci->PEPModel->load(array('kategorie_id' => $mitarbeiter_stunden->kategorie));


					if (!hasData($kategorie) || isError($kategorie))
						$this->terminateWithJsonError("Fehler beim Laden");

					$defaultStunden = $this->_ci->PEPModel->checkDefaultStunden($mitarbeiter_stunden->studienjahr, $mitarbeiter_stunden->kategorie, $mitarbeiter_stunden->stunden);

					if (isError($defaultStunden))
					{
						$this->terminateWithJsonError("Fehler beim Laden");
					}
					
					$stunden_exists = $this->_ci->PEPModel->checkStunden($mitarbeiter_stunden->studienjahr, $mitarbeiter_stunden->kategorie, $mitarbeiter_uid);

					if (hasData($stunden_exists) && !isError($stunden_exists))
					{

						$stunden_exists = getData($stunden_exists)[0];
						if ($stunden_exists->stunden !== number_format($mitarbeiter_stunden->stunden, 2)
							|| ($stunden_exists->anmerkung !== $mitarbeiter_stunden->anmerkung)
						)
						{
							$result = $this->_ci->PEPModel->updateStundenForMitarbeiter($mitarbeiter_stunden->studienjahr, $mitarbeiter_stunden->kategorie, $mitarbeiter_stunden->stunden, $mitarbeiter_uid, $mitarbeiter_stunden->anmerkung);
							if (isError($result))
								$this->terminateWithJsonError('Fehler beim Speichern');
						}

					}
					else if (!hasData($defaultStunden))
					{
						
						$result = $this->_ci->PEPModel->addStundenForMitarbeiter($mitarbeiter_stunden->studienjahr, $mitarbeiter_stunden->kategorie, $mitarbeiter_stunden->stunden, $mitarbeiter_uid, $mitarbeiter_stunden->anmerkung);
						if (isError($result))
							$this->terminateWithJsonError('Fehler beim Speichern');
					}

					$this->outputJsonSuccess("Erfolgreich gespeichert");
				}
				
			}
		}
	}
	
	private function _getStunden($dienstverhaeltnis_id, $studiensemester)
	{
		$dbModel = new DB_Model();
		$qry = "SELECT dienstverhaeltnis_id,
					wochenstunden,
					tbl_vertragsbestandteil.von,
					tbl_vertragsbestandteil.bis,
					oe_kurzbz
				FROM hr.tbl_dienstverhaeltnis
					JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
					JOIN hr.tbl_vertragsbestandteil_stunden USING(vertragsbestandteil_id)
				WHERE dienstverhaeltnis_id = ?
				AND (
					tbl_vertragsbestandteil.von <= (
						SELECT MIN(start)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR tbl_vertragsbestandteil.von IS NULL
				)
				AND
				(
					tbl_vertragsbestandteil.bis >= (
						SELECT MAX(ende)
						FROM public.tbl_studiensemester
						WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
					)
					OR tbl_vertragsbestandteil.bis IS NULL
				)
			";
		
		$stunden = $dbModel->execReadOnlyQuery($qry, array($dienstverhaeltnis_id, $studiensemester, $studiensemester));

		if (hasData($stunden))
		{
			$stunden = getData($stunden);
			
			foreach ($stunden as $stunde)
			{
				if (is_null($stunde->wochenstunden))
				{
					$jahresstunden = null;
				}
				elseif ($stunde->oe_kurzbz === 'gst')
				{
					$jahresstunden = round(1680/38.5 * $stunde->wochenstunden, 2);
				}
				else
				{
					$jahresstunden = round(1700/40 * $stunde->wochenstunden,2);
				}
				
				$stunde->jahresstunden = $jahresstunden;
			}
			
			return $stunden;
		}
		return getData($stunden);
	}
	
	
	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}
}

