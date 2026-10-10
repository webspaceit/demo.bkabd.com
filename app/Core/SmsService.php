<?php

namespace App\Core;

/**
 * MRAM SMS gateway client. API key lives in .env (never in code).
 *
 * Original quirk preserved (see SmsController):
 *   type=1 -> mask sender "BKABD"
 *   type=2 -> numeric sender 8809601002953
 *
 * SMS_DRY_RUN=1 logs instead of sending (safe default for new installs).
 */
final class SmsService
{
    /**
     * @return string|false Gateway response body, or false on transport error
     */
    public static function send(string $number, string $message, bool $useMask): string|false
    {
        $number = trim($number);
        $message = trim($message);
        if ($number === '' || $message === '') {
            return false;
        }

        if (Config::getBool('SMS_DRY_RUN', false)) {
            error_log(sprintf('[sms:dry-run] to=%s sender=%s msg=%s', $number, $useMask ? 'BKABD' : '8809601002953', $message));
            return 'DRY_RUN_OK';
        }

        $data = [
            'api_key' => (string) Config::get('SMS_API_KEY', ''),
            'type' => 'text',
            'contacts' => $number,
            'senderid' => $useMask
                ? (string) Config::get('SMS_SENDER_MASK', 'BKABD')
                : (string) Config::get('SMS_SENDER_NORMAL', '8809601002953'),
            'msg' => $message,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => Config::get('SMS_API_URL', 'https://sms.mram.com.bd/smsapi'),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            error_log('[sms] curl error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }
}
