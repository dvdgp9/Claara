# Audio Transcription Implementation Scratchpad

## Goal

Upgrade Claara's Audio Transcriber gesture so it can process long recordings, including 40-45 minute meetings, without HTTP timeouts, memory-heavy base64 uploads, truncated output, or missing speaker structure.

The server already has:

- `/usr/bin/ffmpeg`
- `/usr/bin/ffprobe`
- no PHP `open_basedir` restriction

This removes the main operational blocker for segmentation.

## Current State

- `public/gestos/transcriptor-audio.php` uploads audio as browser-generated base64 JSON.
- `public/api/gestures/transcribe.php` processes synchronously and returns only when transcription finishes.
- `src/Sop/AudioTranscriber.php` uses Gemini direct File API, but receives base64 and does not segment.
- `public/api/jobs/process.php` supports background jobs, but only `podcast`.
- `src/Jobs/BackgroundJobsRepo.php` can create/update/complete/fail jobs, but cannot store partial output during processing.
- `public/api/jobs/status.php` can already return `output_data`, so partial progress can reuse it once stored.

## Implementation Phases

### Phase 1: Job Plumbing

Status: pending

Scope:

- Add `BackgroundJobsRepo::updateProcessingSnapshot(int $id, string $progressText, array $outputData): bool`.
- Add `audio-transcribe` support in `public/api/jobs/process.php`.
- Add a `processAudioTranscribeJob()` function that:
  - reads temporary audio path from job input;
  - calls `AudioTranscriber`;
  - saves final result in `gesture_executions`;
  - logs usage;
  - deletes temporary files when done.

Validation:

- Create a dummy/pending `audio-transcribe` job and confirm `process.php` recognizes the type.
- Existing `podcast` job path remains unchanged.

### Phase 2: Multipart Upload Endpoint

Status: pending

Scope:

- Update `public/api/gestures/transcribe.php` to support primary `multipart/form-data` uploads:
  - field: `audio_file`;
  - optional field: `async=1`.
- Keep legacy JSON/base64 support temporarily for compatibility.
- Validate CSRF, MIME, size, and upload errors.
- Store uploaded audio in `storage/transcribe-jobs`.
- Create `audio-transcribe` background job and return quickly:

```json
{
  "success": true,
  "async": true,
  "job_id": 123
}
```

Validation:

- Small upload returns `job_id`.
- Invalid MIME returns a clear English error.
- Oversized upload returns a clear English error.

### Phase 3: Frontend Polling And Partial UI

Status: pending

Scope:

- Update `public/gestos/transcriptor-audio.php`:
  - stop converting files to base64;
  - upload `multipart/form-data`;
  - trigger `/api/jobs/process.php`;
  - poll `/api/jobs/status.php?id=...`;
  - persist active job ID in `sessionStorage`;
  - render progress text, segment counts, and partial transcription.
- Recover active job after page reload.
- Keep history behavior.

Validation:

- Short audio shows queued/processing/completed states.
- Reload during processing resumes polling.
- Partial text appears if available.

### Phase 4: Transcriber Refactor

Status: pending

Scope:

- Add `AudioTranscriber::transcribeFile(string $path, string $mimeType, string $filename, ?callable $onProgress = null): array`.
- Add `AudioTranscriber::transcribeBytes(...)` if useful internally.
- Keep `transcribe(base64, mime, filename)` as compatibility wrapper.
- Switch prompts to English product text while preserving source language:
  - transcribe fully and chronologically;
  - do not summarize;
  - always use speaker labels;
  - use `Speaker 1:`, `Speaker 2:` unless names/roles are clear;
  - if no intelligible speech, return exactly `[no speech]`.
- Increase `maxOutputTokens` where supported.
- Normalize provider/model IDs for future provider switching.

Validation:

- Existing synchronous call still works.
- Short audio returns speaker-labeled text.

### Phase 5: Duration Detection And Segmentation

Status: pending

Scope:

- Detect duration with `ffprobe`.
- Segment audio with `ffmpeg` when duration is >= 10 minutes.
- Use:
  - base segment length: 180 seconds;
  - minimum fallback segment length: 45 seconds;
  - normalized segment format: M4A/AAC mono, 16 kHz, 48 kbps.
- Process segments sequentially first.
- Update job partial output after each segment.

Validation:

- 40-45 minute audio creates expected segment count.
- Job status shows `segments_done` and `segments_total`.
- Temporary segment files are cleaned.

### Phase 6: Segment Fallbacks

Status: pending

Scope:

- Detect empty transcription responses.
- Retry once with a simpler ASR prompt.
- If still empty, subdivide until minimum segment length.
- Treat `[no speech]` as a successful empty segment and omit from final text.
- Detect `MAX_TOKENS` / length finish reasons and subdivide instead of accepting truncated text.
- Detect obvious repetition artifacts and retry/sanitize.

Validation:

- Silent sections do not fail the whole job.
- Truncated model responses are not saved as final text.

### Phase 7: Optional Gemini Parallelism

Status: pending

Scope:

- Add `GEMINI_TRANSCRIBE_CONCURRENCY` support.
- Parallelize only Gemini direct segment processing.
- Fallback failed parallel segments to sequential retry path.

Validation:

- Default concurrency is conservative.
- Sequential path remains available and reliable.

### Phase 8: Operational Hardening

Status: pending

Scope:

- Add English env vars to `.env.example`:
  - `AUDIO_TRANSCRIBE_PROVIDER=auto`
  - `GEMINI_TRANSCRIBE_MODEL=gemini-3-flash-preview`
  - `OPENROUTER_TRANSCRIBE_MODEL=google/gemini-3-flash-preview`
  - `FFMPEG_PATH=/usr/bin/ffmpeg`
  - `FFPROBE_PATH=/usr/bin/ffprobe`
  - `GEMINI_TRANSCRIBE_CONCURRENCY=5`
- Add `storage/transcribe-debug.log` logging.
- Add cleanup for old files in `storage/transcribe-jobs`.
- Consider increasing `set_time_limit` for job processing or making it configurable.

Validation:

- Missing `ffmpeg` gives clear error.
- Logs include provider, model, segment count, and failure codes.

## Recommended First Implementation Slice

Start with Phases 1-3 plus a minimal `transcribeFile()` wrapper that does not segment yet.

Reason:

- It removes the biggest immediate failure modes: base64 memory overhead and HTTP 504.
- It reuses existing job infrastructure.
- It creates a stable path to add segmentation without rewriting the UI again.

After that, implement Phases 5-6 for long-audio reliability.

## Manual Test Matrix

- Short MP3 under 2 minutes.
- M4A meeting around 10-12 minutes to trigger segmentation threshold.
- 40-45 minute meeting.
- Audio with long silence.
- Audio with two or more speakers.
- Invalid file type.
- File near upload size limit.
- Page reload while processing.

