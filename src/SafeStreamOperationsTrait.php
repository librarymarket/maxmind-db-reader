<?php

declare(strict_types = 1);

namespace LibraryMarket\MaxMind\Database;

/**
 * A trait to provide safe stream operations.
 */
trait SafeStreamOperationsTrait {

  /**
   * Safely seek within the supplied stream.
   *
   * @param resource $stream
   *   The stream on which to perform the seek operation.
   * @param int $offset
   *   The offset to use when seeking.
   * @param int $whence
   *   The type of seek to perform (see fseek()).
   *
   * @throws \RuntimeException
   *   If the seek operation fails.
   */
  protected function seek($stream, int $offset, int $whence = \SEEK_SET): void {
    if (!\is_resource($stream) || \fseek($stream, $offset, $whence) !== 0) {
      throw new \RuntimeException('Unable to seek within the supplied stream');
    }
  }

  /**
   * Safely read data from the supplied stream.
   *
   * @param resource $stream
   *   The stream on which to perform the read operation.
   * @param int $length
   *   The length to read.
   * @param bool $strict
   *   Whether the length of data read from the stream should match the
   *   requested length (default: FALSE).
   *
   * @throws \RuntimeException
   *   If the read operation fails.
   *
   * @return string
   *   The data that was read.
   */
  protected function read($stream, int $length, bool $strict = FALSE): string {
    if (!\is_resource($stream) || FALSE === $data = \fread($stream, $length)) {
      throw new \RuntimeException('Unable to read from the supplied stream');
    }

    if ($strict && $length !== strlen($data)) {
      throw new \RuntimeException('The length of data read from the stream differs from the requested length');
    }

    return $data;
  }

}
