<?php

declare(strict_types = 1);

require_once __DIR__ . '/../vendor/autoload.php';

use tobimori\Trawl\TranslationManager;

test('TranslationManager can be instantiated', function () {
	$manager = new TranslationManager([
		'languages' => ['en', 'de'],
		'outputPath' => sys_get_temp_dir(),
		'outputFormat' => 'json'
	]);
	expect($manager)->toBeInstanceOf(TranslationManager::class);
});

test('TranslationManager can generate JSON files', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid();
	mkdir($tempDir);

	$translations = ['Hello World', 'Welcome', 'About Us'];
	$manager = new TranslationManager([
		'languages' => ['en', 'de'],
		'outputPath' => $tempDir,
		'outputFormat' => 'json',
		'sourceLanguage' => 'en'
	]);
	// Convert strings to the format expected by generateTranslations
	$extractedTranslations = array_map(function ($text) {
		return ['key' => $text, 'function' => 't', 'line' => 1, 'context' => []];
	}, $translations);
	$manager->generateTranslations($extractedTranslations);

	expect(file_exists($tempDir . '/en.json'))->toBeTrue();
	expect(file_exists($tempDir . '/de.json'))->toBeTrue();

	$enContent = json_decode(file_get_contents($tempDir . '/en.json'), true);
	$deContent = json_decode(file_get_contents($tempDir . '/de.json'), true);

	expect($enContent)->toHaveKey('Hello World', 'Hello World');
	expect($enContent)->toHaveKey('Welcome', 'Welcome');
	expect($deContent)->toHaveKey('Hello World', '');
	expect($deContent)->toHaveKey('Welcome', '');

	// Cleanup
	unlink($tempDir . '/en.json');
	unlink($tempDir . '/de.json');
	rmdir($tempDir);
});

test('TranslationManager can generate YAML files', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid();
	mkdir($tempDir);

	$translations = ['Hello World', 'Welcome'];
	$manager = new TranslationManager([
		'languages' => ['en'],
		'outputPath' => $tempDir,
		'outputFormat' => 'yaml',
		'sourceLanguage' => 'en'
	]);
	// Convert strings to the format expected by generateTranslations
	$extractedTranslations = array_map(function ($text) {
		return ['key' => $text, 'function' => 't', 'line' => 1, 'context' => []];
	}, $translations);
	$manager->generateTranslations($extractedTranslations);

	expect(file_exists($tempDir . '/en.yaml'))->toBeTrue();

	$content = file_get_contents($tempDir . '/en.yaml');
	expect($content)->toContain('Hello World: Hello World');
	expect($content)->toContain('Welcome: Welcome');

	// Cleanup
	unlink($tempDir . '/en.yaml');
	rmdir($tempDir);
});

test('TranslationManager preserves existing translations', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid();
	mkdir($tempDir);

	// Create existing translation file
	$existing = [
		'Hello World' => 'Hallo Welt',
		'Old Translation' => 'Alte Übersetzung'
	];
	file_put_contents($tempDir . '/de.json', json_encode($existing, JSON_PRETTY_PRINT));

	$translations = ['Hello World', 'Welcome', 'New Item'];
	$manager = new TranslationManager([
		'languages' => ['de'],
		'outputPath' => $tempDir,
		'outputFormat' => 'json'
	]);
	// Convert strings to the format expected by generateTranslations
	$extractedTranslations = array_map(function ($text) {
		return ['key' => $text, 'function' => 't', 'line' => 1, 'context' => []];
	}, $translations);
	$manager->generateTranslations($extractedTranslations);

	$content = json_decode(file_get_contents($tempDir . '/de.json'), true);

	expect($content)->toHaveKey('Hello World', 'Hallo Welt'); // Preserved
	expect($content)->toHaveKey('Welcome', ''); // New, empty
	expect($content)->toHaveKey('New Item', ''); // New, empty
	expect($content)->toHaveKey('Old Translation', 'Alte Übersetzung'); // Preserved

	// Cleanup
	unlink($tempDir . '/de.json');
	rmdir($tempDir);
});

test('TranslationManager can clean unused translations', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid();
	mkdir($tempDir);

	// Create existing translation file with extra translations
	$existing = [
		'Hello World' => 'Hallo Welt',
		'Welcome' => 'Willkommen',
		'Unused Translation' => 'Unbenutzte Übersetzung',
		'Another Unused' => 'Noch eine unbenutzte'
	];
	file_put_contents($tempDir . '/de.json', json_encode($existing, JSON_PRETTY_PRINT));

	$translations = ['Hello World', 'Welcome'];
	$manager = new TranslationManager([
		'languages' => ['de'],
		'outputPath' => $tempDir,
		'outputFormat' => 'json'
	]);
	// Convert strings to the format expected by generateTranslations
	$extractedTranslations = array_map(function ($text) {
		return ['key' => $text, 'function' => 't', 'line' => 1, 'context' => []];
	}, $translations);
	$manager->generateTranslationsClean($extractedTranslations); // Clean mode

	$content = json_decode(file_get_contents($tempDir . '/de.json'), true);

	expect($content)->toHaveKey('Hello World', 'Hallo Welt');
	expect($content)->toHaveKey('Welcome', 'Willkommen');
	expect($content)->not->toHaveKey('Unused Translation');
	expect($content)->not->toHaveKey('Another Unused');

	// Cleanup
	unlink($tempDir . '/de.json');
	rmdir($tempDir);
});

test('TranslationManager handles source language correctly', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid();
	mkdir($tempDir);

	$translations = ['Hello World', 'Welcome'];
	$manager = new TranslationManager([
		'languages' => ['en', 'de'],
		'outputPath' => $tempDir,
		'outputFormat' => 'json',
		'sourceLanguage' => 'en'
	]);
	// Convert strings to the format expected by generateTranslations
	$extractedTranslations = array_map(function ($text) {
		return ['key' => $text, 'function' => 't', 'line' => 1, 'context' => []];
	}, $translations);
	$manager->generateTranslations($extractedTranslations);

	$enContent = json_decode(file_get_contents($tempDir . '/en.json'), true);
	$deContent = json_decode(file_get_contents($tempDir . '/de.json'), true);

	// Source language should have key=value pairs
	expect($enContent)->toHaveKey('Hello World', 'Hello World');
	expect($enContent)->toHaveKey('Welcome', 'Welcome');

	// Other languages should have empty values
	expect($deContent)->toHaveKey('Hello World', '');
	expect($deContent)->toHaveKey('Welcome', '');

	// Cleanup
	unlink($tempDir . '/en.json');
	unlink($tempDir . '/de.json');
	rmdir($tempDir);
});

test('TranslationManager creates output directory if it does not exist', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid() . '/nested/path';

	$translations = ['Hello World'];
	$manager = new TranslationManager([
		'languages' => ['en'],
		'outputPath' => $tempDir,
		'outputFormat' => 'json'
	]);
	// Convert strings to the format expected by generateTranslations
	$extractedTranslations = array_map(function ($text) {
		return ['key' => $text, 'function' => 't', 'line' => 1, 'context' => []];
	}, $translations);
	$manager->generateTranslations($extractedTranslations);

	expect(file_exists($tempDir . '/en.json'))->toBeTrue();

	// Cleanup
	unlink($tempDir . '/en.json');
	rmdir($tempDir);
	rmdir(dirname($tempDir));
	rmdir(dirname($tempDir, 2));
});

test('TranslationManager scaffolds tc() keys as arrays', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid();
	mkdir($tempDir);

	$extractedTranslations = [
		['key' => 'Hello World', 'function' => 't', 'line' => 1, 'context' => []],
		['key' => '{{ count }} members', 'function' => 'tc', 'line' => 2, 'context' => ['plural' => true]],
	];

	$manager = new TranslationManager([
		'languages' => ['en', 'de'],
		'outputPath' => $tempDir,
		'outputFormat' => 'json',
		'sourceLanguage' => 'en',
	]);
	$manager->generateTranslations($extractedTranslations);

	$enContent = json_decode(file_get_contents($tempDir . '/en.json'), true);
	$deContent = json_decode(file_get_contents($tempDir . '/de.json'), true);

	// regular key scaffolds as string
	expect($enContent['Hello World'])->toBe('Hello World');
	expect($deContent['Hello World'])->toBe('');

	// plural key scaffolds as array
	expect($enContent['{{ count }} members'])->toBe(['', '{{ count }} members']);
	expect($deContent['{{ count }} members'])->toBe(['', '']);

	// Cleanup
	unlink($tempDir . '/en.json');
	unlink($tempDir . '/de.json');
	rmdir($tempDir);
});

test('TranslationManager preserves existing plural translations', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid();
	mkdir($tempDir);

	// pre-populate with a translated plural
	$existing = [
		'{{ count }} members' => ['{{ count }} Mitglied', '{{ count }} Mitglieder'],
	];
	file_put_contents($tempDir . '/de.json', json_encode($existing, JSON_PRETTY_PRINT));

	$extractedTranslations = [
		['key' => '{{ count }} members', 'function' => 'tc', 'line' => 1, 'context' => ['plural' => true]],
	];

	$manager = new TranslationManager([
		'languages' => ['de'],
		'outputPath' => $tempDir,
		'outputFormat' => 'json',
	]);
	$manager->generateTranslations($extractedTranslations);

	$deContent = json_decode(file_get_contents($tempDir . '/de.json'), true);

	// existing translation should be preserved
	expect($deContent['{{ count }} members'])->toBe(['{{ count }} Mitglied', '{{ count }} Mitglieder']);

	// Cleanup
	unlink($tempDir . '/de.json');
	rmdir($tempDir);
});

test('TranslationManager treats empty plural arrays as missing', function () {
	$tempDir = sys_get_temp_dir() . '/trawl_test_' . uniqid();
	mkdir($tempDir);

	// file has the key but all elements are empty
	$existing = [
		'{{ count }} members' => ['', ''],
		'Hello' => 'Hallo',
	];
	file_put_contents($tempDir . '/de.json', json_encode($existing, JSON_PRETTY_PRINT));

	$extractedTranslations = [
		['key' => '{{ count }} members', 'function' => 'tc', 'line' => 1, 'context' => ['plural' => true]],
		['key' => 'Hello', 'function' => 't', 'line' => 2, 'context' => []],
	];

	$manager = new TranslationManager([
		'languages' => ['de'],
		'outputPath' => $tempDir,
		'outputFormat' => 'json',
	]);

	$missing = $manager->getMissingTranslations($extractedTranslations);

	// empty plural array should be reported as missing
	expect($missing['de'])->toContain('{{ count }} members');
	// translated key should not be missing
	expect($missing['de'])->not->toContain('Hello');

	// Cleanup
	unlink($tempDir . '/de.json');
	rmdir($tempDir);
});
