<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class PEP extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
			'index' => 'mitarbeiter/pep:r',
			)
		);
	}
	
	// -----------------------------------------------------------------------------------------------------------------
	// Public methods
	
	public function index()
	{
		$this->load->view('extensions/FHC-Core-PEP/pep.php');
	}

}

