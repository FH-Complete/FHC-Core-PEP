<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class LVEntwicklungTags extends Tag_Controller
{
	private $_ci;

	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';

	public function __construct()
	{
		parent::__construct([
			'getTag' => self::BERECHTIGUNG_KURZBZ,
			'getTags' => self::BERECHTIGUNG_KURZBZ,
			'addLVEntwicklungTag' => self::BERECHTIGUNG_KURZBZ,
			'updateTag' => self::BERECHTIGUNG_KURZBZ,
			'doneTag' => self::BERECHTIGUNG_KURZBZ,
			'deleteTag' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();
		$this->load->model('extensions/FHC-Core-PEP/PEP_LV_Entwicklung_Notiz_model', 'PEPLVEntwicklungNotizModel');
		$this->load->helper('extensions/FHC-Core-PEP/hlp_employee_helper');
	}

	public function addLVEntwicklungTag()
	{
		$postData = $this->getPostJson();

		$return = array();
		foreach ($postData->values as $value)
		{
			$insertResult = parent::addTag(false);

			$insertZuordnung = $this->PEPLVEntwicklungNotizModel->insert(array(
				'notiz_id' => $insertResult,
				'pep_lv_entwicklung_id' => $value
			));

			if (isError($insertZuordnung))
				$this->terminateWithError('Error occurred', self::ERROR_TYPE_GENERAL);
			$return[] = ['pep_lv_entwicklung_id' => $value, 'id' => $insertResult];
		}
		$this->terminateWithSuccess($return);
	}

	public function deleteMitarbeiterTag()
	{
		$postData = $this->getPostJson();

		$deleteZuordnung = $this->PEPLVEntwicklungNotizModel->delete(array(
			'notiz_id' => $postData->id
		));

		if (isSuccess($deleteZuordnung))
		{
			parent::deleteTag(false);
		}
	}
}