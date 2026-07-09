<?php

declare(strict_types=1);

namespace Connectors;

/**
 * Google-flavoured convenience wrapper over ConnectorTokenService.
 */
class GoogleTokenService extends ConnectorTokenService
{
    public function __construct(
        ?ConnectorTokensRepo $tokensRepo = null,
        ?ConnectorAccountsRepo $accountsRepo = null,
        ?GoogleDriveProvider $provider = null
    ) {
        parent::__construct($provider ?? new GoogleDriveProvider(), $tokensRepo, $accountsRepo);
    }
}
