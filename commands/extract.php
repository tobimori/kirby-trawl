<?php

use Kirby\CLI\CLI;
use tobimori\Trawl\Extractor;
use tobimori\Trawl\TranslationManager;

return [
	'description' => 'Extract translation strings from PHP files and blueprints',
	'args' => [
		'clean' => [
			'prefix' => 'clean',
			'longPrefix' => 'clean',
			'description' => 'Remove unused translations from the files',
			'noValue' => true,
		],
		'verbose' => [
			'prefix' => 'v',
			'longPrefix' => 'verbose',
			'description' => 'Show detailed output',
			'noValue' => true,
		],
	],
	'command' => static function (CLI $cli): void {
		$cli->info('Starting translation extraction...');

		// Get plugin options
		$options = kirby()->option('tobimori.trawl', []);

		// Show current configuration
		$cli->out('Configuration:');
		$cli->out('  Source language: ' . ($options['sourceLanguage'] ?? 'none'));
		$cli->out('  Output format: ' . ($options['outputFormat'] ?? 'json'));
		$cli->out('  Output path: ' . ($options['outputPath'] ?? 'site/translations'));
		$cli->out('  Languages: ' . implode(', ', $options['languages'] ?? ['en', 'de']));
		$cli->br();

		try {
			// Create extractor and extract translations
			$extractor = new Extractor($options);
			$translations = $extractor->extract();

			// Show extraction stats
			$stats = $extractor->getStats($translations);
			$cli->success("Found {$stats['total']} translation strings ({$stats['unique']} unique)");

			if ($cli->arg('verbose')) {
				$cli->out('Files scanned: ' . count($stats['byFile']));
				foreach ($stats['byFile'] as $file => $count) {
					$cli->out('  - ' . basename($file) . ": $count");
				}
			}

			if (!empty($stats['byType'])) {
				$cli->out('By type:');
				foreach ($stats['byType'] as $type => $count) {
					$cli->out("  - $type: $count");
				}
			}

			// Generate translation files
			$cli->br();
			$cli->info('Generating translation files...');

			$manager = new TranslationManager($options);

			// Check if clean flag is set
			if ($cli->arg('clean')) {
				$cli->info('Cleaning mode enabled - removing unused translations');
				$generatedFiles = $manager->generateTranslationsClean($translations);
			} else {
				$generatedFiles = $manager->generateTranslations($translations);
			}

			foreach ($generatedFiles as $language => $file) {
				$cli->success("Generated: $file");
			}

			// Check for missing translations
			$missing = $manager->getMissingTranslations($translations);
			if (!empty($missing)) {
				$cli->br();
				$cli->warning('Missing translations:');
				foreach ($missing as $language => $keys) {
					$cli->out("$language: " . count($keys) . ' missing');
					if ($cli->arg('verbose')) {
						foreach (array_slice($keys, 0, 10) as $key) {
							$cli->out("  - $key");
						}
						if (count($keys) > 10) {
							$cli->out('  ... and ' . (count($keys) - 10) . ' more');
						}
					}
				}
			}

			// Check for unused translations
			$unused = $manager->getUnusedTranslations($translations);
			if (!empty($unused)) {
				$cli->br();
				$cli->warning('Unused translations:');
				foreach ($unused as $language => $keys) {
					$cli->out("$language: " . count($keys) . ' unused');
					if ($cli->arg('verbose')) {
						foreach (array_slice($keys, 0, 10) as $key) {
							$cli->out("  - $key");
						}
						if (count($keys) > 10) {
							$cli->out('  ... and ' . (count($keys) - 10) . ' more');
						}
					}
				}
			}

			$cli->br();
			$cli->success('Translation extraction completed!');
		} catch (\Exception $e) {
			$cli->error('Extraction failed: ' . $e->getMessage());
			exit(1);
		}
	}
];
