<?php

namespace Glhd\Bits\SequenceResolvers;

use Glhd\Bits\Contracts\ResolvesSequences;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Redis;
use RedisCluster;

class CacheSequenceResolver implements ResolvesSequences
{
	public function __construct(
		protected Repository $cache,
	) {
	}

	public function next(int $timestamp): int
	{
		$key = "glhd-bits-seq:{$timestamp}";

		$this->withoutSerializationOrCompression(
			fn() => $this->cache->add($key, 0, now()->addSeconds(10)),
		);

		return $this->cache->increment($key) - 1;
	}

	protected function withoutSerializationOrCompression(callable $callback)
	{
		// This is a copied from `RateLimiter::withoutSerializationOrCompression` and
		// `PacksPhpRedisValues::withoutSerializationOrCompression` for backwards-compatibility
		// reasons (the feature wasn't added until Laravel 11.41.0).

		$store = $this->cache->getStore();

		if (! $store instanceof RedisStore) {
			return $callback();
		}

		$connection = $store->connection();

		if (! $connection instanceof PhpRedisConnection) {
			return $callback();
		}

		$client = $connection->client();

		$old_serializer = null;
		if ($this->serialized($client)) {
			$old_serializer = $client->getOption(Redis::OPT_SERIALIZER);
			$client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
		}

		$old_compressor = null;
		if ($this->compressed($client)) {
			$old_compressor = $client->getOption(Redis::OPT_COMPRESSION);
			$client->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE);
		}

		try {
			return $callback();
		} finally {
			if (null !== $old_serializer) {
				$client->setOption(Redis::OPT_SERIALIZER, $old_serializer);
			}
			
			if (null !== $old_compressor) {
				$client->setOption(Redis::OPT_COMPRESSION, $old_compressor);
			}
		}
	}

	public function serialized(Redis|RedisCluster $client): bool
	{
		return defined('Redis::OPT_SERIALIZER')
			&& $client->getOption(Redis::OPT_SERIALIZER) !== Redis::SERIALIZER_NONE;
	}

	public function compressed(Redis|RedisCluster $client): bool
	{
		return defined('Redis::OPT_COMPRESSION')
			&& $client->getOption(Redis::OPT_COMPRESSION) !== Redis::COMPRESSION_NONE;
	}
}
