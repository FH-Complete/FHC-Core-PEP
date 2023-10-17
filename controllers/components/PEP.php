<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class PEP extends FHC_Controller
{
	private $_ci;
	private $_uid;
	const BERECHTIGUNG_KURZBZ = 'mitarbeiter/pep';
	
	public function __construct()
	{
		parent::__construct();
		
		$this->_ci = &get_instance();

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studiengang_model', 'StudiengangModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('person/Benutzerfunktion_model', 'BenutzerfunktionModel');
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');
		
		
		$this->_ci->load->library('AuthLib');
		$this->_ci->load->library('PermissionLib');
		$this->_setAuthUID();
		
	}
	
	//------------------------------------------------------------------------------------------------------------------
	// Public methods
	
	public function getStudienjahr()
	{
		$this->_ci->StudienjahrModel->addOrder('studienjahr_kurzbz', 'DESC');
		$semester = $this->_ci->StudienjahrModel->load();
		
		$this->outputJsonSuccess(getData($semester));
	}
	
	public function getOrg()
	{
		$oeKurzbz = $this->_ci->permissionlib->getOE_isEntitledFor(self::BERECHTIGUNG_KURZBZ);
		$this->outputJsonSuccess($oeKurzbz);
	}
	
	public function loadReport()
	{
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		$allMitarbeiter = $this->_getMitarbeiter($org, $studienjahr);
		
		if (!hasData($allMitarbeiter))
			$this->terminateWithJsonError("No Data");
		$mitarbeiterDataArray = array();
		
		foreach (getData($allMitarbeiter) as $mitarbeiter)
		{
			$mitarbeiterData = $mitarbeiter;
			
			$mitarbeiterData->dv = $this->_getDVs($mitarbeiter->uid, $studienjahr);
			
			foreach ($mitarbeiterData->dv as $dv)
			{
				$dv->stunden = $this->_getStunden($dv->dienstverhaeltnis_id);
			}
			
			$mitarbeiterData->aktuellstesDV = $this->_getAktuellstesDV($mitarbeiter->uid, $org);
			
			$mitarbeiterDataArray[] = $mitarbeiterData;
		}
		$this->outputJsonSuccess($mitarbeiterDataArray);
	}

	
	private function _getAktuellstesDV($uid, $org)
	{
		$dbModel = new DB_Model();
		$qry = "
		SELECT
		(
			SELECT wochenstunden
			FROM hr.tbl_vertragsbestandteil
			JOIN hr.tbl_vertragsbestandteil_stunden USING (vertragsbestandteil_id)
			WHERE dienstverhaeltnis_id = bestandteil.dienstverhaeltnis_id
				AND (tbl_vertragsbestandteil.von <= NOW() OR tbl_vertragsbestandteil.von IS NULL)
				AND (tbl_vertragsbestandteil.bis >= NOW() OR tbl_vertragsbestandteil.bis IS NULL)
			AND vertragsbestandteiltyp_kurzbz = 'stunden'
			ORDER BY von DESC NULLS LAST LIMIT 1
		) as Wochenstunden,
		(
			SELECT tbl_organisationseinheit.bezeichnung
			FROM hr.tbl_vertragsbestandteil
			JOIN hr.tbl_vertragsbestandteil_funktion USING (vertragsbestandteil_id)
			JOIN tbl_benutzerfunktion ON tbl_vertragsbestandteil_funktion.benutzerfunktion_id = tbl_benutzerfunktion.benutzerfunktion_id
			JOIN tbl_organisationseinheit ON tbl_benutzerfunktion.oe_kurzbz = tbl_organisationseinheit.oe_kurzbz
			WHERE dienstverhaeltnis_id = bestandteil.dienstverhaeltnis_id
				AND (tbl_vertragsbestandteil.von <= NOW() OR tbl_vertragsbestandteil.von IS NULL)
				AND (tbl_vertragsbestandteil.bis >= NOW() OR tbl_vertragsbestandteil.bis IS NULL)
				AND vertragsbestandteiltyp_kurzbz = 'funktion' AND funktion_kurzbz = 'kstzuordnung'
			ORDER BY von DESC NULLS LAST LIMIT 1
		) as kst_oe_kurzbz, tbl_vertragsart.bezeichnung
		FROM
			hr.tbl_dienstverhaeltnis dv
			JOIN hr.tbl_vertragsbestandteil bestandteil USING(dienstverhaeltnis_id)
			JOIN hr.tbl_vertragsart ON dv.vertragsart_kurzbz = tbl_vertragsart.vertragsart_kurzbz
		WHERE dienstverhaeltnis_id = (SELECT dienstverhaeltnis_id
										FROM hr.tbl_dienstverhaeltnis dv
										WHERE dv.mitarbeiter_uid = '". $uid ."'
										AND (dv.von <= NOW() OR dv.von IS NULL)
										AND (dv.bis >= NOW() OR dv.bis IS NULL)
										ORDER BY dv.von DESC NULLS LAST
										LIMIT 1)
		LIMIT 1
		";

		$aktuellstesDV = $dbModel->execReadOnlyQuery($qry);
		return getData($aktuellstesDV)[0];
	}

	public function lehreReport()
	{
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		
		$dbModel = new DB_Model();
		
		$qry = "
		SELECT
			distinct on (lehrveranstaltung_id)
			(
				WITH RECURSIVE meine_oes(oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz) as
				(
					SELECT
						oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz
					FROM
						public.tbl_organisationseinheit
					WHERE
						oe_kurzbz=tbl_studiengang.oe_kurzbz
						AND aktiv = true
					UNION ALL
					SELECT
						o.oe_kurzbz, o.oe_parent_kurzbz, o.organisationseinheittyp_kurzbz
					FROM
						public.tbl_organisationseinheit o, meine_oes
					WHERE
						o.oe_kurzbz=meine_oes.oe_parent_kurzbz
						AND aktiv = true
				)
				SELECT
					tbl_organisationseinheit.bezeichnung
				FROM
					meine_oes
					JOIN public.tbl_organisationseinheit USING(oe_kurzbz)
			    WHERE (
					(
						(meine_oes.organisationseinheittyp_kurzbz)::TEXT = 'Fakultaet'::TEXT)
						OR ((meine_oes.oe_kurzbz)::TEXT = 'lehrgang'::TEXT)
						OR ((meine_oes.oe_kurzbz)::TEXT = 'Spezialfaelle'::TEXT)
			    )
				LIMIT 1

			) as Fakultaet,
			upper(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) as stg_kuerzel,
			tbl_lehrveranstaltung.bezeichnung as lv_bezeichnung,
			tbl_lehreinheitgruppe.semester as legrp_semester,
			tbl_lehreinheitgruppe.verband as legrp_verband,
			tbl_lehreinheitgruppe.gruppe as legrp_gruppe,
			tbl_lehreinheitgruppe.gruppe_kurzbz as legrp_gruppekz,
			tbl_mitarbeiter.kurzbz as lektor,
			tbl_person.vorname as lektor_vorname,
			tbl_person.nachname as lektor_nachname,
			tbl_lehrveranstaltung.lehrveranstaltung_id,
			tbl_lehreinheit.studiensemester_kurzbz
		FROM
			lehre.tbl_lehrveranstaltung
			JOIN lehre.tbl_lehreinheit ON tbl_lehrveranstaltung.lehrveranstaltung_id = tbl_lehreinheit.lehrfach_id
			JOIN lehre.tbl_lehreinheitgruppe ON tbl_lehreinheit.lehreinheit_id = tbl_lehreinheitgruppe.lehreinheit_id
			LEFT JOIN public.tbl_studiengang ON tbl_lehrveranstaltung.studiengang_kz = tbl_studiengang.studiengang_kz
			JOIN lehre.tbl_lehreinheitmitarbeiter ON tbl_lehreinheit.lehreinheit_id = tbl_lehreinheitmitarbeiter.lehreinheit_id
			JOIN public.tbl_mitarbeiter USING(mitarbeiter_uid)
			JOIN public.tbl_benutzer ON tbl_mitarbeiter.mitarbeiter_uid = tbl_benutzer.uid
			JOIN public.tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
		WHERE
			tbl_lehrveranstaltung.oe_kurzbz='". $org ."'
			AND tbl_lehrveranstaltung.aktiv
			AND tbl_lehreinheit.studiensemester_kurzbz IN (
				SELECT tbl_studiensemester.studiensemester_kurzbz
				FROM tbl_studienjahr
					JOIN tbl_studiensemester ON tbl_studienjahr.studienjahr_kurzbz = tbl_studiensemester.studienjahr_kurzbz
				WHERE tbl_studiensemester.studienjahr_kurzbz = '". $studienjahr ."'
			);
		";
		$result = $dbModel->execReadOnlyQuery($qry);
		$this->outputJsonSuccess(getData($result));
	}
	
	private function _getMitarbeiter($org, $studienjahr)
	{
		$dbModel = new DB_Model();

		$qry = "SELECT lektor,
					vorname,
					nachname,
					ma.uid,
				    (
						SELECT kontakt
						FROM PUBLIC.tbl_kontakt
						WHERE person_id = ma.person_id
							AND kontakttyp = 'email'
						ORDER BY zustellung ASC,
							insertamum DESC LIMIT 1
					) AS email
				FROM campus.vw_mitarbeiter ma
				JOIN hr.tbl_dienstverhaeltnis dv ON ma.uid = dv.mitarbeiter_uid
				JOIN hr.tbl_vertragsart vertragsart USING(vertragsart_kurzbz)
				JOIN hr.tbl_vertragsbestandteil bestandteil ON dv.dienstverhaeltnis_id = bestandteil.dienstverhaeltnis_id
				JOIN hr.tbl_vertragsbestandteil_funktion vbstfunktion ON bestandteil.vertragsbestandteil_id = vbstfunktion.vertragsbestandteil_id
				LEFT JOIN public.tbl_benutzerfunktion funktion ON vbstfunktion.benutzerfunktion_id = funktion.benutzerfunktion_id
				WHERE funktion_kurzbz = 'kstzuordnung'
				AND (
					bestandteil.von <= (
						SELECT MIN(start)
						FROM public.tbl_studienjahr
							JOIN public.tbl_studiensemester USING(studienjahr_kurzbz)
						WHERE public.tbl_studienjahr.studienjahr_kurzbz = '". $studienjahr ."'
					)
					OR bestandteil.von IS NULL
				)
				AND
				(
					bestandteil.bis >= (
						SELECT MAX(ende)
						FROM public.tbl_studienjahr
							JOIN public.tbl_studiensemester USING(studienjahr_kurzbz)
						WHERE public.tbl_studienjahr.studienjahr_kurzbz = '". $studienjahr ."'
					)
					OR bestandteil.bis IS NULL
				)
				AND funktion.oe_kurzbz IN
				(
					WITH RECURSIVE oes(oe_kurzbz, oe_parent_kurzbz) as
					(
						SELECT oe_kurzbz, oe_parent_kurzbz FROM public.tbl_organisationseinheit
						WHERE oe_kurzbz = '". $org ."'
						UNION ALL
						SELECT o.oe_kurzbz, o.oe_parent_kurzbz FROM public.tbl_organisationseinheit o, oes
						WHERE o.oe_parent_kurzbz=oes.oe_kurzbz
				    )
					SELECT oe_kurzbz
					FROM oes
					GROUP BY oe_kurzbz
				)
		";
		
		$mitarbeiter = $dbModel->execReadOnlyQuery($qry);
		
		return ($mitarbeiter);
	}
	
	private function _getDVs($uid, $studienjahr)
	{
		$dbModel = new DB_Model();
		$qry = "SELECT dv.von,
						dv.bis,
						dv.dienstverhaeltnis_id,
						bezeichnung
				FROM hr.tbl_dienstverhaeltnis dv
				JOIN hr.tbl_vertragsart USING (vertragsart_kurzbz)
				WHERE (
					dv.von <= (
						SELECT MIN(start)
						FROM public.tbl_studienjahr
							JOIN public.tbl_studiensemester USING(studienjahr_kurzbz)
						WHERE public.tbl_studienjahr.studienjahr_kurzbz = '". $studienjahr ."'
					)
					OR dv.von IS NULL
				)
				AND
				(
					dv.bis >= (
						SELECT MAX(ende)
						FROM public.tbl_studienjahr
							JOIN public.tbl_studiensemester USING(studienjahr_kurzbz)
						WHERE public.tbl_studienjahr.studienjahr_kurzbz = '". $studienjahr ."'
					)
					OR dv.bis IS NULL
				)
				AND dv.mitarbeiter_uid = '". $uid ."'
				ORDER BY dv.von
		";
		
		$dienstverhaeltnis = $dbModel->execReadOnlyQuery($qry);
		
		return getData($dienstverhaeltnis);
	}
	
	
	public function personalReport()
	{
		$org = $this->_ci->input->get('org');
		$studienjahr = $this->_ci->input->get('studienjahr');
		
		$dbModel = new DB_Model();
		
		$qry = "
		SELECT
			DISTINCT ON (tbl_dienstverhaeltnis.mitarbeiter_uid)
		(
			WITH RECURSIVE meine_oes(oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz) as
			(
				SELECT
					oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz
				FROM
					public.tbl_organisationseinheit
				WHERE
					oe_kurzbz=tbl_benutzerfunktion.oe_kurzbz
					AND aktiv = true
				UNION ALL
				SELECT
					o.oe_kurzbz, o.oe_parent_kurzbz, o.organisationseinheittyp_kurzbz
				FROM
					public.tbl_organisationseinheit o, meine_oes
				WHERE
					o.oe_kurzbz=meine_oes.oe_parent_kurzbz
					AND aktiv = true
			)
			SELECT
				tbl_organisationseinheit.bezeichnung
			FROM
				meine_oes
					 JOIN public.tbl_organisationseinheit USING(oe_kurzbz)
				WHERE (
					((meine_oes.organisationseinheittyp_kurzbz)::TEXT = 'Fakultaet'::TEXT)
						 OR ((meine_oes.oe_kurzbz)::TEXT = 'lehrgang'::TEXT)
						 OR ((meine_oes.oe_kurzbz)::TEXT = 'Spezialfaelle'::TEXT)
				)
			LIMIT 1
		) as Fakultaet,
		(
			WITH RECURSIVE meine_oes(oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz) as
			(
				SELECT
					oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz
				FROM
					public.tbl_organisationseinheit
				WHERE
					oe_kurzbz=tbl_benutzerfunktion.oe_kurzbz
					AND aktiv = true
				UNION ALL
				SELECT
					o.oe_kurzbz, o.oe_parent_kurzbz, o.organisationseinheittyp_kurzbz
				FROM
					public.tbl_organisationseinheit o, meine_oes
				WHERE
					o.oe_kurzbz=meine_oes.oe_parent_kurzbz
					AND aktiv = true
			)
			SELECT
				tbl_organisationseinheit.bezeichnung
			FROM
				meine_oes
					 JOIN public.tbl_organisationseinheit USING(oe_kurzbz)
				WHERE
					((meine_oes.organisationseinheittyp_kurzbz)::TEXT = 'Department'::TEXT)
			LIMIT 1
		) as Department,(
			WITH RECURSIVE meine_oes(oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz) as
			(
				SELECT
					oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz
				FROM
					public.tbl_organisationseinheit
				WHERE
					oe_kurzbz=tbl_benutzerfunktion.oe_kurzbz
					AND aktiv = true
				UNION ALL
				SELECT
					o.oe_kurzbz, o.oe_parent_kurzbz, o.organisationseinheittyp_kurzbz
				FROM
					public.tbl_organisationseinheit o, meine_oes
				WHERE
					o.oe_kurzbz=meine_oes.oe_parent_kurzbz
					AND aktiv = true
			)
			SELECT
				tbl_organisationseinheit.bezeichnung
			FROM
				meine_oes
					 JOIN public.tbl_organisationseinheit USING(oe_kurzbz)
				WHERE
					((meine_oes.organisationseinheittyp_kurzbz)::TEXT = 'Kompetenzfeld'::TEXT)
			LIMIT 1
		) as Kompetenzfeld,
		tbl_person.vorname,
		tbl_person.nachname
		FROM
			public.tbl_mitarbeiter
			JOIN public.tbl_benutzer ON tbl_mitarbeiter.mitarbeiter_uid = tbl_benutzer.uid
			JOIN public.tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
			JOIN hr.tbl_dienstverhaeltnis ON tbl_mitarbeiter.mitarbeiter_uid = tbl_dienstverhaeltnis.mitarbeiter_uid
			JOIN hr.tbl_vertragsbestandteil ON tbl_dienstverhaeltnis.dienstverhaeltnis_id = tbl_vertragsbestandteil.dienstverhaeltnis_id
			JOIN hr.tbl_vertragsbestandteil_funktion ON tbl_vertragsbestandteil.vertragsbestandteil_id = tbl_vertragsbestandteil_funktion.vertragsbestandteil_id
			JOIN public.tbl_benutzerfunktion ON tbl_vertragsbestandteil_funktion.benutzerfunktion_id = tbl_benutzerfunktion.benutzerfunktion_id
		WHERE
		(
			tbl_vertragsbestandteil.von <= (
				SELECT MIN(start)
				FROM public.tbl_studienjahr
						 JOIN public.tbl_studiensemester USING(studienjahr_kurzbz)
				WHERE public.tbl_studienjahr.studienjahr_kurzbz = '". $studienjahr ."'
			)
			OR tbl_vertragsbestandteil.von IS NULL
		)
		AND
		(
			tbl_vertragsbestandteil.bis >= (
				SELECT MAX(ende)
				FROM public.tbl_studienjahr
					 JOIN public.tbl_studiensemester USING(studienjahr_kurzbz)
				WHERE public.tbl_studienjahr.studienjahr_kurzbz = '". $studienjahr ."'
			)
			OR tbl_vertragsbestandteil.bis IS NULL
		)
		AND tbl_benutzerfunktion.oe_kurzbz IN
		(
			WITH RECURSIVE oes(oe_kurzbz, oe_parent_kurzbz) as
			(
				SELECT oe_kurzbz, oe_parent_kurzbz FROM public.tbl_organisationseinheit
				WHERE oe_kurzbz = 'kfDigitalEnterprise'
				UNION ALL
				SELECT o.oe_kurzbz, o.oe_parent_kurzbz FROM public.tbl_organisationseinheit o, oes
				WHERE o.oe_parent_kurzbz=oes.oe_kurzbz
			)
			SELECT oe_kurzbz
			FROM oes
			GROUP BY oe_kurzbz
		)
		ORDER BY tbl_dienstverhaeltnis.mitarbeiter_uid";
		
		$dienstverhaeltnis = $dbModel->execReadOnlyQuery($qry);
		
		$this->outputJsonSuccess(getData($dienstverhaeltnis));
	}
	
	private function _getStunden($dienstverhaeltnis_id)
	{
		$dbModel = new DB_Model();
		$qry = "SELECT dienstverhaeltnis_id,
					wochenstunden,
					tbl_vertragsbestandteil.von,
					tbl_vertragsbestandteil.bis
				FROM hr.tbl_dienstverhaeltnis
					JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
					JOIN hr.tbl_vertragsbestandteil_stunden USING(vertragsbestandteil_id)
				WHERE dienstverhaeltnis_id = '". $dienstverhaeltnis_id."'";
		
		$stunden = $dbModel->execReadOnlyQuery($qry);
		
		return getData($stunden);
	}
	
	
	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid)
			show_error('User authentification failed');
	}
}

