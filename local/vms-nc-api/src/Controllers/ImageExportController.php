<?php

declare(strict_types=1);

namespace VmsNcApi\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Psr7\Stream;
use VmsNcApi\Engine\CatalogExportEngine;
use ZipArchive;

final class ImageExportController
{
  private CatalogExportEngine $exportEngine;

  public function __construct(CatalogExportEngine $exportEngine)
  {
    $this->exportEngine = $exportEngine;
  }

  /**
   * Сборка и копирование только необходимых картинок каталога
   */
  public function exportImages(Request $request, Response $response, array $args): Response
  {
    $clientCode = (string)($args['client'] ?? 'stoleshka_ru');
    $catalogDto = $this->exportEngine->export($clientCode);

    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    $exportDir = __DIR__ . '/../../storage/export_images';

    $imagePaths = $this->collectImagePathsFromDto($catalogDto);

    $copiedCount  = 0;
    $missingCount = 0;
    $missingFiles = [];

    foreach ($imagePaths as $relPath) {
      $sourcePath = $docRoot . '/' . ltrim($relPath, '/');
      $destPath   = $exportDir . '/' . ltrim($relPath, '/');

      if (file_exists($sourcePath)) {
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
          @mkdir($dir, 0755, true);
        }
        @copy($sourcePath, $destPath);
        $copiedCount++;
      } else {
        $missingCount++;
        $missingFiles[] = $relPath;
      }
    }

    $payload = json_encode([
      'status'           => 'success',
      'client_code'      => $clientCode,
      'total_images'     => count($imagePaths),
      'copied_images'    => $copiedCount,
      'missing_images'   => $missingCount,
      'export_path'      => realpath($exportDir) ?: $exportDir,
      'zip_download_url' => '/local/vms-nc-api/export/' . $clientCode . '/images/zip',
      'missing_files'    => $missingFiles
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
  }


  /**
   * Генерация и скачивание ZIP-архива со всеми собранными картинками (с сохранением структуры папок)
   */
  public function downloadZip(Request $request, Response $response, array $args): Response
  {
    $exportDir = realpath(__DIR__ . '/../../storage/export_images');
    $zipPath   = __DIR__ . '/../../storage/export_images.zip';

    if (!$exportDir || !is_dir($exportDir)) {
      $response->getBody()->write((string)json_encode(['error' => 'Папка с картинками не найдена. Сначала запустите /images']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    if (class_exists('ZipArchive')) {
      $zip = new ZipArchive();
      if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $files = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($exportDir, RecursiveDirectoryIterator::SKIP_DOTS),
          RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
          if (!$file->isDir()) {
            $filePath = $file->getRealPath();

            // 1. Вычисляем относительный путь
            $relativePath = substr($filePath, strlen($exportDir));

            // 2. Приводим ВСЕ слэши к прямому стандарту ZIP ('/') и убираем ведущий слэш
            $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

            // 3. Записываем в ZIP с гарантированно правильным относительным путем
            $zip->addFile($filePath, $relativePath);
          }
        }
        $zip->close();
      }
    }

    if (!file_exists($zipPath)) {
      $response->getBody()->write((string)json_encode(['error' => 'Не удалось создать ZIP архив. Убедитесь, что php-zip установлен.']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    $stream = new Stream(fopen($zipPath, 'rb'));
    return $response
      ->withHeader('Content-Type', 'application/zip')
      ->withHeader('Content-Disposition', 'attachment; filename="export_images_' . date('Y-m-d') . '.zip"')
      ->withHeader('Content-Length', (string)filesize($zipPath))
      ->withBody($stream);
  }

  /**
   * Сборка всех путей к файлам из всех разделов DTO
   */
  private function collectImagePathsFromDto($dto): array
  {
    $paths = [];

    foreach ($dto->products as $p) {
      if (!empty($p['preview_picture'])) {
        $paths[] = $p['preview_picture'];
      }
      if (!empty($p['detail_picture'])) {
        $paths[] = $p['detail_picture'];
      }

      foreach ($p['variants'] ?? [] as $v) {
        if (!empty($v['preview_picture'])) {
          $paths[] = $v['preview_picture'];
        }
        if (!empty($v['detail_picture'])) {
          $paths[] = $v['detail_picture'];
        }
      }
    }

    foreach ($dto->attributes as $attr) {
      foreach ($attr['options'] ?? [] as $opt) {
        $img = $opt['meta']['image'] ?? null;
        if (!empty($img)) {
          $paths[] = $img;
        }
      }
    }

    return array_values(array_unique(array_filter($paths)));
  }
}