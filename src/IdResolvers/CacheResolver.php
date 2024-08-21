<?php

namespace Glhd\Bits\IdResolvers;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\DateFactory;
use RuntimeException;

class CacheResolver extends IdResolver
{
	protected ?int $value = null;
	
	public function __construct(
		protected Store&LockProvider $cache,
		protected DateFactory $dates,
		protected int $max = 0b1111111111,
	) {
	}
	
	protected function value(): int
	{
		return $this->value ??= $this->acquire();
	}
	
	protected function acquire(): int
	{
		// First we acquire a lock to ensure no other process is reserving an ID. Then we
		// get the current list of reserved IDs, and either find one that hasn't been
		// reserved, or one where the reservation is expired.
		
		return $this->lock(function() {
				$reserved = $this->cache->get('glhd-bits-ids:reserved') ?? [];
				$id = $this->firstAvailable($reserved) ?? $this->findExpired($reserved);
				
				if (null !== $id) {
					$reserved[$id] = $this->dates->now()->addHour()->unix();
					$this->cache->forever('glhd-bits-ids:reserved', $reserved);
					return $id;
				}
				
				throw new RuntimeException('Unable to acquire a unique worker ID.');
			});
	}
	
	protected function release(): void
	{
		if (null === $this->value) {
			return;
		}
		
		$this->lock(function() {
			$reserved = $this->cache->get('glhd-bits-ids:reserved') ?? [];
			
			unset($reserved[$this->value]);
			
			$this->cache->forever('glhd-bits-ids:reserved', $reserved);
		}, 1);
	}
	
	protected function lock(Closure $callback, int $seconds = 5): mixed
	{
		return $this->cache->lock('glhd-bits-ids:lock')->block($seconds, $callback);
	}
	
	protected function firstAvailable(array $reserved): ?int
	{
		for ($id = 0; $id <= $this->max; $id++) {
			if (! isset($reserved[$id])) {
				return $id;
			}
		}
		
		return null;
	}
	
	protected function findExpired(array $reserved): ?int
	{
		$now = $this->dates->now()->unix();
		
		[$_, $id] = collect($reserved)
			->filter(fn($expiration) => $expiration <= $now)
			->reduce(function($carry, $expiration, $id) {
				return $expiration < $carry[0] ? [$expiration, $id] : $carry;
			}, [PHP_INT_MAX, null]);
		
		return $id;
	}
	
	public function __destruct()
	{
		$this->release();
	}
}
