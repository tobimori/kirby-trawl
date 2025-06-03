# Kirby Trawl

Trawl is an experimental Kirby plugin for extracting translation strings from Kirby templates and content files.

It's made to be combined with an AI localization tool like [Lingo.dev](https://lingo.dev/en) to automate the process of translating your blueprints and templates.

You can find an example implementation of Trawl + Lingo using GitHub Actions CI/CD in the [Baukasten](https://github.com/tobimori/kirby-baukasten) repository.

## Setup Guide

### 1. Install Kirby CLI

```bash
composer global require getkirby/cli
```

Add composer bin directory to your shell profile:

```bash
# Find your global composer directory
composer -n config --global home

# Add to ~/.bash_profile (macOS) or ~/.bashrc (Linux)
export PATH=~/.composer/vendor/bin:$PATH
```

Verify installation:

```bash
kirby
```

### 2. Install Plugin

```bash
composer require tobimori/kirby-trawl
```

### 3. Configure

Add to your `config.php`:

```php
return [
  'tobimori.trawl' => [
    'sourceLanguage' => 'en',        // Source language for extraction (leave empty to output just keys)
    'languages' => ['de', 'en']      // Languages to validate
  ],
];
```

These config options serve as defaults for the CLI commands. You can override them with command flags when needed. For example, if `sourceLanguage` is empty, the extract command will output translation keys without values.

### 4. Update Language Files

Load translations from JSON files in your language files:

```php
<?php

use Kirby\Data\Json;

return [
  'code' => 'de',
  'default' => true,
  'direction' => 'ltr',
  'locale' => [
    'LC_ALL' => 'de_DE'
  ],
  'name' => 'Deutsch',
  'translations' => Json::read(__DIR__ . '/../translations/de.json'),
  'url' => ''
];
```

### 5. Extract Translations

Run the extract command to generate translation files:

```bash
php kirby trawl:extract --output=site/translations/en.json --format=json
```

### 6. Add to CI/CD

Add Trawl to your build process to keep translations in sync. Check the [Baukasten](https://github.com/tobimori/kirby-baukasten) repository for a complete GitHub Actions example.

## Commands

- `trawl:extract` - Extract translation strings to JSON/YAML/PHP
- `trawl:validate` - Check for missing or unused translations
- `trawl:migrate` - Rename translation keys across your codebase (to what's in your source language file)

## What Gets Extracted

- Blueprint fields: `label`, `title`, `help`, `placeholder`, `empty`, `info`, `text`, `description`, `confirm`
- Language variables: `*: translation.key` in options
- KQL expressions: `{{ t("key") }}`, `{{ tc("key") }}`, `{{ tt("key") }}`
- PHP functions: `t()`, `tc()`, `tt()`

## License

[MIT License](./LICENSE)
Copyright © 2025 Tobias Möritz
