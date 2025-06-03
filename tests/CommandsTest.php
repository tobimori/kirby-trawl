<?php

test('trawl:extract command exists', function () {
	$kirby = kirby();
	expect($kirby->plugin('tobimori/trawl'))->not->toBeNull();

	$commands = $kirby->extensions('commands');
	expect($commands)->toHaveKey('trawl:extract');
});

test('trawl:validate command exists', function () {
	$kirby = kirby();
	$commands = $kirby->extensions('commands');
	expect($commands)->toHaveKey('trawl:validate');
});

test('trawl:migrate command exists', function () {
	$kirby = kirby();
	$commands = $kirby->extensions('commands');
	expect($commands)->toHaveKey('trawl:migrate');
});

test('plugin options are properly configured', function () {
	$kirby = kirby();

	expect($kirby->option('tobimori.trawl.sourceLanguage'))->toBe('en');
	expect($kirby->option('tobimori.trawl.languages'))->toBe(['en', 'de']);
	expect($kirby->option('tobimori.trawl.outputFormat'))->toBe('json');
});

test('plugin can extract translations from test templates', function () {
	// Get the test template content
	$templatePath = __DIR__ . '/site/templates/default.php';
	expect(file_exists($templatePath))->toBeTrue();

	$content = file_get_contents($templatePath);
	expect($content)->toContain('t(\'Welcome to our website\')');
	expect($content)->toContain('t(\'About Us\')');
});

test('plugin can extract translations from test blueprints', function () {
	// Get the test blueprint content
	$blueprintPath = __DIR__ . '/site/blueprints/pages/default.yml';
	expect(file_exists($blueprintPath))->toBeTrue();

	$content = file_get_contents($blueprintPath);
	expect($content)->toContain('label: Content');
	expect($content)->toContain('label: Title');
});

test('test site has required languages configured', function () {
	$languages = kirby()->languages();
	expect($languages->count())->toBe(2);

	$en = $languages->findByKey('en');
	$de = $languages->findByKey('de');

	expect($en)->not->toBeNull();
	expect($de)->not->toBeNull();
	expect($en->isDefault())->toBeTrue();
	expect($de->isDefault())->toBeFalse();
});

test('test site has home page', function () {
	$homePage = kirby()->page('home');
	expect($homePage)->not->toBeNull();
	expect($homePage->exists())->toBeTrue();
});
