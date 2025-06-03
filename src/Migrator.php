<?php

namespace tobimori\Trawl;

use Kirby\Filesystem\F;

class Migrator
{
	private array $migrationMap;

	public function __construct(array $migrationMap)
	{
		$this->migrationMap = $migrationMap;
	}

	public function findAffectedFiles(array $options): array
	{
		$phpPatterns = [
			'site/templates/**/*.php',
			'site/snippets/**/*.php',
			'site/models/**/*.php',
			'site/plugins/**/*.php',
			'site/config/**/*.php',
		];

		$blueprintPatterns = [
			'site/blueprints/**/*.yml',
		];

		$phpFiles = $this->findFiles($phpPatterns, $options['exclude'] ?? []);
		$blueprintFiles = $this->findFiles($blueprintPatterns, $options['exclude'] ?? []);

		return [
			'php' => $phpFiles,
			'blueprint' => $blueprintFiles,
		];
	}

	private function findFiles(array $patterns, array $exclude): array
	{
		$files = [];
		$root = dirname(kirby()->root('index'));

		foreach ($patterns as $pattern) {
			$resolvedPattern = rtrim($root, '/') . '/' . $pattern;
			$matchedFiles = $this->glob($resolvedPattern);

			foreach ($matchedFiles as $file) {
				if ($this->shouldIncludeFile($file, $exclude)) {
					$files[] = $file;
				}
			}
		}

		return array_unique($files);
	}

	private function glob(string $pattern): array
	{
		$files = [];

		if (str_contains($pattern, '**')) {
			$parts = explode('**/', $pattern, 2);
			$basePath = rtrim($parts[0], '/');
			$filePattern = $parts[1] ?? '*';

			if (is_dir($basePath)) {
				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
					\RecursiveIteratorIterator::LEAVES_ONLY
				);

				foreach ($iterator as $file) {
					if ($file->isFile()) {
						$filePath = $file->getPathname();
						$relativePath = str_replace($basePath . '/', '', $filePath);
						if ($this->matchesWildcard($relativePath, $filePattern)) {
							$files[] = $filePath;
						}
					}
				}
			}
		} else {
			$matched = glob($pattern);
			if ($matched !== false) {
				$files = array_filter($matched, 'is_file');
			}
		}

		return $files;
	}

	private function matchesWildcard(string $path, string $pattern): bool
	{
		if ($pattern === '*' || empty($pattern)) {
			return true;
		}

		if (str_starts_with($pattern, '*.')) {
			$extension = substr($pattern, 1);
			return str_ends_with($path, $extension);
		}

		$regex = str_replace(
			['*', '?', '.'],
			['.*', '.', '\.'],
			$pattern
		);

		return preg_match('/^' . $regex . '$/', $path) === 1;
	}

	private function shouldIncludeFile(string $file, array $exclude): bool
	{
		foreach ($exclude as $excludePattern) {
			$regex = str_replace(
				['**/', '*', '?'],
				['.*/', '[^/]*', '.'],
				$excludePattern
			);

			if (preg_match('#' . $regex . '#', $file) === 1) {
				return false;
			}
		}
		return true;
	}

	public function migrate(array $affectedFiles): array
	{
		$results = [
			'php' => 0,
			'blueprint' => 0,
			'replacements' => 0,
		];

		// Migrate PHP files
		foreach ($affectedFiles['php'] as $file) {
			$count = $this->migratePHPFile($file);
			if ($count > 0) {
				$results['php']++;
				$results['replacements'] += $count;
			}
		}

		// Migrate blueprint files
		foreach ($affectedFiles['blueprint'] as $file) {
			$count = $this->migrateBlueprintFile($file);
			if ($count > 0) {
				$results['blueprint']++;
				$results['replacements'] += $count;
			}
		}

		return $results;
	}

	private function migratePHPFile(string $file): int
	{
		$content = F::read($file);
		if ($content === false) {
			return 0;
		}

		$replacements = 0;

		// Pattern to match t(), tc(), tt() function calls
		$pattern = '/\b(t|tc|tt)\s*\(\s*([\'"])([^\'"]+)\2/';

		$newContent = preg_replace_callback($pattern, function ($matches) use (&$replacements) {
			$function = $matches[1];
			$quote = $matches[2];
			$key = $matches[3];

			// Check if this key should be migrated
			if (isset($this->migrationMap[$key])) {
				$replacements++;
				return $function . '(' . $quote . $this->migrationMap[$key] . $quote;
			}

			return $matches[0];
		}, $content);

		if ($newContent === null) {
			return 0;
		}

		if ($replacements > 0) {
			F::write($file, $newContent);
		}

		return $replacements;
	}

	private function migrateKQLExpression(string $value): string
	{
		// Pattern to match t(), tc(), tt() function calls within KQL
		$pattern = '/\b(t|tc|tt)\s*\(\s*([\'"])([^\'"]+)\2/';

		return preg_replace_callback($pattern, function ($matches) {
			$function = $matches[1];
			$quote = $matches[2];
			$key = $matches[3];

			// Check if this key should be migrated
			if (isset($this->migrationMap[$key])) {
				return $function . '(' . $quote . $this->migrationMap[$key] . $quote;
			}

			return $matches[0];
		}, $value) ?? $value;
	}

	private function migrateBlueprintFile(string $file): int
	{
		try {
			$content = F::read($file);
			if ($content === false) {
				return 0;
			}

			$replacements = 0;
			$translatableFields = [
				'label',
				'title',
				'help',
				'placeholder',
				'empty',
				'info',
				'text',
				'description',
				'confirm',
				'*', // Special key for language variables
			];

			// Process line by line to preserve formatting
			$lines = explode("\n", $content);
			$newLines = [];

			foreach ($lines as $line) {
				$newLine = $line;

				// Check if this line contains a translatable field
				foreach ($translatableFields as $field) {
					// Match field: value pattern with various quote styles
					if (preg_match('/^(\s*' . preg_quote($field, '/') . '\s*:\s*)(.*)$/i', $line, $matches)) {
						$prefix = $matches[1];
						$valuepart = $matches[2];

						// Extract the actual value, handling different quote styles
						$value = null;
						$newValuePart = $valuepart;

						// Double quotes
						if (preg_match('/^"([^"]*)"(.*)$/', $valuepart, $valueMatches)) {
							$value = $valueMatches[1];
							$changed = false;

							// Check if it's a KQL expression with translation functions
							if (str_contains($value, '{{') && str_contains($value, '}}')) {
								$newValue = $this->migrateKQLExpression($value);
								if ($newValue !== $value) {
									$newValuePart = '"' . $newValue . '"' . $valueMatches[2];
									$changed = true;
								}
							} elseif (isset($this->migrationMap[$value])) {
								$newValuePart = '"' . $this->migrationMap[$value] . '"' . $valueMatches[2];
								$changed = true;
							}

						}
						// Single quotes
						elseif (preg_match('/^\'([^\']*)\'(.*)$/', $valuepart, $valueMatches)) {
							$value = $valueMatches[1];
							$changed = false;

							// Check if it's a KQL expression with translation functions
							if (str_contains($value, '{{') && str_contains($value, '}}')) {
								$newValue = $this->migrateKQLExpression($value);
								if ($newValue !== $value) {
									$newValuePart = "'" . $newValue . "'" . $valueMatches[2];
									$changed = true;
								}
							} elseif (isset($this->migrationMap[$value])) {
								$newValuePart = "'" . $this->migrationMap[$value] . "'" . $valueMatches[2];
								$changed = true;
							}

						}
						// No quotes
						elseif (preg_match('/^([^#\n]+?)(\s*#.*)?$/', $valuepart, $valueMatches)) {
							$value = trim($valueMatches[1]);
							$comment = $valueMatches[2] ?? '';
							$changed = false;

							// Check if it's a KQL expression with translation functions
							if (str_contains($value, '{{') && str_contains($value, '}}')) {
								$newValue = $this->migrateKQLExpression($value);
								if ($newValue !== $value) {
									$newValuePart = $newValue . $comment;
									$changed = true;
								}
							} elseif (isset($this->migrationMap[$value])) {
								$newValuePart = $this->migrationMap[$value] . $comment;
								$changed = true;
							}

						}

						// Update the line if changes were made
						if (isset($changed) && $changed) {
							$newLine = $prefix . $newValuePart;
							$replacements++;
						}
						break;
					}
				}

				$newLines[] = $newLine;
			}

			if ($replacements > 0) {
				F::write($file, implode("\n", $newLines));
			}

			return $replacements;
		} catch (\Exception $e) {
			return 0;
		}
	}


	public function previewChanges(array $affectedFiles): array
	{
		$changes = [];

		// Preview PHP file changes
		foreach ($affectedFiles['php'] as $file) {
			$fileChanges = $this->previewPHPFileChanges($file);
			if (!empty($fileChanges)) {
				$changes[$file] = $fileChanges;
			}
		}

		// Preview blueprint file changes
		foreach ($affectedFiles['blueprint'] as $file) {
			$fileChanges = $this->previewBlueprintFileChanges($file);
			if (!empty($fileChanges)) {
				$changes[$file] = $fileChanges;
			}
		}

		return $changes;
	}

	private function previewPHPFileChanges(string $file): array
	{
		$content = F::read($file);
		if ($content === false) {
			return [];
		}

		$changes = [];
		$lines = explode("\n", $content);

		foreach ($lines as $lineNum => $line) {
			$pattern = '/\b(t|tc|tt)\s*\(\s*([\'"])([^\'"]+)\2/';

			if (preg_match_all($pattern, $line, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					$key = $match[3];
					if (isset($this->migrationMap[$key])) {
						$changes[] = [
							'line' => $lineNum + 1,
							'old' => $match[0],
							'new' => $match[1] . '(' . $match[2] . $this->migrationMap[$key] . $match[2],
						];
					}
				}
			}
		}

		return $changes;
	}

	private function previewBlueprintFileChanges(string $file): array
	{
		try {
			$content = F::read($file);
			if ($content === false) {
				return [];
			}

			$changes = [];
			$translatableFields = [
				'label',
				'title',
				'help',
				'placeholder',
				'empty',
				'info',
				'text',
				'description',
				'confirm',
				'*', // Special key for language variables
			];

			$lines = explode("\n", $content);

			foreach ($lines as $lineNum => $line) {
				foreach ($translatableFields as $field) {
					if (preg_match('/^(\s*' . preg_quote($field, '/') . '\s*:\s*)(.*)$/i', $line, $matches)) {
						$prefix = $matches[1];
						$valuepart = $matches[2];

						$value = null;
						$newValuePart = $valuepart;

						// Double quotes
						if (preg_match('/^"([^"]*)"(.*)$/', $valuepart, $valueMatches)) {
							$value = $valueMatches[1];
							$changed = false;

							// Check if it's a KQL expression with translation functions
							if (str_contains($value, '{{') && str_contains($value, '}}')) {
								$newValue = $this->migrateKQLExpression($value);
								if ($newValue !== $value) {
									$newValuePart = '"' . $newValue . '"' . $valueMatches[2];
									$changed = true;
								}
							} elseif (isset($this->migrationMap[$value])) {
								$newValuePart = '"' . $this->migrationMap[$value] . '"' . $valueMatches[2];
								$changed = true;
							}

						}
						// Single quotes
						elseif (preg_match('/^\'([^\']*)\'(.*)$/', $valuepart, $valueMatches)) {
							$value = $valueMatches[1];
							$changed = false;

							// Check if it's a KQL expression with translation functions
							if (str_contains($value, '{{') && str_contains($value, '}}')) {
								$newValue = $this->migrateKQLExpression($value);
								if ($newValue !== $value) {
									$newValuePart = "'" . $newValue . "'" . $valueMatches[2];
									$changed = true;
								}
							} elseif (isset($this->migrationMap[$value])) {
								$newValuePart = "'" . $this->migrationMap[$value] . "'" . $valueMatches[2];
								$changed = true;
							}

						}
						// No quotes
						elseif (preg_match('/^([^#\n]+?)(\s*#.*)?$/', $valuepart, $valueMatches)) {
							$value = trim($valueMatches[1]);
							$comment = $valueMatches[2] ?? '';
							$changed = false;

							// Check if it's a KQL expression with translation functions
							if (str_contains($value, '{{') && str_contains($value, '}}')) {
								$newValue = $this->migrateKQLExpression($value);
								if ($newValue !== $value) {
									$newValuePart = $newValue . $comment;
									$changed = true;
								}
							} elseif (isset($this->migrationMap[$value])) {
								$newValuePart = $this->migrationMap[$value] . $comment;
								$changed = true;
							}

						}

						// Add to changes if a modification was made
						if (isset($changed) && $changed) {
							$changes[] = [
								'line' => $lineNum + 1,
								'old' => trim($line),
								'new' => trim($prefix . $newValuePart),
							];
						}
						break;
					}
				}
			}

			return $changes;
		} catch (\Exception $e) {
			return [];
		}
	}
}
