<?php

namespace Drupal\ai_metering\Controller;

use Drupal\ai_metering\PricingSourcePluginManager;
use Drupal\ai_metering\Service\ModelPricingService;
use Drupal\ai_metering\Service\QuotaManager;
use Drupal\ai_metering\Service\TokenEstimator;
use Drupal\ai_metering\Service\UsageExportService;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides controllers for the AI Metering module.
 */
class AiMeteringController extends ControllerBase implements TrustedCallbackInterface {

  use TopActiveLazyBuilderTrait;

  /**
   * Rows per page for the editor breakdown table.
   */
  private const EDITORS_PER_PAGE = 10;

  /**
   * Constructs an AiMeteringController.
   *
   * @param \Drupal\ai_metering\Service\TokenEstimator $tokenEstimator
   *   The token estimator service.
   * @param \Drupal\ai_metering\Service\QuotaManager $quotaManager
   *   The quota manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $meteringConfigFactory
   *   The config factory.
   * @param \Drupal\ai_metering\Service\ModelPricingService $modelPricingService
   *   The model pricing service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The CSRF token generator.
   * @param \Drupal\ai_metering\PricingSourcePluginManager $pricingSourcePluginManager
   *   The pricing source plugin manager.
   * @param \Drupal\ai_metering\Service\UsageExportService $usageExportService
   *   The usage export service.
   */
  public function __construct(
    protected readonly TokenEstimator $tokenEstimator,
    protected readonly QuotaManager $quotaManager,
    protected readonly ConfigFactoryInterface $meteringConfigFactory,
    protected readonly ModelPricingService $modelPricingService,
    protected readonly StateInterface $state,
    protected readonly CsrfTokenGenerator $csrfToken,
    protected readonly PricingSourcePluginManager $pricingSourcePluginManager,
    protected readonly UsageExportService $usageExportService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai_metering.token_estimator'),
      $container->get('ai_metering.quota_manager'),
      $container->get('config.factory'),
      $container->get('ai_metering.model_pricing'),
      $container->get('state'),
      $container->get('csrf_token'),
      $container->get('plugin.manager.ai_metering.pricing_source'),
      $container->get('ai_metering.usage_export'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyBuildTopActive'];
  }

  /**
   * Hub: overview page linking to all AI Metering admin areas.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  public function hub(): array {
    $config = $this->meteringConfigFactory->get('ai_metering.settings');
    $displayCurrency = $config->get('currency.display') ?? 'USD';

    return [
      '#theme' => 'ai_metering_hub',
      '#display_currency' => $displayCurrency,
      '#personal_usage_url' => Url::fromRoute('ai_metering.personal_usage_tab', ['user' => $this->currentUser()->id()])->toString(),
      '#usage_log_url' => $this->resolveLogBaseUrl(),
      '#cache' => [
        // The personal usage link embeds the current user's uid.
        'contexts' => ['user'],
        'tags' => ['config:ai_metering.settings'],
      ],
    ];
  }

  /**
   * Dashboard: renders the per-editor AI usage report.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  public function dashboard(Request $request): array {
    $monthContext = $this->resolveMonthContext($request);
    $month = $monthContext['month'];
    $sortContext = $this->resolveSortContext($request);

    $data = $this->quotaManager->getDashboardData($month, self::EDITORS_PER_PAGE, $sortContext['sort'], $sortContext['direction']);

    $config = $this->meteringConfigFactory->get('ai_metering.settings');
    $displayCurrency = $config->get('currency.display') ?? 'USD';
    $alertThreshold = (int) ($config->get('alerts.threshold_pct') ?? 75);

    foreach ($data as &$row) {
      $row['cost_display'] = $this->tokenEstimator->formatCost((float) $row['total_cost']);
    }
    unset($row);

    // Computed independently of the (now paginated) $data rows above, via a
    // single un-grouped aggregate query, so the Totals row always reflects
    // every editor's usage that month rather than just the visible page.
    $totals = $this->quotaManager->getMonthTotals($month);
    $totals['cost_display'] = $this->tokenEstimator->formatCost((float) $totals['total_cost']);

    $quotaEditUrls = $this->buildQuotaEditUrls();
    $logBaseUrl = $this->resolveLogBaseUrl();
    $data = $this->attachRowDropbuttons($data, $logBaseUrl);

    return [
      '#theme' => 'ai_metering_dashboard',
      '#rows' => $data,
      '#totals' => $totals,
      '#month_label' => $monthContext['month_label'],
      '#active_month' => $month,
      '#active_year' => $monthContext['year'],
      '#active_mon' => $monthContext['mon'],
      '#available_months' => $monthContext['available_months'],
      '#available_years' => $monthContext['available_years'],
      '#display_currency' => $displayCurrency,
      '#alert_threshold' => $alertThreshold,
      '#system_limits' => $this->getSystemLimits(),
      '#set_quota_url' => $quotaEditUrls['setQuotaUrl'],
      '#set_threshold_url' => $quotaEditUrls['setThresholdUrl'],
      '#log_base_url' => $logBaseUrl,
      '#export_dropbutton' => $this->buildExportDropbutton(),
      '#by_role_url' => Url::fromRoute('ai_metering.dashboard_by_role')->toString(),
      '#top_active' => $this->buildTopActiveElement('ai_metering_top_editors', 'editors'),
      '#sort_links' => $this->buildSortLinks($month, $sortContext['sort'], $sortContext['direction']),
      '#pager' => ['#type' => 'pager'],
      '#attached' => [
        'library' => ['ai_metering/ai_metering_dashboard'],
      ],
      '#cache' => [
        'contexts' => [
          'url.query_args:month',
          'url.query_args:page',
          'url.query_args:sort',
          'url.query_args:dir',
          'user.permissions',
        ],
        'tags' => ['ai_metering_usage'],
        'max_age' => 0,
      ],
    ];
  }

  /**
   * Resolves the active month, its display label, and dropdown options.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array{month: string, year: string, mon: string, month_label: string, available_months: array<int, string>, available_years: array<int, string>}
   *   The resolved month context.
   */
  private function resolveMonthContext(Request $request): array {
    $month = (string) $request->query->get('month', '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
      $month = date('Y-m');
    }

    $available_months = $this->quotaManager->getAvailableMonths();

    $current_month = date('Y-m');
    if (!in_array($current_month, $available_months, TRUE)) {
      array_unshift($available_months, $current_month);
    }

    [$year, $mon] = explode('-', $month);
    $month_label = date('F Y', mktime(0, 0, 0, (int) $mon, 1, (int) $year));

    // Derive sorted unique years for the year dropdown (most recent first).
    // array_filter() guards against any empty/null month_year rows in the DB.
    $available_years = array_values(array_unique(array_filter(array_map(
      fn(string $m): string => explode('-', $m)[0],
      array_filter($available_months),
    ))));
    rsort($available_years);

    return [
      'month' => $month,
      'year' => $year,
      'mon' => $mon,
      'month_label' => $month_label,
      'available_months' => $available_months,
      'available_years' => $available_years,
    ];
  }

  /**
   * Resolves the active sort column and direction from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array{sort: string, direction: string}
   *   'sort' is a key from QuotaManager::SORT_COLUMNS, or '' for the
   *   default; 'direction' is always 'ASC' or 'DESC'.
   */
  private function resolveSortContext(Request $request): array {
    $sort = (string) $request->query->get('sort', '');
    if (!array_key_exists($sort, QuotaManager::SORT_COLUMNS)) {
      $sort = '';
    }
    $direction = strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

    return ['sort' => $sort, 'direction' => $direction];
  }

  /**
   * Builds the dashboard's "Sort by" links.
   *
   * Each link keeps the active month, sets ?sort= to that column, and
   * resets to page 1, since a resort makes the previous page number
   * meaningless. Clicking the already-active column flips its direction
   * instead of resetting it.
   *
   * @param string $month
   *   The active month, preserved on every sort link.
   * @param string $activeSort
   *   The currently active sort key, or '' for the default.
   * @param string $activeDirection
   *   The currently active direction, 'ASC' or 'DESC'.
   *
   * @return array<string, array<string, mixed>>
   *   Keyed by sort column, each with 'label', 'url', 'active', and
   *   'indicator' (an arrow showing the current direction, empty when
   *   this column isn't the active sort).
   */
  private function buildSortLinks(string $month, string $activeSort, string $activeDirection): array {
    $labels = [
      'editor' => $this->t('Editor'),
      'calls' => $this->t('Calls'),
      'cost' => $this->t('Cost'),
      'quota' => $this->t('Quota %'),
    ];

    $links = [];
    foreach ($labels as $key => $label) {
      $isActive = $activeSort === $key;
      // Clicking the active column flips its direction; clicking any other
      // column starts it fresh at DESC (most-active-first is the common case).
      $nextDirection = $isActive && $activeDirection === 'DESC' ? 'ASC' : 'DESC';

      $links[$key] = [
        'label' => $label,
        'active' => $isActive,
        'indicator' => $isActive ? ($activeDirection === 'ASC' ? '▲' : '▼') : '',
        'url' => Url::fromRoute('ai_metering.dashboard', [], [
          'query' => ['month' => $month, 'sort' => $key, 'dir' => $nextDirection],
        ])->toString(),
      ];
    }

    return $links;
  }

  /**
   * Builds CSRF-protected URLs for the dashboard's inline-edit HTMX endpoints.
   *
   * CsrfAccessCheck validates via generateRoutePath() which strips the
   * leading slash (ltrim). Tokens MUST be generated without leading slash
   * to match. See CsrfAccessCheck docblock: "route's path (without leading
   * slash)". Tokens are session-specific; max_age: 0 ensures no cache.
   *
   * @return array{setQuotaUrl: string, setThresholdUrl: string}
   *   The two endpoint URLs.
   */
  private function buildQuotaEditUrls(): array {
    $setQuotaPath = 'admin/ai-metering/set-quota';
    $setThresholdPath = 'admin/ai-metering/set-user-threshold';

    return [
      'setQuotaUrl' => Url::fromRoute('ai_metering.set_quota', [], [
        'query' => ['token' => $this->csrfToken->get($setQuotaPath)],
      ])->toString(),
      'setThresholdUrl' => Url::fromRoute('ai_metering.set_user_threshold', [], [
        'query' => ['token' => $this->csrfToken->get($setThresholdPath)],
      ])->toString(),
    ];
  }

  /**
   * Resolves the per-user usage log's base URL, if the View is available.
   *
   * Guards against the route being absent (Views not installed, or view
   * not deployed) so the dashboard renders safely without Views.
   *
   * @return string|null
   *   The log view's URL, or NULL if the route doesn't exist.
   */
  private function resolveLogBaseUrl(): ?string {
    try {
      return Url::fromRoute('view.ai_usage_log.dashboard')->toString();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Builds the CSV/JSON export dropbutton for the dashboard header.
   *
   * A single dropbutton (core 'operations' element) instead of two separate
   * buttons: same native chevron/toggle used for the per-row Details
   * control below and on the by-role dashboard (#3611743).
   *
   * @return array<string, mixed>
   *   Render array for the dropbutton.
   */
  private function buildExportDropbutton(): array {
    return [
      '#type' => 'operations',
      '#links' => [
        'csv' => [
          'title' => $this->t('Export CSV'),
          'url' => Url::fromRoute('ai_metering.export_csv'),
        ],
        'json' => [
          'title' => $this->t('Export JSON'),
          'url' => Url::fromRoute('ai_metering.export_json'),
        ],
      ],
    ];
  }

  /**
   * Attaches a per-row Details dropbutton to each editor's dashboard row.
   *
   * A core operations element — the exact dropbutton used for row actions
   * on core admin listings (content, users), so Claro and Gin render it
   * pixel-identically to the rest of the admin UI. It bundles the raw call
   * log and the user's personal usage tab (#3610799) without adding a
   * table column.
   *
   * @param array<int, array<string, mixed>> $data
   *   Dashboard rows keyed by uid.
   * @param string|null $logBaseUrl
   *   The usage log's base URL, or NULL if the View is unavailable.
   *
   * @return array<int, array<string, mixed>>
   *   The same rows, each with a 'details_dropbutton' key added.
   */
  private function attachRowDropbuttons(array $data, ?string $logBaseUrl): array {
    foreach ($data as $uid => &$row) {
      $links = [];
      if ($logBaseUrl !== NULL) {
        $links['view_calls'] = [
          'title' => $this->t('View calls'),
          'url' => Url::fromRoute('view.ai_usage_log.dashboard', ['arg_0' => $uid]),
        ];
      }
      $links['personal_view'] = [
        'title' => $this->t('Personal view'),
        'url' => Url::fromRoute('ai_metering.personal_usage_tab', ['user' => $uid]),
      ];
      $row['details_dropbutton'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }
    unset($row);

    return $data;
  }

  /**
   * Reads provider-level limits from ai_usage_limits (if installed).
   *
   * Returns NULL when the companion module is absent so the template can skip
   * the panel entirely. When present, returns an array keyed by provider_id:
   *   - label: human-readable provider name
   *   - total_limit: configured total-token ceiling (0 = not set)
   *   - total_used: tokens consumed in the current rolling window
   *   - pct_used: percentage of the ceiling consumed (0-100)
   *   - retention_days: rolling window length in days
   *   - settings_url: URL to the ai_usage_limits settings form.
   *
   * @return array<string, mixed>|null
   *   Per-provider limit/usage data, or NULL if module absent/unconfigured.
   */
  protected function getSystemLimits(): ?array {
    if (!$this->moduleHandler()->moduleExists('ai_usage_limits')) {
      return NULL;
    }

    $config = $this->meteringConfigFactory->get('ai_usage_limits.settings');
    $providers = $config->get('providers') ?? [];
    $retentionDays = (int) ($config->get('retention_days') ?? 30);
    $currentUsage = $this->state->get('ai_usage_limits', []);

    $result = [];
    foreach ($providers as $providerId => $limits) {
      if (empty($limits['enable_limits'])) {
        continue;
      }
      $totalLimit = (int) ($limits['total_token_usage'] ?? 0);
      $totalUsed = (int) ($currentUsage[$providerId]['total_token_usage'] ?? 0);
      $pct = ($totalLimit > 0) ? min(100, round(($totalUsed / $totalLimit) * 100, 1)) : 0;

      $result[$providerId] = [
        'label' => ucfirst($providerId),
        'total_limit' => $totalLimit,
        'input_limit' => (int) ($limits['input_token_usage'] ?? 0),
        'output_limit' => (int) ($limits['output_token_usage'] ?? 0),
        'total_used' => $totalUsed,
        'input_used' => (int) ($currentUsage[$providerId]['input_token_usage'] ?? 0),
        'output_used' => (int) ($currentUsage[$providerId]['output_token_usage'] ?? 0),
        'pct_used' => $pct,
        'retention_days' => $retentionDays,
        'settings_url' => Url::fromRoute('ai_usage_limits.settings')->toString(),
      ];
    }

    return empty($result) ? NULL : $result;
  }

  /**
   * HTMX endpoint: set a custom monthly budget for one user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   HTML fragment response.
   */
  public function setQuota(Request $request): Response {
    $uid = (int) $request->request->get('uid');
    $budget = (int) $request->request->get('budget');

    if ($uid <= 0 || $budget < 0) {
      return new Response('<span style="color:#dc3545">' . $this->t('Invalid input') . '</span>');
    }

    $this->quotaManager->setUserBudget($uid, $budget);
    return new Response('<span style="color:#198754">' . $this->t('@count tokens/mo saved', ['@count' => number_format($budget)]) . '</span>');
  }

  /**
   * HTMX endpoint: set a per-user alert threshold override.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   HTML fragment response.
   */
  public function setUserThreshold(Request $request): Response {
    $uid = (int) $request->request->get('uid');
    $raw = $request->request->get('alert_threshold');

    if ($uid <= 0) {
      return new Response('<span style="color:#dc3545">' . $this->t('Invalid user') . '</span>');
    }

    $threshold = ($raw !== '' && $raw !== NULL) ? (int) $raw : NULL;
    if ($threshold !== NULL && ($threshold < 1 || $threshold > 100)) {
      return new Response('<span style="color:#dc3545">' . $this->t('Threshold must be 1–100') . '</span>');
    }

    $this->quotaManager->setUserAlertThreshold($uid, $threshold);
    $label = $threshold !== NULL ? $threshold . '%' : $this->t('global default');
    return new Response('<span style="color:#198754">' . $this->t('Alert at @val saved', ['@val' => $label]) . '</span>');
  }

  /**
   * Triggers a pricing sync from the configured source and redirects.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to settings page with a status message.
   */
  public function syncPricing(): RedirectResponse {
    $pluginId = $this->meteringConfigFactory->get('ai_metering.settings')->get('pricing.active_source') ?? 'litellm';
    $sourceLabel = $this->pricingSourcePluginManager->getOptions()[$pluginId] ?? $pluginId;
    $result = $this->modelPricingService->syncFromSource($pluginId);

    if ($result['error']) {
      $this->messenger()->addError($this->t('Pricing sync failed: @error', ['@error' => $result['error']]));
    }
    else {
      $this->messenger()->addStatus($this->t(
        'Synced pricing for @count models from @source. Manual entries were preserved.',
        ['@count' => $result['synced'], '@source' => $sourceLabel],
      ));
    }

    return new RedirectResponse(Url::fromRoute('ai_metering.settings')->toString());
  }

  /**
   * Exports usage data as CSV.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   CSV file response.
   */
  public function exportCsv(): Response {
    return $this->usageExportService->exportCsv();
  }

  /**
   * Exports the filtered raw usage log as JSON.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON array of log rows matching the active filters.
   */
  public function exportLogJson(Request $request): JsonResponse {
    return $this->usageExportService->exportLogJson($request);
  }

  /**
   * Exports usage data as JSON.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON file response.
   */
  public function exportJson(): JsonResponse {
    return $this->usageExportService->exportJson();
  }

}
