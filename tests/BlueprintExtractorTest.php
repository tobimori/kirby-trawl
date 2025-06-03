<?php

use tobimori\Trawl\BlueprintExtractor;

test('BlueprintExtractor can be instantiated', function () {
    $extractor = new BlueprintExtractor();
    expect($extractor)->toBeInstanceOf(BlueprintExtractor::class);
});

test('BlueprintExtractor can extract label values', function () {
    $yaml = "
title: Test Page
fields:
  title:
    label: Page Title
    type: text
  content:
    label: Content Area
    type: textarea
";
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
    file_put_contents($tempFile, $yaml);
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extract($tempFile);
    $translations = array_column($result, 'key');
    
    unlink($tempFile);
    
    expect($translations)->toContain('Page Title');
    expect($translations)->toContain('Content Area');
});

test('BlueprintExtractor can extract help text', function () {
    $yaml = "
fields:
  email:
    label: Email
    type: email
    help: Enter a valid email address
  phone:
    label: Phone
    type: text
    help: Include country code
";
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
    file_put_contents($tempFile, $yaml);
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extract($tempFile);
    $translations = array_column($result, 'key');
    
    unlink($tempFile);
    
    expect($translations)->toContain('Enter a valid email address');
    expect($translations)->toContain('Include country code');
});

test('BlueprintExtractor can extract placeholder text', function () {
    $yaml = "
fields:
  title:
    label: Title
    type: text
    placeholder: Enter page title here
  description:
    label: Description
    type: textarea
    placeholder: Describe your content
";
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
    file_put_contents($tempFile, $yaml);
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extract($tempFile);
    $translations = array_column($result, 'key');
    
    unlink($tempFile);
    
    expect($translations)->toContain('Enter page title here');
    expect($translations)->toContain('Describe your content');
});

test('BlueprintExtractor can extract basic field labels', function () {
    $yaml = "
fields:
  status:
    label: Status
    type: select
    help: Choose the publication status
    placeholder: Select status
";
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
    file_put_contents($tempFile, $yaml);
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extract($tempFile);
    $translations = array_column($result, 'key');
    
    unlink($tempFile);
    
    expect($translations)->toContain('Status');
    expect($translations)->toContain('Choose the publication status');
    expect($translations)->toContain('Select status');
});

test('BlueprintExtractor skips empty values', function () {
    $yaml = "
fields:
  title:
    label: Valid Label
    type: text
    placeholder: ''
  content:
    label: ''
    type: textarea
    help: Valid Help
";
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
    file_put_contents($tempFile, $yaml);
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extract($tempFile);
    $translations = array_column($result, 'key');
    
    unlink($tempFile);
    
    expect($translations)->toContain('Valid Label');
    expect($translations)->toContain('Valid Help');
    expect($translations)->not->toContain('');
});

test('BlueprintExtractor skips KQL expressions', function () {
    $yaml = "
fields:
  title:
    label: Page Title
    type: text
  pages:
    label: Related Pages
    type: pages
    query: site.children.published
    info: '{{ page.title }}'
  dynamic:
    label: Dynamic Field
    text: '{{ site.title }} - {{ page.slug }}'
";
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
    file_put_contents($tempFile, $yaml);
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extract($tempFile);
    $translations = array_column($result, 'key');
    
    unlink($tempFile);
    
    expect($translations)->toContain('Page Title');
    expect($translations)->toContain('Related Pages');
    expect($translations)->toContain('Dynamic Field');
    expect($translations)->not->toContain('{{ page.title }}');
    expect($translations)->not->toContain('{{ site.title }} - {{ page.slug }}');
});

test('BlueprintExtractor handles nested structures', function () {
    $yaml = "
tabs:
  content:
    label: Content Tab
    fields:
      blocks:
        label: Content Blocks
        type: blocks
        fieldsets:
          text:
            label: Text Block
            fields:
              content:
                label: Text Content
                type: writer
";
    
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
    file_put_contents($tempFile, $yaml);
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extract($tempFile);
    $translations = array_column($result, 'key');
    
    unlink($tempFile);
    
    expect($translations)->toContain('Content Tab');
    expect($translations)->toContain('Content Blocks');
    expect($translations)->toContain('Text Block');
    expect($translations)->toContain('Text Content');
});

test('BlueprintExtractor can extract from multiple files', function () {
    $files = [];
    
    // Create test files
    $files[] = $tempFile1 = sys_get_temp_dir() . '/test1_' . uniqid() . '.yml';
    file_put_contents($tempFile1, "title: Page One\nfields:\n  title:\n    label: Title One");
    
    $files[] = $tempFile2 = sys_get_temp_dir() . '/test2_' . uniqid() . '.yml';
    file_put_contents($tempFile2, "title: Page Two\nfields:\n  title:\n    label: Title Two");
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extractFromFiles($files);
    $translations = array_column($result, 'key');
    
    // Cleanup
    foreach ($files as $file) {
        unlink($file);
    }
    
    expect($translations)->toContain('Title One');
    expect($translations)->toContain('Title Two');
});

test('BlueprintExtractor handles invalid YAML gracefully', function () {
    $tempFile = sys_get_temp_dir() . '/invalid_' . uniqid() . '.yml';
    file_put_contents($tempFile, "invalid: yaml: content: [unclosed");
    
    $extractor = new BlueprintExtractor();
    $result = $extractor->extract($tempFile);
    $translations = array_column($result, 'key');
    
    unlink($tempFile);
    
    expect($translations)->toBeArray();
    expect($translations)->toHaveCount(0);
});