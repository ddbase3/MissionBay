# MissionBay RAG Payload Specification (v1)

This document defines the unified, extensible payload format used by MissionBay's RAG (Retrieval Augmented Generation) pipeline. It standardizes how extracted, parsed, chunked and embedded content is stored in vector databases (e.g., Qdrant).

The goals of this specification:

* Consistent and flat structure (vector DB friendly)
* Support for multiple source types (files, DB, CMS, websites, API endpoints)
* Full reference and navigation support
* ACL-based access control
* Hierarchical content navigation (chapter trees, knowledge structures)
* Extendable metadata design

---

## 1. Overview

Each stored vector has one associated **payload object**, containing the textual content and all metadata required for:

* Identifying the data
* Reconstructing origin and references
* Enforcing permissions
* Context-aware retrieval
* Structurally filtered search (e.g., subtree queries)

Payloads must remain **flat**, since Qdrant performs best with shallow key/value structures.

---

## 2. Required Fields

These fields are **always** present.

```json
{
	"text": "... chunk text ...",
	"hash": "... document or chunk hash ...",
	"source_id": "... global source identifier ...",
	"chunktoken": "... stable chunk identifier ...",
	"chunk_index": 0
}
```

### Definitions:

* **text** — The actual content of the chunk.
* **hash** — Hash of the original source (or content segment). Used for duplicate detection.
* **source_id** — Logical ID of the content source (file, DB-record, URL, CMS node, etc.).
* **chunktoken** — A stable identifier for the chunk (e.g., `hash + index`).
* **chunk_index** — Position of this chunk within the source.

---

## 3. Recommended Fields

These fields are optional but strongly recommended when available.

### 3.1 Source & Reference Information

```json
{
	"filename": "manual.pdf",
	"url": "https://example.com/page",
	"content_id": 4711
}
```

* **filename** — Original file name (if document-based).
* **url** — URL of original content (web/CMS/API).
* **content_id** — ID of the record in its origin system.

This enables the LLM to return clickable reference links.

---

## 4. Access Control (ACL)

```json
{
	"allowed_user_ids": [1002, 1005],
	"allowed_group_ids": [200, 201]
}
```

Payload-level ACL ensures:

* Vector retrieval filters out restricted chunks *before prompting*
* Sensitive content never reaches the LLM

---

## 5. Structural Navigation

This enables subtree-based retrieval ("search inside chapter X including all subchapters").

```json
{
	"path": [1, 42, 57],
	"parent_id": 42,
	"section": "7.2 – Eligibility Requirements"
}
```

### Definitions:

* **path** — Array of hierarchical IDs from the root to this node.
* **parent_id** — Direct parent node.
* **section** — Human-readable structural label.

With this structure, the system can:

* Fetch all content belonging to a chapter recursively
* Limit retrieval to specific knowledge trees
* Allow fine-grained contextual RAG queries

---

## 6. Additional Metadata

```json
{
	"doctype": "pdf",
	"lang": "de",
	"created_at": "2024-09-01",
	"updated_at": "2024-09-15"
}
```

Useful for filtering, debugging, indexing and ranking.

---

## 7. Full Payload Example

This shows the complete recommended shape.

```json
{
	"text": "...",
	"hash": "c93f...",
	"source_id": "upload-4711",
	"chunktoken": "c93f-0",
	"chunk_index": 0,

	"filename": "Skript Antragsstellung.txt",
	"url": null,
	"content_id": 57,

	"allowed_user_ids": [1002],
	"allowed_group_ids": [5, 7],

	"path": [1, 42, 57],
	"parent_id": 42,
	"section": "Grundlagen",

	"doctype": "text",
	"lang": "de",
	"created_at": "2024-09-01",
	"updated_at": "2024-09-01"
}
```

---

## 8. Principles

* Payload must be **flat**.
* Fields must be **optional-friendly**.
* No nested structures except arrays of primitives.
* All values must be JSON-serializable.
* Vector store should not enforce a schema.
* Search and filtering should operate entirely on payload level.

---

## 9. Future Extensions

* Source-type-specific metadata modules
* Embedding origin signatures
* Multi-modal chunk descriptors (images, audio)
* Semantic-rank field for improved retrieval ordering

---

This concludes the **MissionBay RAG Payload Specification (v1)**.

