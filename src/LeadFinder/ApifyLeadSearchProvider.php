<?php

declare(strict_types=1);

namespace LeadFinder;

class ApifyLeadSearchProvider implements LeadSearchProvider
{
    private string $apiToken;
    private string $actorId;
    private int $timeoutSeconds;

    public function __construct(string $apiToken, string $actorId, int $timeoutSeconds = 120)
    {
        $this->apiToken = trim($apiToken);
        $this->actorId = trim($actorId);
        $this->timeoutSeconds = max(30, min($timeoutSeconds, 600));

        if ($this->apiToken === '') {
            throw new \InvalidArgumentException('Missing APIFY_API_TOKEN');
        }
        if ($this->actorId === '') {
            throw new \InvalidArgumentException('Missing APIFY_ACTOR_ID');
        }
    }

    public function providerKey(): string
    {
        return 'apify';
    }

    public function search(string $query, int $maxResults): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new \InvalidArgumentException('Lead Finder query is empty');
        }

        $maxResults = max(1, min($maxResults, 100));
        $primaryLang = $this->looksSpanish($query) ? 'es' : 'en';
        $secondaryLang = $primaryLang === 'en' ? 'es' : 'en';

        $normalized = [];
        $normalized = array_merge($normalized, $this->normalizeItems(
            $this->runActorAndFetchItems($query, max($maxResults, 50), $primaryLang),
            $query
        ));

        if (count($this->dedupe($normalized)) < $maxResults) {
            $normalized = array_merge($normalized, $this->normalizeItems(
                $this->runActorAndFetchItems($query, max($maxResults, 50), $secondaryLang),
                $query
            ));
        }

        if (count($this->dedupe($normalized)) < $maxResults) {
            $variant = $this->queryVariant($query);
            if ($variant !== '' && mb_strtolower($variant) !== mb_strtolower($query)) {
                $normalized = array_merge($normalized, $this->normalizeItems(
                    $this->runActorAndFetchItems($variant, max($maxResults, 50), $primaryLang),
                    $query
                ));
            }
        }

        return array_slice($this->dedupe($normalized), 0, $maxResults);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runActorAndFetchItems(string $query, int $maxResults, string $language): array
    {
        $url = sprintf(
            'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items?token=%s&format=json&clean=true&timeout=%d',
            rawurlencode($this->actorId),
            rawurlencode($this->apiToken),
            $this->timeoutSeconds
        );

        $input = [
            'searchStringsArray' => [$query],
            'maxCrawledPlacesPerSearch' => $maxResults,
            'maxCrawledPlaces' => $maxResults,
            'language' => $language,
        ];

        $payload = json_encode($input);
        if ($payload === false) {
            throw new \RuntimeException('Could not encode Apify input payload');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $this->timeoutSeconds + 20,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Apify request failed: ' . $curlError);
        }
        if ($httpCode >= 400) {
            throw new \RuntimeException('Apify HTTP error ' . $httpCode . ': ' . mb_substr((string)$response, 0, 500));
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Apify response is not valid JSON array');
        }

        $items = [];
        foreach ($decoded as $row) {
            if (is_array($row)) {
                $items[] = $row;
            }
        }

        return $items;
    }

    private function looksSpanish(string $query): bool
    {
        $q = mb_strtolower($query);
        return preg_match('/\b(colegio|instituto|escuela|en|m[áa]laga|valencia|castell[oó]n)\b/u', $q) === 1;
    }

    private function queryVariant(string $query): string
    {
        $variant = preg_replace('/\bcity\b/i', '', $query);
        $variant = preg_replace('/\s+/', ' ', (string)$variant);
        return trim((string)$variant);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items, string $query): array
    {
        $out = [];

        foreach ($items as $item) {
            $name = $this->stringFrom($item, ['title', 'name']);
            if ($name === '') {
                continue;
            }

            $website = $this->firstUrl($item, ['website', 'websiteUrl', 'url']);
            $email = $this->firstText($item, ['email', 'emails']);
            $phone = $this->firstText($item, ['phone', 'phoneUnformatted', 'phones']);
            $address = $this->stringFrom($item, ['address', 'fullAddress', 'street']);
            $sourceUrl = $this->firstUrl($item, ['url', 'placeUrl', 'sourceUrl', 'source_url']);
            $confidence = $this->confidenceFrom($item);

            $out[] = [
                'name' => $name,
                'website' => $website !== '' ? $website : null,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'address' => $address !== '' ? $address : null,
                'source_url' => $sourceUrl !== '' ? $sourceUrl : ($website !== '' ? $website : null),
                'confidence' => $confidence,
                'raw_data' => [
                    'provider' => 'apify',
                    'query' => $query,
                    'actor_id' => $this->actorId,
                    'item' => $item,
                ],
            ];
        }

        return $out;
    }

    private function confidenceFrom(array $item): float
    {
        $raw = $item['totalScore'] ?? $item['score'] ?? null;
        if (is_numeric($raw)) {
            $v = (float)$raw;
            if ($v > 1) {
                $v = $v / 5.0;
            }
            return round(max(0.0, min($v, 1.0)), 2);
        }
        return 0.75;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string> $keys
     */
    private function stringFrom(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }
            $value = $item[$key];
            if (is_string($value)) {
                $v = trim($value);
                if ($v !== '') {
                    return $v;
                }
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string> $keys
     */
    private function firstText(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }
            $value = $item[$key];
            if (is_string($value)) {
                $v = trim($value);
                if ($v !== '') {
                    return $v;
                }
            }
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_string($entry) && trim($entry) !== '') {
                        return trim($entry);
                    }
                }
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string> $keys
     */
    private function firstUrl(array $item, array $keys): string
    {
        $candidate = $this->firstText($item, $keys);
        if ($candidate === '') {
            return '';
        }
        if (!preg_match('/^https?:\/\//i', $candidate)) {
            return '';
        }
        return $candidate;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function dedupe(array $items): array
    {
        $seen = [];
        $out = [];

        foreach ($items as $item) {
            $key = mb_strtolower((string)($item['website'] ?? $item['source_url'] ?? $item['name'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }

        return $out;
    }
}
