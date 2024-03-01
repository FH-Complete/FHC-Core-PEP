<?php
$includesArray = array(
	'title' => 'PEP',
	'vue3' => true,
	'axios027' => true,
	'bootstrap5' => true,
	'primevue3' => true,
	'tabulator5' => true,
	'fontawesome6' => true,
	'navigationcomponent' => true,
	'customJSModules' => array('public/extensions/FHC-Core-PEP/js/apps/PEPApp.js'),
	'customCSSs' => array('public/extensions/FHC-Core-PEP/css/main.css'),
);

$this->load->view('templates/FHC-Header', $includesArray);
?>

<div id="main">
	<pep-report></pep-report>
</div>

<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>
