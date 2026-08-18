<?php
$this->loadBricks('Administration');
$mbUiText = is_array($this->_['bricks']['missionbay_admin'] ?? null) ? $this->_['bricks']['missionbay_admin'] : [];
$mbText = static fn(string $key, string $fallback): string => trim((string)($mbUiText[$key] ?? '')) !== '' ? (string)$mbUiText[$key] : $fallback;
$mbTextEsc = static fn(string $key, string $fallback): string => htmlspecialchars($mbText($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php
$resolve = $this->_['resolve'];

$serviceUrl = (string) ($this->_['service'] ?? '');
$resourceOptions = is_array($this->_['resource_options'] ?? null) ? $this->_['resource_options'] : [];
$presetOptions = is_array($this->_['preset_options'] ?? null) ? $this->_['preset_options'] : [];
$openPresetId = (string)($this->_['open_preset_id'] ?? '');
$secretValueMarker = (string)($this->_['secret_value_marker'] ?? '__missionbay_secret_configured__');
$categoryOptions = ['context', 'web', 'ai', 'memory', 'tool', 'storage', 'integration', 'system', 'experimental'];
$statusOptions = ['draft', 'ready', 'disabled', 'deprecated'];
$riskOptions = ['none', 'read_external_url', 'reads_context', 'writes_memory', 'writes_settings', 'external_api', 'destructive', 'experimental'];
$capabilityOptions = ['memory', 'context', 'tool', 'chatmodel'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$modularDialogCssUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/styles/modulardialog.css');
$modularDialogJsUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/index.js');
$timestamp = date('c');
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?php echo $e($modularGridCssUrl); ?>" />
<link rel="stylesheet" href="<?php echo $e($modularDialogCssUrl); ?>" />

<style>
	.agent-component-preset-step5-shell {
		max-width: 1700px;
	}

	.agent-component-preset-step5-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.agent-component-preset-step5-shell p {
		margin: 0 0 16px 0;
		max-width: 1120px;
		color: #555;
		line-height: 1.45;
	}

	.agent-component-preset-step5-grid .agent-component-preset-step5-panel {
		display: flex;
		align-items: center;
		flex-wrap: nowrap;
		gap: 8px;
		min-width: 0;
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		overflow-x: auto;
	}

	.agent-component-preset-step5-grid .agent-component-preset-step5-panel--filters {
		flex-wrap: wrap;
		align-items: flex-start;
		overflow-x: visible;
	}

	.agent-component-preset-step5-grid .agent-component-preset-step5-panel > * {
		flex: 0 0 auto;
	}

	.agent-component-preset-step5-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.agent-component-preset-step5-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.agent-component-preset-step5-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.agent-component-preset-step5-grid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.agent-component-preset-step5-grid .mg-input,
	.agent-component-preset-step5-grid .mg-select,
	.agent-component-preset-step5-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.agent-component-preset-step5-grid input[type="search"].mg-input {
		width: 300px;
	}

	.agent-component-preset-step5-grid .mg-select {
		width: auto;
		min-width: 96px;
	}

	.agent-component-preset-step5-grid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.agent-component-preset-step5-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.agent-component-preset-step5-grid .mg-table thead th.mg-cell-pinned {
		z-index: 14;
	}

	.agent-component-preset-step5-grid .mg-table th,
	.agent-component-preset-step5-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.agent-component-preset-step5-grid .mg-row-actions-cell,
	.agent-component-preset-step5-grid .mg-row-actions-header {
		width: 54px;
		min-width: 54px;
		text-align: center;
	}

	.agent-component-preset-step5-top-actions {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		flex: 0 0 auto;
	}

	.agent-component-preset-step5-detail-actions {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 8px;
	}

	.agent-component-preset-step5-cell-stack {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.agent-component-preset-step5-cell-main {
		font-weight: 600;
		color: #222;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.agent-component-preset-step5-cell-sub {
		font-size: 12px;
		color: #666;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.agent-component-preset-step5-pill-row {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		align-items: center;
	}

	.agent-component-preset-step5-pill {
		display: inline-flex;
		align-items: center;
		padding: 1px 6px;
		border: 1px solid #d6d6d6;
		border-radius: 999px;
		background: #fafafa;
		font-size: 11px;
		line-height: 1.35;
		color: #444;
		white-space: nowrap;
	}

	.agent-component-preset-step5-output,
	.agent-component-preset-step5-status {
		margin-top: 12px;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.agent-component-preset-step5-output strong,
	.agent-component-preset-step5-status strong {
		color: #222;
	}

	.agent-component-preset-step5-startup {
		padding: 16px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.agent-component-preset-step5-startup-error {
		border-color: #e4b9b9;
		background: #fff8f8;
		color: #8a1f1f;
	}

	.agent-component-preset-step5-startup pre {
		white-space: pre-wrap;
		word-break: break-word;
		margin: 8px 0 0 0;
		font-size: 12px;
	}

	.agent-component-preset-step5-detail {
		min-width: 0;
	}

	.agent-component-preset-step5-detail-layout,
	.agent-component-preset-step5-detail {
		display: grid;
		grid-template-columns: minmax(320px, 1fr) minmax(360px, 1.15fr);
		gap: 14px;
		align-items: start;
		padding: 10px;
		background: #fbfbfb;
	}

	.agent-component-preset-step5-detail-card {
		min-width: 0;
		padding: 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
	}

	.agent-component-preset-step5-detail-title {
		margin: 0 0 6px 0;
		font-size: 15px;
		font-weight: 600;
		color: #222;
	}

	.agent-component-preset-step5-detail-row {
		display: grid;
		grid-template-columns: 120px minmax(0, 1fr);
		gap: 6px;
		margin: 0 0 6px 0;
		font-size: 13px;
	}

	.agent-component-preset-step5-detail-key {
		font-weight: 600;
		color: #444;
	}

	.agent-component-preset-step5-json,
	.agent-component-preset-step5-log {
		margin: 0;
		padding: 8px;
		max-height: 360px;
		overflow: auto;
		border: 1px solid #e2e2e2;
		border-radius: 6px;
		background: #fbfbfb;
		white-space: pre-wrap;
		word-break: break-word;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.4;
	}

	.agent-component-preset-step5-log {
		max-height: 260px;
	}

	.agent-component-preset-step5-log-details {
		margin-top: 10px;
	}

	.agent-component-preset-step5-log-details summary {
		padding: 7px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		cursor: pointer;
		font-size: 13px;
		color: #444;
	}

	.agent-component-preset-step5-button {
		appearance: none;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 28px;
		padding: 4px 10px;
		border: 1px solid #cfcfcf;
		border-radius: 6px;
		background: #fff;
		color: #222;
		font: inherit;
		font-size: 12px;
		line-height: 1.25;
		cursor: pointer;
		white-space: nowrap;
	}

	.agent-component-preset-step5-button:hover {
		background: #f5f5f5;
	}

	.agent-component-preset-step5-button:focus-visible {
		outline: 2px solid #86a8cf;
		outline-offset: 2px;
	}

	.agent-component-preset-step5-button-primary {
		background: #2f5d91;
		border-color: #2f5d91;
		color: #fff;
	}

	.agent-component-preset-step5-button-primary:hover {
		background: #284f7c;
	}

	.agent-component-preset-step5-button-danger {
		border-color: #c8a2a2;
		color: #8a1f1f;
	}

	.agent-component-preset-step5-button-danger:hover {
		background: #fff0f0;
	}

	.agent-component-preset-step5-dialog-surface {
		width: min(1120px, 100%);
		max-height: min(860px, 100%);
	}

	.agent-component-preset-step5-dialog-surface .md-shell-body {
		min-height: 0;
		overflow: auto;
	}

	.agent-component-preset-step5-editor-content {
		min-width: 0;
	}

	.agent-component-preset-step5-form {
		display: grid;
		grid-template-columns: repeat(2, minmax(260px, 1fr));
		gap: 12px;
	}

	.agent-component-preset-step5-field-full {
		grid-column: 1 / -1;
	}

	.agent-component-preset-step5-label {
		display: block;
		margin: 0 0 4px 0;
		font-size: 12px;
		font-weight: 600;
		color: #333;
	}

	.agent-component-preset-step5-input,
	.agent-component-preset-step5-select,
	.agent-component-preset-step5-textarea {
		width: 100%;
		max-width: 100%;
		min-height: 32px;
		padding: 5px 7px;
		border: 1px solid #cfcfcf;
		border-radius: 6px;
		background: #fff;
		color: #222;
		font: inherit;
		font-size: 13px;
		box-sizing: border-box;
	}

	.agent-component-preset-step5-select[multiple] {
		min-height: 72px;
	}

	.agent-component-preset-step5-textarea {
		min-height: 110px;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.4;
		resize: vertical;
	}

	.agent-component-preset-step5-checkbox-row {
		display: flex;
		align-items: center;
		gap: 10px;
		min-height: 32px;
		font-size: 13px;
	}

	.agent-component-preset-step5-resource-info {
		margin-top: 6px;
		padding: 7px 8px;
		border: 1px solid #e2e2e2;
		border-radius: 6px;
		background: #fbfbfb;
		font-size: 12px;
		color: #555;
		line-height: 1.4;
	}

	.agent-component-preset-step5-resource-info code {
		color: #222;
		background: transparent;
		font-size: 12px;
	}


	.agent-component-preset-step5-definition-fields {
		display: grid;
		gap: 10px;
	}

	.agent-component-preset-step5-definition-empty,
	.agent-component-preset-step5-definition-help {
		color: #666;
		font-size: 12px;
		line-height: 1.4;
	}

	.agent-component-preset-step5-definition-row {
		display: grid;
		grid-template-columns: minmax(180px, 260px) minmax(0, 1fr);
		gap: 8px 12px;
		align-items: start;
		padding: 8px;
		border: 1px solid #e2e2e2;
		border-radius: 6px;
		background: #fbfbfb;
	}

	.agent-component-preset-step5-definition-row-required .agent-component-preset-step5-definition-label::after {
		content: " *";
		color: #8a1f1f;
	}

	.agent-component-preset-step5-definition-label {
		font-size: 12px;
		font-weight: 600;
		color: #333;
		overflow-wrap: anywhere;
	}

	.agent-component-preset-step5-definition-control {
		display: grid;
		gap: 4px;
		min-width: 0;
	}

	.agent-component-preset-step5-definition-hint {
		color: #666;
		font-size: 11px;
		line-height: 1.35;
		overflow-wrap: anywhere;
	}

	.agent-component-preset-step5-hidden-contract-field {
		display: none !important;
	}

	@media (max-width: 980px) {
		.agent-component-preset-step5-detail,
		.agent-component-preset-step5-form,
		.agent-component-preset-step5-definition-row {
			grid-template-columns: 1fr;
		}
	}

	@media (max-width: 720px) {
		.agent-component-preset-step5-shell h1 {
			font-size: 21px;
		}

		.agent-component-preset-step5-grid .mg-table-scroll {
			height: 420px;
		}
	}
</style>

<div class="agent-component-preset-step5-shell">
	<h1><?php echo $mbTextEsc('agent_component_preset_admin', 'Agent Component Preset Admin'); ?></h1>
	<p>
		Manage reusable presets for dockable MissionBay agent components. Resource types and meta fields are edited through controlled fields. Capabilities are derived from the selected resource implementation. Resource configuration and docks are generated from resource schemas and dock definitions.
	</p>

	<div class="agent-component-preset-step5-grid">
		<div id="agent-component-preset-step5-grid" class="agent-component-preset-step5-grid-shell">
			<div class="agent-component-preset-step5-startup"><?php echo $mbTextEsc('loading_agent_component_preset_admin_display', 'Loading Agent Component Preset Admin display...'); ?></div>
		</div>
		<div id="agent-component-preset-step5-output" class="agent-component-preset-step5-status"><strong><?php echo $mbTextEsc('last_action', 'Last action:'); ?></strong> <?php echo $mbTextEsc('waiting_for_initialization', 'Waiting for initialization.'); ?></div>
		<details class="agent-component-preset-step5-log-details">
			<summary><?php echo $mbTextEsc('debug_log', 'Debug log'); ?></summary>
			<pre id="agent-component-preset-step5-log" class="agent-component-preset-step5-log"><?php echo $mbTextEsc('status_log_will_appear_here', 'Status log will appear here.'); ?></pre>
		</details>
	</div>
</div>

<template id="agent-component-preset-step5-editor-template">
	<div id="agent-component-preset-step5-editor-content" class="agent-component-preset-step5-editor-content">
		<form id="agent-component-preset-step5-form" class="agent-component-preset-step5-form">
			<input type="hidden" name="old_id" />

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('preset_id', 'Preset ID'); ?></label>
			<input type="text" name="id" class="agent-component-preset-step5-input" />
			</div>

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('label', 'Label'); ?></label>
			<input type="text" name="label" class="agent-component-preset-step5-input" />
			</div>

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('resource_type', 'Resource type'); ?></label>
			<select name="type" class="agent-component-preset-step5-select">
				<option value=""><?php echo $mbTextEsc('select_resource_type', 'Select resource type'); ?></option>
<?php foreach($resourceOptions as $resourceOption): ?>
<?php
	$resourceId = is_array($resourceOption) ? (string)($resourceOption['id'] ?? '') : (string)$resourceOption;
	$resourceClass = is_array($resourceOption) ? (string)($resourceOption['class'] ?? '') : '';
	if($resourceId === '') {
		continue;
	}
?>
				<option value="<?php echo $e($resourceId); ?>" title="<?php echo $e($resourceClass); ?>"><?php echo $e($resourceId); ?></option>
<?php endforeach; ?>
			</select>
			<div id="agent-component-preset-step5-resource-info" class="agent-component-preset-step5-resource-info"><?php echo $mbTextEsc('no_resource_type_selected', 'No resource type selected.'); ?></div>
			</div>

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('enabled', 'Enabled'); ?></label>
			<label class="agent-component-preset-step5-checkbox-row"><input type="checkbox" name="enabled" value="1" /> <?php echo $mbTextEsc('enabled', 'Enabled'); ?></label>
			</div>

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('capabilities', 'Capabilities'); ?></label>
			<div id="agent-component-preset-step5-capability-info" class="agent-component-preset-step5-resource-info"><?php echo $mbTextEsc('no_resource_type_selected', 'No resource type selected.'); ?></div>
			</div>

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('category', 'Category'); ?></label>
			<select name="category" class="agent-component-preset-step5-select">
				<option value=""><?php echo $mbTextEsc('select_category', 'Select category'); ?></option>
<?php foreach($categoryOptions as $categoryOption): ?>
				<option value="<?php echo $e($categoryOption); ?>"><?php echo $e($categoryOption); ?></option>
<?php endforeach; ?>
			</select>
			</div>

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('status', 'Status'); ?></label>
			<select name="status" class="agent-component-preset-step5-select">
				<option value=""><?php echo $mbTextEsc('select_status', 'Select status'); ?></option>
<?php foreach($statusOptions as $statusOption): ?>
				<option value="<?php echo $e($statusOption); ?>"><?php echo $e($statusOption); ?></option>
<?php endforeach; ?>
			</select>
			</div>

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('risk', 'Risk'); ?></label>
			<select name="risk" class="agent-component-preset-step5-select">
				<option value=""><?php echo $mbTextEsc('select_risk', 'Select risk'); ?></option>
<?php foreach($riskOptions as $riskOption): ?>
				<option value="<?php echo $e($riskOption); ?>"><?php echo $e($riskOption); ?></option>
<?php endforeach; ?>
			</select>
			</div>

			<div>
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('version', 'Version'); ?></label>
			<input type="text" name="version" class="agent-component-preset-step5-input" />
			</div>

			<div class="agent-component-preset-step5-field-full">
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('description', 'Description'); ?></label>
			<textarea name="description" class="agent-component-preset-step5-textarea"></textarea>
			</div>

			<div class="agent-component-preset-step5-field-full">
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('configuration', 'Configuration'); ?></label>
			<div id="agent-component-preset-step5-config-fields" class="agent-component-preset-step5-definition-fields"></div>
			<textarea name="config_json" class="agent-component-preset-step5-hidden-contract-field" hidden></textarea>
			</div>

			<div class="agent-component-preset-step5-field-full">
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('docks', 'Docks'); ?></label>
			<div id="agent-component-preset-step5-dock-fields" class="agent-component-preset-step5-definition-fields"></div>
			<textarea name="docks_json" class="agent-component-preset-step5-hidden-contract-field" hidden></textarea>
			</div>

			<div class="agent-component-preset-step5-field-full">
			<label class="agent-component-preset-step5-label"><?php echo $mbTextEsc('meta_json', 'Meta JSON'); ?></label>
			<textarea name="meta_json" class="agent-component-preset-step5-textarea"></textarea>
			</div>
		</form>
	</div>
</template>


<script>
	(function() {
	const MB_UI_TEXT = <?php echo json_encode($mbUiText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const mbText = (key, fallback, replacements = {}) => {
		let value = String(MB_UI_TEXT[key] || fallback || '');
		Object.entries(replacements).forEach(([name, replacement]) => {
			value = value.split('{' + name + '}').join(String(replacement));
		});
		return value;
	};
	const mbStringSet = (prefix) => Object.fromEntries(
		Object.entries(MB_UI_TEXT)
			.filter(([key, value]) => key.startsWith(prefix) && String(value || '').trim() !== '')
			.map(([key, value]) => [key.slice(prefix.length), value])
	);

console.log('[AgentComponentPresetAdmin] script entered');

const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const MODULARGRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const MODULARDIALOG_URL = <?php echo json_encode($modularDialogJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const RESOURCE_OPTIONS = <?php echo json_encode($resourceOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const SECRET_VALUE_MARKER = <?php echo json_encode($secretValueMarker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const PRESET_OPTIONS = <?php echo json_encode($presetOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const OPEN_PRESET_ID = <?php echo json_encode($openPresetId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const RESOURCE_TYPE_FILTER_OPTIONS = [
	{ value: '', label: mbText('all_resource_types', 'All resource types') },
	...RESOURCE_OPTIONS
		.map((entry) => ({ value: String(entry && entry.id ? entry.id : ''), label: String(entry && entry.id ? entry.id : '') }))
		.filter((entry) => entry.value !== '')
];
const CATEGORY_FILTER_OPTIONS = [
	{ value: '', label: mbText('all_categories', 'All categories') },
	...<?php echo json_encode($categoryOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.map((value) => ({ value, label: value }))
];
const STATUS_FILTER_OPTIONS = [
	{ value: '', label: mbText('all_statuses', 'All statuses') },
	...<?php echo json_encode($statusOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.map((value) => ({ value, label: value }))
];
const CAPABILITY_FILTER_OPTIONS = [
	{ value: '', label: mbText('all_capabilities', 'All capabilities') },
	...<?php echo json_encode($capabilityOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.map((value) => ({ value, label: value }))
];
const ENABLED_FILTER_OPTIONS = [
	{ value: '', label: mbText('all_states', 'All states') },
	{ value: '1', label: mbText('enabled', 'Enabled') },
	{ value: '0', label: mbText('disabled', 'Disabled') }
];
const GRID_SELECTOR = '#agent-component-preset-step5-grid';
const LOG_SELECTOR = '#agent-component-preset-step5-log';
const OUTPUT_SELECTOR = '#agent-component-preset-step5-output';
const BATCH_SIZE = 50;
const SORT_TYPES = {
	preset_id: 'string',
	label: 'string',
	type: 'string',
	capability_text: 'string',
	interface_text: 'string',
	status: 'string',
	category: 'string'
};

let grid = null;
let editorDialog = null;
let editorContent = null;
let currentEditorPresetId = '';

const layout = {
	type: 'stack',
	className: 'mg-layout-root',
	children: [
		{
			type: 'zone',
			key: 'topLine',
			className: 'agent-component-preset-step5-panel agent-component-preset-step5-panel--main'
		},
		{
			type: 'zone',
			key: 'topLine2',
			className: 'agent-component-preset-step5-panel agent-component-preset-step5-panel--filters'
		},
		{
			type: 'view',
			key: 'main',
			className: 'agent-component-preset-step5-main'
		},
		{
			type: 'zone',
			key: 'statusZone',
			className: 'agent-component-preset-step5-panel'
		}
	]
};

function log(label, value = undefined) {
	const message = value === undefined ? String(label) : String(label) + ' ' + stringifyJson(value);
	const logElement = document.querySelector(LOG_SELECTOR);

	console.log('[AgentComponentPresetAdminStep5]', label, value === undefined ? '' : value);

	if (logElement) {
		logElement.textContent = (logElement.textContent === mbText('status_log_will_appear_here', 'Status log will appear here.') ? '' : logElement.textContent + '\n') + message;
	}
}

function setLog(message) {
	const output = document.querySelector(OUTPUT_SELECTOR);

	if (!output) {
		return;
	}

	output.innerHTML = '';
	const label = document.createElement('strong');
	label.textContent = mbText('last_action', 'Last action:');
	output.appendChild(label);
	output.appendChild(document.createTextNode(' ' + message));
}

function stringifyJson(value) {
	try {
		return JSON.stringify(value, null, 2);
	} catch (error) {
		return String(value);
	}
}

async function copyText(text) {
	if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
		await navigator.clipboard.writeText(String(text || ''));
		return;
	}

	const textarea = document.createElement('textarea');
	textarea.value = String(text || '');
	textarea.setAttribute('readonly', 'readonly');
	textarea.style.position = 'fixed';
	textarea.style.left = '-9999px';
	document.body.appendChild(textarea);
	textarea.select();
	document.execCommand('copy');
	textarea.remove();
}

function createElement(className = '', text = '') {
	const element = document.createElement('div');

	if (className) {
		element.className = className;
	}

	if (text !== '') {
		element.textContent = text;
	}

	return element;
}

function getText(value, placeholder = '-') {
	if (value === null || value === undefined || value === '') {
		return placeholder;
	}

	return String(value);
}

function buildFilterPayload(filters) {
	const result = {};

	Object.entries(filters || {}).forEach(([key, value]) => {
		if (value === '' || value === null || value === undefined) {
			return;
		}

		result[key] = value;
	});

	return result;
}

function getResourceOption(type) {
	const key = String(type || '').trim();

	return (Array.isArray(RESOURCE_OPTIONS) ? RESOURCE_OPTIONS : []).find((item) => item && String(item.id || '') === key) || null;
}

function getCurrentResource(form) {
	return getResourceOption(getFormFieldValue(form, 'type'));
}

function getDerivedCapabilitiesForType(type) {
	const resource = getResourceOption(type);
	const capabilities = resource && Array.isArray(resource.capabilities) ? resource.capabilities : [];

	return capabilities.map((item) => String(item || '').trim()).filter(Boolean);
}

function getSelectedCapabilities(form) {
	return getDerivedCapabilitiesForType(getFormFieldValue(form, 'type'));
}

function renderCapabilityInfo(form) {
	const info = document.getElementById('agent-component-preset-step5-capability-info');
	const capabilities = getSelectedCapabilities(form);

	if (!info) {
		return;
	}

	info.textContent = capabilities.length > 0
		? 'Derived from resource implementation: ' + capabilities.join(', ')
		: 'No memory/tool capability detected for this resource type.';
}

function renderResourceInfo(form) {
	const info = document.getElementById('agent-component-preset-step5-resource-info');

	if (!info) {
		return;
	}

	const resource = getCurrentResource(form);

	if (!resource) {
		info.textContent = mbText('no_resource_type_selected', 'No resource type selected.');
		renderCapabilityInfo(form);
		return;
	}

	const dockCount = Array.isArray(resource.docks) ? resource.docks.length : 0;
	const schemaProperties = resource.schema && resource.schema.properties && typeof resource.schema.properties === 'object'
		? Object.keys(resource.schema.properties).length
		: 0;
	const capabilities = Array.isArray(resource.capabilities) ? resource.capabilities : [];

	info.innerHTML = '';
	info.appendChild(document.createTextNode(mbText('class_prefix', 'Class: ')));

	const classCode = document.createElement('code');
	classCode.textContent = getText(resource.class);
	info.appendChild(classCode);

	if (resource.description) {
		info.appendChild(document.createElement('br'));
		info.appendChild(document.createTextNode(resource.description));
	}

	info.appendChild(document.createElement('br'));
	info.appendChild(document.createTextNode(mbText('capabilities_prefix', 'Capabilities: ') + (capabilities.length > 0 ? capabilities.join(', ') : '-')));
	info.appendChild(document.createElement('br'));
	info.appendChild(document.createTextNode(mbText('config_fields_prefix', 'Config fields: ') + String(schemaProperties)));
	info.appendChild(document.createElement('br'));
	info.appendChild(document.createTextNode(mbText('dock_definitions_prefix', 'Dock definitions: ') + String(dockCount)));

	if (dockCount > 0) {
		const dockNames = resource.docks
			.map((dock) => dock && dock.name ? String(dock.name) : '')
			.filter(Boolean);

		if (dockNames.length > 0) {
			info.appendChild(document.createElement('br'));
			info.appendChild(document.createTextNode(mbText('docks_prefix', 'Docks: ') + dockNames.join(', ')));
		}
	}

	renderCapabilityInfo(form);
}

function renderResourceEditor(form, record = null) {
	renderResourceInfo(form);

	const config = readPlainObjectPayload(form, record, 'config', 'config_json', 'Config JSON');
	const docks = readPlainObjectPayload(form, record, 'docks', 'docks_json', 'Docks JSON');

	renderConfigControls(form, config);
	renderDockControls(form, docks, getPresetIdFromForm(form));
	syncDefinitionFields(form);
}

function readPlainObjectPayload(form, record, recordKey, fieldName, label) {
	if (record && Object.prototype.hasOwnProperty.call(record, recordKey)) {
		return normalizePlainObjectPayload(record[recordKey], label);
	}

	return parseEditorJsonField(form, fieldName, label, true);
}

function normalizePlainObjectPayload(value, label = 'JSON value') {
	if (value && typeof value === 'object' && !Array.isArray(value)) {
		return value;
	}

	if (Array.isArray(value) && value.length === 0) {
		return {};
	}

	if (value === null || value === undefined || value === '') {
		return {};
	}

	throw new Error(label + ' must be a JSON object. Empty legacy arrays are accepted as empty objects.');
}

function normalizeJsonObjectString(value, label = 'JSON value') {
	if (value === null || value === undefined || value === '') {
		return '{}';
	}

	if (typeof value === 'string') {
		const trimmed = value.trim();

		if (trimmed === '') {
			return '{}';
		}

		try {
			return stringifyJson(normalizePlainObjectPayload(JSON.parse(trimmed), label));
		} catch (error) {
			return '{}';
		}
	}

	try {
		return stringifyJson(normalizePlainObjectPayload(value, label));
	} catch (error) {
		return '{}';
	}
}

function getPresetIdFromForm(form) {
	return getFormFieldValue(form, 'id') || getFormFieldValue(form, 'old_id');
}

function getResourceSchema(form) {
	const resource = getCurrentResource(form);

	if (!resource || !resource.schema || typeof resource.schema !== 'object' || Array.isArray(resource.schema)) {
		return {};
	}

	return resource.schema;
}

function getSchemaProperties(schema) {
	return schema && schema.properties && typeof schema.properties === 'object' && !Array.isArray(schema.properties)
		? schema.properties
		: {};
}

function getSchemaRequiredSet(schema) {
	return new Set(Array.isArray(schema && schema.required) ? schema.required.map(String) : []);
}

function getSchemaType(schema) {
	const type = schema && schema.type !== undefined ? schema.type : 'string';

	if (Array.isArray(type)) {
		return String(type.find((item) => item !== 'null') || 'string');
	}

	return String(type || 'string');
}

function getSchemaDefault(schema) {
	if (schema && Object.prototype.hasOwnProperty.call(schema, 'default')) {
		return schema.default;
	}

	const type = getSchemaType(schema);

	if (type === 'boolean') {
		return false;
	}

	if (type === 'integer' || type === 'number') {
		return '';
	}

	if (type === 'array') {
		return [];
	}

	if (type === 'object') {
		return {};
	}

	return '';
}

function getRawConfigValue(config, key, schema) {
	if (config && Object.prototype.hasOwnProperty.call(config, key)) {
		return config[key];
	}

	return getSchemaDefault(schema);
}

function unwrapConfigValue(value) {
	if (value === SECRET_VALUE_MARKER) {
		return {
			value: '',
			mode: '',
			secretConfigured: true
		};
	}

	if (value && typeof value === 'object' && !Array.isArray(value) && Object.prototype.hasOwnProperty.call(value, 'mode') && Object.prototype.hasOwnProperty.call(value, 'value')) {
		return {
			value: value.value,
			mode: String(value.mode || ''),
			secretConfigured: false
		};
	}

	return {
		value,
		mode: '',
		secretConfigured: false
	};
}

function createDefinitionRow(labelText, required = false) {
	const row = createElement('agent-component-preset-step5-definition-row' + (required ? ' agent-component-preset-step5-definition-row-required' : ''));
	const label = createElement('agent-component-preset-step5-definition-label', labelText);
	const control = createElement('agent-component-preset-step5-definition-control');

	row.appendChild(label);
	row.appendChild(control);

	return {
		row,
		control
	};
}

function createConfigControl(key, schema, value, mode, required = false, secretConfigured = false) {
	const type = getSchemaType(schema);
	const ui = schema && schema['x-ui'] && typeof schema['x-ui'] === 'object' && !Array.isArray(schema['x-ui'])
		? schema['x-ui']
		: {};
	const uiControl = String(ui.control || '').toLowerCase();
	const isSensitive = uiControl === 'password' || ui.sensitive === true;
	let control;

	if (Array.isArray(schema && schema.enum) && schema.enum.length > 0) {
		control = document.createElement('select');
		control.className = 'agent-component-preset-step5-select';
		if (required || value === null || value === undefined || value === '') {
			const empty = document.createElement('option');
			empty.value = '';
			empty.textContent = mbText('select', 'Select...');
			control.appendChild(empty);
		}
		schema.enum.forEach((item) => {
			const option = document.createElement('option');
			option.value = String(item);
			option.textContent = String(item);
			control.appendChild(option);
		});
		control.value = value === null || value === undefined ? '' : String(value);
	} else if (type === 'boolean') {
		control = document.createElement('input');
		control.type = 'checkbox';
		control.checked = !!value;
	} else if (type === 'integer' || type === 'number') {
		control = document.createElement('input');
		control.type = 'number';
		control.step = type === 'integer' ? '1' : 'any';
		control.className = 'agent-component-preset-step5-input';
		control.value = value === null || value === undefined ? '' : String(value);
		if (schema && schema.minimum !== undefined) {
			control.min = String(schema.minimum);
		}
		if (schema && schema.maximum !== undefined) {
			control.max = String(schema.maximum);
		}
	} else if (type === 'string' && isSensitive) {
		control = document.createElement('input');
		control.type = 'password';
		control.className = 'agent-component-preset-step5-input';
		control.autocomplete = String(ui.autocomplete || 'new-password');
		control.value = '';
		control.placeholder = secretConfigured ? 'Stored value unchanged' : '';
		control.dataset.configSecret = '1';
		control.dataset.configSecretConfigured = secretConfigured ? '1' : '0';
	} else if (type === 'object' || type === 'array' || (type === 'string' && uiControl === 'textarea')) {
		control = document.createElement('textarea');
		control.className = 'agent-component-preset-step5-textarea';
		control.rows = Math.max(2, Math.min(40, Number(ui.rows || (type === 'string' ? 8 : 5)) || 5));
		control.value = type === 'string'
			? (value === null || value === undefined ? '' : String(value))
			: stringifyJson(value === undefined ? getSchemaDefault(schema) : value);
	} else {
		control = document.createElement('input');
		control.type = 'text';
		control.className = 'agent-component-preset-step5-input';
		control.value = value === null || value === undefined ? '' : String(value);
	}

	control.dataset.configKey = key;
	control.dataset.configType = type;
	control.dataset.configMode = mode || '';
	control.setAttribute('aria-required', required ? 'true' : 'false');
	if (required && type !== 'boolean' && !secretConfigured) {
		control.required = true;
	}
	control.addEventListener('input', () => syncDefinitionFields(control.form || getEditorElements().form));
	control.addEventListener('change', () => syncDefinitionFields(control.form || getEditorElements().form));

	return control;
}

function renderConfigControls(form, config) {
	const root = document.getElementById('agent-component-preset-step5-config-fields');

	if (!root) {
		return;
	}

	root.replaceChildren();

	const schema = getResourceSchema(form);
	const properties = getSchemaProperties(schema);
	const required = getSchemaRequiredSet(schema);
	const keys = Object.keys(properties);

	if (keys.length === 0) {
		root.appendChild(createElement('agent-component-preset-step5-definition-empty', 'This resource does not provide a configuration schema. The saved config will be an empty object unless existing unknown values are preserved by the current hidden payload.'));
		return;
	}

	keys.forEach((key) => {
		const propertySchema = properties[key] || {};
		const raw = getRawConfigValue(config, key, propertySchema);
		const unwrapped = unwrapConfigValue(raw);
		const parts = createDefinitionRow(key, required.has(key));
		const control = createConfigControl(key, propertySchema, unwrapped.value, unwrapped.mode, required.has(key), unwrapped.secretConfigured);

		parts.control.appendChild(control);

		const hints = [];
		if (propertySchema.description) {
			hints.push(String(propertySchema.description));
		}
		hints.push('Type: ' + getSchemaType(propertySchema));
		if (unwrapped.mode) {
			hints.push('Stored as ConfigValue mode "' + unwrapped.mode + '"; the mode will be preserved.');
		}
		if (unwrapped.secretConfigured) {
			hints.push('A secret value is configured. Leave the field empty to keep it unchanged.');
		}

		parts.control.appendChild(createElement('agent-component-preset-step5-definition-hint', hints.join(' ')));
		root.appendChild(parts.row);
	});
}

function readConfigControlValue(control) {
	const type = control.dataset.configType || 'string';

	if (control.dataset.configSecret === '1' && control.value === '' && control.dataset.configSecretConfigured === '1') {
		return SECRET_VALUE_MARKER;
	}

	let value;

	if (type === 'boolean') {
		value = control.checked;
	} else if (type === 'integer') {
		value = control.value === '' ? null : parseInt(control.value, 10);
	} else if (type === 'number') {
		value = control.value === '' ? null : Number(control.value);
	} else if (type === 'object' || type === 'array') {
		value = JSON.parse(control.value || (type === 'array' ? '[]' : '{}'));
	} else {
		value = control.value;
	}

	if ((type === 'integer' || type === 'number') && value !== null && !Number.isFinite(value)) {
		throw new Error('Config field "' + control.dataset.configKey + '" must be numeric.');
	}

	if (control.dataset.configMode) {
		return {
			mode: control.dataset.configMode,
			value
		};
	}

	return value;
}

function buildConfigJsonFromControls(form) {
	const controls = Array.from(form.querySelectorAll('[data-config-key]'));

	if (controls.length === 0) {
		return null;
	}

	const configField = form.elements.namedItem('config_json');
	const result = configField
		? parseEditorJsonField(form, 'config_json', 'Config JSON', true)
		: {};

	controls.forEach((control) => {
		const key = control.dataset.configKey || '';

		if (!key) {
			return;
		}

		result[key] = readConfigControlValue(control);
	});

	return result;
}

function candidateMatchesDock(preset, dock) {
	if (!preset || !dock) {
		return false;
	}

	if (preset.enabled === false) {
		return false;
	}

	const requiredInterface = String(dock.interface || '').trim();

	if (requiredInterface === '') {
		return true;
	}

	const interfaces = Array.isArray(preset.interfaces) ? preset.interfaces.map(String) : [];

	return interfaces.indexOf(requiredInterface) !== -1;
}

function getDockTargetValues(docks, dockName) {
	const value = docks && typeof docks === 'object' ? docks[dockName] : [];

	if (Array.isArray(value)) {
		return value.map((item) => String(item || '').trim()).filter(Boolean);
	}

	const single = String(value || '').trim();

	return single ? [single] : [];
}

function renderDockControls(form, docks, currentPresetId = '') {
	const root = document.getElementById('agent-component-preset-step5-dock-fields');

	if (!root) {
		return;
	}

	root.replaceChildren();

	const resource = getCurrentResource(form);
	const definitions = resource && Array.isArray(resource.docks) ? resource.docks : [];

	if (definitions.length === 0) {
		root.appendChild(createElement('agent-component-preset-step5-definition-empty', 'This resource does not define any docks.'));
		return;
	}

	definitions.forEach((dock) => {
		const dockName = String(dock.name || '').trim();

		if (!dockName) {
			return;
		}

		const maxConnections = dock.maxConnections === null || dock.maxConnections === undefined ? null : Number(dock.maxConnections);
		const isMultiple = maxConnections === null || maxConnections !== 1;
		const selectedValues = getDockTargetValues(docks, dockName);
		const parts = createDefinitionRow(dockName, !!dock.required);
		const select = document.createElement('select');
		const candidates = PRESET_OPTIONS.filter((preset) => String(preset.id || '') !== String(currentPresetId || '') && candidateMatchesDock(preset, dock));

		select.className = 'agent-component-preset-step5-select';
		select.dataset.dockName = dockName;
		select.multiple = isMultiple;
		select.required = !!dock.required;
		select.setAttribute('aria-required', dock.required ? 'true' : 'false');

		if (!isMultiple) {
			const empty = document.createElement('option');
			empty.value = '';
			empty.textContent = dock.required ? mbText('select_required_dock_target', 'Select required dock target') : mbText('no_dock_target', 'No dock target');
			select.appendChild(empty);
		}

		candidates.forEach((preset) => {
			const option = document.createElement('option');
			option.value = String(preset.id || '');
			option.textContent = String((preset.label || preset.id || '') + ' (' + (preset.type || '-') + ')');
			option.title = String(preset.class || '');
			select.appendChild(option);
		});

		selectedValues.forEach((value) => {
			if (!Array.from(select.options).some((option) => option.value === value)) {
				const option = document.createElement('option');
				option.value = value;
				option.textContent = mbText('current_value', 'Current value: {value}', {value});
				option.dataset.generated = '1';
				select.appendChild(option);
			}
		});

		Array.from(select.options).forEach((option) => {
			option.selected = selectedValues.indexOf(option.value) !== -1;
		});

		select.addEventListener('change', () => syncDefinitionFields(form));
		parts.control.appendChild(select);

		const hintParts = [];
		if (dock.description) {
			hintParts.push(String(dock.description));
		}
		if (dock.interface) {
			hintParts.push('Requires: ' + dock.interface);
		}
		hintParts.push(isMultiple ? 'Multiple targets allowed.' : 'One target allowed.');
		parts.control.appendChild(createElement('agent-component-preset-step5-definition-hint', hintParts.join(' ')));
		root.appendChild(parts.row);
	});
}

function buildDocksJsonFromControls(form) {
	const controls = Array.from(form.querySelectorAll('[data-dock-name]'));

	if (controls.length === 0) {
		return null;
	}

	const result = {};

	controls.forEach((select) => {
		const name = select.dataset.dockName || '';

		if (!name) {
			return;
		}

		const values = Array.from(select.selectedOptions || [])
			.map((option) => String(option.value || '').trim())
			.filter(Boolean);

		if (values.length > 0) {
			result[name] = values;
		}
	});

	return result;
}

function syncDefinitionFields(form) {
	if (!form) {
		return;
	}

	const configField = form.elements.namedItem('config_json');
	const docksField = form.elements.namedItem('docks_json');

	if (configField) {
		const config = buildConfigJsonFromControls(form);

		if (config !== null) {
			configField.value = stringifyJson(config);
		}
	}

	if (docksField) {
		const docks = buildDocksJsonFromControls(form);

		if (docks !== null) {
			docksField.value = stringifyJson(docks);
		}
	}
}

function setStartupStatus(message, details = '', isError = false) {
	const root = document.querySelector(GRID_SELECTOR);

	log('startup: ' + message, details || undefined);

	if (!root) {
		return;
	}

	const box = createElement('agent-component-preset-step5-startup' + (isError ? ' agent-component-preset-step5-startup-error' : ''));
	box.appendChild(document.createTextNode(message));

	if (details) {
		const pre = document.createElement('pre');
		pre.textContent = details;
		box.appendChild(pre);
	}

	root.replaceChildren(box);
}

async function importFirst(url, moduleLabel) {
	log('import start: ' + moduleLabel, url);

	try {
		const absoluteUrl = new URL(url, document.baseURI).href;
		log('import attempt: ' + moduleLabel, absoluteUrl);
		const module = await import(absoluteUrl);
		log('import success: ' + moduleLabel, Object.keys(module || {}));
		return module;
	} catch (error) {
		log('import failed: ' + moduleLabel, error && error.message ? error.message : String(error));
		throw error;
	}
}

async function postJson(payload) {
	log('POST JSON start', payload);

	/*
	 * CRITICAL: Do not change this request contract.
	 * ModularGrid and the BASE3/ILIAS endpoint currently rely on this exact fetch setup:
	 * POST + Content-Type application/json + JSON.stringify(payload).
	 * Do not add credentials, mode, cache, FormData, query params, CSRF handling,
	 * wrappers, adapter changes, or any other request architecture here.
	 * Any change to this block requires an explicit user request and a separate runtime test.
	 */
	const response = await fetch(ENDPOINT_URL, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(payload)
	});

	log('POST JSON response status', response.status);

	if (!response.ok) {
		throw new Error('Request failed with status ' + response.status);
	}

	const json = await response.json();
	log('POST JSON response body', json);

	return json;
}

async function refreshGrid() {
	if (!grid) {
		setLog(mbText('grid_is_not_initialized_refresh_skipped', 'Grid is not initialized; refresh skipped.'));
		return;
	}

	const commands = ['reloadData', 'reload', 'refreshData', 'refresh'];

	if (typeof grid.execute === 'function') {
		for (const commandName of commands) {
			try {
				const result = grid.execute(commandName);

				if (result && typeof result.then === 'function') {
					await result;
				}

				return;
			} catch (error) {}
		}
	}

	for (const methodName of commands) {
		if (typeof grid[methodName] === 'function') {
			const result = grid[methodName]();

			if (result && typeof result.then === 'function') {
				await result;
			}

			return;
		}
	}

	setLog(mbText('grid_refresh_is_not_available_please_refresh_the_page_manually', 'Grid refresh is not available. Please refresh the page manually.'));
}

function renderPreset(value, row) {
	const wrapper = createElement('agent-component-preset-step5-cell-stack');
	const main = createElement('agent-component-preset-step5-cell-main', getText(row.label || row.preset_id));
	const sub = createElement('agent-component-preset-step5-cell-sub', getText(row.preset_id));

	wrapper.appendChild(main);
	wrapper.appendChild(sub);

	return wrapper;
}

function renderType(value, row) {
	const wrapper = createElement('agent-component-preset-step5-cell-stack');
	const main = createElement('agent-component-preset-step5-cell-main', getText(row.type));
	const sub = createElement('agent-component-preset-step5-cell-sub', getText(row.category));

	wrapper.appendChild(main);
	wrapper.appendChild(sub);

	return wrapper;
}

function renderPills(value) {
	const wrapper = createElement('agent-component-preset-step5-pill-row');
	const items = String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

	if (items.length === 0) {
		wrapper.appendChild(createPill('-'));
		return wrapper;
	}

	items.forEach((item) => wrapper.appendChild(createPill(item)));

	return wrapper;
}

function renderCapabilities(value, row) {
	const wrapper = createElement('agent-component-preset-step5-cell-stack');
	const interfaces = getDisplayInterfaceText(row);
	const interfaceLine = createElement('agent-component-preset-step5-cell-sub', getText(interfaces));

	wrapper.appendChild(renderPills(value));
	wrapper.appendChild(interfaceLine);

	return wrapper;
}

function getDisplayInterfaceText(row) {
	if (!row || typeof row !== 'object') {
		return '';
	}

	if (Array.isArray(row.display_interfaces)) {
		return row.display_interfaces.map((item) => String(item || '').trim()).filter(Boolean).join(', ');
	}

	return String(row.interface_text || '').trim();
}

function createPill(text) {
	const pill = document.createElement('span');
	pill.className = 'agent-component-preset-step5-pill';
	pill.textContent = getText(text);

	return pill;
}

function getPresetIdFromRow(row) {
	if (!row || typeof row !== 'object') {
		return '';
	}

	return String(row.preset_id || row.id || '').trim();
}

async function loadRemoteRecord(row) {
	const id = getPresetIdFromRow(row);

	if (!id) {
		throw new Error('Missing preset id for detail request.');
	}

	const response = await postJson({
		mode: 'record',
		id
	});

	if (!response || !response.ok || !response.record) {
		throw new Error(response && response.error ? response.error : 'No record returned for ' + id);
	}

	return response.record;
}

function createDetailLoadingPlaceholder(row) {
	return createElement('agent-component-preset-step5-startup', 'Loading record for ' + getText(getPresetIdFromRow(row)) + '...');
}

function createDetailErrorPlaceholder(row, error) {
	return createElement('agent-component-preset-step5-startup agent-component-preset-step5-startup-error', 'Failed to load record for ' + getText(getPresetIdFromRow(row)) + ': ' + getText(error && error.message ? error.message : error));
}

function createDetailRow(key, value) {
	const row = createElement('agent-component-preset-step5-detail-row');
	row.appendChild(createElement('agent-component-preset-step5-detail-key', key));
	row.appendChild(createElement('', getText(value)));

	return row;
}

function renderPresetDetail(context) {
	const record = context && context.payload ? context.payload : null;

	if (!record || typeof record !== 'object') {
		return document.createTextNode(getText(record));
	}

	const wrapper = createElement('agent-component-preset-step5-detail');
	const left = createElement('agent-component-preset-step5-detail-card');
	const right = createElement('agent-component-preset-step5-detail-card');
	const pre = document.createElement('pre');

	left.appendChild(createElement('agent-component-preset-step5-detail-title', getText(record.label || record.preset_id)));
	left.appendChild(createDetailRow(mbText('id', 'ID'), record.preset_id || record.id));
	left.appendChild(createDetailRow(mbText('type', 'Type'), record.type));
	left.appendChild(createDetailRow(mbText('enabled', 'Enabled'), record.enabled ? 'yes' : 'no'));
	left.appendChild(createDetailRow(mbText('capabilities', 'Capabilities'), record.capability_text));
	left.appendChild(createDetailRow(mbText('interfaces', 'Interfaces'), getDisplayInterfaceText(record)));
	left.appendChild(createDetailRow(mbText('category', 'Category'), record.category));
	left.appendChild(createDetailRow(mbText('status', 'Status'), record.status));
	left.appendChild(createDetailRow(mbText('risk', 'Risk'), record.risk));
	left.appendChild(createDetailRow(mbText('version', 'Version'), record.version));
	left.appendChild(createDetailRow(mbText('description', 'Description'), record.description));

	right.appendChild(createElement('agent-component-preset-step5-detail-title', 'Record JSON'));
	pre.className = 'agent-component-preset-step5-json';
	pre.textContent = record.preset_json || stringifyJson(record);
	right.appendChild(pre);

	wrapper.appendChild(left);
	wrapper.appendChild(right);

	return wrapper;
}


function createEditorContent() {
	if (editorContent) {
		return editorContent;
	}

	const template = document.getElementById('agent-component-preset-step5-editor-template');

	if (!template || !template.content) {
		throw new Error('Preset editor template not found.');
	}

	const fragment = template.content.cloneNode(true);
	const content = fragment.querySelector('#agent-component-preset-step5-editor-content');

	if (!content) {
		throw new Error('Preset editor content not found.');
	}

	editorContent = content;

	return editorContent;
}

function getEditorElements() {
	const root = editorContent;

	return {
		root,
		form: root ? root.querySelector('#agent-component-preset-step5-form') : null
	};
}

function setEditorStatus(message, type = '') {
	if (!editorDialog || typeof editorDialog.execute !== 'function') {
		return;
	}

	editorDialog.execute('setStatus', {
		message: message || '',
		type
	});
}

function setFormValue(form, name, value) {
	const field = form.elements.namedItem(name);

	if (!field) {
		return;
	}

	field.value = value === null || value === undefined ? '' : String(value);
}

function ensureSelectOption(form, name, value, label = '') {
	const field = form.elements.namedItem(name);
	const normalizedValue = value === null || value === undefined ? '' : String(value);

	if (!(field instanceof HTMLSelectElement) || normalizedValue === '') {
		return;
	}

	const exists = Array.from(field.options).some((option) => option.value === normalizedValue);

	if (exists) {
		return;
	}

	const option = document.createElement('option');
	option.value = normalizedValue;
	option.textContent = label || normalizedValue;
	option.dataset.generated = '1';
	field.appendChild(option);
}

function setSelectValue(form, name, value) {
	const field = form.elements.namedItem(name);
	const normalizedValue = value === null || value === undefined ? '' : String(value);

	if (!(field instanceof HTMLSelectElement)) {
		setFormValue(form, name, normalizedValue);
		return;
	}

	ensureSelectOption(form, name, normalizedValue);
	field.value = normalizedValue;
}

function setCapabilityCheckboxes(form, capabilities) {
	renderCapabilityInfo(form);
}

function getCapabilityValues(form) {
	return getDerivedCapabilitiesForType(getFormFieldValue(form, 'type'));
}

function buildMetaJsonFromForm(form) {
	const meta = parseEditorJsonField(form, 'meta_json', mbText('meta_json', 'Meta JSON'), true);
	const versionRaw = getFormFieldValue(form, 'version');

	meta.description = getFormFieldValue(form, 'description');
	meta.category = getFormFieldValue(form, 'category');
	meta.risk = getFormFieldValue(form, 'risk');
	meta.status = getFormFieldValue(form, 'status');

	if (versionRaw === '') {
		delete meta.version;
	} else {
		const numericVersion = Number(versionRaw);
		meta.version = Number.isFinite(numericVersion) && String(numericVersion) === versionRaw ? numericVersion : versionRaw;
	}

	return meta;
}


function buildEditorButtons(isExisting = false) {
	const buttons = [
		{
			key: 'copy-payload',
			label: mbText('copy_payload', 'Copy payload'),
			async action() {
				await copyEditorPayload();
			}
		},
		{
			key: 'save',
			label: mbText('save', 'Save'),
			primary: true,
			busyLabel: 'Saving...',
			async action() {
				await saveEditorPayload();
			}
		}
	];

	if (isExisting) {
		buttons.unshift({
			key: 'delete-current-preset',
			label: mbText('delete', 'Delete'),
			danger: true,
			busyLabel: 'Deleting...',
			async action() {
				await deleteCurrentPresetFromEditor();
			}
		});
	}

	return buttons;
}

function initEditorDialog(modularDialogModule) {
	if (editorDialog) {
		return editorDialog;
	}

	if (!modularDialogModule || typeof modularDialogModule.createStandardDialog !== 'function') {
		throw new Error('ModularDialog createStandardDialog export not found.');
	}

	const content = createEditorContent();

	editorDialog = modularDialogModule.createStandardDialog({
			strings: mbStringSet('cs_dialog_'),
		id: 'agent-component-preset-step5-editor-dialog',
		className: 'agent-component-preset-step5-dialog',
		surfaceClassName: 'agent-component-preset-step5-dialog-surface',
		size: 'large',
		title: mbText('preset_editor', 'Preset editor'),
		content,
		status: 'Save is enabled.',
		closeButtonPlugin: {
			label: mbText('close', 'Close')
		},
		statusPlugin: {
			renderEmpty: false
		},
		buttons: buildEditorButtons()
	});

	editorDialog.on('afterClose', () => {
		currentEditorPresetId = '';
		setLog(mbText('closed_editor', 'Closed editor.'));
	});

	editorDialog.init();

	return editorDialog;
}

function openPresetEditor(record) {
	const elements = getEditorElements();

	if (!editorDialog || !elements.form) {
		setLog(mbText('preset_editor_is_not_available', 'Preset editor is not available.'));
		return;
	}

	const form = elements.form;
	form.reset();

	record = record && typeof record === 'object' ? record : {};

	const oldIdValue = Object.prototype.hasOwnProperty.call(record, 'old_id') ? record.old_id : (record.preset_id || record.id || '');
	currentEditorPresetId = String(oldIdValue || '').trim();
	setFormValue(form, 'old_id', oldIdValue);
	setFormValue(form, 'id', record.preset_id || record.id || '');
	setFormValue(form, 'label', record.label || '');
	setSelectValue(form, 'type', record.type || '');
	setSelectValue(form, 'category', record.category || '');
	setSelectValue(form, 'status', record.status || '');
	setSelectValue(form, 'risk', record.risk || '');
	setFormValue(form, 'version', record.version || '');
	setFormValue(form, 'description', record.description || '');
	setFormValue(form, 'config_json', normalizeJsonObjectString(Object.prototype.hasOwnProperty.call(record, 'config') ? record.config : record.config_json, 'Config JSON'));
	setFormValue(form, 'docks_json', normalizeJsonObjectString(Object.prototype.hasOwnProperty.call(record, 'docks') ? record.docks : record.docks_json, 'Docks JSON'));
	setFormValue(form, 'meta_json', normalizeJsonObjectString(Object.prototype.hasOwnProperty.call(record, 'meta') ? record.meta : record.meta_json, mbText('meta_json', 'Meta JSON')));

	const enabled = form.elements.namedItem('enabled');
	if (enabled) {
		enabled.checked = record.enabled !== false;
	}

	let renderError = null;

	try {
		renderResourceEditor(form, record);
	} catch (error) {
		renderError = error;
		renderResourceInfo(form);
		const configRoot = form.querySelector('#agent-component-preset-step5-config-fields');
		const dockRoot = form.querySelector('#agent-component-preset-step5-dock-fields');

		if (configRoot) {
			configRoot.replaceChildren(createElement('agent-component-preset-step5-definition-empty', 'Configuration controls could not be rendered: ' + getText(error && error.message, String(error))));
		}

		if (dockRoot) {
			dockRoot.replaceChildren(createElement('agent-component-preset-step5-definition-empty', 'Dock controls could not be rendered.'));
		}

		setEditorStatus(getText(error && error.message, String(error)), 'error');
		setLog(mbText('editor_render_failed_prefix', 'Editor render failed: ') + getText(error && error.message, String(error)));
	}

	editorDialog.execute('setTitle', record.preset_id || record.id ? mbText('edit_preset', 'Edit preset') : mbText('add_preset', 'Add preset'));
	editorDialog.execute('setButtons', buildEditorButtons(currentEditorPresetId !== ''));

	if (renderError) {
		setEditorStatus('Editor opened, but generated controls need attention: ' + getText(renderError && renderError.message, String(renderError)), 'error');
	} else {
		setEditorStatus('Editor opened. Save is enabled.', 'ok');
	}

	editorDialog.open({ source: 'agentComponentPresetEditor', record });

	window.setTimeout(() => {
		const idField = form.elements.namedItem('id');

		if (idField && idField.value === '') {
			idField.focus();
			return;
		}

		const typeField = form.elements.namedItem('type');
		if (typeField) {
			typeField.focus();
		}
	}, 0);

	setLog(mbText('opened_editor_for_prefix', 'Opened editor for ') + getText(record.preset_id || record.id, 'new preset'));
}

function openNewPresetEditor() {
	openPresetEditor({
		preset_id: '',
		id: '',
		label: '',
		type: '',
		enabled: true,
		capabilities: ['tool'],
		category: '',
		status: 'draft',
		risk: '',
		version: 1,
		description: '',
		config_json: '{}',
		docks_json: '{}',
		meta_json: stringifyJson({
			description: '',
			category: '',
			risk: '',
			status: 'draft',
			version: 1
		})
	});
}

function createDuplicatePresetRecord(record) {
	record = record && typeof record === 'object' ? record : {};

	const sourceId = String(record.preset_id || record.id || '').trim();
	const sourceLabel = String(record.label || sourceId || '').trim();
	const duplicateId = sourceId ? sourceId + '_copy' : '';
	const duplicate = Object.assign({}, record);

	duplicate.old_id = '';
	duplicate.preset_id = duplicateId;
	duplicate.id = duplicateId;
	duplicate.label = sourceLabel ? 'Copy of ' + sourceLabel : '';

	return duplicate;
}

function closePresetEditor() {
	if (!editorDialog) {
		return;
	}

	editorDialog.close({ source: 'agentComponentPresetEditor' });
}

function getFormFieldValue(form, name) {
	const field = form.elements.namedItem(name);

	if (!field) {
		return '';
	}

	return String(field.value || '').trim();
}

function parseEditorJsonField(form, fieldName, label, requirePlainObject = false) {
	const field = form.elements.namedItem(fieldName);
	const value = field ? String(field.value || '').trim() : '';
	let decoded;

	try {
		decoded = JSON.parse(value || '{}');
	} catch (error) {
		throw new Error(label + ': ' + (error && error.message ? error.message : String(error)));
	}

	if (decoded === null || typeof decoded !== 'object') {
		throw new Error(label + ' must decode to a JSON object or array.');
	}

	if (requirePlainObject) {
		if (Array.isArray(decoded) && decoded.length === 0) {
			return {};
		}

		if (Array.isArray(decoded)) {
			throw new Error(label + ' must decode to a JSON object. Empty legacy arrays are accepted.');
		}
	}

	return decoded;
}

function getSelectedCapabilities(form) {
	return getCapabilityValues(form);
}

function validateEditorRequiredFields(form, capabilities) {
	if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
		throw new Error('Complete all required preset fields.');
	}

	const id = getFormFieldValue(form, 'id');
	const label = getFormFieldValue(form, 'label');
	const type = getFormFieldValue(form, 'type');

	if (!id) {
		throw new Error('Preset ID is required.');
	}

	if (!label) {
		throw new Error('Label is required.');
	}

	if (!type) {
		throw new Error('Resource type is required.');
	}

}

function syncVisibleMetaFields(form) {
	const meta = buildMetaJsonFromForm(form);
	form.elements.namedItem('meta_json').value = stringifyJson(meta);

	return meta;
}

function buildEditorPayload(options = {}) {
	const settings = Object.assign({ validateRequired: false, syncMeta: true }, options || {});
	const elements = getEditorElements();
	const form = elements.form;

	if (!form) {
		throw new Error('Preset editor form not found.');
	}

	const capabilities = getSelectedCapabilities(form);

	syncDefinitionFields(form);
	parseEditorJsonField(form, 'config_json', 'Config JSON');
	parseEditorJsonField(form, 'docks_json', 'Docks JSON');

	if (settings.syncMeta) {
		syncVisibleMetaFields(form);
	} else {
		parseEditorJsonField(form, 'meta_json', mbText('meta_json', 'Meta JSON'), true);
	}

	if (settings.validateRequired) {
		validateEditorRequiredFields(form, capabilities);
	}

	return {
		mode: 'save',
		old_id: getFormFieldValue(form, 'old_id'),
		id: getFormFieldValue(form, 'id'),
		label: getFormFieldValue(form, 'label'),
		type: getFormFieldValue(form, 'type'),
		enabled: form.elements.namedItem('enabled').checked,
		capabilities,
		category: getFormFieldValue(form, 'category'),
		status: getFormFieldValue(form, 'status'),
		risk: getFormFieldValue(form, 'risk'),
		version: getFormFieldValue(form, 'version'),
		description: getFormFieldValue(form, 'description'),
		config_json: form.elements.namedItem('config_json').value,
		docks_json: form.elements.namedItem('docks_json').value,
		meta_json: form.elements.namedItem('meta_json').value
	};
}

async function copyEditorPayload() {
	try {
		const payload = buildEditorPayload({ validateRequired: false, syncMeta: true });
		await copyText(stringifyJson(payload));
		setEditorStatus('Payload copied. Visible meta fields were synchronized.', 'ok');
		setLog(mbText('copied_editor_payload_for_prefix', 'Copied editor payload for ') + getText(payload.id, 'new preset'));
	} catch (error) {
		setEditorStatus(error && error.message ? error.message : String(error), 'error');
	}
}

async function saveEditorPayload() {
	try {
		const payload = buildEditorPayload({ validateRequired: true, syncMeta: true });

		setEditorStatus('Saving preset...', '');
		setLog(mbText('saving_preset_prefix', 'Saving preset ') + getText(payload.id, 'new preset'));

		const response = await postJson(payload);

		if (!response || !response.ok) {
			throw new Error(response && response.error ? response.error : 'Save failed.');
		}

		setEditorStatus('Preset saved. Updating grid...', 'ok');
		closePresetEditor();
		await refreshGrid();

		const record = response.record || payload;
		setLog(mbText('saved_preset_prefix', 'Saved preset ') + getText(record.preset_id || record.id || payload.id, payload.id) + '.');
	} catch (error) {
		setEditorStatus(error && error.message ? error.message : String(error), 'error');
		setLog(mbText('save_failed_prefix', 'Save failed: ') + getText(error && error.message ? error.message : error));
	}
}

async function deletePresetById(id) {
	id = String(id || '').trim();

	if (!id) {
		throw new Error('Missing preset id.');
	}

	const response = await postJson({
		mode: 'delete',
		id
	});

	if (!response || !response.ok) {
		throw new Error(response && response.error ? response.error : 'Delete failed.');
	}

	return response;
}

async function deleteCurrentPresetFromEditor() {
	const elements = getEditorElements();
	const form = elements.form;

	if (!form) {
		setLog(mbText('delete_failed_preset_editor_form_not_found', 'Delete failed: preset editor form not found.'));
		return;
	}

	const id = String(currentEditorPresetId || getFormFieldValue(form, 'old_id') || getFormFieldValue(form, 'id') || '').trim();

	if (!id) {
		setEditorStatus('Missing preset id.', 'error');
		return;
	}

	if (!window.confirm(mbText('delete_preset_confirm', 'Delete preset \"{id}\"?', {id}))) {
		setLog(mbText('delete_cancelled_for_prefix', 'Delete cancelled for ') + id);
		return;
	}

	try {
		setEditorStatus('Deleting preset...', '');
		setLog(mbText('deleting_preset_prefix', 'Deleting preset ') + id);

		const response = await deletePresetById(id);

		closePresetEditor();
		await refreshGrid();
		setLog(mbText('deleted_preset_prefix', 'Deleted preset ') + getText(response.id || id, id) + '.');
	} catch (error) {
		setEditorStatus(error && error.message ? error.message : String(error), 'error');
		setLog(mbText('delete_failed_prefix', 'Delete failed: ') + getText(error && error.message ? error.message : error));
	}
}

async function deletePresetFromRow(row) {
	try {
		const id = getPresetIdFromRow(row);

		if (!id) {
			throw new Error('Missing preset id.');
		}

		if (!window.confirm(mbText('delete_preset_confirm', 'Delete preset \"{id}\"?', {id}))) {
			setLog(mbText('delete_cancelled_for_prefix', 'Delete cancelled for ') + id);
			return;
		}

		setLog(mbText('deleting_preset_prefix', 'Deleting preset ') + id);
		const response = await deletePresetById(id);
		setLog(mbText('deleted_preset_prefix', 'Deleted preset ') + getText(response.id || id, id) + '. Updating grid...');
		await refreshGrid();
		setLog(mbText('deleted_preset_prefix', 'Deleted preset ') + getText(response.id || id, id) + '.');
	} catch (error) {
		setLog(mbText('delete_failed_prefix', 'Delete failed: ') + getText(error && error.message ? error.message : error));
	}
}

async function reloadPresetDefaults() {
	try {
		if (!window.confirm(mbText('reload_preset_defaults_from_settingsstore', 'Reload preset defaults from SettingsStore?'))) {
			setLog(mbText('reload_defaults_cancelled', 'Reload defaults cancelled.'));
			return;
		}

		setLog(mbText('reloading_preset_defaults', 'Reloading preset defaults.'));
		setLog(mbText('available_resource_options', 'Available resource options'), RESOURCE_OPTIONS);

		const response = await postJson({
			mode: 'reload'
		});

		if (!response || !response.ok) {
			throw new Error(response && response.error ? response.error : 'Reload failed.');
		}

		setLog(mbText('preset_defaults_reloaded', 'Preset defaults reloaded.'));
		setLog(mbText('reload_response', 'Reload response'), response);

		window.setTimeout(() => {
			window.location.reload();
		}, 500);
	} catch (error) {
		setLog(mbText('reload_defaults_failed_prefix', 'Reload defaults failed: ') + getText(error && error.message ? error.message : error));
	}
}

async function openEditorById(id) {
	id = String(id || '').trim();
	if (!id) {
		return;
	}

	try {
		setLog(mbText('loading_preset_from_direct_link_prefix', 'Loading preset from direct link: ') + id);
		const record = await loadRemoteRecord({ preset_id: id });
		openPresetEditor(record);
	} catch (error) {
		setLog(mbText('could_not_open_linked_preset_prefix', 'Could not open linked preset: ') + getText(error && error.message ? error.message : error));
	}
}

async function openEditorFromRow(row) {
	try {
		setLog(mbText('loading_record_for_editor_prefix', 'Loading record for editor: ') + getText(getPresetIdFromRow(row)));
		const record = await loadRemoteRecord(row);
		openPresetEditor(record);
	} catch (error) {
		setLog(mbText('could_not_open_editor_prefix', 'Could not open editor: ') + getText(error && error.message ? error.message : error));
	}
}

async function openDuplicateEditorFromRow(row) {
	try {
		setLog(mbText('loading_record_for_duplicate_prefix', 'Loading record for duplicate: ') + getText(getPresetIdFromRow(row)));
		const record = await loadRemoteRecord(row);
		const duplicate = createDuplicatePresetRecord(record);

		openPresetEditor(duplicate);
		setEditorStatus('Duplicate opened. Review the preset id, then save as a new preset.', 'ok');
		setLog(mbText('opened_duplicate_editor_for_prefix', 'Opened duplicate editor for ') + getText(record.preset_id || record.id));
	} catch (error) {
		setLog(mbText('could_not_duplicate_preset_prefix', 'Could not duplicate preset: ') + getText(error && error.message ? error.message : error));
	}
}

function bindEditorEvents() {
	const elements = getEditorElements();

	if (elements.form) {
		elements.form.addEventListener('submit', (event) => {
			event.preventDefault();
			saveEditorPayload();
		});
	}

	if (elements.form && elements.form.elements.namedItem('type')) {
		elements.form.elements.namedItem('type').addEventListener('change', () => {
			try {
				setFormValue(elements.form, 'config_json', '{}');
				setFormValue(elements.form, 'docks_json', '{}');
				renderResourceEditor(elements.form);
			} catch (error) {
				setEditorStatus(error && error.message ? error.message : String(error), 'error');
			}
		});
	}

	log('editor events bound');
}

function createPresetActionsPlugin() {
	return {
		name: 'agentComponentPresetActions',

		layoutContributions() {
			return [
				{
					zone: 'topLine',
					order: 5,
					render() {
						const wrapper = document.createElement('div');
						wrapper.className = 'agent-component-preset-step5-top-actions';

						const addButton = document.createElement('button');
						addButton.type = 'button';
						addButton.className = 'agent-component-preset-step5-button agent-component-preset-step5-button-primary';
						addButton.textContent = mbText('add_preset', 'Add preset');
						addButton.addEventListener('click', () => openNewPresetEditor());

						const reloadButton = document.createElement('button');
						reloadButton.type = 'button';
						reloadButton.className = 'agent-component-preset-step5-button';
						reloadButton.textContent = mbText('reload_defaults', 'Reload defaults');
						reloadButton.addEventListener('click', () => reloadPresetDefaults());

						wrapper.appendChild(addButton);
						wrapper.appendChild(reloadButton);

						return wrapper;
					}
				}
			];
		}
	};
}

async function initGrid(modularGridModule) {
	log('initGrid start');

	let editorInitializationError = '';

	try {
		const modularDialogModule = await importFirst(MODULARDIALOG_URL, 'ModularDialog');
		initEditorDialog(modularDialogModule);
		bindEditorEvents();
	} catch (error) {
		console.error('Agent Component Preset editor dialog failed:', error);
		editorInitializationError = 'Preset editor failed: ' + getText(error && error.message ? error.message : error);
		setLog(editorInitializationError);
	}

	const {
		AjaxAdapter,
		ColumnVisibilityPlugin,
		FiltersPlugin,
		HeaderMenuPlugin,
		InfoPlugin,
		InfiniteScrollPlugin,
		ModularGrid,
		ResetPlugin,
		RowActionsPlugin,
		RowDetailPlugin,
		SearchPlugin
	} = modularGridModule;

	log('selected exports', {
		AjaxAdapter: !!AjaxAdapter,
		ColumnVisibilityPlugin: !!ColumnVisibilityPlugin,
		FiltersPlugin: !!FiltersPlugin,
		HeaderMenuPlugin: !!HeaderMenuPlugin,
		InfoPlugin: !!InfoPlugin,
		InfiniteScrollPlugin: !!InfiniteScrollPlugin,
		ModularGrid: !!ModularGrid,
		ResetPlugin: !!ResetPlugin,
		RowActionsPlugin: !!RowActionsPlugin,
		RowDetailPlugin: !!RowDetailPlugin,
		SearchPlugin: !!SearchPlugin
	});

	if (!AjaxAdapter || !ModularGrid) {
		throw new Error('ModularGrid module was loaded, but AjaxAdapter or ModularGrid export is missing.');
	}

	const adapter = new AjaxAdapter({
		url: ENDPOINT_URL,
		method: 'POST',
		rowsPath: 'data',
		totalPath: 'total',
		mapRequest(request) {
			const state = grid ? grid.getState() : {};
			const filters = buildFilterPayload(state.filters || {});
			const sortKey = request.sortKey || 'preset_id';
			const sortDirection = request.sortDirection || 'asc';
			const payload = {
				mode: 'page',
				page: request.page || 1,
				pageSize: request.pageSize || BATCH_SIZE,
				search: request.search || '',
				sort: [
					{
						key: sortKey,
						dir: sortDirection,
						type: SORT_TYPES[sortKey] || 'string'
					}
				],
				filters
			};

			log('mapRequest payload', payload);

			return payload;
		}
	});

	log('adapter created');

	grid = new ModularGrid(GRID_SELECTOR, {
			strings: mbStringSet('cs_grid_'),
		layout,
		adapter,
		dataMode: 'server',
		server: {
			searchDebounceMs: 220,
			watchStateKeys: ['query', 'filters']
		},
		features: {
			paging: false
		},
		pageSize: BATCH_SIZE,
		sort: {
			key: 'preset_id',
			direction: 'asc'
		},
		plugins: [
			createPresetActionsPlugin(),
			SearchPlugin,
			FiltersPlugin,
			HeaderMenuPlugin,
			InfoPlugin,
			ColumnVisibilityPlugin,
			ResetPlugin,
			RowActionsPlugin,
			RowDetailPlugin,
			InfiniteScrollPlugin
		].filter(Boolean),
		pluginOptions: {
			search: {
				zone: 'topLine',
				order: 10,
				label: mbText('search', 'Search'),
				placeholder: mbText('search_preset_id_label_or_type', 'Search preset id, label or type')
			},
			filters: {
				zone: 'topLine2',
				order: 10,
				stateKey: 'filters',
				showClearButton: true,
				clearLabel: 'Clear filters',
				fields: [
					{
						key: 'type',
						label: mbText('type', 'Type'),
						type: 'select',
						options: RESOURCE_TYPE_FILTER_OPTIONS
					},
					{
						key: 'capability',
						label: mbText('capability', 'Capability'),
						type: 'select',
						options: CAPABILITY_FILTER_OPTIONS
					},
					{
						key: 'category',
						label: mbText('category', 'Category'),
						type: 'select',
						options: CATEGORY_FILTER_OPTIONS
					},
					{
						key: 'status',
						label: mbText('status', 'Status'),
						type: 'select',
						options: STATUS_FILTER_OPTIONS
					},
					{
						key: 'enabled',
						label: mbText('state', 'State'),
						type: 'select',
						options: ENABLED_FILTER_OPTIONS
					}
				]
			},
			headerMenu: {
				showSortActions: true,
				showClearSortAction: true,
				showHideColumnAction: true
			},
			columnVisibility: {
				zone: ''
			},
			reset: {
				zone: 'topLine',
				order: 20,
				label: mbText('reset', 'Reset'),
				sections: ['query', 'filters', 'columns', 'detailView']
			},
			info: {
				zone: 'statusZone',
				order: 10,
				displayMode: 'loaded'
			},
			rowActions: {
				headerMenu: {
					enabled: true,
					buttonLabel: '...',
					items: [
						{
							type: 'columnVisibility',
							label: mbText('columns', 'Columns'),
							showReset: true,
							resetLabel: 'Reset columns'
						}
					]
				},
				items: [
					{
						key: 'edit-preset',
						label: mbText('edit_preset', 'Edit preset'),
						onClick(context) {
							openEditorFromRow(context && context.row ? context.row : null);
						}
					},
					{
						key: 'duplicate-preset',
						label: mbText('duplicate_preset', 'Duplicate preset'),
						onClick(context) {
							openDuplicateEditorFromRow(context && context.row ? context.row : null);
						}
					},
					{
						key: 'delete-preset',
						label: mbText('delete_preset', 'Delete preset'),
						onClick(context) {
							deletePresetFromRow(context && context.row ? context.row : null);
						}
					}
				]
			},
			rowDetail: {
				rowIdKey: 'preset_id',
				clearOnDataReload: true,
				asyncDetail: {
					load(context) {
						log('row detail load', context && context.row ? context.row : null);
						return loadRemoteRecord(context.row);
					},
					renderLoading(context) {
						return createDetailLoadingPlaceholder(context.row);
					},
					renderError(context) {
						return createDetailErrorPlaceholder(context.row, context.error);
					},
					render(context) {
						log('row detail render', context && context.payload ? context.payload.preset_id || context.payload.id : null);
						return renderPresetDetail(context);
					}
				}
			},
			infiniteScroll: {
				threshold: 180,
				pageSize: BATCH_SIZE,
				containerSelector: '.mg-table-scroll'
			}
		},
		columns: [
			{
				key: 'preset_id',
				label: mbText('preset', 'Preset'),
				width: 300,
				headerMenu: {
					defaultSortKey: 'preset_id',
					defaultSortDirection: 'asc',
					sortOptions: [
						{ key: 'preset_id', label: mbText('preset_id', 'Preset ID') },
						{ key: 'label', label: mbText('label', 'Label') }
					]
				},
				render(value, row) {
					return renderPreset(value, row);
				}
			},
			{
				key: 'type',
				label: mbText('type', 'Type'),
				width: 300,
				headerMenu: {
					defaultSortKey: 'type',
					defaultSortDirection: 'asc',
					sortOptions: [
						{ key: 'type', label: mbText('type', 'Type') },
						{ key: 'category', label: mbText('category', 'Category') }
					]
				},
				render(value, row) {
					return renderType(value, row);
				}
			},
			{
				key: 'capability_text',
				label: mbText('capabilities', 'Capabilities'),
				width: 240,
				headerMenu: {
					defaultSortKey: 'capability_text',
					defaultSortDirection: 'asc',
					sortOptions: [
						{ key: 'capability_text', label: mbText('capabilities', 'Capabilities') },
						{ key: 'interface_text', label: mbText('interfaces', 'Interfaces') }
					]
				},
				render(value, row) {
					return renderCapabilities(value, row);
				}
			},
			{
				key: 'enabled_label',
				label: mbText('enabled', 'Enabled'),
				width: 120,
				visible: true,
				render(value) {
					return renderPills(value);
				}
			},
			{
				key: 'status',
				label: mbText('status', 'Status'),
				width: 160,
				headerMenu: {
					defaultSortKey: 'status',
					defaultSortDirection: 'asc',
					sortOptions: [
						{ key: 'status', label: mbText('status', 'Status') }
					]
				}
			},
			{
				key: 'category',
				label: mbText('category', 'Category'),
				width: 160,
				visible: false
			},
			{
				key: 'risk',
				label: mbText('risk', 'Risk'),
				width: 220,
				visible: false,
				textDisplay: {
					strategy: 'clamp',
					lines: 2,
					expandable: true
				}
			},
			{
				key: 'description',
				label: mbText('description', 'Description'),
				width: 380,
				visible: false,
				textDisplay: {
					strategy: 'clamp',
					lines: 3,
					expandable: true
				}
			},
			{
				key: 'config_count',
				label: mbText('config', 'Config'),
				width: 110,
				visible: false
			},
			{
				key: 'dock_count',
				label: mbText('docks', 'Docks'),
				width: 110,
				visible: false
			}
		]
	});

	log('grid created');

	grid.on('data:loaded', (event) => {
		log('event data:loaded', event);
	});

	grid.on('data:appended', (event) => {
		log('event data:appended', event);
	});

	grid.on('detail:loaded', (event) => {
		log('event detail:loaded', event);
		setLog(mbText('loaded_detail_for_prefix', 'Loaded detail for ') + getText(event && event.rowId));
	});

	grid.on('detail:error', (event) => {
		log('event detail:error', event);
		setLog(mbText('failed_to_load_detail_prefix', 'Failed to load detail: ') + getText(event && event.error));
	});

	log('grid.init start');
	await grid.init();
	log('grid.init finished');
	if (editorInitializationError !== '') {
		setLog(editorInitializationError);
		return;
	}

	setLog(mbText('agent_component_preset_admin_loaded_column_visibility_and_infinite_scroll_are_enabled', 'Agent Component Preset Admin loaded. Column visibility and infinite scroll are enabled.'));
	if (OPEN_PRESET_ID !== '') {
		await openEditorById(OPEN_PRESET_ID);
	}
}

(async function() {
	const root = document.querySelector(GRID_SELECTOR);

	log('bootstrap start', {
		rootFound: !!root,
		initialized: root ? root.dataset.initialized || '' : null,
		endpoint: ENDPOINT_URL,
		modularGridUrl: MODULARGRID_URL,
		modularDialogUrl: MODULARDIALOG_URL
	});

	if (!root || root.dataset.initialized === '1') {
		return;
	}

	root.dataset.initialized = '1';
	setStartupStatus(mbText('loading_modulargrid_module', 'Loading ModularGrid module.'));

	try {
		const modularGridModule = await importFirst(MODULARGRID_URL, 'ModularGrid');
		setStartupStatus(mbText('initializing_preset_grid', 'Initializing preset grid.'));
		await initGrid(modularGridModule);
	} catch (error) {
		const message = error && error.message ? error.message : String(error);
		setStartupStatus(mbText('agent_component_preset_admin_could_not_be_initialized', 'Agent Component Preset Admin could not be initialized.'), message, true);
		setLog(mbText('initialization_failed_prefix', 'Initialization failed: ') + message);
		console.error(error);
	}
})();

	})();
</script>
