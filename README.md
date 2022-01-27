# MaxMind DB Reader

[![Maintainability](https://api.codeclimate.com/v1/badges/186cc1e8868cddaacccd/maintainability)](https://codeclimate.com/github/librarymarket/maxmind-db-reader/maintainability)

An unofficial library to facilitate reading the MaxMind database file format.

## Why?

MaxMind's open source PHP library for reading `*.mmdb` files is incompatible
with the GNU General Public License, version 2. Because of this, we decided to
implement our own database reader based off of MaxMind's open file format
specifications and their Ruby library for the same purpose (which was
dual-licensed under MIT at the time this library was written).

## Requirements

`ext-bcmath` or `ext-gmp` may be required for decoding unsigned integers of more
than 31 bits in length, but this depends on the contents of the database you're
trying to read.

## Usage

1. Create an instance of the database reader by providing the path to your
MaxMind database file on disk.
2. Invoke `Reader::searchForAddress(string $ip_address, int &$depth = 0): array`
to retrieve information about the IP address from the database.
    - If the IP address wasn't found in the database, an empty array is
    returned.
    - If the optional second argument is supplied, it will be populated with the
    bit depth at which the address was found. This is the netmask of the
    resulting information.

```php
$reader = new \LibraryMarket\MaxMind\Database\Reader('/path/to/database.mmdb');

// Search for an IP address in human-readable format.
$reader->searchForAddress('127.0.0.1');
$reader->searchForAddress('::1');
```

## License

Copyright (c) 2022 Library Solutions, LLC (et al.).

This is free software, licensed under the MIT License. See `LICENSE.txt` in this
project's root directory for the full license text.
