<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Project extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';

	public function __construct()
	{
		parent::__construct([
			'getProjekte' => self::BERECHTIGUNG_KURZBZ,
			'getLektoren' => self::BERECHTIGUNG_KURZBZ,
			'getProjects' => self::BERECHTIGUNG_KURZBZ,
			'addProjectStunden' => self::BERECHTIGUNG_KURZBZ,
			'updateProjectStunden' => self::BERECHTIGUNG_KURZBZ,
			'deleteProjectStunden' => self::BERECHTIGUNG_KURZBZ,
			'getStartAndEnd' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_Projects_Employees_model', 'PEPProjectsEmployeesModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_Projekt_Notiz_model', 'PEPProjektNotizModel');

		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_ci->load->library('PhrasesLib');

		$this->loadPhrases(
			array(
				'ui'
			)
		);
		$this->_ci->load->config('extensions/FHC-Core-PEP/pep');
		$this->load->helper('extensions/FHC-Core-PEP/hlp_employee_helper');
		$this->_setAuthUID();
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

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
		$mitarbeiter_uids = getMitarbeiterUids($org, $studiensemestern , $recursive === "true");

		$projectsData = $this->_ci->PEPModel->getProjectData($mitarbeiter_uids, $studienjahr, $org);
		$this->terminateWithSuccess(hasData($projectsData) ? getData($projectsData) : array());
	}

	public function addProjectStunden()
	{

		$data = $this->getPostJson();

		if ((property_exists($data, 'lektor')) &&
			(property_exists($data, 'project')) &&
			(property_exists($data, 'studienjahr')) &&
			(property_exists($data, 'stunden')))
		{

			if (isEmptyString($data->lektor) || isEmptyString($data->project) || isEmptyString($data->studienjahr) || isEmptyString((string)$data->stunden))
				$this->terminateWithError($this->p->t('ui', 'felderFehlen'), self::ERROR_TYPE_GENERAL);

			$studienjahr = $data->studienjahr;
			$studiensemester = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
			if (!hasData($studiensemester))
				$this->terminateWithSuccess(false);

			$studiensemester = array_column(getData($studiensemester), 'studiensemester_kurzbz');
			$mitarbeiteruids = getMitarbeiterUids($data->org, $studiensemester, true);

			if (!in_array($data->lektor, $mitarbeiteruids) &&
				!hasData($this->_ci->PEPModel->isProjectAssignedToOrganization($data->org, $data->project)))
			{
				$this->terminateWithError($this->p->t('ui', 'maprojohneoe'), self::ERROR_TYPE_GENERAL);
			}

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
				'stunden' => number_format($data->stunden, 2, '.', ''),
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
		else
			$this->terminateWithError($this->p->t('ui', 'felderFehlen'), self::ERROR_TYPE_GENERAL);
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
				$this->_ci->PEPProjektNotizModel->delete(array('pep_projects_employees_id' => $data->id));
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
							'stunden' => is_numeric($data->stunden) ? number_format($data->stunden, 2, '.', '') : 0,
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
					'stunden' => is_numeric($data->stunden) ? number_format($data->stunden, 2, '.', '') : 0,
					'anmerkung' => isset($data->anmerkung) ? ($data->anmerkung) : null,
					'studienjahr_kurzbz' =>  $data->studienjahr,
					'insertamum' => date('Y-m-d H:i:s'),
					'insertvon' => $this->_uid
				));

				$this->terminateWithSuccess(getData($result));
			}
		}
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
				LEFT JOIN sync.tbl_sap_projects_status_intern ON NULLIF(tbl_sap_projects_timesheets.custom_fields->>'Status_KUT', '')::numeric = tbl_sap_projects_status_intern.status
				WHERE project_task_id IS NULL
				AND project_id ilike any (array['$projects'])
				AND deleted is false
				AND (tbl_sap_projects_status_intern.status NOT IN ? OR tbl_sap_projects_status_intern.status IS NULL)
				ORDER BY project_id";

		$result = $dbModel->execReadOnlyQuery($qry, array($this->_ci->config->item('excluded_project_status')));
		$this->terminateWithSuccess(hasData($result) ? getData($result) : []);
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

	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}
}

