<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_language\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the shared language policy for AI consumers.
 */
final class TorneoAiLanguageSettingsForm extends ConfigFormBase {

  public function __construct(
    \Drupal\Core\Config\ConfigFactoryInterface $configFactory,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'torneo_ai_language_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['torneo_ai_language.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('torneo_ai_language.settings');
    $installed_languages = $this->languageManager->getLanguages();
    $languages = [];
    foreach ($installed_languages as $langcode => $language) {
      $languages[$langcode] = $language->getName() . " ({$langcode})";
    }

    $available_languages = [];
    foreach (LanguageManager::getStandardLanguageList() as $langcode => [$name]) {
      if (in_array($langcode, [
        LanguageInterface::LANGCODE_NOT_SPECIFIED,
        LanguageInterface::LANGCODE_NOT_APPLICABLE,
      ], TRUE)) {
        continue;
      }
      $available_languages[$langcode] = $this->t('@name (@langcode)', [
        '@name' => $name,
        '@langcode' => $langcode,
      ]);
    }
    natcasesort($available_languages);

    $form['languages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site languages'),
    ];
    $form['languages']['enabled_languages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Languages to enable'),
      '#options' => $available_languages,
      '#default_value' => array_keys($installed_languages),
      '#description' => $this->t('Selected languages that are not already installed will be added. Clearing an existing language does not remove it, because language removal can affect translated site content.'),
    ];

    $source_options = [
      'content' => $this->t('Current content language'),
      'interface' => $this->t('Current interface language'),
      'site_default' => $this->t('Site default language'),
      'fixed' => $this->t('A fixed language'),
    ];
    $target_options = ['source' => $this->t('Same as source language')] + $source_options;

    $form['policy'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Language policy'),
    ];
    $form['policy']['source_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Source language'),
      '#options' => $source_options,
      '#default_value' => $config->get('source_language') ?: 'content',
    ];
    $form['policy']['fixed_source_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Fixed source language'),
      '#options' => $languages,
      '#default_value' => $config->get('fixed_source_language') ?: 'en',
      '#states' => [
        'visible' => [':input[name="source_language"]' => ['value' => 'fixed']],
      ],
    ];
    $form['policy']['target_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Target language'),
      '#options' => $target_options,
      '#default_value' => $config->get('target_language') ?: 'source',
    ];
    $form['policy']['fixed_target_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Fixed target language'),
      '#options' => $languages,
      '#default_value' => $config->get('fixed_target_language') ?: 'en',
      '#states' => [
        'visible' => [':input[name="target_language"]' => ['value' => 'fixed']],
      ],
    ];
    $form['policy']['fallback_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Fallback language'),
      '#options' => $languages,
      '#default_value' => $config->get('fallback_language') ?: 'en',
      '#description' => $this->t('Used when a requested or fixed language is not available.'),
    ];
    $form['policy']['include_prompt_instruction'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate an explicit language instruction for AI prompts'),
      '#default_value' => $config->get('include_prompt_instruction') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected_languages = array_values(array_filter(
      $form_state->getValue('enabled_languages') ?? [],
    ));
    foreach ($selected_languages as $langcode) {
      if (!ConfigurableLanguage::load($langcode)) {
        ConfigurableLanguage::createFromLangcode($langcode)->save();
      }
    }

    // Existing languages are deliberately retained even if their boxes were
    // cleared. Store the complete set that exists after this submission.
    $enabled_languages = array_keys($this->languageManager->getLanguages());

    $this->configFactory->getEditable('torneo_ai_language.settings')
      ->set('enabled_languages', $enabled_languages)
      ->set('source_language', $form_state->getValue('source_language'))
      ->set('target_language', $form_state->getValue('target_language'))
      ->set('fixed_source_language', $form_state->getValue('fixed_source_language'))
      ->set('fixed_target_language', $form_state->getValue('fixed_target_language'))
      ->set('fallback_language', $form_state->getValue('fallback_language'))
      ->set('include_prompt_instruction', (bool) $form_state->getValue('include_prompt_instruction'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
