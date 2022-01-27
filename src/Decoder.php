<?php

declare(strict_types = 1);

namespace LibraryMarket\MaxMind\Database;

use LibraryMarket\MaxMind\Database\Math\BCMath;
use LibraryMarket\MaxMind\Database\Math\GMP;
use LibraryMarket\MaxMind\Database\Math\Native;

/**
 * A class to facilitate decoding fields in the MaxMind database file format.
 */
class Decoder {

  use SafeStreamOperationsTrait;

  const DATA_TYPE_DECODER_MAPPING = [
    1 => 'decodePointer',
    2 => 'decodeBytes',
    3 => 'decodeDouble',
    4 => 'decodeBytes',
    5 => 'decodeUint',
    6 => 'decodeUint',
    7 => 'decodeMap',
    8 => 'decodeInt',
    9 => 'decodeUint',
    10 => 'decodeUint',
    11 => 'decodeArray',
    14 => 'decodeBoolean',
    15 => 'decodeFloat',
  ];

  const POINTER_SIZE_OFFSET_MAPPING = [
    0 => 0,
    1 => 2048,
    2 => 526336,
    3 => 0,
  ];

  /**
   * The base address used to resolve pointers.
   *
   * @var int
   */
  protected $baseAddress;

  /**
   * The extended mathematics implementation to use, or NULL if not available.
   *
   * @var \LibraryMarket\MaxMind\Database\MathInterface|null
   */
  protected $extendedMath;

  /**
   * Whether this machine is using little endian byte order.
   *
   * @var bool
   */
  protected $littleEndian;

  /**
   * The native mathematics implementation.
   *
   * @var \LibraryMarket\MaxMind\Database\Math\Native
   */
  protected $nativeMath;

  /**
   * The database file handle.
   *
   * @var resource
   */
  protected $stream;

  /**
   * Constructs a Decoder object.
   *
   * @param resource $stream
   *   The stream from which to read & decode data.
   * @param int $base_address
   *   The base address used to resolve pointers.
   *
   * @throws \InvalidArgumentException
   *   If the supplied base address is negative, or the stream is invalid.
   */
  public function __construct($stream, int $base_address = 0) {
    if ($base_address < 0) {
      throw new \InvalidArgumentException('The supplied base address is negative; base addresses must be non-negative');
    }

    if (!\is_resource($stream)) {
      throw new \InvalidArgumentException('The supplied stream is invalid');
    }

    $this->baseAddress = $base_address;
    $this->stream = $stream;

    $this->littleEndian = \unpack('S', "\x01\x00")[1] === 1;

    $this->nativeMath = new Native();
    if (\extension_loaded('bcmath')) {
      $this->extendedMath = new BCMath();
    }
    elseif (\extension_loaded('gmp')) {
      $this->extendedMath = new GMP();
    }
  }

  /**
   * Decode the field at the supplied offset in the database.
   *
   * @param int $offset
   *   The offset at which the field to be decoded exists.
   *
   * @throws \RuntimeException
   *   If decoding fails.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  public function decode(int $offset): array {
    $this->seek($this->stream, $offset++);

    // Extract the control byte from the stream.
    $control_byte = \unpack('C', $this->read($this->stream, 1, TRUE))[1];

    // Process the control byte sub-fields.
    //
    // These operations may incur additional reads of the stream (e.g., for
    // extended type information or extended size information).
    $data_type = $this->processControlByteDataType($control_byte, $offset);
    $size = $this->processControlByteSize($control_byte, $offset);

    // Delegate the remainder of decoding to a type-specific method.
    $callback = [$this, self::DATA_TYPE_DECODER_MAPPING[$data_type]];
    return $callback($size, $offset);
  }

  /**
   * Decode a contiguous sequence of fields into an array.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodeArray(int $size, int $offset): array {
    $result = [];

    for ($i = 0; $i < $size; ++$i) {
      [$value, $offset] = $this->decode($offset);

      $result[] = $value;
    }

    return [$result, $offset];
  }

  /**
   * Decode a boolean value from the supplied size.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodeBoolean(int $size, int $offset): array {
    return [$size != 0, $offset];
  }

  /**
   * Decode a byte array into a string.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodeBytes(int $size, int $offset): array {
    return [$this->read($this->stream, $size, TRUE), $offset + $size];
  }

  /**
   * Decode a double-precision real number in IEEE 754 format.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodeDouble(int $size, int $offset): array {
    if ($size !== 8) {
      throw new \RuntimeException('Unexpected double size: ' . $size);
    }

    $result = \unpack('E', $this->read($this->stream, $size, TRUE))[1];
    return [$result, $offset + $size];
  }

  /**
   * Decode a single-precision real number in IEEE 754 format.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodeFloat(int $size, int $offset): array {
    if ($size !== 4) {
      throw new \RuntimeException('Unexpected float size: ' . $size);
    }

    $result = \unpack('G', $this->read($this->stream, $size, TRUE))[1];
    return [$result, $offset + $size];
  }

  /**
   * Decode a signed integer in big endian byte order.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodeInt(int $size, int $offset): array {
    if ($size < 0 || $size > 4) {
      throw new \RuntimeException('Unexpected int size: ' . $size);
    }

    if ($size === 0) {
      return [0, $offset];
    }

    // The specification guarantees that signed integers shorter than 4 bytes
    // are positive, so we don't need to worry about sign extension here.
    $data = $this->read($this->stream, $size, TRUE);
    $data = \str_pad($data, 4, "\x00", \STR_PAD_LEFT);

    if ($this->littleEndian) {
      $data = \strrev($data);
    }

    return [\unpack('l', $data)[1], $offset + $size];
  }

  /**
   * Decode a contiguous sequence of key/value fields into an associative array.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodeMap(int $size, int $offset): array {
    $result = [];

    for ($i = 0; $i < $size; ++$i) {
      [$key, $offset] = $this->decode($offset);
      [$value, $offset] = $this->decode($offset);

      $result[$key] = $value;
    }

    return [$result, $offset];
  }

  /**
   * Decode and dereference the pointer in the next field in the stream.
   *
   * The pointer will be added to the base address specified when constructing
   * this object prior to being dereferenced.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodePointer(int $size, $offset): array {
    $pointer = $this->baseAddress + self::POINTER_SIZE_OFFSET_MAPPING[$delta = $size >> 3];

    $data = \chr($size & 0b00000111) . $this->read($this->stream, $delta += 1, TRUE);
    $data = \str_pad(\substr($data, -4), 4, "\x00", \STR_PAD_LEFT);

    if (($pointer += \unpack('N', $data)[1]) < 0) {
      throw new \RuntimeException('Unable to decode pointer due to platform limitations');
    }

    return [$this->decode($pointer)[0], $offset + $delta];
  }

  /**
   * Decode an unsigned integer in big endian byte order.
   *
   * @param int $size
   *   The size of the element as specified by the database.
   * @param int $offset
   *   The offset at which the stream was left.
   *
   * @return array
   *   The result of the decoding operation. The first element is the value, and
   *   the second element is the new stream offset after decoding.
   */
  protected function decodeUint(int $size, int $offset): array {
    if ($size < 0 || $size > 16) {
      throw new \RuntimeException('Unexpected uint size: ' . $size);
    }

    if ($size === 0) {
      return [0, $offset];
    }

    $data = $this->read($this->stream, $size, TRUE);
    $math = $this->getMathImplementationForUint($data, $size);

    $result = 0;

    for ($i = 0; $i < $size; ++$i) {
      $result = $math->lshift($result, 8);
      $result = $math->add($result, \unpack('C', $data[$i])[1]);
    }

    return [$result, $offset + $size];
  }

  /**
   * Determine which math implementation to use for the given unsigned integer.
   *
   * If the unsigned integer fits within the integer natively supported by PHP,
   * native PHP operations will be used. Otherwise, either BCMath or GMP will be
   * used for arbitrary precision.
   *
   * @param string $data
   *   The big endian byte sequence representation of an unsigned integer.
   * @param int $size
   *   The size of the byte sequence.
   *
   * @throws \RuntimeException
   *   If the given unsigned integer is too big to be supported.
   *
   * @return \LibraryMarket\MaxMind\Database\MathInterface
   *   The math implementation to use for the given unsigned integer.
   */
  protected function getMathImplementationForUint(string $data, int $size): MathInterface {
    if ($size < \PHP_INT_SIZE) {
      // The native integer size is enough to support native math.
      return $this->nativeMath;
    }

    if ($size === \PHP_INT_SIZE && \unpack('C', $data[0])[1] ^ 0b1000000) {
      // Since this value can also fit within a signed integer, use native math.
      return $this->nativeMath;
    }

    // Ensure that an extended mathematics implementation is available.
    if (!isset($this->extendedMath)) {
      throw new \RuntimeException('Unable to decode uint because of platform limitations. Please install the bcmath or gmp extensions to enable support for extended mathematics');
    }

    return $this->extendedMath;
  }

  /**
   * Process the supplied control byte to extract the field's data type.
   *
   * @param int $control_byte
   *   The control byte preceding the field being processed.
   * @param int|null $offset
   *   The offset at which the stream was left. If supplied, this output
   *   parameter will be incremented for any reads incurred during invocation.
   *
   * @return int
   *   The data type value for the field.
   */
  protected function processControlByteDataType(int $control_byte, ?int &$offset = NULL): int {
    // The data type is the top three bits of the control byte.
    $data_type = $control_byte >> 5;

    // Check if an extended data type is being used for this field.
    if ($data_type === 0) {
      $data_type = 7 + \unpack('C', $this->read($this->stream, $delta = 1, TRUE))[1];

      // Check if an invalid data type was produced.
      if (!\array_key_exists($data_type, self::DATA_TYPE_DECODER_MAPPING)) {
        throw new \RuntimeException('Unknown data type: ' . $data_type);
      }
    }

    // Increment the supplied offset by the incurred read delta (if any).
    if (isset($offset, $delta)) {
      $offset += $delta;
    }

    // Return the resulting data type after processing.
    return $data_type;
  }

  /**
   * Process the supplied control byte to determine the field's size.
   *
   * @param int $control_byte
   *   The control byte preceding the field being processed.
   * @param int|null $offset
   *   The offset at which the stream was left. If supplied, this output
   *   parameter will be incremented for any reads incurred during invocation.
   *
   * @return int
   *   The size value for the field.
   */
  protected function processControlByteSize(int $control_byte, ?int &$offset = NULL): int {
    // The length is the bottom five bits of the control byte.
    $size = $control_byte & 0b00011111;

    // Process extended length values.
    switch ($size) {
      case 29:
        $data = $this->read($this->stream, $delta = 1, TRUE);
        $size = 29 + \unpack('C', $data)[1];

        break;

      case 30:
        $data = $this->read($this->stream, $delta = 2, TRUE);
        $size = 285 + \unpack('n', $data)[1];

        break;

      case 31:
        $data = $this->read($this->stream, $delta = 3, TRUE);
        $size = 65821 + \unpack('N', "\x00" . $data)[1];

        break;

      default:
    }

    // Increment the supplied offset by the incurred read delta (if any).
    if (isset($offset, $delta)) {
      $offset += $delta;
    }

    // Return the resulting size after processing.
    return $size;
  }

}
