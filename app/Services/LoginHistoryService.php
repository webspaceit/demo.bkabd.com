<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use PDOException;

final class LoginHistoryService
{
    /** Record only completed sign-ins; a logging failure must not block login. */
    public static function recordSuccessfulLogin(array $user, Request $request): void
    {
        try {
            Database::insert('user_login_history', [
                'user_id' => (int) ($user['id'] ?? 0),
                'user_name' => mb_substr((string) ($user['name'] ?? ''), 0, 255),
                'user_email' => mb_substr((string) ($user['email'] ?? ''), 0, 255),
                'ip_address' => mb_substr($request->ip(), 0, 45),
                'user_agent' => mb_substr((string) $request->header('User-Agent', ''), 0, 500),
            ]);
        } catch (PDOException $e) {
            // Keep authentication available if the migration has not yet run.
            error_log('[login-history] record failed: ' . $e->getCode());
        }
    }

    /** @return array{data:array,total:int,current_page:int,per_page:int,last_page:int,from:?int,to:?int} */
    public static function page(int $page = 1, int $perPage = 25, string $search = ''): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $search = trim($search);
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE `user_name` LIKE ? OR `user_email` LIKE ? OR `ip_address` LIKE ?';
            $needle = '%' . $search . '%';
            $params = [$needle, $needle, $needle];
        }
        $total = (int) Database::scalar('SELECT COUNT(*) FROM `user_login_history`' . $where, $params);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            'SELECT `id`, `user_id`, `user_name`, `user_email`, `ip_address`, `user_agent`, `logged_at` FROM `user_login_history`' . $where . ' ORDER BY `logged_at` DESC, `id` DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        $numbers = $lastPage <= 9 ? range(1, $lastPage) : array_values(array_unique(array_filter([1, 2, $page - 1, $page, $page + 1, $lastPage - 1, $lastPage], static fn(int $n): bool => $n >= 1 && $n <= $lastPage)));
        sort($numbers);
        $url = static function (int $number) use ($perPage, $search): string {
            return '/api/login-history?' . http_build_query(['page' => $number, 'per_page' => $perPage, 'search' => $search]);
        };
        $links = [['url' => $page > 1 ? $url($page - 1) : null, 'label' => '« Previous', 'active' => false]];
        $previous = 0;
        foreach ($numbers as $number) {
            if ($previous > 0 && $number > $previous + 1) {
                $links[] = ['url' => null, 'label' => '...', 'active' => false];
            }
            $links[] = ['url' => $url($number), 'label' => (string) $number, 'active' => $number === $page];
            $previous = $number;
        }
        $links[] = ['url' => $page < $lastPage ? $url($page + 1) : null, 'label' => 'Next »', 'active' => false];
        return [
            'data' => $rows,
            'total' => $total,
            'current_page' => min($page, $lastPage),
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'links' => $links,
            'from' => $total === 0 ? null : $offset + 1,
            'to' => $total === 0 ? null : min($offset + $perPage, $total),
        ];
    }
}
