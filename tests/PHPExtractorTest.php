<?php

use tobimori\Trawl\PHPExtractor;

test('PHPExtractor can be instantiated', function () {
	$extractor = new PHPExtractor();
	expect($extractor)->toBeInstanceOf(PHPExtractor::class);
});

test('PHPExtractor can extract t() function calls', function () {
	$code = '<?php echo t("Hello World"); echo t("Welcome"); ?>';
	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(2);
	expect($translations)->toContain('Hello World');
	expect($translations)->toContain('Welcome');
});

test('PHPExtractor can extract tc() function calls', function () {
	$code = '<?php echo tc("page", 1); echo tc("item", 5); ?>';
	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(2);
	expect($translations)->toContain('page');
	expect($translations)->toContain('item');
});

test('PHPExtractor can extract tt() function calls', function () {
	$code = '<?php echo tt("navigation.home"); echo tt("button.save"); ?>';
	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(2);
	expect($translations)->toContain('navigation.home');
	expect($translations)->toContain('button.save');
});

test('PHPExtractor ignores non-string arguments', function () {
	$code = '<?php echo t($variable); echo t(123); echo t("Valid String"); ?>';
	$tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(1);
	expect($translations)->toContain('Valid String');
});

test('PHPExtractor can extract from multiple files', function () {
	$files = [];

	// Create test files
	$files[] = $tempFile1 = sys_get_temp_dir() . '/test1_' . uniqid() . '.php';
	file_put_contents($tempFile1, '<?php echo t("File One"); ?>');

	$files[] = $tempFile2 = sys_get_temp_dir() . '/test2_' . uniqid() . '.php';
	file_put_contents($tempFile2, '<?php echo t("File Two"); ?>');

	$extractor = new PHPExtractor();
	$result = $extractor->extractFromFiles($files);
	$translations = array_column($result, 'key');

	// Cleanup
	foreach ($files as $file) {
		unlink($file);
	}

	expect($translations)->toHaveCount(2);
	expect($translations)->toContain('File One');
	expect($translations)->toContain('File Two');
});

test('PHPExtractor handles empty files gracefully', function () {
	$tempFile = sys_get_temp_dir() . '/empty_' . uniqid() . '.php';
	file_put_contents($tempFile, '');

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toBeArray();
	expect($translations)->toHaveCount(0);
});

test('PHPExtractor handles invalid PHP files gracefully', function () {
	$tempFile = sys_get_temp_dir() . '/invalid_' . uniqid() . '.php';
	file_put_contents($tempFile, '<?php echo "unclosed string');

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toBeArray();
	expect($translations)->toHaveCount(0);
});
