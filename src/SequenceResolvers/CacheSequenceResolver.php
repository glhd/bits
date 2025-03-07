<?php

namespace Glhd\Bits\SequenceResolvers;

use Glhd\Bits\Contracts\ResolvesSequences;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Redis\Connections\PredisConnection;

class CacheSequenceResolver implements ResolvesSequences
{
    public function __construct(
        protected Repository $cache,
    ) {
    }

    public function next(int $timestamp): int
    {
        $key = "glhd-bits-seq:{$timestamp}";

        if ($this->cache->getStore() instanceof RedisStore) {
            return $this->redisSafeIncrement($key, ttl: 10);
        }

        $this->cache->add($key, 0, now()->addSeconds(10));

        return $this->cache->increment($key) - 1;
    }

    protected function redisSafeIncrement(string $key, int $ttl): int
    {
        $store = $this->cache->getStore();
        $key = $store->getPrefix().$key;

        $script = <<<LUA
            local ttl = tonumber(ARGV[1])
            local value = redis.call('INCR', KEYS[1])
            if value == 1 then
                redis.call('EXPIRE', KEYS[1], ttl)
            end
            return value - 1
        LUA;

        $connection = $store->connection();

        if ($connection instanceof PredisConnection) {
            return (int) $connection->client()->eval($script, 1, $key, $ttl);
        }

        return (int) $connection->client()->eval($script, [$key, $ttl], 1);
    }
}
