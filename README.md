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

### Snowflake format

```
0 0000001100100101110101100110111100101011 01011 01111 000000011101
┳ ━━━━━━━━━━━━━━━━┳━━━━━━━━━━━━━━━━━━━━━━━ ━━━┳━ ━┳━━━ ━┳━━━━━━━━━━
┗━ unused bit     ┗━ timestamp (41 bits)      ┃   ┃     ┗━ "sequence" (12 bits)
                         datacenter (5 bits) ━┛   ┗━ worker (5 bits)
```

### Sonyflake format

```
000000011001001011101011001101111001010 11010110 1111000000011101
━┳━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ ━━━━━━┳━ ━┳━━━━━━━━━━━━━━
 ┗ timestamp (39 bits)    "sequence" (8 bits) ┛   ┗ machine (16 bits)
```

Both of these IDs are represented by the same 64-bit integer, `56705782302306333`,
but convey different metadata. Depending on your scale and distribution needs,
you may find one or the other format preferable, or choose to implement your own 
custom format.

**Bits** lets generate any kind of 64-bit unique ID you'd like, in the way that makes 
the most sense for your use-case.

## Installation

```shell
composer require glhd/bits
```

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

You can use Snowflakes in Laravel models by just casting to `Snowflake::class`
and Snowflakes implement `__toString()` and the Laravel `Query\Expression` interface
so that you can use that easily as-is throughout your app.
