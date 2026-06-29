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

namespace MissionBay\Content;

use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use Base3\Worker\Api\IJobExecutionPolicy;
use JsonException;
use MissionBay\Api\IAgentConfigFormService;
use Throwable;

class AgentConfigDisplay implements IDisplay {

        protected const FORM_ACTION_SAVE = 'save';
        protected const DEFAULT_GROUP = 'agent';

        protected array $data = [];

        protected array $messages = [];

        protected ?array $postedValues = null;

        /**
         * @var array<int,array<string,mixed>>|null
         */
        protected ?array $policyOptions = null;

        public function __construct(
                private readonly IMvcView $view,
                private readonly IRequest $request,
                private readonly ISettingsStore $settingsStore,
                private readonly ILinkTargetService $linkTargetService,
                private readonly IClassMap $classMap,
                private readonly IAgentConfigFormService $agentConfigFormService
        ) {}

        public static function getName(): string {
                return 'agentconfigdisplay';
        }

        // ---------------------------------------------------------------------
        // Render
        // ---------------------------------------------------------------------

        public function getOutput(string $out = 'html', bool $final = false): string {
                $out = strtolower(trim($out));

                if ($out === 'json') {
                        return $this->getJsonOutput($final);
                }

                if ($out !== 'html') {
                        return '';
                }

                $context = $this->getContext(false);

                if ($this->isSaveRequest($context)) {
                        $this->handleSave($context);
                }

                $values = $this->postedValues ?? $this->settingsToViewValues(
                        $this->loadSettings($context)
                );

                $this->view->setPath(DIR_PLUGIN . 'MissionBay');
                $this->view->setTemplate('Content/AgentConfigDisplay.php');

                $this->view->assign('title', $context['title']);
                $this->view->assign('description', $context['description']);
                $this->view->assign('group', $context['group']);
                $this->view->assign('name', $context['name']);
                $this->view->assign('form_id', $context['form_id']);
                $this->view->assign('form_action', $context['form_action']);
                $this->view->assign('submit_label', $context['submit_label']);
                $this->view->assign('mode', $context['mode']);
                $this->view->assign('save_mode', $context['save_mode']);
                $this->view->assign('save_url', $context['save_url']);
                $this->view->assign('render_form', $context['render_form']);
                $this->view->assign('values', $values);
                $this->view->assign('policy_options', $this->listPolicyOptions());
                $this->view->assign('messages', $this->messages);

                $this->agentConfigFormService->assignViewData($this->view, $values, [
                        'form_id' => $context['form_id']
                ]);

                return $this->view->loadTemplate();
        }

        public function getHelp(): string {
                return 'Configure one MissionBay agent instance and store its settings through ISettingsStore.';
        }

        public function setData($data) {
                $this->data = is_array($data) ? $data : [];
                $this->messages = [];
                $this->postedValues = null;
                $this->policyOptions = null;
        }

        // ---------------------------------------------------------------------
        // JSON endpoint
        // ---------------------------------------------------------------------

        protected function getJsonOutput(bool $final): string {
                if ($final && !headers_sent()) {
                        header('Content-Type: application/json; charset=UTF-8');
                }

                $action = trim((string)$this->request->request('action', ''));

                if ($action === '') {
                        $action = trim((string)$this->request->request('agent_config_action', ''));
                }

                if ($action !== self::FORM_ACTION_SAVE) {
                        return $this->jsonError('Unknown action.');
                }

                $context = $this->getContext(true);

                if (!$this->isSaveRequest($context)) {
                        return $this->jsonError('Configuration identity does not match.');
                }

                $result = $this->saveSettingsFromRequest($context);

                return $this->jsonResponse($result['success'], [
                        'messages' => $this->messages,
                        'values' => $this->postedValues ?? $this->settingsToViewValues($this->loadSettings($context))
                ]);
        }

        protected function jsonResponse(bool $success, array $data = []): string {
                $json = json_encode(array_merge([
                        'status' => $success ? 'ok' : 'error',
                        'success' => $success
                ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return is_string($json) ? $json : '{"status":"error","success":false}';
        }

        protected function jsonError(string $message): string {
                $this->messages = [[
                        'type' => 'danger',
                        'text' => $message
                ]];

                return $this->jsonResponse(false, [
                        'messages' => $this->messages
                ]);
        }

        // ---------------------------------------------------------------------
        // Context
        // ---------------------------------------------------------------------

        protected function getContext(bool $allowRequestContext): array {
                $group = self::DEFAULT_GROUP;
                $name = trim((string)($this->data['name'] ?? ''));

                if ($allowRequestContext && $name === '') {
                        $name = trim((string)$this->request->request('agent_config_name', ''));
                }

                if ($name === '') {
                        $name = 'default';
                }

                $title = trim((string)($this->data['title'] ?? 'Agent Configuration'));
                if ($title === '') {
                        $title = 'Agent Configuration';
                }

                $description = trim((string)($this->data['description'] ?? 'Configure the selected agent instance.'));
                $submitLabel = trim((string)($this->data['submit_label'] ?? 'Save'));

                if ($submitLabel === '') {
                        $submitLabel = 'Save';
                }

                $mode = $this->normalizeEnum(
                        (string)($this->data['mode'] ?? 'standalone'),
                        ['standalone', 'embedded'],
                        'standalone'
                );

                $saveMode = $this->normalizeEnum(
                        (string)($this->data['save_mode'] ?? 'ajax'),
                        ['ajax', 'post'],
                        'ajax'
                );

                $renderForm = $mode !== 'embedded';

                if (array_key_exists('render_form', $this->data)) {
                        $renderForm = $this->toBool($this->data['render_form']);
                }

                return [
                        'group' => $group,
                        'name' => $name,
                        'title' => $title,
                        'description' => $description,
                        'submit_label' => $submitLabel,
                        'mode' => $mode,
                        'save_mode' => $saveMode,
                        'render_form' => $renderForm,
                        'form_id' => 'base3_agent_config_' . md5($group . '/' . $name),
                        'form_action' => (string)($this->data['form_action'] ?? ($_SERVER['REQUEST_URI'] ?? '')),
                        'save_url' => $this->getSaveUrl()
                ];
        }

        protected function getSaveUrl(): string {
                $saveUrl = trim((string)($this->data['save_url'] ?? ''));

                if ($saveUrl !== '') {
                        return $saveUrl;
                }

                return $this->linkTargetService->getLink(
                        [
                                'name' => self::getName(),
                                'out' => 'json'
                        ],
                        [
                                'action' => self::FORM_ACTION_SAVE
                        ]
                );
        }

        // ---------------------------------------------------------------------
        // Save handling
        // ---------------------------------------------------------------------

        protected function isSaveRequest(array $context): bool {
                if ((string)$this->request->request('agent_config_action') !== self::FORM_ACTION_SAVE) {
                        return false;
                }

                $postedGroup = trim((string)$this->request->request('agent_config_group'));
                $postedName = trim((string)$this->request->request('agent_config_name'));

                return $postedGroup === self::DEFAULT_GROUP && $postedGroup === $context['group'] && $postedName === $context['name'];
        }

        protected function handleSave(array $context): void {
                $this->saveSettingsFromRequest($context);
        }

        protected function saveSettingsFromRequest(array $context): array {
                $errors = [];
                $settings = $this->getPostedSettings($errors);
                $this->postedValues = $this->getPostedViewValues();

                if ($errors !== []) {
                        foreach ($errors as $error) {
                                $this->addMessage('danger', $error);
                        }

                        return [
                                'success' => false
                        ];
                }

                try {
                        $this->settingsStore->set($context['group'], $context['name'], $settings);
                        $this->settingsStore->save();

                        $this->postedValues = $this->settingsToViewValues($settings);
                        $this->addMessage('success', 'Settings saved.');

                        return [
                                'success' => true
                        ];
                }
                catch (Throwable $e) {
                        $this->addMessage('danger', 'Settings could not be saved: ' . $e->getMessage());

                        return [
                                'success' => false
                        ];
                }
        }

        protected function getPostedSettings(array &$errors): array {
                $policyId = $this->normalizeTechnicalKey((string)$this->request->request('policy'));
                $policyData = $this->normalizePostedPolicyData($policyId, $errors);
                $agentSettings = $this->agentConfigFormService->getPostedSettings($errors);

                if ($policyId === '') {
                        $errors[] = 'Please select a timing policy.';
                }
                elseif (!$this->policyExists($policyId)) {
                        $errors[] = 'Selected timing policy does not exist: ' . $policyId;
                }

                return $this->normalizeSettings(array_merge([
                        'enabled' => $this->request->request('enabled') !== null,
                        'label' => $this->normalizeLabel((string)$this->request->request('label')),
                        'user_prompt' => $this->normalizeTextBlock((string)$this->request->request('user_prompt')),
                        'policy' => [
                                'policy' => $policyId,
                                'data' => $policyData
                        ]
                ], $agentSettings));
        }

        protected function getPostedViewValues(): array {
                $policyId = $this->normalizeTechnicalKey((string)$this->request->request('policy'));
                $errors = [];

                return array_merge([
                        'enabled' => $this->request->request('enabled') !== null,
                        'label' => $this->normalizeLabel((string)$this->request->request('label')),
                        'user_prompt' => $this->normalizeTextBlock((string)$this->request->request('user_prompt')),
                        'policy_policy' => $policyId,
                        'policy_data' => $this->normalizePostedPolicyData($policyId, $errors)
                ], $this->agentConfigFormService->getPostedViewValues());
        }

        protected function addMessage(string $type, string $text): void {
                $this->messages[] = [
                        'type' => $type,
                        'text' => $text
                ];
        }

        // ---------------------------------------------------------------------
        // Policy options
        // ---------------------------------------------------------------------

        /**
         * @return array<int,array<string,mixed>>
         */
        protected function listPolicyOptions(): array {
                if ($this->policyOptions !== null) {
                        return $this->policyOptions;
                }

                $rows = [];

                try {
                        $policies = $this->classMap->getInstancesByInterface(IJobExecutionPolicy::class);
                }
                catch (Throwable $e) {
                        $this->addMessage('danger', 'Timing policies could not be loaded: ' . $e->getMessage());
                        $this->policyOptions = [];

                        return [];
                }

                foreach ($policies as $policy) {
                        if (!$policy instanceof IJobExecutionPolicy) {
                                continue;
                        }

                        $class = $policy::class;
                        $id = $this->normalizeTechnicalKey((string)$class::getName());

                        if ($id === '') {
                                continue;
                        }

                        $schema = $policy->getSchema();

                        if (!is_array($schema)) {
                                $schema = [];
                        }

                        $rows[$id] = [
                                'id' => $id,
                                'label' => $this->policyLabelFromClass($class, $id),
                                'class' => $class,
                                'schema' => $schema
                        ];
                }

                $rows = array_values($rows);

                usort($rows, static function(array $a, array $b): int {
                        return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
                });

                $this->policyOptions = $rows;

                return $rows;
        }

        protected function policyLabelFromClass(string $class, string $fallback): string {
                $parts = explode('\\', $class);
                $short = end($parts);

                if (!is_string($short) || $short === '') {
                        return $fallback;
                }

                $label = preg_replace('/(?<!^)[A-Z]/', ' $0', $short) ?? $short;
                $label = trim(str_replace(' Job Policy', '', $label));

                return $label !== '' ? $label : $fallback;
        }

        protected function policyExists(string $id): bool {
                if ($id === '') {
                        return false;
                }

                foreach ($this->listPolicyOptions() as $option) {
                        if ((string)($option['id'] ?? '') === $id) {
                                return true;
                        }
                }

                return false;
        }

        protected function getPolicySchema(string $id): array {
                foreach ($this->listPolicyOptions() as $option) {
                        if ((string)($option['id'] ?? '') === $id) {
                                $schema = $option['schema'] ?? [];

                                return is_array($schema) ? $schema : [];
                        }
                }

                return [];
        }

        protected function getDefaultPolicyId(): string {
                $options = $this->listPolicyOptions();

                foreach (['manualonlyjobpolicy', 'dailyaftertimejobpolicy', 'dailywindowjobpolicy', 'cronjobpolicy', 'intervaljobpolicy'] as $preferred) {
                        foreach ($options as $option) {
                                if ((string)($option['id'] ?? '') === $preferred) {
                                        return $preferred;
                                }
                        }
                }

                $first = $options[0]['id'] ?? '';

                return is_string($first) ? $first : '';
        }

        /**
         * @return array<string,mixed>
         */
        protected function getPolicySchemaProperties(array $schema): array {
                if(is_array($schema['properties'] ?? null)) {
                        return $schema['properties'];
                }

                if(is_array($schema['fields'] ?? null)) {
                        return $schema['fields'];
                }

                $data = is_array($schema['data'] ?? null) ? $schema['data'] : [];

                if(is_array($data['properties'] ?? null)) {
                        return $data['properties'];
                }

                return [];
        }

        /**
         * @return array<int,string>
         */
        protected function getPolicySchemaRequired(array $schema): array {
                if(is_array($schema['required'] ?? null)) {
                        return array_map('strval', $schema['required']);
                }

                $data = is_array($schema['data'] ?? null) ? $schema['data'] : [];

                if(is_array($data['required'] ?? null)) {
                        return array_map('strval', $data['required']);
                }

                return [];
        }

        protected function normalizePostedPolicyData(string $policyId, array &$errors): array {
                $raw = [];
                $jsonRaw = $this->decodePostedBase64Field('policy_data_b64', 'Timing policy data', $errors);

                if ($jsonRaw === '') {
                        $jsonRaw = trim((string)$this->request->request('policy_data_json', ''));
                }

                if ($jsonRaw !== '') {
                        try {
                                $decoded = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);

                                if (is_array($decoded)) {
                                        $raw = $decoded;
                                }
                        }
                        catch (JsonException $e) {
                                $errors[] = 'Timing policy data must be valid JSON: ' . $e->getMessage();
                        }
                }

                if ($raw === []) {
                        $raw = $this->request->request('policy_data', []);

                        if (!is_array($raw)) {
                                $raw = [];
                        }
                }

                $schema = $this->getPolicySchema($policyId);
                $properties = $this->getPolicySchemaProperties($schema);
                $required = $this->getPolicySchemaRequired($schema);
                $result = [];

                foreach ($properties as $key => $property) {
                        if (!is_string($key) || !is_array($property)) {
                                continue;
                        }

                        $type = (string)($property['type'] ?? 'string');
                        $value = $raw[$key] ?? null;
                        $isRequired = in_array($key, $required, true);

                        if (($value === null || $value === '') && !$isRequired) {
                                continue;
                        }

                        if (($value === null || $value === '') && $isRequired) {
                                $errors[] = 'Timing policy field "' . $key . '" is required.';
                                continue;
                        }

                        if ($type === 'integer') {
                                if (!is_numeric($value)) {
                                        $errors[] = 'Timing policy field "' . $key . '" must be numeric.';
                                        continue;
                                }

                                $result[$key] = (int)$value;
                                continue;
                        }

                        if ($type === 'number') {
                                if (!is_numeric($value)) {
                                        $errors[] = 'Timing policy field "' . $key . '" must be numeric.';
                                        continue;
                                }

                                $result[$key] = (float)$value;
                                continue;
                        }

                        if ($type === 'boolean') {
                                $result[$key] = $this->toBool($value);
                                continue;
                        }

                        if ($type === 'object' || $type === 'array') {
                                $decoded = $this->decodePolicyJsonField((string)$value, $key, $errors);

                                if ($decoded !== null) {
                                        $result[$key] = $decoded;
                                }

                                continue;
                        }

                        $value = trim((string)$value);
                        $enum = is_array($property['enum'] ?? null) ? array_map('strval', $property['enum']) : [];

                        if ($enum !== [] && !in_array($value, $enum, true)) {
                                $errors[] = 'Timing policy field "' . $key . '" has an invalid value.';
                                continue;
                        }

                        $result[$key] = $value;
                }

                return $result;
        }

        protected function decodePostedBase64Field(string $field, string $label, array &$errors): string {
                $raw = trim((string)$this->request->request($field, ''));

                if ($raw === '') {
                        return '';
                }

                $decoded = base64_decode($raw, true);

                if (!is_string($decoded)) {
                        $errors[] = $label . ' could not be decoded from base64.';

                        return '';
                }

                return trim($decoded);
        }

        protected function decodePolicyJsonField(string $raw, string $key, array &$errors): mixed {
                $raw = trim($raw);

                if ($raw === '') {
                        return [];
                }

                try {
                        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                }
                catch (JsonException $e) {
                        $errors[] = 'Timing policy field "' . $key . '" must be valid JSON: ' . $e->getMessage();
                        return null;
                }

                return $decoded;
        }

        // ---------------------------------------------------------------------
        // Settings
        // ---------------------------------------------------------------------

        protected function loadSettings(array $context): array {
                try {
                        return $this->normalizeSettings(
                                $this->settingsStore->get($context['group'], $context['name'], $this->getDefaultSettings())
                        );
                }
                catch (Throwable $e) {
                        $this->addMessage('danger', 'Settings could not be loaded: ' . $e->getMessage());
                        return $this->getDefaultSettings();
                }
        }

        protected function getDefaultSettings(): array {
                return array_merge([
                        'enabled' => true,
                        'label' => '',
                        'user_prompt' => '',
                        'policy' => [
                                'policy' => $this->getDefaultPolicyId(),
                                'data' => []
                        ]
                ], $this->agentConfigFormService->getDefaultSettings());
        }

        protected function normalizeSettings(array $settings): array {
                $defaults = $this->getDefaultSettings();
                $policy = is_array($settings['policy'] ?? null) ? $settings['policy'] : $defaults['policy'];
                $policyId = $this->normalizeTechnicalKey((string)($policy['policy'] ?? ''));

                if ($policyId === '') {
                        $policyId = $this->getDefaultPolicyId();
                }

                $policyData = is_array($policy['data'] ?? null) ? $policy['data'] : [];

                return array_merge([
                        'enabled' => $this->toBool($settings['enabled'] ?? $defaults['enabled']),
                        'label' => $this->normalizeLabel((string)($settings['label'] ?? $defaults['label'])),
                        'user_prompt' => $this->normalizeTextBlock((string)($settings['user_prompt'] ?? $defaults['user_prompt'])),
                        'policy' => [
                                'policy' => $policyId,
                                'data' => $policyData
                        ]
                ], $this->agentConfigFormService->normalizeSettings($settings));
        }

        protected function settingsToViewValues(array $settings): array {
                $settings = $this->normalizeSettings($settings);
                $policy = is_array($settings['policy'] ?? null) ? $settings['policy'] : [];

                return array_merge([
                        'enabled' => $settings['enabled'],
                        'label' => $settings['label'],
                        'user_prompt' => $settings['user_prompt'],
                        'policy_policy' => (string)($policy['policy'] ?? ''),
                        'policy_data' => is_array($policy['data'] ?? null) ? $policy['data'] : []
                ], $this->agentConfigFormService->settingsToViewValues($settings));
        }

        protected function normalizeEnum(string $value, array $allowed, string $default): string {
                return in_array($value, $allowed, true) ? $value : $default;
        }

        protected function normalizeTextBlock(string $value): string {
                return str_replace(["\r\n", "\r"], "\n", $value);
        }

        protected function normalizeLabel(string $value): string {
                return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        }

        protected function normalizeTechnicalKey(string $value): string {
                $value = strtolower(trim($value));

                return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
        }

        protected function toBool(mixed $value): bool {
                if (is_bool($value)) {
                        return $value;
                }

                if (is_int($value)) {
                        return $value === 1;
                }

                $value = strtolower(trim((string)$value));

                return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }
}
