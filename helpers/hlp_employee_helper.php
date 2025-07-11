<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

function getMitarbeiterUids($org, $studiensemester, $recursive)
{
	$ci =& get_instance();

	$ci->load->model('extensions/FHC-Core-PEP/PEP_model', 'PEPModelUid');

	$allMitarbeiter = $ci->PEPModelUid->getMitarbeiter($org, $studiensemester, $recursive);
	$mitarbeiter_uids = array('');
	if (hasData($allMitarbeiter))
		$mitarbeiter_uids = array_column(getData($allMitarbeiter), 'uid');
	return $mitarbeiter_uids;
}