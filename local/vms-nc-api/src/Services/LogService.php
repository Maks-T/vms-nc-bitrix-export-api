<?php

declare(strict_types=1);

namespace VmsNcApi\Services;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use RuntimeException;
use Throwable;

final class LogService
{
  /** @var array */
  private static $loggers = [];

  /** @var string */
  public static $logDir = '';

  public static function setLogDir(string $dir): void
  {
    self::$logDir = rtrim($dir, '/') . '/';
  }

  public static function get(string $channel = 'app'): Logger
  {
    if (!isset(self::$loggers[$channel])) {
      if (!self::$logDir) {
        throw new RuntimeException('Не установлена директория для логов через LogService::setLogDir()');
      }

      $logger = new Logger($channel);
      $logPath = self::$logDir . $channel . '.log';

      $dir = dirname($logPath);
      if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
      }

      $logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
      self::$loggers[$channel] = $logger;
    }

    return self::$loggers[$channel];
  }

  /**
   * @param string|Throwable $message
   * @param array $context
   * @param string $channel
   */
  public static function error($message, array $context = [], string $channel = 'errors'): void
  {
    if ($message instanceof Throwable) {
      $context['exception'] = $message;
      $text = $message->getMessage();
      $context['file'] = $message->getFile();
      $context['line'] = $message->getLine();
    } else {
      $text = (string)$message;
    }

    self::get($channel)->error($text, $context);
  }

  public static function info(string $message, array $context = [], string $channel = 'app'): void
  {
    self::get($channel)->info($message, $context);
  }

  public static function warning(string $message, array $context = [], string $channel = 'app'): void
  {
    self::get($channel)->warning($message, $context);
  }
}