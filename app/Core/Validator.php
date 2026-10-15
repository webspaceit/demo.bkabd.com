<?php

namespace App\Core;

/**
 * Laravel-style validator.
 *
 * Supported rules: required, nullable, email, string, numeric, integer,
 * boolean, min:N, max:N, same:field, unique:table,column[,ignoreId],
 * exists:table,column, date_format:fmt, image, mimes:jpg,png, in:a,b,c,
 * confirmed, regex:/.../
 *
 * On failure throws ValidationException (handled by App: 422 JSON for Ajax,
 * redirect-with-errors for plain form posts).
 */
final class Validator
{
    /**
     * @param array<string,string> $rules  'email' => 'required|email|unique:users,email'
     * @param array<string,string> $messages Custom messages keyed "field.rule"
     * @return array<string,mixed> Sanitized input (only validated fields)
     * @throws ValidationException
     */
    public static function validate(Request $request, array $rules, array $messages = []): array
    {
        $errors = [];
        $clean = [];
        $data = $request->all();

        foreach ($rules as $field => $ruleString) {
            $ruleList = $ruleString === '' ? [] : explode('|', $ruleString);
            $value = $data[$field] ?? null;
            $isNullable = in_array('nullable', $ruleList, true);
            $isRequired = in_array('required', $ruleList, true);

            // ConvertEmptyStringsToNull (Laravel's default middleware): an
            // unselected <select> or a cleared input posts ''. Rules must see
            // '' as ABSENT so nullable fields store NULL — under strict MySQL
            // (STRICT_TRANS_TABLES) an '' bound to an INT/DATE column is a
            // hard error (1366/1292), and '' would also dodge unique: checks.
            if ($value === '') {
                $value = null;
            }

            if ($value === null || $value === '') {
                if ($isRequired) {
                    $errors[$field][] = self::message('required', $field, $messages, "The $field field is required.");
                } elseif (!$isNullable) {
                    // Not required and missing: skip further rules (Laravel behavior for absent optional fields).
                    $clean[$field] = $value;
                    continue;
                } else {
                    $clean[$field] = $value;
                    continue;
                }
                continue;
            }

            // min/max compare numerically only for numeric fields (Laravel); otherwise by length.
            $numericField = in_array('numeric', $ruleList, true) || in_array('integer', $ruleList, true);
            foreach ($ruleList as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $argStr] = explode(':', $rule, 2);
                    $params = explode(',', $argStr);
                }
                $error = self::applyRule($rule, $params, $field, $value, $request, $messages, $numericField);
                if ($error !== null) {
                    $errors[$field][] = $error;
                    break; // one message per field, like Laravel
                }
            }
            $clean[$field] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $clean;
    }

    private static function applyRule(string $rule, array $params, string $field, mixed $value, Request $request, array $messages, bool $numericField = false): ?string
    {
        $fail = fn(string $default): ?string => self::message($rule, $field, $messages, $default);

        switch ($rule) {
            case 'required':
                return ($value === null || $value === '' || $value === []) ? $fail("The $field field is required.") : null;

            case 'nullable':
            case 'string':
                return null;

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : $fail("The $field must be a valid email address.");

            case 'numeric':
                return is_numeric($value) ? null : $fail("The $field must be a number.");

            case 'integer':
            case 'int':
                return (filter_var($value, FILTER_VALIDATE_INT) !== false) ? null : $fail("The $field must be an integer.");

            case 'boolean':
                return in_array($value, [true, 0, 1, '0', '1', 'true', 'false'], true) ? null : $fail("The $field must be true or false.");

            case 'min':
                $min = (float) ($params[0] ?? 0);
                if ($numericField && is_numeric($value)) {
                    return $value + 0 >= $min ? null : $fail("The $field must be at least $min.");
                }
                return mb_strlen((string) $value) >= $min ? null : $fail("The $field must be at least $min characters.");

            case 'max':
                $max = (float) ($params[0] ?? 0);
                if ($numericField && is_numeric($value)) {
                    return $value + 0 <= $max ? null : $fail("The $field may not be greater than $max.");
                }
                return mb_strlen((string) $value) <= $max ? null : $fail("The $field may not be greater than $max characters.");

            case 'same':
                $other = $params[0] ?? '';
                return ($value === ($request->input($other) ?? null)) ? null : $fail("The $field and $other must match.");

            case 'confirmed':
                return ($value === ($request->input($field . '_confirmation') ?? null)) ? null : $fail("The $field confirmation does not match.");

            case 'in':
                return in_array((string) $value, $params, true) ? null : $fail('The selected ' . $field . ' is invalid.');

            case 'date_format':
                $fmt = $params[0] ?? 'Y-m-d';
                $dt = \DateTime::createFromFormat($fmt, (string) $value);
                return ($dt && $dt->format($fmt) === (string) $value) ? null : $fail("The $field does not match the format $fmt.");

            case 'regex':
                $pattern = $params[0] ?? '';
                return preg_match($pattern, (string) $value) ? null : $fail("The $field format is invalid.");

            case 'image':
                $file = $request->file($field);
                if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    return $fail("The $field must be an image.");
                }
                $info = @getimagesize($file['tmp_name']);
                return $info ? null : $fail("The $field must be an image.");

            case 'mimes':
                $file = $request->file($field);
                if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    return $fail("The $field must be a file of type: " . implode(', ', $params) . '.');
                }
                $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
                return in_array($ext, array_map('strtolower', $params), true)
                    ? null
                    : $fail("The $field must be a file of type: " . implode(', ', $params) . '.');

            case 'unique':
                $table = $params[0] ?? '';
                $column = $params[1] ?? $field;
                $ignoreId = isset($params[2]) ? (int) $params[2] : null;
                if ($table === '') {
                    return null;
                }
                $sql = "SELECT COUNT(*) c FROM `$table` WHERE `$column` = ?" . ($ignoreId ? ' AND id != ?' : '');
                $args = $ignoreId ? [$value, $ignoreId] : [$value];
                $count = (int) (Database::selectOne($sql, $args)['c'] ?? 0);
                return $count === 0 ? null : $fail('The ' . str_replace('_', ' ', $field) . ' has already been taken.');

            case 'exists':
                $table = $params[0] ?? '';
                $column = $params[1] ?? 'id';
                if ($table === '') {
                    return null;
                }
                $count = (int) (Database::selectOne("SELECT COUNT(*) c FROM `$table` WHERE `$column` = ?", [$value])['c'] ?? 0);
                return $count > 0 ? null : $fail('The selected ' . str_replace('_', ' ', $field) . ' is invalid.');

            default:
                return null; // unknown rule: ignore rather than break
        }
    }

    private static function message(string $rule, string $field, array $messages, string $default): string
    {
        return $messages["$field.$rule"]
            ?? $messages[$field]
            ?? str_replace(':attribute', str_replace('_', ' ', $field), $default);
    }
}
