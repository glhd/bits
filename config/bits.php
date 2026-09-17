<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Default Format
	|--------------------------------------------------------------------------
	|
	| There are two default formats available: 'snowflake' or 'sonyflake'
	|
	| Each have their own trade-offs. See https://github.com/glhd/bits
	| for details about each format.
	*/
	
	'format' => 'snowflake',
	
	/*
	|--------------------------------------------------------------------------
	| Epoch
	|--------------------------------------------------------------------------
	|
	| Each snowflake is generated relative to an epoch timestamp (much like
	| unix timestamps). This allows them to be precise and still fit in
	| a 64-bit integer. This should be a date in the past, but as recent
	| as possible.
	|
	| PLEASE NOTE: If you change this after you start generating snowflakes,
	|              you may run into collisions!
	|
	*/
	
	'epoch' => env('BITS_EPOCH', '2023-01-01'),
	
	/*
	|--------------------------------------------------------------------------
	| Worker ID
	|--------------------------------------------------------------------------
	|
	| For Snowflakes, you can have up to 31 workers per datacenter. For
	| Sonyflakes, you can have up to 65535 total workers across your entire
	| deployment.
	|
	| Leave this unset to have each process claim its own worker ID from the
	| cache when it generates its first ID. Set it, and every process using
	| this config will share that worker ID.
	*/
	
	'worker_id' => env('BITS_WORKER_ID'),
	
	/*
	|--------------------------------------------------------------------------
	| Datacenter ID
	|--------------------------------------------------------------------------
	|
	| You can have up to 31 datacenters with the default snowflake
	| configuration. As with the worker ID, leave this unset to have each
	| process claim its own, or set it to pin every process in this
	| deployment to one datacenter.
	|
	| Sonyflakes do not use datacenter IDs (and instead use larger worker IDs).
	*/
	
	'datacenter_id' => env('BITS_DATACENTER_ID'),
	
	/*
	|--------------------------------------------------------------------------
	| Identity Resolution
	|--------------------------------------------------------------------------
	|
	| Snowflakes are only unique if no two processes generating them at the
	| same moment share a worker and datacenter ID. On Lambda (and so Laravel
	| Vapor), Cloud Run, or any autoscaling fleet, a process has no way to
	| know either one up front, so Bits claims an unused pair from the cache
	| and holds it for as long as the process lives.
	|
	| driver      'auto' claims from the cache, but keeps the old behavior of
	|             generating a random ID while running tests (where the cache
	|             is usually a per-process array store). 'cache' always claims.
	|             'fixed' never does, and falls back to a random ID for
	|             anything you have not set above.
	|
	| store       Which cache store to claim from. Defaults to your default
	|             store. This needs a store with an atomic "add" operation:
	|             redis (recommended), memcached, dynamodb, database or file.
	|             The array store is NOT safe across processes.
	|
	| ttl         How long a claim lasts, in seconds. Bits renews it as it
	|             generates IDs, and gives it back when the process exits. A
	|             process that dies without cleaning up holds its identity for
	|             at most this long.
	|
	| prefix      Cache key prefix for claims.
	|
	| max_probes  How many identities to try in one pass before giving up.
	|
	| attempts    How many passes to make before throwing.
	|
	*/
	
	'identity' => [
		'driver' => env('BITS_IDENTITY_DRIVER', 'auto'),
		
		'store' => env('BITS_IDENTITY_STORE'),
		
		'ttl' => env('BITS_IDENTITY_TTL', 300),
		
		'prefix' => env('BITS_IDENTITY_PREFIX', 'glhd-bits-id'),
		
		'max_probes' => env('BITS_IDENTITY_MAX_PROBES', 1024),
		
		'attempts' => env('BITS_IDENTITY_ATTEMPTS', 3),
	],
	
	/*
	|--------------------------------------------------------------------------
	| Sequence Resolver
	|--------------------------------------------------------------------------
	|
	| The sequence segment tells apart IDs generated in the same instant by
	| the same worker. A process holding an identity nobody else can use only
	| needs to count in memory; a process sharing a configured worker ID has
	| to coordinate through the cache.
	|
	| 'auto' picks between them for you. Use 'memory' or 'cache' to decide
	| yourself.
	|
	*/
	
	'sequence_resolver' => env('BITS_SEQUENCE_RESOLVER', 'auto'),
];
