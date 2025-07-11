<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class LVEntwicklung extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';

	public function __construct()
	{
		parent::__construct([
			'getLVs' => self::BERECHTIGUNG_KURZBZ,
			'getFutureLvs' => self::BERECHTIGUNG_KURZBZ,
			'getRollen' => self::BERECHTIGUNG_KURZBZ,
			'getStatus' => self::BERECHTIGUNG_KURZBZ,
			'update' => self::BERECHTIGUNG_KURZBZ,
			'delete' => self::BERECHTIGUNG_KURZBZ,

		]);

		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_LV_Entwicklung_model', 'PEPLVEntwicklungModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_LV_Entwicklung_Status_model', 'PEPLVEntwicklungStatusModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_LV_Entwicklung_Rolle_model', 'PEPLVEntwicklungRolleModel');

		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_ci->load->library('PhrasesLib');

		$this->load->helper('extensions/FHC-Core-PEP/hlp_employee_helper');

		$this->loadPhrases(
			array(
				'ui'
			)
		);
		$this->_ci->load->config('extensions/FHC-Core-PEP/pep');

		$this->_setAuthUID();
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods
	public function getFutureLVs()
	{
		$org = $this->_ci->input->get('org');
		$studiensemester = $this->_ci->input->get('semester');
		$recursive = $this->_ci->input->get('recursive');

		if (isEmptyString($org) || isEmptyArray($studiensemester))
			$this->terminateWithError('Bitte alle Felder ausfüllen');

		$this->_ci->StudiensemesterModel->db->where_in('studiensemester_kurzbz', $studiensemester);
		$studiensemester_result = $this->_ci->StudiensemesterModel->load();

		if (isError($studiensemester_result))
			$this->terminateWithError(getError($studiensemester_result));

		if (!hasData($studiensemester_result))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

		$studiensemester_result = getData($studiensemester_result);
		$studiensemester_array = array_column($studiensemester_result, 'studiensemester_kurzbz');

		$future_lvs = $this->_ci->PEPLVEntwicklungModel->getFutureLvs($studiensemester_array, $org, $recursive);
		$this->terminateWithSuccess(hasData($future_lvs) ? getData($future_lvs) : []);
	}

	public function getLVs()
	{
		$org = $this->_ci->input->get('org');
		$studiensemester = $this->_ci->input->get('semester');
		$recursive = $this->_ci->input->get('recursive');

		if (isEmptyString($org) || isEmptyArray($studiensemester))
			$this->terminateWithError('Bitte alle Felder ausfüllen');
		$this->_ci->StudiensemesterModel->db->where_in('studiensemester_kurzbz', $studiensemester);
		$studiensemester_result = $this->_ci->StudiensemesterModel->load();

		if (isError($studiensemester_result))
			$this->terminateWithError(getError($studiensemester_result));

		if (!hasData($studiensemester_result))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

		$studiensemester_array = array_column(getData($studiensemester_result), 'studiensemester_kurzbz');

		$lv_array = [];
		foreach (getData($studiensemester_result) as $studiensemester)
		{
			$new_studiensemester = $this->_ci->StudiensemesterModel->getNextFrom($studiensemester->studiensemester_kurzbz);

			if (isError($new_studiensemester)) $this->terminateWithError(getError($studiensemester_result));

			if (!hasData($new_studiensemester)) $this->terminateWithError("nicht gefunden");

			$lv_array[] = getData($new_studiensemester)[0]->studiensemester_kurzbz;
		}

		$mitarbeiter_uids = getMitarbeiterUids($org, $studiensemester_array, $recursive === "true");
		$result = $this->_ci->PEPLVEntwicklungModel->getLVEntwicklung($lv_array, $studiensemester_array, $mitarbeiter_uids, $org, $recursive);

		if (!hasData($result))
			$this->terminateWithSuccess([]);

		$allinfos = getData($result);

		$uniqueMitarbeiter = array_unique(array_filter(array_column($allinfos, 'mitarbeiter_uid'), function($v) {
			return !is_null($v);
		}));


		$dienstverhaeltnisse = $this->_ci->PEPModel->getMitarbeiterData($uniqueMitarbeiter, $studiensemester_array);

		if (hasData($dienstverhaeltnisse))
		{
			$mitarbeiterInfos = [];

			foreach (getData($dienstverhaeltnisse) as $mitarbeiter)
			{
				$mitarbeiterInfos[$mitarbeiter->mitarbeiter_uid] = (object) [
					'zrm_vertraege' => isset($mitarbeiter->zrm_vertraege) ? $mitarbeiter->zrm_vertraege : '-',
					'zrm_wochenstunden' => isset($mitarbeiter->zrm_wochenstunden) ? $mitarbeiter->zrm_wochenstunden : '-',
					'zrm_jahresstunden' => isset($mitarbeiter->zrm_jahresstunden) ? $mitarbeiter->zrm_jahresstunden : '-',
					'zrm_stundensatz_lehre' => isset($mitarbeiter->zrm_stundensatz_lehre) ? $mitarbeiter->zrm_stundensatz_lehre : '-',
					'zrm_vertraege_kurzbz' => isset($mitarbeiter->zrm_vertraege_kurzbz) ? $mitarbeiter->zrm_vertraege_kurzbz : '-',
					'akt_bezeichnung' => isset($mitarbeiter->akt_bezeichnung) ? $mitarbeiter->akt_bezeichnung : '-',
					'akt_orgbezeichnung' => isset($mitarbeiter->akt_orgbezeichnung) ? $mitarbeiter->akt_orgbezeichnung : '-',
					'akt_parentbezeichnung' => isset($mitarbeiter->akt_parentbezeichnung) ? $mitarbeiter->akt_parentbezeichnung : '-',
					'akt_stunden' => isset($mitarbeiter->akt_stunden) ? $mitarbeiter->akt_stunden : '-',
					'vorname' => isset($mitarbeiter->vorname) ? $mitarbeiter->vorname : '-',
					'nachname' => isset($mitarbeiter->nachname) ? $mitarbeiter->nachname : '-',
				];
			}

			$mitarbeiterDataArray = [];

			foreach ($allinfos as $mitarbeiter)
			{
				$mitarbeiterData = clone $mitarbeiter;
				$info = isset($mitarbeiterInfos[$mitarbeiter->mitarbeiter_uid]) ? $mitarbeiterInfos[$mitarbeiter->mitarbeiter_uid] : new stdClass();

				if ($mitarbeiter->mitarbeiter_uid !== null)
				{
					$mitarbeiterData->zrm_vertraege = $info->zrm_vertraege;
					$mitarbeiterData->zrm_wochenstunden = $info->zrm_wochenstunden;
					$mitarbeiterData->zrm_jahresstunden = $info->zrm_jahresstunden;
					$mitarbeiterData->zrm_stundensatz_lehre = $info->zrm_stundensatz_lehre;
					$mitarbeiterData->zrm_vertraege_kurzbz = $info->zrm_vertraege_kurzbz;
					$mitarbeiterData->akt_bezeichnung = $info->akt_bezeichnung;
					$mitarbeiterData->akt_orgbezeichnung = $info->akt_orgbezeichnung;
					$mitarbeiterData->akt_parentbezeichnung = $info->akt_parentbezeichnung;
					$mitarbeiterData->akt_stunden = $info->akt_stunden;
					$mitarbeiterData->vorname = $info->vorname;
					$mitarbeiterData->nachname = $info->nachname;
				}

				$mitarbeiterDataArray[] = $mitarbeiterData;
			}

			$this->terminateWithSuccess($mitarbeiterDataArray);

		}

		$this->terminateWithSuccess($allinfos);

	}

	public function getRollen()
	{
		$language = $this->_getLanguageIndex();

		$this->_ci->PEPLVEntwicklungRolleModel->addSelect(
			'rolle_kurzbz,
			bezeichnung_mehrsprachig[('.$language.')] as bezeichnung'
		);

		$result = $this->_ci->PEPLVEntwicklungRolleModel->load();

		if (isError($result))
			$this->terminateWithError(getError($result));


		$this->terminateWithSuccess(hasData($result) ? getData($result) : array());

	}

	public function getStatus()
	{
		$language = $this->_getLanguageIndex();

		$this->_ci->PEPLVEntwicklungStatusModel->addSelect(
			'status_kurzbz,
			bezeichnung_mehrsprachig[('.$language.')] as bezeichnung
			'
		);

		$result = $this->_ci->PEPLVEntwicklungStatusModel->load();

		if (isError($result))
			$this->terminateWithError(getError($result));


		$this->terminateWithSuccess(hasData($result) ? getData($result) : array());
	}

	public function update()
	{
		$lv_entwicklung_post = $this->getPostJson();

		$updatableFields = array(
			'studiensemester_kurzbz',
			'mitarbeiter_uid',
			'rolle_kurzbz',
			'stunden',
			'werkvertrag_ects',
			'status_kurzbz',
			'anmerkung',
			'weiterentwicklung'
		);
		$updateData = array();


		$stammdaten = new stdClass();
		if (isset($lv_entwicklung_post->mitarbeiter_uid))
		{
			$stammdaten = $this->_ci->PEPModel->getMitarbeiterData(array($lv_entwicklung_post->mitarbeiter_uid), $lv_entwicklung_post->stammdaten_studiensemester);

			if (hasData($stammdaten))
				$stammdaten = getData($stammdaten)[0];
		}

		foreach ($updatableFields as $field)
		{
			$value = isset($lv_entwicklung_post->$field) ? $lv_entwicklung_post->$field : null;

			if ($value !== null)
			{
				$updateData[$field] = $value;
			}
			if ($value === '')
				$updateData[$field] = null;
		}

		if (!isset($lv_entwicklung_post->pep_lv_entwicklung_id) || is_null($lv_entwicklung_post->pep_lv_entwicklung_id))
		{
			if (isset($stammdaten->zrm_vertraege_kurzbz))
			{
				$match = array_intersect($this->_ci->config->item('lventwicklung_allow_ects_volume_edit'), explode("\n", $stammdaten->zrm_vertraege_kurzbz));

				if (empty($match) && ((isset($lv_entwicklung_post->werkvertrag_ects)  && !isEmptyString(strval($lv_entwicklung_post->werkvertrag_ects))) || (isset($lv_entwicklung_post->status_kurzbz) && !isEmptyString($lv_entwicklung_post->status_kurzbz))))
					$this->terminateWithError("Für interne Personen dürfen das Werkvertragsvolumen in ECTS sowie der Status nicht ausgefüllt werden.");
				else if (!empty($match) && isset($lv_entwicklung_post->stunden) && !isEmptyString(strval($lv_entwicklung_post->stunden)))
					$this->terminateWithError("Für externe Personen dürfen keine Stunden erfasst werden.");
			}

			$updateData['lehrveranstaltung_id'] = $lv_entwicklung_post->lehrveranstaltung_id;
			$updateData['weiterentwicklung'] = true;
			$updateData['insertvon'] = getAuthUID();
			$updateData['insertamum'] = date('Y-m-d H:i:s');
			$result = $this->_ci->PEPLVEntwicklungModel->insert($updateData);

			if (isError($result))
				$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);

			$new_id = getData($result);
			$result = $this->_ci->PEPLVEntwicklungModel->loadWhere(array('pep_lv_entwicklung_id' => $new_id));
			$new_entwicklung = getData($result)[0];

			foreach ($new_entwicklung as $key => $value)
			{
				$stammdaten->$key = $value;
			}

			$lv = $this->_ci->PEPLVEntwicklungModel->getLVInfos($new_entwicklung->lehrveranstaltung_id);
			$lehrveranstaltung = getData($lv)[0];

			foreach (get_object_vars($lehrveranstaltung) as $key => $value)
			{
				$stammdaten->$key = $value;
			}
			$emptyArray = [];
			$json = json_encode($emptyArray);
			$stammdaten->tags = $json;
		}
		else
		{
			$updateData['updatevon'] = getAuthUID();
			$updateData['updateamum'] = date('Y-m-d H:i:s');

			if (isset($stammdaten->zrm_vertraege_kurzbz))
			{
				$match = array_intersect($this->_ci->config->item('lventwicklung_allow_ects_volume_edit'), explode("\n", $stammdaten->zrm_vertraege_kurzbz));

				if (empty($match))
				{
					$updateData['werkvertrag_ects'] = null;
					$updateData['status_kurzbz'] = null;
				}
				else
					$updateData['stunden'] = null;
			}

			$result = $this->_ci->PEPLVEntwicklungModel->update(array('pep_lv_entwicklung_id' => $lv_entwicklung_post->pep_lv_entwicklung_id), $updateData);

			if (isError($result))
				$this->terminateWithError(getError($result));

			$stammdaten->pep_lv_entwicklung_id = $lv_entwicklung_post->pep_lv_entwicklung_id;
		}


		$this->terminateWithSuccess($stammdaten);
	}

	public function delete()
	{
		$lv_entwicklung_post = $this->getPostJson();

		if (!isset($lv_entwicklung_post->pep_lv_entwicklung_id) || isEmptyString(strval($lv_entwicklung_post->pep_lv_entwicklung_id)))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$result = $this->_ci->PEPLVEntwicklungModel->loadWhere(array('pep_lv_entwicklung_id' => $lv_entwicklung_post->pep_lv_entwicklung_id));

		if (isError($result))
			$this->terminateWithError(getError($result));

		if (!hasData($result))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$result = $this->_ci->PEPLVEntwicklungModel->delete(array('pep_lv_entwicklung_id' => $lv_entwicklung_post->pep_lv_entwicklung_id));

		if ($result)
		{
			$this->terminateWithSuccess($result);
		}
		else
		{
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);
		}
	}

	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}

	private function _getLanguageIndex()
	{
		$this->_ci->load->model('system/Sprache_model', 'SpracheModel');
		$this->_ci->SpracheModel->addSelect('index');
		$result = $this->_ci->SpracheModel->loadWhere(array('sprache' => getUserLanguage()));

		return hasData($result) ? getData($result)[0]->index : 1;
	}
}

