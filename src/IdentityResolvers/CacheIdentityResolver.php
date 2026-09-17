<?php

namespace Glhd\Bits\IdentityResolvers;

use Closure;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Exceptions\IdentitySpaceExhausted;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Sleep;
use InvalidArgumentException;

/**
 * Claims a unique identity from the cache at runtime, for deployments where a process
 * has no worker or datacenter ID assigned to it up front — Lambda and Vapor, Cloud Run,
 * autoscaling containers, anywhere machines come and go.
 *
 * The claim uses Repository::add(), which is a compare-and-set-if-absent: the first
 * process to write a key wins, and every other process is told no. This is the same
 * primitive Laravel's own Cache::lock() is built on, but working one layer down keeps
 * control of the expiration, which the Lock contract does not expose.
 *
 * REDIS IS ASSUMED. Repository::add() only delegates to the store's atomic add() when it
 * is given a TTL, and only some stores implement one: redis (recommended), memcached,
 * dynamodb, database and file. The array store has no add(), so Repository falls back to
 * a read-then-write, which is safe inside a single process (tests) but not across them.
 *
 * Note that each identity gets its own key, rather than there being one shared lock and
 * one list of who holds what. That means claims never queue behind each other, which
 * matters most in exactly the situation this class exists for: a hundred cold starts at
 * once. It does mean two processes could pick the same candidate and one has to move on,
 * which is what IdentitySpace's seeded walk keeps rare.
 */
class CacheIdentityResolver extends IdentityResolver
{
	/**
	 * A claim belongs to the OS process, not to the container, so it is tracked
	 * statically. That matters under Octane, where the container is rebuilt per request
	 * but the process — and so the identity it holds — outlives every one of them.
	 *
	 * @var array<string, \Glhd\Bits\IdentityResolvers\Claim>
	 */
	protected static array $claims = [];
	
	protected static bool $registered_shutdown_handler = false;
	
	protected ?Closure $seed_resolver = null;
	
	protected ?Closure $clock = null;
	
	protected string $scope;
	
	public function __construct(
		protected Repository $cache,
		IdentitySpace $space,
		?string $scope = null,
		protected int $ttl = 300,
		protected string $prefix = 'glhd-bits-id',
		protected int $max_probes = 1024,
		protected int $attempts = 3,
	) {
		if ($ttl < 1) {
			throw new InvalidArgumentException(
				'A claimed identity must expire, so the TTL must be at least 1 second. Without an '
				.'expiration, Repository::add() is no longer atomic, and a process that died would '
				.'hold its identity forever.'
			);
		}
		
		if ($max_probes < 1) {
			throw new InvalidArgumentException('An identity resolver must be allowed at least one probe.');
		}
		
		if ($attempts < 1) {
			throw new InvalidArgumentException('An identity resolver must be allowed at least one attempt.');
		}
		
		parent::__construct($space);
		
		$this->scope = $scope ?? $space->signature();
	}
	
	/** Release every identity this process holds. Registered as a shutdown handler */
	public static function releaseAll(): void
	{
		foreach (static::$claims as $claim) {
			$claim->release();
		}
		
		static::$claims = [];
	}
	
	/**
	 * Forget what this process holds without touching the cache, so that the next call
	 * claims again. Useful in tests to stand in for a second process.
	 */
	public static function forgetClaims(): void
	{
		static::$claims = [];
	}
	
	public function seedUsing(?Closure $callback): static
	{
		$this->seed_resolver = $callback;
		
		return $this;
	}
	
	public function clockUsing(?Closure $callback): static
	{
		$this->clock = $callback;
		
		return $this;
	}
	
	public function release(): void
	{
		$claim = static::$claims[$this->scope] ?? null;
		
		if (null !== $claim) {
			unset(static::$claims[$this->scope]);
			
			$claim->release();
		}
	}
	
	/** The identity this process holds, without claiming one if it holds none */
	public function claimed(): ?WorkerIds
	{
		$claim = static::$claims[$this->scope] ?? null;
		
		return null === $claim ? null : $this->space->at($claim->index);
	}
	
	public function scope(): string
	{
		return $this->scope;
	}
	
	/**
	 * How often we go back to the cache to confirm we still hold our claim. A third of
	 * the TTL means an ID is only ever issued with at least two thirds of the lifetime
	 * left, so the claim cannot have quietly lapsed since we last looked.
	 */
	public function renewalInterval(): int
	{
		return max(1, intdiv($this->ttl, 3));
	}
	
	protected function value(): int
	{
		$claim = static::$claims[$this->scope] ?? null;
		
		if (null !== $claim && $this->stillHeld($claim)) {
			return $claim->index;
		}
		
		unset(static::$claims[$this->scope]);
		
		$claim = static::$claims[$this->scope] = $this->claim();
		
		$this->registerShutdownHandler();
		
		return $claim->index;
	}
	
	protected function stillHeld(Claim $claim): bool
	{
		if (($this->now() - $claim->verified_at) < $this->renewalInterval()) {
			return true;
		}
		
		if (! $claim->renew($this->ttl)) {
			return false;
		}
		
		$claim->verified_at = $this->now();
		
		return true;
	}
	
	protected function claim(): Claim
	{
		$probed = 0;
		
		for ($attempt = 0; $attempt < $this->attempts; $attempt++) {
			[$claim, $probes] = $this->probe();
			
			$probed += $probes;
			
			if (null !== $claim) {
				return $claim;
			}
			
			if ($attempt < ($this->attempts - 1)) {
				Sleep::usleep($this->backoff($attempt));
			}
		}
		
		throw IdentitySpaceExhausted::for($this->scope, $this->space->size(), $probed);
	}
	
	/** @return array{0: \Glhd\Bits\IdentityResolvers\Claim|null, 1: int} */
	protected function probe(): array
	{
		$owner = $this->owner();
		$limit = min($this->space->size(), $this->max_probes);
		$probes = 0;
		
		foreach ($this->space->indexes($this->seed()) as $index) {
			if ($probes >= $limit) {
				break;
			}
			
			$probes++;
			
			$key = $this->key($index);
			
			if ($this->cache->add($key, $owner, $this->ttl)) {
				return [new Claim($this->scope, $index, $key, $owner, $this->cache, $this->now()), $probes];
			}
		}
		
		return [null, $probes];
	}
	
	protected function key(int $index): string
	{
		return $this->prefix.':'.$this->scope.':'.implode('-', $this->space->at($index)->ids);
	}
	
	protected function seed(): int
	{
		return $this->seed_resolver
			? (int) call_user_func($this->seed_resolver, $this->space)
			: random_int(0, PHP_INT_MAX);
	}
	
	protected function owner(): string
	{
		return bin2hex(random_bytes(8)).'-'.getmypid();
	}
	
	protected function now(): float
	{
		return $this->clock
			? (float) call_user_func($this->clock)
			: microtime(true);
	}
	
	/** Spread retries out so that a burst of processes does not march in lockstep */
	protected function backoff(int $attempt): int
	{
		return random_int(1_000, 10_000 * (2 ** $attempt));
	}
	
	protected function registerShutdownHandler(): void
	{
		if (static::$registered_shutdown_handler) {
			return;
		}
		
		static::$registered_shutdown_handler = true;
		
		register_shutdown_function([static::class, 'releaseAll']);
	}
}
