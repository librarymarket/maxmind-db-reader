<?php

declare(strict_types = 1);

namespace LibraryMarket\MaxMind\Database;

/**
 * Describes an interface that facilitates mathematics.
 */
interface MathInterface {

  /**
   * Adds the two supplied operands.
   *
   * @param int|string $lhs
   *   The left operand.
   * @param int $rhs
   *   The right operand.
   *
   * @return int|string
   *   The result of the operation.
   */
  public function add($lhs, int $rhs);

  /**
   * Perform a left shift using the two supplied operands.
   *
   * @param int|string $lhs
   *   The left operand.
   * @param int $rhs
   *   The right operand, ranged from 0 to 8 inclusive.
   *
   * @return int|string
   *   The result of the operation.
   */
  public function lshift($lhs, int $rhs);

}
