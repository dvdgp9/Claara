<?php
namespace Claara;

use Repos\ContextDocsRepo;
use Repos\OrganizationResponsibilityRepo;
use Repos\UserFeatureAccessRepo;
use Repos\VoicesRepo;
use Voices\VoiceAccessResolver;
use Throwable;

class CapabilityCatalogService
{
    private const FALLBACK_GESTURE_META = [
        'write-article' => [
            'route' => '/gestos/escribir-articulo.php',
            'when_to_use' => 'Drafting or refining long-form articles from a topic, brief, or source material.',
            'inputs' => 'topic, audience, tone, source notes',
        ],
        'social-media' => [
            'route' => '/gestos/redes-sociales.php',
            'when_to_use' => 'Turning a campaign idea, article, or announcement into social media posts.',
            'inputs' => 'source content, channel, tone, number of posts',
        ],
        'podcast-from-article' => [
            'route' => '/gestos/podcast-articulo.php',
            'when_to_use' => 'Transforming an article, report, or URL into a podcast script or audio workflow.',
            'inputs' => 'article text or URL, podcast style, duration',
        ],
        'image-editor' => [
            'route' => '/gestos/editor-imagenes.php',
            'when_to_use' => 'Editing, adapting, or generating image instructions for visual assets.',
            'inputs' => 'image, edit request, format requirements',
        ],
        'content-repurposer' => [
            'route' => '/gestos/transformador-contenido.php',
            'when_to_use' => 'Converting one piece of content into another format for reuse.',
            'inputs' => 'source content, target format, audience',
        ],
        'sop-generator' => [
            'route' => '/gestos/sop-generator.php',
            'when_to_use' => 'Creating procedures, checklists, operating manuals, or step-by-step SOPs.',
            'inputs' => 'process description, evidence, desired format',
        ],
        'audio-transcriber' => [
            'route' => '/gestos/transcriptor-audio.php',
            'when_to_use' => 'Transcribing audio and extracting structured notes or summaries.',
            'inputs' => 'audio file, summary or extraction goal',
        ],
        'course-creator' => [
            'route' => '/gestos/creador-cursos.php',
            'when_to_use' => 'Designing training courses, lessons, modules, or learning material.',
            'inputs' => 'training objective, audience, duration, source material',
        ],
        'project-admin' => [
            'route' => '/gestos/admin-proyectos.php',
            'when_to_use' => 'Structuring project tasks, timelines, responsibilities, and follow-up actions.',
            'inputs' => 'project goal, stakeholders, dates, constraints',
        ],
        'lead-finder' => [
            'route' => '/gestos/lead-finder.php',
            'when_to_use' => 'Finding or qualifying commercial prospects from a market, sector, or location.',
            'inputs' => 'target market, location, sector, qualification criteria',
        ],
    ];

    private UserFeatureAccessRepo $accessRepo;
    private VoicesRepo $voicesRepo;
    private ?ContextDocsRepo $docsRepo;
    private ?OrganizationResponsibilityRepo $responsibilityRepo;
    private VoiceAccessResolver $accessResolver;

    public function __construct(
        ?UserFeatureAccessRepo $accessRepo = null,
        ?VoicesRepo $voicesRepo = null,
        ?ContextDocsRepo $docsRepo = null,
        ?OrganizationResponsibilityRepo $responsibilityRepo = null,
        ?VoiceAccessResolver $accessResolver = null
    ) {
        $this->accessRepo = $accessRepo ?? new UserFeatureAccessRepo();
        $this->voicesRepo = $voicesRepo ?? new VoicesRepo();
        $this->docsRepo = $docsRepo ?? new ContextDocsRepo();
        $this->responsibilityRepo = $responsibilityRepo ?? new OrganizationResponsibilityRepo();
        $this->accessResolver = $accessResolver ?? new VoiceAccessResolver();
    }

    public function forUser(array $user): array
    {
        $userId = (int)($user['id'] ?? 0);

        return [
            'user' => $this->summarizeUser($user),
            'voices' => $userId > 0 ? $this->getAccessibleVoiceCatalog($userId) : [],
            'gestures' => $userId > 0 ? $this->getAccessibleGestureCatalog($userId) : [],
        ];
    }

    public function toPromptBlock(array $catalog): string
    {
        $lines = [
            '## Claara Internal Capabilities',
            'You know which internal voices and gestures this signed-in user can access. Use this catalog to route the conversation intelligently, but do not claim that you executed any voice or gesture unless a later tool/API explicitly does it.',
            'When the user asks a question that depends on an internal knowledge base, recommend the most relevant voice and, when useful, give a concise preliminary answer from the general context.',
            'When the user asks for a structured workflow, asset, document, process, lead search, transcription, or content transformation, recommend the most relevant gesture and explain in one sentence what it will prepare.',
            'If recommending a capability, include its route as plain text so the interface can later turn it into a button. Do not recommend capabilities that are not listed here.',
            '',
        ];

        $user = $catalog['user'] ?? [];
        $userSummary = trim(implode(', ', array_filter([
            (string)($user['name'] ?? ''),
            (string)($user['email'] ?? ''),
            (string)($user['job_title'] ?? ''),
            (string)($user['department'] ?? ''),
            !empty($user['is_superadmin']) ? 'superadmin' : '',
        ])));
        if ($userSummary !== '') {
            $lines[] = 'Current user: ' . $userSummary;
            if (!empty($user['department_responsibilities'])) {
                $departmentNames = array_map(static fn(array $department): string => (string)($department['name'] ?? ''), $user['department_responsibilities']);
                $lines[] = 'Department responsibility: ' . implode(', ', array_filter($departmentNames));
            }
            if (!empty($user['voice_responsibilities'])) {
                $voiceNames = array_map(static fn(array $voice): string => (string)($voice['name'] ?? $voice['slug'] ?? ''), $user['voice_responsibilities']);
                $lines[] = 'Voice responsibility: ' . implode(', ', array_filter($voiceNames));
            }
            $lines[] = '';
        }

        $voices = array_slice($catalog['voices'] ?? [], 0, 20);
        $lines[] = 'Accessible voices:';
        if (!$voices) {
            $lines[] = '- None available for this user.';
        } else {
            foreach ($voices as $voice) {
                $parts = [
                    (string)$voice['name'] . ' (' . (string)$voice['slug'] . ')',
                    'route: ' . (string)$voice['route'],
                    'role: ' . (string)$voice['role'],
                ];
                if (!empty($voice['trigger_guidance'])) {
                    $parts[] = 'use when: ' . (string)$voice['trigger_guidance'];
                } elseif (!empty($voice['description'])) {
                    $parts[] = 'use when: ' . (string)$voice['description'];
                }
                if (!empty($voice['knowledge_status'])) {
                    $parts[] = 'knowledge: ' . (string)$voice['knowledge_status'];
                }
                $lines[] = '- ' . implode('; ', $parts);
            }
        }

        $gestures = array_slice($catalog['gestures'] ?? [], 0, 20);
        $lines[] = '';
        $lines[] = 'Accessible gestures:';
        if (!$gestures) {
            $lines[] = '- None available for this user.';
        } else {
            foreach ($gestures as $gesture) {
                $lines[] = '- ' . (string)$gesture['name'] . ' (' . (string)$gesture['slug'] . '); route: ' . (string)$gesture['route'] . '; use when: ' . (string)$gesture['when_to_use'] . '; useful inputs: ' . (string)$gesture['inputs'];
            }
        }

        return implode("\n", $lines);
    }

    private function summarizeUser(array $user): array
    {
        $name = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));

        return [
            'id' => isset($user['id']) ? (int)$user['id'] : null,
            'name' => $name,
            'email' => (string)($user['email'] ?? ''),
            'department' => (string)($user['department_name'] ?? $user['department'] ?? ''),
            'job_title' => (string)($user['job_title'] ?? ''),
            'is_superadmin' => !empty($user['is_superadmin']),
            'department_responsibilities' => $this->responsibilityRepo && isset($user['id'])
                ? ($this->responsibilityRepo->getUserDepartmentResponsibilitiesMap()[(int)$user['id']] ?? [])
                : [],
            'voice_responsibilities' => $this->responsibilityRepo && isset($user['id'])
                ? ($this->responsibilityRepo->getUserVoiceResponsibilitiesMap()[(int)$user['id']] ?? [])
                : [],
        ];
    }

    private function getAccessibleVoiceCatalog(int $userId): array
    {
        $voices = [];
        foreach ($this->voicesRepo->listPublished() as $voice) {
            if (!$this->accessResolver->hasVoiceAccess($userId, $voice)) {
                continue;
            }

            $voices[] = [
                'slug' => (string)$voice['slug'],
                'name' => (string)$voice['name'],
                'role' => (string)$voice['role'],
                'description' => (string)$voice['description'],
                'trigger_guidance' => (string)$voice['trigger_guidance'],
                'route' => $this->voiceRoute((string)$voice['slug']),
                'knowledge_status' => $this->voiceKnowledgeStatus((string)$voice['slug']),
            ];
        }

        return $voices;
    }

    private function getAccessibleGestureCatalog(int $userId): array
    {
        $gestures = [];
        foreach ($this->accessRepo->getAccessibleGestures($userId) as $gesture) {
            $slug = (string)($gesture['feature_slug'] ?? '');
            $meta = self::FALLBACK_GESTURE_META[$slug] ?? [
                'route' => '/gestos/',
                'when_to_use' => (string)($gesture['description'] ?? 'A structured workflow is better than a free-form chat answer.'),
                'inputs' => 'the request details and any relevant source material',
            ];
            $inputSchema = $this->decodeInputSchema($gesture['input_schema'] ?? null);

            $gestures[] = [
                'slug' => $slug,
                'name' => (string)($gesture['name'] ?? $slug),
                'description' => (string)($gesture['description'] ?? ''),
                'route' => $this->firstText($gesture['route'] ?? null, $meta['route']),
                'when_to_use' => $this->firstText($gesture['trigger_guidance'] ?? null, $meta['when_to_use']),
                'inputs' => $inputSchema ?: $meta['inputs'],
            ];
        }

        return $gestures;
    }

    private function decodeInputSchema($raw): string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return trim($raw);
        }

        if (isset($decoded['summary']) && is_string($decoded['summary'])) {
            return trim($decoded['summary']);
        }

        $fields = $decoded['fields'] ?? [];
        if (is_array($fields) && $fields) {
            $labels = [];
            foreach ($fields as $field) {
                if (is_array($field) && !empty($field['label'])) {
                    $labels[] = (string)$field['label'];
                } elseif (is_string($field)) {
                    $labels[] = $field;
                }
            }

            if ($labels) {
                return implode(', ', array_slice($labels, 0, 8));
            }
        }

        return '';
    }

    private function firstText($primary, string $fallback): string
    {
        $primary = is_string($primary) ? trim($primary) : '';
        return $primary !== '' ? $primary : $fallback;
    }

    private function voiceKnowledgeStatus(string $slug): string
    {
        if (!$this->docsRepo) {
            return 'rag';
        }

        try {
            $docs = $this->docsRepo->listByVoice($slug);
            if (!$docs) {
                return 'rag empty';
            }

            $processed = 0;
            $pending = 0;
            foreach ($docs as $doc) {
                $status = (string)($doc['rag_status'] ?? '');
                if ($status === 'processed') {
                    $processed++;
                } elseif ($status !== 'not_applicable') {
                    $pending++;
                }
            }

            if ($processed > 0 && $pending === 0) {
                return 'rag ready (' . $processed . ' processed documents)';
            }
            if ($processed > 0) {
                return 'rag partially ready (' . $processed . ' processed, ' . $pending . ' pending)';
            }
            return 'rag pending';
        } catch (Throwable $e) {
            return 'rag';
        }
    }

    private function voiceRoute(string $slug): string
    {
        return $slug === 'lex'
            ? '/voices/lex.php'
            : '/voices/view.php?voice=' . rawurlencode($slug);
    }
}
