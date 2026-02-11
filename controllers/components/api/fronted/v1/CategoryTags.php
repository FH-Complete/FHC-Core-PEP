<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class CategoryTags extends Tag_Controller
{
	private $_ci;

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
		]);

		$this->_ci = &get_instance();
		$this->load->model('extensions/FHC-Core-PEP/PEP_Category_Notiz_model', 'PEPCategoryNotizModel');
		$this->load->helper('extensions/FHC-Core-PEP/hlp_employee_helper');
		$this->_ci->load->config('extensions/FHC-Core-PEP/pep');
	}

	public function getTag($readonly_tags = null)
	{
		parent::getTag($this->config->item('pep_tags'));
	}
	public function getTags($tags = null)
	{
		parent::getTags($this->config->item('pep_tags'));
	}
	public function updateTag($updatable_tags = null)
	{
		parent::updateTag($this->config->item('pep_tags'));
	}
	public function doneTag($updatable_tags = null)
	{
		parent::doneTag($this->config->item('pep_tags'));
	}

	public function addTag($withZuordnung = false, $updatable_tags = null)
	{
		$postData = $this->getPostJson();

		$return = array();
		foreach ($postData->values as $value)
		{
			$insertResult = parent::addTag(false);

			$insertZuordnung = $this->PEPCategoryNotizModel->insert(array(
				'notiz_id' => $insertResult,
				'kategorie_mitarbeiter_id' => $value
			));

			if (isError($insertZuordnung))
				$this->terminateWithError('Error occurred', self::ERROR_TYPE_GENERAL);
			$return[] = ['kategorie_mitarbeiter_id' => $value, 'id' => $insertResult];
		}
		$this->terminateWithSuccess($return);
	}

	public function deleteTag($withZuordnung = false, $updatable_tags = null)
	{
		$postData = $this->getPostJson();

		$deleteZuordnung = $this->PEPCategoryNotizModel->delete(array(
			'notiz_id' => $postData->id
		));

		if (isSuccess($deleteZuordnung))
		{
			parent::deleteTag(false);
		}
	}
}