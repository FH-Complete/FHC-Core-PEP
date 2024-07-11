<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class PEP extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';
	
	public function __construct()
	{
		parent::__construct([
			'getStart' => self::BERECHTIGUNG_KURZBZ,
			'getLehre' => self::BERECHTIGUNG_KURZBZ,
			'getCategory' => self::BERECHTIGUNG_KURZBZ,
			'vorruecken' => self::BERECHTIGUNG_KURZBZ,
			'getCategories' => self::BERECHTIGUNG_KURZBZ,
			'saveMitarbeiter' => self::BERECHTIGUNG_KURZBZ,
			'getLektoren' => self::BERECHTIGUNG_KURZBZ,
			'getRaumtypen' => self::BERECHTIGUNG_KURZBZ,
			'getLehreinheit' => self::BERECHTIGUNG_KURZBZ,
			'saveLehreinheit' => self::BERECHTIGUNG_KURZBZ,

		]);
		
		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studiengang_model', 'StudiengangModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('person/Benutzerfunktion_model', 'BenutzerfunktionModel');
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_Kategorie_Mitarbeiter_model', 'PEPKategorieMitarbeiterModel');
		$this->_ci->load->model('education/Lehreinheit_model', 'LehreinheitModel');
		$this->_ci->load->model('education/Lehreinheitmitarbeiter_model', 'LehreinheitmitarbeiterModel');
		$this->_ci->load->model('ressource/Raumtyp_model', 'RaumtypModel');
		$this->_ci->load->model('person/Person_model', 'PersonModel');

		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_setAuthUID();

	}
	
	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	public function getCategories()
	{
		$this->_ci->PEPModel->addSelect('kategorie_id, array_to_json(bezeichnung_mehrsprachig::varchar[])->>0 as beschreibung');
		$this->terminateWithSuccess(getData($this->_ci->PEPModel->load()));
	}
	public function getStart()
	{
		$org = $this->_ci->input->get('org');
		$studiensemester = $this->_ci->input->get('semester');
		$recursive = $this->_ci->input->get('recursive');

		if (isEmptyString($org) || isEmptyString($studiensemester))
			$this->terminateWithJsonError('Bitte alle Felder ausfüllen');

		$allMitarbeiter = $this->_ci->PEPModel->getMitarbeiter($org, $studiensemester, $recursive === "true");

		if (!hasData($allMitarbeiter))
			$this->terminateWithJsonError("Keine Daten gefunden");

		$mitarbeiterDataArray = array();

		foreach (getData($allMitarbeiter) as $mitarbeiter)
		{
			$dienstverhaeltnis = getData($this->_getDVs($mitarbeiter->uid, $studiensemester))[0];

			$mitarbeiterData = is_null($dienstverhaeltnis) ? new stdClass() : $dienstverhaeltnis;
			$mitarbeiterData->vorname = $mitarbeiter->vorname;
			$mitarbeiterData->nachname = $mitarbeiter->nachname;
			$mitarbeiterData->uid = $mitarbeiter->uid;

			$karenz = $this->_getCurrentKarenz($mitarbeiter->uid);
			$mitarbeiterData->karenz = $karenz ?: false;

			if (isset($dienstverhaeltnis->jahresstunden))
				$mitarbeiterData->summe = $this->_getJahresstunden($dienstverhaeltnis->jahresstunden, count($studiensemester));

			foreach ($studiensemester as $key => $ststem)
			{
				$ststemDV = $this->_ci->PEPModel->getDVForSemester($mitarbeiter->uid, $ststem);
				$lehrauftragsstunden = [];
				$keyname = "studiensemester_" . $key . "_lehrauftrag";
				if (hasData($ststemDV))
				{
					if (getData($ststemDV)[0]->vertragsart_kurzbz === 'echterdv')
					{
						$lehrauftragsstunden =  $this->_ci->PEPModel->getLehrauftraegeStundenWithFaktor($mitarbeiter->uid, $ststem);

						$kategorien = $this->_ci->PEPKategorieMitarbeiterModel->getStundenByMitarbeiter($mitarbeiter->uid, $ststem);

						if (!hasData($kategorien))
							continue;

						foreach(getData($kategorien) as $kategorie)
						{
							$categorykeyname = "studiensemester_" . $key . "_kategorie_" . $kategorie->kategorie_id;
							$mitarbeiterData->$categorykeyname = ($kategorie->stunden / 2);;
						}
					}
					else
					{
						$lehrauftragsstunden =  $this->_ci->PEPModel->getLehrauftraegeStundenWithoutFaktor($mitarbeiter->uid, $ststem);
					}
				}
				$stunden = 0;
				if (hasData($lehrauftragsstunden))
				{
					$stunden = getData($lehrauftragsstunden)[0]->stunden;
				}

				$mitarbeiterData->$keyname = $stunden;
			}

			$mitarbeiterDataArray[] = $mitarbeiterData;
		}

		$this->terminateWithSuccess($mitarbeiterDataArray);
	}

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

		$mitarbeiterDataArray = array();

		$allMitarbeiter = getData($allMitarbeiter);
		$uniqueMitarbeiter = array_unique(array_column($allMitarbeiter, 'uid'));

		$mitarbeiterInfos = [];
		foreach ($uniqueMitarbeiter as $mitarbeiter)
		{
			$mitarbeiterInfos[$mitarbeiter] = new stdClass();

			$dienstverhaeltnis = ($this->_getDVs($mitarbeiter, $studiensemester));
			if (hasData($dienstverhaeltnis))
				$dienstverhaeltnis = getData($dienstverhaeltnis)[0];

			$mitarbeiterInfos[$mitarbeiter]->vertraege = isset($dienstverhaeltnis->vertraege) ? $dienstverhaeltnis->vertraege : '-';
			$mitarbeiterInfos[$mitarbeiter]->wochenstundenstunden = isset($dienstverhaeltnis->wochenstundenstunden) ? $dienstverhaeltnis->wochenstundenstunden : '-';
			$mitarbeiterInfos[$mitarbeiter]->aktbezeichnung = isset($dienstverhaeltnis->aktbezeichnung) ? $dienstverhaeltnis->aktbezeichnung : '-';
			$mitarbeiterInfos[$mitarbeiter]->aktorgbezeichnung = isset($dienstverhaeltnis->aktorgbezeichnung) ? $dienstverhaeltnis->aktorgbezeichnung : '-';
			$mitarbeiterInfos[$mitarbeiter]->aktparentbezeichnung = isset($dienstverhaeltnis->aktparentbezeichnung) ? $dienstverhaeltnis->aktparentbezeichnung : '-';
			$mitarbeiterInfos[$mitarbeiter]->aktstunden = isset($dienstverhaeltnis->aktstunden) ? $dienstverhaeltnis->aktstunden : '-';
			$mitarbeiterInfos[$mitarbeiter]->stundensaetze_lehre_aktuell = isset($dienstverhaeltnis->stundensaetze_lehre_aktuell) ? $dienstverhaeltnis->stundensaetze_lehre_aktuell : '-';
			$mitarbeiterInfos[$mitarbeiter]->stundensaetze_lehre = isset($dienstverhaeltnis->stundensaetze_lehre) ? $dienstverhaeltnis->stundensaetze_lehre : '-';
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
		$this->terminateWithSuccess($mitarbeiterDataArray);
	}

	public function getCategory()
	{
		$category = $this->_ci->input->get('category_id');
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		$recursive = $this->_ci->input->get('recursive');

		$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz');
		$studiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($studiensemestern))
			$this->terminateWithJsonError("Fehler beim Lesen");

		$studiensemestern = array_column(getData($studiensemestern), 'studiensemester_kurzbz');

		$mitarbeiter_uids = $this->_getMitarbeiterUids($org, $studiensemestern, $recursive === "true");

		$categoryData = $this->_ci->PEPModel->getCategoryData($mitarbeiter_uids, $category, $studienjahr);

		$allMitarbeiterData = [];
		foreach (getData($categoryData) as $empCategoryData)
		{
			$mitarbeiterData = $empCategoryData;

			$dvs = $this->_getDVs($empCategoryData->mitarbeiter_uid, $studiensemestern);

			if (hasData($dvs))
				$dvs = getData($dvs)[0];

			$mitarbeiterData->vertraege = isset($dvs->vertraege) ? $dvs->vertraege : '-';
			$mitarbeiterData->releavante_vertragsart = isset($dvs->releavante_vertragsart) ? $dvs->releavante_vertragsart : '-';
			if ($mitarbeiterData->releavante_vertragsart !== 'echterdv' && is_null($mitarbeiterData->kategorie_mitarbeiter_id))
				$mitarbeiterData->stunden = 0;
			$mitarbeiterData->wochenstundenstunden = isset($dvs->wochenstundenstunden) ? $dvs->wochenstundenstunden : '-';
			$mitarbeiterData->jahresstunden = isset($dvs->jahresstunden) ? $dvs->jahresstunden : '-';
			$mitarbeiterData->aktorgbezeichnung = isset($dvs->aktorgbezeichnung) ? $dvs->aktorgbezeichnung : '-';
			$mitarbeiterData->aktparentbezeichnung = isset($dvs->aktparentbezeichnung) ? $dvs->aktparentbezeichnung : '-';
			$allMitarbeiterData[] = $mitarbeiterData;
		}

		$this->terminateWithSuccess($allMitarbeiterData);
	}

	public function vorruecken()
	{
		$studienjahr = $this->_ci->input->post('studienjahr');
		$category_id = $this->_ci->input->post('category_id');
		$org = $this->_ci->input->post('org');
		$recursive = $this->_ci->input->post('recursive');

		$this->_ci->StudiensemesterModel->addSelect('studienjahr_kurzbz');
		$result = $this->_ci->StudiensemesterModel->loadWhere('
			start >= (
				SELECT ende 
				FROM public.tbl_studiensemester
				WHERE studienjahr_kurzbz = '. $this->_ci->db->escape($studienjahr). '
				ORDER BY ende DESC LIMIT 1
			)
			ORDER BY start LIMIT 1
		');

		if (isError($result) || !hasData($result))
			$this->terminateWithError("Fehler");

		$aktStudiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($aktStudiensemestern))
			$this->terminateWithSuccess(false);

		$aktStudiensemestern = array_column(getData($aktStudiensemestern), 'studiensemester_kurzbz');

		$newStudienjahr = getData($result)[0]->studienjahr_kurzbz;

		$newStudiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $newStudienjahr));
		if (!hasData($newStudiensemestern))
			$this->terminateWithSuccess(false);

		$newStudiensemestern = array_column(getData($newStudiensemestern), 'studiensemester_kurzbz');

		$aktStudienjahrUids = $this->_getMitarbeiterUids($org, $aktStudiensemestern, $recursive === "true");
		$newStudienjahrUids = $this->_getMitarbeiterUids($org, $newStudiensemestern, $recursive === "true");

		$uidsNeedUpdate = array_intersect($aktStudienjahrUids, $newStudienjahrUids);

		$exists = $this->_ci->PEPKategorieMitarbeiterModel->loadWhere('
			mitarbeiter_uid IN ('. implode(',', $this->_ci->db->escape($newStudienjahrUids)) .')
			AND studienjahr_kurzbz = '. $this->_ci->db->escape($newStudienjahr).'
			AND kategorie_id = '. $this->_ci->db->escape($category_id) .'
		');

		if (hasData($exists))
			$this->terminateWithSuccess(true);
		else
		{
			$this->terminateWithSuccess($this->_ci->PEPKategorieMitarbeiterModel->vorruecken($studienjahr, $newStudienjahr, $category_id, $uidsNeedUpdate));
		}
	}


	private function _getCurrentKarenz($uid)
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



	public function saveMitarbeiter()
	{
		$data = $this->getPostJson();

		foreach ($data as $categoryId => $categoryArray)
		{
			foreach($categoryArray as $mitarbeiter => $mitarbeiterArray)
			{
				foreach($mitarbeiterArray as $mitarbeiterCategory)
				{
					if (
						property_exists($mitarbeiterCategory, 'studienjahr') &&
						property_exists($mitarbeiterCategory, 'stunden') &&
						property_exists($mitarbeiterCategory, 'anmerkung')
					)
					{
						$kategorie = $this->_ci->PEPModel->load(array('kategorie_id' => $categoryId));

						if (!hasData($kategorie) || isError($kategorie))
							$this->terminateWithJsonError("Fehler beim Speichern");

						if (is_null($mitarbeiterCategory->kategorie_mitarbeiter_id))
						{
							$result = $this->_ci->PEPKategorieMitarbeiterModel->insert(array(
								'kategorie_id' =>  $mitarbeiterCategory->kategorie,
								'mitarbeiter_uid' => $mitarbeiterCategory->uid,
								'studienjahr_kurzbz' => $mitarbeiterCategory->studienjahr,
								'stunden' => $mitarbeiterCategory->stunden,
								'anmerkung' => $mitarbeiterCategory->anmerkung,
								'insertamum' => date('Y-m-d H:i:s'),
								'insertvon' => $this->_uid
							));

							if (isError($result))
								$this->terminateWithJsonError('Fehler beim Speichern');

							$mitarbeiterCategory->kategorie_mitarbeiter_id = getData($result);
						}
						else
						{
							$stunden_exists = $this->_ci->PEPKategorieMitarbeiterModel->load(array($mitarbeiterCategory->kategorie_mitarbeiter_id));

							if (!hasData($stunden_exists) || isError($stunden_exists))
								$this->terminateWithJsonError("Fehler beim Speichern");

							$stunden_exists = getData($stunden_exists)[0];

							if ($stunden_exists->stunden !== number_format($mitarbeiterCategory->stunden, 2)
								|| ($stunden_exists->anmerkung !== $mitarbeiterCategory->anmerkung)
							)
							{
								$result = $this->_ci->PEPKategorieMitarbeiterModel->update(
									array($stunden_exists->kategorie_mitarbeiter_id),
									array(
										'stunden' => $mitarbeiterCategory->stunden,
										'anmerkung' => $mitarbeiterCategory->anmerkung,
										'updatevon' => $this->_uid,
										'updateamum' => date('Y-m-d H:i:s'),
									)
								);
								if (isError($result))
									$this->terminateWithJsonError('Fehler beim Speichern');
							}
						}
					}
					else if (property_exists($mitarbeiterCategory, 'kategorie_mitarbeiter_id') &&
						property_exists($mitarbeiterCategory, 'delete'))
					{
						$stunden_delete = $this->_ci->PEPKategorieMitarbeiterModel->delete(array('kategorie_mitarbeiter_id' => $mitarbeiterCategory->kategorie_mitarbeiter_id));

						if (isError($stunden_delete))
							$this->terminateWithJsonError('Fehler beim Speichern');
					}
				}
			}

			$this->outputJsonSuccess("erfolgreich gespeichert");
		}
		$returnValue = [];
		foreach ($data as $mitarbeiter)
		{
			foreach ($mitarbeiter as $mitarbeiter_stunden)
			{
				if (property_exists($mitarbeiter_stunden, 'reloadStudienjahr') &&
					property_exists($mitarbeiter_stunden, 'reloadKategorie'))
				{
					if (!isset($returnValue[$mitarbeiter_stunden->reloadKategorie]))
						$returnValue[$mitarbeiter_stunden->reloadKategorie] = $mitarbeiter_stunden->reloadStudienjahr;
				}

				if (property_exists($mitarbeiter_stunden, 'kategorie') &&
					property_exists($mitarbeiter_stunden, 'studienjahr') &&
					property_exists($mitarbeiter_stunden, 'stunden') &&
					property_exists($mitarbeiter_stunden, 'anmerkung') &&
					property_exists($mitarbeiter_stunden, 'kategorie_mitarbeiter_id'))
				{
					$kategorie = $this->_ci->PEPModel->load(array('kategorie_id' => $mitarbeiter_stunden->kategorie));

					if (!hasData($kategorie) || isError($kategorie))
						$this->terminateWithJsonError("Fehler beim Speichern");

					if (is_null($mitarbeiter_stunden->kategorie_mitarbeiter_id))
					{
						$kategorie = $this->_ci->PEPModel->load(array('kategorie_id' => $mitarbeiter_stunden->kategorie));

						if (!hasData($kategorie) || isError($kategorie))
							$this->terminateWithJsonError("Fehler beim Speichern");

						$result = $this->_ci->PEPKategorieMitarbeiterModel->insert(array(
							'kategorie_id' =>  $mitarbeiter_stunden->kategorie,
							'mitarbeiter_uid' => $mitarbeiter_stunden->uid,
							'studienjahr_kurzbz' => $mitarbeiter_stunden->studienjahr,
							'stunden' => $mitarbeiter_stunden->stunden,
							'anmerkung' => $mitarbeiter_stunden->anmerkung,
							'insertamum' => date('Y-m-d H:i:s'),
							'insertvon' => $this->_uid
						));

						if (isError($result))
							$this->terminateWithJsonError('Fehler beim Speichern');

						$mitarbeiter_stunden->kategorie_mitarbeiter_id = getData($result);
					}
					else
					{
						$stunden_exists = $this->_ci->PEPKategorieMitarbeiterModel->load(array($mitarbeiter_stunden->kategorie_mitarbeiter_id));

						if (!hasData($stunden_exists) || isError($stunden_exists))
							$this->terminateWithJsonError("Fehler beim Speichern");

						$stunden_exists = getData($stunden_exists)[0];

						if ($stunden_exists->stunden !== number_format($mitarbeiter_stunden->stunden, 2)
							|| ($stunden_exists->anmerkung !== $mitarbeiter_stunden->anmerkung)
						)
						{
							$result = $this->_ci->PEPKategorieMitarbeiterModel->update(
								array($stunden_exists->kategorie_mitarbeiter_id),
								array(
									'stunden' => $mitarbeiter_stunden->stunden,
									'anmerkung' => $mitarbeiter_stunden->anmerkung
								)
							);
							if (isError($result))
								$this->terminateWithJsonError('Fehler beim Speichern');

						}
					}
				}
				else if (property_exists($mitarbeiter_stunden, 'kategorie_mitarbeiter_id') &&
							property_exists($mitarbeiter_stunden, 'delete'))
				{
					$stunden_delete = $this->_ci->PEPKategorieMitarbeiterModel->delete(array('kategorie_mitarbeiter_id' => $mitarbeiter_stunden->kategorie_mitarbeiter_id));

					if (isError($stunden_delete))
						$this->terminateWithJsonError('Fehler beim Speichern');
				}
			}
		}
		$this->outputJsonSuccess($returnValue);
	}

	public function saveLehreinheit()
	{
		$data = $this->getPostJson();

		if (!property_exists($data, 'studiensemester'))
			$this->terminateWithJsonError('Fehler beim Speichern');

		if ((property_exists($data, 'raumtyp')) &&
			(property_exists($data->raumtyp, 'raumtyp_kurzbz')) &&
			(property_exists($data, 'lehreinheit_id')) &&
			(property_exists($data, 'raumtypalternativ')) &&
			(property_exists($data->raumtypalternativ, 'raumtyp_kurzbz')) &&
			(property_exists($data, 'start_kw')) &&
			(property_exists($data, 'stundenblockung')) &&
			(property_exists($data, 'wochenrythmus')) &&
			(property_exists($data, 'anmerkung')) &&
			(property_exists($data, 'lektor')) &&
			(property_exists($data->lektor, 'uid')) &&
			(property_exists($data, 'oldlektor')))
		{
			$result = $this->_ci->LehreinheitModel->update(
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
				$this->terminateWithJsonError('Fehler beim Speichern');

			$result = $this->_ci->LehreinheitmitarbeiterModel->update(
				array(
					'lehreinheit_id' => $data->lehreinheit_id,
					'mitarbeiter_uid' => $data->oldlektor
				),
				array(
					'mitarbeiter_uid' => $data->lektor->uid,
					'anmerkung' => $data->anmerkung,
					'updateamum' => date('Y-m-d H:i:s'),
					'updatevon' => $this->_uid
				)
			);

			if (isError($result))
				$this->terminateWithJsonError('Fehler beim Speichern');

			$this->_ci->PersonModel->addSelect('vorname, nachname, kurzbz as lektor, uid');
			$this->_ci->PersonModel->addJoin('public.tbl_benutzer', 'person_id');
			$this->_ci->PersonModel->addJoin('public.tbl_mitarbeiter', 'mitarbeiter_uid = tbl_benutzer.uid');
			$mitarbeiterData = $this->_ci->PersonModel->loadWhere(array('uid' => $data->lektor->uid));

			$returnData = getData($mitarbeiterData)[0];

			$dvs = $this->_getDVs($data->lektor->uid, $data->studiensemester);

			if (hasData($dvs))
				$dvs = getData($dvs)[0];

			$returnData->vertraege = isset($dvs->vertraege) ? $dvs->vertraege : '-';
			$returnData->releavante_vertragsart = isset($dvs->releavante_vertragsart) ? $dvs->releavante_vertragsart : '-';
			$returnData->wochenstundenstunden = isset($dvs->wochenstundenstunden) ? $dvs->wochenstundenstunden : '-';
			$returnData->jahresstunden = isset($dvs->jahresstunden) ? $dvs->jahresstunden : '-';
			$returnData->aktorgbezeichnung = isset($dvs->aktorgbezeichnung) ? $dvs->aktorgbezeichnung : '-';
			$returnData->aktparentbezeichnung = isset($dvs->aktparentbezeichnung) ? $dvs->aktparentbezeichnung : '-';
			$returnData->stundensaetze_lehre = isset($dvs->stundensaetze_lehre) ? $dvs->stundensaetze_lehre : '-';
			$returnData->aktstunden = isset($dvs->aktstunden) ? $dvs->aktstunden : '-';
			$returnData->stundensaetze_lehre_aktuell = isset($dvs->stundensaetze_lehre_aktuell) ? $dvs->stundensaetze_lehre_aktuell : '-';
			$returnData->aktbezeichnung = isset($dvs->aktbezeichnung) ? $dvs->aktbezeichnung : '-';
			$returnData->updateamum = date('d.m.Y H:i:s');
			$returnData->anmerkung = $data->anmerkung;

			$this->terminateWithSuccess($returnData);
		}
		else
			$this->terminateWithJsonError('Fehler beim Speichern');
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

	public function getRaumtypen()
	{
		$this->_ci->RaumtypModel->addSelect('beschreibung, raumtyp_kurzbz, aktiv');
		$result = $this->_ci->RaumtypModel->load();

		$this->terminateWithSuccess(hasData($result) ? getData($result) : []);
	}

	public function getLehreinheit()
	{
		$lehreinheit_id = $this->_ci->input->get('lehreinheit_id');
		$mitarbeiter_uid = $this->_ci->input->get('mitarbeiter_uid');

		if (isEmptyString($lehreinheit_id))
			$this->terminateWithJsonError('Error');

		$this->_ci->LehreinheitModel->addJoin('lehre.tbl_lehreinheitmitarbeiter', 'lehreinheit_id', 'LEFT');
		$lehreinheit = $this->_ci->LehreinheitModel->loadWhere(array('lehreinheit_id' => $lehreinheit_id, 'mitarbeiter_uid' => $mitarbeiter_uid));

		if (!hasData($lehreinheit))
			$this->terminateWithJsonError('Fehler beim Laden');

		$this->terminateWithSuccess(hasData($lehreinheit) ? getData($lehreinheit)[0] : []);
	}

	private function _getMitarbeiterOld($org, $studiensemester, $recursive)
	{
		$dbModel = new DB_Model();

		$qry = "SELECT
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
		$qry .= " ORDER BY vorname, nachname";
		$mitarbeiter = $dbModel->execReadOnlyQuery($qry, array($studiensemester, $studiensemester, $org));

		return ($mitarbeiter);
	}

	private function _getDVs($uid, $studiensemester)
	{
		$dbModel = new DB_Model();
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
					WHERE (dv.von <= (SELECT ende FROM semester_datum) OR dv.von IS NULL)
					  AND (dv.bis >= (SELECT start FROM semester_datum) OR dv.bis IS NULL)
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
					WHERE (tbl_vertragsbestandteil.von <= (SELECT ende FROM semester_datum) OR tbl_vertragsbestandteil.von IS NULL)
					  AND (tbl_vertragsbestandteil.bis >= (SELECT start FROM semester_datum) OR tbl_vertragsbestandteil.bis IS NULL)
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
							SELECT ende FROM semester_datum
						)
							OR gueltig_von IS NULL
						)
					  AND
						((
							gueltig_bis >=
								(SELECT start FROM semester_datum)
							   
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
		return $dbModel->execReadOnlyQuery($qry, array($studiensemester, $uid, $uid, 'lehre', $uid, 'lehre', $uid));
	}
	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}

	private function _getMitarbeiterUids($org, $studiensemester, $recursive)
	{
		$allMitarbeiter = $this->_ci->PEPModel->getMitarbeiter($org, $studiensemester, $recursive);
		$mitarbeiter_uids = array();
		if (hasData($allMitarbeiter))
			$mitarbeiter_uids = array_column(getData($allMitarbeiter), 'uid');
		return $mitarbeiter_uids;
	}

	private function _getLehreMitarbeiter($org, $studiensemester, $recursive)
	{
		$mitarbeiter_uids = $this->_getMitarbeiterUids($org, $studiensemester, $recursive);
		return $this->_ci->PEPModel->getMitarbeiterLehre($org, $studiensemester, $recursive, $mitarbeiter_uids);
	}

	private function _getJahresstunden($stunden, $semesterAnzahl)
	{
		$stunden = (int) $stunden;
		$jahresstunden = ($semesterAnzahl === 1) ? round($stunden / 2, 2) : $stunden;
		return number_format($jahresstunden, 2, '.', '');
	}
}

