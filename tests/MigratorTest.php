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