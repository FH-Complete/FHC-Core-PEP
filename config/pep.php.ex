<?php

// if set to true, projects will be shown
$config['enable_projects'] = true;

// if set to true, compare tab will be shown
$config['enable_compare_tab'] = false;

// if set to true, lv enwticklung tab will be shown
$config['enable_lv_entwicklung_tab'] = false;

// determines whether projects on the Start tab are displayed by "studienjahr" (study year) or "studiensemester" (study semester)
$config['projects_columns'] = 'studienjahr';

// determines whether categories on the Start tab are displayed by "studienjahr" (study year) or "studiensemester" (study semester)
$config['category_columns'] = 'studienjahr';

// determines if the "Werkvertragsvolumen in ECTS" field is editable based on the person's contract type
$config['lventwicklung_allow_ects_volume_edit'] = array('');

// determines which employees are displayed based on their assigned function types
$config['relevant_function_types'] = array();

// defines the base annual hours and divisors for each organizational unit, used for aliquot calculations
$config['annual_hours'] = array(
	[
		'condition' => "OE",
		'base_value' => 2000,
		'hour_divisor' => 40,
	],
	[
		'condition' => "OE1",
		'base_value' => 2500,
		'hour_divisor' => 20,
	],
);

// defines which projects are displayed based on project id matching
$config['project_id_list'] = array(
	'P1%',
	'P2%',
	'P3%'
);

// content ID for CMS reference
$config['content_id'] = XXX

//defines which tags are displayed in the "Planning Status" column
$config['planungsstatus_tags'] = array('');

// defines which project statuses are NOT loaded
$config['excluded_project_status'] = array(0);