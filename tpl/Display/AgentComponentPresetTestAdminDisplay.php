<?php
$resolve = $this->_['resolve'];
$modularGridCssUrl = (string)$resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string)$resolve('plugin/ClientStack/assets/modulargrid/index.js');
$jsonLensCssUrl = (string)$resolve('plugin/ClientStack/assets/jsonlens/styles/jsonlens.css');
$jsonLensJsUrl = (string)$resolve('plugin/ClientStack/assets/jsonlens/index.js');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularGridCssUrl, ENT_QUOTES); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars($jsonLensCssUrl, ENT_QUOTES); ?>" />

<style>
	.agent-preset-test-shell {
		max-width: 1720px;
	}

	.agent-preset-test-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.agent-preset-test-shell > p {
		max-width: 1160px;
		margin: 0 0 16px 0;
		color: #555;
		line-height: 1.45;
	}

	.agent-preset-grid .agent-preset-panel {
		display: flex;
		align-items: center;
		flex-wrap: nowrap;
		gap: 8px;
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		overflow-x: auto;
	}

	.agent-preset-grid .agent-preset-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.agent-preset-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.agent-preset-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.agent-preset-grid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.agent-preset-grid .mg-input,
	.agent-preset-grid .mg-select,
	.agent-preset-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.agent-preset-grid input[type="search"].mg-input {
		width: 330px;
	}

	.agent-preset-grid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.agent-preset-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.agent-preset-grid .mg-table th,
	.agent-preset-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.agent-preset-cell {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.agent-preset-cell-main {
		font-weight: 600;
		color: #222;
		overflow-wrap: anywhere;
	}

	.agent-preset-cell-sub {
		font-size: 12px;
		color: #666;
		overflow-wrap: anywhere;
	}

	.agent-preset-pill-row {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		align-items: center;
	}

	.agent-preset-pill {
		display: inline-flex;
		align-items: center;
		padding: 1px 7px;
		border: 1px solid #d6d6d6;
		border-radius: 999px;
		background: #fafafa;
		font-size: 11px;
		line-height: 1.35;
		color: #444;
		white-space: nowrap;
	}

	.agent-preset-pill-strong {
		background: #f0f0f0;
		border-color: #c8c8c8;
		color: #222;
	}

	.agent-preset-status-disabled,
	.agent-preset-warning {
		border-color: #d6a7a7;
		background: #fff8f8;
		color: #7a2020;
	}

	.agent-preset-output {
		margin-top: 12px;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.agent-preset-output strong {
		color: #222;
	}

	.agent-preset-startup {
		padding: 16px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.agent-preset-startup-error {
		border-color: #d9aaaa;
		background: #fff7f7;
		color: #7a2020;
	}

	.agent-preset-startup pre {
		margin: 10px 0 0 0;
		white-space: pre-wrap;
		font-size: 12px;
	}

	.agent-preset-detail {
		display: grid;
		gap: 12px;
		padding: 12px;
		background: #f7f7f7;
	}

	.agent-preset-detail-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 14px;
	}

	.agent-preset-detail-actions {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		flex-wrap: wrap;
		gap: 8px;
	}

	.agent-preset-detail-title {
		font-size: 17px;
		font-weight: 600;
		color: #222;
	}

	.agent-preset-detail-summary {
		margin-top: 3px;
		font-size: 12px;
		color: #666;
		overflow-wrap: anywhere;
	}

	.agent-preset-detail-layout {
		display: grid;
		grid-template-columns: minmax(440px, 1fr) minmax(400px, 1fr);
		gap: 12px;
		align-items: start;
	}

	.agent-preset-card {
		min-width: 0;
		padding: 12px;
		border: 1px solid #dedede;
		border-radius: 8px;
		background: #fff;
	}

	.agent-preset-tabs {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		margin: 10px 0;
	}

	.agent-preset-tab,
	.agent-preset-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 29px;
		padding: 4px 10px;
		border: 1px solid #cfcfcf;
		border-radius: 6px;
		background: #fff;
		color: #222;
		font-size: 12px;
		line-height: 1.25;
		cursor: pointer;
	}

	.agent-preset-tab:hover,
	.agent-preset-button:hover {
		background: #f5f5f5;
	}

	.agent-preset-tab-active,
	.agent-preset-button-primary {
		border-color: #222;
		background: #222;
		color: #fff;
	}

	.agent-preset-tab-active:hover,
	.agent-preset-button-primary:hover {
		background: #444;
	}

	.agent-preset-button-danger {
		border-color: #a94b4b;
		color: #8c2626;
	}

	.mg-row-detail:fullscreen,
	.mg-row-detail:-webkit-full-screen {
		display: block;
		width: auto;
		height: auto;
		min-height: 100vh;
		padding: 16px;
		background: #f7f7f7;
		overflow: auto;
	}

	.mg-row-detail:fullscreen .agent-preset-detail,
	.mg-row-detail:-webkit-full-screen .agent-preset-detail {
		display: block;
		width: auto;
		height: auto;
		min-height: 0;
	}

	.mg-row-detail:fullscreen .agent-preset-detail-header,
	.mg-row-detail:-webkit-full-screen .agent-preset-detail-header,
	.mg-row-detail:fullscreen .agent-preset-warning-list,
	.mg-row-detail:-webkit-full-screen .agent-preset-warning-list {
		max-width: 1900px;
		margin-right: auto;
		margin-left: auto;
	}

	.mg-row-detail:fullscreen .agent-preset-detail-layout,
	.mg-row-detail:-webkit-full-screen .agent-preset-detail-layout {
		grid-template-columns: minmax(520px, 1.15fr) minmax(520px, 1fr);
		max-width: 1900px;
		margin: 12px auto 0 auto;
	}

	.mg-row-detail:fullscreen .agent-preset-json-holder,
	.mg-row-detail:-webkit-full-screen .agent-preset-json-holder {
		max-height: 760px;
	}

	.agent-preset-form {
		display: grid;
		gap: 10px;
	}

	.agent-preset-form-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 10px;
	}

	.agent-preset-field {
		display: grid;
		gap: 4px;
		min-width: 0;
	}

	.agent-preset-field-full {
		grid-column: 1 / -1;
	}

	.agent-preset-label {
		font-size: 12px;
		font-weight: 600;
		color: #444;
	}

	.agent-preset-input {
		box-sizing: border-box;
		width: 100%;
		min-height: 31px;
		padding: 5px 7px;
		border: 1px solid #cfcfcf;
		border-radius: 5px;
		background: #fff;
		font: inherit;
		font-size: 12px;
	}

	textarea.agent-preset-input {
		min-height: 100px;
		resize: vertical;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
	}

	.agent-preset-tool-intro {
		display: grid;
		gap: 7px;
		padding: 10px;
		border: 1px solid #dedede;
		border-radius: 7px;
		background: #fafafa;
	}

	.agent-preset-tool-name {
		font-size: 14px;
		font-weight: 600;
		color: #222;
	}

	.agent-preset-tool-description {
		font-size: 12px;
		line-height: 1.45;
		color: #555;
		white-space: pre-wrap;
	}

	.agent-preset-schema-form {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 10px;
	}

	.agent-preset-schema-field {
		display: grid;
		gap: 5px;
		min-width: 0;
		padding: 9px;
		border: 1px solid #e1e1e1;
		border-radius: 6px;
		background: #fff;
	}

	.agent-preset-schema-field-complex {
		grid-column: 1 / -1;
	}

	.agent-preset-schema-field-disabled {
		background: #fafafa;
	}

	.agent-preset-schema-field-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
	}

	.agent-preset-schema-field-title {
		display: flex;
		align-items: center;
		gap: 5px;
		min-width: 0;
		font-size: 12px;
		font-weight: 600;
		color: #333;
		overflow-wrap: anywhere;
	}

	.agent-preset-schema-required,
	.agent-preset-schema-optional {
		display: inline-flex;
		align-items: center;
		padding: 1px 5px;
		border-radius: 999px;
		font-size: 10px;
		font-weight: 500;
		white-space: nowrap;
	}

	.agent-preset-schema-required {
		border: 1px solid #caa2a2;
		background: #fff7f7;
		color: #7b2525;
	}

	.agent-preset-schema-optional {
		border: 1px solid #d2d2d2;
		background: #fafafa;
		color: #666;
	}

	.agent-preset-schema-include,
	.agent-preset-checkbox-row {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 12px;
		font-weight: 400;
		color: #555;
	}

	.agent-preset-schema-include input,
	.agent-preset-checkbox-row input {
		margin: 0;
	}

	.agent-preset-schema-notice {
		grid-column: 1 / -1;
		padding: 8px 10px;
		border: 1px solid #d8d8d8;
		border-radius: 6px;
		background: #fafafa;
		font-size: 12px;
		line-height: 1.4;
		color: #555;
	}

	.agent-preset-schema-extra {
		border-color: #d7c39d;
		background: #fffaf1;
		color: #6f4b15;
	}

	.agent-preset-advanced {
		border: 1px solid #dedede;
		border-radius: 6px;
		background: #fafafa;
	}

	.agent-preset-advanced > summary {
		padding: 8px 10px;
		cursor: pointer;
		font-size: 12px;
		font-weight: 600;
		color: #444;
	}

	.agent-preset-advanced-body {
		display: grid;
		gap: 8px;
		padding: 0 10px 10px 10px;
	}

	.agent-preset-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 7px;
		align-items: center;
	}

	.agent-preset-hint,
	.agent-preset-status {
		font-size: 12px;
		color: #666;
	}

	.agent-preset-status-error {
		color: #8a2626;
	}

	.agent-preset-status-ok {
		color: #246b2b;
	}

	.agent-preset-warning-list {
		display: grid;
		gap: 5px;
		margin: 8px 0;
	}

	.agent-preset-warning-item {
		padding: 7px 9px;
		border: 1px solid #e2c0a4;
		border-radius: 6px;
		background: #fff9f2;
		font-size: 12px;
		color: #74421d;
	}

	.agent-preset-confirmation {
		display: grid;
		gap: 9px;
		padding: 12px;
		border: 2px solid #b7782c;
		border-radius: 8px;
		background: #fffaf3;
	}

	.agent-preset-confirmation-title {
		font-size: 15px;
		font-weight: 700;
		color: #55320f;
	}

	.agent-preset-confirmation-risk {
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		color: #8a4f10;
	}

	.agent-preset-confirmation-summary {
		display: grid;
		grid-template-columns: minmax(150px, 0.8fr) minmax(0, 1.4fr);
		gap: 6px 12px;
		margin: 0;
		padding: 10px;
		border: 1px solid #ead5ba;
		border-radius: 6px;
		background: #fff;
	}

	.agent-preset-confirmation-summary dt,
	.agent-preset-confirmation-summary dd {
		margin: 0;
		min-width: 0;
		font-size: 12px;
		line-height: 1.4;
		word-break: break-word;
	}

	.agent-preset-confirmation-summary dt {
		font-weight: 600;
		color: #5f482c;
	}

	.agent-preset-confirmation-details summary {
		cursor: pointer;
		font-size: 12px;
		font-weight: 600;
		color: #6d512f;
	}

	.agent-preset-confirmation-details .agent-preset-json-holder {
		margin-top: 8px;
		max-height: 320px;
	}

	.agent-preset-json-holder {
		min-width: 0;
		max-height: 620px;
		overflow: auto;
	}

	.agent-preset-json-fallback {
		margin: 0;
		padding: 10px;
		border: 1px solid #ddd;
		border-radius: 6px;
		background: #fafafa;
		white-space: pre-wrap;
		word-break: break-word;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.4;
	}

	@media (max-width: 980px) {
		.agent-preset-detail-layout,
		.agent-preset-form-grid,
		.agent-preset-schema-form {
			grid-template-columns: 1fr;
		}
	}
</style>

<div class="agent-preset-test-shell">
	<h1>Agent Component Preset Tests</h1>
	<p>
		Stored component presets are materialized with their configured resource type and recursive dock dependencies. Tool calls use the normal approval, resume, mutation commit-guard, audit, and contract-validation path. Context contributors and conversation memories can be tested from the same preset detail.
	</p>

	<div class="agent-preset-grid">
		<div id="agent-component-preset-test-grid">
			<div class="agent-preset-startup">Loading Agent Component Preset Tests...</div>
		</div>
		<div id="agent-component-preset-test-output" class="agent-preset-output"><strong>Last action:</strong> Waiting for initialization.</div>
	</div>
</div>

<script type="module">
	const ENDPOINT_URL = <?php echo json_encode((string)$this->_['service'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const MEMORY_TEST_NODE_PREFIX = <?php echo json_encode((string)$this->_['memory_test_node_prefix']); ?>;
	const MODULARGRID_URLS = [<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	const JSONLENS_URLS = [<?php echo json_encode($jsonLensJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	const GRID_SELECTOR = '#agent-component-preset-test-grid';
	const LOG_SELECTOR = '#agent-component-preset-test-output';
	const BATCH_SIZE = 50;
	const SORT_TYPES = {
		name: 'string',
		type: 'string',
		status: 'string',
		capability_names: 'string',
		dock_count: 'int'
	};

	let jsonLensModulePromise = null;

	const layout = {
		type: 'stack',
		className: 'mg-layout-root',
		children: [
			{ type: 'zone', key: 'topLine', className: 'agent-preset-panel' },
			{ type: 'view', key: 'main', className: 'agent-preset-main' },
			{ type: 'zone', key: 'statusZone', className: 'agent-preset-panel' }
		]
	};

	function createElement(className = '', text = '') {
		const element = document.createElement('div');
		if (className) element.className = className;
		if (text !== '') element.textContent = text;
		return element;
	}

	function createButton(label, className = '') {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'agent-preset-button' + (className ? ' ' + className : '');
		button.textContent = label;
		return button;
	}

	function getText(value, fallback = '-') {
		return value === null || value === undefined || value === '' ? fallback : String(value);
	}

	function setLog(message) {
		const target = document.querySelector(LOG_SELECTOR);
		if (!target) return;
		target.replaceChildren();
		const label = document.createElement('strong');
		label.textContent = 'Last action:';
		target.appendChild(label);
		target.appendChild(document.createTextNode(' ' + message));
	}

	function setStartupStatus(message, details = '', isError = false) {
		const root = document.querySelector(GRID_SELECTOR);
		if (!root) return;
		const box = createElement('agent-preset-startup' + (isError ? ' agent-preset-startup-error' : ''), message);
		if (details) {
			const pre = document.createElement('pre');
			pre.textContent = details;
			box.appendChild(pre);
		}
		root.replaceChildren(box);
	}

	async function importFirst(urls, label) {
		const errors = [];
		for (const url of urls.filter(Boolean)) {
			try {
				return await import(new URL(url, document.baseURI).href);
			} catch (error) {
				errors.push(url + ' => ' + getText(error && error.message ? error.message : error));
			}
		}
		throw new Error('Could not load ' + label + '. Tried:\n' + errors.join('\n'));
	}

	async function loadJsonLensModule() {
		if (!jsonLensModulePromise) {
			jsonLensModulePromise = importFirst(JSONLENS_URLS, 'JsonLens').catch(() => null);
		}
		return jsonLensModulePromise;
	}

	function stringifyJson(value) {
		try {
			if (typeof value === 'string' && value.trim() !== '') {
				return JSON.stringify(JSON.parse(value), null, 2);
			}
			return JSON.stringify(value, null, 2);
		} catch (error) {
			return typeof value === 'string' ? value : String(value);
		}
	}

	function parseObjectJson(value, label) {
		const raw = String(value || '').trim();
		if (raw === '') return {};
		const parsed = JSON.parse(raw);
		if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
			throw new Error(label + ' must be a JSON object.');
		}
		return parsed;
	}

	function createJsonValue(value) {
		const holder = createElement('agent-preset-json-holder');
		const fallback = document.createElement('pre');
		fallback.className = 'agent-preset-json-fallback';
		fallback.textContent = stringifyJson(value);
		holder.appendChild(fallback);

		loadJsonLensModule().then((module) => {
			if (!module || !module.JsonLens || typeof module.JsonLens.createElement !== 'function') {
				return;
			}

			const plugins = [
				module.TreeViewPlugin,
				module.SyntaxHighlightPlugin,
				module.ClipboardPlugin,
				module.SearchPlugin,
				module.PathPlugin,
				module.RawViewPlugin
			].filter(Boolean);

			try {
				const parsed = typeof value === 'string' && value.trim() !== ''
					? JSON.parse(value)
					: value;
				const lens = module.JsonLens.createElement({
					value: parsed,
					mode: 'tree',
					collapsedDepth: 3,
					showToolbar: true,
					plugins
				});

				if (lens) {
					holder.replaceChildren(lens);
				}
			} catch (error) {
				// The plain JSON fallback remains visible.
			}
		}).catch(() => {});

		return holder;
	}

	async function postJson(payload) {
		const response = await fetch(ENDPOINT_URL, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: JSON.stringify(payload)
		});
		const text = await response.text();
		let data = null;
		try {
			data = JSON.parse(text);
		} catch (error) {
			throw new Error('Invalid JSON response: ' + text.slice(0, 600));
		}
		if (!response.ok) throw new Error(data && data.error ? data.error : 'HTTP ' + response.status);
		return data;
	}

	async function copyText(text) {
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			await navigator.clipboard.writeText(text);
			return;
		}
		const area = document.createElement('textarea');
		area.value = text;
		document.body.appendChild(area);
		area.select();
		document.execCommand('copy');
		area.remove();
	}

	function getFullscreenTarget(source) {
		if (source instanceof HTMLElement) {
			return source.closest('.mg-row-detail') || source.closest('.agent-preset-detail') || source;
		}

		return null;
	}

	async function toggleDetailFullscreen(source) {
		const target = getFullscreenTarget(source);

		if (!target) {
			setLog('Fullscreen target not found.');
			return;
		}

		try {
			if (document.fullscreenElement === target) {
				await document.exitFullscreen();
				return;
			}

			if (document.fullscreenElement && document.fullscreenElement !== target) {
				await document.exitFullscreen();
			}

			if (typeof target.requestFullscreen === 'function') {
				await target.requestFullscreen();
				setLog('Opened preset test detail in fullscreen.');
				return;
			}

			setLog('Fullscreen API is not supported by this browser.');
		} catch (error) {
			setLog('Could not open fullscreen: ' + getText(error && error.message, String(error)));
		}
	}

	function createFullscreenButton() {
		const button = createButton('Fullscreen');
		button.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			toggleDetailFullscreen(button);
		});

		return button;
	}

	function createPills(values, extraClass = '') {
		const row = createElement('agent-preset-pill-row');
		const items = Array.isArray(values) ? values : String(values || '').split(',');
		items.map((item) => String(item).trim()).filter(Boolean).forEach((item) => {
			const pill = createElement('agent-preset-pill' + (extraClass ? ' ' + extraClass : ''), item);
			row.appendChild(pill);
		});
		return row;
	}

	function renderPreset(value, row) {
		const cell = createElement('agent-preset-cell');
		cell.appendChild(createElement('agent-preset-cell-main', getText(value)));
		cell.appendChild(createElement('agent-preset-cell-sub', getText(row.preset_id)));
		return cell;
	}

	function renderType(value, row) {
		const cell = createElement('agent-preset-cell');
		cell.appendChild(createElement('agent-preset-cell-main', getText(value)));
		cell.appendChild(createElement('agent-preset-cell-sub', row.enabled ? 'enabled' : 'disabled'));
		return cell;
	}

	function renderStatus(value) {
		return createPills([getText(value)], value === 'configured' ? 'agent-preset-pill-strong' : 'agent-preset-status-disabled');
	}

	async function loadRemoteDetail(row) {
		const presetId = getText(row && row.preset_id, '');
		if (!presetId) throw new Error('Missing preset id.');
		const response = await postJson({ mode: 'detail', preset_id: presetId, context_vars: {} });
		if (!response || !response.found || !response.detail) {
			throw new Error('Preset detail could not be materialized: ' + presetId);
		}
		return response.detail;
	}

	function createDetailLoadingPlaceholder(row) {
		return createElement('agent-preset-startup', 'Materializing ' + getText(row && row.preset_id) + '...');
	}

	function createDetailErrorPlaceholder(row, error) {
		return createElement('agent-preset-startup agent-preset-startup-error', 'Failed to load ' + getText(row && row.preset_id) + ': ' + getText(error && error.message ? error.message : error));
	}

	function createField(label, control, full = false, hint = '') {
		const field = createElement('agent-preset-field' + (full ? ' agent-preset-field-full' : ''));
		const labelElement = document.createElement('label');
		labelElement.className = 'agent-preset-label';
		labelElement.textContent = label;
		field.appendChild(labelElement);
		field.appendChild(control);
		if (hint) field.appendChild(createElement('agent-preset-hint', hint));
		return field;
	}

	function createInput(value = '') {
		const input = document.createElement('input');
		input.type = 'text';
		input.className = 'agent-preset-input';
		input.value = value;
		return input;
	}

	function createTextarea(value = '', rows = 8) {
		const textarea = document.createElement('textarea');
		textarea.className = 'agent-preset-input';
		textarea.rows = rows;
		textarea.value = value;
		return textarea;
	}

	function isPlainObject(value) {
		return !!value && typeof value === 'object' && !Array.isArray(value);
	}

	function getSchemaTypes(schema) {
		if (!isPlainObject(schema)) return [];
		const raw = Array.isArray(schema.type) ? schema.type : (schema.type ? [schema.type] : []);
		return raw.map(String).filter(Boolean);
	}

	function getPrimarySchemaType(schema) {
		const types = getSchemaTypes(schema).filter((type) => type !== 'null');
		if (types.length === 1) return types[0];
		if (types.length > 1) return 'union';
		if (Array.isArray(schema && schema.enum) && schema.enum.length > 0) {
			const value = schema.enum.find((item) => item !== null);
			if (Array.isArray(value)) return 'array';
			if (isPlainObject(value)) return 'object';
			if (value !== undefined) return typeof value;
		}
		if (schema && schema.const !== undefined) {
			if (Array.isArray(schema.const)) return 'array';
			if (isPlainObject(schema.const)) return 'object';
			return typeof schema.const;
		}
		return 'any';
	}

	function hasCompositeSchema(schema) {
		return isPlainObject(schema) && (
			(Array.isArray(schema.oneOf) && schema.oneOf.length > 0)
			|| (Array.isArray(schema.anyOf) && schema.anyOf.length > 0)
			|| (Array.isArray(schema.allOf) && schema.allOf.length > 0)
			|| getPrimarySchemaType(schema) === 'union'
		);
	}

	function buildSchemaHint(schema) {
		if (!isPlainObject(schema)) return '';
		const details = [];
		const type = getPrimarySchemaType(schema);
		if (type && type !== 'any') details.push('Type: ' + type);
		if (schema.format) details.push('Format: ' + schema.format);
		if (schema.minimum !== undefined) details.push('Min: ' + schema.minimum);
		if (schema.maximum !== undefined) details.push('Max: ' + schema.maximum);
		if (schema.minLength !== undefined) details.push('Min length: ' + schema.minLength);
		if (schema.maxLength !== undefined) details.push('Max length: ' + schema.maxLength);
		if (schema.pattern) details.push('Pattern: ' + schema.pattern);
		if (schema.default !== undefined) details.push('Default: ' + stringifyJson(schema.default).replace(/\s+/g, ' '));
		const description = getText(schema.description, '');
		return [description, details.join(' · ')].filter(Boolean).join('\n');
	}

	function defaultValueForSchema(schema) {
		if (!isPlainObject(schema)) return '';
		if (schema.default !== undefined) return schema.default;
		if (schema.const !== undefined) return schema.const;
		if (Array.isArray(schema.enum) && schema.enum.length > 0) return schema.enum[0];
		const type = getPrimarySchemaType(schema);
		if (type === 'boolean') return false;
		if (type === 'integer' || type === 'number') return 0;
		if (type === 'array') return [];
		if (type === 'object') return {};
		if (type === 'null') return null;
		return '';
	}

	function buildDefaultArguments(definition) {
		const parameters = isPlainObject(definition && definition.parameters) ? definition.parameters : {};
		const properties = isPlainObject(parameters.properties) ? parameters.properties : {};
		const required = new Set(Array.isArray(parameters.required) ? parameters.required.map(String) : []);
		const result = {};
		Object.keys(properties).forEach((key) => {
			const schema = isPlainObject(properties[key]) ? properties[key] : {};
			if (!required.has(key) && schema.default === undefined && schema.const === undefined) return;
			result[key] = defaultValueForSchema(schema);
		});
		return result;
	}

	function getInputType(schema) {
		const format = String(schema && schema.format || '').toLowerCase();
		if (format === 'date') return 'date';
		if (format === 'date-time' || format === 'datetime') return 'datetime-local';
		if (format === 'email') return 'email';
		if (format === 'uri' || format === 'url') return 'url';
		if (format === 'password') return 'password';
		return 'text';
	}

	function shouldUseTextarea(name, schema) {
		const format = String(schema && schema.format || '').toLowerCase();
		if (format === 'textarea' || format === 'multiline') return true;
		if (Number(schema && schema.maxLength || 0) > 240) return true;
		return /(prompt|content|body|message|instructions|markdown|html|text)$/i.test(String(name || ''));
	}

	function setControlDisabled(control, disabled) {
		if (!control) return;
		if ('disabled' in control) control.disabled = disabled;
		control.querySelectorAll && control.querySelectorAll('input, select, textarea, button').forEach((item) => {
			item.disabled = disabled;
		});
	}

	function parseStructuredValue(raw, expectedType, label) {
		let value = null;
		try {
			value = JSON.parse(String(raw || '').trim() || (expectedType === 'array' ? '[]' : '{}'));
		} catch (error) {
			throw new Error(label + ' must contain valid JSON.');
		}
		if (expectedType === 'array' && !Array.isArray(value)) throw new Error(label + ' must be a JSON array.');
		if (expectedType === 'object' && !isPlainObject(value)) throw new Error(label + ' must be a JSON object.');
		return value;
	}

	function parseAnyValue(raw) {
		const value = String(raw || '');
		if (value.trim() === '') return '';
		try {
			return JSON.parse(value);
		} catch (error) {
			return value;
		}
	}

	function createArgumentControl(name, schema, required, initialValue, onChange) {
		schema = isPlainObject(schema) ? schema : {};
		const type = hasCompositeSchema(schema) ? 'union' : getPrimarySchemaType(schema);
		const complex = ['object', 'array', 'union'].includes(type);
		const field = createElement('agent-preset-schema-field' + (complex ? ' agent-preset-schema-field-complex' : ''));
		const header = createElement('agent-preset-schema-field-header');
		const title = createElement('agent-preset-schema-field-title');
		title.appendChild(document.createTextNode(name));
		title.appendChild(createElement(required ? 'agent-preset-schema-required' : 'agent-preset-schema-optional', required ? 'required' : 'optional'));
		header.appendChild(title);

		let included = required || initialValue !== undefined;
		let include = null;
		if (!required) {
			const includeLabel = document.createElement('label');
			includeLabel.className = 'agent-preset-schema-include';
			include = document.createElement('input');
			include.type = 'checkbox';
			include.checked = included;
			includeLabel.appendChild(include);
			includeLabel.appendChild(document.createTextNode('include'));
			header.appendChild(includeLabel);
		}
		field.appendChild(header);

		let control = null;
		let readValue = null;
		let writeValue = null;
		const value = initialValue !== undefined ? initialValue : defaultValueForSchema(schema);

		if (schema.const !== undefined) {
			control = createInput(stringifyJson(schema.const).replace(/^"|"$/g, ''));
			control.readOnly = true;
			readValue = () => schema.const;
			writeValue = () => {};
		} else if (Array.isArray(schema.enum) && schema.enum.length > 0) {
			control = document.createElement('select');
			control.className = 'agent-preset-input';
			schema.enum.forEach((item) => {
				const option = document.createElement('option');
				option.value = JSON.stringify(item);
				option.textContent = item === null ? 'null' : (typeof item === 'string' ? item : stringifyJson(item).replace(/\s+/g, ' '));
				control.appendChild(option);
			});
			readValue = () => JSON.parse(control.value);
			writeValue = (next) => {
				const encoded = JSON.stringify(next);
				if (Array.from(control.options).some((option) => option.value === encoded)) control.value = encoded;
			};
			writeValue(value);
		} else if (type === 'boolean') {
			const wrapper = document.createElement('label');
			wrapper.className = 'agent-preset-checkbox-row';
			control = document.createElement('input');
			control.type = 'checkbox';
			control.checked = Boolean(value);
			wrapper.appendChild(control);
			wrapper.appendChild(document.createTextNode('Enabled'));
			field.appendChild(wrapper);
			readValue = () => control.checked;
			writeValue = (next) => { control.checked = Boolean(next); };
		} else if (type === 'integer' || type === 'number') {
			control = createInput(value === undefined || value === null ? '' : String(value));
			control.type = 'number';
			control.step = type === 'integer' ? '1' : (schema.multipleOf !== undefined ? String(schema.multipleOf) : 'any');
			if (schema.minimum !== undefined) control.min = String(schema.minimum);
			if (schema.maximum !== undefined) control.max = String(schema.maximum);
			readValue = () => {
				if (control.value.trim() === '') throw new Error(name + ' requires a number.');
				const number = Number(control.value);
				if (!Number.isFinite(number)) throw new Error(name + ' requires a valid number.');
				if (type === 'integer' && !Number.isInteger(number)) throw new Error(name + ' requires an integer.');
				return number;
			};
			writeValue = (next) => { control.value = next === undefined || next === null ? '' : String(next); };
		} else if (type === 'object' || type === 'array' || type === 'union') {
			control = createTextarea(stringifyJson(value), type === 'union' ? 5 : 7);
			readValue = () => type === 'union' ? JSON.parse(control.value || 'null') : parseStructuredValue(control.value, type, name);
			writeValue = (next) => { control.value = stringifyJson(next === undefined ? defaultValueForSchema(schema) : next); };
		} else if (type === 'string') {
			control = shouldUseTextarea(name, schema) ? createTextarea(String(value ?? ''), 5) : createInput(String(value ?? ''));
			if (!shouldUseTextarea(name, schema)) control.type = getInputType(schema);
			if (schema.minLength !== undefined) control.minLength = Number(schema.minLength);
			if (schema.maxLength !== undefined) control.maxLength = Number(schema.maxLength);
			if (schema.pattern) control.pattern = String(schema.pattern);
			readValue = () => control.value;
			writeValue = (next) => { control.value = next === undefined || next === null ? '' : String(next); };
		} else {
			control = createInput(typeof value === 'string' ? value : stringifyJson(value).replace(/\s+/g, ' '));
			readValue = () => parseAnyValue(control.value);
			writeValue = (next) => {
				control.value = typeof next === 'string' ? next : stringifyJson(next).replace(/\s+/g, ' ');
			};
		}

		if (type !== 'boolean') field.appendChild(control);
		const hint = buildSchemaHint(schema);
		if (hint) field.appendChild(createElement('agent-preset-hint', hint));

		function updateEnabledState() {
			included = required || !include || include.checked;
			setControlDisabled(control, !included);
			field.classList.toggle('agent-preset-schema-field-disabled', !included);
		}

		if (include) {
			include.addEventListener('change', () => {
				updateEnabledState();
				onChange();
			});
		}
		control.addEventListener('input', onChange);
		control.addEventListener('change', onChange);
		updateEnabledState();

		return {
			root: field,
			read() {
				if (!included) return { included: false, value: undefined };
				return { included: true, value: readValue() };
			},
			write(nextValue, present) {
				if (include) include.checked = Boolean(present);
				included = required || Boolean(present);
				if (present || required) writeValue(nextValue);
				updateEnabledState();
			},
			reset() {
				const present = required || schema.default !== undefined || schema.const !== undefined;
				this.write(defaultValueForSchema(schema), present);
			}
		};
	}

	function validateSchemaValue(name, value, schema) {
		if (!isPlainObject(schema) || hasCompositeSchema(schema)) return;
		const type = getPrimarySchemaType(schema);
		if (type === 'string' && typeof value !== 'string') throw new Error(name + ' must be a string.');
		if (type === 'boolean' && typeof value !== 'boolean') throw new Error(name + ' must be a boolean.');
		if (type === 'integer' && (!Number.isInteger(value))) throw new Error(name + ' must be an integer.');
		if (type === 'number' && (typeof value !== 'number' || !Number.isFinite(value))) throw new Error(name + ' must be a number.');
		if (type === 'array' && !Array.isArray(value)) throw new Error(name + ' must be an array.');
		if (type === 'object' && !isPlainObject(value)) throw new Error(name + ' must be an object.');
		if (Array.isArray(schema.enum) && !schema.enum.some((item) => JSON.stringify(item) === JSON.stringify(value))) {
			throw new Error(name + ' must use one of the allowed values.');
		}
	}

	function createArgumentSchemaForm(definition, argsEditor) {
		const parameters = isPlainObject(definition && definition.parameters) ? definition.parameters : {};
		const properties = isPlainObject(parameters.properties) ? parameters.properties : {};
		const propertyNames = Object.keys(properties);
		const required = new Set(Array.isArray(parameters.required) ? parameters.required.map(String) : []);
		const root = createElement('agent-preset-schema-form');
		const controls = new Map();
		const extraNotice = createElement('agent-preset-schema-notice agent-preset-schema-extra');
		let extraArguments = {};
		let syncing = false;

		function readControls() {
			const result = Object.assign({}, extraArguments);
			controls.forEach((control, name) => {
				const entry = control.read();
				if (entry.included) result[name] = entry.value;
				else delete result[name];
			});
			return result;
		}

		function syncToJson() {
			if (syncing) return;
			try {
				argsEditor.value = stringifyJson(readControls());
			} catch (error) {
				// Keep the current JSON while a field contains an incomplete value.
			}
		}

		function updateExtraNotice() {
			const names = Object.keys(extraArguments);
			extraNotice.textContent = names.length > 0
				? 'Additional JSON arguments preserved outside the generated fields: ' + names.join(', ')
				: '';
			extraNotice.style.display = names.length > 0 ? '' : 'none';
		}

		if (propertyNames.length === 0) {
			const hasSchema = Object.keys(parameters).length > 0;
			const noArguments = hasSchema && String(parameters.type || 'object') === 'object' && parameters.additionalProperties !== true;
			root.appendChild(createElement('agent-preset-schema-notice', noArguments
				? 'This function declares no arguments. It can be executed with an empty object.'
				: 'This function does not expose named parameter properties. Use Advanced arguments JSON to provide the call payload.'));
			argsEditor.value = stringifyJson(buildDefaultArguments(definition));
			return {
				root,
				reset() { argsEditor.value = stringifyJson(buildDefaultArguments(definition)); },
				apply(args) { argsEditor.value = stringifyJson(args); },
				validate(args) {
					if (!isPlainObject(args)) throw new Error('Arguments must be a JSON object.');
					if (noArguments && Object.keys(args).length > 0 && parameters.additionalProperties === false) {
						throw new Error('This function does not allow arguments.');
					}
				}
			};
		}

		root.appendChild(createElement('agent-preset-schema-notice', 'The fields below are generated from the selected function input schema. Optional values are sent only when “include” is enabled.'));
		propertyNames.forEach((name) => {
			const schema = isPlainObject(properties[name]) ? properties[name] : {};
			const defaults = buildDefaultArguments(definition);
			const control = createArgumentControl(
				name,
				schema,
				required.has(name),
				Object.prototype.hasOwnProperty.call(defaults, name) ? defaults[name] : undefined,
				syncToJson
			);
			controls.set(name, control);
			root.appendChild(control.root);
		});
		root.appendChild(extraNotice);
		updateExtraNotice();
		syncToJson();

		return {
			root,
			reset() {
				syncing = true;
				extraArguments = {};
				controls.forEach((control) => control.reset());
				syncing = false;
				updateExtraNotice();
				syncToJson();
			},
			apply(args) {
				if (!isPlainObject(args)) throw new Error('Arguments must be a JSON object.');
				syncing = true;
				extraArguments = {};
				Object.keys(args).forEach((name) => {
					if (!controls.has(name)) extraArguments[name] = args[name];
				});
				controls.forEach((control, name) => {
					control.write(args[name], Object.prototype.hasOwnProperty.call(args, name));
				});
				syncing = false;
				updateExtraNotice();
				argsEditor.value = stringifyJson(args);
			},
			validate(args) {
				if (!isPlainObject(args)) throw new Error('Arguments must be a JSON object.');
				required.forEach((name) => {
					if (!Object.prototype.hasOwnProperty.call(args, name)) throw new Error('Missing required argument: ' + name);
				});
				if (parameters.additionalProperties === false) {
					const unknown = Object.keys(args).filter((name) => !Object.prototype.hasOwnProperty.call(properties, name));
					if (unknown.length > 0) throw new Error('Unknown arguments are not allowed: ' + unknown.join(', '));
				}
				Object.keys(args).forEach((name) => {
					if (Object.prototype.hasOwnProperty.call(properties, name)) validateSchemaValue(name, args[name], properties[name]);
				});
			}
		};
	}

	function renderFunctionSummary(definition) {
		const box = createElement('agent-preset-tool-intro');
		box.appendChild(createElement('agent-preset-tool-name', getText(definition && definition.label, definition && definition.name) + ' · ' + getText(definition && definition.name)));
		if (definition && definition.description) box.appendChild(createElement('agent-preset-tool-description', definition.description));
		const pills = [];
		if (definition && definition.category) pills.push('Category: ' + definition.category);
		(Array.isArray(definition && definition.tags) ? definition.tags : []).forEach((tag) => pills.push(String(tag)));
		if (definition && definition.mutation) pills.push('Mutation');
		else if (definition && definition.read_only) pills.push('Read only');
		if (definition && definition.requires_approval) pills.push('Approval required');
		if (definition && definition.commit_guard_required) pills.push('Commit guard');
		if (definition && definition.side_effect) pills.push('Side effect');
		const count = Number(definition && definition.parameter_count || 0);
		const requiredCount = Array.isArray(definition && definition.required_parameters) ? definition.required_parameters.length : 0;
		pills.push(count + ' parameter' + (count === 1 ? '' : 's'));
		if (requiredCount > 0) pills.push(requiredCount + ' required');
		box.appendChild(createPills(pills));
		return box;
	}

	function renderWarnings(warnings) {
		const list = createElement('agent-preset-warning-list');
		(Array.isArray(warnings) ? warnings : []).forEach((warning) => {
			list.appendChild(createElement('agent-preset-warning-item', String(warning)));
		});
		return list;
	}

	function renderOverviewPanel(payload) {
		const panel = createElement('agent-preset-form');
		panel.appendChild(createElement('agent-preset-detail-title', 'Materialization'));
		panel.appendChild(createJsonValue({
			ready: payload.ready,
			capabilities: payload.capabilities,
			resource: payload.resource,
			docks: payload.docks,
			warnings: payload.warnings
		}));
		return panel;
	}

	function renderToolPanel(payload, resultRoot) {
		const tool = payload.tool || {};
		const functions = Array.isArray(tool.functions) ? tool.functions : [];
		const panel = createElement('agent-preset-form');
		const grid = createElement('agent-preset-form-grid');
		const select = document.createElement('select');
		select.className = 'agent-preset-input';
		functions.forEach((definition) => {
			const option = document.createElement('option');
			option.value = getText(definition.name, '');
			option.textContent = getText(definition.label, definition.name) + ' (' + getText(definition.name) + ')';
			select.appendChild(option);
		});

		const functionSummary = createElement('agent-preset-field-full');
		const argumentFormHost = createElement('agent-preset-field-full');
		const argsEditor = createTextarea('{}', 12);
		const contextEditor = createTextarea('{}', 7);
		const status = createElement('agent-preset-status');
		let argumentController = null;

		function selectedDefinition() {
			return functions.find((item) => String(item.name || '') === select.value) || null;
		}

		function refreshDefinition() {
			const definition = selectedDefinition();
			functionSummary.replaceChildren();
			argumentFormHost.replaceChildren();
			if (!definition) {
				functionSummary.appendChild(createElement('agent-preset-schema-notice', 'This preset exposes no callable tool definitions.'));
				argumentController = null;
				argsEditor.value = '{}';
				return;
			}
			functionSummary.appendChild(renderFunctionSummary(definition));
			argumentController = createArgumentSchemaForm(definition, argsEditor);
			argumentFormHost.appendChild(argumentController.root);
			status.textContent = '';
			status.className = 'agent-preset-status';
		}

		select.addEventListener('change', refreshDefinition);
		grid.appendChild(createField('Function', select, true, 'Select a concrete callable function. Its description and input schema are shown below.'));
		grid.appendChild(functionSummary);
		grid.appendChild(argumentFormHost);
		grid.appendChild(createField('Agent context variables JSON', contextEditor, true, 'Optional run-local variables, for example user, session, tenant, or audit context values.'));
		panel.appendChild(grid);

		const advanced = document.createElement('details');
		advanced.className = 'agent-preset-advanced';
		const advancedSummary = document.createElement('summary');
		advancedSummary.textContent = 'Advanced arguments JSON';
		advanced.appendChild(advancedSummary);
		const advancedBody = createElement('agent-preset-advanced-body');
		advancedBody.appendChild(createField('Arguments JSON', argsEditor, true, 'The generated form synchronizes into this object. Manual edits are used for execution; click “Apply JSON to form” to update the visible fields.'));
		const advancedActions = createElement('agent-preset-actions');
		const applyJson = createButton('Apply JSON to form');
		const formatJson = createButton('Format JSON');
		const reset = createButton('Reset arguments');
		const copy = createButton('Copy arguments');
		advancedActions.appendChild(applyJson);
		advancedActions.appendChild(formatJson);
		advancedActions.appendChild(reset);
		advancedActions.appendChild(copy);
		advancedBody.appendChild(advancedActions);
		advanced.appendChild(advancedBody);
		panel.appendChild(advanced);

		const actions = createElement('agent-preset-actions');
		const run = createButton('Run function', 'agent-preset-button-primary');
		actions.appendChild(run);
		panel.appendChild(actions);
		panel.appendChild(status);

		applyJson.addEventListener('click', () => {
			try {
				const args = parseObjectJson(argsEditor.value, 'Arguments');
				if (argumentController) argumentController.apply(args);
				status.textContent = 'JSON arguments applied to the generated form.';
				status.className = 'agent-preset-status agent-preset-status-ok';
			} catch (error) {
				status.textContent = getText(error && error.message ? error.message : error);
				status.className = 'agent-preset-status agent-preset-status-error';
			}
		});

		formatJson.addEventListener('click', () => {
			try {
				argsEditor.value = stringifyJson(parseObjectJson(argsEditor.value, 'Arguments'));
				status.textContent = 'Arguments JSON formatted.';
				status.className = 'agent-preset-status agent-preset-status-ok';
			} catch (error) {
				status.textContent = getText(error && error.message ? error.message : error);
				status.className = 'agent-preset-status agent-preset-status-error';
			}
		});

		reset.addEventListener('click', () => {
			if (argumentController) argumentController.reset();
			else argsEditor.value = '{}';
			status.textContent = 'Arguments reset from the input schema.';
			status.className = 'agent-preset-status agent-preset-status-ok';
		});

		copy.addEventListener('click', async () => {
			await copyText(argsEditor.value);
			status.textContent = 'Arguments copied.';
			status.className = 'agent-preset-status agent-preset-status-ok';
		});

		run.addEventListener('click', async () => {
			try {
				const definition = selectedDefinition();
				if (!definition) throw new Error('Select a callable function first.');
				const args = parseObjectJson(argsEditor.value, 'Arguments');
				if (argumentController) argumentController.validate(args);
				const contextVars = parseObjectJson(contextEditor.value, 'Context variables');
				run.disabled = true;
				status.textContent = 'Running through action policy...';
				status.className = 'agent-preset-status';
				const response = await postJson({
					mode: 'call_tool',
					preset_id: payload.preset_id,
					function_name: select.value,
					arguments: args,
					context_vars: contextVars
				});
				await handleToolResponse(response, payload, contextVars, resultRoot, status);
			} catch (error) {
				status.textContent = getText(error && error.message ? error.message : error);
				status.className = 'agent-preset-status agent-preset-status-error';
				resultRoot.replaceChildren(createJsonValue({ ok: false, error: status.textContent }));
			} finally {
				run.disabled = false;
			}
		});

		select.disabled = functions.length === 0;
		run.disabled = functions.length === 0;
		refreshDefinition();
		panel.appendChild(createElement('agent-preset-detail-title', 'Effective tool definitions'));
		panel.appendChild(createJsonValue(tool.definitions_json || functions));
		return panel;
	}

	async function handleToolResponse(response, payload, contextVars, resultRoot, status) {
		resultRoot.replaceChildren(createJsonValue(response));
		if (!response || response.requires_confirmation !== true) {
			status.textContent = response && response.ok ? 'Tool executed.' : 'Tool was blocked or failed.';
			status.className = 'agent-preset-status ' + (response && response.ok ? 'agent-preset-status-ok' : 'agent-preset-status-error');
			setLog('Tool test completed for ' + payload.preset_id + '.');
			return;
		}

		const request = Array.isArray(response.interaction_requests) ? response.interaction_requests[0] : null;
		if (!request) throw new Error('Confirmation response does not contain an interaction request.');
		status.textContent = 'Explicit confirmation required.';
		status.className = 'agent-preset-status agent-preset-status-error';
		resultRoot.replaceChildren(renderConfirmation(response, request, payload, contextVars, resultRoot, status));
		setLog('Approval required for ' + getText(request.action && request.action.name, payload.preset_id) + '.');
	}

	function renderInteractionSummary(summary) {
		const list = document.createElement('dl');
		list.className = 'agent-preset-confirmation-summary';
		const entries = summary && typeof summary === 'object' && !Array.isArray(summary)
			? Object.entries(summary)
			: [];

		if (entries.length === 0) {
			const empty = createElement('agent-preset-detail-summary', 'No additional user-facing summary is available.');
			return empty;
		}

		entries.forEach(([label, value]) => {
			const term = document.createElement('dt');
			term.textContent = String(label);
			const description = document.createElement('dd');
			description.textContent = formatInteractionValue(value);
			list.appendChild(term);
			list.appendChild(description);
		});

		return list;
	}

	function renderInteractionTechnicalDetails(action) {
		const details = document.createElement('details');
		details.className = 'agent-preset-confirmation-details';
		const summary = document.createElement('summary');
		summary.textContent = 'Technical details';
		details.appendChild(summary);
		details.appendChild(createJsonValue({
			tool: getText(action && action.name, ''),
			input: action && action.input && typeof action.input === 'object' ? action.input : {}
		}));
		return details;
	}

	function formatInteractionValue(value) {
		if (value === null || value === undefined || value === '') return '-';
		if (typeof value === 'boolean') return value ? 'Yes' : 'No';
		if (typeof value === 'object') return stringifyJson(value);
		return String(value);
	}

	function renderConfirmation(response, request, payload, contextVars, resultRoot, status) {
		const card = createElement('agent-preset-confirmation');
		card.appendChild(createElement('agent-preset-confirmation-title', getText(request.title, 'Confirm tool action')));
		card.appendChild(createElement('agent-preset-confirmation-risk', 'Risk: ' + getText(request.risk, 'medium')));
		card.appendChild(createElement('agent-preset-detail-summary', getText(request.message, 'Review the exact action before approval.')));
		card.appendChild(renderInteractionSummary(request.summary || {}));
		card.appendChild(renderInteractionTechnicalDetails(request.action || {}));
		const note = createInput('');
		card.appendChild(createField('Optional decision note', note, true));
		const actions = createElement('agent-preset-actions');
		const approve = createButton('Approve and execute', 'agent-preset-button-primary');
		const decline = createButton('Decline', 'agent-preset-button-danger');
		actions.appendChild(approve);
		actions.appendChild(decline);
		card.appendChild(actions);

		async function decide(decision) {
			approve.disabled = true;
			decline.disabled = true;
			status.textContent = decision === 'approve' ? 'Executing approved action...' : 'Declining action...';
			try {
				const resumed = await postJson({
					mode: 'resume_tool',
					preset_id: payload.preset_id,
					resume_handle: response.resume_handle,
					request_id: request.id,
					decision,
					note: note.value,
					context_vars: contextVars
				});
				resultRoot.replaceChildren(createJsonValue(resumed));
				status.textContent = decision === 'deny'
					? 'Action declined.'
					: (resumed && resumed.ok ? 'Approved action executed.' : 'Approved action was blocked or failed.');
				status.className = 'agent-preset-status ' + (decision === 'deny' || (resumed && resumed.ok) ? 'agent-preset-status-ok' : 'agent-preset-status-error');
				setLog(decision === 'approve' ? 'Approved tool action completed.' : 'Tool action declined.');
			} catch (error) {
				status.textContent = getText(error && error.message ? error.message : error);
				status.className = 'agent-preset-status agent-preset-status-error';
				resultRoot.replaceChildren(createJsonValue({ ok: false, error: status.textContent }));
			} finally {
				approve.disabled = false;
				decline.disabled = false;
			}
		}

		approve.addEventListener('click', () => decide('approve'));
		decline.addEventListener('click', () => decide('deny'));
		return card;
	}

	function renderContextPanel(payload, resultRoot) {
		const panel = createElement('agent-preset-form');
		const contextEditor = createTextarea('{}', 10);
		const status = createElement('agent-preset-status');
		panel.appendChild(createField('Agent context variables JSON', contextEditor, true));
		const run = createButton('Run context contributor', 'agent-preset-button-primary');
		panel.appendChild(run);
		panel.appendChild(status);
		run.addEventListener('click', async () => {
			try {
				run.disabled = true;
				const response = await postJson({
					mode: 'context_contribute',
					preset_id: payload.preset_id,
					context_vars: parseObjectJson(contextEditor.value, 'Context variables')
				});
				resultRoot.replaceChildren(createJsonValue(response));
				status.textContent = response.ok ? 'Context contribution generated.' : 'Context contribution failed.';
				status.className = 'agent-preset-status ' + (response.ok ? 'agent-preset-status-ok' : 'agent-preset-status-error');
				setLog('Context contributor tested for ' + payload.preset_id + '.');
			} catch (error) {
				status.textContent = getText(error && error.message ? error.message : error);
				status.className = 'agent-preset-status agent-preset-status-error';
			} finally {
				run.disabled = false;
			}
		});
		return panel;
	}

	function renderMemoryPanel(payload, resultRoot) {
		const panel = createElement('agent-preset-form');
		const grid = createElement('agent-preset-form-grid');
		const nodeId = createInput(MEMORY_TEST_NODE_PREFIX + Math.random().toString(36).slice(2, 10));
		const role = createInput('user');
		const content = createTextarea('Preset memory test message', 5);
		const messageId = createInput('');
		const feedback = createInput('helpful');
		const contextEditor = createTextarea('{}', 5);
		const status = createElement('agent-preset-status');
		grid.appendChild(createField('Node id', nodeId, true, 'Writes are restricted server-side to isolated ids beginning with ' + MEMORY_TEST_NODE_PREFIX));
		grid.appendChild(createField('Role', role));
		grid.appendChild(createField('Message id for feedback', messageId));
		grid.appendChild(createField('Message content', content, true));
		grid.appendChild(createField('Feedback', feedback));
		grid.appendChild(createField('Context variables JSON', contextEditor, true));
		panel.appendChild(grid);

		const actions = createElement('agent-preset-actions');
		const load = createButton('Load history');
		const append = createButton('Append test message', 'agent-preset-button-primary');
		const setFeedback = createButton('Set feedback');
		const reset = createButton('Reset test node', 'agent-preset-button-danger');
		actions.appendChild(load);
		actions.appendChild(append);
		actions.appendChild(setFeedback);
		actions.appendChild(reset);
		panel.appendChild(actions);
		panel.appendChild(status);

		async function request(mode, extra = {}, requiresConfirmation = false) {
			if (requiresConfirmation && !window.confirm('Execute this state-changing memory test operation on the isolated test node?')) return;
			try {
				const response = await postJson(Object.assign({
					mode,
					preset_id: payload.preset_id,
					node_id: nodeId.value.trim(),
					context_vars: parseObjectJson(contextEditor.value, 'Context variables'),
					confirmed: requiresConfirmation
				}, extra));
				resultRoot.replaceChildren(createJsonValue(response));
				status.textContent = response.ok ? 'Memory operation completed.' : 'Memory operation returned a negative result.';
				status.className = 'agent-preset-status ' + (response.ok ? 'agent-preset-status-ok' : 'agent-preset-status-error');
				const history = response && Array.isArray(response.history) ? response.history : [];
				if (history.length > 0 && !messageId.value) {
					messageId.value = getText(history[history.length - 1].id, '');
				}
				setLog('Memory operation ' + mode + ' completed for ' + payload.preset_id + '.');
			} catch (error) {
				status.textContent = getText(error && error.message ? error.message : error);
				status.className = 'agent-preset-status agent-preset-status-error';
			}
		}

		load.addEventListener('click', () => request('memory_load'));
		append.addEventListener('click', () => request('memory_append', {
			message: { role: role.value.trim() || 'user', content: content.value }
		}, true));
		setFeedback.addEventListener('click', () => request('memory_feedback', {
			message_id: messageId.value.trim(),
			feedback: feedback.value
		}, true));
		reset.addEventListener('click', () => request('memory_reset', {}, true));
		return panel;
	}

	function renderAgentComponentPresetDetail(context) {
		const payload = context && context.payload ? context.payload : null;
		if (!payload || typeof payload !== 'object') return document.createTextNode(getText(payload));

		const wrapper = createElement('agent-preset-detail');
		const header = createElement('agent-preset-detail-header');
		const headerText = createElement();
		const headerActions = createElement('agent-preset-detail-actions');
		headerText.appendChild(createElement('agent-preset-detail-title', getText(payload.headline)));
		headerText.appendChild(createElement('agent-preset-detail-summary', getText(payload.summary)));
		if (payload.description) headerText.appendChild(createElement('agent-preset-detail-summary', payload.description));
		headerActions.appendChild(createPills(payload.badges || [], 'agent-preset-pill-strong'));
		headerActions.appendChild(createFullscreenButton());
		header.appendChild(headerText);
		header.appendChild(headerActions);
		wrapper.appendChild(header);
		if (Array.isArray(payload.warnings) && payload.warnings.length > 0) wrapper.appendChild(renderWarnings(payload.warnings));

		const detailLayout = createElement('agent-preset-detail-layout');
		const left = createElement('agent-preset-card');
		const right = createElement('agent-preset-card');
		const tabs = createElement('agent-preset-tabs');
		const content = createElement();
		const resultRoot = createElement();
		right.appendChild(createElement('agent-preset-detail-title', 'Test result'));
		right.appendChild(resultRoot);
		resultRoot.appendChild(createJsonValue({ preset_id: payload.preset_id, ready: payload.ready, capabilities: payload.capabilities }));

		const panels = [
			['Overview', () => renderOverviewPanel(payload), true],
			['Tool', () => renderToolPanel(payload, resultRoot), !!payload.tool],
			['Context', () => renderContextPanel(payload, resultRoot), !!payload.context],
			['Memory', () => renderMemoryPanel(payload, resultRoot), !!payload.memory],
			['Preset JSON', () => createJsonValue(payload.preset_json || {}), true]
		].filter((item) => item[2]);

		function activate(index) {
			Array.from(tabs.children).forEach((button, buttonIndex) => {
				button.classList.toggle('agent-preset-tab-active', buttonIndex === index);
			});
			content.replaceChildren(panels[index][1]());
		}

		panels.forEach((panel, index) => {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'agent-preset-tab';
			button.textContent = panel[0];
			button.addEventListener('click', () => activate(index));
			tabs.appendChild(button);
		});

		left.appendChild(tabs);
		left.appendChild(content);
		detailLayout.appendChild(left);
		detailLayout.appendChild(right);
		wrapper.appendChild(detailLayout);
		activate(0);
		return wrapper;
	}

	async function copyPreset(row) {
		if (!row) return;
		const response = await postJson({ mode: 'record', preset_id: row.preset_id });
		await copyText(stringifyJson(response.record));
		setLog('Copied preset ' + getText(row.preset_id) + '.');
	}

	async function initGrid(modularGridModule) {
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

		if (!AjaxAdapter || !ModularGrid) throw new Error('ModularGrid exports are incomplete.');

		const adapter = new AjaxAdapter({
			url: ENDPOINT_URL,
			method: 'POST',
			rowsPath: 'data',
			totalPath: 'total',
			mapRequest(request) {
				const sortKey = request.sortKey || 'name';
				return {
					mode: 'page',
					page: request.page || 1,
					pageSize: request.pageSize || BATCH_SIZE,
					search: request.search || '',
					sort: [{ key: sortKey, dir: request.sortDirection || 'asc', type: SORT_TYPES[sortKey] || 'string' }]
				};
			}
		});

		const grid = new ModularGrid(GRID_SELECTOR, {
			layout,
			adapter,
			dataMode: 'server',
			server: { searchDebounceMs: 220, watchStateKeys: ['query'] },
			features: { paging: false },
			pageSize: BATCH_SIZE,
			sort: { key: 'name', direction: 'asc' },
			plugins: [SearchPlugin, HeaderMenuPlugin, InfoPlugin, ColumnVisibilityPlugin, ResetPlugin, RowActionsPlugin, RowDetailPlugin, InfiniteScrollPlugin].filter(Boolean),
			pluginOptions: {
				search: { zone: 'topLine', order: 10, label: 'Search', placeholder: 'Search preset, type, capability or dock' },
				headerMenu: { showSortActions: true, showClearSortAction: true, showHideColumnAction: true },
				reset: { zone: 'topLine', order: 20, label: 'Reset', sections: ['query', 'columns', 'detailView'] },
				info: { zone: 'statusZone', order: 10, displayMode: 'loaded' },
				rowActions: {
					headerMenu: { enabled: true, buttonLabel: '...', items: [{ type: 'columnVisibility', label: 'Columns', showReset: true, resetLabel: 'Reset columns' }] },
					items: [{ key: 'copy-preset', label: 'Copy preset JSON', onClick(context) { copyPreset(context && context.row ? context.row : null); } }]
				},
				rowDetail: {
					rowIdKey: 'preset_id',
					clearOnDataReload: true,
					asyncDetail: {
						load(context) { return loadRemoteDetail(context.row); },
						renderLoading(context) { return createDetailLoadingPlaceholder(context.row); },
						renderError(context) { return createDetailErrorPlaceholder(context.row, context.error); },
						render(context) { return renderAgentComponentPresetDetail(context); }
					}
				},
				infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' }
			},
			columns: [
				{
					key: 'name', label: 'Preset', width: 290,
					headerMenu: { defaultSortKey: 'name', defaultSortDirection: 'asc', sortOptions: [{ key: 'name', label: 'Preset' }] },
					render(value, row) { return renderPreset(value, row); }
				},
				{
					key: 'type', label: 'Resource type', width: 280,
					headerMenu: { defaultSortKey: 'type', defaultSortDirection: 'asc', sortOptions: [{ key: 'type', label: 'Resource type' }] },
					render(value, row) { return renderType(value, row); }
				},
				{
					key: 'capabilities', label: 'Capabilities', width: 200,
					headerMenu: { defaultSortKey: 'capability_names', defaultSortDirection: 'asc', sortOptions: [{ key: 'capability_names', label: 'Capabilities' }] },
					render(value) { return createPills(value, 'agent-preset-pill-strong'); }
				},
				{
					key: 'dock_count', label: 'Docks', width: 95,
					headerMenu: { defaultSortKey: 'dock_count', defaultSortDirection: 'desc', sortOptions: [{ key: 'dock_count', label: 'Dock count' }] }
				},
				{
					key: 'status', label: 'Status', width: 120,
					headerMenu: { defaultSortKey: 'status', defaultSortDirection: 'asc', sortOptions: [{ key: 'status', label: 'Status' }] },
					render(value) { return renderStatus(value); }
				},
				{
					key: 'dock_names', label: 'Dock names', width: 220, visible: false,
					textDisplay: { strategy: 'clamp', lines: 3, expandable: true }
				},
				{
					key: 'description', label: 'Description', width: 420, visible: false,
					textDisplay: { strategy: 'clamp', lines: 3, expandable: true }
				}
			]
		});

		grid.on('data:appended', ({ appendedCount, totalLoaded }) => setLog('Loaded ' + appendedCount + ' more presets; ' + totalLoaded + ' currently loaded.'));
		grid.on('detail:loaded', (event) => setLog('Materialized preset ' + getText(event && event.rowId) + '.'));
		grid.on('detail:error', (event) => setLog('Preset detail failed: ' + getText(event && event.error && event.error.message ? event.error.message : event && event.error)));
		await grid.init();
		setLog('Initial preset batch loaded. Open a row to test its effective capabilities.');
	}

	(async function() {
		const root = document.querySelector(GRID_SELECTOR);
		if (!root || root.dataset.initialized === '1') return;
		root.dataset.initialized = '1';
		setStartupStatus('Loading ModularGrid module...');
		try {
			const module = await importFirst(MODULARGRID_URLS, 'ModularGrid');
			setStartupStatus('Initializing preset grid...');
			await initGrid(module);
		} catch (error) {
			const message = getText(error && error.message ? error.message : error);
			setStartupStatus('Agent Component Preset Tests could not be initialized.', message, true);
			setLog('Initialization failed: ' + message);
			console.error(error);
		}
	})();
</script>
