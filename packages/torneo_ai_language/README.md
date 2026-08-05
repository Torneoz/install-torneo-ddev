# Torneo AI Language

Torneo AI Language is a standalone Drupal module for coordinating language
context across AI-powered Torneoz features.

## Features

This module:

- installs Arabic, German, Spanish, French, Hindi, Japanese, Portuguese,
  Russian, Swahili, and Simplified Chinese on first enable;
- provides a UI for adding any standard Drupal language later;
- exposes the active site and content language to AI consumers;
- provides consistent source and target language context;
- allows Torneoz, AI Image Studio, Grok, and other integrations to share the
  same language policy;
- remains independent of the main `torneo` module and individual AI providers.

It provides an administration form at
`/admin/config/services/torneo-ai-language` and the injectable
`torneo_ai_language.language_policy` service. AI consumers can request a
normalized context containing source and target language codes, names, text
directions, and an optional prompt instruction.

For safety, clearing an already-installed language in this module's settings
does not delete it. Language removal is a global site operation that can affect
translated content and remains an administrator-controlled task.

## Requirements

- Drupal 11.3 or later
- Drupal AI 1.4 or later

## Installation

Install with Composer and enable the module normally:

```shell
composer require torneoz/torneo_ai_language
drush en torneo_ai_language
```

## Usage

Inject `Drupal\torneo_ai_language\LanguagePolicyInterface` into an AI service,
then resolve the active policy:

```php
$context = $this->languagePolicy->getContext();
// $context['source']['langcode'], $context['target']['langcode'],
// and $context['instruction'] are provider-neutral values.
```

## License

GPL-2.0-or-later
