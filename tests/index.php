<?php

const KIRBY_HELPER_DUMP = false;
const KIRBY_HELPER_E = false;

require_once __DIR__ . '/../vendor/autoload.php';

// Initialize Kirby with test configuration
echo (new Kirby([
	'roots' => [
		'index' => __DIR__,
		'base' => __DIR__,
	],
]))->render();
