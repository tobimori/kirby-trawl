<?php

use tobimori\Trawl\BlueprintExtractor;

test('BlueprintExtractor can be instantiated', function () {
	$extractor = new BlueprintExtractor();
	expect($extractor)->toBeInstanceOf(BlueprintExtractor::class);
});

test('BlueprintExtractor can extract label values', function () {
	$yaml = '
title: Test Page
fields:
  title:
    label: Page Title
    type: text
  content:
    label: Content Area
    type: textarea
';

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
	$yaml = '
fields:
  email:
    label: Email
    type: email
    help: Enter a valid email address
  phone:
    label: Phone
    type: text
    help: Include country code
';

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
	$yaml = '
fields:
  title:
    label: Title
    type: text
    placeholder: Enter page title here
  description:
    label: Description
    type: textarea
    placeholder: Describe your content
';

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
	$yaml = '
fields:
  status:
    label: Status
    type: select
    help: Choose the publication status
    placeholder: Select status
';

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

test('BlueprintExtractor extracts translations from KQL t() function', function () {
	$yaml = "
fields:
  status:
    label: '{{ t(\"Status\") }}'
    type: select
    help: '{{ t(\"Choose your status\") }}'
  description:
    label: Description
    type: textarea
    placeholder: '{{ page.title }} - {{ t(\"Add description here\") }}'
";

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('Status');
	expect($translations)->toContain('Choose your status');
	expect($translations)->toContain('Description');
	expect($translations)->toContain('Add description here');
});

test('BlueprintExtractor extracts translations from KQL tc() function', function () {
	$yaml = "
fields:
  title:
    label: '{{ tc(\"page.title\", \"Title\") }}'
    type: text
    help: '{{ tc(\"page.title.help\", \"Enter the page title\") }}'
  content:
    label: '{{ tc(\"page.content\", \"Content\") }}'
    type: textarea
";

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('page.title');
	expect($translations)->toContain('page.title.help');
	expect($translations)->toContain('page.content');
});

test('BlueprintExtractor extracts translations from KQL tt() function', function () {
	$yaml = "
fields:
  count:
    label: '{{ tt(\"item\", \"items\", page.children.count) }}'
    type: text
    info: '{{ tt(\"file\", \"files\", files.count) }} available'
";

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('item');
	expect($translations)->toContain('file');
});

test('BlueprintExtractor handles mixed KQL translation functions', function () {
	$yaml = "
fields:
  composite:
    label: '{{ t(\"Welcome\") }} - {{ tc(\"user.greeting\", \"Hello\") }}'
    type: text
    help: '{{ tt(\"day\", \"days\", 1) }} {{ t(\"remaining\") }}'
    placeholder: 'Start with {{ t(\"Type here\") }}'
";

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('Welcome');
	expect($translations)->toContain('user.greeting');
	expect($translations)->toContain('day');
	expect($translations)->toContain('remaining');
	expect($translations)->toContain('Type here');
});

test('BlueprintExtractor handles KQL translation functions with single quotes', function () {
	$yaml = "
fields:
  title:
    label: \"{{ t('Page Title') }}\"
    type: text
    help: \"{{ tc('help.text', 'Need help?') }}\"
";

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('Page Title');
	expect($translations)->toContain('help.text');
});

test('BlueprintExtractor handles edge cases in KQL translation extraction', function () {
	$yaml = "
fields:
  edge1:
    label: '{{ t(\"Text with spaces\") }}'
    type: text
  edge2:
    label: '{{ t(\"Text with \\\"quotes\\\"\") }}'
    type: text
  edge3:
    label: '{{ t(\"\") }}' # Empty string
    type: text
  edge4:
    label: '{{ page.title }}' # No translation function
    type: text
  edge5:
    label: Normal Label # Not a KQL expression
    type: text
";

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('Text with spaces');
	expect($translations)->toContain('Text with "quotes"');
	expect($translations)->toContain('Normal Label');
	expect($translations)->not->toContain('');
	expect($translations)->not->toContain('{{ page.title }}');
});

test('BlueprintExtractor handles nested structures', function () {
	$yaml = '
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
';

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
	file_put_contents($tempFile, 'invalid: yaml: content: [unclosed');

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toBeArray();
	expect($translations)->toHaveCount(0);
});

test('BlueprintExtractor extracts * values from options', function () {
	$yaml = '
fields:
  category:
    label: Category
    type: select
    options:
      architecture:
        *: category.architecture
      photography:
        *: category.photography
      design:
        en: Design
        de: Gestaltung
        *: category.design
';

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('Category');
	expect($translations)->toContain('category.architecture');
	expect($translations)->toContain('category.photography');
	expect($translations)->toContain('category.design');
});

test('BlueprintExtractor extracts * values from nested structures', function () {
	$yaml = '
fields:
  status:
    label: Status
    type: radio
    options:
      draft:
        *: status.draft
      published:
        *: status.published
        label: Published
  blocks:
    type: blocks
    fieldsets:
      text:
        *: blocks.text
        label: Text Block
';

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('Status');
	expect($translations)->toContain('status.draft');
	expect($translations)->toContain('status.published');
	expect($translations)->toContain('Published');
	expect($translations)->toContain('blocks.text');
	expect($translations)->toContain('Text Block');
});

test('BlueprintExtractor handles * values with KQL expressions', function () {
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
        *: '{{ tt(\"item\", \"items\", 1) }}'
";

	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
	file_put_contents($tempFile, $yaml);

	$extractor = new BlueprintExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toContain('Choice');
	expect($translations)->toContain('option.one');
	expect($translations)->toContain('simple.key');
	expect($translations)->toContain('item');
});
