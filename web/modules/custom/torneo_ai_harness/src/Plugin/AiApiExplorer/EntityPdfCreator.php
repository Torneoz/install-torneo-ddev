<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_harness\Plugin\AiApiExplorer;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
use Drupal\ai_api_explorer\ExplorerHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Explores Grok-authored PDFs grounded in Drupal content entities.
 */
#[AiApiExplorer(
  id: 'grok_entity_pdf_creator',
  title: new TranslatableMarkup('Grok Entity PDF Creator [experimental]'),
  description: new TranslatableMarkup('Draft a document from Drupal entities with Grok, preview the grounded result, and render it locally as a PDF.'),
)]
final class EntityPdfCreator extends AiApiExplorerPluginBase {

  private const ALLOWED_TAGS = [
    'a', 'blockquote', 'br', 'caption', 'cite', 'code', 'dd', 'del', 'div',
    'dl', 'dt', 'em', 'figcaption', 'figure', 'h2', 'h3', 'h4', 'hr', 'ins',
    'li', 'mark', 'ol', 'p', 'pre', 'q', 's', 'small', 'span', 'strong', 'sub',
    'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
  ];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $request_stack,
    AiProviderFormHelper $ai_provider_helper,
    ExplorerHelper $explorer_helper,
    AiProviderPluginManager $provider_manager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PrivateTempStoreFactory $tempStoreFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request_stack, $ai_provider_helper, $explorer_helper, $provider_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('ai.form_helper'),
      $container->get('ai_api_explorer.helper'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return isset($this->providerManager->getProvidersForOperationType('chat', TRUE)['grok']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, 'grok-entity-pdf-response');
    $form['left']['explanation'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Grok authors semantic HTML from entity field values. Drupal renders the final PDF locally; entity data is sent to xAI only when you submit this form.'),
    ];
    $form['left']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->entityTypeOptions(),
      '#default_value' => 'node',
      '#required' => TRUE,
    ];
    $form['left']['entity_ids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity IDs'),
      '#description' => $this->t('Enter up to 20 IDs separated by commas. Only entities you can view are included.'),
      '#required' => TRUE,
    ];
    $form['left']['document_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document title'),
      '#default_value' => $this->t('Drupal entity report'),
      '#maxlength' => 180,
      '#required' => TRUE,
    ];
    $form['left']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Document brief'),
      '#default_value' => $this->t('Create a concise, well-structured report. Preserve important facts, distinguish each source entity, and include a source register at the end.'),
      '#rows' => 5,
      '#required' => TRUE,
    ];
    $form['left']['grok_entity_pdf_ai_provider'] = [
      '#type' => 'hidden',
      '#value' => 'grok',
    ];
    $this->aiProviderHelper->generateAiProvidersForm(
      $form['left'],
      $form_state,
      'chat',
      'grok_entity_pdf',
      AiProviderFormHelper::FORM_CONFIGURATION_FULL,
      0,
      'grok',
    );
    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create PDF draft'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'grok-entity-pdf-response',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      [$grounding, $sources] = $this->buildGrounding(
        (string) $form_state->getValue('entity_type'),
        (string) $form_state->getValue('entity_ids'),
      );
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'chat', 'grok_entity_pdf');
      $title = trim((string) $form_state->getValue('document_title'));
      $brief = trim((string) $form_state->getValue('prompt'));
      $input = new ChatInput([new ChatMessage('user', $brief . "\n\nSOURCE ENTITIES:\n" . $grounding)]);
      $input->setSystemPrompt('Create a publication-ready document grounded only in the supplied Drupal entity data. Return semantic HTML fragments only, without html/body tags, Markdown, scripts, styles, images, or code fences. Never invent missing facts. Use headings, paragraphs, lists, and tables where useful.');
      $output = $provider->chat(
        $input,
        (string) $form_state->getValue('grok_entity_pdf_ai_model'),
        ['grok_entity_pdf_explorer', 'ai_api_explorer'],
      )->getNormalized();
      if (!$output instanceof ChatMessage) {
        throw new \UnexpectedValueException('Grok did not return a document draft.');
      }

      $html = Xss::filter($output->getText(), self::ALLOWED_TAGS);
      $token = bin2hex(random_bytes(16));
      $this->tempStoreFactory->get('torneo_ai_harness.entity_pdf')->set($token, ['title' => $title, 'html' => $html]);
      $form['right']['response']['#context']['ai_response'] = [
        'title' => ['#markup' => '<h2>' . htmlspecialchars($title) . '</h2>'],
        'document' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['ai-text-response']],
          'content' => ['#markup' => $html],
          'download' => [
            '#type' => 'link',
            '#title' => $this->t('Download as PDF'),
            '#url' => Url::fromRoute('torneo_ai_harness.entity_pdf_download', ['token' => $token]),
            '#attributes' => [
              'class' => ['button', 'button--primary'],
              'download' => TRUE,
            ],
            '#prefix' => '<div class="form-actions">',
            '#suffix' => '</div>',
          ],
        ],
        'grounding' => [
          '#type' => 'details',
          '#title' => $this->t('Grounding sent to Grok'),
          'sources' => ['#theme' => 'item_list', '#title' => $this->t('Included entities'), '#items' => $sources],
          'payload' => ['#markup' => '<pre>' . htmlspecialchars($grounding) . '</pre>'],
        ],
      ];
    }
    catch (\Throwable $exception) {
      $form['right']['response']['#context']['ai_response'] = [
        'title' => ['#markup' => '<h3>' . $this->t('PDF creation failed') . '</h3>'],
        'message' => ['#type' => 'html_tag', '#tag' => 'div', '#plain_text' => $exception->getMessage()],
      ];
    }
    $form_state->setRebuild();
    return $form['right'];
  }

  /**
   * Returns content entity types suitable for grounding.
   */
  private function entityTypeOptions(): array {
    $excluded = ['file', 'menu_link_content', 'path_alias', 'shortcut', 'user'];
    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
      if ($definition->getGroup() !== 'content' || in_array($id, $excluded, TRUE)
        || !$definition->getKey('id') || !$definition->getKey('label')) {
        continue;
      }
      $options[$id] = (string) $definition->getCollectionLabel();
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /**
   * Builds a bounded, inspectable text representation of selected entities.
   */
  private function buildGrounding(string $entity_type, string $raw_ids): array {
    if (!isset($this->entityTypeOptions()[$entity_type])) {
      throw new \InvalidArgumentException('Select an available content entity type.');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/\s*,\s*/', trim($raw_ids)) ?: []))));
    if ($ids === [] || count($ids) > 20) {
      throw new \InvalidArgumentException('Enter between 1 and 20 valid entity IDs.');
    }

    $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
    $documents = [];
    $sources = [];
    foreach ($ids as $id) {
      $entity = $entities[$id] ?? NULL;
      if (!$entity instanceof ContentEntityInterface || !$entity->access('view')) {
        continue;
      }
      $lines = ['Entity type: ' . $entity_type, 'Entity ID: ' . $entity->id(), 'Label: ' . $entity->label()];
      foreach ($entity->getFields() as $field_name => $field) {
        $definition = $field->getFieldDefinition();
        if ($field->isEmpty() || !$field->access('view')
          || $definition->isComputed()
          || in_array($field_name, ['revision_log', 'pass'], TRUE)) {
          continue;
        }
        $value = trim($field->getString());
        if ($value !== '') {
          $lines[] = (string) $definition->getLabel() . ': ' . mb_substr($value, 0, 12000);
        }
      }
      $documents[] = '--- SOURCE ' . (count($documents) + 1) . " ---\n" . implode("\n", $lines);
      $sources[] = $entity->label() . ' (' . $entity_type . ':' . $entity->id() . ')';
    }
    if ($documents === []) {
      throw new \InvalidArgumentException('No viewable entities were found for those IDs.');
    }
    return [mb_substr(implode("\n\n", $documents), 0, 120000), $sources];
  }

}
