<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_harness\Plugin\Block;

use Composer\InstalledVersions;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

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
    private readonly AccountProxyInterface $currentUser,
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
      $container->get('current_user'),
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
        $settings = isset($definition['route'])
          ? $this->localLink($definition['route_label'] ?? 'Settings', $definition['route'])
          : NULL;
        $groups[$group][] = [
          'name' => $definition['name'],
          'machine_name' => $definition['module'],
          'version' => InstalledVersions::getPrettyVersion($definition['package']) ?? 'Installed',
          'url' => $definition['url'],
          'settings' => $settings,
        ];
      }
    }

    return [
      '#theme' => 'torneo_ai_harness_included_modules',
      '#groups' => $groups,
      '#attached' => [
        'library' => ['torneo_ai_harness/projects'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Returns the curated AI module inventory.
   */
  private function definitions(): array {
    return [
      'Drupal AI modules' => [
        ['name' => 'AI Core', 'module' => 'ai', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai', 'route' => 'ai.settings_form'],
        ['name' => 'AI API Explorer', 'module' => 'ai_api_explorer', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai', 'route' => 'ai_api_explorer.list_page', 'route_label' => 'Open'],
        ['name' => 'AI Assistant API', 'module' => 'ai_assistant_api', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai', 'route' => 'entity.ai_assistant.collection', 'route_label' => 'Open'],
        ['name' => 'AI Chatbot', 'module' => 'ai_chatbot', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai'],
        ['name' => 'AI CKEditor', 'module' => 'ai_ckeditor', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai'],
        ['name' => 'AI Observability', 'module' => 'ai_observability', 'package' => 'drupal/ai', 'url' => 'https://www.drupal.org/project/ai', 'route' => 'ai_observability.settings'],
      ],
      'Third-party AI modules' => [
        ['name' => 'AI Agents', 'module' => 'ai_agents', 'package' => 'drupal/ai_agents', 'url' => 'https://www.drupal.org/project/ai_agents', 'route' => 'entity.ai_agent.collection', 'route_label' => 'Open'],
        ['name' => 'AI Costs', 'module' => 'ai_costs', 'package' => 'drupal/ai_costs', 'url' => 'https://www.drupal.org/project/ai_costs', 'route' => 'ai_costs.settings'],
        ['name' => 'AI Metering', 'module' => 'ai_metering', 'package' => 'drupal/ai_metering', 'url' => 'https://www.drupal.org/project/ai_metering', 'route' => 'ai_metering.settings'],
        ['name' => 'AI Budget Control', 'module' => 'ai_budget_control', 'package' => 'drupal/ai_budget_control', 'url' => 'https://www.drupal.org/project/ai_budget_control', 'route' => 'ai_budget_control.dashboard', 'route_label' => 'Open'],
        ['name' => 'AI Usage Limits', 'module' => 'ai_usage_limits', 'package' => 'drupal/ai_usage_limits', 'url' => 'https://www.drupal.org/project/ai_usage_limits', 'route' => 'ai_usage_limits.settings'],
        ['name' => 'AI Dashboard', 'module' => 'ai_dashboard', 'package' => 'drupal/ai_dashboard', 'url' => 'https://www.drupal.org/project/ai_dashboard', 'route' => 'ai.settings.menu', 'route_label' => 'Open'],
        ['name' => 'AI Image Alt Text', 'module' => 'ai_image_alt_text', 'package' => 'drupal/ai_image_alt_text', 'url' => 'https://www.drupal.org/project/ai_image_alt_text', 'route' => 'ai_image_alt_text.settings_form'],
        ['name' => 'AI Image Bulk Alt Text', 'module' => 'ai_image_bulk_alt_text', 'package' => 'drupal/ai_image_alt_text', 'url' => 'https://www.drupal.org/project/ai_image_alt_text', 'route' => 'ai_image_bulk_alt_text.fix_alt_text', 'route_label' => 'Open'],
        ['name' => 'AI Image Studio', 'module' => 'ai_image_studio', 'package' => 'drupal/ai_image_studio', 'url' => 'https://www.drupal.org/project/ai_image_studio', 'route' => 'ai_image_studio.settings'],
        ['name' => 'AI Media Image', 'module' => 'ai_media_image', 'package' => 'drupal/ai_media_image', 'url' => 'https://www.drupal.org/project/ai_media_image', 'route' => 'ai_media_image.settings_form'],
        ['name' => 'OpenAI Provider', 'module' => 'ai_provider_openai', 'package' => 'drupal/ai_provider_openai', 'url' => 'https://www.drupal.org/project/ai_provider_openai', 'route' => 'ai_provider_openai.settings_form'],
        ['name' => 'Anthropic Provider', 'module' => 'ai_provider_anthropic', 'package' => 'drupal/ai_provider_anthropic', 'url' => 'https://www.drupal.org/project/ai_provider_anthropic', 'route' => 'ai_provider_anthropic.settings_form'],
        ['name' => 'Gemini Provider', 'module' => 'gemini_provider', 'package' => 'drupal/gemini_provider', 'url' => 'https://www.drupal.org/project/gemini_provider', 'route' => 'gemini_provider.settings_form'],
        ['name' => 'Grok Integration', 'module' => 'grok', 'package' => 'drupal/grok', 'url' => 'https://www.drupal.org/project/grok', 'route' => 'grok.settings_form'],
        ['name' => 'Grok Documents', 'module' => 'grok_doc', 'package' => 'torneoz/grok_doc', 'url' => 'https://www.drupal.org/project/grok', 'route' => 'grok_doc.settings'],
      ],
    ];
  }

  /**
   * Builds a local link when its route exists and is accessible.
   */
  private function localLink(string $label, string $routeName): ?array {
    try {
      $url = Url::fromRoute($routeName);
      if (!$url->access($this->currentUser)) {
        return NULL;
      }
      return [
        'label' => $label,
        'url' => $url->toString(),
      ];
    }
    catch (RouteNotFoundException) {
      return NULL;
    }
  }

}
