<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Category extends FHCAPI_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'extension/pep:rw';

	public function __construct()
	{
		parent::__construct([
			'getCategory' => self::BERECHTIGUNG_KURZBZ,
			'getOrgForCategories' => self::BERECHTIGUNG_KURZBZ,
			'stundenzuruecksetzen' => self::BERECHTIGUNG_KURZBZ,
			'saveMitarbeiter' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Organisationseinheit_model', 'OrganisationseinheitModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_Kategorie_Mitarbeiter_model', 'PEPKategorieMitarbeiterModel');
		$this->_ci->load->model('extensions/FHC-Core-PEP/PEP_Kategorie_Notiz_model', 'PEPKategorieNotizModel');

		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_ci->load->library('PhrasesLib');

		$this->load->helper('extensions/FHC-Core-PEP/hlp_employee_helper');

		$this->loadPhrases(
			array(
				'ui'
			)
		);
		$this->_setAuthUID();
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	public function getCategory()
	{
		$category = $this->_ci->input->get('category_id');
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		$recursive = $this->_ci->input->get('recursive');

		$this->_ci->StudiensemesterModel->addSelect('studiensemester_kurzbz');
		$studiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($studiensemestern))
			$this->terminateWithError($this->p->t('ui', 'fehlerBeimLesen'), self::ERROR_TYPE_GENERAL);

		$studiensemestern = array_column(getData($studiensemestern), 'studiensemester_kurzbz');

		$mitarbeiter_uids = getMitarbeiterUids($org, $studiensemestern, $recursive === "true");

		$categoryData = $this->_ci->PEPModel->getCategoryData($mitarbeiter_uids, $category, $studienjahr);

		$dienstverhaeltnisse = $this->_ci->PEPModel->_getDVs($mitarbeiter_uids, $studienjahr);

		if (!hasData($dienstverhaeltnisse))
			$this->terminateWithSuccess([]);

		$mitarbeiterInfos = [];

		foreach (getData($dienstverhaeltnisse) as $mitarbeiter)
		{
			$mitarbeiterInfos[$mitarbeiter->mitarbeiter_uid] = (object) [
				'zrm_vertraege' => isset($mitarbeiter->zrm_vertraege) ? $mitarbeiter->zrm_vertraege : '-',
				'zrm_wochenstunden' => isset($mitarbeiter->zrm_wochenstunden) ? $mitarbeiter->zrm_wochenstunden : '-',
				'zrm_jahresstunden' => isset($mitarbeiter->zrm_jahresstunden) ? $mitarbeiter->zrm_jahresstunden : '-',
				'zrm_stundensatz_lehre' => isset($mitarbeiter->zrm_stundensatz_lehre) ? $mitarbeiter->zrm_stundensatz_lehre : '-',

				'akt_bezeichnung' => isset($mitarbeiter->akt_bezeichnung) ? $mitarbeiter->akt_bezeichnung : '-',
				'akt_orgbezeichnung' => isset($mitarbeiter->akt_orgbezeichnung) ? $mitarbeiter->akt_orgbezeichnung : '-',
				'akt_parentbezeichnung' => isset($mitarbeiter->akt_parentbezeichnung) ? $mitarbeiter->akt_parentbezeichnung : '-',
				'akt_stunden' => isset($mitarbeiter->akt_stunden) ? $mitarbeiter->akt_stunden : '-',
				'akt_stundensaetze_lehre' => isset($mitarbeiter->akt_stundensaetze_lehre) ? $mitarbeiter->akt_stundensaetze_lehre : '-'
			];
		}


		$mitarbeiterDataArray = [];
		if (hasData($categoryData))
		{
			foreach (getData($categoryData) as $empCategoryData)
			{
				$mitarbeiterData = clone $empCategoryData;
				$info = isset($mitarbeiterInfos[$empCategoryData->mitarbeiter_uid]) ? $mitarbeiterInfos[$empCategoryData->mitarbeiter_uid] : new stdClass();

				$mitarbeiterData->zrm_vertraege = $info->zrm_vertraege;
				$mitarbeiterData->zrm_wochenstunden = $info->zrm_wochenstunden;
				$mitarbeiterData->zrm_jahresstunden = $info->zrm_jahresstunden;
				$mitarbeiterData->zrm_stundensatz_lehre = $info->zrm_stundensatz_lehre;

				$mitarbeiterData->akt_bezeichnung = $info->akt_bezeichnung;
				$mitarbeiterData->akt_orgbezeichnung = $info->akt_orgbezeichnung;
				$mitarbeiterData->akt_parentbezeichnung = $info->akt_parentbezeichnung;
				$mitarbeiterData->akt_stunden = $info->akt_stunden;
				$mitarbeiterData->akt_stundensaetze_lehre = $info->akt_stundensaetze_lehre;

				$mitarbeiterDataArray[] = $mitarbeiterData;
			}
		}

		$this->terminateWithSuccess($mitarbeiterDataArray);
	}

	public function getOrgForCategories()
	{
		$this->_ci->OrganisationseinheitModel->addJoin('tbl_studiengang', 'oe_kurzbz', 'LEFT');

		$this->_ci->OrganisationseinheitModel->addSelect('public.tbl_organisationseinheit.organisationseinheittyp_kurzbz, 
															public.tbl_organisationseinheit.bezeichnung,
															public.tbl_organisationseinheit.oe_kurzbz,
															public.tbl_organisationseinheit.aktiv,
															COALESCE(public.tbl_studiengang.bezeichnung, \'\') as stgbezeichnung
															');
		$this->_ci->OrganisationseinheitModel->addOrder('public.tbl_organisationseinheit.aktiv', 'DESC');
		$this->_ci->OrganisationseinheitModel->addOrder('organisationseinheittyp_kurzbz');
		$organisationen = $this->_ci->OrganisationseinheitModel->load();
		$this->terminateWithSuccess(getData($organisationen));
	}

	public function stundenzuruecksetzen()
	{
		$data = $this->getPostJson();

		$studienjahr = $data->studienjahr;
		$category_id = $data->category_id;
		$org = $data->org;

		$recursive = $data->recursive;

		$aktStudiensemestern = $this->_ci->StudiensemesterModel->loadWhere(array('studienjahr_kurzbz' => $studienjahr));
		if (!hasData($aktStudiensemestern))
			$this->terminateWithSuccess(false);

		$aktStudiensemestern = array_column(getData($aktStudiensemestern), 'studiensemester_kurzbz');


		$aktStudienjahrUids = getMitarbeiterUids($org, $aktStudiensemestern, $recursive === "true");

		$this->_ci->PEPKategorieMitarbeiterModel->addSelect('kategorie_mitarbeiter_id');
		$neededDelete = $this->_ci->PEPKategorieMitarbeiterModel->loadWhere('
			mitarbeiter_uid IN ('. implode(',', $this->_ci->db->escape($aktStudienjahrUids)) .')
			AND studienjahr_kurzbz = '. $this->_ci->db->escape($studienjahr).'
			AND kategorie_id = '. $this->_ci->db->escape($category_id) .'
		');

		if (hasData($neededDelete))
		{
			$neededDeleteIds = array_column(getData($neededDelete), 'kategorie_mitarbeiter_id');

			foreach ($neededDeleteIds as $id)
			{
				$this->_ci->PEPKategorieMitarbeiterModel->delete(array($id));
			}
		}
		$this->terminateWithSuccess();
	}

	public function saveMitarbeiter()
	{
		$mitarbeiterCategory = $this->getPostJson();

		if (isset($mitarbeiterCategory->kategorie))
		{
			$category = $this->_ci->PEPModel->loadWhere(array('kategorie_id' => $mitarbeiterCategory->kategorie));

			if (isError($category))
				$this->terminateWithError($category, self::ERROR_TYPE_GENERAL);

			if (!hasData($category))
				$this->terminateWithError($this->p->t('ui', 'fehlerBeimSpeichern'), self::ERROR_TYPE_GENERAL);

			$category = getData($category)[0];
			if ($category->aktiv === false)
				$this->terminateWithError($this->p->t('ui', 'readonlycategory'), self::ERROR_TYPE_GENERAL);
		}
		if (is_null($mitarbeiterCategory->kategorie_mitarbeiter_id))
		{
			$result = $this->_ci->PEPKategorieMitarbeiterModel->insert(array(
				'kategorie_id' =>  $mitarbeiterCategory->kategorie,
				'mitarbeiter_uid' => $mitarbeiterCategory->mitarbeiter_uid,
				'studienjahr_kurzbz' => $mitarbeiterCategory->studienjahr,
				'stunden' => is_null($mitarbeiterCategory->stunden) ? 0 : number_format($mitarbeiterCategory->stunden, 2, '.', ''),
				'oe_kurzbz' => isEmptyString($mitarbeiterCategory->category_oe_kurzbz) ? null : $mitarbeiterCategory->category_oe_kurzbz,
				'anmerkung' => $mitarbeiterCategory->anmerkung,
				'insertamum' => date('Y-m-d H:i:s'),
				'insertvon' => $this->_uid
			));

			if (isError($result))
				$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);

			$mitarbeiterCategory->kategorie_mitarbeiter_id = getData($result);
			$this->terminateWithSuccess($mitarbeiterCategory->kategorie_mitarbeiter_id);
		}
		else
		{
			if (property_exists($mitarbeiterCategory, 'delete') && $mitarbeiterCategory->delete)
			{
				$this->_ci->PEPKategorieNotizModel->delete(array('kategorie_mitarbeiter_id' => $mitarbeiterCategory->kategorie_mitarbeiter_id));
				$stunden_delete = $this->_ci->PEPKategorieMitarbeiterModel->delete(array('kategorie_mitarbeiter_id' => $mitarbeiterCategory->kategorie_mitarbeiter_id));

				if (isError($stunden_delete))
					$this->terminateWithError($stunden_delete, self::ERROR_TYPE_GENERAL);

				$categoryData = $this->_ci->PEPModel->getCategoryData([$mitarbeiterCategory->mitarbeiter_uid],  $mitarbeiterCategory->kategorie, $mitarbeiterCategory->studienjahr);

				$this->terminateWithSuccess(getData($categoryData)[0]);
			}
			else
			{
				$stunden_exists = $this->_ci->PEPKategorieMitarbeiterModel->load(array($mitarbeiterCategory->kategorie_mitarbeiter_id));

				if (!hasData($stunden_exists) || isError($stunden_exists))
					$this->terminateWithError($stunden_exists, self::ERROR_TYPE_GENERAL);

				$stunden_exists = getData($stunden_exists)[0];

				if ($stunden_exists->stunden !== number_format($mitarbeiterCategory->stunden, 2)
					|| ($stunden_exists->anmerkung !== $mitarbeiterCategory->anmerkung)
					|| ($stunden_exists->oe_kurzbz !== $mitarbeiterCategory->category_oe_kurzbz)
					|| ($stunden_exists->mitarbeiter_uid !== $mitarbeiterCategory->mitarbeiter_uid)
				)
				{
					$result = $this->_ci->PEPKategorieMitarbeiterModel->update(
						array($stunden_exists->kategorie_mitarbeiter_id),
						array(
							'stunden' => is_null($mitarbeiterCategory->stunden) ? 0 : number_format($mitarbeiterCategory->stunden, 2, '.', ''),
							'mitarbeiter_uid' => $mitarbeiterCategory->mitarbeiter_uid,
							'anmerkung' => $mitarbeiterCategory->anmerkung,
							'oe_kurzbz' => isEmptyString($mitarbeiterCategory->category_oe_kurzbz) ? null : $mitarbeiterCategory->category_oe_kurzbz,
							'updatevon' => $this->_uid,
							'updateamum' => date('Y-m-d H:i:s'),
						)
					);
					if (isError($result))
						$this->terminateWithError($result, self::ERROR_TYPE_GENERAL);
				}

				$dv = array();
				if ($stunden_exists->mitarbeiter_uid !== $mitarbeiterCategory->mitarbeiter_uid)
				{
					$dienstverhaeltnisse = $this->_ci->PEPModel->_getDVs(array($mitarbeiterCategory->mitarbeiter_uid), $mitarbeiterCategory->studienjahr);

					if (hasData($dienstverhaeltnisse))
						$dv = getData($dienstverhaeltnisse)[0];
				}
				$this->terminateWithSuccess(array('id' => $mitarbeiterCategory->kategorie_mitarbeiter_id, 'updated' => $dv));
			}
		}
	}

	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}

}

