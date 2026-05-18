# Translation Extraction Pack

This folder contains a static extraction of likely user-facing text from public PHP pages.

## Files

- `app_texts_en.csv`: global list for spreadsheet workflows.
- `app_texts_en.json`: global list for CAT tools/scripts.
- `pages/*.md`: one document per public page (excluding `/admin` by default).

Each row includes:

- source file path
- source line
- extraction type (`html_text`, `attribute`, `script_string`, `php_string`)
- extracted text

## Regenerate

Run:

```bash
php scripts/extract_page_texts.php
```

Optional (include admin pages as well):

```bash
php scripts/extract_page_texts.php --include-admin
```

## Notes

- This is a static extraction, not a runtime render.
- It may include some false positives and may miss fully dynamic text composed at runtime.
- Recommended translator flow:
  1. Translate from `app_texts_en.csv` or per-page markdown files.
  2. Keep a reviewer pass focused on dynamic UI states and API error messages.
