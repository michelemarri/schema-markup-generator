<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Cache;

/**
 * Object Cache Implementation
 *
 * Uses WordPress object cache (Redis/Memcached if available).
 *
 * @package flavor\SchemaMarkupGenerator\Cache
 * @author  Michele Marri <info@metodo.dev>
 */
class ObjectCache implements CacheInterface
{
    /**
     * Cache group name
     */
    private const CACHE_GROUP = 'smg_schema';

    /**
     * Default TTL in seconds
     */
    private int $defaultTtl;

    public function __construct(int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        $value = wp_cache_get($this->prefixKey($key), self::CACHE_GROUP);

        if ($value === false) {
            return null;
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $ttl = $ttl > 0 ? $ttl : $this->defaultTtl;

        return wp_cache_set(
            $this->prefixKey($key),
            $value,
            self::CACHE_GROUP,
            $ttl
        );
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return wp_cache_delete($this->prefixKey($key), self::CACHE_GROUP);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @inheritDoc
     */
    public function flush(): bool
    {
        // WordPress object cache doesn't support group flush natively
        // For Redis/Memcached with group support, this would work
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group(self::CACHE_GROUP);
        }

        // Fallback: increment group key to invalidate all cached items
        $groupKey = self::CACHE_GROUP . '_version';
        $version = wp_cache_get($groupKey, self::CACHE_GROUP);
        $newVersion = ($version ?: 0) + 1;
        wp_cache_set($groupKey, $newVersion, self::CACHE_GROUP, 0);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(array $values, int $ttl = 0): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Prefix cache key with version for invalidation
     */
    private function prefixKey(string $key): string
    {
        return $key;
    }
}

