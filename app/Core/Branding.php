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
        'show_admit_card_print_date' => '1',
        'admit_card_rules_title' => 'বিশেষ নির্দেশাবলিঃ',
        'admit_card_rules' => "১. প্রবেশপত্র (অ্যাডমিট কার্ড) ছাড়া কোনো পরীক্ষার্থীকে পরীক্ষা কক্ষে প্রবেশ করতে দেওয়া হবে না।\n২. প্রবেশপত্র ছাড়া কোনো পরীক্ষার্থী লিখিত পরীক্ষার কাগজ বা বই সঙ্গে রাখতে পারবে না।\n৩. পরীক্ষা শুরুর দুই ঘণ্টার আগে কোনো পরীক্ষার্থী পরীক্ষা কক্ষ থেকে বাইরে যেতে পারবেন না।\n৪. প্রত্যেক পরীক্ষার্থীকে অ্যাসোসিয়েশনের সকল নিয়ম মেনে চলতে হবে। অন্যথায়, তাকে পরীক্ষায় অংশগ্রহণের অনুমতি দেওয়া হবে না।",
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
                $stored = array_intersect_key($row, $defaults);
                $stored = array_filter($stored, static fn($value): bool => $value !== null);
                $defaults = array_merge($defaults, $stored);
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
    (`id`, `authority_name`, `short_name`, `app_name`, `exam_name`, `show_admit_card_print_date`, `admit_card_rules_title`, `admit_card_rules`, `registration_label`, `registration_no`, `address`, `phone`, `email`, `website`, `copyright_text`, `developer_credit`, `chairman_name`, `chairman_title`, `general_secretary_name`, `general_secretary_title`, `controller_name`, `controller_title`, `education_secretary_name`, `education_secretary_title`, `logo_path`, `header_image_path`, `footer_image_path`, `favicon_path`, `watermark_path`, `pattern_path`, `updated_at`)
VALUES
    (:id, :authority_name, :short_name, :app_name, :exam_name, :show_admit_card_print_date, :admit_card_rules_title, :admit_card_rules, :registration_label, :registration_no, :address, :phone, :email, :website, :copyright_text, :developer_credit, :chairman_name, :chairman_title, :general_secretary_name, :general_secretary_title, :controller_name, :controller_title, :education_secretary_name, :education_secretary_title, :logo_path, :header_image_path, :footer_image_path, :favicon_path, :watermark_path, :pattern_path, :updated_at)
ON DUPLICATE KEY UPDATE
    `authority_name`=VALUES(`authority_name`), `short_name`=VALUES(`short_name`), `app_name`=VALUES(`app_name`), `exam_name`=VALUES(`exam_name`),
    `show_admit_card_print_date`=VALUES(`show_admit_card_print_date`),
    `admit_card_rules_title`=VALUES(`admit_card_rules_title`), `admit_card_rules`=VALUES(`admit_card_rules`),
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
