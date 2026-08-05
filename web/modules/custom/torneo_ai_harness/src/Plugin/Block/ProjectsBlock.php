<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_harness\Plugin\Block;

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
        $this->externalLink('GitHub', $definition['github']),
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
        'description' => $definition['description'],
        'icon' => $definition['icon'],
        'main_url' => $definition['drupal'] ?? $definition['github'],
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
        'description' => 'Connect Drupal AI to xAI models for chat, vision, image and video generation, classification, moderation, speech, and hosted tools.',
        'icon' => 'G',
        'github' => 'https://github.com/Torneoz/grok_ai_provider',
        'drupal' => 'https://www.drupal.org/project/grok',
        'settings' => 'grok.settings_form',
        'ui' => 'ai_api_explorer.list_page',
        'ui_label' => 'API Explorer',
      ],
      [
        'title' => 'AI Image Studio',
        'description' => 'Generate, refine, compare, and publish AI images and videos through a conversational Drupal workspace with usage and cost metadata.',
        'icon' => 'I',
        'github' => 'https://github.com/Torneoz/ai_image_studio',
        'drupal' => 'https://www.drupal.org/project/ai_image_studio',
        'settings' => 'ai_image_studio.settings',
        'ui' => 'entity.ai_image_studio_session.collection',
        'ui_label' => 'Open Studio',
      ],
      [
        'title' => 'Drupal AI Test Harness',
        'description' => 'Rebuild a complete Drupal CMS AI environment with packaged providers, assistants, agents, chatbot configuration, API Explorer, and diagnostics.',
        'icon' => 'T',
        'github' => 'https://github.com/Torneoz/install-torneo-ddev',
        'settings' => 'ai.settings.menu',
        'ui' => 'ai_api_explorer.list_page',
        'ui_label' => 'API Explorer',
      ],
      [
        'title' => 'AI Costs',
        'description' => 'Track provider-neutral model pricing, estimate request costs, and record usage metadata across Drupal AI integrations.',
        'icon' => '$',
        'github' => 'https://github.com/Torneoz/ai_costs',
        'settings' => 'ai_costs.settings',
      ],
      [
        'title' => 'Grok Collections',
        'description' => 'Manage xAI Collections, bulk-ingest documents, and explore collection search from Drupal through the Grok provider.',
        'icon' => 'C',
        'github' => 'https://github.com/Torneoz/grok_doc',
        'settings' => 'entity.grok_doc_collection.collection',
        'settings_label' => 'Collections',
        'ui' => 'ai_api_explorer.list_page',
        'ui_label' => 'API Explorer',
        'ui_requires' => 'entity.grok_doc_collection.collection',
      ],
    ];
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
