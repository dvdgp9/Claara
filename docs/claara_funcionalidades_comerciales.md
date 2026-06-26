# Claara - Current Features for Commercial Dossier

## Executive Summary

Claara is a private AI workspace for companies. Its commercial proposition is not to offer a generic chat, but to turn a company's internal intelligence into a daily work tool: asking questions, creating assets, transforming content, consulting specialized knowledge, and running guided workflows from a single environment.

The product value is built around three connected layers:

- **Central chat**: users start with a question or task, just as they would with any conversational assistant.
- **Specialized voices**: when a question requires internal knowledge, Claara can guide the user to the right voice or run that voice directly from the chat.
- **Guided gestures**: when a task needs structure, Claara offers prepared workflows that produce concrete deliverables with less friction.

## 1. Central Work Chat

The chat is Claara's main entry point. It lets users work naturally, without having to decide upfront which tool they need.

Commercially relevant elements:

- Saved conversation history for returning to previous work.
- Folders to organize conversations.
- File attachments in conversations: PDF, images, CSV, and Excel.
- Web search from the chat when external information is needed.
- Image generation mode when the user needs visual assets.
- Capability recommendations: Claara can suggest a voice or gesture when it detects that the user needs a more specialized tool.
- Shared conversations with different permission levels:
  - **Can view**: read-only access, with no risk of changing the conversation.
  - **Can chat**: active collaboration, with the ability to participate.
- Collaboration indicators to avoid overlapping AI responses when Claara is replying in a shared conversation.

Commercial message: Claara lowers the adoption barrier because users do not need to learn a complex map of tools. They can start by typing, and Claara brings the right capability closer to them.

## 2. Chat and Voice Integration

This is one of the strongest points to present to Pierre.

Voices are specialized assistants with their own knowledge, instructions, and permissions. Instead of forcing users to leave the general chat to find the right assistant, Claara integrates voices directly into the conversational experience.

How this creates value:

- A user can ask a question in the general chat and receive a clear recommendation for the most relevant voice.
- If the voice is available to that user, Claara can run it from the same conversation.
- The voice answer is inserted into the chat thread, so the work does not become fragmented across screens.
- Voice answers can include consulted sources, increasing trust and making review easier.
- Voice answers can show a source match signal, helping users distinguish answers that are strongly supported by documents.
- If documents conflict or contain different positions, Claara is designed to show that as part of the answer.
- Each voice answer can be reported if information is missing, incorrect, or needs review.

Commercial message: Claara turns internal company knowledge into assistants that are accessible from the natural flow of work. The user does not need to know where the document is or which assistant to open; they can ask, and Claara helps route the need.

## 3. Specialized Voices

Voices are internal knowledge assistants. Each voice can have:

- Its own name, role, and description.
- Specific behavior instructions.
- Its own document knowledge base.
- Access limited by user or profile.
- Responsible users who maintain and review the quality of the voice.
- Publication status to control which voices are available.

Voice confirmed in the current codebase:

- **Lex**: legal and labor assistant based on reference documents. It is designed to answer questions using a document-backed knowledge base.

Validation note: the system supports creating additional dynamic voices from the admin area. This environment could not connect to the production database, so the live server should be checked if the dossier needs to name every currently published voice.

## 4. Available Gestures

Gestures are guided workflows for specific tasks. They are commercially important because they turn complex work into simple, repeatable processes.

### 4.1 Write Content

Generates articles, blog posts, and press releases.

Commercial interest:

- Helps create long-form content without starting from a blank page.
- Lets the user adapt content type, category, length, and details.
- Supports SEO blog posts, informative articles, and press releases.
- Helps maintain editorial consistency.

### 4.2 Social Media

Creates posts for social channels from an idea, campaign, announcement, or source content.

Commercial interest:

- Lets the user define intent, channel, length, narrative focus, and closing style.
- Accelerates post creation for different situations.
- Helps generate structured editorial variants.

### 4.3 Generate Podcast

Turns an article, document, text, or URL into a podcast hosted by Iris and Bruno.

Commercial interest:

- Converts written content into audio format.
- Lets teams reuse reports, articles, or documents for easier consumption.
- Includes player, download, and podcast script.
- Strong commercial fit for learning, internal communication, and content repurposing.

### 4.4 Image Editor

Generates and edits images with AI.

Commercial interest:

- Creates images from scratch.
- Edits an existing image.
- Offers control over style, lighting, composition, color, and format.
- Helps produce visual assets aligned with brand or communication needs.

### 4.5 Content Transformer

Converts one piece of content into other formats.

Commercial interest:

- Repurposes existing content into posts, blogs, landing pages, newsletters, or FAQs.
- Saves time on multi-channel adaptation.
- Extracts more value from a single document, article, or source material.

### 4.6 Process Generator

Turns text, audio, images, URLs, or PDFs into structured procedures.

Commercial interest:

- Converts operational knowledge into clear processes.
- Can generate procedures, checklists, manuals, and step-by-step flows.
- Helps document internal operations in a repeatable way.
- Especially relevant for training, quality, operations, and knowledge transfer.

### 4.7 Audio Transcriber

Converts audio recordings into text.

Commercial interest:

- Supports recordings, interviews, meetings, and voice notes.
- Accepts common formats such as MP3, WAV, M4A, WebM, and OGG.
- Shows estimated duration, word count, and character count.
- Lets users copy and download the transcription.
- Keeps history so previous transcriptions can be recovered.

### 4.8 Course Creator

Creates training material from a PDF or source text.

Commercial interest:

- Generates an editable course outline before developing the full content.
- Lets the user configure duration, level, and format.
- Can produce syllabus, modules, handouts, quizzes, flashcards, educational podcasts, and exams.
- Strong fit for internal training, onboarding, and corporate academies.

### 4.9 Project Analysis

Analyzes tender or project documentation to extract key information.

Commercial interest:

- Lets users upload PDFs and extract non-staff costs, work hours, and relevant data.
- Reduces manual review time for tenders or extensive documentation.
- Helps management and administration teams identify actionable information.

### 4.10 Lead Finder

Finds organizations and builds reviewable lead lists.

Commercial interest:

- Users can request leads in natural language, for example by sector and location.
- Returns a review queue with structured data.
- Lets users edit fields, validate useful leads, reject noise, and export to CSV.
- Directly relevant for sales, business development, and prospecting teams.

## 5. Knowledge and Document Management

Claara can turn internal documents into knowledge that voices can use.

Commercially relevant elements:

- Document upload to feed specialized voices.
- Document processing so voices can answer based on sources.
- Source document consultation from the voice experience.
- Control over documents associated with each voice.
- Ability to manage sources to improve answer quality.

Commercial message: the company is not only using generic AI; it can connect its own knowledge and make it available in a controlled way.

## 6. Governance, Permissions, and Control

Claara includes administration capabilities designed for company use.

Commercially relevant elements:

- Users and departments.
- Job titles or user profiles.
- Permissions by feature, gesture, and voice.
- Department responsible users.
- Voice responsible users.
- Separate concepts of access and responsibility: a person can access a voice without being responsible for maintaining it.
- Administration panels for managing voices, users, and permissions.

Commercial message: Claara is designed for organizations, not only for individual users. It controls who can access which knowledge and who is responsible for maintaining it.

## 7. Feedback and Continuous Improvement

Voice answers can be reported directly from the chat.

Commercially relevant elements:

- Report missing information.
- Report incorrect answers.
- Free-form report for other cases.
- Reports go to administrators or responsible users for that voice.
- Follow-up states: open, in progress, resolved, or dismissed.

Commercial message: Claara includes a quality improvement loop. Answers are not a black box; they can be reviewed, corrected, and improved over time.

## 8. Collaboration

Claara already supports shared work around conversations.

Commercially relevant elements:

- Share conversations with people or departments.
- Read-only or participation permissions.
- Separate sections for own conversations, shared conversations, and department conversations.
- Single-response control to keep shared conversations orderly when Claara is responding.

Commercial message: Claara is not only an individual tool. It lets teams share AI-assisted work and internal knowledge with control.

## 9. Strengths to Highlight in the Dossier

- **Less friction**: the user starts in the chat, and Claara brings voices or gestures closer when needed.
- **Real specialization**: voices are not isolated prompts; they have knowledge, permissions, responsible users, and sources.
- **Traceability**: voice answers can show sources and be reported.
- **Immediate productivity**: gestures cover concrete tasks across marketing, training, operations, audio, image, projects, and sales.
- **Company governance**: departments, permissions, responsible users, and access control.
- **Knowledge reuse**: documents, articles, PDFs, audio, and images are transformed into useful deliverables.
- **Clear commercial orientation**: Lead Finder, content generation, podcasts, courses, and document analysis have demonstrable value for business teams.

## 10. Suggested Commercial Wording

Claara allows a company to centralize its AI work in a single environment: chat, consult specialized internal knowledge, produce materials, and run guided processes. Its main strength is that it combines the ease of a chat with the depth of expert voices and the consistency of prepared workflows.

The integration between chat and voices reduces complexity for the user: they do not need to know which document to consult, which assistant to open, or which process to follow. Claara interprets the need, suggests the right capability, and keeps the result inside the conversation. This makes adoption easier and turns company knowledge into a daily working tool.
