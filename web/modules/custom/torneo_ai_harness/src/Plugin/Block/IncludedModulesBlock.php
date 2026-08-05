<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_harness\Plugin\Block;

use Composer\InstalledVersions;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the Drupal AI and third-party modules included in the harness.
 */
#[Block(
  id: 'torneo_ai_harness_included_modules',
  admin_label: new TranslatableMarkup('Included AI modules'),
  category: new TranslatableMarkup('Torneo AI'),
)]
final class IncludedModulesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an IncludedModulesBlock.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $groups = [];
    foreach ($this->definitions() as $group => $definitions) {
      foreach ($definitions as $definition) {
        if (!$this->moduleHandler->moduleExists($definition['module'])) {
          continue;
        }
        $groups[$group][] = [
          'name' => $definition['name'],
          'machine_name' => $definition['module'],
          'version' => InstalledVersions::getPrettyVersion($definition['package']) ?? 'Installed',
          'url' => $definition['url'],
        ];
      }
    }

    return [
      '#theme' => 'torneo_ai_harness_included_modules',
      '#groups' => $groups,
      '#attached' => [
        'library' => ['torneo_ai_harness/projects'],
      ],
    ];
  }

  /**
   * Returns the curated AI module inventory.
   */
  private function definitions(): array {
    return [
      'Drupal AI modules' => [
        ['name' => 'AI Core', 'module' => 'ai', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai'],
        ['name' => 'AI API Explorer', 'module' => 'ai_api_explorer', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai'],
        ['name' => 'AI Assistant API', 'module' => 'ai_assistant_api', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai'],
        ['name' => 'AI Chatbot', 'module' => 'ai_chatbot', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai'],
        ['name' => 'AI CKEditor', 'module' => 'ai_ckeditor', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai'],
        ['name' => 'AI Observability', 'module' => 'ai_observability', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai'],
      ],
      'Third-party AI modules' => [
        ['name' => 'AI Agents', 'module' => 'ai_agents', 'package' => 'drupal/ai_agents', 'url' => 'https://www.drupal.org/project/ai_agents'],
        ['name' => 'AI Costs', 'module' => 'ai_costs', 'package' => 'drupal/ai_costs', 'url' => 'https://www.drupal.org/project/ai_costs'],
        ['name' => 'AI Dashboard', 'module' => 'ai_dashboard', 'package' => 'drupal/ai_dashboard', 'url' => 'https://www.drupal.org/project/ai_dashboard'],
        ['name' => 'AI Image Alt Text', 'module' => 'ai_image_alt_text', 'package' => 'drupal/ai_image_alt_text', 'url' => 'https://www.drupal.org/project/ai_image_alt_text'],
        ['name' => 'AI Image Bulk Alt Text', 'module' => 'ai_image_bulk_alt_text', 'package' => 'drupal/ai_image_alt_text', 'url' => 'https://www.drupal.org/project/ai_image_alt_text'],
        ['name' => 'AI Image Studio', 'module' => 'ai_image_studio', 'package' => 'drupal/ai_image_studio', 'url' => 'https://www.drupal.org/project/ai_image_studio'],
        ['name' => 'AI Media Image', 'module' => 'ai_media_image', 'package' => 'drupal/ai_media_image', 'url' => 'https://www.drupal.org/project/ai_media_image'],
        ['name' => 'OpenAI Provider', 'module' => 'ai_provider_openai', 'package' => 'drupal/ai_provider_openai', 'url' => 'https://www.drupal.org/project/ai_provider_openai'],
        ['name' => 'Gemini Provider', 'module' => 'gemini_provider', 'package' => 'drupal/gemini_provider', 'url' => 'https://www.drupal.org/project/gemini_provider'],
        ['name' => 'Grok Integration', 'module' => 'grok', 'package' => 'drupal/grok', 'url' => 'https://www.drupal.org/project/grok'],
      ],
    ];
  }

}
