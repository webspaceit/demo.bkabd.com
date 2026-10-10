<?php

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/**
 * Institute file storage + registration card PDF (original
 * InstituteController image handling and instituteregistrationpdf()).
 *
 * Uploads land in the same public paths as the original app
 * (/images/logos, /images/principals) but without the Intervention/Image
 * resize dependency — names are time_random.ext so two uploads in the
 * same second cannot overwrite each other (original time().ext could).
 */
final class InstituteService
{
    public const LOGO_DIR = 'images/logos';
    public const PRINCIPAL_DIR = 'images/principals';
    /** Same ceiling as the original rules (max:1024 => 1024 KB). */
    public const MAX_BYTES = 1024 * 1024;
    public const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    /**
     * Persist an uploaded file ($_FILES entry).
     * Returns the stored file name, or null when no file was submitted.
     *
     * @param array<string,mixed>|null $file
     * @throws ValidationException on upload/size/type failures (422 like Laravel)
     */
    public function saveUpload(?array $file, string $dir, string $field): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException([$field => ["The $field failed to upload."]]);
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new ValidationException([$field => ["The $field may not be greater than 1024 kilobytes."]]);
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpeg', 'jpg', 'png', 'gif'], true)) {
            throw new ValidationException([$field => ["The $field must be a file of type: jpeg, png, jpg, gif."]]);
        }
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new ValidationException([$field => ["The $field must be an image."]]);
        }
        $name = $this->uniqueName($ext);
        $this->ensureDir($dir);
        if (!move_uploaded_file((string) $file['tmp_name'], BASE_PATH . "/public/$dir/$name")) {
            throw new ValidationException([$field => ["The $field failed to save."]]);
        }
        return $name;
    }

    /**
     * Persist a base64 data URI (the original edit form sent images this
     * way and the controller re-encoded them). Returns the stored file
     * name, or null when empty.
     *
     * @throws ValidationException on size/type/base64 failures
     */
    public function saveDataUri(?string $dataUri, string $dir, string $field): ?string
    {
        if ($dataUri === null || trim($dataUri) === '') {
            return null;
        }
        if (!preg_match('#^data:(image/(?:jpeg|png|gif));base64,#i', $dataUri, $m)) {
            throw new ValidationException([$field => ["The $field must be an image."]]);
        }
        $binary = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);
        if ($binary === false) {
            throw new ValidationException([$field => ["The $field is not valid base64 image data."]]);
        }
        if (strlen($binary) > self::MAX_BYTES) {
            throw new ValidationException([$field => ["The $field may not be greater than 1024 kilobytes."]]);
        }
        if (@getimagesizefromstring($binary) === false) {
            throw new ValidationException([$field => ["The $field must be an image."]]);
        }
        $ext = self::MIME_EXT[strtolower($m[1])];
        $name = $this->uniqueName($ext);
        $this->ensureDir($dir);
        if (file_put_contents(BASE_PATH . "/public/$dir/$name", $binary) === false) {
            throw new ValidationException([$field => ["The $field failed to save."]]);
        }
        return $name;
    }

    /** Remove a stored upload (missing/empty names are ignored). */
    public function delete(?string $name, string $dir): void
    {
        if ($name === null || $name === '') {
            return;
        }
        $path = BASE_PATH . "/public/$dir/" . basename($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    // ---------------------------------------------------------------
    // Registration card (original instituteregistrationpdf)
    // ---------------------------------------------------------------

    /**
     * The joined institute row for the membership registration card
     * (center/user + geo names) — one query, no N+1.
     *
     * @throws \App\Core\HttpException 404 when the institute is missing
     */
    public function registrationCard(int $instituteId): array
    {
        $institute = Database::selectOne(
            "SELECT i.*, c.`id` AS center_id_joined, u.`name` AS center_name,
                    d.`name` AS division_name, dt.`name` AS district_name, uz.`name` AS upazila_name
               FROM `institutes` i
          LEFT JOIN `centers` c ON c.`id` = i.`center_id`
          LEFT JOIN `users` u ON u.`id` = c.`user_id`
          LEFT JOIN `divisions` d ON d.`id` = i.`division_id`
          LEFT JOIN `districts` dt ON dt.`id` = i.`district_id`
          LEFT JOIN `upozilas` uz ON uz.`id` = i.`upazila_id`
              WHERE i.`id` = ?",
            [$instituteId]
        );
        if ($institute === null) {
            throw new \App\Core\HttpException(404, 'Record not found');
        }
        return $institute;
    }

    // ---------------------------------------------------------------

    private function uniqueName(string $ext): string
    {
        return time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    }

    private function ensureDir(string $dir): void
    {
        $path = BASE_PATH . '/public/' . $dir;
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException("Unable to create upload directory: $dir");
        }
    }
}
