<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Query;
use App\Core\ValidationException;
use App\Models\Student;

/**
 * Student registration domain rules: field validation contracts, duplicate
 * detection and the registration-number algorithm (ported from the original
 * StudentController).
 */
class StudentService
{
    /** Validation rules for creating a student (original store() rules). */
    public static function storeRules(): array
    {
        return [
            'name'            => 'required|max:255',
            'father_name'     => 'required|max:255',
            'mother_name'     => 'required|max:255',
            'phone'           => 'required|max:255',
            'gender'          => 'required|max:255',
            'date_of_birth'   => 'required|max:255',
            'session'         => 'required',
            'class'           => 'required|integer',
            'roll'            => 'required|integer',
            'center_id'       => 'required|integer',
            'institute_id'    => 'required|integer',
            'permanent_address' => 'max:255',
            'present_address' => 'required|max:255',
            'image'           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:1024',
        ];
    }

    /** Custom messages for the create rules (original store() messages). */
    public static function storeMessages(): array
    {
        return [
            'name.required'            => 'The name field is required.',
            'father_name.required'     => 'The father name field is required.',
            'mother_name.required'     => 'The mother name field is required.',
            'phone.required'           => 'The phone field is required.',
            'gender.required'          => 'The gender field is required.',
            'date_of_birth.required'   => 'The date of birth field is required.',
            'session.required'         => 'The session field is required.',
            'class.required'           => 'The class field is required.',
            'class.integer'            => 'The class must be an integer.',
            'roll.required'            => 'The roll field is required.',
            'roll.integer'             => 'The roll must be an integer.',
            'center_id.required'       => 'The center id field is required.',
            'center_id.integer'        => 'The center id must be an integer.',
            'institute_id.required'    => 'The institute id field is required.',
            'institute_id.integer'     => 'The institute id must be an integer.',
            'present_address.required' => 'The present address field is required.',
        ];
    }

    /**
     * Validation rules for updating a student (original update() rules plus
     * nullable pass-through fields the form may send).
     */
    public static function updateRules(): array
    {
        return [
            'name'              => 'required|max:255',
            'father_name'       => 'required|max:255',
            'mother_name'       => 'required|max:255',
            'phone'             => 'required|max:255',
            'gender'            => 'required|max:255',
            'date_of_birth'     => 'required|max:255',
            'session'           => 'required',
            'class'             => 'required|integer',
            'roll'              => 'required|integer',
            'center_id'         => 'required|integer',
            'institute_id'      => 'required|integer',
            'present_address'   => 'required|max:255',
            'permanent_address' => 'nullable|max:255',
            'religion'          => 'nullable|max:255',
            'education_type'    => 'nullable|max:255',
            'email'             => 'nullable|max:255',
            'note'              => 'nullable|max:255',
            'union_id'          => 'nullable|integer',
            'photo'             => 'nullable|max:255',
        ];
    }

    /** The original update() carried no custom messages. */
    public static function updateMessages(): array
    {
        return [];
    }

    /** Run the create-validation contract. Returns the cleaned payload. */
    public function validateStore(array $input): array
    {
        return \App\Core\Validator::validate($input, self::storeRules(), self::storeMessages());
    }

    /** Run the update-validation contract. Returns the cleaned payload. */
    public function validateUpdate(array $input): array
    {
        return \App\Core\Validator::validate($input, self::updateRules(), self::updateMessages());
    }

    /**
     * Same uniqueness rule as the original: a student with the same phone,
     * father name, name, session, class, center and institute is a duplicate.
     */
    public function isDuplicate(array $data): bool
    {
        return Student::query()
            ->where('phone', (string) ($data['phone'] ?? ''))
            ->where('father_name', (string) ($data['father_name'] ?? ''))
            ->where('name', (string) ($data['name'] ?? ''))
            ->where('session', (string) ($data['session'] ?? ''))
            ->where('class', (string) (int) ($data['class'] ?? 0))
            ->where('center_id', (string) (int) ($data['center_id'] ?? 0))
            ->where('institute_id', (string) (int) ($data['institute_id'] ?? 0))
            ->count() > 0;
    }

    /**
     * Registration number for a new student.
     *
     * Base form (when nothing exists yet):  <session-yy><center:03><institute:04><class>001
     * Otherwise: last registration for the same session + class + center +
     * institute, incremented by one (pure integer increment, as in the
     * original). Non-numeric legacy values fall back to the base form.
     */
    public function nextRegistration(string $session, int $class, int $centerId, int $instituteId): string
    {
        $last = Student::query()
            ->where('session', $session)
            ->where('class', (string) $class)
            ->where('center_id', (string) $centerId)
            ->where('institute_id', (string) $instituteId)
            ->orderBy('registration', 'desc')
            ->first();

        $lastReg = is_array($last) ? ($last['registration'] ?? null) : null;
        if (is_numeric($lastReg)) {
            return (string) ((int) $lastReg + 1);
        }

        return substr($session, -2)
            . sprintf('%03u', $centerId)
            . sprintf('%04u', $instituteId)
            . $class
            . '001';
    }

    /**
     * Validate and store an uploaded photo file.
     *
     * Mirrors the original `image` + `image|mimes:jpeg,png,jpg,gif|max:<maxKb>`
     * rule with the framework's ValidationException (422 JSON). An absent file
     * (no file, or UPLOAD_ERR_NO_FILE) yields an empty string. The file lands
     * in public/uploads/students/ with a collision-proof name and is served as
     * a static file by the PHP dev server.
     *
     * @param array<string> $mimes Allowed extensions (lowercase).
     * @return string Stored file name (not a path).
     */
    public function saveUploadedImage(?array $file, string $field, int $maxKb, array $mimes): string
    {
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        $errors = [];

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[$field][] = 'The ' . $field . ' failed to upload.';
        } elseif (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[$field][] = 'The ' . $field . ' failed to upload.';
        } else {
            $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, $mimes, true)) {
                $errors[$field][] = 'The ' . $field . ' must be a file of type: ' . implode(', ', $mimes) . '.';
            } elseif ((int) ($file['size'] ?? 0) > $maxKb * 1024) {
                $errors[$field][] = 'The ' . $field . ' may not be greater than ' . $maxKb . ' kilobytes.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/students';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'Unable to create the upload directory.');
        }

        $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            throw new HttpException(500, 'Unable to store the uploaded file.');
        }

        return $name;
    }

    /**
     * Resolve the photo column value for an update payload.
     *
     * Contract (original edit.vue): the client sends either a data-URL picked
     * by the file input, a bare file name produced by POST /api/uploadFile, or
     * nothing/a cleared value. When the value is unchanged (or empty) the
     * current photo is kept, so a value loaded from the record and submitted
     * back untouched never rewrites the column. Replacing a photo removes the
     * previous file from disk.
     *
     * @return string Photo column value to persist.
     */
    public function savePhotoInput(?string $photo, ?string $current): string
    {
        $current = $current ?? '';
        $photo = trim($photo ?? '');

        // Unchanged or cleared: keep the stored photo.
        if ($photo === '' || $photo === $current) {
            return $current;
        }

        if (stripos($photo, 'data:image/') === 0) {
            $name = $this->storeBase64Photo($photo);
        } else {
            $name = $this->adoptExistingPhoto($photo);
        }

        if ($current !== '' && $current !== $name) {
            $this->deletePhoto($current);
        }

        return $name;
    }

    /** Decode and persist a base64 data-URL photo. */
    private function storeBase64Photo(string $photo): string
    {
        $parts = explode(',', $photo, 2);
        if (count($parts) !== 2) {
            throw new ValidationException(['photo' => ['The photo is invalid.']]);
        }

        $data = base64_decode($parts[1], true);
        $info = $data === false ? false : @getimagesizefromstring($data);
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if ($info === false || !in_array((int) $info[2], $allowed, true)) {
            throw new ValidationException(['photo' => ['The photo must be a jpeg, png, jpg or gif image.']]);
        }

        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'][(int) $info[2]];
        $dir = dirname(__DIR__, 2) . '/public/uploads/students';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'Unable to create the upload directory.');
        }

        $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $data) === false) {
            throw new HttpException(500, 'Unable to store the uploaded file.');
        }

        return $name;
    }

    /**
     * Accept a bare file name produced by POST /api/uploadFile (validated:
     * basename only, known extension, file must exist on disk).
     */
    private function adoptExistingPhoto(string $photo): string
    {
        $name = basename($photo);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($name !== $photo || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            throw new ValidationException(['photo' => ['The photo must be a file of type: jpg, jpeg, png, gif.']]);
        }

        if (!is_file(dirname(__DIR__, 2) . '/public/uploads/students/' . $name)) {
            throw new ValidationException(['photo' => ['The selected photo is invalid.']]);
        }

        return $name;
    }

    /** Remove a stored photo file (silently ignores a missing file). */
    public function deletePhoto(?string $photo): void
    {
        $photo = trim((string) $photo);
        if ($photo === '') {
            return;
        }

        $base = basename($photo);
        $path = dirname(__DIR__, 2) . '/public/uploads/students/' . $base;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
