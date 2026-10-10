<?php

namespace App\Core;

/**
 * Application kernel: boot session, load routes, dispatch, convert
 * exceptions to JSON (Ajax) or HTML (pages).
 */
final class App
{
    private static ?App $instance = null;

    public static function boot(): App
    {
        return self::$instance ??= new self();
    }

    public function run(): void
    {
        Session::start();

        try {
            require BASE_PATH . '/routes.php';
            Router::router()->dispatch(Request::capture());
        } catch (ValidationException $e) {
            $this->handleValidation($e);
        } catch (HttpException $e) {
            $this->handleHttp($e);
        } catch (\PDOException $e) {
            error_log('[db] ' . Database::redact($e->getMessage()));
            $this->handleHttp(new HttpException(500, 'Database error'));
        } catch (\Throwable $e) {
            error_log('[error] ' . Database::redact($e->getMessage()) . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (Config::getBool('APP_DEBUG', false)) {
                $this->debugPage($e);
            } else {
                $this->handleHttp(new HttpException(500, 'Server error'));
            }
        }
    }

    private function handleValidation(ValidationException $e): void
    {
        $request = Request::capture();
        if ($request->expectsJson()) {
            Response::validationError($e->errors, $e->getMessage());
        }
        // Plain form post: redirect back with errors + old input (Laravel style).
        Session::putErrors($e->errors);
        Session::flash('old', $request->all());
        $referer = $request->header('Referer');
        $back = $referer ? (parse_url($referer, PHP_URL_PATH) ?: '/') : '/';
        Response::redirect($back);
    }

    private function handleHttp(HttpException $e): void
    {
        $request = Request::capture();
        $status = $e->status();

        if ($request->expectsJson() || str_starts_with($request->path(), '/api/')) {
            $payload = ['message' => $e->getMessage()];
            if ($e->errors !== []) {
                $payload['errors'] = $e->errors;
            }
            if ($status === 401) {
                $payload = ['message' => 'Unauthenticated.'];
            }
            Response::json($payload, $status);
        }

        // Web responses.
        if ($status === 401) {
            Response::redirect('/login');
        }
        Response::html(View::render('errors.error', [
            'status' => $status,
            'title' => match ($status) {
                403 => 'Access denied',
                404 => 'Page not found',
                405 => 'Method not allowed',
                default => 'Error',
            },
            'message' => $e->getMessage(),
        ], 'layouts.guest'), $status);
    }

    private function debugPage(\Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $e->getMessage() . "\n\n" . $e->getTraceAsString();
        exit;
    }
}
