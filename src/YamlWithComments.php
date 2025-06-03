<?php

namespace tobimori\Trawl;

use Kirby\Data\Yaml;
use Kirby\Filesystem\F;

class YamlWithComments
{
    /**
     * Read YAML file and preserve comments
     */
    public static function read(string $file): array
    {
        $content = F::read($file);
        $data = Yaml::decode($content);
        
        // Store the original content with comments
        $data['__yaml_comments__'] = self::extractComments($content);
        
        return $data;
    }
    
    /**
     * Write YAML file with preserved comments
     */
    public static function write(string $file, array $data): void
    {
        $comments = $data['__yaml_comments__'] ?? [];
        unset($data['__yaml_comments__']);
        
        // If we have preserved comments, reconstruct the file with them
        if (!empty($comments)) {
            $output = self::reconstructWithComments($data, $comments);
        } else {
            // No comments to preserve, just encode normally
            $output = Yaml::encode($data);
        }
        
        F::write($file, $output);
    }
    
    /**
     * Extract comments and their positions from YAML content
     */
    private static function extractComments(string $content): array
    {
        $lines = explode("\n", $content);
        $comments = [];
        $currentSection = null;
        $lineNumber = 0;
        
        foreach ($lines as $line) {
            $lineNumber++;
            $trimmed = trim($line);
            
            // Section comment (starts with # and is not indented)
            if (str_starts_with($trimmed, '#') && $line[0] === '#') {
                $currentSection = [
                    'text' => $trimmed,
                    'line' => $lineNumber,
                    'type' => 'section',
                    'keys' => []
                ];
                $comments[] = $currentSection;
            }
            // Key-value line
            elseif (str_contains($line, ':') && !str_starts_with($trimmed, '#')) {
                $key = trim(explode(':', $line)[0]);
                if ($currentSection !== null) {
                    $currentSection['keys'][] = $key;
                }
            }
        }
        
        return $comments;
    }
    
    /**
     * Reconstruct YAML with comments
     */
    private static function reconstructWithComments(array $data, array $comments): string
    {
        $output = [];
        $processedKeys = [];
        
        // Group keys by their comment sections
        $keyToSection = [];
        foreach ($comments as $section) {
            foreach ($section['keys'] as $key) {
                $keyToSection[$key] = $section;
            }
        }
        
        // Process sections in order
        foreach ($comments as $section) {
            if (!empty($output)) {
                $output[] = ''; // Empty line before section
            }
            
            $output[] = $section['text'];
            
            // Add all keys that belong to this section
            foreach ($section['keys'] as $key) {
                if (isset($data[$key])) {
                    $value = $data[$key];
                    if (is_string($value)) {
                        // Handle quoting for special characters
                        if (preg_match('/[:#@|>]/', $value) || $value === '') {
                            $value = "'" . str_replace("'", "''", $value) . "'";
                        }
                        $output[] = "$key: $value";
                    } else {
                        $output[] = "$key: " . Yaml::encode($value);
                    }
                    $processedKeys[] = $key;
                }
            }
        }
        
        // Add any remaining keys that weren't in sections
        $remainingKeys = array_diff(array_keys($data), $processedKeys);
        if (!empty($remainingKeys)) {
            if (!empty($output)) {
                $output[] = '';
            }
            
            foreach ($remainingKeys as $key) {
                $value = $data[$key];
                if (is_string($value)) {
                    if (preg_match('/[:#@|>]/', $value) || $value === '') {
                        $value = "'" . str_replace("'", "''", $value) . "'";
                    }
                    $output[] = "$key: $value";
                } else {
                    $output[] = "$key: " . Yaml::encode($value);
                }
            }
        }
        
        return implode("\n", $output);
    }
}