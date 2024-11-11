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
			'updateLehre' => self::BERECHTIGUNG_KURZBZ,
			'doneLehre' => self::BERECHTIGUNG_KURZBZ,
			'deleteLehre' => self::BERECHTIGUNG_KURZBZ,
		]);

		$this->_ci = &get_instance();
	}
}