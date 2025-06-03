<?php

use Kirby\CLI\CLI;
use tobimori\Trawl\Extractor;
use tobimori\Trawl\TranslationManager;

return [
	'description' => 'Validate translations and check for missing or unused keys',
	'args' => [],
	'command' => static function (CLI $cli): void {
		$cli->info('Validating translations...');

		// Get plugin options
		$options = kirby()->option('tobimori.trawl', []);

		try {
			// Extract current translations from code
			$extractor = new Extractor($options);
			$translations = $extractor->extract();

			$stats = $extractor->getStats($translations);
			$cli->out("Analyzing {$stats['unique']} unique translation keys...");
			$cli->br();

			$manager = new TranslationManager($options);

			// Check for missing translations
			$missing = $manager->getMissingTranslations($translations);
			$hasIssues = false;

			if (!empty($missing)) {
				$hasIssues = true;
				$cli->error('Missing translations found:');
				foreach ($missing as $language => $keys) {
					$cli->br();
					$cli->out("Language: $language");
					$cli->out("Missing: " . count($keys) . " translations");

					foreach ($keys as $key) {
						$cli->out("  ❌ $key");
					}
				}
			} else {
				$cli->success('✓ All translations are present');
			}

			$cli->br();

			// Check for unused translations
			$unused = $manager->getUnusedTranslations($translations);

			if (!empty($unused)) {
				$hasIssues = true;
				$cli->warning('Unused translations found:');
				foreach ($unused as $language => $keys) {
					$cli->br();
					$cli->out("Language: $language");
					$cli->out("Unused: " . count($keys) . " translations");

					foreach ($keys as $key) {
						$cli->out("  ⚠️  $key");
					}
				}
			} else {
				$cli->success('✓ No unused translations');
			}

			$cli->br();

			if ($hasIssues) {
				$cli->error('Validation completed with issues');
				exit(1);
			} else {
				$cli->success('Validation completed successfully!');
			}
		} catch (\Exception $e) {
			$cli->error('Validation failed: ' . $e->getMessage());
			exit(1);
		}
	}
];
