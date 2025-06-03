<?php

namespace tobimori\Trawl;

use Kirby\Data\Yaml;

class BlueprintExtractor
{
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

			if (is_string($value) && in_array($key, $this->translatableFields, true)) {
				// Skip empty strings, whitespace-only strings, and KQL expressions
				if (!$this->shouldSkipValue($value)) {
					$translations[] = [
						'key' => $value,
						'file' => $file,
						'path' => $currentPath,
						'field' => $key,
						'context' => $this->getContextFromPath($currentPath),
					];
				}
			} elseif (is_array($value)) {
				// Recursively extract from nested arrays
				$nestedTranslations = $this->extractFromArray($value, $file, $currentPath);
				$translations = array_merge($translations, $nestedTranslations);
			}
		}

		return $translations;
	}

	private function shouldSkipValue(string $value): bool
	{
		// Skip empty or whitespace-only strings
		if (trim($value) === '') {
			return true;
		}

		// Skip KQL expressions (contain {{ }})
		if (preg_match('/\{\{.*\}\}/', $value)) {
			return true;
		}

		// Skip values that start with / and contain KQL (like /{{ page.slug }})
		if (str_starts_with($value, '/') && str_contains($value, '{{')) {
			return true;
		}

		return false;
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
