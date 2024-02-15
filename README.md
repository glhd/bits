<div style="float: right;">
	<a href="https://github.com/glhd/bits/actions" target="_blank">
		<img 
			src="https://github.com/glhd/bits/workflows/PHPUnit/badge.svg" 
			alt="Build Status" 
		/>
	</a>
	<a href="https://codeclimate.com/github/glhd/bits/test_coverage" target="_blank">
		<img 
			src="https://api.codeclimate.com/v1/badges/6d6485f01a3118f38a63/test_coverage" 
			alt="Coverage Status" 
		/>
	</a>
	<a href="https://packagist.org/packages/glhd/bits" target="_blank">
        <img 
            src="https://poser.pugx.org/glhd/bits/v/stable" 
            alt="Latest Stable Release" 
        />
	</a>
	<a href="./LICENSE" target="_blank">
        <img 
            src="https://poser.pugx.org/glhd/bits/license" 
            alt="MIT Licensed" 
        />
    </a>
    <a href="https://twitter.com/inxilpro" target="_blank">
        <img 
            src="https://img.shields.io/twitter/follow/inxilpro?style=social" 
            alt="Follow @inxilpro on Twitter" 
        />
    </a>
    <a href="https://any.dev/@chris" target="_blank">
        <img 
            src="https://img.shields.io/mastodon/follow/109584001693739813?domain=https%3A%2F%2Fany.dev&style=social" 
            alt="Follow @chris@any.dev on Mastodon" 
        />
    </a>
</div>

# Bits

**Bits** is a PHP library for generating unique 64-bit identifiers for use in distributed computing.
You can use **Bits** to create [Twitter Snowflake IDs](https://en.wikipedia.org/wiki/Snowflake_ID),
[Sonyflake IDs](https://github.com/sony/sonyflake), or any other unique ID that uses bit sequences.

## Installation

```shell
composer require glhd/bits
```

## Configuration

There are two things you will need to configure to ensure that your IDs are valid and unique.

### Set the `BITS_WORKER_ID` and `BITS_DATACENTER_ID`

Snowflakes are so compact because they rely on the fact that each worker has its own ID. If you are
running multiple servers in multiple datacenters, you need to give each a unique value. You need to
set a unique `BITS_DATACENTER_ID` (0-31) for each datacenter your application uses, and each worker in the
same datacenter should have a unique `BITS_WORKER_ID` (0-31). This means that you can have, at most, 1024
separate workers generating snowflakes at the same exact moment in time.

**Note:** If you use Lambda, this can be an issue. Eventually, we hope to have a serverless solution for
Bits, but right now you need to manage locking/releasing IDs yourself if you're generating snowflakes
in something like Laravel Vapor.

### Set the `BITS_EPOCH`

Another reason snowflakes are compact is because they use a custom "epoch" value (rather than the Unix 
epoch of January 1, 1970). This is the earliest a snowflake can be generated, and also set the limit to 
how far in the future snowflakes can be generated. By default, this value is `2023-01-01`, which should 
be fine for most systems. But if you're going to use time-travel to before January 2023 in your tests, 
this may cause problems (in which case you should set your epoch to before the earliest moment you 
would time-travel).

## Usage

To get a new snowflake ID, simply call `Snowflake::make()`. This returns a new
`Snowflake object`:

```php
class Snowflake
{
    public readonly int $timestamp;
    public readonly int $datacenter_id;
    public readonly int $worker_id;
    public readonly int $sequence;
    
    public function id(): int;
    public function is(Snowflake $other): bool;
}
```

You can also use the `snowflake()` or `sonyflake()` global helper functions,
if you prefer.

All Bits IDs implement `__toString()` and the Laravel `Query\Expression` interface so that you
can easily pass them around without juggling types.

### Usage with Eloquent Models

Bits provides a `HasSnowflakes` trait that behaves the same as 
[Eloquent’s `HasUuids` and `HasUlids` traits](https://laravel.com/docs/10.x/eloquent#uuid-and-ulid-keys). 
Simply add `HasSnowflakes` to your model, and whenever they're inserted or upserted, and new Snowflake
will be generated for you.

You can also use `Snowflake` or `Sonyflake` as in your Eloquent `$casts` array to have
that attribute automatically cast to a Bits instance.

```php
use Glhd\Bits\Database\HasSnowflakes;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Model;

class Example extends Model
{
    // Auto-generate Snowflake for new models
    use HasSnowflakes;
    
    // Any attribute can be cast to a `Snowflake` (or `Sonyflake`)
    protected $casts = [
        'id' => Snowflake::class,
    ];
}

$example = Example::create();

$example->id instanceof Snowflake; // true

echo $example->id; // 65898467809951744
```

## About 64-bit Unique IDs

### Snowflake format

```
0 0000001100100101110101100110111100101011 01011 01111 000000011101
┳ ━━━━━━━━━━━━━━━━┳━━━━━━━━━━━━━━━━━━━━━━━ ━━━┳━ ━┳━━━ ━┳━━━━━━━━━━
┗━ unused bit     ┗━ timestamp (41)           ┃   ┃     ┗━ sequence (12)
                              datacenter (5) ━┛   ┗━ worker (5)
```

### Sonyflake format

```
0 000000011001001011101011001101111001010 11010110 1111000000011101
┳ ━━━━━━━━┳━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ ━━━━━━┳━ ━┳━━━━━━━━━━━━━━
┗━ sign   ┗ timestamp (39)         sequence (8) ┛   ┗ machine (16)
```

Both of these IDs are represented by the same 64-bit integer, `56705782302306333`,
but convey different metadata. Depending on your scale and distribution needs,
you may find one or the other format preferable, or choose to implement your own
custom format.

**Bits** lets generate any kind of 64-bit unique ID you'd like, in the way that makes
the most sense for your use-case.
