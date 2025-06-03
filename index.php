<?php

use Kirby\Cms\App;

@include_once __DIR__ . '/vendor/autoload.php';

if (
	version_compare(App::version() ?? '0.0.0', '5.0.0-rc.3', '<') === true ||
	version_compare(App::version() ?? '0.0.0', '6.0.0', '>') === true
) {
	throw new Exception('Kirby Trawl requires Kirby 5');
}

App::plugin('tobimori/trawl', [
	'options' => [
		'sourceLanguage' => 'en', // e.g., 'en' or 'de'
		'languages' => ['en', 'de'], // Languages to generate
		'outputPath' => 'site/translations',
		'outputFormat' => 'json', // json or yml
		'include' => [
			'site/templates/**/*.php',
			'site/snippets/**/*.php',
			'site/models/**/*.php',
			'site/blueprints/**/*.yml',
		],
		'exclude' => [
			'**/node_modules/**',
			'**/vendor/**',
		],
	],
	'commands' => [
		'trawl:extract' => require __DIR__ . '/commands/extract.php',
		'trawl:validate' => require __DIR__ . '/commands/validate.php',
		'trawl:migrate' => require __DIR__ . '/commands/migrate.php',
	],
]);
