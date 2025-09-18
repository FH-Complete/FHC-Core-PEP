<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class SelfOverviewTags extends Tag_Controller
{
	private $_ci;

	const BERECHTIGUNG_KURZBZ = 'extension/pep_selfoverview:rw';

	public function __construct()
	{
		parent::__construct([
			'getTag' => [self::BERECHTIGUNG_KURZBZ, 'extension/pep:r'],
		]);

		$this->_ci = &get_instance();
		$this->load->model('person/Notizzuordnung_model', 'NotizzuordnungModel');
		$this->load->model('extensions/FHC-Core-PEP/PEP_LV_Entwicklung_Notiz_model', 'PEPLVEntwicklungNotizModel');
		$this->_ci->load->library('PermissionLib');

	}

	public function getTag($readonly_tags = null)
	{
		$id = $this->input->get('id');
		$notiz = $this->NotizModel->load($id);

		if (!hasData($notiz) || isError($notiz))
			$this->terminateWithError('Error occurred', self::ERROR_TYPE_GENERAL);

		$notiz_data = getData($notiz)[0];

		if ($notiz_data->typ === 'hinweis_lehrende' && $notiz_data->erledigt === false)
		{

			if ($this->_ci->permissionlib->isBerechtigt('extension/pep:r'))
			{
				parent::getTag();
			}
			else
			{
				$this->_ci->NotizzuordnungModel->addSelect('lehre.tbl_lehreinheitmitarbeiter.mitarbeiter_uid');
				$this->_ci->NotizzuordnungModel->addJoin('lehre.tbl_lehreinheit', 'lehreinheit_id');
				$this->_ci->NotizzuordnungModel->addJoin('lehre.tbl_lehreinheitmitarbeiter', 'lehreinheit_id');
				$notiz_lehreinheit_zuordnung = $this->_ci->NotizzuordnungModel->loadWhere(array('notiz_id' => $notiz_data->notiz_id));

				$this->_ci->PEPLVEntwicklungNotizModel->addSelect('extension.tbl_pep_lv_entwicklung.mitarbeiter_uid');
				$this->_ci->PEPLVEntwicklungNotizModel->addJoin('extension.tbl_pep_lv_entwicklung', 'pep_lv_entwicklung_id');
				$notiz_lv_entwicklung_zuordnung = $this->_ci->PEPLVEntwicklungNotizModel->loadWhere(array('notiz_id' => $notiz_data->notiz_id));

				if (isError($notiz_lehreinheit_zuordnung) || isError($notiz_lv_entwicklung_zuordnung))
					$this->terminateWithError('Error occurred', self::ERROR_TYPE_GENERAL);

				$notiz_lehreinheit_zuordnung_result = array_column(hasData($notiz_lehreinheit_zuordnung) ? getData($notiz_lehreinheit_zuordnung) : [], 'mitarbeiter_uid');
				$notiz_lv_entwicklung_zuordnung_result = array_column(hasData($notiz_lv_entwicklung_zuordnung) ? getData($notiz_lv_entwicklung_zuordnung) : [], 'mitarbeiter_uid');

				if ((in_array(getAuthUID(), $notiz_lehreinheit_zuordnung_result)) || (in_array(getAuthUID(), $notiz_lv_entwicklung_zuordnung_result)))
				{
					parent::getTag();
				}
			}
		}
	}
}