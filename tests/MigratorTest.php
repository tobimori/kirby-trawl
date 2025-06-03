<?php

use tobimori\Trawl\Migrator;

test('Migrator can be instantiated', function () {
	$migrator = new Migrator([]);
	expect($migrator)->toBeInstanceOf(Migrator::class);
});

test('Migrator can store migration mapping', function () {
	$mapping = [
		'field.title.label' => 'Title',
		'field.content.label' => 'Content',
		'page.home.title' => 'Home Page'
	];

	$migrator = new Migrator($mapping);
	expect($migrator)->toBeInstanceOf(Migrator::class);
});

test('Migrator can find affected files', function () {
	$mapping = [
		'field.title.label' => 'Title',
		'field.content.label' => 'Content'
	];

	$migrator = new Migrator($mapping);

	// Test with minimal options
	$options = ['exclude' => []];
	$result = $migrator->findAffectedFiles($options);

	expect($result)->toHaveKey('php');
	expect($result)->toHaveKey('blueprint');
	expect($result['php'])->toBeArray();
	expect($result['blueprint'])->toBeArray();
});

test('Migrator migrates * values in blueprints', function () {
	$mapping = [
		'category.architecture' => 'cat.arch',
		'category.photography' => 'cat.photo',
		'status.draft' => 'status.unpublished',
	];

	$yaml = "
fields:
  category:
    label: Category
    type: select
    options:
      architecture:
        *: category.architecture
      photography:
        *: 'category.photography'
      design:
        *: \"category.design\"
  status:
    type: radio
    options:
      draft:
        *: status.draft
";

	$tempFile = sys_get_temp_dir() . '/test_migrate_star_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$migrator = new Migrator($mapping);
	$result = $migrator->migrate(['php' => [], 'blueprint' => [$tempFile]]);

	$migratedContent = file_get_contents($tempFile);
	unlink($tempFile);

	expect($result['blueprint'])->toBe(1);
	expect($result['replacements'])->toBe(3);
	expect($migratedContent)->toContain('*: cat.arch');
	expect($migratedContent)->toContain("*: 'cat.photo'");
	expect($migratedContent)->toContain('*: status.unpublished');
	expect($migratedContent)->toContain('*: "category.design"'); // Should remain unchanged
});

test('Migrator migrates * values with KQL expressions', function () {
	$mapping = [
		'option.one' => 'opt.first',
		'item' => 'element',
		'simple.key' => 'basic.key',
	];

	$yaml = "
fields:
  choice:
    label: Choice
    type: select
    options:
      option1:
        *: '{{ t(\"option.one\") }}'
      option2:
        *: 'simple.key'
      option3:
        *: \"{{ tt('item', 'items', 1) }}\"
";

	$tempFile = sys_get_temp_dir() . '/test_migrate_kql_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$migrator = new Migrator($mapping);
	$result = $migrator->migrate(['php' => [], 'blueprint' => [$tempFile]]);

	$migratedContent = file_get_contents($tempFile);
	unlink($tempFile);

	expect($result['blueprint'])->toBe(1);
	expect($result['replacements'])->toBe(3);
	expect($migratedContent)->toContain('*: \'{{ t("opt.first") }}\'');
	expect($migratedContent)->toContain("*: 'basic.key'");
	expect($migratedContent)->toContain('*: "{{ tt(\'element\', \'items\', 1) }}"');
});
