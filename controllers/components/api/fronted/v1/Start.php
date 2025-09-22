<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Start extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';

	public function __construct()
	{
		parent::__construct([
			'getStart' => self::BERECHTIGUNG_KURZBZ,
			'getStudiensemester' => self::BERECHTIGUNG_KURZBZ,
			'getCategories' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_LV_Entwicklung_model', 'PEPLVEntwicklungModel');

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

	public function getCategories()
	{
		$language = getUserLanguage() == 'German' ? 0 : 1;

		$columns = array();
		$this->_ci->PEPModel->addSelect('kategorie_id, array_to_json(bezeichnung_mehrsprachig::varchar[])->>'. $language. ' as beschreibung');
		$categoryColumns = $this->_ci->PEPModel->load();

		if (hasData($categoryColumns))
		{
			$columns['categories'] = getData($categoryColumns);
			$columns['mode']['categories'] = $this->_ci->config->item('category_columns');
		}

		$columns['lehrauftraege'] = true;

		if ($this->_ci->config->item('enable_projects') === true)
		{
			$columns['projects'] = true;
			$columns['mode']['projects'] = $this->_ci->config->item('projects_columns');
		}

		if ($this->_ci->config->item('enable_lv_entwicklung_tab') === true)
		{
			$columns['lventwicklung'] = true;
			$columns['mode']['lventwicklung'] = $this->_ci->config->item('lvenwticklung_columns');
		}

		$this->terminateWithSuccess($columns);
	}

	public function getStudiensemester()
	{
		$studienjahr = $this->_ci->input->get('studienjahr');
		$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz');
		$this->_ci->StudiensemesterModel->addOrder('start');
		$studiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($studiensemestern))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

		$studiensemester = array_column(getData($studiensemestern), 'studiensemester_kurzbz');

		$this->terminateWithSuccess($studiensemester);
	}

	public function getStart()
	{
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		$studiensemester = $this->_ci->input->get('semester');
		$recursive = $this->_ci->input->get('recursive');
		$oldSemester = $this->_ci->input->get('oldSemester') === "true";

		if (isEmptyString($org) || (isEmptyString($studienjahr) && isEmptyString($studiensemester)))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		if (isEmptyArray($studiensemester))
		{
			$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz');
			$this->_ci->StudiensemesterModel->addOrder('start');
			$studiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
			if (!hasData($studiensemestern))
				$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);
			$studiensemester = array_column(getData($studiensemestern), 'studiensemester_kurzbz');
		}

		$allMitarbeiterUid = getMitarbeiterUids($org, $studiensemester, $recursive === "true");
		$allMitarbeiter = $this->_ci->PEPModel->_getDVs($allMitarbeiterUid, (isEmptyString($studienjahr) ? null : $studienjahr), isEmptyString($studienjahr) ? $studiensemester : null);

		if (!hasData($allMitarbeiter))
			$this->terminateWithSuccess([]);

		$mitarbeiterDataArray = array();
		$projectColumnsStudiensemester = $this->_ci->config->item('projects_columns') === 'studiensemester';
		$categoriesColumnsStudiensemester = $this->_ci->config->item('category_columns') === 'studiensemester';
		$lvenwicklungColumnsStudiensemester = $this->_ci->config->item('lvenwticklung_columns') === 'studiensemester';
		foreach (getData($allMitarbeiter) as $mitarbeiter)
		{
			$mitarbeiterData = $mitarbeiter;

			$mitarbeiterData->karenz = isEmptyString($mitarbeiter->karenzvon);

			$this->getLehrauftraegeEachStudiensemester($mitarbeiterData, $studiensemester, $oldSemester);
			$this->getColumnsEachStudiensemester($mitarbeiterData, $studiensemester, $projectColumnsStudiensemester, $categoriesColumnsStudiensemester, $lvenwicklungColumnsStudiensemester);
			$this->getColumnsEachStudienjahr($mitarbeiterData, $studienjahr, !$projectColumnsStudiensemester, !$categoriesColumnsStudiensemester, !$lvenwicklungColumnsStudiensemester);
			$mitarbeiterData->summe = $mitarbeiter->zrm_einzeljahresstunden;

			$mitarbeiterDataArray[] = $mitarbeiterData;
		}

		$this->terminateWithSuccess($mitarbeiterDataArray);
	}

	private function getColumnsEachStudienjahr(&$mitarbeiterData, $studienjahr, $projects = false, $categories = false, $lventwicklung = false)
	{
		if ($projects)
			$this->getProjectStundenByYear($mitarbeiterData, $mitarbeiterData->uid, $studienjahr);

		if ($categories)
		{

			$kategorien = $this->_ci->PEPModel->getCategoryStundenByMitarbeiter($mitarbeiterData->uid, null, $studienjahr);
			if (hasData($kategorien))
			{
				foreach(getData($kategorien) as $kategorie)
				{
					$categorykeyname = "studiensemester_kategorie_" . $kategorie->kategorie_id;
					$mitarbeiterData->$categorykeyname = ($kategorie->stunden);;
				}
			}
		}

		if ($lventwicklung)
			$this->getLVEntwicklungStundenByYear($mitarbeiterData, $mitarbeiterData->uid, $studienjahr);
	}

	private function getLehrauftraegeEachStudiensemester(&$mitarbeiterData, $studiensemester, $oldSemester = false)
	{
		if ($oldSemester)
		{
			$studiensemester = array_map(function($item) {
				if (strpos($item, 'SS') === 0) {
					$jahr = substr($item, -4) - 1;
					return "SS" . $jahr;
				}
				return $item;
			}, $studiensemester);
		}

		$ststemDV = $this->_ci->PEPModel->getDVForSemester($mitarbeiterData->uid, $studiensemester);

		foreach ($studiensemester as $key => $ststem)
		{
			$lehrauftragsstunden = [];
			$lvstunden = 0;
			$keyname = "studiensemester_" . $key . "_lehrauftrag";

			if (hasData($ststemDV))
			{
				$allVertraege = getData($ststemDV);

				$ststemDVForCurrentSemester = [];
				foreach ($allVertraege as $dv)
				{
					if ($dv->studiensemester_kurzbz === $ststem)
					{
						$ststemDVForCurrentSemester[$dv->studiensemester_kurzbz] = $dv;
					}
				}

				if (isset($ststemDVForCurrentSemester[$ststem]->vertragsart_kurzbz))
				{
					if (($ststemDVForCurrentSemester[$ststem]->vertragsart_kurzbz) === 'echterdv')
					{
						$lehrauftragsstunden = $this->_ci->PEPModel->getLehrauftraegeStundenWithFaktor($mitarbeiterData->uid, $ststem);
					}
					else
					{
						$lehrauftragsstunden = $this->_ci->PEPModel->getLehrauftraegeStundenWithoutFaktor($mitarbeiterData->uid, $ststem);
					}
				}
			}
			$lvstunden = hasData($lehrauftragsstunden) ? getData($lehrauftragsstunden)[0]->stunden : $lvstunden;

		$mitarbeiterData->$keyname = $lvstunden;
		}
	}

	private function getColumnsEachStudiensemester(&$mitarbeiterData, $studiensemester, $projects = false, $categories = false, $lventwicklung = false)
	{
		$ststemDV = $this->_ci->PEPModel->getDVForSemester($mitarbeiterData->uid, $studiensemester);

		foreach ($studiensemester as $key => $ststem)
		{
			if (hasData($ststemDV))
			{
				$allVertraege = getData($ststemDV);

				$ststemDVForCurrentSemester = [];
				foreach ($allVertraege as $dv)
				{
					if ($dv->studiensemester_kurzbz === $ststem)
					{
						$ststemDVForCurrentSemester[$dv->studiensemester_kurzbz] = $dv;
					}
				}
				if ($categories)
				{
					$kategorien = $this->_ci->PEPModel->getCategoryStundenByMitarbeiter($mitarbeiterData->uid, $ststem);
					if (hasData($kategorien))
					{
						foreach(getData($kategorien) as $kategorie)
						{
							$categorykeyname = "studiensemester_" . $key . "_kategorie_" . $kategorie->kategorie_id;
							$mitarbeiterData->$categorykeyname = ($kategorie->stunden / 2);
						}
					}
				}

				if ($projects)
				{
					$this->getProjectStundenBySemester($mitarbeiterData, $mitarbeiterData->uid, $key, $ststem);
				}

				if ($lventwicklung)
				{
					$this->getLVEntwicklungStundenBySemester($mitarbeiterData, $mitarbeiterData->uid, $key, $ststem);
				}
			}
		}
	}

	private function getLVEntwicklungStundenBySemester(&$mitarbeiterData, $uid, $key, $ststem)
	{
		if ($this->_ci->config->item('enable_lv_entwicklung_tab'))
		{
			$lventiwcklungstunden = 0;
			$keyproject = "studiensemester_" . $key . "_lv_entwicklung";
			$lventwicklung =  $this->_ci->PEPLVEntwicklungModel->getLVEntwicklungStundenByEmployee($uid, $ststem);
			$lventiwcklungstunden = hasData($lventwicklung) ? getData($lventwicklung)[0]->stunden : $lventiwcklungstunden;
			$mitarbeiterData->$keyproject = $lventiwcklungstunden;
		}
	}

	private function getLVEntwicklungStundenByYear(&$mitarbeiterData, $uid, $studienjahr)
	{
		if ($this->_ci->config->item('enable_lv_entwicklung_tab'))
		{
			$lventiwcklungstunden = 0;
			$keyproject = "studiensemester_lv_entwicklung";
			$lventwicklung =  $this->_ci->PEPLVEntwicklungModel->getLVEntwicklungStundenByEmployee($uid, null, $studienjahr);
			$lventiwcklungstunden = hasData($lventwicklung) ? getData($lventwicklung)[0]->stunden : $lventiwcklungstunden;
			$mitarbeiterData->$keyproject = $lventiwcklungstunden;
		}
	}

	private function getProjectStundenBySemester(&$mitarbeiterData, $uid, $key, $ststem)
	{
		if ($this->_ci->config->item('enable_projects'))
		{
			$projectstunden = 0;
			$keyproject = "studiensemester_" . $key . "_project";
			$projects =  $this->_ci->PEPModel->getProjectStundenByEmployee($uid, $ststem);
			$projectstunden = hasData($projects) ? getData($projects)[0]->stunden/2 : $projectstunden;
			$mitarbeiterData->$keyproject = $projectstunden;
		}
	}

	private function getProjectStundenByYear(&$mitarbeiterData, $uid, $studienjahr)
	{
		if ($this->_ci->config->item('enable_projects'))
		{
			$projectstunden = 0;
			$keyproject = "studiensemester_project";
			$projects =  $this->_ci->PEPModel->getProjectStundenByEmployee($uid, null, $studienjahr);
			$projectstunden = hasData($projects) ? getData($projects)[0]->stunden : $projectstunden;
			$mitarbeiterData->$keyproject = $projectstunden;
		}
	}

	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}

}

