<?php

declare(strict_types = 1);

namespace LibraryMarket\MaxMind\Database;

/**
 * A trait to provide a safe unpack operation.
 */
trait SafeUnpackTrait {

  /**
   * Unpack data from a binary string, throwing an exception on failure.
   *
   * @param string $format
   *   A format string to describe the packed data.
   * @param string $string
   *   The packed data.
   * @param int $offset
   *   The offset from which to begin unpacking.
   *
   * @throws \RuntimeException
   *   If the unpack operation fails.
   *
   * @return mixed[]
   *   An associative array containing unpacked elements of the binary data.
   */
  protected function unpack(string $format, string $string, int $offset = 0): array {
    if (!\is_array($result = \unpack($format, $string, $offset))) {
      throw new \RuntimeException('The unpack operation failed');
    }

    return $result;
  }

}
