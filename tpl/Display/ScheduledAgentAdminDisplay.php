<?php
	$resolve = $this->_['resolve'];
	$serviceUrl = (string)($this->_['service'] ?? '');
	$settingsGroup = (string)($this->_['settings_group'] ?? 'scheduled-agent');
	$policyOptions = is_array($this->_['policy_options'] ?? null) ? $this->_['policy_options'] : [];
	$defaultRecord = is_array($this->_['default_record'] ?? null) ? $this->_['default_record'] : [];
	$modularGridCssUrl = (string)$resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
	$modularGridJsUrl = (string)$resolve('plugin/ClientStack/assets/modulargrid/index.js');
	$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?php echo $e($modularGridCssUrl); ?>" />

<style>
	.scheduled-agent-admin-shell {
		max-width: 1700px;
	}

	.scheduled-agent-admin-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.scheduled-agent-admin-shell p {
		margin: 0 0 16px 0;
		max-width: 1120px;
		color: #555;
		line-height: 1.45;
	}

	.scheduled-agent-admin-grid .scheduled-agent-admin-panel {
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

	.scheduled-agent-admin-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.scheduled-agent-admin-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.scheduled-agent-admin-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.scheduled-agent-admin-grid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.scheduled-agent-admin-grid .mg-input,
	.scheduled-agent-admin-grid .mg-select,
	.scheduled-agent-admin-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.scheduled-agent-admin-grid input[type="search"].mg-input {
		width: 300px;
	}

	.scheduled-agent-admin-grid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.scheduled-agent-admin-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.scheduled-agent-admin-grid .mg-table th,
	.scheduled-agent-admin-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.scheduled-agent-admin-top-actions,
	.scheduled-agent-admin-detail-actions {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 8px;
	}

	.scheduled-agent-admin-button {
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

	.scheduled-agent-admin-button:hover {
		background: #f5f5f5;
	}

	.scheduled-agent-admin-button-primary {
		background: #222;
		border-color: #222;
		color: #fff;
	}

	.scheduled-agent-admin-button-primary:hover {
		background: #444;
	}

	.scheduled-agent-admin-button-danger {
		border-color: #c8a2a2;
		color: #8a1f1f;
	}

	.scheduled-agent-admin-button-danger:hover {
		background: #fff0f0;
	}

	.scheduled-agent-admin-status,
	.scheduled-agent-admin-output {
		margin-top: 12px;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.scheduled-agent-admin-status strong,
	.scheduled-agent-admin-output strong {
		color: #222;
	}

	.scheduled-agent-admin-startup {
		padding: 16px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.scheduled-agent-admin-startup-error {
		border-color: #e4b9b9;
		background: #fff8f8;
		color: #8a1f1f;
	}

	.scheduled-agent-admin-cell-stack {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.scheduled-agent-admin-cell-main {
		font-weight: 600;
		color: #222;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.scheduled-agent-admin-cell-sub {
		font-size: 12px;
		color: #666;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.scheduled-agent-admin-pill-row {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		align-items: center;
	}

	.scheduled-agent-admin-pill {
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

	.scheduled-agent-admin-detail {
		display: grid;
		grid-template-columns: minmax(320px, 1fr) minmax(360px, 1.15fr);
		gap: 14px;
		align-items: start;
		padding: 10px;
		background: #fbfbfb;
	}

	.scheduled-agent-admin-detail-card {
		min-width: 0;
		padding: 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
	}

	.scheduled-agent-admin-detail-title {
		margin: 0 0 6px 0;
		font-size: 15px;
		font-weight: 600;
		color: #222;
	}

	.scheduled-agent-admin-detail-row {
		display: grid;
		grid-template-columns: 120px minmax(0, 1fr);
		gap: 6px;
		margin: 0 0 6px 0;
		font-size: 13px;
	}

	.scheduled-agent-admin-detail-key {
		font-weight: 600;
		color: #444;
	}

	.scheduled-agent-admin-json,
	.scheduled-agent-admin-log {
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

	.scheduled-agent-admin-log {
		max-height: 260px;
	}

	.scheduled-agent-admin-log-details {
		margin-top: 10px;
	}

	.scheduled-agent-admin-log-details summary {
		padding: 7px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		cursor: pointer;
		font-size: 13px;
		color: #444;
	}

	.scheduled-agent-admin-modal-backdrop {
		position: fixed;
		inset: 0;
		z-index: 9000;
		display: none;
		align-items: center;
		justify-content: center;
		padding: 24px;
		background: rgba(0, 0, 0, 0.35);
	}

	.scheduled-agent-admin-modal-backdrop.is-open {
		display: flex;
	}

	.scheduled-agent-admin-modal {
		display: grid;
		grid-template-rows: auto 1fr auto;
		gap: 12px;
		width: min(1180px, 100%);
		max-height: min(900px, 100%);
		border: 1px solid #d6d6d6;
		border-radius: 8px;
		background: #fff;
		box-shadow: 0 16px 50px rgba(0, 0, 0, 0.20);
		padding: 16px;
	}

	.scheduled-agent-admin-modal-header,
	.scheduled-agent-admin-modal-footer {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
	}

	.scheduled-agent-admin-modal-footer {
		align-items: center;
	}

	.scheduled-agent-admin-modal-title {
		font-size: 18px;
		line-height: 1.25;
		font-weight: 600;
		color: #222;
	}

	.scheduled-agent-admin-modal-body {
		min-height: 0;
		overflow: auto;
	}

	.scheduled-agent-admin-form-header {
		display: grid;
		grid-template-columns: minmax(260px, 520px);
		gap: 12px;
		margin: 0 0 14px;
		padding: 12px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fbfbfb;
	}

	.scheduled-agent-admin-label {
		display: block;
		margin: 0 0 4px 0;
		font-size: 12px;
		font-weight: 600;
		color: #333;
	}

	.scheduled-agent-admin-input {
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

	.scheduled-agent-admin-help {
		margin: 5px 0 0;
		color: #666;
		font-size: 12px;
	}

	.scheduled-agent-admin-modal-status {
		min-height: 18px;
		font-size: 12px;
		color: #666;
	}

	.scheduled-agent-admin-modal-status-error {
		color: #8a1f1f;
	}

	.scheduled-agent-admin-modal-status-ok {
		color: #276028;
	}

	@media (max-width: 980px) {
		.scheduled-agent-admin-detail,
		.scheduled-agent-admin-form-header {
			grid-template-columns: 1fr;
		}
	}
</style>

<div class="scheduled-agent-admin-shell">
	<h1>Scheduled Agent Admin</h1>
	<p>
		Manage configured MissionBay agents that can be executed by worker timing policies. The editor uses the scheduled agent configuration form and stores records in SettingsStore group <code><?php echo $e($settingsGroup); ?></code>.
	</p>

	<div class="scheduled-agent-admin-grid">
		<div class="scheduled-agent-admin-top-actions">
			<button type="button" id="scheduled-agent-admin-add" class="scheduled-agent-admin-button scheduled-agent-admin-button-primary">Add scheduled agent</button>
			<button type="button" id="scheduled-agent-admin-reload" class="scheduled-agent-admin-button">Reload</button>
		</div>

		<div id="scheduled-agent-admin-grid" class="scheduled-agent-admin-grid-shell">
			<div class="scheduled-agent-admin-startup">Loading Scheduled Agent Admin display...</div>
		</div>
		<div id="scheduled-agent-admin-output" class="scheduled-agent-admin-status"><strong>Last action:</strong> Waiting for initialization.</div>
		<details class="scheduled-agent-admin-log-details">
			<summary>Debug log</summary>
			<pre id="scheduled-agent-admin-log" class="scheduled-agent-admin-log">Status log will appear here.</pre>
		</details>
	</div>
</div>

<div id="scheduled-agent-admin-editor" class="scheduled-agent-admin-modal-backdrop" aria-hidden="true">
	<div class="scheduled-agent-admin-modal" role="dialog" aria-modal="true" aria-labelledby="scheduled-agent-admin-editor-title">
		<div class="scheduled-agent-admin-modal-header">
			<div id="scheduled-agent-admin-editor-title" class="scheduled-agent-admin-modal-title">Scheduled agent editor</div>
			<button type="button" class="scheduled-agent-admin-button" data-editor-close="1">Close</button>
		</div>
		<div class="scheduled-agent-admin-modal-body">
			<form id="base3_scheduled_agent_admin_editor_form">
				<input type="hidden" name="mode" value="save" />
				<input type="hidden" name="old_id" />
				<div class="scheduled-agent-admin-form-header">
					<div>
						<label class="scheduled-agent-admin-label">Agent ID</label>
						<input type="text" name="agent_id" class="scheduled-agent-admin-input" />
						<p class="scheduled-agent-admin-help">SettingsStore name inside the fixed group <code><?php echo $e($settingsGroup); ?></code>. Existing records keep their ID while editing.</p>
					</div>
				</div>
<?php include DIR_PLUGIN . 'MissionBay/tpl/Content/ScheduledAgentFormFields.php'; ?>
			</form>
		</div>
		<div class="scheduled-agent-admin-modal-footer">
			<div id="scheduled-agent-admin-editor-status" class="scheduled-agent-admin-modal-status">Save is enabled.</div>
			<div>
				<button type="button" class="scheduled-agent-admin-button" id="scheduled-agent-admin-copy-payload">Copy payload</button>
				<button type="button" class="scheduled-agent-admin-button scheduled-agent-admin-button-primary" id="scheduled-agent-admin-save">Save</button>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const MODULARGRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const DEFAULT_RECORD = <?php echo json_encode($defaultRecord, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const POLICY_OPTIONS = <?php echo json_encode(array_values($policyOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const SETTINGS_GROUP = <?php echo json_encode($settingsGroup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_SELECTOR = '#scheduled-agent-admin-grid';
	const LOG_SELECTOR = '#scheduled-agent-admin-log';
	const OUTPUT_SELECTOR = '#scheduled-agent-admin-output';
	const BATCH_SIZE = 50;
	const SORT_TYPES = {
		agent_id: 'string',
		label: 'string',
		enabled_label: 'string',
		policy_label: 'string',
		llm: 'string',
		component_count: 'int',
		user_prompt_preview: 'string'
	};

	let grid = null;

	const layout = {
		type: 'stack',
		className: 'mg-layout-root',
		children: [
			{
				type: 'zone',
				key: 'topLine',
				className: 'scheduled-agent-admin-panel'
			},
			{
				type: 'view',
				key: 'main',
				className: 'scheduled-agent-admin-main'
			},
			{
				type: 'zone',
				key: 'statusZone',
				className: 'scheduled-agent-admin-panel'
			}
		]
	};

	function log(label, value = undefined) {
		const message = value === undefined ? String(label) : String(label) + ' ' + stringifyJson(value);
		const logElement = document.querySelector(LOG_SELECTOR);

		console.log('[ScheduledAgentAdmin]', label, value === undefined ? '' : value);

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

	function createPill(text) {
		const pill = document.createElement('span');
		pill.className = 'scheduled-agent-admin-pill';
		pill.textContent = getText(text);

		return pill;
	}

	function renderPills(value) {
		const wrapper = createElement('scheduled-agent-admin-pill-row');
		const items = String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

		if (items.length === 0) {
			wrapper.appendChild(createPill('-'));
			return wrapper;
		}

		items.forEach((item) => wrapper.appendChild(createPill(item)));

		return wrapper;
	}

	function renderAgent(value, row) {
		const wrapper = createElement('scheduled-agent-admin-cell-stack');
		const main = createElement('scheduled-agent-admin-cell-main', getText(row.label || row.agent_id));
		const sub = createElement('scheduled-agent-admin-cell-sub', getText(row.agent_id) + ' · ' + getText(row.enabled_label));

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderPolicy(value, row) {
		const wrapper = createElement('scheduled-agent-admin-cell-stack');
		const main = createElement('scheduled-agent-admin-cell-main', getText(row.policy_label || row.policy));
		const sub = createElement('scheduled-agent-admin-cell-sub', getText(row.policy_data_text));

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderComponentCount(value, row) {
		return createElement('scheduled-agent-admin-cell-main', getText(row.component_count, '0'));
	}

	function getAgentIdFromRow(row) {
		if (!row || typeof row !== 'object') {
			return '';
		}

		return String(row.agent_id || row.id || '').trim();
	}

	async function loadRemoteRecord(row) {
		const id = getAgentIdFromRow(row);

		if (!id) {
			throw new Error('Missing scheduled agent id for detail request.');
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
		return createElement('scheduled-agent-admin-startup', 'Loading record for ' + getText(getAgentIdFromRow(row)) + '...');
	}

	function createDetailErrorPlaceholder(row, error) {
		return createElement('scheduled-agent-admin-startup scheduled-agent-admin-startup-error', 'Failed to load record for ' + getText(getAgentIdFromRow(row)) + ': ' + getText(error && error.message ? error.message : error));
	}

	function createDetailRow(key, value) {
		const row = createElement('scheduled-agent-admin-detail-row');
		row.appendChild(createElement('scheduled-agent-admin-detail-key', key));
		row.appendChild(createElement('', getText(value)));

		return row;
	}

	function renderAgentDetail(context) {
		const record = context && context.payload ? context.payload : null;

		if (!record || typeof record !== 'object') {
			return document.createTextNode(getText(record));
		}

		const wrapper = createElement('scheduled-agent-admin-detail');
		const left = createElement('scheduled-agent-admin-detail-card');
		const right = createElement('scheduled-agent-admin-detail-card');
		const pre = document.createElement('pre');

		left.appendChild(createElement('scheduled-agent-admin-detail-title', getText(record.label || record.agent_id || record.id)));
		left.appendChild(createDetailRow('ID', record.agent_id || record.id));
		left.appendChild(createDetailRow('Label', record.label));
		left.appendChild(createDetailRow('Enabled', record.enabled ? 'yes' : 'no'));
		left.appendChild(createDetailRow('Policy', record.policy_label || record.policy));
		left.appendChild(createDetailRow('Policy data', record.policy_data_text));
		left.appendChild(createDetailRow('LLM', record.llm));
		left.appendChild(createDetailRow('Components', record.component_count));
		left.appendChild(createDetailRow('User prompt', record.user_prompt_preview));

		right.appendChild(createElement('scheduled-agent-admin-detail-title', 'Settings JSON'));
		pre.className = 'scheduled-agent-admin-json';
		pre.textContent = record.settings_json || stringifyJson(record);
		right.appendChild(pre);

		wrapper.appendChild(left);
		wrapper.appendChild(right);

		return wrapper;
	}

	function getEditorElements() {
		return {
			modal: document.getElementById('scheduled-agent-admin-editor'),
			form: document.getElementById('base3_scheduled_agent_admin_editor_form'),
			status: document.getElementById('scheduled-agent-admin-editor-status')
		};
	}

	function setEditorStatus(message, type = '') {
		const elements = getEditorElements();

		if (!elements.status) {
			return;
		}

		elements.status.className = 'scheduled-agent-admin-modal-status';

		if (type) {
			elements.status.classList.add('scheduled-agent-admin-modal-status-' + type);
		}

		elements.status.textContent = message || '';
	}

	function setFormValue(form, name, value) {
		const field = form.elements.namedItem(name);

		if (!field) {
			return;
		}

		field.value = value === null || value === undefined ? '' : String(value);
	}

	function getFormFieldValue(form, name) {
		const field = form.elements.namedItem(name);

		if (!field) {
			return '';
		}

		return String(field.value || '').trim();
	}

	function normalizeTechnicalKey(value) {
		return String(value || '').toLowerCase().trim().replace(/[^a-z0-9._-]+/g, '');
	}


	function encodeUtf8Base64(value) {
		value = String(value === null || value === undefined ? '' : value);

		if (window.TextEncoder) {
			const bytes = new TextEncoder().encode(value);
			let binary = '';
			const chunkSize = 0x8000;

			for (let offset = 0; offset < bytes.length; offset += chunkSize) {
				const chunk = bytes.subarray(offset, offset + chunkSize);
				binary += String.fromCharCode.apply(null, Array.prototype.slice.call(chunk));
			}

			return btoa(binary);
		}

		return btoa(unescape(encodeURIComponent(value)));
	}

	function getPolicyOptionById(policyId) {
		policyId = String(policyId || '');

		for (let i = 0; i < POLICY_OPTIONS.length; i++) {
			if (String(POLICY_OPTIONS[i].id || '') === policyId) {
				return POLICY_OPTIONS[i];
			}
		}

		return null;
	}

	function getPolicySchemaProperties(schema) {
		if (schema && schema.properties && typeof schema.properties === 'object' && !Array.isArray(schema.properties)) {
			return schema.properties;
		}

		if (schema && schema.fields && typeof schema.fields === 'object' && !Array.isArray(schema.fields)) {
			return schema.fields;
		}

		if (schema && schema.data && schema.data.properties && typeof schema.data.properties === 'object' && !Array.isArray(schema.data.properties)) {
			return schema.data.properties;
		}

		return {};
	}

	function getPolicySchemaRequired(schema) {
		if (Array.isArray(schema && schema.required)) {
			return schema.required.map(String);
		}

		if (schema && schema.data && Array.isArray(schema.data.required)) {
			return schema.data.required.map(String);
		}

		return [];
	}

	function getPolicySchemaType(schema) {
		let type = schema && schema.type !== undefined ? schema.type : 'string';

		if (Array.isArray(type)) {
			type = type.find((item) => item !== 'null') || 'string';
		}

		return String(type || 'string');
	}

	function getPolicySchemaDefault(schema) {
		if (schema && Object.prototype.hasOwnProperty.call(schema, 'default')) {
			return schema.default;
		}

		const type = getPolicySchemaType(schema);

		if (type === 'boolean') {
			return false;
		}

		if (type === 'array') {
			return [];
		}

		if (type === 'object') {
			return {};
		}

		return '';
	}

	function markAdminPolicyControl(control, key, type) {
		control.setAttribute('data-base3-scheduled-agent-policy-key', key);
		control.setAttribute('data-base3-scheduled-agent-policy-type', type);
		control.addEventListener('input', () => syncAdminPolicyData(getEditorElements().form));
		control.addEventListener('change', () => syncAdminPolicyData(getEditorElements().form));

		return control;
	}

	function createAdminPolicyControl(key, property, value) {
		const type = getPolicySchemaType(property);
		const enumValues = Array.isArray(property && property.enum) ? property.enum : [];
		let control;

		if (enumValues.length > 0) {
			control = document.createElement('select');
			control.name = 'policy_data[' + key + ']';
			control.className = 'form-control';

			enumValues.forEach((item) => {
				const option = document.createElement('option');
				option.value = String(item);
				option.textContent = String(item);
				control.appendChild(option);
			});

			control.value = value === null || value === undefined ? '' : String(value);

			return markAdminPolicyControl(control, key, type);
		}

		if (type === 'boolean') {
			const label = document.createElement('label');
			const hidden = document.createElement('input');
			const checkbox = document.createElement('input');

			label.className = 'base3-scheduled-agent-checkbox-row';
			hidden.type = 'hidden';
			hidden.name = 'policy_data[' + key + ']';
			hidden.value = '0';
			checkbox.type = 'checkbox';
			checkbox.name = 'policy_data[' + key + ']';
			checkbox.value = '1';
			checkbox.checked = !!value && String(value) !== '0';
			markAdminPolicyControl(checkbox, key, type);
			label.appendChild(hidden);
			label.appendChild(checkbox);
			label.appendChild(document.createTextNode(' Enabled'));

			return label;
		}

		if (type === 'object' || type === 'array') {
			control = document.createElement('textarea');
			control.name = 'policy_data[' + key + ']';
			control.className = 'form-control base3-scheduled-agent-json';
			control.value = stringifyJson(value === undefined ? getPolicySchemaDefault(property) : value);

			return markAdminPolicyControl(control, key, type);
		}

		control = document.createElement('input');
		control.type = (type === 'integer' || type === 'number') ? 'number' : 'text';
		control.name = 'policy_data[' + key + ']';
		control.className = 'form-control';
		control.value = value === null || value === undefined ? '' : String(value);

		if (type === 'number') {
			control.step = 'any';
		}

		return markAdminPolicyControl(control, key, type);
	}

	function renderAdminPolicyFields(form, policyId, data = {}) {
		const root = getScheduledFieldsRoot(form);
		const policyFields = root ? root.querySelector('[data-base3-scheduled-agent-policy-fields]') : null;

		if (!policyFields) {
			return;
		}

		policyId = String(policyId || '');
		data = data && typeof data === 'object' && !Array.isArray(data) ? data : {};
		policyFields.replaceChildren();

		const option = getPolicyOptionById(policyId);
		const schema = option && option.schema && typeof option.schema === 'object' ? option.schema : {};
		const properties = getPolicySchemaProperties(schema);
		const required = getPolicySchemaRequired(schema);
		const keys = Object.keys(properties);

		if (keys.length === 0) {
			policyFields.appendChild(createElement('base3-scheduled-agent-policy-empty', policyId ? 'This policy does not expose configurable fields.' : 'Select a timing policy.'));
			syncAdminPolicyData(form);
			return;
		}

		keys.forEach((key) => {
			const property = properties[key] || {};
			const row = createElement('base3-scheduled-agent-policy-field' + (required.indexOf(key) !== -1 ? ' base3-scheduled-agent-policy-field-required' : ''));
			const label = createElement('base3-scheduled-agent-policy-label', key);
			const controlCell = createElement('base3-scheduled-agent-policy-control');
			const value = Object.prototype.hasOwnProperty.call(data, key) ? data[key] : getPolicySchemaDefault(property);
			const description = String(property.description || '');

			controlCell.appendChild(createAdminPolicyControl(key, property, value));

			if (description !== '') {
				controlCell.appendChild(createElement('base3-scheduled-agent-help', description));
			}

			row.appendChild(label);
			row.appendChild(controlCell);
			policyFields.appendChild(row);
		});

		syncAdminPolicyData(form);
	}

	function readAdminPolicyControlValue(control) {
		const type = control.getAttribute('data-base3-scheduled-agent-policy-type') || 'string';

		if (type === 'boolean') {
			return !!control.checked;
		}

		if (type === 'integer') {
			return control.value === '' ? null : parseInt(control.value, 10);
		}

		if (type === 'number') {
			return control.value === '' ? null : Number(control.value);
		}

		if (type === 'object' || type === 'array') {
			try {
				return JSON.parse(control.value || (type === 'array' ? '[]' : '{}'));
			} catch (error) {
				return type === 'array' ? [] : {};
			}
		}

		return control.value;
	}

	function syncAdminPolicyData(form) {
		const root = getScheduledFieldsRoot(form);

		if (!root) {
			return {};
		}

		const data = {};
		root.querySelectorAll('[data-base3-scheduled-agent-policy-key]').forEach((control) => {
			const key = control.getAttribute('data-base3-scheduled-agent-policy-key') || '';

			if (key === '') {
				return;
			}

			data[key] = readAdminPolicyControlValue(control);
		});

		const json = stringifyJson(data) || '{}';
		const jsonField = root.querySelector('[data-base3-scheduled-agent-policy-data-json]');
		const b64Field = root.querySelector('[data-base3-scheduled-agent-policy-data-b64]');

		if (jsonField) {
			jsonField.value = json;
		}

		if (b64Field) {
			b64Field.value = encodeUtf8Base64(json);
		}

		return data;
	}

	function getScheduledFieldsRoot(form) {
		return form ? form.querySelector('[data-base3-scheduled-agent-fields]') : null;
	}


	function setCheckboxField(form, name, checked) {
		const field = form.elements.namedItem(name);

		if (field && typeof field.checked === 'boolean') {
			field.checked = !!checked;
		}
	}

	function updateAgentConfigFallback(form, values) {
		const agentRoot = form.querySelector('[data-base3-agent-config-root]');

		if (agentRoot && typeof agentRoot.__base3AgentConfigUpdateValues === 'function') {
			try {
				agentRoot.__base3AgentConfigUpdateValues(values);
				log('agent config form helper updated values', { llm: values.llm || '', components: Array.isArray(values.agent_components) ? values.agent_components.length : 0 });
			} catch (error) {
				log('agent config form helper failed', error && error.message ? error.message : String(error));
			}
		}

		setFormValue(form, 'llm', values.llm || '');
		setFormValue(form, 'system_prompt', values.system_prompt || '');
		setFormValue(form, 'agent_flow', values.agent_flow_json || '{}');

		if (agentRoot && typeof agentRoot.__base3AgentConfigPrepareSubmit === 'function') {
			try {
				agentRoot.__base3AgentConfigPrepareSubmit();
			} catch (error) {
				log('agent config prepare helper failed', error && error.message ? error.message : String(error));
			}
		}
	}

	function updateScheduledAgentFormFallback(form, values) {
		const policyId = String(values.policy_policy || values.policy || '').trim();
		const policyData = values.policy_data && typeof values.policy_data === 'object' && !Array.isArray(values.policy_data) ? values.policy_data : {};
		const policySelect = form.querySelector('[data-base3-scheduled-agent-policy-select]');
		const groupField = form.elements.namedItem('scheduled_agent_config_group');
		const nameField = form.elements.namedItem('scheduled_agent_config_name');

		setCheckboxField(form, 'enabled', !Object.prototype.hasOwnProperty.call(values, 'enabled') || !!values.enabled);
		setFormValue(form, 'label', values.label || '');
		setFormValue(form, 'user_prompt', values.user_prompt || '');

		if (groupField) {
			groupField.value = SETTINGS_GROUP;
		}

		if (nameField) {
			nameField.value = values.scheduled_agent_config_name || values.agent_id || values.id || '';
		}

		if (policySelect) {
			policySelect.value = policyId;
			renderAdminPolicyFields(form, policyId, policyData);
		}
		else {
			syncAdminPolicyData(form);
		}

		updateAgentConfigFallback(form, values);
	}

	function updateEditorForm(record) {
		const elements = getEditorElements();

		if (!elements.modal || !elements.form) {
			setLog('Scheduled agent editor elements not found.');
			return;
		}

		const form = elements.form;
		const fieldsRoot = getScheduledFieldsRoot(form);
		record = record && typeof record === 'object' ? record : {};

		form.reset();

		const oldId = String(record.old_id || record.agent_id || record.id || '').trim();
		const currentId = String(record.agent_id || record.id || '').trim();
		setFormValue(form, 'old_id', oldId);
		setFormValue(form, 'agent_id', currentId);

		const agentIdField = form.elements.namedItem('agent_id');
		if (agentIdField) {
			agentIdField.readOnly = oldId !== '';
		}

		const values = Object.assign({}, DEFAULT_RECORD, record, {
			scheduled_agent_config_group: SETTINGS_GROUP,
			scheduled_agent_config_name: currentId
		});

		if (fieldsRoot && typeof fieldsRoot.__base3ScheduledAgentUpdateValues === 'function') {
			try {
				fieldsRoot.__base3ScheduledAgentUpdateValues(values);
				log('scheduled agent form helper updated values', { id: currentId, policy: values.policy_policy || values.policy || '' });
			} catch (error) {
				log('scheduled agent form helper failed', error && error.message ? error.message : String(error));
			}
		}
		else {
			log('scheduled agent form helper missing; using admin fallback hydration');
		}

		updateScheduledAgentFormFallback(form, values);

		elements.modal.classList.add('is-open');
		elements.modal.setAttribute('aria-hidden', 'false');
		setEditorStatus(oldId === '' ? 'New scheduled agent. Enter an ID, then save.' : 'Editor opened. Save is enabled.', 'ok');
		setLog('Opened editor for ' + getText(currentId, 'new scheduled agent'));
	}

	function openNewEditor() {
		const record = Object.assign({}, DEFAULT_RECORD, {
			id: '',
			agent_id: '',
			old_id: '',
			label: '',
			scheduled_agent_config_name: ''
		});

		updateEditorForm(record);
	}

	function closeEditor() {
		const elements = getEditorElements();

		if (!elements.modal) {
			return;
		}

		elements.modal.classList.remove('is-open');
		elements.modal.setAttribute('aria-hidden', 'true');
		setLog('Closed editor.');
	}

	async function openEditorFromRow(row) {
		try {
			setLog('Loading record for editor: ' + getText(getAgentIdFromRow(row)));
			const record = await loadRemoteRecord(row);
			updateEditorForm(record);
		} catch (error) {
			setLog('Could not open editor: ' + getText(error && error.message ? error.message : error));
		}
	}

	function validateEditorForm(form) {
		const id = normalizeTechnicalKey(getFormFieldValue(form, 'agent_id'));

		if (!id) {
			throw new Error('Agent ID is required.');
		}

		return id;
	}

	function prepareConfigFormData(form) {
		const id = validateEditorForm(form);
		const nameField = form.elements.namedItem('scheduled_agent_config_name');
		const groupField = form.elements.namedItem('scheduled_agent_config_group');
		const modeField = form.elements.namedItem('mode');
		const fieldsRoot = getScheduledFieldsRoot(form);

		if (fieldsRoot && typeof fieldsRoot.__base3ScheduledAgentPrepareSubmit === 'function') {
			try {
				fieldsRoot.__base3ScheduledAgentPrepareSubmit();
			} catch (error) {
				log('scheduled agent prepare helper failed', error && error.message ? error.message : String(error));
			}
		}

		syncAdminPolicyData(form);

		const agentRoot = form.querySelector('[data-base3-agent-config-root]');
		if (agentRoot && typeof agentRoot.__base3AgentConfigPrepareSubmit === 'function') {
			try {
				agentRoot.__base3AgentConfigPrepareSubmit();
			} catch (error) {
				log('agent config prepare helper failed', error && error.message ? error.message : String(error));
			}
		}

		const agentFlow = form.elements.namedItem('agent_flow');
		const agentFlowB64 = form.querySelector('[data-base3-agent-config-agent-flow-b64]');
		if (agentFlow && agentFlowB64) {
			agentFlowB64.value = encodeUtf8Base64(agentFlow.value || '{}');
		}

		if (nameField) {
			nameField.value = id;
		}

		if (groupField) {
			groupField.value = SETTINGS_GROUP;
		}

		if (modeField) {
			modeField.value = 'save';
		}

		const formData = new FormData(form);
		formData.set('mode', 'save');
		formData.set('agent_id', id);
		formData.set('scheduled_agent_config_group', SETTINGS_GROUP);
		formData.set('scheduled_agent_config_name', id);

		return formData;
	}

	function buildEditorPayloadPreview() {
		const elements = getEditorElements();
		const form = elements.form;

		if (!form) {
			throw new Error('Scheduled agent editor form not found.');
		}

		const id = validateEditorForm(form);
		const formData = prepareConfigFormData(form);
		const payload = {};

		formData.forEach((value, key) => {
			if (payload[key] === undefined) {
				payload[key] = value;
				return;
			}

			if (!Array.isArray(payload[key])) {
				payload[key] = [payload[key]];
			}

			payload[key].push(value);
		});

		payload.agent_id = id;
		payload.old_id = getFormFieldValue(form, 'old_id');

		return payload;
	}

	async function copyEditorPayload() {
		try {
			const payload = buildEditorPayloadPreview();
			await copyText(stringifyJson(payload));
			setEditorStatus('Payload copied.', 'ok');
			setLog('Copied editor payload for ' + getText(payload.agent_id, 'new scheduled agent'));
		} catch (error) {
			setEditorStatus(error && error.message ? error.message : String(error), 'error');
		}
	}

	async function saveEditorPayload() {
		try {
			const elements = getEditorElements();

			if (!elements.form) {
				throw new Error('Scheduled agent editor form not found.');
			}

			const id = validateEditorForm(elements.form);
			const oldId = getFormFieldValue(elements.form, 'old_id');

			if (oldId !== '' && oldId !== id) {
				throw new Error('Renaming scheduled agents is not supported in this editor. Duplicate/create a new record and delete the old one if needed.');
			}

			const formData = prepareConfigFormData(elements.form);

			setEditorStatus('Saving scheduled agent...', '');
			setLog('Saving scheduled agent ' + id);

			const response = await fetch(ENDPOINT_URL, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			});

			if (!response.ok) {
				throw new Error('Save failed with status ' + response.status);
			}

			const json = await response.json();
			log('Save response body', json);

			if (!json || !json.ok) {
				throw new Error(json && json.error ? json.error : 'Save failed.');
			}

			setEditorStatus('Scheduled agent saved. Updating grid...', 'ok');
			closeEditor();
			await refreshGrid();
			setLog('Saved scheduled agent ' + id + '.');
		} catch (error) {
			setEditorStatus(error && error.message ? error.message : String(error), 'error');
			setLog('Save failed: ' + getText(error && error.message ? error.message : error));
		}
	}

	async function deleteAgentById(id) {
		id = String(id || '').trim();

		if (!id) {
			throw new Error('Missing scheduled agent id.');
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

	async function deleteAgentFromRow(row) {
		try {
			const id = getAgentIdFromRow(row);

			if (!id) {
				throw new Error('Missing scheduled agent id.');
			}

			if (!window.confirm('Delete scheduled agent "' + id + '"?')) {
				setLog('Delete cancelled for ' + id);
				return;
			}

			setLog('Deleting scheduled agent ' + id);
			const response = await deleteAgentById(id);
			setLog('Deleted scheduled agent ' + getText(response.id || id, id) + '. Updating grid...');
			await refreshGrid();
			setLog('Deleted scheduled agent ' + getText(response.id || id, id) + '.');
		} catch (error) {
			setLog('Delete failed: ' + getText(error && error.message ? error.message : error));
		}
	}

	async function reloadStore() {
		try {
			setLog('Reloading scheduled agent store.');
			const response = await postJson({
				mode: 'reload'
			});

			if (!response || !response.ok) {
				throw new Error(response && response.error ? response.error : 'Reload failed.');
			}

			await refreshGrid();
			setLog('Scheduled agent store reloaded.');
		} catch (error) {
			setLog('Reload failed: ' + getText(error && error.message ? error.message : error));
		}
	}

	function bindEditorEvents() {
		const addButton = document.getElementById('scheduled-agent-admin-add');
		const reloadButton = document.getElementById('scheduled-agent-admin-reload');
		const copyButton = document.getElementById('scheduled-agent-admin-copy-payload');
		const saveButton = document.getElementById('scheduled-agent-admin-save');
		const elements = getEditorElements();

		if (elements.form) {
			elements.form.addEventListener('submit', (event) => {
				event.preventDefault();
				saveEditorPayload();
			});

			const policySelect = elements.form.querySelector('[data-base3-scheduled-agent-policy-select]');
			if (policySelect) {
				policySelect.addEventListener('change', () => {
					renderAdminPolicyFields(elements.form, policySelect.value, {});
					setLog('Timing policy form rendered for ' + getText(policySelect.value));
				});
			}
		}

		if (addButton) {
			addButton.addEventListener('click', (event) => {
				event.preventDefault();
				openNewEditor();
			});
		}

		if (reloadButton) {
			reloadButton.addEventListener('click', (event) => {
				event.preventDefault();
				reloadStore();
			});
		}

		if (copyButton) {
			copyButton.addEventListener('click', (event) => {
				event.preventDefault();
				copyEditorPayload();
			});
		}

		if (saveButton) {
			saveButton.addEventListener('click', (event) => {
				event.preventDefault();
				saveEditorPayload();
			});
		}

		if (elements.modal) {
			elements.modal.querySelectorAll('[data-editor-close]').forEach((button) => {
				button.addEventListener('click', (event) => {
					event.preventDefault();
					closeEditor();
				});
			});

			elements.modal.addEventListener('click', (event) => {
				if (event.target === elements.modal) {
					closeEditor();
				}
			});
		}

		log('editor events bound');
	}

	async function initGrid(modularGridModule) {
		log('initGrid start');
		bindEditorEvents();

		const {
			AjaxAdapter,
			ColumnVisibilityPlugin,
			HeaderMenuPlugin,
			InfoPlugin,
			InfiniteScrollPlugin,
			ModularGrid,
			ResetPlugin,
			RowActionsPlugin,
			RowDetailPlugin,
			SearchPlugin
		} = modularGridModule;

		if (!AjaxAdapter || !ModularGrid) {
			throw new Error('ModularGrid module was loaded, but AjaxAdapter or ModularGrid export is missing.');
		}

		const adapter = new AjaxAdapter({
			url: ENDPOINT_URL,
			method: 'POST',
			rowsPath: 'data',
			totalPath: 'total',
			mapRequest(request) {
				const sortKey = request.sortKey || 'agent_id';
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
					filters: {}
				};

				log('mapRequest payload', payload);

				return payload;
			}
		});

		log('selected exports', {
			AjaxAdapter: !!AjaxAdapter,
			ColumnVisibilityPlugin: !!ColumnVisibilityPlugin,
			HeaderMenuPlugin: !!HeaderMenuPlugin,
			InfoPlugin: !!InfoPlugin,
			InfiniteScrollPlugin: !!InfiniteScrollPlugin,
			ModularGrid: !!ModularGrid,
			ResetPlugin: !!ResetPlugin,
			RowActionsPlugin: !!RowActionsPlugin,
			RowDetailPlugin: !!RowDetailPlugin,
			SearchPlugin: !!SearchPlugin
		});

		log('adapter created');

		grid = new ModularGrid(GRID_SELECTOR, {
			layout,
			adapter,
			dataMode: 'server',
			server: {
				searchDebounceMs: 220,
				watchStateKeys: ['query']
			},
			features: {
				paging: false
			},
			pageSize: BATCH_SIZE,
			sort: {
				key: 'agent_id',
				direction: 'asc'
			},
			plugins: [
				SearchPlugin,
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
					placeholder: 'Search agent id, policy or prompt'
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
					sections: ['query', 'columns', 'detailView']
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
							key: 'edit-agent',
							label: 'Edit scheduled agent',
							onClick(context) {
								openEditorFromRow(context && context.row ? context.row : null);
							}
						},
						{
							key: 'delete-agent',
							label: 'Delete scheduled agent',
							onClick(context) {
								deleteAgentFromRow(context && context.row ? context.row : null);
							}
						}
					]
				},
				rowDetail: {
					rowIdKey: 'agent_id',
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
							return renderAgentDetail(context);
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
					key: 'agent_id',
					label: 'Agent',
					width: 300,
					headerMenu: {
						defaultSortKey: 'label',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'label', label: 'Label' },
							{ key: 'agent_id', label: 'Agent ID' }
						]
					},
					render(value, row) {
						return renderAgent(value, row);
					}
				},
				{
					key: 'policy_label',
					label: 'Timing policy',
					width: 300,
					headerMenu: {
						defaultSortKey: 'policy_label',
						defaultSortDirection: 'asc'
					},
					render(value, row) {
						return renderPolicy(value, row);
					}
				},
				{
					key: 'llm',
					label: 'LLM',
					width: 180,
					render(value) {
						return renderPills(value);
					}
				},
				{
					key: 'component_count',
					label: 'Components',
					width: 120,
					headerMenu: {
						defaultSortKey: 'component_count',
						defaultSortDirection: 'desc'
					},
					render(value, row) {
						return renderComponentCount(value, row);
					}
				},
				{
					key: 'user_prompt_preview',
					label: 'User prompt',
					width: 420,
					render(value) {
						return createElement('scheduled-agent-admin-cell-sub', getText(value));
					}
				}
			]
		});

		log('grid created');

		if (typeof grid.on === 'function') {
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
		}

		log('grid.init start');
		await grid.init();
		log('grid.init finished');
		setLog('Scheduled Agent Admin loaded.');
	}

	async function importFirst(url, moduleLabel) {
		log('import start: ' + moduleLabel, url);

		try {
			const absoluteUrl = new URL(url, document.baseURI).href;
			const module = await import(absoluteUrl);
			log('import success: ' + moduleLabel, Object.keys(module || {}));
			return module;
		} catch (error) {
			log('import failed: ' + moduleLabel, error && error.message ? error.message : String(error));
			throw error;
		}
	}

	function setStartupStatus(message, details = '', isError = false) {
		const root = document.querySelector(GRID_SELECTOR);
		log('startup: ' + message, details || undefined);

		if (!root) {
			return;
		}

		const box = createElement('scheduled-agent-admin-startup' + (isError ? ' scheduled-agent-admin-startup-error' : ''));
		box.appendChild(document.createTextNode(message));

		if (details) {
			const pre = document.createElement('pre');
			pre.textContent = details;
			box.appendChild(pre);
		}

		root.replaceChildren(box);
	}

	(async function boot() {
		const root = document.querySelector(GRID_SELECTOR);

		log('bootstrap start', {
			rootFound: !!root,
			initialized: root ? root.dataset.initialized || '' : null,
			endpoint: ENDPOINT_URL,
			modularGridUrl: MODULARGRID_URL
		});

		if (!root || root.dataset.initialized === '1') {
			return;
		}

		root.dataset.initialized = '1';
		setStartupStatus('Loading ModularGrid module.');

		try {
			if (!ENDPOINT_URL) {
				throw new Error('Missing Scheduled Agent Admin endpoint URL.');
			}

			const module = await importFirst(MODULARGRID_URL, 'ModularGrid');
			setStartupStatus('Initializing scheduled agent grid.');
			await initGrid(module);
		} catch (error) {
			const message = error && error.message ? error.message : String(error);
			setStartupStatus('Scheduled Agent Admin could not be initialized.', message, true);
			setLog('Initialization failed: ' + message);
			console.error(error);
		}
	})();
})();
</script>
