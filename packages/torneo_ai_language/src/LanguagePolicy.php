<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_language;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Resolves shared source and target languages for AI consumers.
 */
final class LanguagePolicy implements LanguagePolicyInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getSourceLanguage(?string $langcode = NULL): LanguageInterface {
    if ($langcode !== NULL) {
      return $this->resolveLangcode($langcode);
    }

    $config = $this->configFactory->get('torneo_ai_language.settings');
    return $this->resolveStrategy(
      (string) ($config->get('source_language') ?: 'content'),
      (string) ($config->get('fixed_source_language') ?: 'en'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLanguage(?string $langcode = NULL): LanguageInterface {
    if ($langcode !== NULL) {
      return $this->resolveLangcode($langcode);
    }

    $config = $this->configFactory->get('torneo_ai_language.settings');
    $strategy = (string) ($config->get('target_language') ?: 'source');
    if ($strategy === 'source') {
      return $this->getSourceLanguage();
    }

    return $this->resolveStrategy(
      $strategy,
      (string) ($config->get('fixed_target_language') ?: 'en'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(?string $sourceLangcode = NULL, ?string $targetLangcode = NULL): array {
    $source = $this->getSourceLanguage($sourceLangcode);
    $target = $this->resolveTargetForSource($source, $targetLangcode);

    return [
      'source' => $this->normalize($source),
      'target' => $this->normalize($target),
      'instruction' => $this->getPromptInstruction($source->getId(), $target->getId()),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPromptInstruction(?string $sourceLangcode = NULL, ?string $targetLangcode = NULL): string {
    if ($this->configFactory->get('torneo_ai_language.settings')->get('include_prompt_instruction') === FALSE) {
      return '';
    }

    $source = $this->getSourceLanguage($sourceLangcode);
    $target = $this->resolveTargetForSource($source, $targetLangcode);

    if ($source->getId() === $target->getId()) {
      return sprintf('Respond in %s (%s).', $target->getName(), $target->getId());
    }

    return sprintf(
      'Interpret the source as %s (%s) and respond in %s (%s).',
      $source->getName(),
      $source->getId(),
      $target->getName(),
      $target->getId(),
    );
  }

  /**
   * Resolves a configured language strategy.
   */
  private function resolveStrategy(string $strategy, string $fixedLangcode): LanguageInterface {
    return match ($strategy) {
      'interface' => $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE),
      'site_default' => $this->languageManager->getDefaultLanguage(),
      'fixed' => $this->resolveLangcode($fixedLangcode),
      default => $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT),
    };
  }

  /**
   * Resolves a target while preserving an operation-level source override.
   */
  private function resolveTargetForSource(LanguageInterface $source, ?string $langcode): LanguageInterface {
    if ($langcode !== NULL) {
      return $this->resolveLangcode($langcode);
    }

    $strategy = (string) ($this->configFactory
      ->get('torneo_ai_language.settings')
      ->get('target_language') ?: 'source');

    return $strategy === 'source' ? $source : $this->getTargetLanguage();
  }

  /**
   * Resolves a language code, falling back safely when it is unavailable.
   */
  private function resolveLangcode(string $langcode): LanguageInterface {
    $language = $this->languageManager->getLanguage($langcode);
    if ($language !== NULL) {
      return $language;
    }

    $fallback = (string) ($this->configFactory
      ->get('torneo_ai_language.settings')
      ->get('fallback_language') ?: 'en');

    return $this->languageManager->getLanguage($fallback)
      ?? $this->languageManager->getDefaultLanguage();
  }

  /**
   * Converts a language object into provider-neutral metadata.
   *
   * @return array{langcode: string, name: string, direction: string}
   *   Normalized language metadata.
   */
  private function normalize(LanguageInterface $language): array {
    return [
      'langcode' => $language->getId(),
      'name' => $language->getName(),
      'direction' => $language->getDirection(),
    ];
  }

}
