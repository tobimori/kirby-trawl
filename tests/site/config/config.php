<?php

return [
	'debug' => true,
	'languages' => true,
	'cache' => [
		'pages' => [
			'active' => false
		]
	],
	'tobimori.trawl' => [
		'sourceLanguage' => 'en',
		'languages' => ['en', 'de'],
		'outputPath' => __DIR__ . '/../../site/translations',
		'outputFormat' => 'json',
	]
];
