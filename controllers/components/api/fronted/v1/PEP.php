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
			'stundenzuruecksetzen' => self::BERECHTIGUNG_KURZBZ,
			'getCategories' => self::BERECHTIGUNG_KURZBZ,
			'saveMitarbeiter' => self::BERECHTIGUNG_KURZBZ,
			'getLektoren' => self::BERECHTIGUNG_KURZBZ,
			'getProjekte' => self::BERECHTIGUNG_KURZBZ,
			'getRaumtypen' => self::BERECHTIGUNG_KURZBZ,
			'getLehreinheit' => self::BERECHTIGUNG_KURZBZ,
			'getLehreinheiten' => self::BERECHTIGUNG_KURZBZ,
			'saveLehreinheit' => self::BERECHTIGUNG_KURZBZ,
			'updateAnmerkung' => self::BERECHTIGUNG_KURZBZ,
			'setVariables' => self::BERECHTIGUNG_KURZBZ,
			'getStudienjahre' => self::BERECHTIGUNG_KURZBZ,
			'getStartAndEnd' => self::BERECHTIGUNG_KURZBZ,
			'getOrganisationen' => self::BERECHTIGUNG_KURZBZ,
			'getProjects' => self::BERECHTIGUNG_KURZBZ,
			'addProjectStunden' => self::BERECHTIGUNG_KURZBZ,
			'deleteProjectStunden' => self::BERECHTIGUNG_KURZBZ,
			'updateProjectStunden' => self::BERECHTIGUNG_KURZBZ,
			'updateFaktor' => self::BERECHTIGUNG_KURZBZ,
			'getStudiensemester' => self::BERECHTIGUNG_KURZBZ,

		]);

		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studiengang_model', 'StudiengangModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('organisation/Organisationseinheit_model', 'OrganisationseinheitModel');
		$this->_ci->load->model('person/Benutzerfunktion_model', 'BenutzerfunktionModel');
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_Kategorie_Mitarbeiter_model', 'PEPKategorieMitarbeiterModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_Projects_Employees_model', 'PEPProjectsEmployeesModel');
		$this->_ci->load->model('education/Lehreinheit_model', 'LehreinheitModel');
		$this->_ci->load->model('education/Lehreinheitmitarbeiter_model', 'LehreinheitmitarbeiterModel');
		$this->_ci->load->model('education/LehrveranstaltungFaktor_model', 'LehrveranstaltungFaktorModel');
		$this->_ci->load->model('ressource/Raumtyp_model', 'RaumtypModel');
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('system/Variable_model', 'VariableModel');

		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_ci->load->library('PhrasesLib');

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
		$columns = array();
		$this->_ci->PEPModel->addSelect('kategorie_id, array_to_json(bezeichnung_mehrsprachig::varchar[])->>0 as beschreibung');
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

		$this->terminateWithSuccess($columns);
	}

	public function getStudienjahre()
	{
		$this->_ci->StudienjahrModel->addOrder('studienjahr_kurzbz', 'DESC');
		$studienjahre = getData($this->_ci->StudienjahrModel->load());

		$this->terminateWithSuccess($studienjahre);
	}

	public function getStartAndEnd()
	{
		$studienjahr = $this->_ci->input->get('studienjahr');

		$this->terminateWithSuccess($this->getMinStartAndMaxEnd($studienjahr));
	}

	private function getMinStartAndMaxEnd($studienjahr)
	{
		$this->_ci->StudiensemesterModel->addSelect('MIN(start) as start, MAX(ende) as ende, studienjahr_kurzbz');
		$this->_ci->StudiensemesterModel->addGroupBy('studienjahr_kurzbz');

		$dates = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		return hasData($dates) ? getData($dates)[0] : [];
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

	public function getOrganisationen()
	{
		$oeKurzbz = $this->_ci->permissionlib->getOE_isEntitledFor(self::BERECHTIGUNG_KURZBZ);
		$this->_ci->OrganisationseinheitModel->addSelect('organisationseinheittyp_kurzbz, bezeichnung, oe_kurzbz');
		$this->_ci->OrganisationseinheitModel->addOrder('organisationseinheittyp_kurzbz');
		$organisationen = $this->_ci->OrganisationseinheitModel->loadWhere("oe_kurzbz IN ('". implode("', '", $oeKurzbz) . "')");
		$this->terminateWithSuccess(getData($organisationen));
	}
	public function setVariables()
	{
		$variables = $this->getPostJson();
		$studienjahr = $variables->var_studienjahr;
		$studiensemester = $variables->var_studiensemester;
		$org = $variables->var_organisation;

		if (!isEmptyString($studienjahr))
			$this->_ci->VariableModel->setVariable($this->_uid, 'pep_studienjahr', $studienjahr);

		if (!isEmptyString($studiensemester))
			$this->_ci->VariableModel->setVariable($this->_uid, 'pep_studiensemester', implode(",", $studiensemester));

		if (!isEmptyString($org))
			$this->_ci->VariableModel->setVariable($this->_uid, 'pep_abteilung', $org);
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

		$allMitarbeiterUid = $this->_getMitarbeiterUids($org, $studiensemester, $recursive === "true");
		$allMitarbeiter = $this->_ci->PEPModel->_getDVs($allMitarbeiterUid, (isEmptyString($studienjahr) ? null : $studienjahr), isEmptyString($studienjahr) ? $studiensemester : null);

		if (!hasData($allMitarbeiter))
			$this->terminateWithSuccess([]);

		$mitarbeiterDataArray = array();
		$projectColumnsStudiensemester = $this->_ci->config->item('projects_columns') === 'studiensemester';
		$categoriesColumnsStudiensemester = $this->_ci->config->item('category_columns') === 'studiensemester';
		foreach (getData($allMitarbeiter) as $mitarbeiter)
		{
			$mitarbeiterData = $mitarbeiter;

			$mitarbeiterData->karenz = isEmptyString($mitarbeiter->karenzvon);

			$this->getLehrauftraegeEachStudiensemester($mitarbeiterData, $studiensemester, $oldSemester);
			$this->getColumnsEachStudiensemester($mitarbeiterData, $studiensemester, $projectColumnsStudiensemester, $categoriesColumnsStudiensemester);
			$this->getColumnsEachStudienjahr($mitarbeiterData, $studienjahr, !$projectColumnsStudiensemester, !$categoriesColumnsStudiensemester);
			$mitarbeiterData->summe = $mitarbeiter->zrm_einzeljahresstunden;

			$mitarbeiterDataArray[] = $mitarbeiterData;
		}

		$this->terminateWithSuccess($mitarbeiterDataArray);
	}

	private function getColumnsEachStudienjahr(&$mitarbeiterData, $studienjahr, $projects = false, $categories = false)
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
	private function getColumnsEachStudiensemester(&$mitarbeiterData, $studiensemester, $projects = false, $categories = false)
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
							$mitarbeiterData->$categorykeyname = ($kategorie->stunden / 2);;
						}
					}
				}

				if ($projects)
				{
					$this->getProjectStundenBySemester($mitarbeiterData, $mitarbeiterData->uid, $key, $ststem);
				}
			}
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

	public function getCategory()
	{
		$category = $this->_ci->input->get('category_id');
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		$recursive = $this->_ci->input->get('recursive');

		$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz');
		$studiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($studiensemestern))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

		$studiensemestern = array_column(getData($studiensemestern), 'studiensemester_kurzbz');

		$mitarbeiter_uids = $this->_getMitarbeiterUids($org, $studiensemestern, $recursive === "true");

		$categoryData = $this->_ci->PEPModel->getCategoryData($mitarbeiter_uids, $category, $studienjahr);

		$dienstverhaeltnisse = $this->_ci->PEPModel->_getDVs($mitarbeiter_uids, $studienjahr);

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
		if (hasData($categoryData))
		{
			foreach (getData($categoryData) as $empCategoryData)
			{
				$mitarbeiterData = clone $empCategoryData;
				$info = isset($mitarbeiterInfos[$empCategoryData->mitarbeiter_uid]) ? $mitarbeiterInfos[$empCategoryData->mitarbeiter_uid] : new stdClass();

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
		}

		$this->terminateWithSuccess($mitarbeiterDataArray);
	}

	public function getProjects()
	{
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		$recursive = $this->_ci->input->get('recursive');

		$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz');
		$studiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($studiensemestern))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

		$studiensemestern = array_column(getData($studiensemestern), 'studiensemester_kurzbz');
		$mitarbeiter_uids = $this->_getMitarbeiterUids($org, $studiensemestern , $recursive === "true");

		$projectsData = $this->_ci->PEPModel->getProjectData($mitarbeiter_uids, $studienjahr, $org);
		$this->terminateWithSuccess(hasData($projectsData) ? getData($projectsData) : array());
	}

	public function stundenzuruecksetzen()
	{
		$data = $this->getPostJson();

		$studienjahr = $data->studienjahr;
		$category_id = $data->category_id;
		$org = $data->org;

		$recursive = $data->recursive;

		$aktStudiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($aktStudiensemestern))
			$this->terminateWithSuccess(false);

		$aktStudiensemestern = array_column(getData($aktStudiensemestern), 'studiensemester_kurzbz');


		$aktStudienjahrUids = $this->_getMitarbeiterUids($org, $aktStudiensemestern, $recursive === "true");

		$this->_ci->PEPKategorieMitarbeiterModel->addSelect('kategorie_mitarbeiter_id');
		$neededDelete = $this->_ci->PEPKategorieMitarbeiterModel->loadWhere('
			mitarbeiter_uid IN ('. implode(',', $this->_ci->db->escape($aktStudienjahrUids)) .')
			AND studienjahr_kurzbz = '. $this->_ci->db->escape($studienjahr).'
			AND kategorie_id = '. $this->_ci->db->escape($category_id) .'
		');

		if (hasData($neededDelete))
		{
			$neededDeleteIds = array_column(getData($neededDelete), 'kategorie_mitarbeiter_id');

			foreach ($neededDeleteIds as $id)
			{
				$this->_ci->PEPKategorieMitarbeiterModel->delete(array($id));
			}
		}
			$this->terminateWithSuccess();

	}

	public function vorruecken()
	{
		$data = $this->getPostJson();

		$fromStudienjahr = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $data->fromStudienjahr));
		if (!hasData($fromStudienjahr))
			$this->terminateWithSuccess(false);
		$fromStudiensemester = array_column(getData($fromStudienjahr), 'studiensemester_kurzbz');

		$toStudienjahr = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $data->toStudienjahr));
		if (!hasData($toStudienjahr))
			$this->terminateWithSuccess(false);
		$toStudiensemester = array_column(getData($toStudienjahr), 'studiensemester_kurzbz');

		$aktStudienjahrUids = $this->_getMitarbeiterUids($data->organisation, $fromStudiensemester, $data->recursive === "true");
		$newStudienjahrUids = $this->_getMitarbeiterUids($data->organisation, $toStudiensemester, $data->recursive === "true");
		$uidsNeedUpdate = array_intersect($aktStudienjahrUids, $newStudienjahrUids);

		$fromStudienjahr = getData($fromStudienjahr)[0]->studienjahr_kurzbz;
		$toStudienjahr = getData($toStudienjahr)[0]->studienjahr_kurzbz;
		$nichtVorgerueckt = [];

		foreach ($data->kategorien as $kategorie)
		{
			$exists = $this->_ci->PEPKategorieMitarbeiterModel->loadWhere('
				mitarbeiter_uid IN ('. implode(',', $this->_ci->db->escape($newStudienjahrUids)) .')
				AND studienjahr_kurzbz = '. $this->_ci->db->escape($toStudienjahr).'
				AND kategorie_id = '. $this->_ci->db->escape($kategorie) .'
			');


			if (hasData($exists))
			{
				$nichtVorgerueckt[] = $kategorie;
				continue;
			}
			$this->_ci->PEPKategorieMitarbeiterModel->vorruecken($fromStudienjahr, $toStudienjahr, $kategorie, $uidsNeedUpdate);
		}

		$this->terminateWithSuccess($nichtVorgerueckt);
	}

	public function addProjectStunden()
	{

		$data = $this->getPostJson();
		$studienjahr = $data->studienjahr;
		$studiensemester = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($studiensemester))
			$this->terminateWithSuccess(false);

		$studiensemester = array_column(getData($studiensemester), 'studiensemester_kurzbz');
		$mitarbeiteruids = $this->_ci->_getMitarbeiterUids($data->org, $studiensemester, true);

		if (!in_array($data->lektor, $mitarbeiteruids) &&
			!hasData($this->_ci->PEPModel->isProjectAssignedToOrganization($data->org, $data->project)))
		{
			$this->terminateWithError($this->p->t('ui', 'maprojohneoe'), self::ERROR_TYPE_GENERAL);
		}

		if ((property_exists($data, 'lektor')) &&
			(property_exists($data, 'project')) &&
			(property_exists($data, 'studienjahr')) &&
			(property_exists($data, 'stunden')))
		{
			if (isEmptyString($data->lektor) || isEmptyString($data->project) || isEmptyString($data->studienjahr) || isEmptyString($data->stunden))
				$this->terminateWithSuccess(false);

			$exist = $this->_ci->PEPProjectsEmployeesModel->loadWhere(array(
				'projekt_id' => $data->project,
				'mitarbeiter_uid' => $data->lektor,
				'studienjahr_kurzbz' => $data->studienjahr,
			));

			if (hasData($exist))
				$this->terminateWithSuccess(true);

			$result = $this->_ci->PEPProjectsEmployeesModel->insert(array(
				'projekt_id' => $data->project,
				'mitarbeiter_uid' => $data->lektor,
				'stunden' => $data->stunden,
				'anmerkung' => isset($data->anmerkung) ? $data->anmerkung: null,
				'studienjahr_kurzbz' => $data->studienjahr,
				'insertamum' => date('Y-m-d H:i:s'),
				'insertvon' => $this->_uid
			));

			if (isError($result))
				$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);

			$result = $this->_ci->PEPModel->getProjectRow($data->studienjahr, $result->retval);

			$returnResult = hasData($result) ? getData($result)[0] : array();
			$returnResult->anmerkung = isset($data->anmerkung) ? $data->anmerkung: null;
			$this->terminateWithSuccess($returnResult);
		}
	}

	public function deleteProjectStunden()
	{
		$data = $this->getPostJson();

		if ((property_exists($data, 'id')) &&
			(property_exists($data, 'uid')))
		{
			$result = $this->_ci->PEPProjectsEmployeesModel->loadWhere(array('pep_projects_employees_id' =>  $data->id, 'mitarbeiter_uid' => $data->uid));

			if (hasData($result))
			{
				$deleteResult = $this->_ci->PEPProjectsEmployeesModel->delete(array('pep_projects_employees_id' =>  $data->id));
				$this->terminateWithSuccess($deleteResult);
			}
				$this->terminateWithSuccess(false);

		}
	}

	public function updateProjectStunden()
	{
		$data = $this->getPostJson();

		if ((property_exists($data, 'project_id')) &&
			(property_exists($data, 'id')) &&
			(property_exists($data, 'uid')))
		{
			if ($data->id !== null)
			{
				$result = $this->_ci->PEPProjectsEmployeesModel->loadWhere(array('pep_projects_employees_id' =>  $data->id, 'mitarbeiter_uid' => $data->uid));

				if (hasData($result))
				{
					$updateResult = $this->_ci->PEPProjectsEmployeesModel->update(
						array('pep_projects_employees_id' => $data->id),
						array(
							'stunden' => is_numeric($data->stunden) ? $data->stunden : 0,
							'anmerkung' => isset($data->anmerkung) ? ($data->anmerkung) : null,
							'updatevon' => $this->_uid,
							'updateamum' => date('Y-m-d H:i:s'),
						)
					);
					$this->terminateWithSuccess($data->id);
				}
				$this->terminateWithSuccess(false);
			}
			else
			{
				$result = $this->_ci->PEPProjectsEmployeesModel->insert(array(
					'projekt_id' => $data->project_id,
					'mitarbeiter_uid' => $data->uid,
					'stunden' => is_numeric($data->stunden) ? $data->stunden : 0,
					'anmerkung' => isset($data->anmerkung) ? ($data->anmerkung) : null,
					'studienjahr_kurzbz' =>  $data->studienjahr,
					'insertamum' => date('Y-m-d H:i:s'),
					'insertvon' => $this->_uid
				));

				$this->terminateWithSuccess(getData($result));
			}
		}
	}


	public function updateFaktor()
	{
		$data = $this->getPostJson();

		if ((property_exists($data, 'lv_id')) &&
			(property_exists($data, 'faktor')) &&
			(property_exists($data, 'semester')))
		{
			$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz, start');
			$this->_ci->StudiensemesterModel->addOrder('start', 'DESC');
			$this->_ci->StudiensemesterModel->addLimit(1);
			$studiensemester = $this->_ci->StudiensemesterModel->loadWhere("studiensemester_kurzbz IN ('". implode("', '", $data->semester) . "')");
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

			$exists = $this->_ci->LehrveranstaltungFaktorModel->loadWhere(array('studiensemester_kurzbz_von' => $studiensemester, 'lehrveranstaltung_id' => $data->lv_id));

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
						ORDER BY vonstsem.start DESC
						LIMIT 1";

				$exists = $dbModel->execReadOnlyQuery($qry, array($data->lv_id, $studiensemester));

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
					'faktor' => $data->faktor,
					'studiensemester_kurzbz_von' => $studiensemester,
					'insertamum' => date('Y-m-d H:i:s'),
					'insertvon' => $this->_uid
				));

				$this->terminateWithSuccess($insertResult);
			}
		}
	}
	public function saveMitarbeiter()
	{
		$mitarbeiterCategory = $this->getPostJson();

		$kategorie = $this->_ci->PEPModel->load(array('kategorie_id' => $mitarbeiterCategory->kategorie));

		if (is_null($mitarbeiterCategory->kategorie_mitarbeiter_id))
		{
			$result = $this->_ci->PEPKategorieMitarbeiterModel->insert(array(
				'kategorie_id' =>  $mitarbeiterCategory->kategorie,
				'mitarbeiter_uid' => $mitarbeiterCategory->mitarbeiter_uid,
				'studienjahr_kurzbz' => $mitarbeiterCategory->studienjahr,
				'stunden' => is_null($mitarbeiterCategory->stunden) ? 0 : $mitarbeiterCategory->stunden,
				'anmerkung' => $mitarbeiterCategory->anmerkung,
				'insertamum' => date('Y-m-d H:i:s'),
				'insertvon' => $this->_uid
			));

			if (isError($result))
				$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);

			$mitarbeiterCategory->kategorie_mitarbeiter_id = getData($result);
			$this->terminateWithSuccess($mitarbeiterCategory->kategorie_mitarbeiter_id);
		}
		else
		{
			if (property_exists($mitarbeiterCategory, 'delete') && $mitarbeiterCategory->delete)
			{
				$stunden_delete = $this->_ci->PEPKategorieMitarbeiterModel->delete(array('kategorie_mitarbeiter_id' => $mitarbeiterCategory->kategorie_mitarbeiter_id));

				if (isError($stunden_delete))
					$this->terminateWithError($stunden_delete, self::ERROR_TYPE_GENERAL);

				$categoryData = $this->_ci->PEPModel->getCategoryData([$mitarbeiterCategory->mitarbeiter_uid],  $mitarbeiterCategory->kategorie, $mitarbeiterCategory->studienjahr);

				$this->terminateWithSuccess(getData($categoryData)[0]);
			}
			else
			{
				$stunden_exists = $this->_ci->PEPKategorieMitarbeiterModel->load(array($mitarbeiterCategory->kategorie_mitarbeiter_id));

				if (!hasData($stunden_exists) || isError($stunden_exists))
					$this->terminateWithError($stunden_exists, self::ERROR_TYPE_GENERAL);

				$stunden_exists = getData($stunden_exists)[0];

				if ($stunden_exists->stunden !== number_format($mitarbeiterCategory->stunden, 2)
					|| ($stunden_exists->anmerkung !== $mitarbeiterCategory->anmerkung)
				)
				{
					$result = $this->_ci->PEPKategorieMitarbeiterModel->update(
						array($stunden_exists->kategorie_mitarbeiter_id),
						array(
							'stunden' => is_null($mitarbeiterCategory->stunden) ? 0 : $mitarbeiterCategory->stunden,
							'anmerkung' => $mitarbeiterCategory->anmerkung,
							'updatevon' => $this->_uid,
							'updateamum' => date('Y-m-d H:i:s'),
						)
					);
					if (isError($result))
						$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);
				}
				$this->terminateWithSuccess($mitarbeiterCategory->kategorie_mitarbeiter_id);
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
			if (!isEmptyArray($data->lehreinheit_ids))
			{
				foreach ($data->lehreinheit_ids as $lehreinheit)
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

					$result = $this->_ci->LehreinheitmitarbeiterModel->update(
						array(
							'lehreinheit_id' => $lehreinheit->lehreinheit_id,
							'mitarbeiter_uid' => $lehreinheit->uid
						),
						array(
							'mitarbeiter_uid' => $data->lektor->uid,
							'updateamum' => date('Y-m-d H:i:s'),
							'updatevon' => $this->_uid,
							'stundensatz' => $stundensatzResult
						)
					);

					if (isError($result))
						$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);

					$successUpdated[] = ['id' => $lehreinheit->row_index, 'le_stundensatz' => $stundensatzResult];
				}
			}
			else
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

				$result = $this->_ci->LehreinheitmitarbeiterModel->update(
					array(
						'lehreinheit_id' => $data->lehreinheit_id,
						'mitarbeiter_uid' => $data->oldlektor
					),
					array(
						'mitarbeiter_uid' => $data->lektor->uid,
						'anmerkung' => $data->anmerkung,
						'updateamum' => date('Y-m-d H:i:s'),
						'updatevon' => $this->_uid,
						'stundensatz' => $stundensatzResult
					)
				);
			}

			if (isError($result))
				$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);

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
			$returnData->le_stundensatz = isset($stundensatzResult) ? $stundensatzResult : '-';
			$returnData->akt_stunden = isset($dv->akt_stunden) ? $dv->akt_stunden : '-';

			$returnData->akt_stundensaetze_lehre = isset($dv->akt_stundensaetze_lehre) ? $dv->akt_stundensaetze_lehre : '-';
			$returnData->bezeichnung = isset($dv->bezeichnung) ? $dv->bezeichnung : '-';
			$returnData->updateamum = date('d.m.Y H:i:s');
			$returnData->anmerkung = $data->anmerkung;
			$returnData->lehreinheiten_ids = $successUpdated;

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


	public function getProjekte()
	{
		$projects = $this->_ci->config->item('project_id_list');
		$projects = implode('\', \'', $projects);
		$dbModel = new DB_Model();
		$qry = "
			SELECT DISTINCT(project_id), start_date, end_date, name
				FROM sync.tbl_sap_projects_timesheets
				WHERE project_task_id IS NULL
				AND project_id ilike any (array['$projects'])
				ORDER BY project_id";

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
		$lehrveranstaltung_id = $this->_ci->input->get('lehrveranstaltung_id');

		if (isEmptyString($lehreinheit_id))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$this->_ci->LehreinheitModel->addJoin('lehre.tbl_lehreinheitmitarbeiter', 'lehreinheit_id', 'LEFT');
		$lehreinheit = $this->_ci->LehreinheitModel->loadWhere(array('lehreinheit_id' => $lehreinheit_id, 'mitarbeiter_uid' => $mitarbeiter_uid));

		if (!hasData($lehreinheit))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$this->terminateWithSuccess(hasData($lehreinheit) ? getData($lehreinheit)[0] : []);
	}

	private function getStudiensemesterQuery()
	{
		return "semester_datum AS (
				SELECT MIN(start) as start,
						MAX(ende) as ende
				FROM public.tbl_studiensemester
				WHERE public.tbl_studiensemester.studiensemester_kurzbz IN ?
			)";
	}

	private function getRelevanteDVsByPerson()
	{
		return "relevante_dvs AS (
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
				)";
	}
	public function getLehreinheiten()
	{
		$lehrveranstaltung_id = $this->_ci->input->get('lehrveranstaltung_id');
		$studiensemester = $this->_ci->input->get('studiensemester');

		if (isEmptyString($lehrveranstaltung_id) || isEmptyArray($studiensemester))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

		$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz, start');
		$this->_ci->StudiensemesterModel->addOrder('start', 'DESC');
		$this->_ci->StudiensemesterModel->addLimit(1);
		$updateStudiensemester = $this->_ci->StudiensemesterModel->loadWhere("studiensemester_kurzbz IN ('". implode("', '", $studiensemester) . "')");
		if (isError($updateStudiensemester) || !hasData($updateStudiensemester))
			$this->terminateWithError($updateStudiensemester, self::ERROR_TYPE_GENERAL);

		$updateStudiensemester = getData($updateStudiensemester)[0];
		$updateStudiensemester = $updateStudiensemester->studiensemester_kurzbz;

		$dbModel = new DB_Model();

		$qry = "
			SELECT tbl_lehrveranstaltung.bezeichnung,
					SUM(tbl_lehreinheitmitarbeiter.semesterstunden) OVER () AS lvstunden,
					tbl_lehreinheitmitarbeiter.semesterstunden,
					? as updateStudiensemester,
					(SELECT faktor
						FROM lehre.tbl_lehrveranstaltung_faktor
								 LEFT JOIN public.tbl_studiensemester vonstsem
										   ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_von = vonstsem.studiensemester_kurzbz
								 LEFT JOIN public.tbl_studiensemester bisstem
										   ON tbl_lehrveranstaltung_faktor.studiensemester_kurzbz_bis = bisstem.studiensemester_kurzbz
						WHERE tbl_lehrveranstaltung_faktor.lehrveranstaltung_id = ?
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
						LIMIT 1) as faktor,
					studiensemester_kurzbz,
					tbl_lehreinheit.lehreinheit_id,
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
				LEFT JOIN lehre.tbl_lehrveranstaltung_faktor ON tbl_lehreinheit.lehrveranstaltung_id = tbl_lehrveranstaltung_faktor.lehrveranstaltung_id
				WHERE tbl_lehrveranstaltung.lehrveranstaltung_id = ?
					AND studiensemester_kurzbz IN ?
			GROUP BY tbl_lehreinheit.lehreinheit_id, tbl_lehrveranstaltung.bezeichnung, las,  tbl_lehreinheitmitarbeiter.semesterstunden, tbl_mitarbeiter.kurzbz, vorname,
					nachname, tbl_lehrveranstaltung.lehrveranstaltung_id, tbl_mitarbeiter.mitarbeiter_uid
		";

		$result = $dbModel->execReadOnlyQuery($qry, array($updateStudiensemester, $lehrveranstaltung_id, $studiensemester, $studiensemester, $lehrveranstaltung_id, $studiensemester));
		$this->terminateWithSuccess(hasData($result) ? getData($result) : []);
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
		$mitarbeiter_uids = array('');
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

