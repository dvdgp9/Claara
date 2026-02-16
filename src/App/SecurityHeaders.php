<?php
namespace App;

/**
 * Security headers para todas las respuestas HTTP
 */
class SecurityHeaders
{
    /**
     * Envía todos los headers de seguridad recomendados
     */
    public static function send(): void
    {
        // Prevenir clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevenir MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Controlar información del referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // HSTS - forzar HTTPS (solo activar si el sitio está en HTTPS)
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // XSS Protection (legacy, pero no hace daño)
        header('X-XSS-Protection: 1; mode=block');
        
        // Permissions Policy - allow microphone for audio recording (SOP voice)
        header("Permissions-Policy: geolocation=(), camera=()");
    }
    
    /**
     * Envía Content Security Policy
     * Separado porque puede variar por página
     */
    public static function sendCsp(array $options = []): void
    {
        $defaultSrc = $options['default-src'] ?? "'self'";
        $scriptSrc = $options['script-src'] ?? "'self' 'unsafe-inline' cdn.tailwindcss.com cdn.jsdelivr.net";
        $styleSrc = $options['style-src'] ?? "'self' 'unsafe-inline' cdn.tailwindcss.com cdn.jsdelivr.net fonts.googleapis.com";
        $fontSrc = $options['font-src'] ?? "'self' fonts.gstatic.com cdn.jsdelivr.net";
        $imgSrc = $options['img-src'] ?? "'self' data: blob:";
        $connectSrc = $options['connect-src'] ?? "'self'";
        $mediaSrc = $options['media-src'] ?? "'self' blob:";
        $frameSrc = $options['frame-src'] ?? "'none'";
        
        $csp = implode('; ', [
            "default-src $defaultSrc",
            "script-src $scriptSrc",
            "style-src $styleSrc",
            "font-src $fontSrc",
            "img-src $imgSrc",
            "connect-src $connectSrc",
            "media-src $mediaSrc",
            "frame-src $frameSrc",
            "base-uri 'self'",
            "form-action 'self'"
        ]);
        
        header("Content-Security-Policy: $csp");
    }
    
    /**
     * Headers para respuestas API JSON
     */
    public static function sendApiHeaders(): void
    {
        self::send();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
}
