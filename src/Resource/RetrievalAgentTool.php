<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiEmbeddingModel;
use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Api\IRetrievalFilterProvider;
use AssistantFoundation\Api\IRetrievalIndex;
use AssistantFoundation\Api\IRetrievalIndexInspector;
use AssistantFoundation\Dto\RetrievalHit;
use AssistantFoundation\Dto\RetrievalSearchRequest;
use Base3\Api\ISchemaProvider;
use Base3\Logger\Api\ILogger;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentTool;
use MissionBay\Retrieval\PhoneticTextMaterializer;

/**
 * Read-only multi-representation retrieval tool.
 *
 * Domain-specific collection schemas, mandatory filters, filter exposure, and
 * result projection remain outside this generic tool.
 */
final class RetrievalAgentTool extends AbstractAgentResource implements IAgentTool, ISchemaProvider {

        private int $limit = 5;
        private int $candidateLimit = 20;
        private ?float $denseMinScore = null;
        private string $collectionKey = 'default';

        private ?IAiEmbeddingModel $embeddingModel = null;
        private ?IRetrievalIndex $retrievalIndex = null;
        private ?ILogger $logger = null;

        /** @var IRetrievalFilterProvider[] */
        private array $filters = [];

        public function __construct(
                private readonly IAgentConfigValueResolver $resolver,
                private readonly IRetrievalCollectionDefinition $collectionDefinition,
                private readonly PhoneticTextMaterializer $phoneticTextMaterializer,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'retrievalagenttool';
        }

        public function getDescription(): string {
                return 'Searches and browses a retrieval index using semantic, lexical, phrase, phonetic, and metadata constraints.';
        }

        public function getSchema(): array {
                return [
                        '$schema' => 'https://json-schema.org/draft-2020-12/schema',
                        'type' => 'object',
                        'properties' => [
                                'limit' => [
                                        'type' => 'integer',
                                        'description' => 'Default maximum number of retrieval results.',
                                        'default' => 5,
                                        'minimum' => 1,
                                        'maximum' => 20
                                ],
                                'candidate_limit' => [
                                        'type' => 'integer',
                                        'description' => 'Candidates collected per retrieval channel before fusion.',
                                        'default' => 20,
                                        'minimum' => 1
                                ],
                                'minscore' => [
                                        'type' => ['number', 'null'],
                                        'description' => 'Optional minimum score for a single semantic search channel.',
                                        'default' => null
                                ],
                                'collectionkey' => [
                                        'type' => 'string',
                                        'description' => 'Logical collection key searched by this retrieval tool.',
                                        'default' => 'default'
                                ]
                        ],
                        'required' => []
                ];
        }

        public function getDockDefinitions(): array {
                return [
                        new AgentNodeDock(
                                name: 'embedding',
                                description: 'Embedding model used for semantic retrieval channels.',
                                interface: IAiEmbeddingModel::class,
                                maxConnections: 1,
                                required: true
                        ),
                        new AgentNodeDock(
                                name: 'vectorstore',
                                description: 'Retrieval index searched and browsed by this tool.',
                                interface: IRetrievalIndex::class,
                                maxConnections: 1,
                                required: true
                        ),
                        new AgentNodeDock(
                                name: 'filters',
                                description: 'Mandatory retrieval filters applied in addition to agent-requested filters.',
                                interface: IRetrievalFilterProvider::class,
                                maxConnections: null,
                                required: false
                        ),
                        new AgentNodeDock(
                                name: 'logger',
                                description: 'Optional retrieval logger.',
                                interface: ILogger::class,
                                maxConnections: 1,
                                required: false
                        )
                ];
        }

        public function setConfig(array $config): void {
                parent::setConfig($config);

                if(isset($config['limit'])) {
                        $this->limit = max(1, min(20, (int)$this->resolver->resolveValue($config['limit'])));
                }
                if(isset($config['candidate_limit'])) {
                        $this->candidateLimit = max(1, (int)$this->resolver->resolveValue($config['candidate_limit']));
                }
                if(array_key_exists('minscore', $config)) {
                        $value = $this->resolver->resolveValue($config['minscore']);
                        $this->denseMinScore = $value === null || $value === '' ? null : (float)$value;
                }
                if(isset($config['collectionkey'])) {
                        $key = trim((string)$this->resolver->resolveValue($config['collectionkey']));
                        if($key !== '') {
                                $this->collectionKey = $key;
                        }
                }
        }

        public function init(array $resources, IAgentContext $context): void {
                $this->embeddingModel = $this->pickResource($resources, 'embedding', IAiEmbeddingModel::class);
                $this->retrievalIndex = $this->pickResource($resources, 'vectorstore', IRetrievalIndex::class);
                $this->logger = $this->pickResource($resources, 'logger', ILogger::class);
                $this->filters = $this->pickResources($resources, 'filters', IRetrievalFilterProvider::class);

                $this->collectionDefinition->getBackendCollectionName($this->collectionKey);
                $this->log('Initialized collectionKey=' . $this->collectionKey . ', mandatoryFilters=' . count($this->filters));
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Retrieval Search',
                                'category' => 'retrieval',
                                'tags' => ['retrieval', 'search', 'hybrid'],
                                'priority' => 50,
                                'readOnlyHint' => true,
                                'mutation' => false,
                                'requiresApproval' => false,
                                'function' => [
                                        'name' => 'retrieval_search',
                                        'description' => 'Searches indexed content for factual content questions, explanations, passage discovery, and grounded summaries. Use retrieval_search whenever the user asks to find content by meaning, topic, wording, phrase, phonetics, or exact text. Do not replace a requested content search with retrieval_browse. Preserve the user\'s core topical intent in query; when metadata filters already encode the scope, query should describe what to find rather than repeat the scope name. When an exact filter identifier is already known from the user or trusted context, pass that exact value unchanged and never broaden the requested scope by substituting a parent or ancestor identifier. When the answer or summary may span more than one chunk, use retrieval_search to find relevant anchor chunks, then call retrieval_context with selected hits to load their preceding and following chunks before answering. Search additional aspects when the first anchors do not cover the requested subject. Use auto when no search strategy is requested. If the user explicitly requests semantic, lexical/BM25/full-text, phonetic, or exact search, set mode to semantic, lexical, phonetic, or exact accordingly. Use phrases for ordered multi-token phrases: in phonetic mode they are matched after phonetic normalization; in all other modes they are matched in the normal text index. Use required_terms for required terms and excluded_terms for exclusions. If retrieval_search returns hits, treat them as retrieval evidence and summarize the relevant hit contents when the user asks for details. Do not describe a non-empty result set as no results; distinguish partial or indirect evidence from an empty result set. Use only metadata filters exposed by this schema; if a requested filter is unavailable, do not claim it was applied and do not silently substitute an unfiltered search. If filtering is required but field semantics, identifier kinds, or value shapes are unclear, call retrieval_filter_help before searching. Never guess a filter field, identifier kind, or value.',
                                        'parameters' => $this->getSearchToolParameters()
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Retrieval Browse',
                                'category' => 'retrieval',
                                'tags' => ['retrieval', 'browse', 'filter'],
                                'priority' => 49,
                                'readOnlyHint' => true,
                                'mutation' => false,
                                'requiresApproval' => false,
                                'function' => [
                                        'name' => 'retrieval_browse',
                                        'description' => 'Lists indexed content by approved metadata filters without a content query. Use retrieval_browse only for inventory or enumeration questions such as what indexed items exist inside a known metadata scope. Never use it as a substitute for semantic, lexical, phrase, phonetic, exact, or other topical content search; use retrieval_search for those requests. Browse results are not relevance-ranked and must not be used to conclude that topical content is absent. When an exact filter identifier is already known from the user or trusted context, pass that exact value unchanged and never broaden the requested scope by substituting a parent or ancestor identifier. Keep result sets small and prefer narrower filters over paging through broad scopes. If has_more is true, use next_offset only when additional inventory results are actually needed. Use only metadata filters exposed by this schema; if field semantics, identifier kinds, operators, or value shapes are unclear, call retrieval_filter_help first. Never guess a filter field, identifier kind, or value.',
                                        'parameters' => $this->getBrowseToolParameters()
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Retrieval Context',
                                'category' => 'retrieval',
                                'tags' => ['retrieval', 'context', 'neighbors'],
                                'priority' => 45,
                                'readOnlyHint' => true,
                                'mutation' => false,
                                'requiresApproval' => false,
                                'function' => [
                                        'name' => 'retrieval_context',
                                        'description' => 'Loads preceding and following chunks from the same content sequence around an exact hit returned by retrieval_search or retrieval_browse. Use it to expand an anchor hit before answering content questions or producing summaries when the hit alone may omit definitions, qualifications, examples, or continuations. Pass that hit\'s retrieval_ref value verbatim. The reference is opaque and must not be derived or reconstructed from other result fields.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'retrieval_ref' => [
                                                                'type' => 'string',
                                                                'description' => 'Opaque retrieval reference copied verbatim from the selected retrieval_search or retrieval_browse result. Do not derive or reconstruct this value from any other field.'
                                                        ],
                                                        'before' => [
                                                                'type' => 'integer',
                                                                'description' => 'Number of preceding chunks from the same content sequence to load.',
                                                                'minimum' => 0,
                                                                'maximum' => 10,
                                                                'default' => 1
                                                        ],
                                                        'after' => [
                                                                'type' => 'integer',
                                                                'description' => 'Number of following chunks from the same content sequence to load.',
                                                                'minimum' => 0,
                                                                'maximum' => 10,
                                                                'default' => 1
                                                        ]
                                                ],
                                                'required' => ['retrieval_ref'],
                                                'additionalProperties' => false
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Retrieval Filter Help',
                                'category' => 'retrieval',
                                'tags' => ['retrieval', 'filter', 'help'],
                                'priority' => 48,
                                'readOnlyHint' => true,
                                'mutation' => false,
                                'requiresApproval' => false,
                                'function' => [
                                        'name' => 'retrieval_filter_help',
                                        'description' => 'Explains the agent-approved metadata filters available for retrieval_search and retrieval_browse in the active collection. Call this before searching or browsing when filtering is needed but field semantics, identifier kinds, operators, or value shapes are unclear. It does not expose stored payload metadata or authorization fields.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => new \stdClass(),
                                                'required' => [],
                                                'additionalProperties' => false
                                        ]
                                ]
                        ]
                ];
        }

        public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
                return match($toolName) {
                        'retrieval_search' => $this->callSearch($arguments),
                        'retrieval_browse' => $this->callBrowse($arguments),
                        'retrieval_context' => $this->callContext($arguments),
                        'retrieval_filter_help' => $this->callFilterHelp(),
                        default => throw new \InvalidArgumentException("Unsupported tool: {$toolName}")
                };
        }

        private function callSearch(array $arguments): array {
                if(!$this->retrievalIndex instanceof IRetrievalIndex) {
                        return $this->error('Retrieval index not initialized.');
                }

                $query = trim((string)($arguments['query'] ?? ''));
                if($query === '') {
                        return $this->error('Missing required parameter: query');
                }

                $mode = strtolower(trim((string)($arguments['mode'] ?? RetrievalSearchRequest::MODE_AUTO)));
                if(!in_array($mode, $this->getSearchModes(), true)) {
                        return $this->error('Unsupported retrieval mode: ' . $mode);
                }

                try {
                        $filter = $this->buildEffectiveFilter($arguments['filters'] ?? []);
                        $phraseInput = $this->normalizeStringList($arguments['phrases'] ?? []);
                        $phrases = $mode === RetrievalSearchRequest::MODE_PHONETIC ? [] : $phraseInput;
                        $phoneticPhrases = $mode === RetrievalSearchRequest::MODE_PHONETIC
                                ? $this->materializePhrases($phraseInput)
                                : [];
                        $requiredTerms = $this->normalizeStringList($arguments['required_terms'] ?? []);
                        $excludedTerms = $this->normalizeStringList($arguments['excluded_terms'] ?? []);
                        $phoneticMode = strtolower(trim((string)($arguments['phonetic'] ?? 'auto')));
                        $phoneticText = $this->buildPhoneticQuery($query, $mode, $phoneticMode);
                        $denseVector = $this->requiresDenseVector($mode) ? $this->embedQuery($query) : [];
                        $topK = max(1, min(20, (int)($arguments['top_k'] ?? $this->limit)));

                        $request = new RetrievalSearchRequest(
                                collectionKey: $this->collectionKey,
                                query: $query,
                                mode: $mode,
                                denseVector: $denseVector,
                                filterSpec: $filter,
                                phrases: $phrases,
                                phoneticPhrases: $phoneticPhrases,
                                requiredTerms: $requiredTerms,
                                excludedTerms: $excludedTerms,
                                phoneticText: $phoneticText,
                                limit: $topK,
                                candidateLimit: max($topK, $this->candidateLimit),
                                denseMinScore: $this->denseMinScore
                        );

                        $result = $this->retrievalIndex->search($request);
                }
                catch(\Throwable $e) {
                        return $this->error('Retrieval search failed: ' . $e->getMessage());
                }

                return [
                        'query' => $query,
                        'mode' => $mode,
                        'channels' => $result->getChannels(),
                        'results' => array_map(
                                fn(RetrievalHit $hit): array => $this->hitToAgentResult($hit),
                                $result->getHits()
                        )
                ];
        }

        private function callBrowse(array $arguments): array {
                if(!$this->retrievalIndex instanceof IRetrievalIndex) {
                        return $this->error('Retrieval index not initialized.');
                }
                if(!$this->retrievalIndex instanceof IRetrievalIndexInspector) {
                        return $this->error('Retrieval index does not support browsing.');
                }

                $limit = max(1, min(8, (int)($arguments['limit'] ?? 5)));
                $offset = $arguments['offset'] ?? null;
                if($offset === '') {
                        $offset = null;
                }
                if($offset !== null && !is_string($offset) && !is_int($offset)) {
                        return $this->error('Retrieval browse offset must be a string or integer.');
                }

                try {
                        $filter = $this->buildEffectiveFilter($arguments['filters'] ?? []);
                        $result = $this->retrievalIndex->inspectPoints(
                                $this->collectionKey,
                                $limit,
                                $filter,
                                $offset,
                                false
                        );
                }
                catch(\Throwable $e) {
                        return $this->error('Retrieval browse failed: ' . $e->getMessage());
                }

                $results = [];
                $points = is_array($result['points'] ?? null) ? $result['points'] : [];
                foreach($points as $point) {
                        if(!is_array($point)) continue;

                        $id = $point['id'] ?? null;
                        if(!is_string($id) && !is_int($id)) continue;

                        $payload = is_array($point['payload'] ?? null) ? $point['payload'] : [];
                        $results[] = [
                                'retrieval_ref' => (string)$id,
                                'context' => $this->collectionDefinition->projectPayload($this->collectionKey, $payload)
                        ];
                }

                $nextOffset = $result['next_offset'] ?? null;
                if(!is_string($nextOffset) && !is_int($nextOffset)) {
                        $nextOffset = null;
                }

                $out = [
                        'offset' => $offset,
                        'returned' => count($results),
                        'has_more' => $nextOffset !== null,
                        'next_offset' => $nextOffset,
                        'results' => $results
                ];
                if($nextOffset !== null) {
                        $out['guidance'] = 'More results are available. Pass next_offset verbatim as offset only when more results are needed; otherwise prefer narrowing the filters.';
                }

                return $out;
        }

        private function callContext(array $arguments): array {
                if(!$this->retrievalIndex instanceof IRetrievalIndex) {
                        return $this->error('Retrieval index not initialized.');
                }

                $retrievalRef = trim((string)($arguments['retrieval_ref'] ?? ''));
                if($retrievalRef === '') {
                        return $this->error('Missing required parameter: retrieval_ref');
                }

                $before = max(0, min(10, (int)($arguments['before'] ?? 1)));
                $after = max(0, min(10, (int)($arguments['after'] ?? 1)));

                try {
                        $result = $this->retrievalIndex->context(
                                $this->collectionKey,
                                $retrievalRef,
                                $before,
                                $after,
                                $this->buildMandatoryFilter()
                        );
                }
                catch(\Throwable $e) {
                        return $this->error('Retrieval context failed: ' . $e->getMessage());
                }

                return [
                        'retrieval_ref' => $retrievalRef,
                        'chunks' => array_map(
                                fn(RetrievalHit $hit): array => $this->hitToAgentResult($hit),
                                $result->getHits()
                        )
                ];
        }

        /** @return array<string,mixed> */
        private function callFilterHelp(): array {
                return [
                        'collection_key' => $this->collectionKey,
                        'filters' => $this->getAgentFilterHelpEntries(),
                        'guidance' => [
                                'Use only the listed field and operator combinations.',
                                'Match every identifier and value to the field description and expected value type.',
                                'Do not guess filter fields, identifier kinds, or values when the required scope cannot be mapped confidently.',
                                'When an exact filter value is already known from the user or trusted context, use that exact value and do not substitute a broader parent or ancestor value.',
                                'Agent-requested filters only narrow mandatory server-side restrictions and cannot relax them.'
                        ]
                ];
        }

        /** @return array<string,mixed> */
        private function getSearchToolParameters(): array {
                return [
                        'type' => 'object',
                        'properties' => [
                                'query' => [
                                        'type' => 'string',
                                        'description' => 'Query text expressing the content need. Preserve the user\'s core topical terms. When filters already encode a scope, do not replace the topic with the scope name.'
                                ],
                                'mode' => [
                                        'type' => 'string',
                                        'enum' => $this->getSearchModes(),
                                        'default' => RetrievalSearchRequest::MODE_AUTO,
                                        'description' => 'Retrieval strategy. Use auto only when the user did not request a specific strategy. Explicit semantic, lexical/BM25/full-text, phonetic, or exact requests must use the matching mode.'
                                ],
                                'filters' => $this->getAgentFilterArraySchema(),
                                'phrases' => $this->stringArraySchema('Ordered multi-token phrases. In mode=phonetic each phrase is phonetic-normalized before ordered phrase matching; in all other modes it is matched in the normal text index. For a term that merely must occur, use required_terms instead.'),
                                'required_terms' => $this->stringArraySchema('Terms that must occur in the normal text index. Use this when the user requires a word or term to be present; do not encode it as a phrase unless contiguous phrase order is required.'),
                                'excluded_terms' => $this->stringArraySchema('Terms that must not occur in the normal text index.'),
                                'phonetic' => [
                                        'type' => 'string',
                                        'enum' => ['auto', 'on', 'off'],
                                        'default' => 'auto',
                                        'description' => 'Controls the phonetic retrieval channel. auto only enables it for suitable short name-like queries.'
                                ],
                                'top_k' => [
                                        'type' => 'integer',
                                        'minimum' => 1,
                                        'maximum' => 20,
                                        'default' => $this->limit
                                ]
                        ],
                        'required' => ['query'],
                        'additionalProperties' => false
                ];
        }

        /** @return array<string,mixed> */
        private function getBrowseToolParameters(): array {
                return [
                        'type' => 'object',
                        'properties' => [
                                'filters' => $this->getAgentFilterArraySchema(),
                                'limit' => [
                                        'type' => 'integer',
                                        'description' => 'Maximum number of inventory results to return. Browse is intentionally capped at 8; narrow filters before requesting additional pages.',
                                        'minimum' => 1,
                                        'maximum' => 8,
                                        'default' => 5
                                ],
                                'offset' => [
                                        'type' => ['string', 'integer'],
                                        'description' => 'Opaque continuation offset copied verbatim from next_offset of a previous retrieval_browse result.'
                                ]
                        ],
                        'required' => [],
                        'additionalProperties' => false
                ];
        }

        /** @return array<string,mixed> */
        private function getAgentFilterArraySchema(): array {
                $variants = [];

                foreach($this->getAgentFilterHelpEntries() as $definition) {
                        $field = (string)$definition['field'];
                        $type = (string)$definition['type'];
                        $description = (string)($definition['description'] ?? '');
                        $valueDescription = (string)($definition['value_description'] ?? '');
                        $examples = is_array($definition['examples'] ?? null) ? $definition['examples'] : [];

                        foreach($definition['operators'] as $operator) {
                                $fieldSchema = ['type' => 'string', 'enum' => [$field]];
                                if($description !== '') {
                                        $fieldSchema['description'] = $description;
                                }

                                $valueSchema = $this->getFilterValueSchema($type, $operator);
                                if($valueDescription !== '') {
                                        $valueSchema['description'] = $valueDescription;
                                }

                                $operatorExamples = array_values(array_filter(
                                        $examples,
                                        static fn(array $example): bool => ($example['operator'] ?? null) === $operator
                                ));

                                $variant = [
                                        'type' => 'object',
                                        'properties' => [
                                                'field' => $fieldSchema,
                                                'operator' => ['type' => 'string', 'enum' => [$operator]],
                                                'value' => $valueSchema
                                        ],
                                        'required' => ['field', 'operator', 'value'],
                                        'additionalProperties' => false
                                ];
                                if($operatorExamples !== []) {
                                        $variant['examples'] = $operatorExamples;
                                }

                                $variants[] = $variant;
                        }
                }

                $out = [
                        'type' => 'array',
                        'description' => 'Optional domain-approved metadata filters. Only the listed field/operator combinations are accepted. Never invent unavailable filter fields; if the user requests one, report that the filter is unavailable instead of claiming it was applied.',
                        'default' => []
                ];
                if($variants === []) {
                        $out['maxItems'] = 0;
                        $out['items'] = ['type' => 'object'];
                }
                else {
                        $out['items'] = ['oneOf' => $variants];
                }

                return $out;
        }

        /** @return array<int,array<string,mixed>> */
        private function getAgentFilterHelpEntries(): array {
                $schema = $this->collectionDefinition->getAgentFilterSchema($this->collectionKey);
                $out = [];

                foreach($schema as $field => $definition) {
                        if(!is_string($field) || trim($field) === '' || !is_array($definition)) continue;

                        $type = strtolower(trim((string)($definition['type'] ?? '')));
                        $operators = $this->normalizeFilterOperators($definition['operators'] ?? []);
                        if($type === '' || $operators === []) continue;

                        $entry = [
                                'field' => trim($field),
                                'type' => $type,
                                'operators' => $operators
                        ];
                        foreach(['description', 'value_description'] as $key) {
                                $value = $definition[$key] ?? null;
                                if(is_scalar($value) && trim((string)$value) !== '') {
                                        $entry[$key] = trim((string)$value);
                                }
                        }

                        $examples = $this->normalizeFilterExamples(
                                trim($field),
                                $operators,
                                $definition,
                                $definition['examples'] ?? []
                        );
                        if($examples !== []) {
                                $entry['examples'] = $examples;
                        }

                        $out[] = $entry;
                }

                return $out;
        }

        /** @return string[] */
        private function normalizeFilterOperators(mixed $operators): array {
                if(!is_array($operators)) return [];

                $supported = ['eq', 'in', 'range', 'text', 'phrase'];
                $out = [];
                foreach($operators as $operator) {
                        $operator = strtolower(trim((string)$operator));
                        if($operator !== '' && in_array($operator, $supported, true)) {
                                $out[$operator] = $operator;
                        }
                }

                return array_values($out);
        }

        /** @return array<int,array<string,mixed>> */
        private function normalizeFilterExamples(
                string $field,
                array $operators,
                array $definition,
                mixed $examples
        ): array {
                if(!is_array($examples)) return [];

                $out = [];
                foreach($examples as $example) {
                        if(!is_array($example)) continue;

                        $operator = strtolower(trim((string)($example['operator'] ?? '')));
                        if(!in_array($operator, $operators, true) || !array_key_exists('value', $example)) continue;

                        try {
                                $this->validateFilterValue($field, $operator, $example['value'], $definition);
                        }
                        catch(\Throwable $e) {
                                continue;
                        }

                        $out[] = [
                                'field' => $field,
                                'operator' => $operator,
                                'value' => $example['value']
                        ];
                }

                return $out;
        }

        /** @return array<string,mixed> */
        private function getFilterValueSchema(string $type, string $operator): array {
                if(in_array($operator, ['text', 'phrase'], true)) {
                        return ['type' => 'string', 'minLength' => 1];
                }

                $scalar = $this->getFilterScalarSchema($type);
                if($operator === 'in') {
                        return [
                                'type' => 'array',
                                'items' => $scalar,
                                'minItems' => 1,
                                'uniqueItems' => true
                        ];
                }
                if($operator === 'range') {
                        return [
                                'type' => 'object',
                                'properties' => [
                                        'gt' => $scalar,
                                        'gte' => $scalar,
                                        'lt' => $scalar,
                                        'lte' => $scalar
                                ],
                                'minProperties' => 1,
                                'additionalProperties' => false
                        ];
                }

                return $scalar;
        }

        /** @return array<string,mixed> */
        private function getFilterScalarSchema(string $type): array {
                return match($type) {
                        'integer' => ['type' => 'integer'],
                        'float' => ['type' => 'number'],
                        'bool', 'boolean' => ['type' => 'boolean'],
                        'datetime' => [
                                'type' => 'string',
                                'description' => 'Qdrant datetime value, for example 2026-08-12T10:30:00Z or 2026-08-12 10:30:00.'
                        ],
                        default => ['type' => 'string', 'minLength' => 1]
                };
        }

        /** @return string[] */
        private function getSearchModes(): array {
                return [
                        RetrievalSearchRequest::MODE_AUTO,
                        RetrievalSearchRequest::MODE_HYBRID,
                        RetrievalSearchRequest::MODE_SEMANTIC,
                        RetrievalSearchRequest::MODE_LEXICAL,
                        RetrievalSearchRequest::MODE_PHONETIC,
                        RetrievalSearchRequest::MODE_EXACT
                ];
        }

        /** @return array<string,mixed> */
        private function stringArraySchema(string $description): array {
                return [
                        'type' => 'array',
                        'description' => $description,
                        'items' => ['type' => 'string'],
                        'default' => []
                ];
        }

        /** @return array<float> */
        private function embedQuery(string $query): array {
                if(!$this->embeddingModel instanceof IAiEmbeddingModel) {
                        throw new \RuntimeException('Embedding model not initialized.');
                }

                $vector = $this->embeddingModel->embed([$query])[0] ?? null;
                if(!is_array($vector) || $vector === []) {
                        throw new \RuntimeException('No embedding generated for query.');
                }
                return $vector;
        }

        private function requiresDenseVector(string $mode): bool {
                return in_array($mode, [
                        RetrievalSearchRequest::MODE_AUTO,
                        RetrievalSearchRequest::MODE_HYBRID,
                        RetrievalSearchRequest::MODE_SEMANTIC
                ], true);
        }

        private function buildPhoneticQuery(string $query, string $mode, string $phoneticMode): string {
                if(!in_array($phoneticMode, ['auto', 'on', 'off'], true)) {
                        throw new \InvalidArgumentException('Unsupported phonetic mode: ' . $phoneticMode);
                }
                if($phoneticMode === 'off') {
                        if($mode === RetrievalSearchRequest::MODE_PHONETIC) {
                                throw new \InvalidArgumentException('Phonetic retrieval mode cannot be combined with phonetic=off.');
                        }
                        return '';
                }

                $enabled = $phoneticMode === 'on'
                        || $mode === RetrievalSearchRequest::MODE_PHONETIC
                        || ($phoneticMode === 'auto' && $this->isSuitablePhoneticQuery($query));

                return $enabled ? $this->phoneticTextMaterializer->materialize($this->collectionKey, $query) : '';
        }

        private function isSuitablePhoneticQuery(string $query): bool {
                if(preg_match('/\d|§|https?:\/\/|www\.|@/iu', $query) === 1) {
                        return false;
                }

                $matches = [];
                preg_match_all('/\p{L}[\p{L}\p{M}\'’\-]*/u', $query, $matches);
                $tokens = array_values(array_filter(
                        $matches[0] ?? [],
                        static fn(string $token): bool => mb_strlen(trim($token)) >= 3
                ));

                return count($tokens) >= 1 && count($tokens) <= 4;
        }

        /** @param string[] $phrases @return string[] */
        private function materializePhrases(array $phrases): array {
                $out = [];
                foreach($phrases as $phrase) {
                        $materialized = $this->phoneticTextMaterializer->materialize($this->collectionKey, $phrase);
                        if($materialized !== '') $out[] = $materialized;
                }
                return $out;
        }

        /** @return array<string,mixed> */
        private function buildEffectiveFilter(mixed $filters): array {
                return $this->mergeFilterSpecs(
                        $this->buildMandatoryFilter(),
                        $this->buildAgentFilter($filters)
                );
        }

        /** @return array<string,mixed>|null */
        private function buildMandatoryFilter(): ?array {
                $out = null;
                foreach($this->filters as $filter) {
                        $spec = $filter->getRetrievalFilter();
                        if(is_array($spec)) {
                                $out = $this->mergeFilterSpecs($out, $spec);
                        }
                }
                return $out;
        }

        /** @return array<string,mixed>|null */
        private function buildAgentFilter(mixed $filters): ?array {
                if($filters === null || $filters === []) return null;
                if(!is_array($filters)) {
                        throw new \InvalidArgumentException('filters must be an array.');
                }

                $schema = $this->collectionDefinition->getAgentFilterSchema($this->collectionKey);
                $must = [];
                foreach($filters as $filter) {
                        if(!is_array($filter)) {
                                throw new \InvalidArgumentException('Each retrieval filter must be an object.');
                        }

                        $field = trim((string)($filter['field'] ?? ''));
                        $operator = strtolower(trim((string)($filter['operator'] ?? '')));
                        if($field === '' || !isset($schema[$field]) || !is_array($schema[$field])) {
                                throw new \InvalidArgumentException("Retrieval filter field '{$field}' is not available.");
                        }

                        $operators = $schema[$field]['operators'] ?? [];
                        if(!is_array($operators) || !in_array($operator, $operators, true)) {
                                throw new \InvalidArgumentException("Retrieval filter operator '{$operator}' is not allowed for field '{$field}'.");
                        }

                        $value = $filter['value'] ?? null;
                        $this->validateFilterValue($field, $operator, $value, $schema[$field]);
                        $must[] = ['field' => $field, 'operator' => $operator, 'value' => $value];
                }

                return $must === [] ? null : ['must' => $must];
        }

        /** @param array<string,mixed> $definition */
        private function validateFilterValue(string $field, string $operator, mixed $value, array $definition): void {
                $type = strtolower(trim((string)($definition['type'] ?? '')));

                if(in_array($operator, ['text', 'phrase'], true)) {
                        if($type !== 'text' || !is_string($value) || trim($value) === '') {
                                throw new \InvalidArgumentException("Retrieval {$operator} filter for '{$field}' requires non-empty text.");
                        }
                        return;
                }

                if($operator === 'in') {
                        if(!is_array($value) || $value === []) {
                                throw new \InvalidArgumentException("Retrieval in-filter for '{$field}' requires a non-empty array.");
                        }
                        foreach($value as $item) {
                                $this->assertFilterScalarType($field, $type, $item);
                        }
                        return;
                }

                if($operator === 'range') {
                        if(!is_array($value)) {
                                throw new \InvalidArgumentException("Retrieval range filter for '{$field}' requires an object value.");
                        }

                        $bounds = array_intersect(['gt', 'gte', 'lt', 'lte'], array_keys($value));
                        if($bounds === []) {
                                throw new \InvalidArgumentException("Retrieval range filter for '{$field}' requires gt, gte, lt, or lte.");
                        }
                        if(count($bounds) !== count($value)) {
                                throw new \InvalidArgumentException("Retrieval range filter for '{$field}' contains unsupported bounds.");
                        }
                        foreach($bounds as $bound) {
                                $this->assertRangeBoundType($field, $type, $value[$bound]);
                        }
                        return;
                }

                if($operator === 'eq') {
                        $this->assertFilterScalarType($field, $type, $value);
                        return;
                }

                throw new \InvalidArgumentException("Unsupported retrieval filter operator '{$operator}'.");
        }

        private function assertFilterScalarType(string $field, string $type, mixed $value): void {
                $valid = match($type) {
                        'integer' => is_int($value),
                        'float' => is_int($value) || is_float($value),
                        'bool', 'boolean' => is_bool($value),
                        'keyword', 'text', 'uuid', 'datetime' => is_string($value) && trim($value) !== '',
                        default => false
                };

                if(!$valid) {
                        throw new \InvalidArgumentException("Retrieval filter for '{$field}' requires a {$type} value.");
                }
        }

        private function assertRangeBoundType(string $field, string $type, mixed $value): void {
                $valid = match($type) {
                        'integer' => is_int($value),
                        'float' => is_int($value) || is_float($value),
                        'datetime' => is_string($value) && trim($value) !== '',
                        default => false
                };

                if(!$valid) {
                        throw new \InvalidArgumentException("Retrieval range filter for '{$field}' is not valid for type '{$type}'.");
                }
        }

        /** @return array<string,mixed> */
        private function mergeFilterSpecs(?array $a, ?array $b): array {
                $out = [];
                foreach(['must', 'should', 'must_not'] as $group) {
                        $left = is_array($a[$group] ?? null) ? $a[$group] : [];
                        $right = is_array($b[$group] ?? null) ? $b[$group] : [];
                        $merged = array_values(array_merge($left, $right));
                        if($merged !== []) $out[$group] = $merged;
                }
                return $out;
        }

        /** @return string[] */
        private function normalizeStringList(mixed $value): array {
                if($value === null || $value === []) return [];
                if(!is_array($value)) {
                        throw new \InvalidArgumentException('Expected an array of strings.');
                }

                $out = [];
                foreach($value as $item) {
                        if(!is_string($item)) {
                                throw new \InvalidArgumentException('Expected an array of strings.');
                        }

                        $item = trim($item);
                        if($item !== '') $out[$item] = $item;
                }
                return array_values($out);
        }

        /** @return array<string,mixed> */
        private function hitToAgentResult(RetrievalHit $hit): array {
                $out = [
                        'retrieval_ref' => $hit->id,
                        'context' => $hit->payload
                ];
                if($hit->score !== null) $out['score'] = $hit->score;
                return $out;
        }

        private function pickResource(array $resources, string $dock, string $class): mixed {
                $list = $resources[$dock] ?? null;
                return is_array($list) && isset($list[0]) && $list[0] instanceof $class ? $list[0] : null;
        }

        private function pickResources(array $resources, string $dock, string $class): array {
                $list = $resources[$dock] ?? null;
                if(!is_array($list)) return [];
                return array_values(array_filter($list, static fn($resource) => $resource instanceof $class));
        }

        private function log(string $message): void {
                if($this->logger) {
                        $this->logger->log('RetrievalAgentTool', '[' . $this->getId() . '] ' . $message);
                }
        }

        /** @return array{error:string} */
        private function error(string $message): array {
                $this->log('ERROR: ' . $message);
                return ['error' => $message];
        }
}
