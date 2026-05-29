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

namespace MissionBay\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use RuntimeException;

final class ChatLlmAdminDisplay implements IDisplay {

	private const SETTINGS_GROUP = 'chat-llm';
	private const PROVIDER_GROUP = 'ai-provider';

	/**
	 * @var array<int,string>
	 */
	private const MODEL_SUGGESTIONS = [
		'gpt-4o-mini',
		'gpt-4o',
		'mistral-small-latest',
		'mistral-large-latest',
		'Qwen/Qwen2.5-14B-Instruct-AWQ',
	];

	public function __construct(
		private readonly IMvcView $view,
		private readonly IRequest $request,
		private readonly ILinkTargetService $linkTargetService,
		private readonly ISettingsStore $settingsStore
	) {}

	public static function getName(): string {
		return 'chatllmadmindisplay';
	}

	public function getHelp(): string {
		return 'Configure chat LLM models stored in settings group "chat-llm".';
	}

	public function setData($data) {
		// no-op
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$out = strtolower((string)$out);

		if ($out === 'json') {
			return $this->handleJson();
		}

		return $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MissionBay');
		$this->view->setTemplate('Display/ChatLlmAdminDisplay.php');

		$instanceId = 'chatllmadm-' . uniqid();
		$modelListId = $instanceId . '-models';

		$this->view->assign('instanceId', $instanceId);
		$this->view->assign('endpoint', $this->buildEndpointBase());
		$this->view->assign('configGroup', self::SETTINGS_GROUP);
		$this->view->assign('providerGroup', self::PROVIDER_GROUP);
		$this->view->assign('modelSuggestions', self::MODEL_SUGGESTIONS);
		$this->view->assign('modelListId', $modelListId);

		return $this->view->loadTemplate();
	}

	private function handleJson(): string {
		$action = strtolower(trim((string)$this->request->request('action', '')));

		try {
			return match($action) {
				'list' => $this->jsonSuccess([
					'group' => self::SETTINGS_GROUP,
					'provider_group' => self::PROVIDER_GROUP,
					'providers' => $this->listProviders(),
					'llms' => $this->listLlms(),
				]),
				'save' => $this->jsonSuccess([
					'group' => self::SETTINGS_GROUP,
					'llm' => $this->saveLlm(),
				]),
				'remove' => $this->jsonSuccess([
					'group' => self::SETTINGS_GROUP,
					'name' => $this->removeLlm(),
				]),
				default => $this->jsonError("Unknown action '$action'. Use: list|save|remove"),
			};
		}
		catch(\Throwable $e) {
			return $this->jsonError('Exception: ' . $e->getMessage());
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function listLlms(): array {
		$group = $this->settingsStore->getGroup(self::SETTINGS_GROUP);
		$providers = $this->listProvidersByName();
		$rows = [];

		foreach($group as $name => $settings) {
			if(!is_string($name) || $name === '' || !is_array($settings)) {
				continue;
			}

			$rows[] = $this->normalizeLlm($name, $settings, $providers);
		}

		usort($rows, function(array $a, array $b): int {
			$aSort = trim((string)($a['label'] ?? ''));
			$bSort = trim((string)($b['label'] ?? ''));

			if($aSort === '') {
				$aSort = (string)($a['name'] ?? '');
			}

			if($bSort === '') {
				$bSort = (string)($b['name'] ?? '');
			}

			$cmp = strcasecmp($aSort, $bSort);
			if($cmp !== 0) {
				return $cmp;
			}

			return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
		});

		return $rows;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function listProviders(): array {
		$rows = array_values($this->listProvidersByName());

		usort($rows, function(array $a, array $b): int {
			$aSort = trim((string)($a['label'] ?? ''));
			$bSort = trim((string)($b['label'] ?? ''));

			if($aSort === '') {
				$aSort = (string)($a['name'] ?? '');
			}

			if($bSort === '') {
				$bSort = (string)($b['name'] ?? '');
			}

			$cmp = strcasecmp($aSort, $bSort);
			if($cmp !== 0) {
				return $cmp;
			}

			return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
		});

		return $rows;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function listProvidersByName(): array {
		$group = $this->settingsStore->getGroup(self::PROVIDER_GROUP);
		$rows = [];

		foreach($group as $name => $settings) {
			if(!is_string($name) || $name === '' || !is_array($settings)) {
				continue;
			}

			$provider = $this->normalizeProvider($name, $settings);
			$rows[(string)$provider['name']] = $provider;
		}

		return $rows;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function saveLlm(): array {
		$name = $this->normalizeKey((string)$this->request->request('name', ''));
		$label = trim((string)$this->request->request('label', ''));
		$provider = $this->normalizeKey((string)$this->request->request('provider', ''));
		$model = trim((string)$this->request->request('model', ''));
		$temperature = $this->readOptionalFloat('temperature', 'Temperature');
		$maxTokens = $this->readOptionalInt('max_tokens', 'Max tokens');
		$topP = $this->readOptionalFloat('top_p', 'Top P');
		$timeoutSeconds = $this->readOptionalInt('timeout_seconds', 'Timeout seconds');
		$connectTimeoutSeconds = $this->readOptionalInt('connect_timeout_seconds', 'Connect timeout seconds');
		$params = $this->readParams();
		$enabled = $this->normalizeBool($this->request->request('enabled', 0));

		if($name === '') {
			throw new RuntimeException('Missing settings name.');
		}

		if($label === '') {
			throw new RuntimeException('Missing label.');
		}

		if($provider === '') {
			throw new RuntimeException('Missing provider.');
		}

		if(!$this->settingsStore->has(self::PROVIDER_GROUP, $provider)) {
			throw new RuntimeException('Provider not found: ' . $provider);
		}

		if($model === '') {
			throw new RuntimeException('Missing model name.');
		}

		$settings = [
			'label' => $label,
			'provider' => $provider,
			'model' => $model,
			'enabled' => $enabled,
			'params' => $params,
		];

		if($temperature !== null) {
			$settings['temperature'] = $temperature;
		}

		if($maxTokens !== null) {
			$settings['max_tokens'] = $maxTokens;
		}

		if($topP !== null) {
			$settings['top_p'] = $topP;
		}

		if($timeoutSeconds !== null) {
			$settings['timeout_seconds'] = $timeoutSeconds;
		}

		if($connectTimeoutSeconds !== null) {
			$settings['connect_timeout_seconds'] = $connectTimeoutSeconds;
		}

		$this->settingsStore->set(self::SETTINGS_GROUP, $name, $settings);
		$this->settingsStore->save();

		return $this->normalizeLlm($name, $settings, $this->listProvidersByName());
	}

	private function removeLlm(): string {
		$name = $this->normalizeKey((string)$this->request->request('name', ''));

		if($name === '') {
			throw new RuntimeException('Missing settings name.');
		}

		$this->settingsStore->remove(self::SETTINGS_GROUP, $name);
		$this->settingsStore->save();

		return $name;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function normalizeProvider(string $name, array $settings): array {
		$name = $this->normalizeKey($name);
		$label = trim((string)($settings['label'] ?? ''));
		$driver = $this->normalizeKey((string)($settings['driver'] ?? ''));
		$endpoint = trim((string)($settings['endpoint'] ?? ''));
		$enabled = $this->normalizeBool($settings['enabled'] ?? false);

		if($label === '') {
			$label = $name;
		}

		return [
			'name' => $name,
			'label' => $label,
			'driver' => $driver,
			'endpoint' => $endpoint,
			'enabled' => $enabled,
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,array<string,mixed>> $providers
	 * @return array<string,mixed>
	 */
	private function normalizeLlm(string $name, array $settings, array $providers): array {
		$name = $this->normalizeKey($name);
		$label = trim((string)($settings['label'] ?? ''));
		$provider = $this->normalizeKey((string)($settings['provider'] ?? ($settings['provider_id'] ?? '')));
		$model = trim((string)($settings['model'] ?? ''));
		$enabled = $this->normalizeBool($settings['enabled'] ?? false);
		$params = is_array($settings['params'] ?? null) ? $settings['params'] : [];

		if($label === '') {
			$label = $name;
		}

		$providerLabel = '';
		$providerDriver = '';
		$providerEnabled = false;

		if(isset($providers[$provider])) {
			$providerLabel = (string)($providers[$provider]['label'] ?? '');
			$providerDriver = (string)($providers[$provider]['driver'] ?? '');
			$providerEnabled = $this->normalizeBool($providers[$provider]['enabled'] ?? false);
		}

		return [
			'name' => $name,
			'label' => $label,
			'provider' => $provider,
			'provider_label' => $providerLabel,
			'provider_driver' => $providerDriver,
			'provider_enabled' => $providerEnabled,
			'model' => $model,
			'temperature' => $this->normalizeNullableNumber($settings['temperature'] ?? null),
			'max_tokens' => $this->normalizeNullableNumber($settings['max_tokens'] ?? ($settings['maxtokens'] ?? null)),
			'top_p' => $this->normalizeNullableNumber($settings['top_p'] ?? null),
			'timeout_seconds' => $this->normalizeNullableNumber($settings['timeout_seconds'] ?? null),
			'connect_timeout_seconds' => $this->normalizeNullableNumber($settings['connect_timeout_seconds'] ?? null),
			'params' => $params,
			'enabled' => $enabled,
		];
	}

	private function readOptionalFloat(string $key, string $label): ?float {
		$value = trim((string)$this->request->request($key, ''));

		if($value === '') {
			return null;
		}

		if(!is_numeric($value)) {
			throw new RuntimeException($label . ' must be numeric.');
		}

		return (float)$value;
	}

	private function readOptionalInt(string $key, string $label): ?int {
		$value = trim((string)$this->request->request($key, ''));

		if($value === '') {
			return null;
		}

		if(!ctype_digit($value)) {
			throw new RuntimeException($label . ' must be a positive integer.');
		}

		return (int)$value;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function readParams(): array {
		$raw = trim((string)$this->request->request('params', ''));

		if($raw === '') {
			return [];
		}

		$decoded = json_decode($raw, true);

		if(!is_array($decoded)) {
			throw new RuntimeException('Params must be a valid JSON object.');
		}

		return $decoded;
	}

	private function normalizeNullableNumber(mixed $value): string {
		if($value === null || $value === '') {
			return '';
		}

		if(!is_numeric($value)) {
			return '';
		}

		return (string)$value;
	}

	private function normalizeKey(string $s): string {
		$s = trim($s);
		$s = strtolower($s);
		$s = preg_replace('/[^a-z0-9._-]+/', '', $s) ?? '';
		return $s;
	}

	private function normalizeBool(mixed $v): bool {
		if(is_bool($v)) {
			return $v;
		}

		$s = strtolower(trim((string)$v));
		return in_array($s, ['1', 'true', 'yes', 'on'], true);
	}

	private function buildEndpointBase(): string {
		return $this->linkTargetService->getLink(
			[
				'name' => self::getName(),
			],
			[
				'out' => 'json',
			]
		);
	}

	/**
	 * @param array<string,mixed> $data
	 * @return string
	 */
	private function jsonSuccess(array $data): string {
		return (string)json_encode([
			'status' => 'ok',
			'timestamp' => gmdate('c'),
			'data' => $data,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	private function jsonError(string $message): string {
		return (string)json_encode([
			'status' => 'error',
			'timestamp' => gmdate('c'),
			'message' => $message,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}
