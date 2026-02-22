<?php

namespace tobimori\Trawl;

use Kirby\Cms\Blueprint;
use Kirby\Data\Yaml;

class BlueprintExtractor
{
	private static array $blueprintCache = [];
	private array $translatableFields = [
		'label',
		'title',
		'help',
		'placeholder',
		'empty',
		'info',
		'text',
		'description',
		'confirm',
	];

	public function extract(string $file): array
	{
		if (!file_exists($file) || !str_ends_with($file, '.yml')) {
			return [];
		}

		try {
			$content = Yaml::read($file);
			return $this->extractFromArray($content, $file);
		} catch (\Exception $e) {
			return [];
		}
	}

	private function extractFromArray(array $data, string $file, string $path = ''): array
	{
		$translations = [];

		foreach ($data as $key => $value) {
			$currentPath = $path ? "$path.$key" : $key;

			// Handle special * key for language variables
			if ($key === '*' && is_string($value)) {
				if (!$this->shouldSkipValue($value)) {
					// Check if this is a KQL expression with translation functions
					if (str_contains($value, '{{') && str_contains($value, '}}')) {
						// Extract strings from t(), tc(), and tt() functions
						$extracted = $this->extractFromKQL($value);
						foreach ($extracted as $extractedKey) {
							$translations[] = [
								'key' => $extractedKey,
								'file' => $file,
								'path' => $currentPath,
								'field' => '*',
								'context' => $this->getContextFromPath($path),
							];
						}
					} else {
						// Regular language variable
						$translations[] = [
							'key' => $value,
							'file' => $file,
							'path' => $currentPath,
							'field' => '*',
							'context' => $this->getContextFromPath($path),
						];
					}
				}
			} elseif (is_string($value) && in_array($key, $this->translatableFields, true)) {
				// Skip empty strings, whitespace-only strings, and KQL expressions
				if (!$this->shouldSkipValue($value)) {
					// Check if this is a KQL expression with translation functions
					if (str_contains($value, '{{') && str_contains($value, '}}')) {
						// Extract strings from t() and tc() functions
						$extracted = $this->extractFromKQL($value);
						foreach ($extracted as $extractedKey) {
							$translations[] = [
								'key' => $extractedKey,
								'file' => $file,
								'path' => $currentPath,
								'field' => $key,
								'context' => $this->getContextFromPath($currentPath),
							];
						}
					} else {
						// Regular translatable string
						$translations[] = [
							'key' => $value,
							'file' => $file,
							'path' => $currentPath,
							'field' => $key,
							'context' => $this->getContextFromPath($currentPath),
						];
					}
				}
			} elseif (is_array($value)) {
				// Recursively extract from nested arrays
				$nestedTranslations = $this->extractFromArray($value, $file, $currentPath);
				$translations = array_merge($translations, $nestedTranslations);
			}
		}

		return $translations;
	}

	private function extractFromKQL(string $value): array
	{
		$translations = [];

		// Extract first string parameter from t(), tc(), and tt() functions
		// All three functions can have additional parameters after the first string
		// Handle both double and single quotes, including escaped quotes
		if (preg_match_all('/\b(?:t|tc|tt)\s*\(\s*"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'/', $value, $matches)) {
			// Combine matches from both quote types and filter out empty ones
			$doubleQuoteMatches = array_filter($matches[1]);
			$singleQuoteMatches = array_filter($matches[2]);
			$allMatches = array_merge($doubleQuoteMatches, $singleQuoteMatches);

			// Unescape the strings
			$unescapedMatches = array_map(function ($str) {
				return stripcslashes($str);
			}, $allMatches);

			$translations = array_merge($translations, $unescapedMatches);
		}

		return array_unique($translations);
	}

	private function shouldSkipValue(string $value): bool
	{
		// Skip empty or whitespace-only strings
		if (trim($value) === '') {
			return true;
		}

		// Check if value contains KQL expressions
		if (str_contains($value, '{{') && str_contains($value, '}}')) {
			// Check if it contains any translation functions (t, tc, or tt)
			if (preg_match('/\b(?:t|tc|tt)\s*\(/', $value)) {
				// This value contains translatable strings, don't skip
				return false;
			}

			// Skip other KQL expressions
			return true;
		}

		// Skip blueprint extends references
		if ($this->isBlueprint($value)) {
			return true;
		}

		return false;
	}

	private function isBlueprint(string $value): bool
	{
		if (!isset(static::$blueprintCache[$value])) {
			try {
				Blueprint::find($value);
				static::$blueprintCache[$value] = true;
			} catch (\Exception) {
				static::$blueprintCache[$value] = false;
			}
		}

		return static::$blueprintCache[$value];
	}

	private function getContextFromPath(string $path): array
	{
		$parts = explode('.', $path);
		$context = [];

		// Determine the type based on path structure
		if (in_array('fields', $parts, true)) {
			$context['type'] = 'field';
		} elseif (in_array('sections', $parts, true)) {
			$context['type'] = 'section';
		} elseif (in_array('tabs', $parts, true)) {
			$context['type'] = 'tab';
		} elseif (in_array('columns', $parts, true)) {
			$context['type'] = 'column';
		} elseif (in_array('blocks', $parts, true)) {
			$context['type'] = 'block';
		}

		return $context;
	}

	public function extractFromFiles(array $files): array
	{
		$allTranslations = [];

		foreach ($files as $file) {
			$fileTranslations = $this->extract($file);
			$allTranslations = array_merge($allTranslations, $fileTranslations);
		}

		return $allTranslations;
	}
}
