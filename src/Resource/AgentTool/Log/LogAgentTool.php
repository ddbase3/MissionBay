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

namespace MissionBay\Resource\AgentTool\Log;

use Base3\Api\ISchemaProvider;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use InvalidArgumentException;
use MissionBay\Api\IAgentConfigValueResolver;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\AbstractConfiguredServiceAgentResource;

/**
 * LogAgentTool
 *
 * Allows an agent to write structured log messages from the tool loop.
 * The log scope is fixed by resource configuration and cannot be changed
 * by the agent during tool calls.
 */
class LogAgentTool extends AbstractConfiguredServiceAgentResource implements IAgentTool, ISchemaProvider {

	private const TOOL_NAME = 'agent_log';

	/**
	 * @var array<string>
	 */
	private const ALLOWED_LEVELS = [
		ILogger::EMERGENCY,
		ILogger::ALERT,
		ILogger::CRITICAL,
		ILogger::ERROR,
		ILogger::WARNING,
		ILogger::NOTICE,
		ILogger::INFO,
		ILogger::DEBUG
	];

	protected string $scope = 'agent';
	protected string $defaultLevel = ILogger::INFO;

	public function __construct(
		IAgentConfigValueResolver $resolver,
		ISettingsStore $settingsStore,
		private readonly ILogger $logger,
		?string $id = null
	) {
		parent::__construct($resolver, $settingsStore, $id);
	}

	public static function getName(): string {
		return 'logagenttool';
	}

	public function getDescription(): string {
		return 'Allows an agent to write log messages with a fixed configured scope.';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSchema(): array {
		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'scope' => [
					'type' => 'string',
					'description' => 'Fixed log scope used for all log entries written by this tool.'
				],
				'defaultlevel' => [
					'type' => 'string',
					'description' => 'Default log level used when the agent does not provide a level.',
					'enum' => self::ALLOWED_LEVELS,
					'default' => ILogger::INFO
				]
			],
			'required' => ['scope']
		];
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$scope = $this->readRequiredStringConfig($config, 'scope');
		if($scope !== '') {
			$this->scope = $scope;
		}

		$defaultLevel = $this->readOptionalLevelConfig($config, 'defaultlevel', ILogger::INFO);
		if($defaultLevel !== null) {
			$this->defaultLevel = $defaultLevel;
		}

		$this->resolvedOptions = [
			'scope' => $this->scope,
			'defaultlevel' => $this->defaultLevel
		];

		$this->applyResolvedOptions();
	}

	public function init(array $resources, IAgentContext $context): void {
		$this->writeInternalLog(
			ILogger::INFO,
			'Initialized scope=' . $this->scope . ', defaultLevel=' . $this->defaultLevel
		);
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Log',
			'category' => 'debugging',
			'tags' => ['logging', 'debugging', 'observability'],
			'priority' => 20,
			'function' => [
				'name' => self::TOOL_NAME,
				'description' => 'Writes a log message. The log scope is fixed by configuration and cannot be changed by the agent.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'message' => [
							'type' => 'string',
							'description' => 'The message to write to the log.'
						],
						'level' => [
							'type' => 'string',
							'description' => 'Optional log level. Defaults to the configured default level.',
							'enum' => self::ALLOWED_LEVELS
						],
						'data' => [
							'type' => 'object',
							'description' => 'Optional structured data to include with the log message.',
							'additionalProperties' => true
						]
					],
					'required' => ['message']
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): array {
		if($name !== self::TOOL_NAME) {
			throw new InvalidArgumentException('Unsupported tool: ' . $name);
		}

		$message = $arguments['message'] ?? null;

		if(!is_string($message) || trim($message) === '') {
			return $this->errorResult('Missing required parameter: message', 'missing_message');
		}

		$message = trim($message);
		$level = $this->readOptionalLevelArgument($arguments, 'level', $this->defaultLevel);

		if($level === null) {
			return $this->errorResult('Unsupported log level.', 'unsupported_level');
		}

		$data = $this->readOptionalDataArgument($arguments, 'data');

		if($data === null) {
			return $this->errorResult('Invalid parameter: data', 'invalid_data');
		}

		$this->writeLog($level, $message, $data);

		return [
			'ok' => true,
			'logged' => true,
			'scope' => $this->scope,
			'level' => $level
		];
	}

	protected function ensureConfigured(): void {
		$this->resolvedOptions = array_merge([
			'scope' => $this->scope,
			'defaultlevel' => $this->defaultLevel
		], $this->optionOverrides);

		$this->applyResolvedOptions();
	}

	protected function applyResolvedOptions(): void {
		$scope = $this->resolvedOptions['scope'] ?? null;

		if(is_scalar($scope)) {
			$scope = trim((string)$scope);

			if($scope !== '') {
				$this->scope = $scope;
			}
		}

		$defaultLevel = $this->resolvedOptions['defaultlevel'] ?? null;

		if(is_scalar($defaultLevel)) {
			$defaultLevel = $this->normalizeLevel((string)$defaultLevel);

			if($defaultLevel !== null) {
				$this->defaultLevel = $defaultLevel;
			}
		}
	}

	// -------------------------------------------------
	// Config
	// -------------------------------------------------

	/**
	 * @param array<string,mixed> $config
	 */
	private function readRequiredStringConfig(array $config, string $key): string {
		if(!array_key_exists($key, $config)) {
			return '';
		}

		$value = $this->resolver->resolveValue($config[$key]);

		if($value === null) {
			return '';
		}

		return trim((string)$value);
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private function readOptionalLevelConfig(array $config, string $key, string $default): ?string {
		if(!array_key_exists($key, $config)) {
			return $default;
		}

		$value = $this->resolver->resolveValue($config[$key]);

		if($value === null || $value === '') {
			return $default;
		}

		return $this->normalizeLevel((string)$value);
	}

	// -------------------------------------------------
	// Arguments
	// -------------------------------------------------

	/**
	 * @param array<string,mixed> $arguments
	 */
	private function readOptionalLevelArgument(array $arguments, string $key, string $default): ?string {
		if(!array_key_exists($key, $arguments)) {
			return $default;
		}

		$value = $arguments[$key];

		if($value === null || $value === '') {
			return $default;
		}

		if(!is_string($value)) {
			return null;
		}

		return $this->normalizeLevel($value);
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>|null
	 */
	private function readOptionalDataArgument(array $arguments, string $key): ?array {
		if(!array_key_exists($key, $arguments)) {
			return [];
		}

		if(!is_array($arguments[$key])) {
			return null;
		}

		return $arguments[$key];
	}

	// -------------------------------------------------
	// Helpers
	// -------------------------------------------------

	private function normalizeLevel(string $level): ?string {
		$level = strtolower(trim($level));

		return in_array($level, self::ALLOWED_LEVELS, true) ? $level : null;
	}

	protected function writeLog(string $level, string $message, array $data = []): void {
		$context = $data;
		$context['scope'] = $this->scope;
		$context['agentToolId'] = $this->getId();

		$this->logger->logLevel($level, $message, $context);
	}

	protected function writeInternalLog(string $level, string $message): void {
		$this->writeLog($level, '[' . $this->getId() . '] ' . $message);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function errorResult(string $message, string $errorCode): array {
		$this->writeInternalLog(ILogger::ERROR, 'ERROR: ' . $message);

		return [
			'ok' => false,
			'error_code' => $errorCode,
			'error' => $message
		];
	}
}
