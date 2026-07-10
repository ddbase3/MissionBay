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

use Base3\Api\IClassMap;
use Base3\Api\ISchemaProvider;
use Base3\Settings\Api\ISettingsStore;
use InvalidArgumentException;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Api\ISearchService;
use MissionBay\Connection\ConnectionConfig;
use MissionBay\SearchService\MistralWebSearchService;
use MissionBay\SearchService\OpenAiWebSearchService;
use MissionBay\Service\ServiceConfig;
use RuntimeException;

/**
 * ConfiguredSearchServiceAgentResource
 *
 * Loads a configured search service and delegates to the matching
 * ISearchService adapter.
 *
 * The resource also exposes the configured search service as an
 * assistant tool. Tool usage is intentionally defensive: configuration
 * errors are returned as structured tool results instead of breaking
 * the whole assistant flow during resource setup.
 */
class ConfiguredSearchServiceAgentResource extends AbstractConfiguredServiceAgentResource implements ISearchService, IAgentTool, ISchemaProvider {

	private const SEARCH_SETTINGS_GROUP = 'service-search';
	private const CONNECTION_SETTINGS_GROUP = 'connection';
	private const SERVICE_TYPE = 'search';
	private const SERVICE_ALIAS = 'search';
	private const TOOL_NAME = 'web_search';

	private ?ISearchService $service = null;
	private ?int $toolMaxResults = null;
	private bool $includeRawResult = false;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly IClassMap $classMap,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'configuredsearchserviceagentresource';
	}

	public function getDescription(): string {
		return 'Loads a configured web search service by id and delegates to the matching search adapter.';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'service' => [
					'type' => 'string',
					'description' => 'Configured search service id from the service-search settings group.'
				],
				'maxresults' => [
					'type' => 'integer',
					'description' => 'Optional maximum number of search results exposed through the web_search tool. This caps the tool argument max_results.',
					'minimum' => 1
				],
				'includeraw' => [
					'type' => 'boolean',
					'description' => 'Whether raw provider results should be included in tool responses.',
					'default' => false
				]
			],
			'required' => ['service']
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->setServiceConfigFromResourceConfig($config);
		$this->service = null;
		$this->resolvedOptions = [];

		$this->toolMaxResults = $this->readOptionalPositiveIntConfig($config, 'maxresults');
		$this->includeRawResult = $this->readOptionalBoolConfig($config, 'includeraw', false);

		// Do not configure the service here.
		// A broken search service must not prevent the assistant from starting.
	}

	public function search(string $query, array $options = []): array {
		return $this->ensureService()->search($query, $options);
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Web Search',
			'category' => 'web',
			'tags' => ['web', 'search', 'current-information'],
			'priority' => 60,
			'function' => [
				'name' => self::TOOL_NAME,
				'description' => 'Searches the web through the configured MissionBay search service. Use it for current or external information that is not already available in the conversation.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'query' => [
							'type' => 'string',
							'description' => 'The natural language search query.'
						],
						'max_results' => [
							'type' => 'integer',
							'description' => 'Optional maximum number of search results to return. The resource configuration may cap this value.',
							'minimum' => 1
						]
					],
					'required' => ['query']
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): array {
		if($name !== self::TOOL_NAME) {
			throw new InvalidArgumentException('Unsupported tool: ' . $name);
		}

		$query = $arguments['query'] ?? null;

		if(!is_string($query) || trim($query) === '') {
			return $this->errorResult(
				'Missing required parameter: query',
				'missing_query',
				$query,
				[]
			);
		}

		$query = trim($query);
		$options = $this->buildToolSearchOptions($arguments);

		try {
			$result = $this->search($query, $options);
		} catch(\Throwable $e) {
			return $this->errorResult(
				'Web search failed: ' . $e->getMessage(),
				'web_search_failed',
				$query,
				$options,
				$e
			);
		}

		return $this->buildToolResult($query, $options, $result);
	}

	protected function ensureConfigured(): void {
		try {
			$this->ensureService();
		} catch(\Throwable $e) {
			$this->resolvedOptions['configuration_error'] = [
				'message' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode()
			];
		}
	}

	protected function applyResolvedOptions(): void {
		if($this->service instanceof ISearchService) {
			$this->service->setOptions($this->resolvedOptions);
		}
	}

	private function ensureService(): ISearchService {
		if($this->service instanceof ISearchService) {
			return $this->service;
		}

		$this->configureService();

		if(!$this->service instanceof ISearchService) {
			throw new RuntimeException('Configured search service could not be initialized.');
		}

		return $this->service;
	}

	private function configureService(): void {
		$serviceId = $this->resolveServiceId();

		if($serviceId === '') {
			throw new RuntimeException('ConfiguredSearchServiceAgentResource requires config key "service".');
		}

		$serviceConfig = $this->loadServiceConfig(self::SEARCH_SETTINGS_GROUP, $serviceId, self::SERVICE_TYPE);
		$connectionConfig = $this->loadConnectionConfig(self::CONNECTION_SETTINGS_GROUP, $serviceConfig->getConnectionId());

		$serviceName = $this->resolveSearchServiceName($serviceConfig->getDriver());

		if($serviceName === '') {
			throw new RuntimeException(
				'Search service config has no usable driver: ' . $serviceId . ' ' . $this->formatConfigDebug($serviceConfig->toSettings())
			);
		}

		$service = $this->classMap->getInstanceByInterfaceName(ISearchService::class, $serviceName);

		if(!$service instanceof ISearchService) {
			throw new RuntimeException(
				'Unable to resolve search service "' . $serviceName . '" for driver "' . $serviceConfig->getDriver() . '".'
			);
		}

		$this->resolvedOptions = $this->buildRuntimeOptions($serviceConfig, $connectionConfig);
		$this->resolvedOptions = array_merge($this->resolvedOptions, $this->optionOverrides);

		$this->service = $service;
		$this->applyResolvedOptions();
	}

	private function resolveSearchServiceName(string $driver): string {
		$map = [
			'openai-websearch' => OpenAiWebSearchService::getName(),
			'mistral-websearch' => MistralWebSearchService::getName()
		];

		return $map[$driver] ?? '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildRuntimeOptions(ServiceConfig $serviceConfig, ConnectionConfig $connectionConfig): array {
		$options = $this->buildBaseRuntimeOptions($serviceConfig, $connectionConfig, self::SERVICE_ALIAS);
		$serviceOptions = $serviceConfig->getOptions();

		$options = $this->mergeServiceOptions($options, $serviceOptions, [
			'search_id' => true,
			'search_label' => true,
			'service_type' => true,
			'service_driver' => true,
			'connection_id' => true,
			'connection_label' => true,
			'connection_type' => true,
			'connection_driver' => true,
			'auth_type' => true,
			'auth_header_name' => true,
			'model' => true,
			'endpoint' => true,
			'base_url' => true,
			'apikey' => true,
			'auth_secret' => true
		]);

		$this->mapOptionalNumber($options, $serviceOptions, 'maxResults', 'max_results', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'timeoutSeconds', 'timeout_seconds', 'int');
		$this->mapOptionalNumber($options, $serviceOptions, 'connectTimeoutSeconds', 'connect_timeout_seconds', 'int');
		$this->mapOptionalBool($options, $serviceOptions, 'externalWebAccess', 'external_web_access');
		$this->mapOptionalString($options, $serviceOptions, 'searchContextSize', 'search_context_size');
		$this->mapOptionalString($options, $serviceOptions, 'returnTokenBudget', 'return_token_budget');
		$this->mapOptionalString($options, $serviceOptions, 'toolChoice', 'tool_choice');
		$this->mapOptionalArray($options, $serviceOptions, 'allowedDomains', 'allowed_domains');
		$this->mapOptionalArray($options, $serviceOptions, 'blockedDomains', 'blocked_domains');

		return $options;
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	private function buildToolSearchOptions(array $arguments): array {
		$options = [];

		$maxResults = $this->readOptionalPositiveIntArgument($arguments, 'max_results');

		if($maxResults !== null && $this->toolMaxResults !== null) {
			$maxResults = min($maxResults, $this->toolMaxResults);
		}

		if($maxResults === null) {
			$maxResults = $this->toolMaxResults;
		}

		if($maxResults !== null) {
			$options['max_results'] = $maxResults;
		}

		return $options;
	}

	/**
	 * @param array<string,mixed> $options
	 * @param array<string,mixed> $result
	 * @return array<string,mixed>
	 */
	private function buildToolResult(string $query, array $options, array $result): array {
		$result = $this->redactSensitiveValue($result);

		if(!$this->includeRawResult && isset($result['raw'])) {
			unset($result['raw']);
		}

		if(($result['ok'] ?? true) === false) {
			$result['query'] = $result['query'] ?? $query;

			if($options !== [] && !isset($result['options'])) {
				$result['options'] = $this->redactSensitiveValue($options);
			}

			return $result;
		}

		$out = [
			'ok' => true,
			'query' => $query
		];

		if($options !== []) {
			$out['options'] = $this->redactSensitiveValue($options);
		}

		if(isset($result['answer'])) {
			$out['answer'] = $result['answer'];
		}

		if(isset($result['results'])) {
			$out['results'] = $result['results'];
		}

		if(isset($result['citations'])) {
			$out['citations'] = $result['citations'];
		}

		$meta = $result;
		unset($meta['query'], $meta['answer'], $meta['results'], $meta['citations']);

		if($meta !== []) {
			$out['meta'] = $meta;
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private function readOptionalPositiveIntConfig(array $config, string $key): ?int {
		if(!array_key_exists($key, $config)) {
			return null;
		}

		$value = $this->resolver->resolveValue($config[$key]);

		if($value === null || $value === '' || !is_numeric($value)) {
			return null;
		}

		$value = (int)$value;

		return $value > 0 ? $value : null;
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private function readOptionalBoolConfig(array $config, string $key, bool $default): bool {
		if(!array_key_exists($key, $config)) {
			return $default;
		}

		return $this->toBool($this->resolver->resolveValue($config[$key]), $default);
	}

	/**
	 * @param array<string,mixed> $arguments
	 */
	private function readOptionalPositiveIntArgument(array $arguments, string $key): ?int {
		if(!array_key_exists($key, $arguments)) {
			return null;
		}

		$value = $arguments[$key];

		if($value === null || $value === '' || !is_numeric($value)) {
			return null;
		}

		$value = (int)$value;

		return $value > 0 ? $value : null;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function errorResult(
		string $message,
		string $errorCode,
		mixed $query = null,
		array $options = [],
		?\Throwable $exception = null
	): array {
		$out = [
			'ok' => false,
			'error_code' => $errorCode,
			'error' => $message
		];

		if(is_string($query) && trim($query) !== '') {
			$out['query'] = trim($query);
		}

		if($options !== []) {
			$out['options'] = $this->redactSensitiveValue($options);
		}

		if($exception !== null) {
			$out['diagnostic'] = [
				'type' => get_class($exception),
				'code' => $exception->getCode(),
				'message' => $exception->getMessage()
			];
		}

		return $out;
	}

	private function redactSensitiveValue(mixed $value): mixed {
		if(!is_array($value)) {
			return $value;
		}

		$out = [];

		foreach($value as $key => $item) {
			$keyString = strtolower((string)$key);

			if($this->isSensitiveKey($keyString)) {
				$out[$key] = '[redacted]';
				continue;
			}

			$out[$key] = $this->redactSensitiveValue($item);
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	private function mapOptionalString(array &$runtimeOptions, array $sourceOptions, string $sourceKey, string $targetKey): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$value = trim((string)$sourceOptions[$sourceKey]);

		if($value === '') {
			return;
		}

		$runtimeOptions[$targetKey] = $value;
	}

	/**
	 * @param array<string,mixed> $runtimeOptions
	 * @param array<string,mixed> $sourceOptions
	 */
	private function mapOptionalArray(array &$runtimeOptions, array $sourceOptions, string $sourceKey, string $targetKey): void {
		if(!array_key_exists($sourceKey, $sourceOptions)) {
			return;
		}

		$value = $sourceOptions[$sourceKey];

		if(!is_array($value)) {
			return;
		}

		$out = [];

		foreach($value as $item) {
			$item = trim((string)$item);

			if($item !== '') {
				$out[] = $item;
			}
		}

		if($out === []) {
			return;
		}

		$runtimeOptions[$targetKey] = array_values(array_unique($out));
	}
}
