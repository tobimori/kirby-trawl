<?php

use Kirby\CLI\CLI;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use Kirby\Filesystem\F;
use tobimori\Trawl\Extractor;
use tobimori\Trawl\Migrator;

return [
	'description' => 'Migrate from key-based to value-based translations',
	'args' => [
		'force' => [
			'prefix' => 'f',
			'longPrefix' => 'force',
			'description' => 'Force migration even with uncommitted changes',
			'noValue' => true,
		],
		'dry-run' => [
			'prefix' => 'd',
			'longPrefix' => 'dry-run',
			'description' => 'Show what would be changed without making changes',
			'noValue' => true,
		],
	],
	'command' => static function (CLI $cli): void {
		$cli->info('Migration from key-based to value-based translations');
		$cli->br();

		// Check git status
		exec('git status --porcelain 2>&1', $output, $returnCode);

		if ($returnCode !== 0) {
			$cli->error('This command requires a git repository.');
			exit(1);
		}

		if (!empty($output) && !$cli->arg('force')) {
			$cli->error('Working tree is not clean. Please commit or stash your changes.');
			$cli->out('Use --force to override this check (not recommended).');
			exit(1);
		}

		$cli->warning('This will convert your existing translation keys to use values as keys.');
		$cli->warning('For example: t("Content") → t("Content")');
		$cli->br();

		try {
			// Get current translation files
			$translationsPath = kirby()->root('site') . '/translations';
			$options = kirby()->option('tobimori.trawl', []);
			$languages = $options['languages'] ?? ['de', 'en'];
			$sourceLanguage = $options['sourceLanguage'] ?? 'en';

			// Create migration map from existing translations
			$migrationMap = [];

			foreach ($languages as $lang) {
				$ymlFile = "$translationsPath/$lang.yml";
				$jsonFile = "$translationsPath/$lang.json";

				$translations = [];
				if (F::exists($ymlFile)) {
					$translations = Yaml::read($ymlFile);
				} elseif (F::exists($jsonFile)) {
					$translations = Json::read($jsonFile);
				}

				foreach ($translations as $key => $value) {
					// Skip non-key translations (no dots)
					if (strpos($key, '.') === false) {
						continue;
					}

					// Use the source language value as the new key
					if ($lang === $sourceLanguage && !empty($value)) {
						$migrationMap[$key] = $value;
					}
				}
			}

			if (empty($migrationMap)) {
				$cli->warning('No translation keys found to migrate.');
				return;
			}

			$cli->out('Found ' . count($migrationMap) . ' translation keys to migrate.');
			$cli->br();

			// Show preview
			$cli->out('Preview of migration:');
			$preview = array_slice($migrationMap, 0, 5, true);
			foreach ($preview as $oldKey => $newKey) {
				$cli->out("  $oldKey → $newKey");
			}
			if (count($migrationMap) > 5) {
				$cli->out('  ... and ' . (count($migrationMap) - 5) . ' more');
			}
			$cli->br();

			if ($cli->arg('dry-run')) {
				$cli->info('Dry run mode - no changes will be made.');
				$cli->br();
			}

			if (!$cli->arg('dry-run') && !$cli->confirm('Proceed with migration?')) {
				$cli->info('Migration cancelled.');
				return;
			}

			// Create migrator instance
			$migrator = new Migrator($migrationMap);

			// Find all PHP and blueprint files
			$extractor = new Extractor($options);
			$affectedFiles = $migrator->findAffectedFiles($options);

			$cli->out('Files to be updated:');
			$cli->out('  PHP files: ' . count($affectedFiles['php']));
			$cli->out('  Blueprint files: ' . count($affectedFiles['blueprint']));
			$cli->br();

			if (!$cli->arg('dry-run')) {
				// Perform migration
				$results = $migrator->migrate($affectedFiles);

				$cli->success('Migration completed!');
				$cli->out('Updated files:');
				$cli->out('  PHP files: ' . $results['php']);
				$cli->out('  Blueprint files: ' . $results['blueprint']);
				$cli->out('  Total replacements: ' . $results['replacements']);

				// Update translation files
				$cli->br();
				$cli->info('Updating translation files...');

				// Run extract with clean to generate new translation files
				try {
					CLI::command('trawl:extract', '--clean');
				} catch (\Exception $e) {
					$cli->warning('Could not automatically update translation files.');
					$cli->info('Run "kirby trawl:extract --clean" manually to update them.');
				}

				$cli->br();
				$cli->success('Migration completed successfully!');
				$cli->info('Please review the changes and test your application.');
			} else {
				// Show what would be changed
				$changes = $migrator->previewChanges($affectedFiles);

				foreach ($changes as $file => $fileChanges) {
					if (!empty($fileChanges)) {
						$cli->out("File: $file");
						foreach ($fileChanges as $change) {
							$cli->out("  Line {$change['line']}: {$change['old']} → {$change['new']}");
						}
						$cli->br();
					}
				}
			}

		} catch (\Exception $e) {
			$cli->error('Migration failed: ' . $e->getMessage());
			exit(1);
		}
	}
];
