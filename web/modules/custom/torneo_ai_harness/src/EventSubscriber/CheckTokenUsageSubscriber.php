<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_harness\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai_usage_limits\Exception\AiTokenUsageException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Enforces only explicitly enabled and configured provider token limits.
 */
final class CheckTokenUsageSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the token usage subscriber.
   */
  public function __construct(
    private readonly StateInterface $state,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'checkTokenUsage',
    ];
  }

  /**
   * Checks recorded usage against explicitly enabled provider limits.
   */
  public function checkTokenUsage(PreGenerateResponseEvent $event): void {
    $providerId = $event->getProviderId();
    $usageLimits = $this->configFactory
      ->get('ai_usage_limits.settings')
      ->get('providers.' . $providerId);

    if (!is_array($usageLimits) || empty($usageLimits['enable_limits'])) {
      return;
    }

    $currentUsage = $this->state->get('ai_usage_limits', []);
    $providerUsage = $currentUsage[$providerId] ?? [];

    foreach ($this->getUsages() as $usage) {
      if (!isset($usageLimits[$usage])) {
        continue;
      }

      if (($providerUsage[$usage] ?? 0) > $usageLimits[$usage]) {
        throw new AiTokenUsageException('Token limit reached for ' . $providerId);
      }
    }
  }

  /**
   * Gets the supported token usage counters.
   *
   * @return string[]
   *   The usage counter keys.
   */
  private function getUsages(): array {
    return [
      'input_token_usage',
      'output_token_usage',
      'total_token_usage',
      'cached_token_usage',
      'reasoning_token_usage',
    ];
  }

}
