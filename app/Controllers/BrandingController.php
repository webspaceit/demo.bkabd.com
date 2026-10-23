<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Branding;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\ValidationException;
use App\Core\View;

final class BrandingController extends Controller
{
    private const TEXT_FIELDS = [
        'authority_name' => 255,
        'short_name' => 100,
        'app_name' => 160,
        'exam_name' => 160,
        'admit_card_rules_title' => 255,
        'admit_card_rules' => 5000,
        'registration_label' => 100,
        'registration_no' => 160,
        'address' => 500,
        'phone' => 255,
        'email' => 255,
        'website' => 500,
        'copyright_text' => 255,
        'developer_credit' => 255,
        'chairman_name' => 160,
        'chairman_title' => 100,
        'general_secretary_name' => 160,
        'general_secretary_title' => 100,
        'controller_name' => 160,
        'controller_title' => 100,
        'education_secretary_name' => 160,
        'education_secretary_title' => 100,
    ];
    private const IMAGE_FIELDS = ['logo', 'header_image', 'footer_image', 'favicon', 'watermark', 'pattern'];

    public function page(Request $request): string
    {
        return View::render('settings.branding', ['title' => 'Branding', 'branding' => Branding::all()], 'layouts.dashboard');
    }

    public function show(Request $request): array
    {
        return ['branding' => Branding::all()];
    }

    public function update(Request $request): array
    {
        $input = $request->all();
        $values = [];
        $errors = [];
        foreach (self::TEXT_FIELDS as $field => $max) {
            if (!array_key_exists($field, $input)) continue;
            $value = trim((string) $input[$field]);
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($length > $max) {
                $errors[$field] = ["May not be greater than {$max} characters."];
                continue;
            }
            $values[$field] = $value;
        }
        if ($errors !== []) throw new ValidationException($errors);
        if ($values === []) throw new HttpException(422, 'Enter at least one branding detail.');

        Branding::update($values);
        return ['status' => 'success', 'branding' => Branding::all()];
    }

    public function upload(Request $request): array
    {
        $role = (string) ($request->input('asset') ?? '');
        if (!in_array($role, self::IMAGE_FIELDS, true)) {
            throw new HttpException(422, 'Choose a valid branding image.');
        }
        $file = $request->file('image');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['image' => ['Choose an image file to upload.']]);
        }
        $errors = [];
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || @getimagesize($file['tmp_name']) === false) {
            $errors['image'] = ['The file must be a valid PNG or JPEG image.'];
        } elseif (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
            $errors['image'] = ['Only PNG and JPEG images are supported.'];
        } elseif ((int) ($file['size'] ?? 0) > 4 * 1024 * 1024) {
            $errors['image'] = ['The image may not be greater than 4 MB.'];
        }
        if ($errors !== []) throw new ValidationException($errors);

        $dir = BASE_PATH . '/public/uploads/branding';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'Could not store the branding image.');
        }
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $filename = $role . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            throw new HttpException(500, 'Could not store the branding image.');
        }
        foreach (['png', 'jpg', 'jpeg'] as $oldExt) {
            if ($oldExt !== $ext) @unlink($dir . '/' . $role . '.' . $oldExt);
        }

        Branding::update([$role . '_path' => '/uploads/branding/' . $filename]);
        return ['status' => 'success', 'branding' => Branding::all()];
    }
}
