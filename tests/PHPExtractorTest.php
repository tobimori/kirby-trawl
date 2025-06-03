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

test('PHPExtractor can extract from mixed HTML/PHP content', function () {
	$code = '
<!DOCTYPE html>
<html>
<head>
	<title><?php echo t("Page Title"); ?></title>
</head>
<body>
	<h1><?= t("Welcome Message") ?></h1>
	<p>Some regular HTML content</p>
	<p><?php echo t("Description Text"); ?></p>
	<nav>
		<a href="/"><?= t("Home") ?></a>
		<a href="/about"><?php echo t("About Us"); ?></a>
	</nav>
</body>
</html>';
	$tempFile = sys_get_temp_dir() . '/mixed_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(5);
	expect($translations)->toContain('Page Title');
	expect($translations)->toContain('Welcome Message');
	expect($translations)->toContain('Description Text');
	expect($translations)->toContain('Home');
	expect($translations)->toContain('About Us');
});

test('PHPExtractor can extract from PHP blocks within HTML attributes', function () {
	$code = '
<div class="container">
	<img src="/image.jpg" alt="<?php echo t("Image Alt Text"); ?>" />
	<button title="<?= t("Button Tooltip") ?>" data-label="<?php echo t("Button Label"); ?>">
		<?php echo t("Click Me"); ?>
	</button>
	<input placeholder="<?= t("Enter your name") ?>" />
</div>';
	$tempFile = sys_get_temp_dir() . '/attrs_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(5);
	expect($translations)->toContain('Image Alt Text');
	expect($translations)->toContain('Button Tooltip');
	expect($translations)->toContain('Button Label');
	expect($translations)->toContain('Click Me');
	expect($translations)->toContain('Enter your name');
});

test('PHPExtractor handles multiple PHP blocks in single file', function () {
	$code = '
<?php
// Some PHP logic
$user = getUser();
?>
<h1><?php echo t("User Profile"); ?></h1>
<?php if ($user): ?>
	<p><?= t("Welcome Back") ?></p>
	<p><?php echo tt("user.name", ["name" => $user->name]); ?></p>
<?php else: ?>
	<p><?php echo t("Please Login"); ?></p>
<?php endif; ?>
<footer>
	<?php
	// Footer section
	echo t("Copyright Notice");
	echo " | ";
	echo t("Privacy Policy");
	?>
</footer>';
	$tempFile = sys_get_temp_dir() . '/multiple_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(6);
	expect($translations)->toContain('User Profile');
	expect($translations)->toContain('Welcome Back');
	expect($translations)->toContain('user.name');
	expect($translations)->toContain('Please Login');
	expect($translations)->toContain('Copyright Notice');
	expect($translations)->toContain('Privacy Policy');
});

test('PHPExtractor handles Kirby-style template with mixed content', function () {
	$code = '<?php snippet("header") ?>

<main class="page">
	<article>
		<header>
			<h1><?= t($page->title()->or(t("Untitled Page"))) ?></h1>
			<time><?= t("Published on") ?> <?= $page->date()->toDate(t("date.format")) ?></time>
		</header>

		<div class="content">
			<?php foreach ($page->blocks()->toBlocks() as $block): ?>
				<?php if ($block->type() === "heading"): ?>
					<h2><?= t($block->text()) ?></h2>
				<?php elseif ($block->type() === "text"): ?>
					<p><?= $block->text() ?></p>
				<?php endif ?>
			<?php endforeach ?>
		</div>

		<footer>
			<a href="<?= $page->parent()->url() ?>"><?= t("Back to overview") ?></a>
		</footer>
	</article>
</main>

<?php snippet("footer") ?>';
	$tempFile = sys_get_temp_dir() . '/kirby_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(4);
	expect($translations)->toContain('Untitled Page');
	expect($translations)->toContain('Published on');
	expect($translations)->toContain('date.format');
	expect($translations)->toContain('Back to overview');
});

test('PHPExtractor handles concatenated strings in mixed HTML/PHP', function () {
	$code = '
<div>
	<h1><?php echo t("Welcome to " . "our website"); ?></h1>
	<p><?= t("Contact us at: " . "support@example.com") ?></p>
</div>';
	$tempFile = sys_get_temp_dir() . '/concat_' . uniqid() . '.php';
	file_put_contents($tempFile, $code);

	$extractor = new PHPExtractor();
	$result = $extractor->extract($tempFile);
	$translations = array_column($result, 'key');

	unlink($tempFile);

	expect($translations)->toHaveCount(2);
	expect($translations)->toContain('Welcome to our website');
	expect($translations)->toContain('Contact us at: support@example.com');
});
