<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$scriptPath = $root . '/scripts/ops/verify_instance_isolation.sh';
$source = file_get_contents($scriptPath) ?: '';
$passed = 0;
$failed = 0;

function isolationContractCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

$requirements = [
    'alpha enables Lead Finder while beta does not' => 'MODULE_ISOLATION_ALPHA_ENABLED',
    'both instances seed normal and superadmin identities' => 'MODULE_ISOLATION_ROLE_MATRIX',
    'both normal users retain a database grant' => 'MODULE_ISOLATION_STALE_GRANT',
    'anonymous direct page and API requests are characterized' => 'MODULE_ISOLATION_ANONYMOUS',
    'enabled normal-user page and API access is exercised' => 'MODULE_ISOLATION_ALPHA_NORMAL',
    'enabled superadmin access is exercised' => 'MODULE_ISOLATION_ALPHA_SUPERADMIN',
    'disabled normal-user page and API access fails closed' => 'MODULE_ISOLATION_BETA_NORMAL',
    'disabled superadmin access fails closed' => 'MODULE_ISOLATION_BETA_SUPERADMIN',
    'enabled queued work completes with the mock provider' => 'MODULE_ISOLATION_ALPHA_JOB',
    'disabled queued work fails before provider execution' => 'MODULE_ISOLATION_BETA_JOB',
    'production database and RAG are fingerprinted' => 'production_qdrant_after',
    'temporary resources are removed through an exit trap' => 'trap cleanup EXIT INT TERM',
];
foreach ($requirements as $label => $needle) {
    isolationContractCheck(str_contains($source, $needle), $label);
}

isolationContractCheck(str_contains($source, 'feature_unavailable'), 'disabled APIs assert the stable unavailable code');
isolationContractCheck(str_contains($source, 'required_module'), 'job ownership is captured server-side in test data');
isolationContractCheck(str_contains($source, 'gesture.lead-finder'), 'the real Lead Finder module is the acceptance capability');

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
