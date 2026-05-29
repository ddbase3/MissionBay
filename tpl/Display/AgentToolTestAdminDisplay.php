<?php
$resolve = $this->_['resolve'];

$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$jsonLensCssUrl = (string) $resolve('plugin/ClientStack/assets/jsonlens/styles/jsonlens.css');
$jsonLensJsUrl = (string) $resolve('plugin/ClientStack/assets/jsonlens/index.js');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularGridCssUrl, ENT_QUOTES); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars($jsonLensCssUrl, ENT_QUOTES); ?>" />

<style>
	.agent-tool-shell {
		max-width: 1700px;
	}

	.agent-tool-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.agent-tool-shell p {
		margin: 0 0 16px 0;
		max-width: 1120px;
		color: #555;
		line-height: 1.45;
	}

	.agent-tool-grid .agent-tool-panel {
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

	.agent-tool-grid .agent-tool-panel > * {
		flex: 0 0 auto;
	}

	.agent-tool-grid .agent-tool-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.agent-tool-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.agent-tool-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.agent-tool-grid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.agent-tool-grid .mg-input,
	.agent-tool-grid .mg-select,
	.agent-tool-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.agent-tool-grid input[type="search"].mg-input {
		width: 300px;
	}

	.agent-tool-grid .mg-select {
		width: auto;
		min-width: 96px;
	}

	.agent-tool-grid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.agent-tool-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.agent-tool-grid .mg-table thead th.mg-cell-pinned {
		z-index: 14;
	}

	.agent-tool-grid .mg-table th,
	.agent-tool-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.agent-tool-grid .mg-row-actions-cell,
	.agent-tool-grid .mg-row-actions-header {
		width: 54px;
		min-width: 54px;
		text-align: center;
	}

	.agent-tool-cell-stack {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.agent-tool-cell-main {
		font-weight: 600;
		color: #222;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.agent-tool-cell-sub {
		font-size: 12px;
		color: #666;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.agent-tool-pill-row {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		align-items: center;
	}

	.agent-tool-pill {
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

	.agent-tool-pill-strong {
		background: #f0f0f0;
		color: #222;
		border-color: #cfcfcf;
	}

	.agent-tool-output {
		margin-top: 12px;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.agent-tool-output strong {
		color: #222;
	}

	.agent-tool-startup {
		padding: 16px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.agent-tool-startup-error {
		border-color: #e4b9b9;
		background: #fff8f8;
		color: #8a1f1f;
	}

	.agent-tool-startup pre {
		white-space: pre-wrap;
		word-break: break-word;
		margin: 8px 0 0 0;
		font-size: 12px;
	}

	.agent-tool-detail {
		min-width: 0;
	}

	.agent-tool-detail-layout {
		display: grid;
		grid-template-columns: minmax(320px, 1fr) minmax(360px, 1.15fr);
		gap: 14px;
		align-items: start;
	}

	.agent-tool-detail-card {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 10px;
		min-width: 0;
	}

	.agent-tool-detail-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 10px;
	}

	.agent-tool-detail-title {
		font-weight: 600;
		font-size: 15px;
		color: #222;
	}

	.agent-tool-detail-summary {
		margin-top: 2px;
		font-size: 12px;
		color: #666;
		overflow-wrap: anywhere;
	}

	.agent-tool-detail-actions,
	.agent-tool-form-actions,
	.agent-tool-form-toolbar {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 6px;
	}

	.agent-tool-detail-actions {
		justify-content: flex-end;
		flex: 0 0 auto;
	}

	.agent-tool-button,
	.agent-tool-form-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 28px;
		padding: 4px 10px;
		border: 1px solid #cfcfcf;
		border-radius: 6px;
		background: #fff;
		color: #222;
		font-size: 12px;
		line-height: 1.25;
		cursor: pointer;
		white-space: nowrap;
	}

	.agent-tool-button:hover,
	.agent-tool-form-button:hover {
		background: #f5f5f5;
	}

	.agent-tool-button:focus-visible,
	.agent-tool-form-button:focus-visible {
		outline: 2px solid #86a8cf;
		outline-offset: 2px;
	}

	.agent-tool-form-button-primary {
		background: #222;
		border-color: #222;
		color: #fff;
	}

	.agent-tool-form-button-primary:hover {
		background: #444;
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

	.mg-row-detail:fullscreen .agent-tool-detail,
	.mg-row-detail:-webkit-full-screen .agent-tool-detail {
		display: block;
		width: auto;
		height: auto;
		min-height: 0;
	}

	.mg-row-detail:fullscreen .agent-tool-detail-layout,
	.mg-row-detail:-webkit-full-screen .agent-tool-detail-layout {
		grid-template-columns: minmax(360px, 0.95fr) minmax(420px, 1.25fr);
		max-width: 1800px;
		margin: 0 auto;
	}

	.mg-row-detail:fullscreen .agent-tool-json-fallback,
	.mg-row-detail:fullscreen .agent-tool-form-preview,
	.mg-row-detail:-webkit-full-screen .agent-tool-json-fallback,
	.mg-row-detail:-webkit-full-screen .agent-tool-form-preview {
		max-height: 620px;
	}

	.agent-tool-form {
		display: grid;
		gap: 10px;
	}

	.agent-tool-form-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 10px;
	}

	.agent-tool-form-title {
		font-weight: 600;
		color: #222;
	}

	.agent-tool-form-select,
	.agent-tool-form-input {
		width: 100%;
		max-width: 100%;
		min-height: 30px;
		border: 1px solid #cfcfcf;
		border-radius: 6px;
		background: #fff;
		padding: 4px 7px;
		font-size: 13px;
		box-sizing: border-box;
	}

	.agent-tool-form-select {
		min-width: 260px;
	}

	.agent-tool-form-checkbox-row {
		display: flex;
		align-items: center;
		gap: 6px;
		font-size: 13px;
		color: #333;
	}

	.agent-tool-form-textarea {
		min-height: 92px;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.4;
		resize: vertical;
	}

	.agent-tool-form-description,
	.agent-tool-form-empty,
	.agent-tool-form-status,
	.agent-tool-form-preview-title {
		font-size: 12px;
		color: #666;
		line-height: 1.4;
	}

	.agent-tool-form-status-error {
		color: #8a1f1f;
	}

	.agent-tool-form-status-ok {
		color: #276028;
	}

	.agent-tool-form-fields {
		display: grid;
		grid-template-columns: repeat(2, minmax(220px, 1fr));
		gap: 10px;
	}

	.agent-tool-form-field-full {
		grid-column: 1 / -1;
	}

	.agent-tool-form-label {
		display: block;
		margin-bottom: 3px;
		font-size: 12px;
		font-weight: 600;
		color: #333;
	}

	.agent-tool-form-hint {
		margin-top: 3px;
		font-size: 11px;
		color: #777;
		line-height: 1.35;
	}

	.agent-tool-json-fallback,
	.agent-tool-form-preview {
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

	.agent-tool-json-holder {
		min-width: 0;
	}

	.agent-tool-definition-panel {
		display: grid;
		gap: 8px;
		margin-top: 10px;
	}

	@media (max-width: 980px) {
		.agent-tool-detail-layout,
		.agent-tool-form-fields {
			grid-template-columns: 1fr;
		}

		.agent-tool-form-select {
			min-width: 0;
		}
	}

	@media (max-width: 720px) {
		.agent-tool-shell h1 {
			font-size: 21px;
		}

		.agent-tool-grid .mg-table-scroll {
			height: 420px;
		}
	}
</style>

<div class="agent-tool-shell">
	<h1>Agent tool test</h1>
	<p>
		Registered <code>IAgentTool</code> instances are listed below. Open a row to inspect the tool definitions and test one function through a generated form.
	</p>

	<div class="agent-tool-grid">
		<div id="agent-tool-test-grid">
			<div class="agent-tool-startup">Loading Agent Tool Test display...</div>
		</div>
		<div id="agent-tool-test-output" class="agent-tool-output"><strong>Last action:</strong> Waiting for initialization.</div>
	</div>
</div>

<script type="module">
	const ENDPOINT_URL = <?php echo json_encode((string) $this->_['service'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const MODULARGRID_URLS = [<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	const JSONLENS_URLS = [<?php echo json_encode($jsonLensJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	const GRID_SELECTOR = '#agent-tool-test-grid';
	const LOG_SELECTOR = '#agent-tool-test-output';
	const BATCH_SIZE = 50;
	const SORT_TYPES = {
		name: 'string',
		class: 'string',
		description: 'string',
		function_count: 'int',
		function_names: 'string',
		categories: 'string',
		tags: 'string'
	};

	let jsonLensModulePromise = null;

	const layout = {
		type: 'stack',
		className: 'mg-layout-root',
		children: [
			{
				type: 'zone',
				key: 'topLine',
				className: 'agent-tool-panel'
			},
			{
				type: 'view',
				key: 'main',
				className: 'agent-tool-main'
			},
			{
				type: 'zone',
				key: 'statusZone',
				className: 'agent-tool-panel'
			}
		]
	};

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

	function setLog(message) {
		const logElement = document.querySelector(LOG_SELECTOR);

		if (!logElement) {
			return;
		}

		logElement.innerHTML = '';
		const label = document.createElement('strong');
		label.textContent = 'Last action:';
		logElement.appendChild(label);
		logElement.appendChild(document.createTextNode(' ' + message));
	}

	function setStartupStatus(message, details = '', isError = false) {
		const root = document.querySelector(GRID_SELECTOR);

		if (!root) {
			return;
		}

		const box = createElement('agent-tool-startup' + (isError ? ' agent-tool-startup-error' : ''));
		box.appendChild(document.createTextNode(message));

		if (details) {
			const pre = document.createElement('pre');
			pre.textContent = details;
			box.appendChild(pre);
		}

		root.replaceChildren(box);
	}

	async function importFirst(urls, moduleLabel) {
		const attempts = Array.isArray(urls) ? urls.filter((url) => typeof url === 'string' && url.trim() !== '') : [];
		const errors = [];

		for (const url of attempts) {
			try {
				return await import(new URL(url, document.baseURI).href);
			} catch (error) {
				errors.push(url + ' => ' + (error && error.message ? error.message : String(error)));
			}
		}

		throw new Error('Could not load ' + moduleLabel + '. Tried:\n' + errors.join('\n'));
	}

	async function loadJsonLensModule() {
		if (!jsonLensModulePromise) {
			jsonLensModulePromise = importFirst(JSONLENS_URLS, 'JsonLens').catch((error) => {
				setLog('JsonLens could not be loaded. Falling back to plain JSON: ' + error.message);
				return null;
			});
		}

		return jsonLensModulePromise;
	}

	function stringifyJson(value) {
		try {
			if (typeof value === 'string') {
				const trimmed = value.trim();

				if (trimmed !== '') {
					return JSON.stringify(JSON.parse(trimmed), null, 2);
				}
			}

			return JSON.stringify(value, null, 2);
		} catch (error) {
			return typeof value === 'string' ? value : String(value);
		}
	}

	function createJsonValue(value) {
		const holder = createElement('agent-tool-json-holder');
		const fallback = document.createElement('pre');
		fallback.className = 'agent-tool-json-fallback';
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

			const lens = module.JsonLens.createElement({
				value,
				mode: 'tree',
				collapsedDepth: 2,
				showToolbar: true,
				plugins
			});

			if (lens) {
				holder.replaceChildren(lens);
			}
		}).catch(() => {});

		return holder;
	}

	async function postJson(payload) {
		const response = await fetch(ENDPOINT_URL, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(payload)
		});

		if (!response.ok) {
			throw new Error('Request failed with status ' + response.status);
		}

		return response.json();
	}

	async function copyText(text) {
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			await navigator.clipboard.writeText(text);
			return;
		}

		const textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', 'readonly');
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();
		document.execCommand('copy');
		textarea.remove();
	}

	async function copySingleTool(row) {
		await copyText(stringifyJson(row || {}));
		setLog('Copied tool row ' + getText(row && (row.id || row.tool_key || row.name)));
	}

	function getFullscreenTarget(source) {
		if (source instanceof HTMLElement) {
			return source.closest('.mg-row-detail') || source.closest('.agent-tool-detail') || source;
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
				setLog('Opened tool detail in fullscreen.');
				return;
			}

			setLog('Fullscreen API is not supported by this browser.');
		} catch (error) {
			setLog('Could not open fullscreen: ' + getText(error && error.message, String(error)));
		}
	}

	function createFullscreenButton() {
		const button = document.createElement('button');

		button.type = 'button';
		button.className = 'agent-tool-button';
		button.textContent = 'Fullscreen';
		button.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			toggleDetailFullscreen(button);
		});

		return button;
	}

	function renderTool(value, row) {
		const wrapper = createElement('agent-tool-cell-stack');
		const main = createElement('agent-tool-cell-main', getText(row.name));
		const sub = createElement('agent-tool-cell-sub', getText(row.function_names));

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderClass(value, row) {
		const wrapper = createElement('agent-tool-cell-stack');
		const main = createElement('agent-tool-cell-main', getText(row.class));
		const sub = createElement('agent-tool-cell-sub', 'Functions: ' + getText(row.function_count, '0'));

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderFunctionCount(value, row) {
		const wrapper = createElement('agent-tool-pill-row');
		const pill = document.createElement('span');
		pill.className = 'agent-tool-pill agent-tool-pill-strong';
		pill.textContent = getText(row.function_count, '0') + ' functions';
		wrapper.appendChild(pill);

		return wrapper;
	}

	function renderPills(value) {
		const wrapper = createElement('agent-tool-pill-row');
		const items = String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

		if (items.length === 0) {
			wrapper.appendChild(createPill('-'));
			return wrapper;
		}

		items.slice(0, 8).forEach((item) => wrapper.appendChild(createPill(item)));

		if (items.length > 8) {
			wrapper.appendChild(createPill('+' + String(items.length - 8)));
		}

		return wrapper;
	}

	function createPill(text, extraClass = '') {
		const pill = document.createElement('span');
		pill.className = ('agent-tool-pill ' + extraClass).trim();
		pill.textContent = getText(text);

		return pill;
	}

	function getToolKeyFromRow(row) {
		if (!row || typeof row !== 'object') {
			return '';
		}

		const value = row.tool_key || row.id || row.name || '';

		return String(value || '').trim();
	}

	function getToolKeyFromContext(context) {
		if (context && typeof context === 'object') {
			const rowKey = getToolKeyFromRow(context.row);

			if (rowKey) {
				return rowKey;
			}

			if (context.rowId !== null && context.rowId !== undefined && context.rowId !== '') {
				return String(context.rowId).trim();
			}

			if (context.id !== null && context.id !== undefined && context.id !== '') {
				return String(context.id).trim();
			}
		}

		if (context !== null && context !== undefined && context !== '') {
			return String(context).trim();
		}

		return '';
	}

	async function loadRemoteDetail(row) {
		const toolKey = getToolKeyFromRow(row);

		if (!toolKey) {
			throw new Error('Missing tool key for detail request.');
		}

		const response = await postJson({
			mode: 'detail',
			tool_key: toolKey
		});

		if (!response || !response.found || !response.detail) {
			throw new Error('No detail data returned for ' + getText(row && row.name, toolKey));
		}

		return response.detail;
	}

	function createDetailLoadingPlaceholder(row) {
		return createElement('agent-tool-startup', 'Loading detail for ' + getText(row && row.name, row && row.tool_key) + '...');
	}

	function createDetailErrorPlaceholder(row, error) {
		return createElement('agent-tool-startup agent-tool-startup-error', 'Failed to load detail for ' + getText(row && row.name, row && row.tool_key) + ': ' + getText(error && error.message ? error.message : error));
	}

	function renderAgentToolDetail(context) {
		const payload = context && context.payload ? context.payload : null;

		if (!payload || typeof payload !== 'object') {
			return document.createTextNode(getText(payload));
		}

		const wrapper = createElement('agent-tool-detail');
		const layout = createElement('agent-tool-detail-layout');
		const left = createElement('agent-tool-detail-card');
		const right = createElement('agent-tool-detail-card');
		const header = createElement('agent-tool-detail-header');
		const headerText = createElement();
		const actions = createElement('agent-tool-detail-actions');
		const title = createElement('agent-tool-detail-title', getText(payload.headline));
		const summary = createElement('agent-tool-detail-summary', getText(payload.summary));

		headerText.appendChild(title);
		headerText.appendChild(summary);

		if (payload.description) {
			headerText.appendChild(createElement('agent-tool-detail-summary', payload.description));
		}

		actions.appendChild(createFullscreenButton());

		const copyButton = document.createElement('button');
		copyButton.type = 'button';
		copyButton.className = 'agent-tool-button';
		copyButton.textContent = 'Copy definitions';
		copyButton.addEventListener('click', async () => {
			await copyText(stringifyJson(payload.functions || []));
			setLog('Copied tool definitions for ' + getText(payload.tool_key));
		});
		actions.appendChild(copyButton);

		header.appendChild(headerText);
		header.appendChild(actions);
		left.appendChild(header);

		const badgeRow = createElement('agent-tool-pill-row');
		(payload.badges || []).forEach((badge) => badgeRow.appendChild(createPill(badge, 'agent-tool-pill-strong')));
		left.appendChild(badgeRow);

		const formRoot = createElement();
		const resultRoot = createElement('agent-tool-definition-panel');
		left.appendChild(formRoot);
		right.appendChild(createElement('agent-tool-detail-title', 'Result JSON'));
		right.appendChild(resultRoot);

		const form = new AgentToolInlineForm(formRoot, {
			payload,
			resultRoot
		});
		form.render();

		const definitionsPanel = createElement('agent-tool-definition-panel');
		definitionsPanel.appendChild(createElement('agent-tool-detail-title', 'Tool definitions JSON'));
		definitionsPanel.appendChild(createJsonValue(payload.tool_definitions_json || payload.functions || []));
		left.appendChild(definitionsPanel);

		layout.appendChild(left);
		layout.appendChild(right);
		wrapper.appendChild(layout);

		return wrapper;
	}

	class AgentToolInlineForm {
		constructor(root, options) {
			this.root = root;
			this.options = options || {};
			this.payload = this.options.payload || {};
			this.resultRoot = this.options.resultRoot || null;
			this.functions = Array.isArray(this.payload.functions) ? this.payload.functions : [];
			this.selectedName = this.functions.length > 0 ? String(this.functions[0].name || '') : '';
			this.fields = new Map();
			this.rawMode = false;
			this.rawEditor = null;
			this.preview = null;
			this.status = null;
			this.form = null;
		}

		render() {
			this.fields.clear();
			this.rawEditor = null;
			this.root.replaceChildren();

			const wrapper = createElement('agent-tool-form');
			const header = createElement('agent-tool-form-header');
			const title = createElement('agent-tool-form-title', 'Function test');
			const toolbar = createElement('agent-tool-form-toolbar');

			toolbar.appendChild(this.createFunctionSelect());
			toolbar.appendChild(this.createRawToggle());
			header.appendChild(title);
			header.appendChild(toolbar);
			wrapper.appendChild(header);
			wrapper.appendChild(this.createDescription());

			this.form = document.createElement('form');
			this.form.className = 'agent-tool-form-body';
			this.form.addEventListener('submit', (event) => {
				event.preventDefault();
				this.submit();
			});

			if (this.rawMode) {
				this.form.appendChild(this.createRawEditor());
			} else {
				this.form.appendChild(this.createFields());
			}

			const actions = createElement('agent-tool-form-actions');
			const submit = document.createElement('button');
			submit.type = 'submit';
			submit.className = 'agent-tool-form-button agent-tool-form-button-primary';
			submit.textContent = 'Run function';
			actions.appendChild(submit);

			const copy = document.createElement('button');
			copy.type = 'button';
			copy.className = 'agent-tool-form-button';
			copy.textContent = 'Copy arguments';
			copy.addEventListener('click', () => this.copyArguments());
			actions.appendChild(copy);

			this.form.appendChild(actions);
			wrapper.appendChild(this.form);

			this.preview = document.createElement('pre');
			this.preview.className = 'agent-tool-form-preview';
			wrapper.appendChild(createElement('agent-tool-form-preview-title', 'Arguments preview'));
			wrapper.appendChild(this.preview);

			this.status = createElement('agent-tool-form-status');
			wrapper.appendChild(this.status);

			this.root.appendChild(wrapper);
			this.refreshPreview();

			if (this.resultRoot && this.resultRoot.childNodes.length === 0) {
				this.resultRoot.appendChild(createElement('agent-tool-form-empty', 'No function has been executed yet.'));
			}
		}

		createFunctionSelect() {
			const select = document.createElement('select');
			select.className = 'agent-tool-form-select';

			if (this.functions.length === 0) {
				const option = document.createElement('option');
				option.value = '';
				option.textContent = 'No functions';
				select.appendChild(option);
				select.disabled = true;
				return select;
			}

			this.functions.forEach((definition) => {
				const option = document.createElement('option');
				option.value = String(definition.name || '');
				option.textContent = getText(definition.label || definition.name);
				select.appendChild(option);
			});

			select.value = this.selectedName;
			select.addEventListener('change', () => {
				this.selectedName = select.value;
				this.rawMode = false;
				this.render();
			});

			return select;
		}

		createRawToggle() {
			const label = document.createElement('label');
			label.className = 'agent-tool-form-checkbox-row';
			const input = document.createElement('input');
			input.type = 'checkbox';
			input.checked = this.rawMode;
			input.addEventListener('change', () => {
				this.rawMode = input.checked;
				this.render();
			});
			label.appendChild(input);
			label.appendChild(document.createTextNode('Raw JSON'));

			return label;
		}

		createDescription() {
			const definition = this.getSelectedDefinition();

			if (!definition) {
				return createElement('agent-tool-form-empty', 'This tool does not expose any callable function definitions.');
			}

			const wrapper = createElement('agent-tool-form-description');
			const strong = document.createElement('strong');
			strong.textContent = getText(definition.name);
			wrapper.appendChild(strong);
			wrapper.appendChild(document.createElement('br'));
			wrapper.appendChild(document.createTextNode(getText(definition.description, 'No description available.')));

			return wrapper;
		}

		createFields() {
			const wrapper = createElement('agent-tool-form-fields');
			const definition = this.getSelectedDefinition();
			const parameters = this.getParameters(definition);
			const properties = this.getProperties(parameters);
			const required = new Set(Array.isArray(parameters.required) ? parameters.required.map(String) : []);
			const keys = Object.keys(properties);

			if (keys.length === 0) {
				wrapper.appendChild(createElement('agent-tool-form-empty agent-tool-form-field-full', 'This function does not define input parameters.'));
				return wrapper;
			}

			keys.forEach((key) => {
				wrapper.appendChild(this.createField(key, properties[key] || {}, required.has(key)));
			});

			return wrapper;
		}

		createField(key, schema, required) {
			const type = this.getSchemaType(schema);
			const row = createElement('agent-tool-form-field' + (type === 'object' || type === 'array' ? ' agent-tool-form-field-full' : ''));
			const id = 'agent-tool-form-' + key.replace(/[^a-zA-Z0-9_-]/g, '-');
			const label = document.createElement('label');
			label.className = 'agent-tool-form-label';
			label.htmlFor = id;
			label.textContent = key + (required ? ' *' : '');
			const control = this.createControl(id, key, schema, required);
			const hintText = this.getFieldHint(schema, type);

			row.appendChild(label);
			row.appendChild(control);

			if (hintText) {
				row.appendChild(createElement('agent-tool-form-hint', hintText));
			}

			this.fields.set(key, control);

			return row;
		}

		createControl(id, key, schema, required) {
			const type = this.getSchemaType(schema);
			let control = null;

			if (Array.isArray(schema.enum) && schema.enum.length > 0) {
				control = document.createElement('select');
				control.className = 'agent-tool-form-input';

				if (!required) {
					const empty = document.createElement('option');
					empty.value = '';
					empty.textContent = '-';
					control.appendChild(empty);
				}

				schema.enum.forEach((value) => {
					const option = document.createElement('option');
					option.value = String(value);
					option.textContent = String(value);
					control.appendChild(option);
				});
			} else if (type === 'boolean') {
				const label = document.createElement('label');
				label.className = 'agent-tool-form-checkbox-row';
				control = document.createElement('input');
				control.type = 'checkbox';
				control.checked = !!schema.default;
				label.appendChild(control);
				label.appendChild(document.createTextNode('enabled'));
				control.id = id;
				control.name = key;
				control.addEventListener('change', () => this.refreshPreview());
				return label;
			} else if (type === 'integer' || type === 'number') {
				control = document.createElement('input');
				control.type = 'number';
				control.step = type === 'integer' ? '1' : 'any';
				control.className = 'agent-tool-form-input';
				control.value = schema.default !== undefined && schema.default !== null ? String(schema.default) : '';
			} else if (type === 'object' || type === 'array') {
				control = document.createElement('textarea');
				control.className = 'agent-tool-form-input agent-tool-form-textarea';
				control.rows = 6;
				control.value = this.getStructuredDefault(schema, type);
			} else if (this.isLongText(schema)) {
				control = document.createElement('textarea');
				control.className = 'agent-tool-form-input agent-tool-form-textarea';
				control.rows = 4;
				control.value = schema.default !== undefined && schema.default !== null ? String(schema.default) : '';
			} else {
				control = document.createElement('input');
				control.type = 'text';
				control.className = 'agent-tool-form-input';
				control.value = schema.default !== undefined && schema.default !== null ? String(schema.default) : '';
			}

			control.id = id;
			control.name = key;
			control.addEventListener('input', () => this.refreshPreview());
			control.addEventListener('change', () => this.refreshPreview());

			return control;
		}

		createRawEditor() {
			const wrapper = createElement('agent-tool-form-field agent-tool-form-field-full');
			const label = document.createElement('label');
			label.className = 'agent-tool-form-label';
			label.textContent = 'Arguments JSON';
			const textarea = document.createElement('textarea');
			textarea.className = 'agent-tool-form-input agent-tool-form-textarea';
			textarea.rows = 12;
			textarea.value = stringifyJson(this.buildDefaultArguments());
			textarea.addEventListener('input', () => this.refreshPreview());
			this.rawEditor = textarea;
			wrapper.appendChild(label);
			wrapper.appendChild(textarea);
			wrapper.appendChild(createElement('agent-tool-form-hint', 'Submit a JSON object directly.'));

			return wrapper;
		}

		getSelectedDefinition() {
			return this.functions.find((definition) => String(definition.name || '') === this.selectedName) || null;
		}

		getParameters(definition) {
			return definition && definition.parameters && typeof definition.parameters === 'object' && !Array.isArray(definition.parameters) ? definition.parameters : {};
		}

		getProperties(parameters) {
			return parameters && parameters.properties && typeof parameters.properties === 'object' && !Array.isArray(parameters.properties) ? parameters.properties : {};
		}

		getSchemaType(schema) {
			const type = schema && schema.type !== undefined ? schema.type : 'string';

			if (Array.isArray(type)) {
				return String(type.find((item) => item !== 'null') || 'string');
			}

			return String(type || 'string');
		}

		getFieldHint(schema, type) {
			const parts = [];

			if (schema && schema.description) {
				parts.push(String(schema.description));
			}

			if (type === 'object' || type === 'array') {
				parts.push('Enter valid JSON.');
			}

			return parts.join(' ');
		}

		isLongText(schema) {
			const description = String(schema && schema.description ? schema.description : '').toLowerCase();

			return description.includes('json') || description.includes('content') || description.includes('query') || description.includes('text');
		}

		getStructuredDefault(schema, type) {
			if (schema.default !== undefined) {
				return stringifyJson(schema.default);
			}

			return type === 'array' ? '[]' : '{}';
		}

		buildDefaultArguments() {
			const definition = this.getSelectedDefinition();
			const parameters = this.getParameters(definition);
			const properties = this.getProperties(parameters);
			const required = new Set(Array.isArray(parameters.required) ? parameters.required.map(String) : []);
			const out = {};

			Object.keys(properties).forEach((key) => {
				const schema = properties[key] || {};

				if (!required.has(key) && schema.default === undefined) {
					return;
				}

				const value = this.getDefaultValue(schema);

				if (value !== undefined) {
					out[key] = value;
				}
			});

			return out;
		}

		getDefaultValue(schema) {
			if (schema.default !== undefined) {
				return schema.default;
			}

			const type = this.getSchemaType(schema);

			if (type === 'boolean') {
				return false;
			}
			if (type === 'integer' || type === 'number') {
				return 0;
			}
			if (type === 'array') {
				return [];
			}
			if (type === 'object') {
				return {};
			}

			return '';
		}

		readArguments() {
			if (this.rawMode) {
				const raw = this.rawEditor ? this.rawEditor.value.trim() : '';

				if (raw === '') {
					return {};
				}

				const parsed = JSON.parse(raw);

				if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
					throw new Error('Arguments JSON must be an object.');
				}

				return parsed;
			}

			const definition = this.getSelectedDefinition();
			const parameters = this.getParameters(definition);
			const properties = this.getProperties(parameters);
			const required = new Set(Array.isArray(parameters.required) ? parameters.required.map(String) : []);
			const out = {};

			Object.keys(properties).forEach((key) => {
				const control = this.fields.get(key);
				const schema = properties[key] || {};
				const value = this.readControlValue(control, schema, required.has(key));

				if (value !== undefined) {
					out[key] = value;
				}
			});

			return out;
		}

		readControlValue(control, schema, required) {
			if (!control) {
				return undefined;
			}

			const input = control.matches && control.matches('input, select, textarea') ? control : control.querySelector('input, select, textarea');

			if (!input) {
				return undefined;
			}

			const type = this.getSchemaType(schema);

			if (type === 'boolean') {
				if (!required && !input.checked && schema.default === undefined) {
					return undefined;
				}

				return !!input.checked;
			}

			const raw = String(input.value || '').trim();

			if (raw === '' && !required) {
				return undefined;
			}

			if (type === 'integer') {
				return raw === '' ? 0 : parseInt(raw, 10);
			}

			if (type === 'number') {
				return raw === '' ? 0 : parseFloat(raw);
			}

			if (type === 'object' || type === 'array') {
				return raw === '' ? (type === 'array' ? [] : {}) : JSON.parse(raw);
			}

			return raw;
		}

		refreshPreview() {
			if (!this.preview) {
				return;
			}

			try {
				this.preview.textContent = stringifyJson(this.readArguments());
				this.setStatus('', '');
			} catch (error) {
				this.preview.textContent = '';
				this.setStatus(error && error.message ? error.message : String(error), 'error');
			}
		}

		setStatus(message, type) {
			if (!this.status) {
				return;
			}

			this.status.className = 'agent-tool-form-status';

			if (type) {
				this.status.classList.add('agent-tool-form-status-' + type);
			}

			this.status.textContent = message || '';
		}

		setBusy(isBusy) {
			if (!this.form) {
				return;
			}

			this.form.querySelectorAll('button, input, select, textarea').forEach((element) => {
				element.disabled = !!isBusy;
			});
		}

		async copyArguments() {
			try {
				await copyText(stringifyJson(this.readArguments()));
				this.setStatus('Arguments copied.', 'ok');
			} catch (error) {
				this.setStatus(error && error.message ? error.message : String(error), 'error');
			}
		}

		async submit() {
			const definition = this.getSelectedDefinition();

			if (!definition) {
				this.setStatus('No function selected.', 'error');
				return;
			}

			let args = {};

			try {
				args = this.readArguments();
			} catch (error) {
				this.setStatus(error && error.message ? error.message : String(error), 'error');
				return;
			}

			this.setBusy(true);
			this.setStatus('Running function...', '');
			setLog('Running ' + definition.name + ' on ' + getText(this.payload.tool_key));

			try {
				const response = await postJson({
					mode: 'call_tool',
					tool_key: this.payload.tool_key,
					function_name: definition.name,
					arguments: args
				});

				if (this.resultRoot) {
					this.resultRoot.replaceChildren(createJsonValue(response));
				}

				this.setStatus(response && response.ok ? 'Function executed.' : 'Function returned an error result.', response && response.ok ? 'ok' : 'error');
				setLog('Executed ' + definition.name + ' on ' + getText(this.payload.tool_key));
			} catch (error) {
				const result = {
					ok: false,
					error: error && error.message ? error.message : String(error)
				};

				if (this.resultRoot) {
					this.resultRoot.replaceChildren(createJsonValue(result));
				}

				this.setStatus(result.error, 'error');
				setLog('Failed to execute ' + definition.name + ': ' + result.error);
			} finally {
				this.setBusy(false);
			}
		}
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

		if (!AjaxAdapter || !ModularGrid) {
			throw new Error('ModularGrid module was loaded, but AjaxAdapter or ModularGrid export is missing.');
		}

		const adapter = new AjaxAdapter({
			url: ENDPOINT_URL,
			method: 'POST',
			rowsPath: 'data',
			totalPath: 'total',
			mapRequest(request) {
				const sortKey = request.sortKey || 'name';
				const sortDirection = request.sortDirection || 'asc';

				return {
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
					filters: {},
					group: []
				};
			}
		});

		const grid = new ModularGrid(GRID_SELECTOR, {
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
				key: 'name',
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
					placeholder: 'Search tool name, class or function'
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
							key: 'copy-tool-row',
							label: 'Copy to clipboard',
							onClick(context) {
								copySingleTool(context && context.row ? context.row : null);
							}
						}
					]
				},
				rowDetail: {
					rowIdKey: 'tool_key',
					clearOnDataReload: true,
					asyncDetail: {
						load(context) {
							return loadRemoteDetail(context.row);
						},
						renderLoading(context) {
							return createDetailLoadingPlaceholder(context.row);
						},
						renderError(context) {
							return createDetailErrorPlaceholder(context.row, context.error);
						},
						render(context) {
							return renderAgentToolDetail(context);
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
					key: 'name',
					label: 'Tool',
					width: 280,
					headerMenu: {
						defaultSortKey: 'name',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'name', label: 'Tool' },
							{ key: 'function_names', label: 'Function names' }
						]
					},
					render(value, row) {
						return renderTool(value, row);
					}
				},
				{
					key: 'class',
					label: 'Class',
					width: 520,
					textDisplay: {
						strategy: 'clamp',
						lines: 2,
						expandable: true
					},
					headerMenu: {
						defaultSortKey: 'class',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'class', label: 'Class' }
						]
					},
					render(value, row) {
						return renderClass(value, row);
					}
				},
				{
					key: 'function_count',
					label: 'Functions',
					width: 130,
					headerMenu: {
						defaultSortKey: 'function_count',
						defaultSortDirection: 'desc',
						sortOptions: [
							{ key: 'function_count', label: 'Functions' }
						]
					},
					render(value, row) {
						return renderFunctionCount(value, row);
					}
				},
				{
					key: 'categories',
					label: 'Categories',
					width: 180,
					visible: true,
					headerMenu: {
						defaultSortKey: 'categories',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'categories', label: 'Categories' }
						]
					},
					render(value) {
						return renderPills(value);
					}
				},
				{
					key: 'function_names',
					label: 'Function names',
					width: 420,
					visible: false,
					textDisplay: {
						strategy: 'clamp',
						lines: 4,
						expandable: true
					},
					headerMenu: {
						defaultSortKey: 'function_names',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'function_names', label: 'Function names' }
						]
					}
				},
				{
					key: 'description',
					label: 'Description',
					width: 420,
					visible: false,
					textDisplay: {
						strategy: 'clamp',
						lines: 3,
						expandable: true
					},
					headerMenu: {
						defaultSortKey: 'description',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'description', label: 'Description' }
						]
					}
				}
			]
		});

		grid.on('data:appended', ({ appendedCount, totalLoaded }) => {
			setLog('Loaded ' + String(appendedCount) + ' more tools. ' + String(totalLoaded) + ' tools are currently loaded.');
		});

		grid.on('detail:loaded', (event) => {
			const detailRowId = event && typeof event === 'object' ? event.rowId : null;

			setLog('Loaded detail for ' + getText(detailRowId));
		});

		grid.on('detail:error', (event) => {
			const detailRowId = event && typeof event === 'object' ? event.rowId : null;
			const detailError = event && typeof event === 'object' ? event.error : null;

			setLog('Failed to load detail for ' + getText(detailRowId) + ': ' + getText(detailError));
		});

		await grid.init();
		setLog('Initial batch loaded. Open a row to test a tool function.');
	}

	(async function() {
		const root = document.querySelector(GRID_SELECTOR);

		if (!root || root.dataset.initialized === '1') {
			return;
		}

		root.dataset.initialized = '1';
		setStartupStatus('Loading ModularGrid module...');

		try {
			const modularGridModule = await importFirst(MODULARGRID_URLS, 'ModularGrid');
			setStartupStatus('Initializing grid...');
			await initGrid(modularGridModule);
		} catch (error) {
			const message = error && error.message ? error.message : String(error);
			setStartupStatus('Agent Tool Test display could not be initialized.', message, true);
			setLog('Initialization failed: ' + message);
			console.error(error);
		}
	})();
</script>
