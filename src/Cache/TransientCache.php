<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Cache;

/**
 * Transient Cache Implementation
 *
 * Uses WordPress transients as fallback when object cache is not available.
 *
 * @package Metodo\SchemaMarkupGenerator\Cache
 * @author  Michele Marri <plugins@metodo.dev>
 */
class TransientCache implements CacheInterface
{
    /**
     * Transient prefix
     */
    private const PREFIX = 'smg_';

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
        $value = get_transient($this->prefixKey($key));

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

        // If TTL is 0 (disabled cache), don't store
        if ($ttl === 0) {
            return true;
        }

        return set_transient($this->prefixKey($key), $value, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return delete_transient($this->prefixKey($key));
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
        global $wpdb;

        // Delete all transients with our prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_' . self::PREFIX) . '%',
                $wpdb->esc_like('_transient_timeout_' . self::PREFIX) . '%'
            )
        );

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
     * Prefix cache key
     */
    private function prefixKey(string $key): string
    {
        // Transient names must be 172 characters or fewer
        $prefixedKey = self::PREFIX . $key;

        if (strlen($prefixedKey) > 172) {
            $prefixedKey = self::PREFIX . md5($key);
        }

        return $prefixedKey;
    }
}

