<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Tags extends Tag_Controller
{
	private $_ci;
	private $_uid;

	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';

	public function __construct()
	{
		parent::__construct([
			'getTag' => self::BERECHTIGUNG_KURZBZ,
			'getTags' => self::BERECHTIGUNG_KURZBZ,
			'addTag' => self::BERECHTIGUNG_KURZBZ,
			'updateTag' => self::BERECHTIGUNG_KURZBZ,
			'doneTag' => self::BERECHTIGUNG_KURZBZ,
			'deleteTag' => self::BERECHTIGUNG_KURZBZ,
			'updateLehre' => self::BERECHTIGUNG_KURZBZ,
			'doneLehre' => self::BERECHTIGUNG_KURZBZ,
			'deleteLehre' => self::BERECHTIGUNG_KURZBZ,
			'addMitarbeiterTag' => self::BERECHTIGUNG_KURZBZ,
			'deleteMitarbeiterTag' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();
		$this->load->model('extensions/FHC-Core-PEP/PEP_Notiz_Mitarbeiter_model', 'PEPNotizMitarbeiterModel');
	}

	public function addMitarbeiterTag()
	{
		$postData = $this->getPostJson();

		$return = array();
		foreach ($postData->values as $value)
		{
			$insertResult = parent::addTag(false);

			$insertZuordnung = $this->PEPNotizMitarbeiterModel->insert(array(
				'notiz_id' => $insertResult,
				'mitarbeiter_uid' => $value
			));

			if (isError($insertZuordnung))
				$this->terminateWithError('Error occurred', self::ERROR_TYPE_GENERAL);
			$return[] = ['mitarbeiter_uid' => $value, 'id' => $insertResult];
		}
		$this->terminateWithSuccess($return);
	}

	public function deleteMitarbeiterTag()
	{
		$postData = $this->getPostJson();

		$deleteZuordnung = $this->PEPNotizMitarbeiterModel->delete(array(
			'notiz_id' => $postData->id
		));

		if (isSuccess($deleteZuordnung))
		{
			parent::deleteTag(false);
		}
	}
}