# Kuncia Assistant: project brief

Status: planning (2026-10-15). Nothing built yet.

## One line

An assistant inside Kuncia where a landlord asks questions in plain English or Malay about their properties, tenants, money and documents, and every answer shows where it came from.

## Why

Landlords look things up across invoices, tenancies, tickets and signed agreements. Facts like dates and amounts are already in the database; wording like "who pays for aircon repairs" is buried in PDFs. The assistant answers both kinds of question in one place, with sources, so the landlord can check the answer.

For the portfolio it shows grounded AI done properly: retrieval with citations, the model choosing between database tools and document search, and refusing to guess when the sources don't cover a question.

## How it answers (RAG + tools)

RAG (retrieval-augmented generation): search the landlord's own documents for the most relevant passages, put those passages in the prompt, and let the model answer from them only.

- **Unstructured text** (agreement clauses, ticket descriptions): RAG. Text is split into chunks, each chunk is turned into a vector by an embeddings model, and questions are matched to the closest chunks.
- **Structured facts** (lease dates, overdue amounts, payments): fixed read-only database tools the model can call. No RAG and no model-written SQL.

| Example question | Answered by |
|---|---|
| Which leases end in the next 2 months? | Tool: tenancies |
| How much rent is overdue, and from who? | Tool: invoices |
| What does Unit 3's agreement say about aircon repairs? | RAG: agreement PDF |
| Has the water heater at Rumah Melaka been reported before? | RAG: ticket text |
| Can I raise rent for Unit 2 this year? | RAG (agreement clause) + tool (tenancy dates) |

## Features (v1)

1. **Documents:** upload tenancy agreements (PDF) per tenancy. They are chunked and embedded. Maintenance ticket text is embedded automatically when a ticket is created or edited.
2. **Chat page** (landlord only): streamed answers with citations, for example "Tenancy agreement, Unit 3, page 4" (opens the PDF) or "Invoice #2026-0312".
3. **Database tools** (read-only, always scoped to the signed-in landlord on the server): tenancies, invoices, payments, maintenance tickets, units.
4. **Honest fallback:** if nothing relevant is found, say so instead of guessing.
5. **Demo mode:** the guest account gets fake agreements and data, reset nightly like the rest of the guest data.

## Decisions

- **Inside the Kuncia app**, as a new feature (same Laravel + Inertia/React codebase, same auth and roles).
- **Vectors in a separate Postgres with pgvector** (for example Supabase free tier). Kuncia's main data stays in MySQL; only document chunks, ticket chunks and their vectors live in Postgres, each row tagged with the landlord id.
- **AI provider:** Gemini for embeddings (already used in Kuncia for bill scanning). The chat model is chosen after a side-by-side test on the same set of questions (Gemini vs Claude). Look up current model names and pricing at that point; don't hard-code from memory.
- **Security:** every tool and every vector search filters by landlord id on the server. The model never writes SQL. Tenants and maintenance staff can't use the assistant in v1. API keys stay in `.env`, never in the repo.

## Build order

Each step is demoable on its own.

1. **Retrieval first:** document upload, PDF text extraction, chunking, embeddings into pgvector, and a plain search page that shows the matching passages (no AI answer yet). Proves retrieval works.
2. **RAG chat:** chat page with streamed answers and citations from the retrieved passages.
3. **Tools:** read-only database tools, and the model choosing between tools and documents.
4. **Quality:** Malay questions, the "I don't know" fallback, and an evaluation set of 20 to 30 questions with expected answers and sources, run after every change.
5. **Packaging:** demo data for the guest account, README section, screenshots or a short video, portfolio card update.

## Out of scope (v1)

- Tenant or maintenance staff access
- The assistant changing anything (read-only)
- Voice input
- Moving Kuncia's main database to Postgres

## Open questions

- Chunk size and overlap for agreements (start around 500 to 800 tokens with some overlap, tune with the evaluation set).
- Scanned PDFs with no text layer: OCR, or ask for a text PDF in v1.
- Cost limits: a per-landlord daily question cap so the demo can't be abused.

## Working rules

- Plan and checklist before multi-step work; show mockups or drafts and wait for "go".
- No AI-isms or em dashes in user-facing text. Don't overclaim.
- Never ask for, print or commit keys.
- Database changes are add-only migrations, shown before they run.
- Run the existing checks (Pest, PHPStan level 7, Pint, `vp check`) before saying a change is done.
