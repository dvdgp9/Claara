<?php

declare(strict_types=1);

namespace LeadFinder;

class MockLeadSearchProvider implements LeadSearchProvider
{
    public function providerKey(): string
    {
        return 'mock';
    }

    public function search(string $query, int $maxResults): array
    {
        $maxResults = max(1, min($maxResults, 100));
        $location = $this->inferLocation($query);
        $sector = $this->inferSector($query);
        $seed = abs(crc32(mb_strtolower($query)));

        $items = [];
        for ($i = 0; $i < $maxResults; $i++) {
            $name = $this->nameFor($sector, $location, $seed, $i);
            $domain = $this->domainFromName($name);
            $hasEmail = $i % 4 !== 1;

            $items[] = [
                'name' => $name,
                'website' => 'https://' . $domain,
                'email' => $hasEmail ? 'info@' . $domain : null,
                'phone' => '+34 ' . sprintf('%03d %03d %03d', 900 + (($seed + $i) % 80), 100 + (($seed + $i * 7) % 800), 100 + (($seed + $i * 13) % 800)),
                'address' => $this->addressFor($location, $seed, $i),
                'source_url' => 'https://example-search.local/result/' . urlencode($domain),
                'confidence' => round(0.72 + (($seed + $i * 11) % 24) / 100, 2),
                'raw_data' => [
                    'query' => $query,
                    'mock_rank' => $i + 1,
                    'sector' => $sector,
                    'location' => $location,
                ],
            ];
        }

        return $this->dedupe($items);
    }

    private function inferLocation(string $query): string
    {
        if (preg_match('/\b(?:in|near|around|at|en)\s+([^,.;]+)/iu', $query, $matches)) {
            return trim($matches[1]);
        }
        return 'Selected area';
    }

    private function inferSector(string $query): string
    {
        $lower = mb_strtolower($query);
        if (str_contains($lower, 'school') || str_contains($lower, 'colegio') || str_contains($lower, 'instituto')) {
            return 'Education';
        }
        if (str_contains($lower, 'restaurant') || str_contains($lower, 'hotel')) {
            return 'Hospitality';
        }
        if (str_contains($lower, 'clinic') || str_contains($lower, 'medical') || str_contains($lower, 'health')) {
            return 'Healthcare';
        }
        return 'Business';
    }

    private function nameFor(string $sector, string $location, int $seed, int $index): string
    {
        $prefixes = ['North', 'Central', 'San Martín', 'Riverside', 'Monteclaro', 'Avenida', 'Civic', 'Llevant'];
        $suffixes = [
            'Education' => ['School', 'High School', 'Learning Center', 'Institute'],
            'Hospitality' => ['Restaurant', 'Hotel', 'Kitchen', 'House'],
            'Healthcare' => ['Clinic', 'Medical Center', 'Health Practice', 'Care Unit'],
            'Business' => ['Group', 'Studio', 'Services', 'Office'],
        ];
        $prefix = $prefixes[($seed + $index) % count($prefixes)];
        $suffixList = $suffixes[$sector] ?? $suffixes['Business'];
        $suffix = $suffixList[($seed + $index * 3) % count($suffixList)];
        return trim($prefix . ' ' . $location . ' ' . $suffix);
    }

    private function domainFromName(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string)$slug, '-');
        return ($slug ?: 'lead') . '.example';
    }

    private function addressFor(string $location, int $seed, int $index): string
    {
        $streets = ['Calle Mayor', 'Avenida del Mar', 'Ronda Norte', 'Plaza Central', 'Calle Industria'];
        $street = $streets[($seed + $index * 5) % count($streets)];
        return $street . ', ' . (3 + (($seed + $index) % 87)) . ', ' . $location;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function dedupe(array $items): array
    {
        $seen = [];
        $deduped = [];
        foreach ($items as $item) {
            $key = mb_strtolower((string)($item['website'] ?? $item['email'] ?? $item['name']));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $item;
        }
        return $deduped;
    }
}
