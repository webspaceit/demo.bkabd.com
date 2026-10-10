<?php

namespace App\Services;

use App\Core\Session;

/**
 * Server-side math captcha (a + b) for the public result portal.
 *
 * Original (resources/views/result.blade.php) generated the sum in JavaScript
 * and posted the answer back in a hidden `captcha_answer` field, so the
 * `same:captcha_answer` rule could be forged by anyone. Here the answer lives
 * only in the session; the client only ever sees the equation.
 */
final class CaptchaService
{
    private const SESSION_KEY = 'result_captcha_answer';

    /**
     * Issue a fresh problem for the search form and return the equation
     * text to display (e.g. "7+8"). The answer never leaves the server.
     */
    public static function issue(): string
    {
        $a = random_int(1, 10);
        $b = random_int(1, 10);
        Session::put(self::SESSION_KEY, $a + $b);
        return $a . '+' . $b;
    }

    /**
     * One-shot read of the stored answer — call before Session::close() so
     * the value is captured without keeping the session file lock later.
     * Returns null when no problem was issued (e.g. forged POST without the form).
     */
    public static function consume(): ?int
    {
        $answer = Session::get(self::SESSION_KEY);
        Session::forget(self::SESSION_KEY);
        return $answer === null ? null : (int) $answer;
    }

    /** Compare the posted value against the consumed answer. */
    public static function isCorrect(?int $expected, mixed $given): bool
    {
        return $expected !== null
            && is_numeric($given)
            && (int) $given === $expected;
    }
}
