<?php

declare(strict_types = 1);

namespace LibraryMarket\MaxMind\Database\Math;

use LibraryMarket\MaxMind\Database\MathInterface;

/**
 * Implements arbitrary position mathematics using GMP as a backend.
 */
class GMP implements MathInterface {

  /**
   * {@inheritdoc}
   */
  public function add($lhs, int $rhs) {
    return \gmp_strval(\gmp_add(\gmp_strval($lhs), \gmp_strval($rhs)));
  }

  /**
   * {@inheritdoc}
   */
  public function lshift($lhs, int $rhs) {
    if ($rhs < 0 || $rhs > 8) {
      throw new \InvalidArgumentException('The right-hand operand of the left shift operation must be on the range [0,8]');
    }

    return \gmp_strval(\gmp_mul(\gmp_strval($lhs), \gmp_strval(\pow(2, $rhs))));
  }

}
