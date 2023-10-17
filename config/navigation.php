<?php
// Add Header-Menu-Entry to all Pages

$config['navigation_header']['*']['Personen']['children']['PEP'] = array(
	'link' => site_url('extensions/FHC-Core-PEP/PEP'),
	'description' => 'Personaleinsatzplanung',
	'expand' => false,
	'requiredPermissions' => 'mitarbeiter/pep:rw'
);