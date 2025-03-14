<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class PEP extends Auth_Controller
{
	private $_ci;

	const BERECHTIGUNG_KURZBZ = 'extension/pep';
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
			'index' => 'extension/pep:r',
			)
		);
		$this->_ci = &get_instance();
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Organisationseinheit_model', 'OrganisationseinheitModel');
		$this->_ci->load->library('VariableLib', array('uid' => getAuthUID()));
	}
	
	// -----------------------------------------------------------------------------------------------------------------
	// Public methods
	
	public function index()
	{
		$this->_ci->StudienjahrModel->addOrder('studienjahr_kurzbz', 'DESC');
		$studienjahre = getData($this->_ci->StudienjahrModel->load());
		$this->_ci->StudiensemesterModel->addOrder("start", "DESC");
		$studiensemestern = getData($this->_ci->StudiensemesterModel->load());

		$oeKurzbz = $this->_ci->permissionlib->getOE_isEntitledFor(self::BERECHTIGUNG_KURZBZ);
		$this->_ci->OrganisationseinheitModel->addJoin('tbl_studiengang', 'oe_kurzbz', 'LEFT');

		$this->_ci->OrganisationseinheitModel->addSelect('public.tbl_organisationseinheit.organisationseinheittyp_kurzbz,
															public.tbl_organisationseinheit.bezeichnung,
															public.tbl_organisationseinheit.oe_kurzbz,
															public.tbl_organisationseinheit.aktiv,
															COALESCE(public.tbl_studiengang.bezeichnung, \'\') as stgbezeichnung');
		$this->_ci->OrganisationseinheitModel->addOrder('tbl_organisationseinheit.aktiv', 'DESC');
		$this->_ci->OrganisationseinheitModel->addOrder('tbl_organisationseinheit.organisationseinheittyp_kurzbz');
		$organisationen = getData($this->_ci->OrganisationseinheitModel->loadWhere("tbl_organisationseinheit.oe_kurzbz IN ('". implode("', '", $oeKurzbz) . "')"));

		$data = [
			'studienjahre' => $studienjahre,
			'studiensemestern' => $studiensemestern,
			'organisationen' => $organisationen,
			'var_studienjahr' => $this->_ci->variablelib->getVar('pep_studienjahr'),
			'var_studiensemester' => explode(',', $this->_ci->variablelib->getVar('pep_studiensemester')),
			'var_organisation' => $this->_ci->variablelib->getVar('pep_abteilung')
		];
		$this->load->view('extensions/FHC-Core-PEP/pep.php',
			$data
		);
	}

}

