# NGT PHP

[NGT](https://github.com/yahoojapan/NGT) - high-speed approximate nearest neighbors - for PHP

[![Build Status](https://github.com/ankane/ngt-php/actions/workflows/build.yml/badge.svg)](https://github.com/ankane/ngt-php/actions)

## Installation

Run:

```sh
composer require ankane/ngt
```

Add scripts to `composer.json` to download the shared library:

```json
    "scripts": {
        "post-install-cmd": "Ngt\\Vendor::check",
        "post-update-cmd": "Ngt\\Vendor::check"
    }
```

And run:

```sh
composer install
```

On Mac, also install OpenMP:

```sh
brew install libomp
```

NGT is not available for Windows

## Getting Started

Prep your data

```php
$objects = [
    [1, 1, 2, 1],
    [5, 4, 6, 5],
    [1, 2, 1, 2]
];
```

Create an index

```php
$index = new Ngt\Index($dimensions);
```

Insert objects

```php
$index->batchInsert($objects);
```

Search the index

```php
$index->search($query, size: 3);
```

Save the index

```php
$index->save($path);
```

Load an index

```php
$index = Ngt\Index::load($path);
```

Get an object by id

```php
$index->object($id);
```

Insert a single object

```php
$index->insert($object);
```

Remove an object by id

```php
$index->remove($id);
```

Build the index

```php
$index->buildIndex();
```

## Full Example

```php
$dim = 10;
$objects = [];
for ($i = 0; $i < 100; $i++) {
    $object = [];
    for ($j = 0; $j < $dim; $j++) {
        $object[] = rand(0, 100);
    }
    $objects[] = $object;
}

$index = new Ngt\Index($dim);
$index->batchInsert($objects);

$query = $objects[0];
$result = $index->search($query, size: 3);

foreach ($result as $res) {
    print($res['id'] . ', ' . $res['distance'] . "\n");
}
```

## Index Options

Defaults shown below

```php
use Ngt\DistanceType;
use Ngt\ObjectType;

new Ngt\Index(
    $dimensions,
    edgeSizeForCreation: 10,
    edgeSizeForSearch: 40,
    distanceType: DistanceType::L2,  // L1, L2, Hamming, Angle, Cosine, NormalizedAngle, NormalizedCosine, Jaccard
    objectType: ObjectType::Float    // Float, Float16, Integer
);
```

## Credits

This library is modeled after NGT’s [Python API](https://github.com/yahoojapan/NGT/blob/master/python/README-ngtpy.md).

## History

View the [changelog](CHANGELOG.md)

## Contributing

Everyone is encouraged to help improve this project. Here are a few ways you can help:

- [Report bugs](https://github.com/ankane/ngt-php/issues)
- Fix bugs and [submit pull requests](https://github.com/ankane/ngt-php/pulls)
- Write, clarify, or fix documentation
- Suggest or add new features

To get started with development:

```sh
git clone https://github.com/ankane/ngt-php.git
cd ngt-php
composer install
composer test
```
