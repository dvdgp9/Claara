<?php

declare(strict_types=1);

namespace Connectors;

/**
 * User-facing import failure (unsupported type, too large, not found...).
 * The code is a stable machine key; the message is safe to show in the UI.
 */
class ConnectorImportException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
