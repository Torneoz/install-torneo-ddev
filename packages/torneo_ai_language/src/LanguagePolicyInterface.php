<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_language;

use Drupal\Core\Language\LanguageInterface;

/**
 * Resolves the shared language context used by Torneo AI integrations.
 */
interface LanguagePolicyInterface {

  /**
   * Returns the resolved source language.
   */
  public function getSourceLanguage(?string $langcode = NULL): LanguageInterface;

  /**
   * Returns the resolved target language.
   */
  public function getTargetLanguage(?string $langcode = NULL): LanguageInterface;

  /**
   * Returns source and target language metadata for an AI operation.
   *
   * @return array{source: array{langcode: string, name: string, direction: string}, target: array{langcode: string, name: string, direction: string}, instruction: string}
   *   The normalized language context.
   */
  public function getContext(?string $sourceLangcode = NULL, ?string $targetLangcode = NULL): array;

  /**
   * Builds a concise instruction suitable for appending to an AI prompt.
   */
  public function getPromptInstruction(?string $sourceLangcode = NULL, ?string $targetLangcode = NULL): string;

}

