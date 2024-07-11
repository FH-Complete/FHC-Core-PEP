<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class TabsConfig extends FHCAPI_Controller
{

	const DEFAULT_PERMISSION = 'extension/pep';

	private $_ci;

	public function __construct() {

		parent::__construct(
			array(
				'get' => self::DEFAULT_PERMISSION . ':r'
			)
		);
		$this->_ci = &get_instance();

		// Loads phrases system
		$this->_ci->loadPhrases(
			array(
				'global',
				'ui',
				'person'
			)
		);

		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
	}

	public function get()
	{
		$language = $this->_getLanguageIndex();

		$tabs = array(
			'start' => array(
				'title' =>  'Start',
				'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Start.js',
				'config' => ['studiensemester' => true]
			),
			'lehre' => array(
				'title' =>  'Lehre',
				'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Lehre.js',
				'config' => ['studiensemester' => true]
			)
		);

		$this->_ci->PEPModel->addOrder(
			'sort'
		);

		$this->_ci->PEPModel->addSelect(
			'kategorie_id,
			bezeichnung,
			bezeichnung_mehrsprachig[('.$language.')] as tabname
			'
		);

		$result = $this->_ci->PEPModel->load();

		if (hasData($result))
		{
			$categories = getData($result);

			foreach ($categories as $category)
			{
				if (isset($tabs[$category->tabname]))
					continue;
				$config = [
					'category_id' => $category->kategorie_id,
					'studienjahr' => true,
				];

				$tab = [
					'title' => $category->tabname,
					'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Category.js',
					'config' => $config
				];

				$tabs[$category->bezeichnung] = $tab;
			}
		}

		$this->terminateWithSuccess($tabs);

	}

	private function _getLanguageIndex()
	{
		$this->_ci->load->model('system/Sprache_model', 'SpracheModel');
		$this->_ci->SpracheModel->addSelect('index');
		$result = $this->_ci->SpracheModel->loadWhere(array('sprache' => getUserLanguage()));

		return hasData($result) ? getData($result)[0]->index : 1;
	}
}