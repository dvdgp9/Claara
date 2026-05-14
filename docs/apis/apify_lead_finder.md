# Apify Lead Finder Integration

## Purpose
Production provider for `Lead Finder` gesture. Replaces mock data with real results from an Apify Actor.

## Required `.env` variables

```env
LEAD_FINDER_PROVIDER=apify
APIFY_API_TOKEN=...
APIFY_ACTOR_ID=compass/google-maps-extractor
APIFY_TIMEOUT_SECONDS=120
```

## Runtime flow

1. `public/api/gestures/lead-finder/search.php` creates a run and job.
2. `public/api/jobs/process.php` handles job type `lead-finder`.
3. `buildLeadSearchProvider()` selects:
   - `apify` => `LeadFinder\\ApifyLeadSearchProvider`
   - otherwise => `LeadFinder\\MockLeadSearchProvider`
4. `ApifyLeadSearchProvider` executes:
   - `POST https://api.apify.com/v2/acts/{actorId}/run-sync-get-dataset-items`
   - Input payload:
     - `searchStringsArray: [query]`
     - `maxCrawledPlacesPerSearch: maxResults`
     - `maxCrawledPlaces: maxResults`
     - `language: "en"`
5. Results are normalized and persisted in `lead_finder_results`.

## Notes

- The provider enforces bounds: `maxResults` in `[1, 100]`.
- HTTP timeout is controlled by `APIFY_TIMEOUT_SECONDS` (clamped to `[30, 600]`).
- If `LEAD_FINDER_PROVIDER=apify` but token/actor is missing, the job fails with explicit error and run status becomes `failed`.
- `raw_data` stores original provider row for traceability.

## Validation checklist

1. Launch a Lead Finder search from UI.
2. Confirm job phases advance to `completed`.
3. Confirm rows include `name`, and where available `website/email/phone/address`.
4. Edit/validate/reject one row and export CSV.
5. Confirm history load and deletion still work.

