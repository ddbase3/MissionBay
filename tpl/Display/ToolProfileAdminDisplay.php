<?php
$resolve = $this->_['resolve'];

$serviceUrl = (string) ($this->_['service'] ?? '');
$settingsGroup = (string) ($this->_['settings_group'] ?? 'tool-profile');
$toolPresetOptions = is_array($this->_['tool_preset_options'] ?? null) ? $this->_['tool_preset_options'] : [];
$typeOptions = ['mcp'];
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
	.tool-profile-admin-shell {
		max-width: 1700px;
	}

	.tool-profile-admin-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.tool-profile-admin-shell p {
		margin: 0 0 16px 0;
		max-width: 1120px;
		color: #555;
		line-height: 1.45;
	}

	.tool-profile-admin-grid .tool-profile-admin-panel {
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

	.tool-profile-admin-grid .tool-profile-admin-panel--filters {
		flex-wrap: wrap;
		align-items: flex-start;
		overflow-x: visible;
	}

	.tool-profile-admin-grid .tool-profile-admin-panel > * {
		flex: 0 0 auto;
	}

	.tool-profile-admin-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.tool-profile-admin-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.tool-profile-admin-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.tool-profile-admin-grid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.tool-profile-admin-grid .mg-input,
	.tool-profile-admin-grid .mg-select,
	.tool-profile-admin-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.tool-profile-admin-grid input[type="search"].mg-input {
		width: 300px;
	}

	.tool-profile-admin-grid .mg-select {
		width: auto;
		min-width: 96px;
	}

	.tool-profile-admin-grid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.tool-profile-admin-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.tool-profile-admin-grid .mg-table thead th.mg-cell-pinned {
		z-index: 14;
	}

	.tool-profile-admin-grid .mg-table th,
	.tool-profile-admin-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.tool-profile-admin-grid .mg-row-actions-cell,
	.tool-profile-admin-grid .mg-row-actions-header {
		width: 54px;
		min-width: 54px;
		text-align: center;
	}

	.tool-profile-admin-top-actions {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		flex: 0 0 auto;
	}

	.tool-profile-admin-cell-stack {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.tool-profile-admin-cell-main {
		font-weight: 600;
		color: #222;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.tool-profile-admin-cell-sub {
		font-size: 12px;
		color: #666;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.tool-profile-admin-pill-row {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		align-items: center;
	}

	.tool-profile-admin-pill {
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

	.tool-profile-admin-pill-enabled,
	.tool-profile-admin-pill-mcp {
		background: #eef7ee;
		border-color: #bddfbd;
	}

	.tool-profile-admin-pill-disabled {
		background: #f5eeee;
		border-color: #e2c5c5;
		color: #7a3333;
	}

	.tool-profile-admin-button {
		appearance: none;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		color: #222;
		cursor: pointer;
		font: inherit;
		font-size: 13px;
		line-height: 1.3;
		min-height: 28px;
		padding: 4px 10px;
		white-space: nowrap;
	}

	.tool-profile-admin-button:hover {
		background: #f5f5f5;
	}

	.tool-profile-admin-button-primary {
		background: #2f5d91;
		border-color: #2f5d91;
		color: #fff;
	}

	.tool-profile-admin-button-primary:hover {
		background: #284f7c;
	}

	.tool-profile-admin-startup,
	.tool-profile-admin-status {
		margin-top: 12px;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.tool-profile-admin-startup {
		margin-top: 0;
	}

	.tool-profile-admin-startup-error {
		border-color: #e4b9b9;
		background: #fff0f0;
		color: #8a1f1f;
	}

	.tool-profile-admin-startup pre {
		margin: 8px 0 0 0;
		white-space: pre-wrap;
		word-break: break-word;
	}

	.tool-profile-admin-status strong {
		color: #222;
	}

	.tool-profile-admin-log-details {
		margin-top: 8px;
	}

	.tool-profile-admin-log {
		max-height: 180px;
		overflow: auto;
		padding: 8px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fafafa;
		font-size: 12px;
		white-space: pre-wrap;
	}

	.tool-profile-admin-detail {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		gap: 12px;
		padding: 12px;
		background: #fafafa;
		border-top: 1px solid #e7e7e7;
	}

	.tool-profile-admin-detail-card {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 10px;
		min-width: 0;
	}

	.tool-profile-admin-detail-title {
		font-weight: 600;
		margin-bottom: 8px;
		color: #222;
	}

	.tool-profile-admin-detail-row {
		display: grid;
		grid-template-columns: 120px minmax(0, 1fr);
		gap: 8px;
		margin: 0 0 5px 0;
		font-size: 13px;
	}

	.tool-profile-admin-detail-key {
		color: #666;
	}

	.tool-profile-admin-detail-value {
		overflow-wrap: anywhere;
	}

	.tool-profile-admin-json {
		margin: 0;
		max-height: 320px;
		overflow: auto;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.45;
		white-space: pre-wrap;
		word-break: break-word;
	}

	.tool-profile-admin-dialog-surface {
		width: min(760px, 100%);
		max-height: min(760px, 100%);
	}

	.tool-profile-admin-dialog-surface .md-shell-body {
		display: grid;
		gap: 12px;
	}

	.tool-profile-admin-dialog-surface .md-close-button {
		width: auto;
		min-height: 28px;
		padding: 4px 10px;
		font-size: 13px;
		line-height: 1.3;
	}

	.tool-profile-admin-editor-content {
		display: grid;
		gap: 12px;
		min-width: 0;
	}

	.tool-profile-admin-form {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 12px;
	}

	.tool-profile-admin-field-full {
		grid-column: 1 / -1;
	}

	.tool-profile-admin-label {
		display: block;
		margin-bottom: 5px;
		color: #555;
		font-size: 12px;
		font-weight: 600;
		line-height: 1.3;
	}

	.tool-profile-admin-input,
	.tool-profile-admin-select {
		width: 100%;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		color: #222;
		font: inherit;
		font-size: 13px;
		line-height: 1.4;
		padding: 7px 9px;
	}

	.tool-profile-admin-token-row {
		display: flex;
		gap: 8px;
		align-items: stretch;
	}

	.tool-profile-admin-token-row .tool-profile-admin-input {
		flex: 1 1 auto;
		min-width: 0;
	}

	.tool-profile-admin-token-row .tool-profile-admin-button {
		flex: 0 0 auto;
		min-height: 34px;
	}

	.tool-profile-admin-checkbox-list {
		display: grid;
		gap: 4px;
		max-height: 280px;
		overflow: auto;
		padding: 8px;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
	}

	.tool-profile-admin-tool-checkbox {
		display: flex;
		align-items: flex-start;
		gap: 8px;
		padding: 5px 6px;
		border-radius: 4px;
		cursor: pointer;
		font-size: 13px;
		line-height: 1.35;
	}

	.tool-profile-admin-tool-checkbox:hover {
		background: #f6f6f6;
	}

	.tool-profile-admin-tool-checkbox input {
		margin-top: 2px;
		flex: 0 0 auto;
	}

	.tool-profile-admin-tool-checkbox-text {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.tool-profile-admin-tool-checkbox-main {
		font-weight: 600;
		color: #222;
	}

	.tool-profile-admin-tool-checkbox-sub {
		font-size: 12px;
		color: #666;
		overflow-wrap: anywhere;
	}

	.tool-profile-admin-checkbox-row {
		display: flex;
		align-items: center;
		gap: 6px;
		min-height: 34px;
		font-size: 13px;
		color: #333;
	}

	.tool-profile-admin-form-hint {
		margin-top: 4px;
		color: #666;
		font-size: 12px;
		line-height: 1.35;
	}

	@media (max-width: 900px) {
		.tool-profile-admin-form,
		.tool-profile-admin-detail {
			grid-template-columns: 1fr;
		}

		.tool-profile-admin-grid .mg-table-scroll {
			height: 420px;
		}
	}
</style>

<div class="tool-profile-admin-shell">
	<h1>Tool Profiles</h1>
	<p>
		Manage simple tool profiles. A profile only stores a named list of already configured tool preset ids.
	</p>

	<div class="tool-profile-admin-grid">
		<div id="tool-profile-admin-grid" class="tool-profile-admin-grid-shell">
			<div class="tool-profile-admin-startup">Loading Tool Profile Admin display...</div>
		</div>
		<div id="tool-profile-admin-output" class="tool-profile-admin-status"><strong>Last action:</strong> Waiting for initialization.</div>
		<details class="tool-profile-admin-log-details">
			<summary>Debug log</summary>
			<pre id="tool-profile-admin-log" class="tool-profile-admin-log">Status log will appear here.</pre>
		</details>
	</div>
</div>

<template id="tool-profile-admin-editor-template">
	<div id="tool-profile-admin-editor-content" class="tool-profile-admin-editor-content">
		<form id="tool-profile-admin-form" class="tool-profile-admin-form">
			<input type="hidden" name="old_id" />

			<div>
				<label class="tool-profile-admin-label">Profile ID</label>
				<input type="text" name="id" class="tool-profile-admin-input" />
			</div>

			<div>
				<label class="tool-profile-admin-label">Label</label>
				<input type="text" name="label" class="tool-profile-admin-input" />
			</div>

			<div class="tool-profile-admin-field-full">
				<label class="tool-profile-admin-label">Description</label>
				<textarea name="description" class="tool-profile-admin-input" rows="3"></textarea>
				<div class="tool-profile-admin-form-hint">Short description exposed as missionbay://profile resource. Do not include secrets.</div>
			</div>

			<div>
				<label class="tool-profile-admin-label">Type</label>
				<select name="type" class="tool-profile-admin-select">
<?php foreach($typeOptions as $typeOption): ?>
					<option value="<?php echo $e($typeOption); ?>"><?php echo $e($typeOption); ?></option>
<?php endforeach; ?>
				</select>
			</div>

			<div>
				<label class="tool-profile-admin-label">Enabled</label>
				<label class="tool-profile-admin-checkbox-row"><input type="checkbox" name="enabled" value="1" /> enabled</label>
			</div>

			<div class="tool-profile-admin-field-full">
				<label class="tool-profile-admin-label">Bearer token</label>
				<div class="tool-profile-admin-token-row">
					<input type="text" name="token" class="tool-profile-admin-input" autocomplete="off" spellcheck="false" />
					<button type="button" class="tool-profile-admin-button" data-action="generate-token">Generate</button>
					<button type="button" class="tool-profile-admin-button" data-action="copy-token">Copy</button>
				</div>
				<div class="tool-profile-admin-form-hint">Used as Authorization: Bearer token for this profile.</div>
			</div>

			<div class="tool-profile-admin-field-full">
				<label class="tool-profile-admin-label">Tool presets</label>
				<div class="tool-profile-admin-checkbox-list" data-tool-checkbox-list>
<?php foreach($toolPresetOptions as $toolPresetOption): ?>
<?php
	$toolPresetId = is_array($toolPresetOption) ? (string)($toolPresetOption['id'] ?? '') : (string)$toolPresetOption;
	$toolPresetLabel = is_array($toolPresetOption) ? (string)($toolPresetOption['label'] ?? $toolPresetId) : $toolPresetId;
	$toolPresetType = is_array($toolPresetOption) ? (string)($toolPresetOption['type'] ?? '') : '';
	$toolPresetEnabled = !is_array($toolPresetOption) || (bool)($toolPresetOption['enabled'] ?? true);
	if($toolPresetId === '') {
		continue;
	}
	$subText = $toolPresetId;
	if($toolPresetType !== '') {
		$subText .= ' - ' . $toolPresetType;
	}
	if(!$toolPresetEnabled) {
		$subText .= ' - disabled';
	}
?>
					<label class="tool-profile-admin-tool-checkbox">
						<input type="checkbox" name="tools[]" value="<?php echo $e($toolPresetId); ?>" />
						<span class="tool-profile-admin-tool-checkbox-text">
							<span class="tool-profile-admin-tool-checkbox-main"><?php echo $e($toolPresetLabel); ?></span>
							<span class="tool-profile-admin-tool-checkbox-sub"><?php echo $e($subText); ?></span>
						</span>
					</label>
<?php endforeach; ?>
				</div>
				<div class="tool-profile-admin-form-hint">Select the tool presets that belong to this profile. The profile stores preset ids only.</div>
			</div>
		</form>
	</div>
</template>

<script>
	(function() {
console.log('[ToolProfileAdmin] script entered');

const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const MODULARGRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const MODULARDIALOG_URL = <?php echo json_encode($modularDialogJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const TOOL_PRESET_OPTIONS = <?php echo json_encode($toolPresetOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const TYPE_FILTER_OPTIONS = [
	{ value: '', label: 'All types' },
	{ value: 'mcp', label: 'mcp' }
];
const ENABLED_FILTER_OPTIONS = [
	{ value: '', label: 'All states' },
	{ value: '1', label: 'Enabled' },
	{ value: '0', label: 'Disabled' }
];
const GRID_SELECTOR = '#tool-profile-admin-grid';
const LOG_SELECTOR = '#tool-profile-admin-log';
const OUTPUT_SELECTOR = '#tool-profile-admin-output';
const BATCH_SIZE = 50;
const SORT_TYPES = {
	profile_id: 'string',
	label: 'string',
	type: 'string',
	enabled_label: 'string',
	tool_count: 'int',
	tool_text: 'string'
};

let grid = null;
let editorDialog = null;
let editorContent = null;
let currentEditorProfileId = '';

const layout = {
	type: 'stack',
	className: 'mg-layout-root',
	children: [
		{
			type: 'zone',
			key: 'topLine',
			className: 'tool-profile-admin-panel tool-profile-admin-panel--main'
		},
		{
			type: 'zone',
			key: 'topLine2',
			className: 'tool-profile-admin-panel tool-profile-admin-panel--filters'
		},
		{
			type: 'view',
			key: 'main',
			className: 'tool-profile-admin-main'
		},
		{
			type: 'zone',
			key: 'statusZone',
			className: 'tool-profile-admin-panel'
		}
	]
};

function log(label, value = undefined) {
	const message = value === undefined ? String(label) : String(label) + ' ' + stringifyJson(value);
	const logElement = document.querySelector(LOG_SELECTOR);

	console.log('[ToolProfileAdmin]', label, value === undefined ? '' : value);

	if (logElement) {
		logElement.textContent = (logElement.textContent === 'Status log will appear here.' ? '' : logElement.textContent + '\n') + message;
	}
}

function setLog(message) {
	const output = document.querySelector(OUTPUT_SELECTOR);

	if (!output) {
		return;
	}

	output.innerHTML = '';
	const label = document.createElement('strong');
	label.textContent = 'Last action:';
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

function getText(value, placeholder = '-') {
	if (value === null || value === undefined || value === '') {
		return placeholder;
	}

	return String(value);
}

function createElement(className = '', text = '') {
	const element = document.createElement('div');

	if (className) {
		element.className = className;
	}

	if (text !== '') {
		element.textContent = String(text);
	}

	return element;
}

function createButton(className, text) {
	const button = document.createElement('button');
	button.type = 'button';
	button.className = className;
	button.textContent = text;

	return button;
}

function createPill(text, extraClass = '') {
	const pill = document.createElement('span');
	pill.className = ('tool-profile-admin-pill ' + extraClass).trim();
	pill.textContent = getText(text);

	return pill;
}

function renderPills(value) {
	const wrapper = createElement('tool-profile-admin-pill-row');
	const items = Array.isArray(value)
		? value
		: String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

	if (items.length === 0) {
		wrapper.appendChild(createPill('-'));
		return wrapper;
	}

	items.forEach((item) => wrapper.appendChild(createPill(item, 'tool-profile-admin-pill-' + String(item).toLowerCase())));

	return wrapper;
}

function renderProfile(value, row) {
	const wrapper = createElement('tool-profile-admin-cell-stack');
	const main = createElement('tool-profile-admin-cell-main', getText(row.label || row.profile_id));
	const sub = createElement('tool-profile-admin-cell-sub', getText(row.profile_id));

	wrapper.appendChild(main);
	wrapper.appendChild(sub);

	return wrapper;
}

function renderType(value, row) {
	const wrapper = createElement('tool-profile-admin-cell-stack');
	const main = renderPills(row.type || value);
	const sub = createElement('tool-profile-admin-cell-sub', getText(row.enabled_label));

	wrapper.appendChild(main);
	wrapper.appendChild(sub);

	return wrapper;
}

function renderTools(value, row) {
	const wrapper = createElement('tool-profile-admin-cell-stack');
	const main = createElement('tool-profile-admin-cell-main', String(row.tool_count || 0) + ' tool preset' + (Number(row.tool_count || 0) === 1 ? '' : 's'));
	const sub = createElement('tool-profile-admin-cell-sub', getText(row.tool_text, 'No tool presets selected'));

	wrapper.appendChild(main);
	wrapper.appendChild(sub);

	return wrapper;
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

function generateMcpToken() {
	const bytes = new Uint8Array(32);

	if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
		window.crypto.getRandomValues(bytes);
	} else {
		for (let i = 0; i < bytes.length; i++) {
			bytes[i] = Math.floor(Math.random() * 256);
		}
	}

	return 'mb-mcp-' + Array.from(bytes).map((byte) => byte.toString(16).padStart(2, '0')).join('');
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

async function postJson(payload) {
	log('POST JSON start', payload);

	/*
	 * CRITICAL: Do not change this request contract.
	 * ModularGrid and the BASE3/ILIAS endpoint currently rely on this exact fetch setup:
	 * POST + Content-Type application/json + JSON.stringify(payload).
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
		setLog('Grid is not initialized; refresh skipped.');
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

	setLog('Grid refresh is not available. Please refresh the page manually.');
}

function getProfileIdFromRow(row) {
	if (!row || typeof row !== 'object') {
		return '';
	}

	return String(row.profile_id || row.id || '').trim();
}

async function loadRemoteRecord(row) {
	const id = getProfileIdFromRow(row);

	if (!id) {
		throw new Error('Missing profile id for detail request.');
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
	return createElement('tool-profile-admin-startup', 'Loading record for ' + getText(getProfileIdFromRow(row)) + '...');
}

function createDetailErrorPlaceholder(row, error) {
	return createElement('tool-profile-admin-startup tool-profile-admin-startup-error', 'Failed to load record for ' + getText(getProfileIdFromRow(row)) + ': ' + getText(error && error.message ? error.message : error));
}

function createDetailRow(key, value) {
	const row = createElement('tool-profile-admin-detail-row');
	const keyElement = createElement('tool-profile-admin-detail-key', key);
	const valueElement = createElement('tool-profile-admin-detail-value', getText(value));

	row.appendChild(keyElement);
	row.appendChild(valueElement);

	return row;
}

function renderProfileDetail(context) {
	const record = context && context.payload ? context.payload : null;

	if (!record || typeof record !== 'object') {
		return createElement('tool-profile-admin-startup tool-profile-admin-startup-error', 'No profile detail payload returned.');
	}

	const wrapper = createElement('tool-profile-admin-detail');
	const left = createElement('tool-profile-admin-detail-card');
	const right = createElement('tool-profile-admin-detail-card');
	const pre = document.createElement('pre');

	left.appendChild(createElement('tool-profile-admin-detail-title', getText(record.label || record.profile_id)));
	left.appendChild(createDetailRow('ID', record.profile_id || record.id));
	left.appendChild(createDetailRow('Type', record.type));
	left.appendChild(createDetailRow('Enabled', record.enabled ? 'yes' : 'no'));
	left.appendChild(createDetailRow('Token', record.token_configured ? 'configured' : 'missing'));
	left.appendChild(createDetailRow('Tools', record.tool_text));

	right.appendChild(createElement('tool-profile-admin-detail-title', 'Record JSON'));
	pre.className = 'tool-profile-admin-json';
	pre.textContent = record.profile_json || stringifyJson(record);
	right.appendChild(pre);

	wrapper.appendChild(left);
	wrapper.appendChild(right);

	return wrapper;
}

function createEditorContent() {
	if (editorContent) {
		return editorContent;
	}

	const template = document.getElementById('tool-profile-admin-editor-template');

	if (!template || !template.content) {
		throw new Error('Tool profile editor template not found.');
	}

	const fragment = template.content.cloneNode(true);
	const content = fragment.querySelector('#tool-profile-admin-editor-content');

	if (!content) {
		throw new Error('Tool profile editor content not found.');
	}

	editorContent = content;

	return editorContent;
}

function getEditorElements() {
	const root = editorContent;

	return {
		root,
		form: root ? root.querySelector('#tool-profile-admin-form') : null
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

function setToolCheckboxValues(form, values) {
	const selected = Array.isArray(values) ? values.map((value) => String(value || '').trim()).filter(Boolean) : [];
	const selectedSet = new Set(selected);

	form.querySelectorAll('input[name="tools[]"]').forEach((field) => {
		field.checked = selectedSet.has(String(field.value || '').trim());
	});
}

function buildEditorButtons(isExisting = false) {
	const buttons = [
		{
			key: 'copy-payload',
			label: 'Copy payload',
			async action() {
				await copyEditorPayload();
			}
		},
		{
			key: 'save',
			label: 'Save',
			primary: true,
			busyLabel: 'Saving...',
			async action() {
				await saveEditorPayload();
			}
		}
	];

	if (isExisting) {
		buttons.unshift({
			key: 'delete-current-profile',
			label: 'Delete',
			danger: true,
			busyLabel: 'Deleting...',
			async action() {
				await deleteCurrentProfileFromEditor();
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
		id: 'tool-profile-admin-editor-dialog',
		className: 'tool-profile-admin-dialog',
		surfaceClassName: 'tool-profile-admin-dialog-surface',
		size: 'large',
		title: 'Tool profile editor',
		content,
		status: 'Save is enabled.',
		closeButtonPlugin: {
			label: 'Close'
		},
		statusPlugin: {
			renderEmpty: false
		},
		buttons: buildEditorButtons()
	});

	editorDialog.on('afterClose', () => {
		currentEditorProfileId = '';
		setLog('Closed editor.');
	});

	editorDialog.init();

	return editorDialog;
}

function openProfileEditor(record) {
	const elements = getEditorElements();

	if (!editorDialog || !elements.form) {
		setLog('Tool profile editor is not available.');
		return;
	}

	const form = elements.form;
	form.reset();

	record = record && typeof record === 'object' ? record : {};

	const oldIdValue = Object.prototype.hasOwnProperty.call(record, 'old_id') ? record.old_id : (record.profile_id || record.id || '');
	currentEditorProfileId = String(oldIdValue || '').trim();
	setFormValue(form, 'old_id', oldIdValue);
	setFormValue(form, 'id', record.profile_id || record.id || '');
	setFormValue(form, 'label', record.label || '');
	setFormValue(form, 'description', record.description || '');
	setSelectValue(form, 'type', record.type || 'mcp');
	setFormValue(form, 'token', record.token || '');
	setToolCheckboxValues(form, Array.isArray(record.tools) ? record.tools : []);

	const enabled = form.elements.namedItem('enabled');
	if (enabled) {
		enabled.checked = record.enabled !== false;
	}

	editorDialog.execute('setTitle', record.profile_id || record.id ? 'Edit tool profile' : 'Add tool profile');
	editorDialog.execute('setButtons', buildEditorButtons(currentEditorProfileId !== ''));
	setEditorStatus('Editor opened. Save is enabled.', 'ok');
	editorDialog.open({ source: 'toolProfileEditor', record });

	window.setTimeout(() => {
		const idField = form.elements.namedItem('id');

		if (idField && idField.value === '') {
			idField.focus();
			return;
		}

		const labelField = form.elements.namedItem('label');
		if (labelField) {
			labelField.focus();
		}
	}, 0);

	setLog('Opened editor for ' + getText(record.profile_id || record.id, 'new profile'));
}

function openNewProfileEditor() {
	openProfileEditor({
		profile_id: '',
		id: '',
		label: '',
		description: '',
		type: 'mcp',
		enabled: true,
		token: '',
		tools: []
	});
}

async function openEditorFromRow(row) {
	if (!row) {
		openNewProfileEditor();
		return;
	}

	try {
		setLog('Loading profile ' + getText(getProfileIdFromRow(row)) + ' for editor...');
		const record = await loadRemoteRecord(row);
		openProfileEditor(record);
	} catch (error) {
		setLog('Failed to load profile for editor: ' + getText(error && error.message ? error.message : error));
	}
}

function createDuplicateProfileRecord(record) {
	record = record && typeof record === 'object' ? record : {};

	const sourceId = String(record.profile_id || record.id || '').trim();
	const sourceLabel = String(record.label || sourceId || '').trim();
	const duplicateId = sourceId ? sourceId + '_copy' : '';
	const duplicate = Object.assign({}, record);

	duplicate.old_id = '';
	duplicate.profile_id = duplicateId;
	duplicate.id = duplicateId;
	duplicate.label = sourceLabel ? 'Copy of ' + sourceLabel : '';

	return duplicate;
}

async function openDuplicateEditorFromRow(row) {
	if (!row) {
		return;
	}

	try {
		setLog('Loading profile ' + getText(getProfileIdFromRow(row)) + ' for duplication...');
		const record = await loadRemoteRecord(row);
		openProfileEditor(createDuplicateProfileRecord(record));
	} catch (error) {
		setLog('Failed to duplicate profile: ' + getText(error && error.message ? error.message : error));
	}
}

function closeProfileEditor() {
	if (!editorDialog) {
		return;
	}

	editorDialog.close({ source: 'toolProfileEditor' });
}

function getFormFieldValue(form, name) {
	const field = form.elements.namedItem(name);

	if (!field) {
		return '';
	}

	return String(field.value || '').trim();
}

function getSelectedTools(form) {
	return Array.from(form.querySelectorAll('input[name="tools[]"]:checked'))
		.map((field) => String(field.value || '').trim())
		.filter(Boolean);
}

function validateEditorRequiredFields(form) {
	const id = getFormFieldValue(form, 'id');
	const label = getFormFieldValue(form, 'label');
	const type = getFormFieldValue(form, 'type');

	if (!id) {
		throw new Error('Profile ID is required.');
	}

	if (!label) {
		throw new Error('Label is required.');
	}

	if (!type) {
		throw new Error('Type is required.');
	}
}

function buildEditorPayload(options = {}) {
	const settings = Object.assign({ validateRequired: false }, options || {});
	const elements = getEditorElements();
	const form = elements.form;

	if (!form) {
		throw new Error('Tool profile editor form not found.');
	}

	if (settings.validateRequired) {
		validateEditorRequiredFields(form);
	}

	return {
		mode: 'save',
		old_id: getFormFieldValue(form, 'old_id'),
		id: getFormFieldValue(form, 'id'),
		label: getFormFieldValue(form, 'label'),
		description: getFormFieldValue(form, 'description'),
		type: getFormFieldValue(form, 'type'),
		enabled: form.elements.namedItem('enabled').checked,
		token: getFormFieldValue(form, 'token'),
		tools: getSelectedTools(form)
	};
}

async function copyEditorPayload() {
	try {
		const payload = buildEditorPayload({ validateRequired: false });
		await copyText(stringifyJson(payload));
		setEditorStatus('Payload copied.', 'ok');
		setLog('Copied editor payload for ' + getText(payload.id, 'new profile'));
	} catch (error) {
		setEditorStatus(error && error.message ? error.message : String(error), 'error');
	}
}

async function saveEditorPayload() {
	try {
		const payload = buildEditorPayload({ validateRequired: true });

		setEditorStatus('Saving profile...', '');
		setLog('Saving profile ' + getText(payload.id, 'new profile'));

		const response = await postJson(payload);

		if (!response || !response.ok) {
			throw new Error(response && response.error ? response.error : 'Save failed.');
		}

		setEditorStatus('Profile saved. Updating grid...', 'ok');
		closeProfileEditor();
		await refreshGrid();

		const record = response.record || payload;
		setLog('Saved profile ' + getText(record.profile_id || record.id || payload.id, payload.id) + '.');
	} catch (error) {
		setEditorStatus(error && error.message ? error.message : String(error), 'error');
		setLog('Save failed: ' + getText(error && error.message ? error.message : error));
	}
}

async function deleteProfileById(id) {
	id = String(id || '').trim();

	if (!id) {
		throw new Error('Missing profile id.');
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

async function deleteCurrentProfileFromEditor() {
	const elements = getEditorElements();
	const form = elements.form;
	const id = form ? getFormFieldValue(form, 'old_id') || getFormFieldValue(form, 'id') : currentEditorProfileId;

	if (!id) {
		setEditorStatus('Cannot delete a profile without id.', 'error');
		return;
	}

	if (!window.confirm('Delete tool profile "' + id + '"?')) {
		return;
	}

	try {
		setEditorStatus('Deleting profile...', '');
		await deleteProfileById(id);
		closeProfileEditor();
		await refreshGrid();
		setLog('Deleted profile ' + id + '.');
	} catch (error) {
		setEditorStatus(error && error.message ? error.message : String(error), 'error');
		setLog('Delete failed: ' + getText(error && error.message ? error.message : error));
	}
}

async function deleteProfileFromRow(row) {
	const id = getProfileIdFromRow(row);

	if (!id) {
		setLog('Cannot delete row without profile id.');
		return;
	}

	if (!window.confirm('Delete tool profile "' + id + '"?')) {
		return;
	}

	try {
		setLog('Deleting profile ' + id + '...');
		await deleteProfileById(id);
		await refreshGrid();
		setLog('Deleted profile ' + id + '.');
	} catch (error) {
		setLog('Delete failed: ' + getText(error && error.message ? error.message : error));
	}
}

async function reloadProfileStore() {
	try {
		setLog('Reloading profile store...');
		const response = await postJson({ mode: 'reload' });

		if (!response || !response.ok) {
			throw new Error(response && response.error ? response.error : 'Reload failed.');
		}

		await refreshGrid();
		setLog('Profile store reloaded.');
	} catch (error) {
		setLog('Reload failed: ' + getText(error && error.message ? error.message : error));
	}
}

function bindEditorEvents() {
	const elements = getEditorElements();
	const form = elements.form;

	if (!form) {
		return;
	}

	form.addEventListener('keydown', (event) => {
		if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
			event.preventDefault();
			saveEditorPayload();
		}
	});

	form.addEventListener('click', async (event) => {
		const button = event.target && typeof event.target.closest === 'function' ? event.target.closest('[data-action]') : null;

		if (!button) {
			return;
		}

		const action = button.getAttribute('data-action');

		if (action === 'generate-token') {
			event.preventDefault();
			setFormValue(form, 'token', generateMcpToken());
			setEditorStatus('Token generated. Save the profile to keep it.', 'ok');
			return;
		}

		if (action === 'copy-token') {
			event.preventDefault();
			await copyText(getFormFieldValue(form, 'token'));
			setEditorStatus('Token copied.', 'ok');
		}
	});
}

function createProfileActionsPlugin() {
	return {
		name: 'toolProfileActions',

		layoutContributions() {
			return [
				{
					zone: 'topLine',
					order: 5,
					render() {
						const wrapper = document.createElement('div');
						wrapper.className = 'tool-profile-admin-top-actions';

						const addButton = createButton('tool-profile-admin-button tool-profile-admin-button-primary', 'Add profile');
						addButton.addEventListener('click', () => openNewProfileEditor());

						const reloadButton = createButton('tool-profile-admin-button', 'Reload');
						reloadButton.addEventListener('click', () => reloadProfileStore());

						wrapper.appendChild(addButton);
						wrapper.appendChild(reloadButton);

						return wrapper;
					}
				}
			];
		}
	};
}

function setStartupStatus(message, details = '', isError = false) {
	const root = document.querySelector(GRID_SELECTOR);

	log('startup: ' + message, details || undefined);

	if (!root) {
		return;
	}

	const box = createElement('tool-profile-admin-startup' + (isError ? ' tool-profile-admin-startup-error' : ''));
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

async function initGrid(modularGridModule) {
	log('initGrid start');

	let editorInitializationError = '';

	try {
		const modularDialogModule = await importFirst(MODULARDIALOG_URL, 'ModularDialog');
		initEditorDialog(modularDialogModule);
		bindEditorEvents();
	} catch (error) {
		console.error('Tool Profile editor dialog failed:', error);
		editorInitializationError = 'Tool profile editor failed: ' + getText(error && error.message ? error.message : error);
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
			const sortKey = request.sortKey || 'profile_id';
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
			key: 'profile_id',
			direction: 'asc'
		},
		plugins: [
			createProfileActionsPlugin(),
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
				label: 'Search',
				placeholder: 'Search profile id, label or tools'
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
						label: 'Type',
						type: 'select',
						options: TYPE_FILTER_OPTIONS
					},
					{
						key: 'enabled',
						label: 'State',
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
				label: 'Reset',
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
							label: 'Columns',
							showReset: true,
							resetLabel: 'Reset columns'
						}
					]
				},
				items: [
					{
						key: 'edit-profile',
						label: 'Edit profile',
						onClick(context) {
							openEditorFromRow(context && context.row ? context.row : null);
						}
					},
					{
						key: 'duplicate-profile',
						label: 'Duplicate profile',
						onClick(context) {
							openDuplicateEditorFromRow(context && context.row ? context.row : null);
						}
					},
					{
						key: 'delete-profile',
						label: 'Delete profile',
						onClick(context) {
							deleteProfileFromRow(context && context.row ? context.row : null);
						}
					}
				]
			},
			rowDetail: {
				rowIdKey: 'profile_id',
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
						log('row detail render', context && context.payload ? context.payload.profile_id || context.payload.id : null);
						return renderProfileDetail(context);
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
				key: 'profile_id',
				label: 'Profile',
				width: 320,
				headerMenu: {
					defaultSortKey: 'profile_id',
					defaultSortDirection: 'asc',
					sortOptions: [
						{ key: 'profile_id', label: 'Profile ID' },
						{ key: 'label', label: 'Label' }
					]
				},
				render(value, row) {
					return renderProfile(value, row);
				}
			},
			{
				key: 'type',
				label: 'Type',
				width: 180,
				headerMenu: {
					defaultSortKey: 'type',
					defaultSortDirection: 'asc',
					sortOptions: [
						{ key: 'type', label: 'Type' },
						{ key: 'enabled_label', label: 'State' }
					]
				},
				render(value, row) {
					return renderType(value, row);
				}
			},
			{
				key: 'token_configured_label',
				label: 'Token',
				width: 120,
				render(value) {
					return renderPills(value);
				}
			},
			{
				key: 'tool_text',
				label: 'Tool presets',
				width: 520,
				headerMenu: {
					defaultSortKey: 'tool_text',
					defaultSortDirection: 'asc',
					sortOptions: [
						{ key: 'tool_text', label: 'Tool presets' },
						{ key: 'tool_count', label: 'Tool count' }
					]
				},
				render(value, row) {
					return renderTools(value, row);
				}
			},
			{
				key: 'enabled_label',
				label: 'Enabled',
				width: 120,
				visible: false,
				render(value) {
					return renderPills(value);
				}
			},
			{
				key: 'tool_count',
				label: 'Tools',
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
		setLog('Loaded detail for ' + getText(event && event.rowId));
	});

	grid.on('detail:error', (event) => {
		log('event detail:error', event);
		setLog('Failed to load detail: ' + getText(event && event.error));
	});

	log('grid.init start');
	await grid.init();
	log('grid.init finished');
	if (editorInitializationError !== '') {
		setLog(editorInitializationError);
		return;
	}

	setLog('Tool Profile Admin loaded. Column visibility and infinite scroll are enabled.');
}

(async function() {
	const root = document.querySelector(GRID_SELECTOR);

	log('bootstrap start', {
		rootFound: !!root,
		initialized: root ? root.dataset.initialized || '' : null,
		endpoint: ENDPOINT_URL,
		modularGridUrl: MODULARGRID_URL,
		modularDialogUrl: MODULARDIALOG_URL,
		settingsGroup: <?php echo json_encode($settingsGroup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
		toolPresetCount: TOOL_PRESET_OPTIONS.length,
		renderedAt: <?php echo json_encode($timestamp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
	});

	if (!root || root.dataset.initialized === '1') {
		return;
	}

	root.dataset.initialized = '1';
	setStartupStatus('Loading ModularGrid module.');

	try {
		const modularGridModule = await importFirst(MODULARGRID_URL, 'ModularGrid');
		setStartupStatus('Initializing tool profile grid.');
		await initGrid(modularGridModule);
	} catch (error) {
		const message = error && error.message ? error.message : String(error);
		setStartupStatus('Tool Profile Admin could not be initialized.', message, true);
		setLog('Initialization failed: ' + message);
		console.error(error);
	}
})();

	})();
</script>
