<?php

namespace Glhd\Bits\SequenceResolvers;

use Glhd\Bits\Contracts\ResolvesSequences;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Redis;

class CacheSequenceResolver implements ResolvesSequences
{
	public function __construct(
		protected Repository $cache
	) {
	}

	public function next(int $timestamp): int
	{
		$key = "glhd-bits-seq:{$timestamp}";

		$this->withoutSerializationOrCompression(
			fn() => $this->cache->add($key, 0, now()->addSeconds(10))
		);

		return $this->cache->increment($key) - 1;
	}

	protected function withoutSerializationOrCompression(callable $callback)
	{
		$store = $this->cache->getStore();

		if (! $store instanceof RedisStore) {
			return $callback();
		}

		$connection = $store->connection();

		if (! $connection instanceof PhpRedisConnection) {
			return $callback();
		}

		$client = $connection->client();

		$oldSerializer = null;

		if ($this->serialized($client)) {
			$oldSerializer = $client->getOption($client::OPT_SERIALIZER);
			$client->setOption($client::OPT_SERIALIZER, $client::SERIALIZER_NONE);
		}

		$oldCompressor = null;

		if ($this->compressed($client)) {
			$oldCompressor = $client->getOption($client::OPT_COMPRESSION);
			$client->setOption($client::OPT_COMPRESSION, $client::COMPRESSION_NONE);
		}

		try {
			return $callback();
		} finally {
			if ($oldSerializer !== null) {
				$client->setOption($client::OPT_SERIALIZER, $oldSerializer);
			}

			if ($oldCompressor !== null) {
				$client->setOption($client::OPT_COMPRESSION, $oldCompressor);
			}
		}
	}

	public function serialized($client): bool
	{
		return defined('Redis::OPT_SERIALIZER') &&
			$client->getOption(Redis::OPT_SERIALIZER) !== Redis::SERIALIZER_NONE;
	}

	public function compressed($client): bool
	{
		return defined('Redis::OPT_COMPRESSION') &&
			$client->getOption(Redis::OPT_COMPRESSION) !== Redis::COMPRESSION_NONE;
	}
}
