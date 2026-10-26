# Persistent and anonymous conversations: router, migration and encryption

## One configured memory slot

Agent memory profiles continue to select **one** `IAgentConversationMemory` resource. Select the new `routedconversationmemoryagentresource` as the one memory preset. Its two required docks are `authenticated` and `anonymous`; connect these to separate component presets of type `databasememoryagentresource` and `sessionmemoryagentresource`, respectively. The docks use the shared `IAgentConversationMemory` interface, so additional backends can implement either role. The database backend always enforces native identity itself. Both child presets are constructed during preset materialization, but neither touches its storage until selected. The router uses the current trusted `IUsermanager::getUser()` identity. `null` means anonymous; an authenticated user with a malformed ID fails closed rather than being treated as a guest. Any valid native ID, including `0` when the host genuinely uses it, is preserved verbatim. It never reads ILIAS-specific globals.

Conceptual component presets in `agent-component-preset`:

```json
[
  {
    "id": "conversation-memory",
    "type": "routedconversationmemoryagentresource",
    "enabled": true,
    "config": {},
    "docks": {"authenticated": ["conversation-database"], "anonymous": ["conversation-session"]}
  },
  {"id": "conversation-database", "type": "databasememoryagentresource", "enabled": true,
   "config": {"namespace": "default"}, "docks": {}},
  {"id": "conversation-session", "type": "sessionmemoryagentresource", "enabled": true,
   "config": {"namespace": "default"}, "docks": {}}
]
```

This is an illustration of three individual stored presets, **not** a single settings-store array. Only the router preset is attached to the agent's memory profile. The two child presets are referenced through its docks.

The database resource independently refuses guests. It never falls back to the session store. Authenticated owners are identified by `owner_type='user'` and the verbatim host-native user ID from `IUsermanager`; there is no numeric-only assumption. The session resource derives an opaque SHA-256 session owner identifier from `ISession::getId()`; no raw session authentication token is persisted as the owner ID. It can delete only its **currently accessible** session and throws if asked to erase another session: the host session backend must handle installation-wide session expiration and physical deletion. Anonymous users are not written into the database by the router. Logging in does **not** import anonymous conversation content into a user account. Logging out does not expose a previous authenticated database conversation.

## Database migration before runtime use

`MissionBayConversationMigrationProvider` owns version `001` (`Migration001ConversationIdentityAndEncryption`). A project must bind the correct `IDatabase` and activate a real `IMigrationRunner` during BASE3 bootstrap. With `NoMigrationRunner`, tables are **not** created automatically. The database memory constructor and resource `init()` never issue schema DDL. Do not use `ensureTable()` for this domain schema.

The current schema DDL targets a MySQL/MariaDB-compatible `IDatabase` backend (`information_schema`, InnoDB and `DATETIME(6)`). The resource and router remain host-neutral; an installation using a different SQL dialect needs a supported migration implementation before enabling the database branch. The presence of a database binding does not imply that standalone or other hosted installations have selected persistent memory.

The migration preserves all existing conversation and message rows. It:

- adds `owner_type`, `owner_id` (binary-collated string), and the chat object's `chatbot_config_group`/`chatbot_config_name` to conversations;
- adds `encryption_method` and `encryption_key_id` to **both** conversations and messages, defaulting legacy rows to `none` / `NULL`;
- expands `title` from `VARCHAR(255)` to `LONGTEXT` for ciphertext and creates new owner indexes;
- makes the historical `owner_key` nullable, retaining it **only for old, as-yet-unattributed data** and the migration period;
- keeps the message relationship and `ON DELETE CASCADE` to delete history when a conversation is deleted.

After migration, new database conversations use only the explicit `owner_type`/`owner_id` columns. When a known user next accesses the database resource, their legacy rows with `owner_key = SHA256('user:' + native_id)` are reassigned to the real ID. `deleteConversationsByOwner('user', native_id)` can delete both migrated and not-yet-reassigned legacy rows for the **specified known ID**, including after native user deletion. Host delete-user handlers must call this lifecycle method with the original trusted native user ID.

The scheme cannot automatically invert hashes for **already deleted** users or expired anonymous sessions. `IUsermanager::getAllUsers()` may not enumerate all users on every host. Unresolved legacy rows therefore remain explicitly visible as an aggregate in the privacy diagnostics; they must be reviewed and cleared through a documented, authorized data-cleanup process. Do not drop the legacy column until those records are addressed. There is no automatic mass deletion in this migration.

Chatbot context already carries `chatbot_config_group`/`chatbot_config_name`. New database conversations record them. When the original chatbot channel is encountered again, older records in that channel can be associated with the same trusted configuration reference; other historic references remain unknown. Non-chatbot agent conversations do not necessarily refer to a chatbot settings object.

## Optional encryption of persisted content with KeyHarbor

**Default: no assigned encryption profile.** The `DatabaseMemoryAgentResource` has no local encryption settings. Its configured component preset is discoverable through `DatabaseMemoryEncryptionTargetProvider`, which implements the shared `CredentialFoundation\Api\IEncryptionTargetProvider` contract. KeyHarbor shows the configured memory preset and lets an administrator assign an existing encryption profile. The current assignment is stored **only** by KeyHarbor. Resource IDs are derived from the canonical preset-flow resource ID and namespaced as `missionbay:<runtime-resource-id>` to avoid confusing targets with other providers. Renaming a preset changes this identity; review its assignment before using the renamed resource.

To enable encryption for `conversation-database`, create an encryption profile under **BASE3 administration / Credentials / Encryption**, add its method and key source, and assign that profile to the **Conversation Database** target. For example, the KeyHarbor profile might have ID `chat-conversation-encryption`, method `xchacha20poly1305-v1`, active key ID `chat-test-2026`, and a source of type `fixed`, `env`, `file`, or `configuration`. The preset's `config` itself stays simply:

```json
{
  "namespace": "default",
  "max": 20,
  "trim": false,
  "priority": 80
}
```

**Historical encryption keys:** Existing encrypted records require their historical key IDs and **original secrets** in KeyHarbor. The central service requires globally unique key IDs across profiles. Reusing a key ID with different key material cannot decrypt old ciphertext. KeyHarbor does not import historical secrets automatically. Do not delete external secret files or environment variables while their ciphertext is still needed.

Each conversation has its own method/key metadata for `title` and `opening_message`; each message has separate method/key metadata for `content` and serialized `payload`. A new encrypted message may be appended to an old unencrypted conversation. Reading always uses each row's **stored** method and key ID via `IEncryptionProfileService::getReadKey()`; it never depends on the current target assignment. A removed or changed assignment cannot make encrypted rows readable without their original key. New writes resolve the assigned active profile through `IEncryptionProfileService::getWriteKey()`; invalid assignments or missing key sources throw rather than writing plaintext.

New encryption algorithms are `ICrypt` implementations registered by the BASE3 classmap; built-in methods are owned by `Base3Framework`, not MissionBay. `SodiumCrypt` uses `xchacha20poly1305-v1`. The legacy `aes-256-cbc-legacy-v1` method remains available to read existing ciphertext; CBC does not authenticate ciphertext and should **not** be selected for new sensitive chat data.

The discoverable target list covers **stored database-memory component presets**. Ad hoc flow resources that were not configured as presets do not appear in the central list and should not be assumed to have an assignment. An unassigned target continues to write plaintext by design; verify the assignment in KeyHarbor before processing sensitive data. Missing profile IDs and inaccessible historical keys are errors, never reasons to write a requested encrypted record in plaintext.

Encryption is server-side at rest, **not** end-to-end encryption. Authorized server-side sharing may decrypt for another permitted user if sharing is later implemented; permission decisions belong solely to `IAuthorizationService`. Encryption does not automatically protect content in session backends, logs, configured providers, model requests or backups. The database resource does not query conversation history to decide whether a key may be removed. KeyHarbor warns on destructive key changes but cannot establish absence of historical references in every consumer.

## Privacy diagnostic interpretation

MissionBay's `conversationmissinguserdiagnosticcheck` aggregates counts per native owner and calls `IUsermanager::getUserById()` for each. The interface returns `null` for either an absent account **or an unsupported lookup**; a missing result is therefore a **warning**, not proof that the user was deleted. Legacy rows without typed owners are also warnings. The check never exports user IDs or message text.

`conversationmissingchatobjectdiagnosticcheck` compares known chatbot config group/name references against `ISettingsStore::has()`. Missing objects are **failed** with aggregate conversation/message counts. Missing historical references are **warnings**. Both checks operate on the whole persistent store, not the current user's session. A successful result cannot establish physical session garbage collection, external provider deletion or expiry of backup copies.

## Deletion and remaining limitations

Application entrypoints must separately authorize requests before invoking lifecycle deletion. Normal conversation operations require the bound owner and current trusted identity; the dedicated lifecycle operation intentionally permits deletion for a user who is no longer logged in. The active host's user-deletion adapter must pass the original native ID *before* discarding that event payload. ILIAS integration currently does this in `Base3IliasLab` without embedding ILIAS APIs into MissionBay.

No automatic time-based database conversation retention job is introduced here. Anonymous conversation lifetime and physical removal remain responsibilities of the configured `ISession` backend. Neither the migration nor a successful diagnostic proves deletion from backups, logs, AI providers, downstream tool stores, caches or all host sessions. The operator's retention policy and verified deletion workflow remain necessary.
