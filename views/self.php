<?php
$includesArray = array(
	'title' => 'PEP',
	'vue3' => true,
	'axios027' => true,
	'bootstrap5' => true,
	'primevue3' => true,
	'tabulator5' => true,
	'fontawesome6' => true,
	'tags' => true,
	'customJSModules' => array('public/extensions/FHC-Core-PEP/js/apps/SelfApp.js'),
	'customCSSs' => array('public/extensions/FHC-Core-PEP/css/main.css'),
);

$this->load->view('templates/FHC-Header', $includesArray);
?>

<div id="main">
	<self-report
		:studienjahre="<?= htmlspecialchars(json_encode($studienjahre)); ?>"
		:mitarbeiter_auswahl="<?= htmlspecialchars(json_encode($mitarbeiter_auswahl)); ?>"
		:mitarbeiter_auswahl_reload="<?= htmlspecialchars(json_encode($mitarbeiter_auswahl_reload)); ?>"

	></self-report>
</div>

<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>
