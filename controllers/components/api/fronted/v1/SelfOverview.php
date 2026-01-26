<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class SelfOverview extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;

	const BERECHTIGUNG_KURZBZ = 'extension/pep_selfoverview:r';

	public function __construct()
	{
		parent::__construct([
			'getSelfOverview' => [self::BERECHTIGUNG_KURZBZ, 'extension/pep:r'],
			'getLektoren' => ['extension/pep:rw'],
		]);

		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->model('person/Benutzer_model', 'BenutzerModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_LV_Entwicklung_model', 'PEPLVEntwicklungModel');



		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->load->helper('extensions/FHC-Core-PEP/hlp_employee_helper');

		$this->_ci->load->config('extensions/FHC-Core-PEP/pep');

		$this->loadPhrases(
			array(
				'ui'
			)
		);
		$this->_setAuthUID();

	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods
	public function getSelfOverview()
	{
		$mode = $this->_ci->input->get('mode');

		if (isEmptyString($mode))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		if ($mode !== 'studienjahre' && $mode !== 'studiensemester')
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$zeitspanne = $this->_ci->input->get('zeitspanne');
		if (isEmptyString($zeitspanne))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$uid = $this->_uid;

		$ignore_mode = false;
		if ($this->_ci->permissionlib->isBerechtigt('admin') || $this->_ci->permissionlib->isBerechtigt('extension/pep'))
		{
			$uid = $this->_ci->input->get('uid');
			if (isEmptyString($uid))
				$uid = $this->_uid;
			else
			{
				$user = $this->_ci->BenutzerModel->load(array('uid' => $uid));

				if (isError($user))
					$this->terminateWithError(getError($user));

				if (!hasData($user))
					$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);
			}

			$ignore_mode = true;
		}


		$this->load->model('vertragsbestandteil/Dienstverhaeltnis_model','DienstverhaeltnisModel');
		$today = date("Y-m-d");
		$echterdv_result = $this->DienstverhaeltnisModel->existsDienstverhaeltnis($uid, $today, $today, 'echterdv');

		$result_studiensemester = null;
		$where = null;

		if ($ignore_mode && $mode === 'studienjahre')
		{
			$where = ['studienjahr_kurzbz' => $zeitspanne];
		}
		elseif (!$ignore_mode)
		{
			if (hasData($echterdv_result) && $mode === 'studienjahre')
			{
				$this->_ci->StudienjahrModel->addDistinct('studienjahr_kurzbz');
				$this->_ci->StudienjahrModel->addSelect('tbl_studienjahr.*');
				$this->_ci->StudienjahrModel->addJoin('public.tbl_studiensemester', 'studienjahr_kurzbz');
				$zeitspanne_check = $this->_ci->StudienjahrModel->loadWhere(array('start >= ' => $today));

				if (!hasData($zeitspanne_check))
					$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

				$zeitspanne_check = array_column(getData($zeitspanne_check), 'studienjahr_kurzbz');

				if (!in_array($zeitspanne, $zeitspanne_check))
					$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

				$where = ['studienjahr_kurzbz' => $zeitspanne];
			}
			elseif (!hasData($echterdv_result) && $mode === 'studiensemester')
			{
				$zeitspanne_check = $this->_ci->StudiensemesterModel->getNext();
				if (!hasData($zeitspanne_check))
					$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

				$zeitspanne_check = getData($zeitspanne_check)[0]->studiensemester_kurzbz;

				if ($zeitspanne_check !== $zeitspanne)
					$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);
				$where = ['studiensemester_kurzbz' => $zeitspanne];
			}
		}

		if ($where !== null)
		{
			$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz, studienjahr_kurzbz');
			$this->_ci->StudiensemesterModel->addOrder('start');
			$result_studiensemester = $this->_ci->StudiensemesterModel->loadWhere(
				$where
			);
		}

		if (!hasData($result_studiensemester))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);
		$result_data = getData($result_studiensemester);

		$studiensemester = array_column($result_data, 'studiensemester_kurzbz');
		$studienjahr = $result_data[0]->studienjahr_kurzbz;

		$mitarbeiter_result = $this->_ci->PEPModel->getRelevanteVertragsart(array($uid), $studiensemester);

		if (!hasData($mitarbeiter_result))
			$this->terminateWithSuccess([]);

		$mitarbeiter_data = getData($mitarbeiter_result)[0];

		$lehrauftraege = $this->_ci->PEPModel->getMitarbeiterLehre('', $studiensemester, false, array($mitarbeiter_data->uid));

		$result = [];

		$language = getUserLanguage() == 'German' ? 0 : 1;

		$result['config']['echterdv'] = $mitarbeiter_data->releavante_vertragsart === 'echterdv';
		if (hasData($lehrauftraege))
		{
			foreach (getData($lehrauftraege) as $lehrauftrag)
			{
				$result['data'][] = array('typ' => $this->p->t('ui', 'lehrauftrag'),
					'beschreibung' => $language === 0 ? $lehrauftrag->lv_bezeichnung : (!isEmptyString($lehrauftrag->lv_bezeichnung_eng) ? $lehrauftrag->lv_bezeichnung_eng : $lehrauftrag->lv_bezeichnung),
					'stunden' => $lehrauftrag->faktorstunden,
					'zeit' => $lehrauftrag->studiensemester_kurzbz,
					'stg' => $lehrauftrag->stg_kuerzel,
					'lehrform' => $lehrauftrag->lehrform_kurzbz,
					'gruppe' => $lehrauftrag->gruppe,
					'info' => $this->_filterTags($lehrauftrag->tags),
				);
			}
		}

		if ($this->_ci->config->item('enable_lv_entwicklung_tab') === true)
		{
			$lventwicklung_data = $this->_ci->PEPLVEntwicklungModel->getLVEntwicklung(array(''), $studiensemester, array($mitarbeiter_data->uid), '', false);

			if (hasData($lventwicklung_data))
			{
				foreach (getData($lventwicklung_data) as $data)
				{
					$lventwicklungarray = array('typ' => $this->p->t('ui', 'lventwicklung'),
						'beschreibung' => $language === 0 ? $data->lvbezeichnung : (!isEmptyString($data->lvbezeichnungeng) ? $data->lvbezeichnungeng : $data->lvbezeichnung),
						'zeit' => $data->studiensemester_kurzbz,
						'stg' => $data->stg_kuerzel,
						'lehrform' => $data->lv_lehrform_kurzbz,
						'gruppe' => null,
						'info' => $this->_filterTags($data->tags),
					);

					if ($data->rolle_kurzbz === 'lead')
					{
						$lventwicklungarray['lead'] = true;
						$member = $this->_ci->PEPLVEntwicklungModel->getMember($data->allelvsid, $studiensemester, $mitarbeiter_data->uid);
						$lventwicklungarray['empinfos'] = hasData($member) ? array_column(getData($member), 'member') : null;
					}
					else
					{
						$lventwicklungarray['lead'] = false;
						$lead = $this->_ci->PEPLVEntwicklungModel->getLead($data->allelvsid, $studiensemester, $mitarbeiter_data->uid);
						$lventwicklungarray['empinfos'] = hasData($lead) ? array_column(getData($lead), 'lead') : null;
					}

					if ($mitarbeiter_data->releavante_vertragsart !== 'echterdv')
						$lventwicklungarray['ects'] = $data->werkvertrag_ects;
					else
					{
						$lventwicklungarray['stunden'] = $data->stunden;
						$lventwicklungarray['anmerkung'] = $data->anmerkung;
					}

					$result['data'][] = $lventwicklungarray;
				}
			}
		}

		if ($mitarbeiter_data->releavante_vertragsart !== 'echterdv')
			$this->terminateWithSuccess($result);

		$this->_ci->PEPModel->addSelect('kategorie_id, array_to_json(bezeichnung_mehrsprachig::varchar[])->>'. $language. ' as beschreibung');
		$categories = $this->_ci->PEPModel->load();

		if (hasData($categories))
		{
			foreach (getData($categories) as $category)
			{
				$category_data = $this->_ci->PEPModel->getCategoryData(array($mitarbeiter_data->uid), $category->kategorie_id, $studienjahr);

				if (hasData($category_data))
				{
					forEach(getData($category_data) as $data)
					{

						$result['data'][] = array('typ' => $this->p->t('ui', 'kategorie'),
							'beschreibung' => $category->beschreibung,
							'stunden' => $data->stunden,
							'anmerkung' => $data->anmerkung,
							'zeit' => $studienjahr,
							'stg' => null,
							'lehrform' => null,
							'gruppe' => null,

						);
					}
				}
			}
		}

		if ($this->_ci->config->item('enable_projects') === true)
		{
			$project_data = $this->_ci->PEPModel->getProjectData(array($mitarbeiter_data->uid), $studienjahr, '', false);

			if (hasData($project_data))
			{
				foreach (getData($project_data) as $data)
				{
					$result['data'][] = array('typ' => $this->p->t('ui', 'projekt'),
						'beschreibung' => $data->name,
						'stunden' => $data->stunden,
						'anmerkung' => $data->anmerkung,
						'zeit' => $studienjahr,
						'stg' => null,
						'lehrform' => null,
						'gruppe' => null,
					);
				}
			}
		}

		$this->terminateWithSuccess($result);
	}
	public function getLektoren()
	{
		$studienjahr = $this->_ci->input->get('studienjahr');
		if (isEmptyString($studienjahr))
		{
			$studienjahr = $this->_ci->StudienjahrModel->getAktOrNextStudienjahr();
		}
		else
		{
			$studienjahr = $this->_ci->StudienjahrModel->load($studienjahr);
		}

		if (isError($studienjahr))
			$this->terminateWithError(getError($studienjahr));

		if (!hasData($studienjahr))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

		$studienjahr = getData($studienjahr)[0]->studienjahr_kurzbz;


		$studiensemester = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));

		if (!hasData($studiensemester))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

		$studiensemestern = array_column(getData($studiensemester), 'studiensemester_kurzbz');

		if ($this->_ci->permissionlib->isBerechtigt('admin'))
		{
			$this->_ci->BenutzerModel->addSelect('nachname, vorname, uid, kurzbz');
			$this->_ci->BenutzerModel->addJoin('campus.vw_mitarbeiter', 'uid');
			$this->_ci->BenutzerModel->addOrder('nachname');
			$lektoren = $this->_ci->BenutzerModel->loadWhere("tbl_benutzer.aktiv IS TRUE");
		}
		else
		{
			$oeKurzbz = $this->_ci->permissionlib->getOE_isEntitledFor('extension/pep');

			$mitarbeiter_uid = array();

			$uids = getMitarbeiterUids($oeKurzbz, $studiensemestern, false);
			$mitarbeiter_uid = array_merge($mitarbeiter_uid, $uids);
			$mitarbeiter_uid = array_values(array_unique($mitarbeiter_uid));

			$this->_ci->BenutzerModel->addSelect('nachname, vorname, uid, kurzbz');
			$this->_ci->BenutzerModel->addJoin('campus.vw_mitarbeiter', 'uid');
			$this->_ci->BenutzerModel->addOrder('nachname');

			$lektoren = $this->_ci->BenutzerModel->loadWhere("tbl_benutzer.aktiv IS TRUE AND uid IN ('". implode("', '", $mitarbeiter_uid) . "')");
		}

		$this->terminateWithSuccess(hasData($lektoren) ? getData($lektoren) : []);
	}

	private function _filterTags($tags, $returnjson = false)
	{
		$tags = json_decode($tags, true);
		$lehrende_tags = array_filter($tags, function ($tag) { return  $tag['typ_kurzbz'] === 'hinweis_lehrende' && $tag['done'] === false;});
		if ($returnjson)
			return json_encode(array_values($lehrende_tags));
		return implode("\n", array_column($lehrende_tags, 'notiz'));
	}

	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}

}

