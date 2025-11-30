<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Cache;

/**
 * Cache Interface
 *
 * Contract for cache implementations.
 *
 * @package Metodo\SchemaMarkupGenerator\Cache
 * @author  Michele Marri <plugins@metodo.dev>
 */
interface CacheInterface
{
    /**
     * Get a cached value
     *
     * @param string $key The cache key
     * @return mixed|null The cached value or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Set a cached value
     *
     * @param string $key   The cache key
     * @param mixed  $value The value to cache
     * @param int    $ttl   Time to live in seconds (0 = use default)
     * @return bool True on success
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Delete a cached value
     *
     * @param string $key The cache key
     * @return bool True on success
     */
    public function delete(string $key): bool;

    /**
     * Check if a key exists in cache
     *
     * @param string $key The cache key
     * @return bool True if exists
     */
    public function has(string $key): bool;

    /**
     * Clear all plugin cache
     *
     * @return bool True on success
     */
    public function flush(): bool;

    /**
     * Get multiple cached values
     *
     * @param array $keys Array of cache keys
     * @return array Associative array of key => value
     */
    public function getMultiple(array $keys): array;

    /**
     * Set multiple cached values
     *
     * @param array $values Associative array of key => value
     * @param int   $ttl    Time to live in seconds
     * @return bool True on success
     */
    public function setMultiple(array $values, int $ttl = 0): bool;
}

