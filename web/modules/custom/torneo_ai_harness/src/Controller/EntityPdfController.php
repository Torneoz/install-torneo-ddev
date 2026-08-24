<?php

declare(strict_types=1);

namespace Drupal\torneo_ai_harness\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a temporary Grok-authored document as PDF.
 */
final class EntityPdfController extends ControllerBase {

  public function __construct(
    private readonly PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('tempstore.private'));
  }

  /**
   * Downloads a generated document from the current user's private tempstore.
   */
  public function download(string $token): Response {
    $document = $this->tempStoreFactory
      ->get('torneo_ai_harness.entity_pdf')
      ->get($token);
    if (!is_array($document) || empty($document['html'])) {
      throw new NotFoundHttpException('The generated PDF is unavailable or has expired.');
    }

    $title = trim((string) ($document['title'] ?? 'Grok entity document'));
    $options = new Options();
    $options->set('isRemoteEnabled', FALSE);
    $options->set('isPhpEnabled', FALSE);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml('<!doctype html><html><head><meta charset="utf-8"><style>'
      . 'body{font-family:DejaVu Sans,sans-serif;color:#18212b;font-size:11pt;line-height:1.5}'
      . 'h1,h2,h3{color:#102a43;page-break-after:avoid}table{border-collapse:collapse;width:100%}'
      . 'th,td{border:1px solid #bcccdc;padding:6px;vertical-align:top}a{color:#075985}'
      . 'blockquote{border-left:3px solid #829ab1;margin-left:0;padding-left:12px;color:#486581}'
      . '</style><title>' . Html::escape($title) . '</title></head><body><h1>'
      . Html::escape($title) . '</h1>' . $document['html'] . '</body></html>');
    $dompdf->setPaper('A4');
    $dompdf->render();

    $filename = preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?: 'grok-entity-document';
    return new Response($dompdf->output(), 200, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => 'attachment; filename="' . trim($filename, '-') . '.pdf"',
      'Cache-Control' => 'private, no-store',
    ]);
  }

}
