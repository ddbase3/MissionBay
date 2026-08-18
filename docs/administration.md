# MissionBay Administration Displays

## Purpose

This document maps the current MissionBay administration displays to the settings or diagnostics they manage.

All displays are discoverable BASE3 display classes. Their stable technical names are listed below.

## Agent configuration

| Display name | Responsibility |
| --- | --- |
| `agentadmindisplay` | named agents in the `agent` group |
| `agentcompositionadmindisplay` | inspect effective compiled agent composition |
| `agentcomponentpresetadmindisplay` | reusable component presets |
| `agentcomponentpresettestadmindisplay` | materialize/test component presets |
| `agentorchestratorprofileadmindisplay` | orchestrator profiles |
| `toolprofileadmindisplay` | tool profiles |
| `agentmemoryprofileadmindisplay` | memory profiles |
| `agentcontextprofileadmindisplay` | context profiles |
| `agentinfotopicprovidertestadmindisplay` | info-topic provider tests |
| `agenttoollogadmindisplay` | tool execution/audit logs |
| `knowledgeagentmemoryadmindisplay` | knowledge-memory administration |
| `userprefdefadmindisplay` | agent-related user preference definitions |

## Provider configuration

| Display name | Settings group |
| --- | --- |
| `connectionconfigdisplay` | `connection` |
| `llmconfigdisplay` | `service-llm` |
| `embeddingconfigdisplay` | `service-embedding` |
| `imageconfigdisplay` | `service-image` |
| `searchconfigdisplay` | `service-search` |
| `vectorsearchconfigdisplay` | `service-vectorsearch` |
| `vectorstoreconfigdisplay` | `service-vectorstore` |
| `parserserviceconfigdisplay` | `service-parser` |
| `speechtotextconfigdisplay` | `service-stt` |
| `texttospeechconfigdisplay` | `service-tts` |

The service displays share `AbstractServiceConfigDisplay` behavior so configuration, driver schemas and service tests are presented consistently.

## Retrieval and embedding

| Display name | Responsibility |
| --- | --- |
| `embeddingorchestratorconfigadmindisplay` | select embedding preset, vector-store preset and logical collection key |
| `retrievalcollectionadmindisplay` | logical collection to backend collection mapping |
| `retrievalsearchadmindisplay` | test retrieval searches through configured presets |
| `retrievalvectorpointsadmindisplay` | inspect vector index points through the configured collection definition |

The administration layer uses the same repositories and interfaces as runtime code. It should not maintain a second mapping for collections or providers.

## AJAX and embedding hosts

Many MissionBay displays are used inside embedded administration shells and tab controls. Display actions should return their own response content and avoid assumptions that a full page reload is available unless the surrounding host integration explicitly provides one.

## Adding an administration display

Use the normal BASE3 display discovery model:

1. implement the appropriate display/output contract
2. use a stable lowercase `getName()`
3. receive services through constructor DI
4. keep the display responsible for UI orchestration, not provider business logic
5. reuse settings repositories and service test services already used by runtime code
6. resolve static assets through `IAssetResolver`
