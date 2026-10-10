<?php

namespace App\Core;

/**
 * Two-tier cache: per-request memo + persistent driver (file or APCu).
 *
 * Keys follow the convention "prefix:suffix" (e.g. "center_list:4").
 * Prefix invalidation ("the center list changed") bumps a per-prefix
 * generation counter instead of deleting entries one by one — cheap for
 * both the file driver (one small file per prefix) and APCu.
 */
final class Cache
{
    /** @var array<string,mixed> Per-request memo */
    private static array $memo = [];
    /** @var array<string,int> Loaded generations */
    private static array $gens = [];
    private static ?string $driver = null;

    private static function driver(): string
    {
        if (self::$driver === null) {
            $d = Config::get('CACHE_DRIVER', 'file');
            self::$driver = ($d === 'apcu' && function_exists('apcu_fetch')) ? 'apcu' : 'file';
        }
        return self::$driver;
    }

    private static function prefixOf(string $key): string
    {
        $pos = strpos($key, ':');
        return $pos === false ? $key : substr($key, 0, $pos);
    }

    private static function generation(string $prefix): int
    {
        if (isset(self::$gens[$prefix])) {
            return self::$gens[$prefix];
        }
        if (self::driver() === 'apcu') {
            $gen = apcu_fetch('bkabd_gen:' . $prefix, $ok);
            self::$gens[$prefix] = $ok ? (int) $gen : 0;
        } else {
            $file = BASE_PATH . '/storage/cache/_gen_' . sha1($prefix);
            self::$gens[$prefix] = is_file($file) ? (int) file_get_contents($file) : 0;
        }
        return self::$gens[$prefix];
    }

    private static function storageKey(string $key): string
    {
        return 'g' . self::generation(self::prefixOf($key)) . ':' . $key;
    }

    private static function file(string $storageKey): string
    {
        return BASE_PATH . '/storage/cache/' . sha1($storageKey) . '.cache';
    }

    /**
     * Get a cached value; compute + store when missing.
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $stored = self::fetch($key);
        if ($stored !== null) {
            return self::$memo[$key] = $stored;
        }
        $value = $callback();
        self::put($key, $value, $ttl);
        return $value;
    }

    public static function rememberForever(string $key, callable $callback): mixed
    {
        return self::remember($key, 0, $callback);
    }

    private static function fetch(string $key): mixed
    {
        $sk = self::storageKey($key);
        if (self::driver() === 'apcu') {
            $entry = apcu_fetch('bkabd:' . $sk, $ok);
            return $ok && is_array($entry) && array_key_exists('v', $entry) ? $entry['v'] : null;
        }
        $file = self::file($sk);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        $entry = $raw === false ? null : @unserialize($raw);
        if (!is_array($entry) || !array_key_exists('v', $entry)) {
            return null;
        }
        if ($entry['e'] !== 0 && $entry['e'] < time()) {
            @unlink($file);
            return null;
        }
        return $entry['v'];
    }

    public static function put(string $key, mixed $value, int $ttl): void
    {
        self::$memo[$key] = $value;
        $entry = ['v' => $value, 'e' => $ttl > 0 ? time() + $ttl : 0];
        $sk = self::storageKey($key);
        if (self::driver() === 'apcu') {
            apcu_store('bkabd:' . $sk, $entry, $ttl > 0 ? $ttl : 0);
            return;
        }
        $file = self::file($sk);
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        if (@file_put_contents($tmp, serialize($entry), LOCK_EX) !== false) {
            @rename($tmp, $file); // atomic replace
        } else {
            @unlink($tmp);
        }
    }

    /** Invalidate one exact key. */
    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
        $sk = self::storageKey($key);
        if (self::driver() === 'apcu') {
            apcu_delete('bkabd:' . $sk);
            return;
        }
        @unlink(self::file($sk));
    }

    /**
     * Invalidate every key under a prefix ("center_list:...").
     * Bumps the generation counter — all old entries become unreachable
     * instantly and expire naturally by TTL.
     */
    public static function forgetPrefix(string $prefix): void
    {
        $newGen = self::generation($prefix) + 1;
        self::$gens[$prefix] = $newGen;
        if (self::driver() === 'apcu') {
            apcu_store('bkabd_gen:' . $prefix, $newGen, 0);
        } else {
            @file_put_contents(BASE_PATH . '/storage/cache/_gen_' . sha1($prefix), (string) $newGen, LOCK_EX);
        }
        foreach (array_keys(self::$memo) as $k) {
            if (self::prefixOf($k) === $prefix) {
                unset(self::$memo[$k]);
            }
        }
    }

    public static function flush(): void
    {
        self::$memo = [];
        self::$gens = [];
        foreach (glob(BASE_PATH . '/storage/cache/*.cache') ?: [] as $f) {
            @unlink($f);
        }
        if (self::driver() === 'apcu') {
            apcu_clear_cache();
        }
    }
}
