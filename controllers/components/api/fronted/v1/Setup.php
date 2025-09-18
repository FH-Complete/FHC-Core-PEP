<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Setup extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';

	public function __construct()
	{
		parent::__construct([
			'setVariables' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();
		$this->_ci->load->model('system/Variable_model', 'VariableModel');

		$this->_ci->load->library('AuthLib');
		$this->_setAuthUID();
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

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

	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}
}

