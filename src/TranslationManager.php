<?php

namespace tobimori\Trawl;

use Kirby\Data\Json;
use Kirby\Data\Yaml;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;

class TranslationManager
{
	private string $outputPath;
	private string $outputFormat;
	private string|null $sourceLanguage;
	private array $languages;

	public function __construct(array $options = [])
	{
		$this->outputPath = $options['outputPath'] ?? 'site/translations';
		$this->outputFormat = $options['outputFormat'] ?? 'json';
		$this->sourceLanguage = $options['sourceLanguage'] ?? null;
		$this->languages = $options['languages'] ?? ['en', 'de'];
	}

	public function generateTranslations(array $extractedTranslations): array
	{
		$translationsByKey = $this->groupTranslationsByKey($extractedTranslations);
		$generatedFiles = [];

		// Ensure output directory exists
		if (!Dir::exists($this->outputPath)) {
			Dir::make($this->outputPath);
		}

		foreach ($this->languages as $language) {
			$translations = $this->buildTranslationsForLanguage($translationsByKey, $language);
			$filename = $this->saveTranslations($translations, $language);
			$generatedFiles[$language] = $filename;
		}

		return $generatedFiles;
	}

	public function generateTranslationsClean(array $extractedTranslations): array
	{
		$translationsByKey = $this->groupTranslationsByKey($extractedTranslations);
		$generatedFiles = [];

		// Ensure output directory exists
		if (!Dir::exists($this->outputPath)) {
			Dir::make($this->outputPath);
		}

		foreach ($this->languages as $language) {
			$translations = $this->buildTranslationsForLanguage($translationsByKey, $language);
			$filename = $this->saveTranslationsClean($translations, $language);
			$generatedFiles[$language] = $filename;
		}

		return $generatedFiles;
	}

	private function groupTranslationsByKey(array $translations): array
	{
		$grouped = [];

		foreach ($translations as $translation) {
			$key = $translation['key'];

			if (!isset($grouped[$key])) {
				$grouped[$key] = [
					'key' => $key,
					'occurrences' => [],
					'context' => [],
				];
			}

			$grouped[$key]['occurrences'][] = [
				'file' => $translation['file'] ?? null,
				'line' => $translation['line'] ?? null,
				'function' => $translation['function'] ?? null,
				'path' => $translation['path'] ?? null,
			];

			if (isset($translation['context'])) {
				$grouped[$key]['context'][] = $translation['context'];
			}
		}

		return $grouped;
	}

	private function buildTranslationsForLanguage(array $translationsByKey, string $language): array
	{
		$translations = [];

		foreach ($translationsByKey as $key => $data) {
			if ($this->hasPlural($data)) {
				$translations[$key] = ($language === $this->sourceLanguage)
					? ['', $key]
					: ['', ''];
			} else {
				$translations[$key] = ($language === $this->sourceLanguage) ? $key : '';
			}
		}

		// Sort translations alphabetically by key
		ksort($translations);

		return $translations;
	}

	private function hasPlural(array $data): bool
	{
		foreach ($data['context'] ?? [] as $context) {
			if (!empty($context['plural'])) {
				return true;
			}
		}

		return false;
	}

	private function saveTranslations(array $translations, string $language): string
	{
		$filename = $language . '.' . $this->outputFormat;
		$filepath = $this->outputPath . '/' . $filename;

		// Load existing translations if file exists
		$existingTranslations = $this->loadExistingTranslations($filepath);

		// Merge with existing translations (new keys only)
		foreach ($translations as $key => $value) {
			if (!isset($existingTranslations[$key])) {
				$existingTranslations[$key] = $value;
			}
		}

		// Sort merged translations
		ksort($existingTranslations);

		// Save based on format
		if ($this->outputFormat === 'json') {
			F::write($filepath, Json::encode($existingTranslations, true));
		} else {
			// For YAML, preserve comments if they exist
			if (F::exists($filepath)) {
				$dataWithComments = YamlWithComments::read($filepath);
				// Update with new translations while preserving comment metadata
				foreach ($existingTranslations as $key => $value) {
					$dataWithComments[$key] = $value;
				}
				YamlWithComments::write($filepath, $dataWithComments);
			} else {
				// No existing file, just write normally
				F::write($filepath, Yaml::encode($existingTranslations));
			}
		}

		return $filepath;
	}

	private function saveTranslationsClean(array $translations, string $language): string
	{
		$filename = $language . '.' . $this->outputFormat;
		$filepath = $this->outputPath . '/' . $filename;

		// Load existing translations if file exists
		$existingTranslations = $this->loadExistingTranslations($filepath);

		// In clean mode, only keep translations that are in the extracted set
		$cleanedTranslations = [];
		foreach ($translations as $key => $value) {
			// Use existing value if available and not empty, otherwise use new value
			$cleanedTranslations[$key] = (!empty($existingTranslations[$key])) ? $existingTranslations[$key] : $value;
		}

		// Sort cleaned translations
		ksort($cleanedTranslations);

		// Save based on format
		if ($this->outputFormat === 'json') {
			F::write($filepath, Json::encode($cleanedTranslations, true));
		} else {
			// For YAML, preserve comments if they exist
			if (F::exists($filepath)) {
				$dataWithComments = YamlWithComments::read($filepath);
				// Clear all translations first (clean mode)
				foreach (array_keys($dataWithComments) as $key) {
					if ($key !== '__yaml_comments__') {
						unset($dataWithComments[$key]);
					}
				}
				// Add only the cleaned translations
				foreach ($cleanedTranslations as $key => $value) {
					$dataWithComments[$key] = $value;
				}
				YamlWithComments::write($filepath, $dataWithComments);
			} else {
				// No existing file, just write normally
				F::write($filepath, Yaml::encode($cleanedTranslations));
			}
		}

		return $filepath;
	}

	private function loadExistingTranslations(string $filepath): array
	{
		if (!F::exists($filepath)) {
			return [];
		}

		try {
			if ($this->outputFormat === 'json') {
				return Json::read($filepath);
			} else {
				$data = YamlWithComments::read($filepath);
				// Remove the comment metadata before returning
				unset($data['__yaml_comments__']);
				return $data;
			}
		} catch (\Exception $e) {
			return [];
		}
	}

	public function getMissingTranslations(array $extractedTranslations): array
	{
		$missing = [];
		$translationsByKey = $this->groupTranslationsByKey($extractedTranslations);

		foreach ($this->languages as $language) {
			$filename = $language . '.' . $this->outputFormat;
			$filepath = $this->outputPath . '/' . $filename;
			$existingTranslations = $this->loadExistingTranslations($filepath);

			$missingInLanguage = [];
			foreach ($translationsByKey as $key => $data) {
				if (!isset($existingTranslations[$key])) {
					$missingInLanguage[] = $key;
				} elseif ($existingTranslations[$key] === '') {
					$missingInLanguage[] = $key;
				} elseif (is_array($existingTranslations[$key]) && $existingTranslations[$key] === array_fill(0, count($existingTranslations[$key]), '')) {
					$missingInLanguage[] = $key;
				}
			}

			if (!empty($missingInLanguage)) {
				$missing[$language] = $missingInLanguage;
			}
		}

		return $missing;
	}

	public function getUnusedTranslations(array $extractedTranslations): array
	{
		$unused = [];
		$translationsByKey = $this->groupTranslationsByKey($extractedTranslations);
		$extractedKeys = array_keys($translationsByKey);

		foreach ($this->languages as $language) {
			$filename = $language . '.' . $this->outputFormat;
			$filepath = $this->outputPath . '/' . $filename;
			$existingTranslations = $this->loadExistingTranslations($filepath);

			$unusedInLanguage = [];
			foreach ($existingTranslations as $key => $value) {
				if (!in_array($key, $extractedKeys, true)) {
					$unusedInLanguage[] = $key;
				}
			}

			if (!empty($unusedInLanguage)) {
				$unused[$language] = $unusedInLanguage;
			}
		}

		return $unused;
	}
}
