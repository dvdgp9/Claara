# Apify Lead Finder Integration

## Purpose
Production provider for `Lead Finder` gesture. Replaces mock data with real results from an Apify Actor.

## Required `.env` variables

```env
LEAD_FINDER_PROVIDER=apify
APIFY_API_TOKEN=...
APIFY_ACTOR_ID=compass/crawler-google-places
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
     - `searchStringsArray: parsed search terms`
     - `locationQuery: parsed location, when detected`
     - `maxCrawledPlacesPerSearch: maxResults`
     - `maxCrawledPlaces: exactly maxResults`
     - `language: "en" or "es"`
5. Results are normalized and persisted in `lead_finder_results`.

## Notes

- The provider enforces bounds: `maxResults` in `[1, 100]`.
- The provider performs one Apify run per user search and hard-caps crawl size to the requested amount to control credit spend.
- Free-form requests are parsed into search terms plus location. Example: `Schools in Malaga city` becomes terms `school`, `high school` and location `Malaga, Spain`.
- HTTP timeout is controlled by `APIFY_TIMEOUT_SECONDS` (clamped to `[30, 600]`).
- If `LEAD_FINDER_PROVIDER=apify` but token/actor is missing, the job fails with explicit error and run status becomes `failed`.
- `raw_data` stores original provider row for traceability.

## Validation checklist

1. Launch a Lead Finder search from UI.
2. Confirm job phases advance to `completed`.
3. Confirm rows include `name`, and where available `website/email/phone/address`.
4. Edit/validate/reject one row and export CSV.
5. Confirm history load and deletion still work.
