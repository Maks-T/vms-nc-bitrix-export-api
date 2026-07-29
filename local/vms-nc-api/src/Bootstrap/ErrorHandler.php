<?php

declare(strict_types=1);

namespace VmsNcApi\Bootstrap;

use Throwable;
use VmsNcApi\Services\LogService;

final class ErrorHandler
{
  public static function register(string $logDir): void
  {
    LogService::setLogDir($logDir);

    set_exception_handler(function (Throwable $e) {
      self::handle($e);
    });

    register_shutdown_function(function () {
      $error = error_get_last();
      if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        self::handleFatal($error);
      }
    });
  }

  private static function handle(Throwable $e): void
  {
    try {
      LogService::error($e);
    } catch (Throwable $loggingError) {
      // Резервный молчаливый перехват
    }

    $code = $e->getCode();
    if (!is_int($code) || $code < 400 || $code >= 600) {
      $code = 500;
    }

    self::renderJson([
      'status'  => 'error',
      'message' => $e->getMessage(),
      'type'    => get_class($e),
      'file'    => $e->getFile(),
      'line'    => $e->getLine()
    ], $code);
  }

  private static function handleFatal(array $error): void
  {
    try {
      LogService::error("FATAL ERROR: " . $error['message'], $error);
    } catch (Throwable $e) {
    }

    self::renderJson([
      'status'  => 'error',
      'message' => $error['message'],
      'type'    => 'fatal_error',
      'file'    => $error['file'] ?? '?',
      'line'    => $error['line'] ?? 0
    ], 500);
  }

  private static function renderJson(array $data, int $code): void
  {
    if (!headers_sent()) {
      http_response_code($code);
      header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
  }
}