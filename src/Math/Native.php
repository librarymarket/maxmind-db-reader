<?php

declare(strict_types = 1);

namespace LibraryMarket\MaxMind\Database\Math;

use LibraryMarket\MaxMind\Database\MathInterface;

/**
 * Implements mathematics using native PHP operations.
 */
class Native implements MathInterface {

  /**
   * {@inheritdoc}
   */
  public function add($lhs, int $rhs) {
    return \intval($lhs) + $rhs;
  }

  /**
   * {@inheritdoc}
   */
  public function lshift($lhs, int $rhs) {
    if ($rhs < 0 || $rhs > 8) {
      throw new \InvalidArgumentException('The right-hand operand of the left shift operation must be on the range [0,8]');
    }

    return \intval($lhs) << $rhs;
  }

}
