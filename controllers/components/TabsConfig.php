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

		$this->_ci->loadPhrases(
			array(
				'global',
				'ui',
				'person'
			)
		);

		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->config('extensions/FHC-Core-PEP/pep');
	}

	public function get()
	{
		$tabs = array();
		$this->_getAdministration($tabs);

		if ($this->_ci->config->item('content_id'))
		{
			$tabs['legende'] = array (
				'title' =>  'Legende',
				'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Legende.js',
				'config' => ['content_url' => APP_ROOT.'cms/content.php?content_id=' . $this->_ci->config->item('content_id')]
			);
		}

		$tabs['start'] = array (
			'title' =>  'Start',
			'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Start.js',
			'config' => ['studienjahr' => true, 'dropdowns' => true, 'reload' => true]
		);

		$tabs['lehre'] = array (
			'title' =>  'Lehre',
			'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Lehre.js',
			'config' => ['studiensemester' => true, 'dropdowns' => true, 'reload' => true]
		);

		if ($this->_ci->config->item('enable_projects') === true)
			$this->_getProjects($tabs);

		$this->_getCategories($tabs);

		if ($this->_ci->config->item('enable_compare_tab') === true)
		{
			$tabs['vergleich'] = array (
				'title' =>  'Vergleichen',
				'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Vergleichen.js',
				'config' => ['studiensemester' => true, 'dropdowns' => true, 'reload' => false]
			);
		}

		$this->terminateWithSuccess($tabs);
	}

	private function _getAdministration(&$tabs)
	{
		$this->_ci->load->library('PermissionLib');

		if (!$this->_ci->permissionlib->isBerechtigt('admin'))
			return;

		$tabs['administration'] = array (
			'title' =>  'Administration',
			'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Administration.js',
			'config' => ['dropdowns' => false, 'reload' => false]
		);
	}

	private function _getProjects(&$tabs)
	{
		$this->_ci->load->library('PermissionLib');

		if (!$this->_ci->permissionlib->isBerechtigt(self::DEFAULT_PERMISSION))
			return;

		$tabs['syncprojects'] = array (
			'title' =>  'Projekte',
			'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Project.js',
			'config' => ['studienjahr' => true, 'dropdowns' => true, 'reload' => true]
		);

	}
	private function _getCategories(&$tabs)
	{

		$language = $this->_getLanguageIndex();
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
					'dropdowns' => true,
					'reload' => true
				];

				$tab = [
					'title' => $category->tabname,
					'component' => APP_ROOT . 'public/extensions/FHC-Core-PEP/js/components/Category.js',
					'config' => $config
				];

				$tabs[$category->bezeichnung] = $tab;
			}
		}
	}

	private function _getLanguageIndex()
	{
		$this->_ci->load->model('system/Sprache_model', 'SpracheModel');
		$this->_ci->SpracheModel->addSelect('index');
		$result = $this->_ci->SpracheModel->loadWhere(array('sprache' => getUserLanguage()));

		return hasData($result) ? getData($result)[0]->index : 1;
	}
}
