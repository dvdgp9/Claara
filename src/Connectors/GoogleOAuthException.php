<?php

declare(strict_types=1);

namespace Connectors;

class GoogleOAuthException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 0
    ) {
        parent::__construct($message);
    }

    public function isInvalidGrant(): bool
    {
        return $this->errorCode === 'invalid_grant';
    }
}
