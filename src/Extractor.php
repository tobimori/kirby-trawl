<?php

namespace tobimori\Trawl;

class Extractor
{
	private PHPExtractor $phpExtractor;
	private BlueprintExtractor $blueprintExtractor;
	private array $include;
	private array $exclude;

	public function __construct(array $options = [])
	{
		$this->phpExtractor = new PHPExtractor();
		$this->blueprintExtractor = new BlueprintExtractor();
		$this->include = $options['include'] ?? [
			'site/templates/**/*.php',
			'site/snippets/**/*.php',
			'site/models/**/*.php',
			'site/blueprints/**/*.yml',
		];
		$this->exclude = $options['exclude'] ?? [
			'**/node_modules/**',
			'**/vendor/**',
		];
	}

	public function extract(): array
	{
		$files = $this->findFiles();
		$translations = [];

		// Debug: log found files
		if (empty($files)) {
			$debugInfo = "No files found. Searched patterns:\n";
			$root = dirname(kirby()->root('index'));
			foreach ($this->include as $pattern) {
				$resolved = $this->resolvePattern($pattern, $root);
				$debugInfo .= "  - $resolved\n";
			}
			throw new \Exception($debugInfo);
		}

		// Extract from PHP files
		$phpFiles = array_filter($files, fn ($file) => str_ends_with($file, '.php'));
		if (!empty($phpFiles)) {
			$phpTranslations = $this->phpExtractor->extractFromFiles($phpFiles);
			$translations = array_merge($translations, $phpTranslations);
		}

		// Extract from Blueprint files
		$blueprintFiles = array_filter($files, fn ($file) => str_ends_with($file, '.yml'));
		if (!empty($blueprintFiles)) {
			$blueprintTranslations = $this->blueprintExtractor->extractFromFiles($blueprintFiles);
			$translations = array_merge($translations, $blueprintTranslations);
		}

		return $translations;
	}

	private function findFiles(): array
	{
		$files = [];
		$root = dirname(kirby()->root('index')); // Get the project root, not public/

		foreach ($this->include as $pattern) {
			$resolvedPattern = $this->resolvePattern($pattern, $root);
			$matchedFiles = $this->glob($resolvedPattern);

			foreach ($matchedFiles as $file) {
				if ($this->shouldIncludeFile($file)) {
					$files[] = $file;
				}
			}
		}

		return array_unique($files);
	}

	private function resolvePattern(string $pattern, string $root): string
	{
		// Convert glob pattern to absolute path
		if (!str_starts_with($pattern, '/')) {
			$pattern = rtrim($root, '/') . '/' . $pattern;
		}
		return $pattern;
	}

	private function glob(string $pattern): array
	{
		$files = [];

		// Handle ** wildcard
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
			} else {
				throw new \Exception("Base path not found: $basePath");
			}
		} else {
			// Use standard glob for simple patterns
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

		// Simple pattern matching for file extensions
		if (str_starts_with($pattern, '*.')) {
			$extension = substr($pattern, 1);
			return str_ends_with($path, $extension);
		}

		// Convert glob pattern to regex
		$regex = str_replace(
			['*', '?', '.'],
			['.*', '.', '\.'],
			$pattern
		);

		return preg_match('/^' . $regex . '$/', $path) === 1;
	}

	private function shouldIncludeFile(string $file): bool
	{
		foreach ($this->exclude as $excludePattern) {
			if ($this->matchesExcludePattern($file, $excludePattern)) {
				return false;
			}
		}
		return true;
	}

	private function matchesExcludePattern(string $file, string $pattern): bool
	{
		// Convert glob pattern to regex
		$regex = str_replace(
			['**/', '*', '?'],
			['.*/', '[^/]*', '.'],
			$pattern
		);

		return preg_match('#' . $regex . '#', $file) === 1;
	}

	public function getStats(array $translations): array
	{
		$stats = [
			'total' => count($translations),
			'byType' => [],
			'byFile' => [],
			'unique' => count(array_unique(array_column($translations, 'key'))),
		];

		foreach ($translations as $translation) {
			// Count by type (PHP function or blueprint field)
			$type = $translation['function'] ?? 'blueprint';
			$stats['byType'][$type] = ($stats['byType'][$type] ?? 0) + 1;

			// Count by file
			$file = $translation['file'] ?? 'unknown';
			$stats['byFile'][$file] = ($stats['byFile'][$file] ?? 0) + 1;
		}

		return $stats;
	}
}
