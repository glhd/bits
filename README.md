<div style="float: right;">
	<a href="https://github.com/glhd/bits/actions" target="_blank">
		<img 
			src="https://github.com/glhd/bits/workflows/PHPUnit/badge.svg" 
			alt="Build Status" 
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
    <a href="https://bsky.app/profile/cmorrell.com" target="_blank">
        <img 
            src="https://img.shields.io/bluesky/followers/cmorrell.com" 
            alt="Follow @cmorrell.com on bsky" 
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

### Worker and datacenter IDs

Snowflakes are compact because each worker has its own ID. Two processes that share a worker
and datacenter ID can generate the same snowflake in the same millisecond, so every process
generating IDs at the same moment needs its own pair.

**By default, Bits claims a pair for you.** The first time a process generates an ID, it takes an
unused datacenter/worker pair from your cache, holds it for as long as it runs, and hands it back
when it exits. You do not have to configure anything, and it works on Lambda, Vapor, Cloud Run,
and anywhere else machines come and go.

This needs a cache store shared across your processes, with an atomic `add`: **redis**
(recommended), memcached, dynamodb, database, or file. The `array` store only lives inside one
process, so it cannot keep two processes apart.

```dotenv
# Claim from a store other than your default one
BITS_IDENTITY_STORE=redis

# How long a claim lasts. Bits renews it as it generates IDs, so this is really
# "how long a crashed process holds its pair". Defaults to 300 seconds.
BITS_IDENTITY_TTL=300
```

The default snowflake format has 5 bits for the datacenter and 5 for the worker, so 1,024
processes can generate snowflakes at once. Sonyflakes have 16 bits for the machine ID, so 65,536.
Ask for one past that and Bits throws `IdentitySpaceExhausted` rather than hand out an ID that
might collide.

#### Assigning IDs yourself

If your processes already have stable IDs — a fixed fleet, a Kubernetes StatefulSet ordinal, a
container index — set them and Bits will not touch your cache:

```dotenv
BITS_DATACENTER_ID=2
BITS_WORKER_ID=7
```

Set only one and Bits pins that one and claims the rest. Pinning `BITS_DATACENTER_ID=2` per
region, for example, lets each region assign its own datacenter while processes still claim their
own worker ID inside it.

To go back to the pre-0.7 behavior, where an unset ID meant a random one:

```dotenv
BITS_IDENTITY_DRIVER=fixed
```

> [!warning]
> A random worker ID collides more often than you would think. With 1,024 pairs, it takes only
> 38 processes before two of them share one about half the time. This is why claiming is now
> the default.

#### While running tests

Bits does not claim identities under `php artisan test`. Your test cache is usually the `array`
store, which would hand every parallel test worker the same pair, so tests keep the old behavior
of a per-worker random ID. Set `BITS_IDENTITY_DRIVER=cache` to exercise claiming.

#### Sourcing IDs from somewhere else

Every factory asks a `ResolvesIdentities` implementation for its IDs:

```php
interface ResolvesIdentities
{
    public function identities(): WorkerIds;
    public function release(): void;
}
```

Bits ships two: `CacheIdentityResolver`, which claims from the cache, and
`FixedIdentityResolver`, which returns the same IDs forever.

To read IDs from somewhere Bits does not know about, extend `IdentityResolver` and return an
index into the ID space. The space takes care of splitting that index back across segments, so
you only deal in one number:

```php
use Glhd\Bits\IdentityResolvers\IdentityResolver;

class StatefulSetResolver extends IdentityResolver
{
    protected function value(): int
    {
        // "web-7" -> 7
        return (int) str(gethostname())->afterLast('-');
    }
}
```

Then bind it:

```php
use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Contracts\ResolvesIdentities;
use Glhd\Bits\IdentityResolvers\IdentitySpace;

$this->app->singleton(ResolvesIdentities::class, function() {
    return new StatefulSetResolver(new IdentitySpace(app(SnowflakesConfig::class)));
});
```

#### How claiming works

Each datacenter/worker pair gets its own cache key, claimed with `Cache::add()` — the first
process to write a key wins and everyone else is told no. There is no shared lock and no shared
list of who holds what, so a hundred cold starts do not queue behind each other.

Two processes could still pick the same pair and one has to move on, so each process walks the ID
space in its own order, from a random seed taken at startup. The walk is a linear congruential
generator over the space, which visits every pair exactly once before repeating. Filling all
1,024 snowflake pairs costs about 6,800 cache calls this way; every process scanning from pair
zero would cost 524,800.

A process renews its claim as it generates IDs, once a third of the TTL has passed. That means an
ID is only ever stamped with a pair that had at least two thirds of its lifetime left when it was
last confirmed. If a renewal finds the pair gone or taken over, the process claims a new one
before generating its next ID.

Claims are given back when the process exits, and are tied to the OS process rather than to the
container. That matters on Octane, where the container is rebuilt per request but the process
outlives all of them.

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

### Usage with JavaScript

It's important to note that JavaScript only supports [~52-bit integers](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Number/MAX_SAFE_INTEGER)
so if you're passing Snowflakes to JavaScript, be sure to cast them to a string.

All Bits IDs implement `Jsonable` and `JsonSerializable`, so if you pass a Bits instance
to `json_encode` it will automatically handle this for you. But if you're using integer IDs
that were generated by Bits, you will have to cast them yourself.

### Usage with Eloquent Models

Bits provides a `HasSnowflakes` trait that behaves the same as 
[Eloquent’s `HasUuids` and `HasUlids` traits](https://laravel.com/docs/10.x/eloquent#uuid-and-ulid-keys). 
Simply add `HasSnowflakes` to your model, and whenever they're inserted or upserted, a new Snowflake
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

### Using IDs as timestamps

One nice property of Snowflakes and Sonyflakes is that they are sortable by time, and can
have the timestamp extracted from the ID for later use. This means that if you want to,
you can use these IDs in place of a `created_at` timestamp:

```php
// Instead of:
User::where('created_at', '>', now());

// You can do (assuming users.id is a snowflake)
User::where('id', '>', app(MakesSnowflakes::class)->firstForTimestamp(now()));
```

```php
// Instead of:
$created_at = $user->created_at;

// You can do (assuming User::id is cast to a Snowflake object)
$created_at = $user->id->toCarbon();
```

### Usage with Livewire

If you're using Livewire, you can use the `SnowflakeSynth` synthesizer to
make Snowflakes usable in your components.

All you need to do is to register the synthesizer in your `AppServiceProvider`:

```php
use Glhd\Bits\Support\Livewire\SnowflakeSynth;
use Glhd\Bits\Support\Livewire\BitsSynth;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // If only using Snowflakes:
        Livewire::propertySynthesizer(SnowflakeSynth::class);
        
        // If using Sonyflakes or a custom Bits variant:
        Livewire::propertySynthesizer(BitsSynth::class);
    }
}
```

If you're using a non-Snowflake variant of Bits, you can use the `BitsSynth` 
instead, which supports any version of Bits at the cost of storing a little
more data.

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
