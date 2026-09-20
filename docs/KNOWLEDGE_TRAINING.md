# Knowledge training and MySQL vector storage

## Approved architecture

MySQL is the only knowledge and vector database. This is a final product decision for the current architecture: do not add or request a decision about an external vector service unless the product owner explicitly replaces this decision.

`knowledge_sources` stores source identity, private-file references, extracted text, ingestion state, checksums, generation state, and training timestamps. `knowledge_chunks` stores tenant and bot scoped content, OpenAI embeddings as JSON, model and dimension metadata, precomputed vector norms, and active generation state.

The existing application has no workspace or membership table. Subscriber `users.id` is therefore the current tenant and billing-owner key. Every source, chunk, job, route binding, deletion, and search carries both `user_id` and `bot_id`. Browser-supplied owner identifiers are ignored.

## Training workflow

Subscribers open `/bots/{public_id}/training` and can:

- submit plain text;
- upload TXT, Markdown, CSV, PDF, or DOCX files, up to 20 MB each;
- synchronize one public website page or a bounded XML sitemap;
- retry one source or retrain all bot sources;
- delete a source, its private file, and only its own indexed chunks.

Files use the private `knowledge` filesystem disk and randomized names. No public storage link is required. PDF extraction uses `smalot/pdfparser`; DOCX extraction uses PHP's Zip extension. Text is normalized and deterministically chunked before embedding.

`ProcessKnowledgeSource` runs on the configured `knowledge-ingestion` queue. It stages a replacement generation as inactive, obtains embeddings in bounded batches, verifies all expected chunks, and atomically switches generations. Existing trained chunks remain active during retraining. Failed staged generations are removed while the previous active generation remains available.

Run a worker with:

```bash
php artisan queue:work --queue=knowledge-ingestion --tries=3 --timeout=300
```

The database queue connection and standard queue tables are reused. A worker started without the `--queue=knowledge-ingestion` option listens to the `default` queue and will leave training sources in the queued state.

## OpenAI configuration

Embeddings are generated server-side through `EmbeddingProviderInterface`. Configure `OPENAI_API_KEY`, `OPENAI_BASE_URL`, `OPENAI_EMBEDDING_MODEL`, and `OPENAI_EMBEDDING_DIMENSIONS`. The key is read through Laravel configuration and is never persisted with a source or returned to a view. Automated tests replace the provider and make no paid API requests.

`OPENAI_CA_BUNDLE` optionally points to a trusted CA bundle for local PHP installations that do not configure `curl.cainfo` or `openssl.cafile`. This keeps TLS verification enabled and scopes the certificate path to NeuralDesk's OpenAI requests. For the bundled XAMPP certificate store on Windows, a local value may be:

```env
OPENAI_CA_BUNDLE=C:\xampp\apache\bin\curl-ca-bundle.crt
```

## Website safety

Website and sitemap inputs accept only HTTP/S public addresses. DNS results, redirect targets, and every sitemap URL are checked against private, loopback, link-local, reserved, and internal ranges. Crawls are same-origin, redirect bounded, timeout bounded, response-size bounded, and page-count bounded. Retrieved scripts and styles are removed; fetched HTML is converted to plain text and never executed.

## Similarity performance boundary

`MySqlVectorStore` first filters SQL candidates by tenant, bot, trained source, active generation, model, dimensions, and any requested source IDs. It then processes rows through lazy batches, validates vectors, calculates cosine similarity in PHP using stored norms, and keeps only the best top-K results above the requested confidence threshold.

This is exact, portable search rather than indexed approximate nearest-neighbor search. Retrieval cost grows with the active chunk count for one bot. Safe retrieval timing, scanned-candidate count, and result count are logged without embeddings or source content. `VectorStoreInterface` is the only future migration seam if product scale later requires a different explicitly approved architecture.
