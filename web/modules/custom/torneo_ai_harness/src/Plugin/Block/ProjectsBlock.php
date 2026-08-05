<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_harness\Plugin\Block;

use Composer\InstalledVersions;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Displays the projects included in the Torneo AI harness.
 */
#[Block(
  id: 'torneo_ai_harness_projects',
  admin_label: new TranslatableMarkup('Torneo AI projects'),
  category: new TranslatableMarkup('Torneo AI'),
)]
final class ProjectsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ProjectsBlock.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
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
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $projects = [];
    foreach ($this->definitions() as $definition) {
      $links = [
        $this->externalLink($definition['repository_label'] ?? 'Repository', $definition['repository']),
      ];
      if (isset($definition['drupal'])) {
        $links[] = $this->externalLink('Drupal.org', $definition['drupal']);
      }
      if (isset($definition['settings'])) {
        $link = $this->routeLink(
          $definition['settings_label'] ?? 'Settings',
          $definition['settings'],
        );
        if ($link !== NULL) {
          $links[] = $link;
        }
      }
      if (isset($definition['ui'])) {
        $link = $this->routeLink(
          $definition['ui_label'] ?? 'Open UI',
          $definition['ui'],
          $definition['ui_requires'] ?? NULL,
        );
        if ($link !== NULL) {
          $links[] = $link;
        }
      }

      $projects[] = [
        'title' => $definition['title'],
        'version' => $this->installedVersion($definition['package']),
        'description' => $definition['description'],
        'icon' => $definition['icon'],
        'main_url' => $definition['drupal'] ?? $definition['repository'],
        'links' => $links,
      ];
    }

    return [
      '#theme' => 'torneo_ai_harness_projects',
      '#projects' => $projects,
      '#attached' => [
        'library' => ['torneo_ai_harness/projects'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Returns project metadata and applicable local destinations.
   */
  private function definitions(): array {
    return [
      [
        'title' => 'Grok Integration',
        'package' => 'drupal/grok',
        'description' => 'Connect Drupal AI to xAI models for chat, vision, image and video generation, classification, moderation, speech, and hosted tools.',
        'icon' => 'grok',
        'repository' => 'https://git.drupalcode.org/project/grok',
        'drupal' => 'https://www.drupal.org/project/grok',
        'settings' => 'grok.settings_form',
        'ui' => 'ai_api_explorer.list_page',
        'ui_label' => 'API Explorer',
      ],
      [
        'title' => 'AI Image Studio',
        'package' => 'drupal/ai_image_studio',
        'description' => 'Generate, refine, compare, and publish AI images and videos through a conversational Drupal workspace with usage and cost metadata.',
        'icon' => 'image',
        'repository' => 'https://git.drupalcode.org/project/ai_image_studio',
        'drupal' => 'https://www.drupal.org/project/ai_image_studio',
        'settings' => 'ai_image_studio.settings',
        'ui' => 'entity.ai_image_studio_session.collection',
        'ui_label' => 'Open Studio',
      ],
      [
        'title' => 'Torneo AI Test Harness',
        'package' => 'torneoz/drupal-ai-test-harness',
        'description' => 'Rebuild a complete Drupal CMS AI environment with packaged providers, assistants, agents, chatbot configuration, API Explorer, and diagnostics.',
        'icon' => 'harness',
        'repository' => 'https://github.com/Torneoz/install-torneo-ddev',
        'repository_label' => 'GitHub',
        'settings' => 'ai.settings.menu',
        'ui' => 'ai_api_explorer.list_page',
        'ui_label' => 'API Explorer',
      ],
      [
        'title' => 'AI Costs',
        'package' => 'drupal/ai_costs',
        'description' => 'Track provider-neutral model pricing, estimate request costs, and record usage metadata across Drupal AI integrations.',
        'icon' => 'costs',
        'repository' => 'https://git.drupalcode.org/project/ai_costs',
        'drupal' => 'https://www.drupal.org/project/ai_costs',
        'settings' => 'ai_costs.settings',
      ],
      [
        'title' => 'Grok Collections',
        'package' => 'torneoz/grok_doc',
        'description' => 'Manage xAI Collections, bulk-ingest documents, and explore collection search from Drupal through the Grok provider.',
        'icon' => 'collections',
        'repository' => 'https://github.com/Torneoz/grok_doc',
        'repository_label' => 'GitHub',
        'drupal' => 'https://www.drupal.org/project/grok',
        'settings' => 'entity.grok_doc_collection.collection',
        'settings_label' => 'Collections',
        'ui' => 'ai_api_explorer.list_page',
        'ui_label' => 'API Explorer',
        'ui_requires' => 'entity.grok_doc_collection.collection',
      ],
    ];
  }

  /**
   * Returns the installed Composer version or a clear availability label.
   */
  private function installedVersion(string $package): string {
    if (!InstalledVersions::isInstalled($package)) {
      return 'Not installed';
    }

    return InstalledVersions::getPrettyVersion($package) ?? 'Installed';
  }

  /**
   * Builds an external footer link.
   */
  private function externalLink(string $label, string $uri): array {
    return [
      'label' => $label,
      'url' => $uri,
      'external' => TRUE,
    ];
  }

  /**
   * Builds an internal link only when its route exists and is accessible.
   */
  private function routeLink(string $label, string $routeName, ?string $requiredRoute = NULL): ?array {
    try {
      if ($requiredRoute !== NULL) {
        Url::fromRoute($requiredRoute)->toString();
      }
      $url = Url::fromRoute($routeName);
      if (!$url->access($this->currentUser)) {
        return NULL;
      }
      return [
        'label' => $label,
        'url' => $url->toString(),
        'external' => FALSE,
      ];
    }
    catch (RouteNotFoundException) {
      return NULL;
    }
  }

}
