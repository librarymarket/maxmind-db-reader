<?php

declare(strict_types = 1);

namespace LibraryMarket\MaxMind\Database\Math;

use LibraryMarket\MaxMind\Database\MathInterface;

/**
 * Implements arbitrary position mathematics using BCMath as a backend.
 */
class BCMath implements MathInterface {

  /**
   * {@inheritdoc}
   */
  public function add($lhs, int $rhs) {
    return \bcadd((string) $lhs, (string) $rhs);
  }

  /**
   * {@inheritdoc}
   */
  public function lshift($lhs, int $rhs) {
    if ($rhs < 0 || $rhs > 8) {
      throw new \InvalidArgumentException('The right-hand operand of the left shift operation must be on the range [0,8]');
    }

    return \bcmul((string) $lhs, (string) \pow(2, $rhs), 0);
  }

}
