# Claara Context

This folder contains the base knowledge files that Claara loads into the main assistant.

## How It Works

1. `ContextBuilder` reads all `.md` files in this folder.
2. It concatenates them alphabetically, prioritizing `system_prompt.md`.
3. The combined text is sent as the system instruction to the LLM provider.

## Current Files

- `system_prompt.md`: base assistant instructions, capabilities, tone, and document-generation rules.

## Adding New Context

1. Create a focused `.md` file in this folder.
2. Use standard Markdown.
3. The content is added automatically on each request.

Keep context concise, current, and free of sensitive data unless the deployment is configured for that use.
