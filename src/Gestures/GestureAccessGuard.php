<?php
declare(strict_types=1);

namespace Gestures;

use App\Response;
use I18n\I18n;
use Modules\ModuleEntitlementService;
use Modules\ModuleRegistry;
use Repos\UserFeatureAccessRepo;
use RuntimeException;

final class GestureAccessGuard
{
    private const JOB_GESTURES = [
        'podcast' => 'podcast-from-article',
        'audio-transcribe' => 'audio-transcriber',
        'lead-finder' => 'lead-finder',
    ];

    private readonly UserFeatureAccessRepo $permissions;
    private readonly ModuleEntitlementService $entitlements;

    public function __construct(
        ?UserFeatureAccessRepo $permissions = null,
        ?ModuleEntitlementService $entitlements = null
    ) {
        $this->permissions = $permissions ?? new UserFeatureAccessRepo();
        $this->entitlements = $entitlements ?? ModuleEntitlementService::current();
    }

    /** @return list<string> */
    public static function supportedGestures(): array
    {
        static $supported = null;
        if (is_array($supported)) {
            return $supported;
        }
        $gestures = [];
        foreach (ModuleRegistry::defaults()->all() as $definition) {
            foreach ($definition->capabilities() as $capability) {
                if (str_starts_with($capability, 'gesture:') && $capability !== 'gesture:*') {
                    $gestures[] = substr($capability, strlen('gesture:'));
                }
            }
        }
        sort($gestures);
        $supported = $gestures;
        return $supported;
    }

    public static function gestureForJobType(string $jobType): ?string
    {
        return self::JOB_GESTURES[$jobType] ?? null;
    }

    /** @param array<string, mixed> $inputData */
    public static function captureJobRequirement(string $jobType, array $inputData): array
    {
        $gesture = self::gestureForJobType($jobType);
        if ($gesture === null) {
            throw new RuntimeException('Unknown background job capability');
        }
        $inputData['required_module'] = 'gesture.' . $gesture;
        return $inputData;
    }

    /** @param array<string, mixed> $user */
    public function requireApi(array $user, string $gesture): void
    {
        if (!in_array($gesture, self::supportedGestures(), true)
            || !$this->entitlements->isCapabilityEnabled('gesture', $gesture)) {
            Response::error('feature_unavailable', I18n::translate('access.feature_unavailable'), 404);
        }

        if (!$this->permissions->hasGestureAccess((int)($user['id'] ?? 0), $gesture)) {
            Response::error('forbidden', I18n::translate('access.feature_forbidden'), 403);
        }
    }

    /** @param array<string, mixed> $user @param list<string>|null $allowed */
    public function requireDynamicApi(array $user, string $gesture, ?array $allowed = null): void
    {
        if ($allowed !== null && !in_array($gesture, $allowed, true)) {
            Response::error('feature_unavailable', I18n::translate('access.feature_unavailable'), 404);
        }
        $this->requireApi($user, $gesture);
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $execution */
    public function requireExecutionApi(array $user, array $execution): void
    {
        $this->requireDynamicApi($user, (string)($execution['gesture_type'] ?? ''));
    }

    /** @param array<string, mixed> $user @param list<array<string, mixed>> $executions */
    public function filterExecutions(array $user, array $executions): array
    {
        return array_values(array_filter(
            $executions,
            fn(array $execution): bool => $this->canAccess((int)($user['id'] ?? 0), (string)($execution['gesture_type'] ?? ''))
        ));
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $job */
    public function requireJobApi(array $user, array $job): void
    {
        $gesture = $this->validatedJobGesture($job);
        if ($gesture === null) {
            Response::error('feature_unavailable', I18n::translate('access.feature_unavailable'), 404);
        }
        $this->requireApi($user, $gesture);
    }

    /** @param array<string, mixed> $user @param list<array<string, mixed>> $jobs */
    public function filterJobs(array $user, array $jobs): array
    {
        return array_values(array_filter($jobs, function (array $job) use ($user): bool {
            $gesture = $this->validatedJobGesture($job);
            return $gesture !== null && $this->canAccess((int)($user['id'] ?? 0), $gesture);
        }));
    }

    /** @param array<string, mixed> $job */
    public function requireJobWorker(array $job): string
    {
        $gesture = $this->validatedJobGesture($job);
        if ($gesture === null || !$this->canAccess((int)($job['user_id'] ?? 0), $gesture)) {
            throw new RuntimeException('feature_unavailable');
        }
        return $gesture;
    }

    private function canAccess(int $userId, string $gesture): bool
    {
        return in_array($gesture, self::supportedGestures(), true)
            && $this->entitlements->isCapabilityEnabled('gesture', $gesture)
            && $this->permissions->hasGestureAccess($userId, $gesture);
    }

    /** @param array<string, mixed> $job */
    private function validatedJobGesture(array $job): ?string
    {
        $gesture = self::gestureForJobType((string)($job['job_type'] ?? ''));
        if ($gesture === null) {
            return null;
        }

        $inputData = is_array($job['input_data'] ?? null) ? $job['input_data'] : [];
        $capturedModule = $inputData['required_module'] ?? null;
        if ($capturedModule !== null && $capturedModule !== 'gesture.' . $gesture) {
            return null;
        }
        return $gesture;
    }

}
