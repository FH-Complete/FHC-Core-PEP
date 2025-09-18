<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Admin extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'admin:rw';

	public function __construct()
	{
		parent::__construct([
			'getStudienjahre' => self::BERECHTIGUNG_KURZBZ,
			'getOrganisationen' => self::BERECHTIGUNG_KURZBZ,
			'vorruecken' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('organisation/Organisationseinheit_model', 'OrganisationseinheitModel');

		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_Kategorie_Mitarbeiter_model', 'PEPKategorieMitarbeiterModel');

		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_ci->load->library('PhrasesLib');

		$this->load->helper('extensions/FHC-Core-PEP/hlp_employee_helper');

		$this->loadPhrases(
			array(
				'ui'
			)
		);
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

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

		$aktStudienjahrUids = getMitarbeiterUids($data->organisation, $fromStudiensemester, $data->recursive === "true");
		$newStudienjahrUids = getMitarbeiterUids($data->organisation, $toStudiensemester, $data->recursive === "true");
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

	public function getOrganisationen()
	{
		$oeKurzbz = $this->_ci->permissionlib->getOE_isEntitledFor(self::BERECHTIGUNG_KURZBZ);
		$this->_ci->OrganisationseinheitModel->addSelect('organisationseinheittyp_kurzbz, bezeichnung, oe_kurzbz, aktiv');
		$this->_ci->OrganisationseinheitModel->addOrder('organisationseinheittyp_kurzbz');
		$organisationen = $this->_ci->OrganisationseinheitModel->loadWhere("oe_kurzbz IN ('". implode("', '", $oeKurzbz) . "')");
		$this->terminateWithSuccess(getData($organisationen));
	}

	public function getStudienjahre()
	{
		$this->_ci->StudienjahrModel->addOrder('studienjahr_kurzbz', 'DESC');
		$studienjahre = getData($this->_ci->StudienjahrModel->load());
		$this->terminateWithSuccess($studienjahre);
	}

}

