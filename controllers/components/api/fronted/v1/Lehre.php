<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Lehre extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';

	public function __construct()
	{
		parent::__construct([
			'getLehre' => self::BERECHTIGUNG_KURZBZ,
			'getLehreinheit' => self::BERECHTIGUNG_KURZBZ,
			'getLehreinheiten' => self::BERECHTIGUNG_KURZBZ,
			'getLektoren' => self::BERECHTIGUNG_KURZBZ,
			'saveLehreinheit' => self::BERECHTIGUNG_KURZBZ,
			'updateFaktor' => self::BERECHTIGUNG_KURZBZ,
			'updateAnmerkung' => self::BERECHTIGUNG_KURZBZ,
			'getRaumtypen' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->model('education/Lehreinheit_model', 'LehreinheitModel');
		$this->_ci->load->model('education/Lehreinheitmitarbeiter_model', 'LehreinheitmitarbeiterModel');
		$this->_ci->load->model('education/LehrveranstaltungFaktor_model', 'LehrveranstaltungFaktorModel');
		$this->_ci->load->model('ressource/Raumtyp_model', 'RaumtypModel');
		$this->_ci->load->model('person/Person_model', 'PersonModel');

		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_ci->load->library('PhrasesLib');

		$this->loadPhrases(
			array(
				'ui'
			)
		);
		$this->load->helper('extensions/FHC-Core-PEP/hlp_employee_helper');

		$this->_setAuthUID();

	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods
	public function getLehre()
	{
		$org = $this->_ci->input->get('org');
		$studiensemester = $this->_ci->input->get('semester');
		$recursive = $this->_ci->input->get('recursive');

		if (isEmptyString($org) || isEmptyString($studiensemester))
			$this->terminateWithError('Bitte alle Felder ausfüllen');

		$allMitarbeiter = $this->_getLehreMitarbeiter($org, $studiensemester, $recursive === "true");

		if (!hasData($allMitarbeiter))
			$this->terminateWithSuccess([]);

		$allMitarbeiter = getData($allMitarbeiter);

		$uniqueMitarbeiter = array_unique(array_column($allMitarbeiter, 'uid'));

		$dienstverhaeltnisse = $this->_ci->PEPModel->_getDVs($uniqueMitarbeiter, null, $studiensemester);

		if (!hasData($dienstverhaeltnisse))
			$this->terminateWithSuccess([]);

		$mitarbeiterInfos = [];

		foreach (getData($dienstverhaeltnisse) as $mitarbeiter)
		{
			$mitarbeiterInfos[$mitarbeiter->mitarbeiter_uid] = (object) [
				'zrm_vertraege' => isset($mitarbeiter->zrm_vertraege) ? $mitarbeiter->zrm_vertraege : '-',
				'zrm_wochenstunden' => isset($mitarbeiter->zrm_wochenstunden) ? $mitarbeiter->zrm_wochenstunden : '-',
				'zrm_jahresstunden' => isset($mitarbeiter->zrm_jahresstunden) ? $mitarbeiter->zrm_jahresstunden : '-',
				'zrm_stundensatz_lehre' => isset($mitarbeiter->zrm_stundensatz_lehre) ? $mitarbeiter->zrm_stundensatz_lehre : '-',

				'akt_bezeichnung' => isset($mitarbeiter->akt_bezeichnung) ? $mitarbeiter->akt_bezeichnung : '-',
				'akt_orgbezeichnung' => isset($mitarbeiter->akt_orgbezeichnung) ? $mitarbeiter->akt_orgbezeichnung : '-',
				'akt_parentbezeichnung' => isset($mitarbeiter->akt_parentbezeichnung) ? $mitarbeiter->akt_parentbezeichnung : '-',
				'akt_stunden' => isset($mitarbeiter->akt_stunden) ? $mitarbeiter->akt_stunden : '-',
				'akt_stundensaetze_lehre' => isset($mitarbeiter->akt_stundensaetze_lehre) ? $mitarbeiter->akt_stundensaetze_lehre : '-'
			];
		}

		$mitarbeiterDataArray = [];

		foreach ($allMitarbeiter as $mitarbeiter) {
			$mitarbeiterData = clone $mitarbeiter;
			$info = isset($mitarbeiterInfos[$mitarbeiter->uid]) ? $mitarbeiterInfos[$mitarbeiter->uid] : new stdClass();
			$mitarbeiterData->zrm_vertraege = $info->zrm_vertraege;
			$mitarbeiterData->zrm_wochenstunden = $info->zrm_wochenstunden;
			$mitarbeiterData->zrm_jahresstunden = $info->zrm_jahresstunden;
			$mitarbeiterData->zrm_stundensatz_lehre = $info->zrm_stundensatz_lehre;

			$mitarbeiterData->akt_bezeichnung = $info->akt_bezeichnung;
			$mitarbeiterData->akt_orgbezeichnung = $info->akt_orgbezeichnung;
			$mitarbeiterData->akt_parentbezeichnung = $info->akt_parentbezeichnung;
			$mitarbeiterData->akt_stunden = $info->akt_stunden;
			$mitarbeiterData->akt_stundensaetze_lehre = $info->akt_stundensaetze_lehre;

			$mitarbeiterDataArray[] = $mitarbeiterData;
		}

		$this->terminateWithSuccess(($mitarbeiterDataArray));
	}
	public function updateFaktor()
	{
		$data = $this->getPostJson();

		if ((property_exists($data, 'lv_id')) &&
			(property_exists($data, 'faktor')) &&
			(property_exists($data, 'lehrform_kurzbz')) &&
			(property_exists($data, 'semester')))
		{

			$studiensemester = $this->_ci->StudiensemesterModel->loadWhere(array("studiensemester_kurzbz" => $data->semester));
			if (isError($studiensemester) || !hasData($studiensemester))
				$this->terminateWithError($studiensemester, self::ERROR_TYPE_GENERAL);

			$studiensemester = getData($studiensemester)[0];

			$actSemester = $this->_ci->StudiensemesterModel->getAktOrNextSemester();

			if (isError($actSemester) || !hasData($actSemester))
				$this->terminateWithError($studiensemester, self::ERROR_TYPE_GENERAL);

			$actSemester = getData($actSemester)[0];

			if ($studiensemester->start < $actSemester->start)
				$this->terminateWithError("Realstunden können nicht für die Vergangenheit geändert werden", self::ERROR_TYPE_GENERAL);

			$studiensemester = $studiensemester->studiensemester_kurzbz;

			$exists = $this->_ci->LehrveranstaltungFaktorModel->loadWhere(array('studiensemester_kurzbz_von' => $studiensemester, 'lehrveranstaltung_id' => $data->lv_id, 'lehrform_kurzbz' => $data->lehrform_kurzbz));

			if (hasData($exists))
			{
				$existsFaktor = getData($exists)[0];

				$updateResult = $this->_ci->LehrveranstaltungFaktorModel->update(
					array(
						'lehrveranstaltung_faktor_id' => $existsFaktor->lehrveranstaltung_faktor_id
					),
					array(
						'faktor' => $data->faktor,
						'updateamum' => date('Y-m-d H:i:s'),
						'updatevon' => $this->_uid,
					)
				);
				$this->terminateWithSuccess($updateResult);
			}
			else
			{

				$dbModel = new DB_Model();

				$qry = "SELECT lehrveranstaltung_faktor_id
						FROM lehre.tbl_lehrveranstaltung_faktor
							LEFT JOIN public.tbl_studiensemester vonstsem
								ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_von = vonstsem.studiensemester_kurzbz
							LEFT JOIN public.tbl_studiensemester bisstem
								ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_bis = bisstem.studiensemester_kurzbz
						WHERE lehrveranstaltung_id = ?
						  AND vonstsem.start < (
							SELECT start
							FROM public.tbl_studiensemester
							WHERE studiensemester_kurzbz = ?
						)
						AND bisstem IS NULL
						AND lehrform_kurzbz = ?
						ORDER BY vonstsem.start DESC
						LIMIT 1";

				$exists = $dbModel->execReadOnlyQuery($qry, array($data->lv_id, $studiensemester, $data->lehrform_kurzbz));

				if (hasData($exists))
				{
					$exists = getData($exists)[0];

					$endStudiensemester = $this->_ci->StudiensemesterModel->getPreviousFrom($studiensemester);

					if (isError($endStudiensemester) || !hasData($endStudiensemester))
						$this->terminateWithJsonError("Error");

					$endStudiensemester = getData($endStudiensemester)[0]->studiensemester_kurzbz;

					$updateResult = $this->_ci->LehrveranstaltungFaktorModel->update(
						array(
							'lehrveranstaltung_faktor_id' => $exists->lehrveranstaltung_faktor_id
						),
						array(
							'studiensemester_kurzbz_bis' => $endStudiensemester,
							'updateamum' => date('Y-m-d H:i:s'),
							'updatevon' => $this->_uid,
						)
					);
					if (isError($updateResult))
						$this->terminateWithJsonError("Error");
				}

				$insertResult = $this->_ci->LehrveranstaltungFaktorModel->insert(array(
					'lehrveranstaltung_id' => $data->lv_id,
					'lehrform_kurzbz' => $data->lehrform_kurzbz,
					'faktor' => $data->faktor,
					'studiensemester_kurzbz_von' => $studiensemester,
					'insertamum' => date('Y-m-d H:i:s'),
					'insertvon' => $this->_uid
				));

				$this->terminateWithSuccess($insertResult);
			}
		}
	}

	public function updateAnmerkung()
	{
		$data = $this->getPostJson();
		if ((property_exists($data, 'lehreinheit_id')) &&
			(property_exists($data, 'anmerkung')) &&
			(property_exists($data, 'uid'))
			)
		{
			$result = $this->_ci->LehreinheitmitarbeiterModel->update(
				array(
					'lehreinheit_id' => $data->lehreinheit_id,
					'mitarbeiter_uid' => $data->uid
				),
				array(
					'anmerkung' => $data->anmerkung,
					'updateamum' => date('Y-m-d H:i:s'),
					'updatevon' => $this->_uid
				)
			);

			if (isError($result))
				$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);
		}
	}

	public function saveLehreinheit()
	{
		$data = $this->getPostJson();

		if (!property_exists($data, 'studiensemester'))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		if (
			(property_exists($data, 'lehreinheit_id')) &&
			/*
			(property_exists($data, 'raumtyp')) &&
			(property_exists($data->raumtyp, 'raumtyp_kurzbz')) &&
			(property_exists($data, 'raumtypalternativ')) &&
			(property_exists($data->raumtypalternativ, 'raumtyp_kurzbz')) &&
			(property_exists($data, 'start_kw')) &&
			(property_exists($data, 'stundenblockung')) &&
			(property_exists($data, 'wochenrythmus')) &&*/
			(property_exists($data, 'anmerkung')) &&
			(property_exists($data, 'lektor')) &&
			(property_exists($data->lektor, 'uid')) &&
			(property_exists($data, 'oldlektor')))
		{
			/*$result = $this->_ci->LehreinheitModel->update(
				array(
					'lehreinheit_id' => $data->lehreinheit_id
				),
				array(
					'stundenblockung' => $data->stundenblockung,
					'wochenrythmus' => $data->wochenrythmus,
					'start_kw' => $data->start_kw,
					'raumtyp' => $data->raumtyp->raumtyp_kurzbz,
					'raumtypalternativ' => $data->raumtypalternativ->raumtyp_kurzbz,
					'updateamum' => date('Y-m-d H:i:s'),
					'updatevon' => $this->_uid
				)
			);

			if (isError($result))
				$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);;*/


			$this->_ci->load->model('ressource/Mitarbeiter_model', 'MitarbeiterModel');
			$fixangestellt = getData($this->_ci->MitarbeiterModel->isMitarbeiter($data->lektor->uid, true));
			$this->_ci->load->model('ressource/Stundensatz_model', 'StundensatzModel');
			$successUpdated = [];
			/*if (!isEmptyArray($data->lehreinheit_ids))
			{
				foreach ($data->lehreinheit_ids as $lehreinheit)
				{
					if (!$this->_canUpdateLehreinheit( $data->lehreinheit_id, $data->lektor->uid))
					{
						$this->addError("Der Lektor ist bereits der Lehreinheit zugeordnet!", self::ERROR_TYPE_GENERAL);
						continue;
					}

					$updateParams = array('mitarbeiter_uid' => $data->lektor->uid,
						'updateamum' => date('Y-m-d H:i:s'),
						'updatevon' => $this->_uid);

					if ($this->_shouldUpdateStundensatz($lehreinheit->lehreinheit_id, $lehreinheit->uid))
					{
						if (!$fixangestellt)
						{
							$semester = $this->_ci->StudiensemesterModel->loadWhere(array('studiensemester_kurzbz' => $lehreinheit->le_semester));
							$semester = getData($semester)[0];
							$stundensatzResult = $this->_ci->StundensatzModel->getStundensatzByDatum($data->lektor->uid, $semester->start, $semester->ende, 'lehre');
							$stundensatzResult = hasData($stundensatzResult) ? getData($stundensatzResult)[0]->stundensatz : null;
						}
						else
							$stundensatzResult = null;

						$updateParams['stundensatz'] = $stundensatzResult;
					}

					$result = $this->_ci->LehreinheitmitarbeiterModel->update(
						array(
							'lehreinheit_id' => $lehreinheit->lehreinheit_id,
							'mitarbeiter_uid' => $lehreinheit->uid
						),
						$updateParams
					);

					if (isError($result))
						$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);

					$successUpdated[] = ['id' => $lehreinheit->row_index, 'le_stundensatz' => isset($stundensatzResult) ? $stundensatzResult : '0.00'];
				}
			}
			else
			{*/


			$updateDatum = date('Y-m-d H:i:s');
			$updateParams = array('mitarbeiter_uid' => $data->lektor->uid,
				'anmerkung' => $data->anmerkung,
				'updateamum' => $updateDatum,
				'updatevon' => $this->_uid);

			if ($data->lektor->uid !== $data->oldlektor)
			{
				if ($this->_cantUpdateLehreinheit($data->lehreinheit_id, $data->lektor->uid))
				{
					$this->terminateWithError("Der Lektor ist bereits der Lehreinheit zugeordnet!", self::ERROR_TYPE_GENERAL);
				}

				if ($this->_vertragExists($data->lehreinheit_id, $data->oldlektor))
				{
					$this->terminateWithError("Es existiert bereits ein Vertrag!", self::ERROR_TYPE_GENERAL);
				}

				if ($this->_verplant($data->lehreinheit_id, $data->oldlektor))
				{
					$this->terminateWithError("Mitarbeiter ist bereits verplant!", self::ERROR_TYPE_GENERAL);
				}

				if ($this->_shouldUpdateStundensatz($data->lehreinheit_id, $data->oldlektor))
				{
					if (!$fixangestellt)
					{
						$semester = $this->_ci->StudiensemesterModel->loadWhere(array('studiensemester_kurzbz' => $data->le_semester));
						$semester = getData($semester)[0];
						$stundensatzResult = $this->_ci->StundensatzModel->getStundensatzByDatum($data->lektor->uid, $semester->start, $semester->ende, 'lehre');
						$stundensatzResult = hasData($stundensatzResult) ? getData($stundensatzResult)[0]->stundensatz : null;
					}
					else
						$stundensatzResult = null;

					$updateParams['stundensatz'] = $stundensatzResult;
				}
			}

			$result = $this->_ci->LehreinheitmitarbeiterModel->update(
				array(
					'lehreinheit_id' => $data->lehreinheit_id,
					'mitarbeiter_uid' => $data->oldlektor
				),
				$updateParams
			);

			if (isError($result))
				$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);
			/*}*/

			$this->_ci->PersonModel->addSelect("(vorname || ' ' || nachname || ' ' || '(' || uid || ')') as lehreinheitupdatevon");
			$this->_ci->PersonModel->addJoin('public.tbl_benutzer updatedbenutzer', 'updatedbenutzer.person_id = tbl_person.person_id');
			$updatedPerson = $this->_ci->PersonModel->loadWhere(array('updatedbenutzer.uid' => $this->_uid));
			$updatedPersonData = getData($updatedPerson)[0];
			$this->_ci->PersonModel->addSelect('vorname, nachname, kurzbz as lektor, uid');
			$this->_ci->PersonModel->addJoin('public.tbl_benutzer', 'person_id');
			$this->_ci->PersonModel->addJoin('public.tbl_mitarbeiter', 'mitarbeiter_uid = tbl_benutzer.uid');
			$mitarbeiterData = $this->_ci->PersonModel->loadWhere(array('uid' => $data->lektor->uid));

			$returnData = getData($mitarbeiterData)[0];


			$dienstverhaeltnisse = $this->_ci->PEPModel->_getDVs(array($data->lektor->uid), null, $data->studiensemester);

			if (hasData($dienstverhaeltnisse))
				$dv = getData($dienstverhaeltnisse)[0];

			$returnData->zrm_vertraege = isset($dv->zrm_vertraege) ? $dv->zrm_vertraege : '-';
			$returnData->releavante_vertragsart = isset($dv->releavante_vertragsart) ? $dv->releavante_vertragsart : '-';
			$returnData->zrm_wochenstunden = isset($dv->zrm_wochenstunden) ? $dv->zrm_wochenstunden : '-';
			$returnData->zrm_jahresstunden = isset($dv->zrm_jahresstunden) ? $dv->zrm_jahresstunden : '-';

			$returnData->akt_orgbezeichnung = isset($dv->akt_orgbezeichnung) ? $dv->akt_orgbezeichnung : '-';
			$returnData->akt_parentbezeichnung = isset($dv->akt_parentbezeichnung) ? $dv->akt_parentbezeichnung : '-';
			$returnData->le_stundensatz = isset($stundensatzResult) ? $stundensatzResult : null;
			$returnData->akt_stunden = isset($dv->akt_stunden) ? $dv->akt_stunden : '-';

			$returnData->akt_stundensaetze_lehre = isset($dv->akt_stundensaetze_lehre) ? $dv->akt_stundensaetze_lehre : '-';
			$returnData->bezeichnung = isset($dv->bezeichnung) ? $dv->bezeichnung : '-';
			$returnData->updateamum = $updateDatum;
			$returnData->anmerkung = $data->anmerkung;
			$returnData->lehreinheiten_ids = $successUpdated;
			$returnData->lehreinheitupdatevon = $updatedPersonData->lehreinheitupdatevon;

			$this->terminateWithSuccess($returnData);
		}
		else
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);
	}

	public function getLektoren()
	{
		$dbModel = new DB_Model();
		$qry = "SELECT nachname, vorname, uid, kurzbz
				FROM campus.vw_mitarbeiter 
					JOIN public.tbl_benutzer USING (uid) 
				WHERE tbl_benutzer.aktiv
				ORDER BY nachname";

		$result = $dbModel->execReadOnlyQuery($qry);
		$this->terminateWithSuccess(hasData($result) ? getData($result) : []);
	}

	//Wird derzeit nicht benötigt -> return empty array
	public function getRaumtypen()
	{
		$this->terminateWithSuccess([]);
		$this->_ci->RaumtypModel->addSelect('beschreibung, raumtyp_kurzbz, aktiv');
		$result = $this->_ci->RaumtypModel->load();

		$this->terminateWithSuccess(hasData($result) ? getData($result) : []);
	}

	public function getLehreinheit()
	{
		$lehreinheit_id = $this->_ci->input->get('lehreinheit_id');
		$mitarbeiter_uid = $this->_ci->input->get('mitarbeiter_uid');

		if (isEmptyString($lehreinheit_id))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$this->_ci->LehreinheitModel->addJoin('lehre.tbl_lehreinheitmitarbeiter', 'lehreinheit_id', 'LEFT');
		$lehreinheit = $this->_ci->LehreinheitModel->loadWhere(array('lehreinheit_id' => $lehreinheit_id, 'mitarbeiter_uid' => $mitarbeiter_uid));

		if (!hasData($lehreinheit))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$this->terminateWithSuccess(hasData($lehreinheit) ? getData($lehreinheit)[0] : []);
	}

	public function getLehreinheiten()
	{
		$lehrveranstaltung_id = $this->_ci->input->get('lehrveranstaltung_id');
		$studiensemester = $this->_ci->input->get('studiensemester');
		$lehrform = $this->_ci->input->get('lehrform_kurzbz');
		$le_studiensemester = $this->_ci->input->get('le_studiensemester_kurzbz');

		if (isEmptyString($lehrveranstaltung_id) || isEmptyArray($studiensemester))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz, start');
		$this->_ci->StudiensemesterModel->addOrder('start', 'DESC');
		$this->_ci->StudiensemesterModel->addLimit(1);

		$dbModel = new DB_Model();
		$qry = "
			WITH semester AS (
				SELECT start, ende
				FROM public.tbl_studiensemester
				WHERE studiensemester_kurzbz = ?
			)
			SELECT tbl_lehrveranstaltung.bezeichnung,
					SUM(tbl_lehreinheitmitarbeiter.semesterstunden) OVER () AS lvstunden,
					tbl_lehreinheitmitarbeiter.semesterstunden,
					(
						SELECT lvf.faktor
						FROM lehre.tbl_lehrveranstaltung_faktor lvf
								 LEFT JOIN public.tbl_studiensemester vonstsem
										   ON lvf.studiensemester_kurzbz_von = vonstsem.studiensemester_kurzbz
								 LEFT JOIN public.tbl_studiensemester bisstem
										   ON lvf.studiensemester_kurzbz_bis = bisstem.studiensemester_kurzbz
								 CROSS JOIN semester
						WHERE lvf.lehrveranstaltung_id = ?
						  AND (bisstem.ende >= semester.start OR bisstem.ende IS NULL)
						  AND vonstsem.start <= semester.ende
						  AND (
							lvf.lehrform_kurzbz = ?
								OR (
								lvf.lehrform_kurzbz IS NULL
									AND NOT EXISTS (
									SELECT 1
									FROM lehre.tbl_lehrveranstaltung_faktor lvf2
											 LEFT JOIN public.tbl_studiensemester vonstsem2
													   ON lvf2.studiensemester_kurzbz_von = vonstsem2.studiensemester_kurzbz
											 LEFT JOIN public.tbl_studiensemester bisstem2
													   ON lvf2.studiensemester_kurzbz_bis = bisstem2.studiensemester_kurzbz
									WHERE lvf2.lehrveranstaltung_id = lvf.lehrveranstaltung_id
									  AND lvf2.lehrform_kurzbz = ?
									  AND (bisstem2.ende >= semester.start OR bisstem2.ende IS NULL)
									  AND vonstsem2.start <= semester.ende
								)
								)
							)
						ORDER BY vonstsem.start DESC
						LIMIT 1) as faktor,
					studiensemester_kurzbz,
					tbl_lehreinheit.lehreinheit_id,
					tbl_lehreinheit.lehrform_kurzbz,
					tbl_mitarbeiter.kurzbz,
					vorname,
					nachname,
					tbl_lehrveranstaltung.lehrveranstaltung_id,
					(
						SELECT va.vertragsart_kurzbz as releavante_vertragsart
						FROM hr.tbl_dienstverhaeltnis dv
								 JOIN hr.tbl_vertragsart va USING (vertragsart_kurzbz)
						WHERE (dv.von <= (SELECT ende FROM public.tbl_studiensemester WHERE tbl_studiensemester.studiensemester_kurzbz IN ? ORDER BY ende desc LIMIT 1) OR dv.von IS NULL)
						  AND (dv.bis >= (SELECT start FROM public.tbl_studiensemester WHERE tbl_studiensemester.studiensemester_kurzbz IN ? ORDER BY von LIMIT 1) OR dv.bis IS NULL)
						  AND dv.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid
						ORDER BY von DESC LIMIT 1
					) as vertrag
			FROM lehre.tbl_lehreinheit
				LEFT JOIN lehre.tbl_lehrveranstaltung USING(lehrveranstaltung_id)
				LEFT JOIN lehre.tbl_lehreinheitmitarbeiter ON tbl_lehreinheit.lehreinheit_id = tbl_lehreinheitmitarbeiter.lehreinheit_id
				LEFT JOIN public.tbl_mitarbeiter ON tbl_lehreinheitmitarbeiter.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid
				JOIN public.tbl_benutzer ON uid = tbl_mitarbeiter.mitarbeiter_uid
				JOIN public.tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
				WHERE tbl_lehrveranstaltung.lehrveranstaltung_id = ?
					AND studiensemester_kurzbz IN ?
					AND tbl_lehreinheit.lehrform_kurzbz = ?
			GROUP BY tbl_lehreinheit.lehreinheit_id, tbl_lehreinheit.lehrform_kurzbz, tbl_lehrveranstaltung.bezeichnung, las,  tbl_lehreinheitmitarbeiter.semesterstunden, tbl_mitarbeiter.kurzbz, vorname,
					nachname, tbl_lehrveranstaltung.lehrveranstaltung_id, tbl_mitarbeiter.mitarbeiter_uid
		";

		$result = $dbModel->execReadOnlyQuery($qry, array($le_studiensemester, $lehrveranstaltung_id, $lehrform, $lehrform, $studiensemester, $studiensemester, $lehrveranstaltung_id, $studiensemester, $lehrform));
		$this->terminateWithSuccess(hasData($result) ? getData($result) : []);
	}

	private function _shouldUpdateStundensatz($lehreinheit, $mitarbeiter_uid)
	{
		$lehreinheitData = $this->_ci->LehreinheitmitarbeiterModel->loadWhere(array('mitarbeiter_uid' => $mitarbeiter_uid, 'lehreinheit_id' => $lehreinheit));

		$lehreinheitData = hasData($lehreinheitData) ? getData($lehreinheitData)[0] : null;

		return !($lehreinheitData->stundensatz === "0.00");
	}

	private function _cantUpdateLehreinheit($lehreinheit, $mitarbeiter_uid)
	{
		$lehreinheitData = $this->_ci->LehreinheitmitarbeiterModel->loadWhere(array('mitarbeiter_uid' => $mitarbeiter_uid, 'lehreinheit_id' => $lehreinheit));

		return hasData($lehreinheitData);
	}

	private function _vertragExists($lehreinheit, $mitarbeiter_uid)
	{
		$lehreinheitData = $this->_ci->LehreinheitmitarbeiterModel->loadWhere(array('mitarbeiter_uid' => $mitarbeiter_uid, 'lehreinheit_id' => $lehreinheit, 'vertrag_id' => NULL));

		return !hasData($lehreinheitData);

	}

	private function _verplant($lehreinheit, $mitarbeiter_uid)
	{
		$dbModel = new DB_Model();
		$qry = "SELECT 1
				FROM
					lehre.tbl_stundenplandev as stpl
						JOIN lehre.tbl_lehreinheit le USING(lehreinheit_id)
						JOIN lehre.tbl_lehrveranstaltung as lehrfach ON(le.lehrfach_id = lehrfach.lehrveranstaltung_id)
				WHERE stpl.lehreinheit_id = ?
					AND stpl.mitarbeiter_uid = ?
				UNION
				SELECT 1
				FROM
					lehre.tbl_stundenplan as stpl
						JOIN lehre.tbl_lehreinheit le USING(lehreinheit_id)
						JOIN lehre.tbl_lehrveranstaltung as lehrfach ON(le.lehrfach_id = lehrfach.lehrveranstaltung_id)
				WHERE stpl.lehreinheit_id = ?
					AND stpl.mitarbeiter_uid = ?";

		$result = $dbModel->execReadOnlyQuery($qry, array($lehreinheit, $mitarbeiter_uid, $lehreinheit, $mitarbeiter_uid));

		return hasData($result);

	}

	private function _getLehreMitarbeiter($org, $studiensemester, $recursive)
	{
		$mitarbeiter_uids = getMitarbeiterUids($org, $studiensemester, $recursive);
		return $this->_ci->PEPModel->getMitarbeiterLehre($org, $studiensemester, $recursive, $mitarbeiter_uids);
	}

	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}



}

