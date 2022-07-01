<?php

declare(strict_types = 1);

namespace LibraryMarket\MaxMind\Database;

/**
 * A class to facilitate reading the MaxMind database file format.
 */
class Reader {

  use SafeStreamOperationsTrait;

  const DATA_SECTION_SEPARATOR_SIZE = 16;

  const METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";
  const METADATA_MAX_SIZE = 128 * 1024;

  /**
   * The file system path to the database.
   *
   * @var string
   */
  protected $path;

  /**
   * An associative array of metadata for the database.
   *
   * @var array
   */
  protected $metadata;

  /**
   * The offset of the metadata section in the database.
   *
   * A negative value means that the metadata section offset was not found.
   *
   * @var int
   */
  protected $metadataOffset;

  /**
   * The database file statistics.
   *
   * @var array
   */
  protected $stat;

  /**
   * The database file handle.
   *
   * @var resource
   */
  protected $stream;

  /**
   * Constructs a Reader object.
   *
   * @param string $path
   *   A file system path to the database file to read.
   */
  public function __construct(string $path) {
    $this->path = $path;

    if (!\is_file($this->path) || !$stream = \fopen($this->path, 'r')) {
      throw new \RuntimeException('Unable to open database: ' . $this->path);
    }

    $this->stream = $stream;

    if (!$stat = \fstat($this->stream)) {
      throw new \RuntimeException('Unable to get database statistics');
    }

    $this->stat = $stat;

    $this->getMetadata();

    if (!\in_array($this->metadata['ip_version'], [4, 6], TRUE)) {
      throw new \RuntimeException('Unsupported database IP version');
    }
  }

  /**
   * Destructs a Reader object.
   */
  public function __destruct() {
    if (\is_resource($this->stream)) {
      \fclose($this->stream);
    }

    $this->stream = NULL;
  }

  /**
   * Get the offset of the data section.
   *
   * @return int
   *   The data section offset.
   */
  protected function getDataOffset(): int {
    return $this->getSearchTreeSize() + self::DATA_SECTION_SEPARATOR_SIZE;
  }

  /**
   * Get the metadata for the open database.
   *
   * An exception may be thrown on the first invocation if an error occurs while
   * loading the metadata from the database.
   *
   * Upon the first successful invocation, the result will be cached.
   *
   * @return array
   *   The database metadata.
   */
  public function getMetadata(): array {
    if (!isset($this->metadata)) {
      $this->metadata = $this->loadMetadata();
    }

    return $this->metadata;
  }

  /**
   * Get the offset of the metadata section.
   *
   * An exception may be thrown on the first invocation if an error occurs while
   * searching for the metadata section in the database.
   *
   * Upon the first successful invocation, the result will be cached.
   *
   * @return int
   *   The metadata section offset.
   */
  protected function getMetadataOffset(): int {
    if (!isset($this->metadataOffset)) {
      $this->metadataOffset = $this->searchForMetadataOffset() ?? -1;
    }

    if ($this->metadataOffset < 0) {
      throw new \RuntimeException('Unable to determine metadata offset');
    }

    return $this->metadataOffset;
  }

  /**
   * Get the number of nodes in this database.
   *
   * @throws \RuntimeException
   *   If the number of nodes reported by the database is negative.
   *
   * @return int
   *   The number of nodes in this database.
   */
  protected function getNodeCount(): int {
    $count = $this->metadata['node_count'] ?? 0;

    if ($count < 0) {
      throw new \RuntimeException('The database is corrupt or cannot be read');
    }

    return $count;
  }

  /**
   * Get the file offset for the requested node.
   *
   * @param int $node
   *   The index of the node for which to calculate the offset.
   *
   * @throws \OutOfBoundsException
   *   If the requested node index does not exist.
   *
   * @return int
   *   The file offset for the requested node.
   */
  protected function getNodeOffset(int $node): int {
    if ($node < 0 || $node >= $this->getNodeCount()) {
      throw new \OutOfBoundsException("Node with index {$node} does not exist");
    }

    return $this->getNodeSize() * $node;
  }

  /**
   * Get the size of each node in bytes.
   *
   * Since each node has two records, this value is calculated as:
   *
   *   2 * (record_bits / 8)
   *
   * This can be simplified by shifting the record bit length right by 2.
   *
   * @return int
   *   The size of each node in bytes.
   */
  protected function getNodeSize(): int {
    return $this->getRecordBitLength() >> 2;
  }

  /**
   * Get the bit length of each record.
   *
   * This library only supports records of 24, 28, or 32 bits in length.
   *
   * @throws \InvalidArgumentException
   *   If an unsupported bit length is encountered.
   *
   * @return int
   *   The bit length of each record.
   */
  protected function getRecordBitLength(): int {
    $bits = $this->metadata['record_size'] ?? 0;

    if (!\in_array($bits, [24, 28, 32], TRUE)) {
      throw new \InvalidArgumentException('This library only supports records of 24, 28, or 32 bits in length');
    }

    return $bits;
  }

  /**
   * Get the file offset for the record index on the requested node.
   *
   * @param int $node
   *   The index of the node from which to calculate the record offset.
   * @param int $index
   *   The index of the record to be retrieved (either 0 or 1).
   *
   * @throws \InvalidArgumentException
   *   If the supplied index is neither 0 nor 1.
   *
   * @return int
   *   The file offset for the record index on the requested node.
   */
  protected function getRecordOffset(int $node, int $index): int {
    if (!\in_array($index, [0, 1], TRUE)) {
      throw new \InvalidArgumentException('$index must be 0 or 1');
    }

    $offset = $this->getNodeOffset($node);

    if ($index !== 0) {
      $offset += $this->getRecordBitLength() >> 3;
    }

    return $offset;
  }

  /**
   * Get the size of each record in bytes.
   *
   * This method is concerned with the number of bytes that must be read from
   * the stream for each record, not the actual size in bytes of each record
   * (which may contain a fractional byte).
   *
   * If the bit length of each record isn't evenly divisible by 8, the result
   * will be rounded up to the next byte.
   *
   * @return int
   *   The size of each record in bytes.
   */
  protected function getRecordSize(): int {
    $bits = $this->getRecordBitLength();

    if ($bits & 0b00000111) {
      return ($bits >> 3) + 1;
    }

    return $bits >> 3;
  }

  /**
   * Get the bit depth of the search tree.
   *
   * @return int
   *   The bit depth of the search tree.
   */
  protected function getSearchTreeBitDepth(): int {
    switch ($this->metadata['ip_version']) {
      case 4:
        return 32;

      case 6:
        return 128;
    }
  }

  /**
   * Get the size of the search tree in bytes.
   *
   * The search tree size is calculated by multiplying the node count for this
   * database by the size of each node.
   *
   * @return int
   *   The size of the search tree in bytes.
   */
  protected function getSearchTreeSize(): int {
    return $this->getNodeCount() * $this->getNodeSize();
  }

  /**
   * Get the size of the database in bytes.
   *
   * @return int
   *   The size of the database in bytes.
   *
   * @throws \RuntimeException
   *   If the database size could not be determined.
   */
  protected function getSize(): int {
    if (!isset($this->stat['size'])) {
      throw new \RuntimeException('Unable to determine database size');
    }

    return $this->stat['size'];
  }

  /**
   * Load the metadata section from the open database.
   *
   * @return array
   *   The metadata loaded from the database.
   */
  protected function loadMetadata(): array {
    $decoder = new Decoder($this->stream, $offset = $this->getMetadataOffset());
    $decoded = $decoder->decode($offset);

    return $decoded[0];
  }

  /**
   * Get the value of a record by index.
   *
   * @param int $node
   *   The index of the node containing the record to retrieve.
   * @param int $index
   *   The index of the record to retrieve (either 0 or 1).
   *
   * @throws \RuntimeException
   *   If the record value was misinterpreted due to platform limitations.
   *
   * @return int
   *   The value of the record.
   */
  protected function getRecordValueByIndex(int $node, int $index): int {
    $this->seek($this->stream, $this->getRecordOffset($node, $index));

    $value = $this->read($this->stream, $this->getRecordSize(), TRUE);
    $value = \unpack('N', \str_pad($value, 4, "\x00", \STR_PAD_LEFT))[1];

    // 28-bit records require special handling.
    if ($this->getRecordBitLength() === 28) {
      // If retrieving the first record, we have to rearrange some bits.
      if ($index === 0) {
        $value = (($value & 0xF0) << 20) | ($value >> 8);
      }

      // Ignore the first four bits of the value.
      $value &= 0x0FFFFFFF;
    }

    if ($value < 0) {
      throw new \RuntimeException('Unable to interpret record value due to platform limitations');
    }

    return $value;
  }

  /**
   * Attempt to find the supplied address in the binary search tree.
   *
   * If an IPv6 address is supplied, but the underlying database only contains
   * IPv4 addresses, then the supplied address will be truncated to 32 bits
   * (using the least significant bits of the address).
   *
   * @param string $ip_address
   *   The IP address in human-readable format.
   * @param int $depth
   *   An output parameter used to report the tree depth of the address.
   *
   * @return array
   *   The record that corresponds with the supplied address. If the address was
   *   not found, this value will be empty.
   */
  public function searchForAddress(string $ip_address, int &$depth = 0): array {
    // Attempt to convert the supplied IP address into a binary string.
    if (FALSE === $ip_address = \inet_pton($ip_address)) {
      throw new \InvalidArgumentException('$ip_address is invalid');
    }

    $ip_address_length = $this->getSearchTreeBitDepth() >> 3;
    $ip_address = \substr(\str_pad($ip_address, $ip_address_length, "\x00", \STR_PAD_LEFT), -$ip_address_length);

    $node_count = $this->getNodeCount();

    // Iterate over each bit in the address to traverse the binary search tree.
    for ($depth = 0, $node = 0; $depth < $this->getSearchTreeBitDepth() && $node < $node_count; ++$depth) {
      $byte = \unpack('C', $ip_address[$depth >> 3])[1];
      $bit = 1 & ($byte >> (7 - ($depth & 0b00000111)));

      $node = $this->getRecordValueByIndex($node, $bit);
    }

    // Decode the field pointed to by the resulting record value (if valid).
    if ($node > $node_count) {
      $offset = $node - $node_count + $this->getSearchTreeSize();

      $decoder = new Decoder($this->stream, $this->getDataOffset());
      $decoded = $decoder->decode($offset);

      return $decoded[0];
    }

    return [];
  }

  /**
   * Search for the metadata section offset in the database file.
   *
   * The metadata section can occur in the last 128 KiB in the database file,
   * and will be preceded by a specific byte sequence marker.
   *
   * This method searches the applicable range of the database file. If the
   * marker is not found, NULL is returned. Otherwise, the offset immediately
   * following the marker is returned.
   *
   * An exception could be thrown if an underlying seek or read fails.
   *
   * @return int|null
   *   The metadata section offset in the database file (excluding the marker),
   *   or NULL if no offset was found.
   */
  protected function searchForMetadataOffset(): ?int {
    // The database file will be scanned from the end, moving the window
    // backward in marker-sized chunks.
    $marker = self::METADATA_MARKER;
    $marker_size = \strlen($marker);

    // Determine the extreme file offsets at which the metadata section marker
    // could be found in the database using the metadata section size limit and
    // the total database size.
    $max = \max(0, $this->getSize() - $marker_size);
    $min = \max(0, $max - self::METADATA_MAX_SIZE);

    for ($chunk = $max; $chunk >= $min; $chunk -= $marker_size) {
      $this->seek($this->stream, $chunk);

      // The read length should be double the marker size to catch markers that
      // could potentially overlap a read boundary.
      $data = $this->read($this->stream, $marker_size * 2, FALSE);

      if (FALSE !== $pos = \strpos($data, $marker)) {
        return $chunk + $pos + $marker_size;
      }
    }

    return NULL;
  }

}
