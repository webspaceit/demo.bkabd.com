<?php

namespace App\Core;

/** Shared, editable authority branding with safe defaults for older installs. */
final class Branding
{
    private static ?array $cache = null;

    private const DEFAULTS = [
        'authority_name' => 'Bangladesh Kindergarten Association',
        'short_name' => 'BKABD',
        'app_name' => 'BKABD Exam',
        'exam_name' => 'Scholarship Examination',
        'registration_label' => 'Govt. Reg. No.',
        'registration_no' => 'S-1028/98',
        'address' => '59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh',
        'phone' => '+88-02-224458009',
        'email' => 'infobkabd@gmail.com, info@bkabd.com',
        'website' => 'www.bkabd.com, www.bka.com.bd, www.bka.org.bd',
        'copyright_text' => 'All Rights Reserved.',
        'developer_credit' => 'Developed by Web Space IT',
        'chairman_name' => 'MONOWARA BHUIYAN',
        'chairman_title' => 'Chairman',
        'general_secretary_name' => 'MD. MIZANUR RAHMAN',
        'general_secretary_title' => 'General Secretary',
        'controller_name' => 'MD. FARUK HOSSEN',
        'controller_title' => 'Controller of Exam',
        'education_secretary_name' => 'MD. ABDUL ALIM',
        'education_secretary_title' => 'Education Secretary',
        'logo_path' => '',
        'header_image_path' => '',
        'footer_image_path' => '',
        'favicon_path' => '',
        'watermark_path' => '',
        'pattern_path' => '',
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = self::DEFAULTS;
        try {
            $row = Database::selectOne('SELECT * FROM `branding_settings` WHERE `id` = 1 LIMIT 1');
            if ($row !== null) {
                $defaults = array_merge($defaults, array_intersect_key($row, $defaults));
            }
        } catch (\Throwable $e) {
            // Existing installations keep their normal defaults until the
            // branding_settings migration is applied.
        }
        return self::$cache = $defaults;
    }

    public static function get(string $key, ?string $fallback = null): string
    {
        $all = self::all();
        return (string) ($all[$key] ?? $fallback ?? '');
    }

    public static function update(array $values): void
    {
        $existing = self::all();
        $row = array_merge($existing, $values, ['id' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        $params = [];
        foreach ($row as $key => $value) {
            $params[':' . $key] = $value;
        }
        $sql = <<<'SQL'
INSERT INTO `branding_settings`
    (`id`, `authority_name`, `short_name`, `app_name`, `exam_name`, `registration_label`, `registration_no`, `address`, `phone`, `email`, `website`, `copyright_text`, `developer_credit`, `chairman_name`, `chairman_title`, `general_secretary_name`, `general_secretary_title`, `controller_name`, `controller_title`, `education_secretary_name`, `education_secretary_title`, `logo_path`, `header_image_path`, `footer_image_path`, `favicon_path`, `watermark_path`, `pattern_path`, `updated_at`)
VALUES
    (:id, :authority_name, :short_name, :app_name, :exam_name, :registration_label, :registration_no, :address, :phone, :email, :website, :copyright_text, :developer_credit, :chairman_name, :chairman_title, :general_secretary_name, :general_secretary_title, :controller_name, :controller_title, :education_secretary_name, :education_secretary_title, :logo_path, :header_image_path, :footer_image_path, :favicon_path, :watermark_path, :pattern_path, :updated_at)
ON DUPLICATE KEY UPDATE
    `authority_name`=VALUES(`authority_name`), `short_name`=VALUES(`short_name`), `app_name`=VALUES(`app_name`), `exam_name`=VALUES(`exam_name`),
    `registration_label`=VALUES(`registration_label`), `registration_no`=VALUES(`registration_no`), `address`=VALUES(`address`), `phone`=VALUES(`phone`),
    `email`=VALUES(`email`), `website`=VALUES(`website`), `copyright_text`=VALUES(`copyright_text`), `developer_credit`=VALUES(`developer_credit`),
    `chairman_name`=VALUES(`chairman_name`), `chairman_title`=VALUES(`chairman_title`), `general_secretary_name`=VALUES(`general_secretary_name`), `general_secretary_title`=VALUES(`general_secretary_title`),
    `controller_name`=VALUES(`controller_name`), `controller_title`=VALUES(`controller_title`), `education_secretary_name`=VALUES(`education_secretary_name`), `education_secretary_title`=VALUES(`education_secretary_title`),
    `logo_path`=VALUES(`logo_path`), `header_image_path`=VALUES(`header_image_path`), `footer_image_path`=VALUES(`footer_image_path`),
    `favicon_path`=VALUES(`favicon_path`), `watermark_path`=VALUES(`watermark_path`), `pattern_path`=VALUES(`pattern_path`), `updated_at`=VALUES(`updated_at`)
SQL;
        Database::statement($sql, $params);
        self::$cache = $row;
    }
}
