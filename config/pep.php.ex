<?php

// if set to true, projects will be shown
$config['enable_projects'] = true;

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