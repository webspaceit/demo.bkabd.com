<?php

namespace App\Core;

/**
 * Fixed-window rate limiter backed by flock-atomic file counters.
 *
 * Deliberately light — no DB, session or APCUs writes — so it survives the
 * ~10k-concurrent result-portal flash crowd. LOCK_EX serializes the
 * read-modify-write per key, so concurrent requests can't lose counts.
 *
 * Usage:
 *   RateLimiter::hit('login', 10, 900);                 // 10 tries / 15 min per IP
 *   RateLimiter::hit('sms:user:' . $id, 100, 86400);    // 100 / day per subject
 *   RateLimiter::clear('login', $ip);                   // reset on success
 *
 * The key may itself carry the subject (email / user id / phone); the IP is
 * appended automatically unless one is passed explicitly.
 */
final class RateLimiter
{
    private static ?string $dir = null;

    /**
     * Count one attempt for $key in the fixed window and throw on overflow.
     *
     * @param int $max    maximum attempts inside one window
     * @param int $window window length in seconds
     * @param string|null $ip scope — defaults to the request IP
     * @throws HttpException 429 when the window is exhausted
     */
    public static function hit(string $key, int $max, int $window = 60, ?string $ip = null): void
    {
        $subject = $ip ?? Request::capture()->ip();
        $now = time();
        $file = self::dir() . '/' . sha1($key . '|' . $subject) . '.json';

        // Fail open: if the cache dir is not writable, never block legit traffic.
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            rewind($fp);
            $row = json_decode($raw === false ? '' : $raw, true);
            $start = $now;
            $count = 0;
            if (is_array($row) && isset($row['start'], $row['count']) && $now - (int) $row['start'] < $window) {
                $start = (int) $row['start'];
                $count = (int) $row['count'];
            }
            $count++;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode(['start' => $start, 'count' => $count, 'ip' => $subject]));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }

        if ($count > $max) {
            throw new HttpException(429, 'Too many attempts. Please try again later.');
        }
    }

    /** Reset the counter for $key (e.g. after a successful login). */
    public static function clear(string $key, ?string $ip = null): void
    {
        $subject = $ip ?? Request::capture()->ip();
        @unlink(self::dir() . '/' . sha1($key . '|' . $subject) . '.json');
    }

    private static function dir(): string
    {
        if (self::$dir === null) {
            $dir = BASE_PATH . '/storage/cache/ratelimit';
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                $dir = sys_get_temp_dir(); // last resort — counters still work
            }
            self::$dir = $dir;
        }
        return self::$dir;
    }
}