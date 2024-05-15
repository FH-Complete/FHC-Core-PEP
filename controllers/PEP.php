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
		$db_Model = new DB_Model();
		$organisationen = getData($db_Model->execReadOnlyQuery("SELECT organisationseinheittyp_kurzbz, bezeichnung, oe_kurzbz
											FROM public.tbl_organisationseinheit
											WHERE oe_kurzbz IN ('". implode("','", $oeKurzbz) . "')
											ORDER BY organisationseinheittyp_kurzbz
											"));

		$data = [
			'studienjahre' => $studienjahre,
			'studiensemestern' => $studiensemestern,
			'organisationen' => $organisationen,
		];
		$this->load->view('extensions/FHC-Core-PEP/pep.php',
			$data
		);
	}

}

