<?php
namespace App;

class Response {
    public static function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $code, string $message, int $status = 400): void {
        self::json([ 'error' => [ 'code' => $code, 'message' => $message ] ], $status);
    }

    /**
     * Error de servidor seguro: loguea internamente, muestra mensaje genérico al cliente
     */
    public static function serverError(string $code, \Throwable $e, string $userMessage = 'Error interno del servidor'): void {
        error_log("[$code] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        self::json([ 'error' => [ 'code' => $code, 'message' => $userMessage ] ], 500);
    }
}
