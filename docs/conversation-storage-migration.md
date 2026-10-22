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
   "config": {"namespace": "default", "encryption": {"method": "none"}}, "docks": {}},
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

## Optional encryption of persisted content

**Default: `encryption.method=none`.** No existing chat is re-encrypted by the schema migration, and administrators must opt in to encryption explicitly. Each conversation has its own method/key metadata for `title` and `opening_message`; each message has separate method/key metadata for `content` and serialized `payload`. A new encrypted message may be appended to an old unencrypted conversation. Reading always uses the row's **stored** method, never the current write default. Existing encrypted rows still require their original key after a policy change.

New encryption algorithms are `ICrypt` implementations registered by the BASE3 classmap; built-in methods are owned by `Base3Framework`, not MissionBay. `SodiumCrypt` uses `xchacha20poly1305-v1`. The legacy `aes-256-cbc-legacy-v1` method remains available for existing cipher data; CBC does not authenticate ciphertext and should **not** be chosen for new sensitive chat data.

`IConfigValueResolver` is reused through `IAgentConfigValueResolver`. The `keys` map references deployment-protected secrets; no key bytes are stored with messages. **The example below is the complete database-memory preset `config` object** (not the contents of the nested `encryption` editor field):

```json
{
  "encryption": {
    "method": "xchacha20poly1305-v1",
    "key_id": "chat-2026",
    "keys": {
      "chat-2026": {"mode": "file", "path": "/etc/base3/secrets/chat-2026"},
      "chat-old": {"mode": "env", "name": "BASE3_CHAT_OLD_KEY"}
    }
  }
}
```

When editing only the `encryption` property in a schema-driven editor, enter just `method`, `key_id` and `keys` inside that property, without a second outer `encryption` object. An incorrectly nested configuration (`config.encryption.encryption.method`) now raises an error rather than defaulting silently to `none`. Leaving the entire `encryption` property absent, using `{}` or explicitly setting `method` to `none` is the supported plaintext default. Test both newly created conversations and appended messages after changing the preset; historical rows retain their original encryption metadata.

The `file` and `env` key-reference shape must follow the installed BASE3 `IConfigValueModeResolver` implementation. Keep secret material outside the webroot and outside database-backed UI settings. Protect backups and operator access. Rotation requires retaining previous key IDs and sources for as long as corresponding ciphertext exists, or a separately verified re-encryption procedure. A missing key, unknown method or failed integrity check is an error, **never a fallback to plaintext**.

Encryption is server-side at rest, **not** end-to-end encryption. Authorized server-side sharing can decrypt for another permitted user if sharing is later implemented; permission decisions belong solely to `IAuthorizationService`. Encryption does not automatically protect content in session backends, logs, configured providers, model requests or backups.

## Privacy diagnostic interpretation

MissionBay's `conversationmissinguserdiagnosticcheck` aggregates counts per native owner and calls `IUsermanager::getUserById()` for each. The interface returns `null` for either an absent account **or an unsupported lookup**; a missing result is therefore a **warning**, not proof that the user was deleted. Legacy rows without typed owners are also warnings. The check never exports user IDs or message text.

`conversationmissingchatobjectdiagnosticcheck` compares known chatbot config group/name references against `ISettingsStore::has()`. Missing objects are **failed** with aggregate conversation/message counts. Missing historical references are **warnings**. Both checks operate on the whole persistent store, not the current user's session. A successful result cannot establish physical session garbage collection, external provider deletion or expiry of backup copies.

## Deletion and remaining limitations

Application entrypoints must separately authorize requests before invoking lifecycle deletion. Normal conversation operations require the bound owner and current trusted identity; the dedicated lifecycle operation intentionally permits deletion for a user who is no longer logged in. The active host's user-deletion adapter must pass the original native ID *before* discarding that event payload. ILIAS integration currently does this in `Base3IliasLab` without embedding ILIAS APIs into MissionBay.

No automatic time-based database conversation retention job is introduced here. Anonymous conversation lifetime and physical removal remain responsibilities of the configured `ISession` backend. Neither the migration nor a successful diagnostic proves deletion from backups, logs, AI providers, downstream tool stores, caches or all host sessions. The operator's retention policy and verified deletion workflow remain necessary.
