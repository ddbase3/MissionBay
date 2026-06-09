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
	.agent-info-topic-shell {
		max-width: 1700px;
	}

	.agent-info-topic-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.agent-info-topic-shell p {
		margin: 0 0 16px 0;
		max-width: 1120px;
		color: #555;
		line-height: 1.45;
	}

	.agent-info-topic-grid .agent-info-topic-panel {
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

	.agent-info-topic-grid .agent-info-topic-panel > * {
		flex: 0 0 auto;
	}

	.agent-info-topic-grid .agent-info-topic-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.agent-info-topic-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.agent-info-topic-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.agent-info-topic-grid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.agent-info-topic-grid .mg-input,
	.agent-info-topic-grid .mg-select,
	.agent-info-topic-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.agent-info-topic-grid input[type="search"].mg-input {
		width: 340px;
	}

	.agent-info-topic-grid .mg-select {
		width: auto;
		min-width: 96px;
	}

	.agent-info-topic-grid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.agent-info-topic-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.agent-info-topic-grid .mg-table thead th.mg-cell-pinned {
		z-index: 14;
	}

	.agent-info-topic-grid .mg-table th,
	.agent-info-topic-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.agent-info-topic-grid .mg-row-actions-cell,
	.agent-info-topic-grid .mg-row-actions-header {
		width: 54px;
		min-width: 54px;
		text-align: center;
	}

	.agent-info-topic-cell-stack {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.agent-info-topic-cell-main {
		font-weight: 600;
		color: #222;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.agent-info-topic-cell-sub {
		font-size: 12px;
		color: #666;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.agent-info-topic-pill-row {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		align-items: center;
	}

	.agent-info-topic-pill {
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

	.agent-info-topic-pill-strong {
		background: #f0f0f0;
		color: #222;
		border-color: #cfcfcf;
	}

	.agent-info-topic-output {
		margin-top: 12px;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.agent-info-topic-output strong {
		color: #222;
	}

	.agent-info-topic-startup {
		padding: 16px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.agent-info-topic-startup-error {
		border-color: #e4b9b9;
		background: #fff8f8;
		color: #8a1f1f;
	}

	.agent-info-topic-startup pre {
		white-space: pre-wrap;
		word-break: break-word;
		margin: 8px 0 0 0;
		font-size: 12px;
	}

	.agent-info-topic-detail {
		min-width: 0;
	}

	.agent-info-topic-detail-layout {
		display: grid;
		grid-template-columns: minmax(340px, 0.95fr) minmax(420px, 1.25fr);
		gap: 14px;
		align-items: start;
	}

	.agent-info-topic-detail-card {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 10px;
		min-width: 0;
	}

	.agent-info-topic-detail-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 10px;
	}

	.agent-info-topic-detail-title {
		font-weight: 600;
		font-size: 15px;
		color: #222;
	}

	.agent-info-topic-detail-summary {
		margin-top: 2px;
		font-size: 12px;
		color: #666;
		overflow-wrap: anywhere;
	}

	.agent-info-topic-detail-actions,
	.agent-info-topic-form-actions,
	.agent-info-topic-form-toolbar {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 6px;
	}

	.agent-info-topic-detail-actions {
		justify-content: flex-end;
		flex: 0 0 auto;
	}

	.agent-info-topic-button,
	.agent-info-topic-form-button {
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

	.agent-info-topic-button:hover,
	.agent-info-topic-form-button:hover {
		background: #f5f5f5;
	}

	.agent-info-topic-button:focus-visible,
	.agent-info-topic-form-button:focus-visible {
		outline: 2px solid #86a8cf;
		outline-offset: 2px;
	}

	.agent-info-topic-form-button-primary {
		background: #222;
		border-color: #222;
		color: #fff;
	}

	.agent-info-topic-form-button-primary:hover {
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

	.mg-row-detail:fullscreen .agent-info-topic-detail,
	.mg-row-detail:-webkit-full-screen .agent-info-topic-detail {
		display: block;
		width: auto;
		height: auto;
		min-height: 0;
	}

	.mg-row-detail:fullscreen .agent-info-topic-detail-layout,
	.mg-row-detail:-webkit-full-screen .agent-info-topic-detail-layout {
		grid-template-columns: minmax(420px, 0.95fr) minmax(520px, 1.35fr);
		max-width: 1800px;
		margin: 0 auto;
	}

	.mg-row-detail:fullscreen .agent-info-topic-json-fallback,
	.mg-row-detail:fullscreen .agent-info-topic-form-preview,
	.mg-row-detail:-webkit-full-screen .agent-info-topic-json-fallback,
	.mg-row-detail:-webkit-full-screen .agent-info-topic-form-preview {
		max-height: 620px;
	}

	.agent-info-topic-form {
		display: grid;
		gap: 10px;
		margin-top: 10px;
	}

	.agent-info-topic-form-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 10px;
	}

	.agent-info-topic-form-title {
		font-weight: 600;
		color: #222;
	}

	.agent-info-topic-form-input,
	.agent-info-topic-form-select {
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

	.agent-info-topic-form-textarea {
		min-height: 92px;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.4;
		resize: vertical;
	}

	.agent-info-topic-form-description,
	.agent-info-topic-form-empty,
	.agent-info-topic-form-status,
	.agent-info-topic-form-preview-title {
		font-size: 12px;
		color: #666;
		line-height: 1.4;
	}

	.agent-info-topic-form-status-error {
		color: #8a1f1f;
	}

	.agent-info-topic-form-status-ok {
		color: #276028;
	}

	.agent-info-topic-form-fields {
		display: grid;
		grid-template-columns: repeat(2, minmax(220px, 1fr));
		gap: 10px;
	}

	.agent-info-topic-form-field-full {
		grid-column: 1 / -1;
	}

	.agent-info-topic-form-label {
		display: block;
		margin-bottom: 3px;
		font-size: 12px;
		font-weight: 600;
		color: #333;
	}

	.agent-info-topic-form-hint {
		margin-top: 3px;
		font-size: 11px;
		color: #777;
		line-height: 1.35;
	}

	.agent-info-topic-json-fallback,
	.agent-info-topic-form-preview {
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

	.agent-info-topic-json-holder {
		min-width: 0;
	}

	.agent-info-topic-definition-panel {
		display: grid;
		gap: 8px;
		margin-top: 10px;
	}

	@media (max-width: 980px) {
		.agent-info-topic-detail-layout,
		.agent-info-topic-form-fields {
			grid-template-columns: 1fr;
		}
	}

	@media (max-width: 720px) {
		.agent-info-topic-shell h1 {
			font-size: 21px;
		}

		.agent-info-topic-grid .mg-table-scroll {
			height: 420px;
		}
	}
</style>

<div class="agent-info-topic-shell">
	<h1>Agent info topic provider test</h1>
	<p>
		Registered <code>IAgentInfoTopicProvider</code> instances are listed below. Open a row to inspect metadata and call the selected provider directly with an <code>AgentInfoRequest</code>.
	</p>

	<div class="agent-info-topic-grid">
		<div id="agent-info-topic-provider-test-grid">
			<div class="agent-info-topic-startup">Loading Agent Info Topic Provider Test display...</div>
		</div>
		<div id="agent-info-topic-provider-test-output" class="agent-info-topic-output"><strong>Last action:</strong> Waiting for initialization.</div>
	</div>
</div>

<script type="module">
	const ENDPOINT_URL = <?php echo json_encode((string) $this->_['service'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const MODULARGRID_URLS = [<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	const JSONLENS_URLS = [<?php echo json_encode($jsonLensJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	const GRID_SELECTOR = '#agent-info-topic-provider-test-grid';
	const LOG_SELECTOR = '#agent-info-topic-provider-test-output';
	const BATCH_SIZE = 50;
	const SORT_TYPES = {
		name: 'string',
		topic: 'string',
		title: 'string',
		class: 'string',
		description: 'string',
		priority: 'int',
		aliases: 'string',
		alias_count: 'int'
	};

	let jsonLensModulePromise = null;

	const layout = {
		type: 'stack',
		className: 'mg-layout-root',
		children: [
			{
				type: 'zone',
				key: 'topLine',
				className: 'agent-info-topic-panel'
			},
			{
				type: 'view',
				key: 'main',
				className: 'agent-info-topic-main'
			},
			{
				type: 'zone',
				key: 'statusZone',
				className: 'agent-info-topic-panel'
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

		const box = createElement('agent-info-topic-startup' + (isError ? ' agent-info-topic-startup-error' : ''));
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
		const holder = createElement('agent-info-topic-json-holder');
		const fallback = document.createElement('pre');
		fallback.className = 'agent-info-topic-json-fallback';
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

	async function copySingleProvider(row) {
		await copyText(stringifyJson(row || {}));
		setLog('Copied provider row ' + getText(row && (row.id || row.provider_key || row.name)));
	}

	function getFullscreenTarget(source) {
		if (source instanceof HTMLElement) {
			return source.closest('.mg-row-detail') || source.closest('.agent-info-topic-detail') || source;
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
				setLog('Opened provider detail in fullscreen.');
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
		button.className = 'agent-info-topic-button';
		button.textContent = 'Fullscreen';
		button.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			toggleDetailFullscreen(button);
		});

		return button;
	}

	function renderProvider(value, row) {
		const wrapper = createElement('agent-info-topic-cell-stack');
		const main = createElement('agent-info-topic-cell-main', getText(row.title || row.name));
		const sub = createElement('agent-info-topic-cell-sub', getText(row.name));

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderTopic(value, row) {
		const wrapper = createElement('agent-info-topic-cell-stack');
		const main = createElement('agent-info-topic-cell-main', getText(row.topic));
		const sub = createElement('agent-info-topic-cell-sub', row.supports_topic ? 'supports own topic' : 'supports() returned false');

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderClass(value, row) {
		const wrapper = createElement('agent-info-topic-cell-stack');
		const main = createElement('agent-info-topic-cell-main', getText(row.class));
		const sub = createElement('agent-info-topic-cell-sub', 'Priority: ' + getText(row.priority, '0'));

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderPriority(value) {
		const wrapper = createElement('agent-info-topic-pill-row');
		wrapper.appendChild(createPill('priority ' + getText(value, '0'), 'agent-info-topic-pill-strong'));

		return wrapper;
	}

	function renderPills(value) {
		const wrapper = createElement('agent-info-topic-pill-row');
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
		pill.className = ('agent-info-topic-pill ' + extraClass).trim();
		pill.textContent = getText(text);

		return pill;
	}

	function getProviderKeyFromRow(row) {
		if (!row || typeof row !== 'object') {
			return '';
		}

		const value = row.provider_key || row.id || row.name || '';

		return String(value || '').trim();
	}

	async function loadRemoteDetail(row) {
		const providerKey = getProviderKeyFromRow(row);

		if (!providerKey) {
			throw new Error('Missing provider key for detail request.');
		}

		const response = await postJson({
			mode: 'detail',
			provider_key: providerKey
		});

		if (!response || !response.found || !response.detail) {
			throw new Error('No detail data returned for ' + getText(row && row.name, providerKey));
		}

		return response.detail;
	}

	function createDetailLoadingPlaceholder(row) {
		return createElement('agent-info-topic-startup', 'Loading detail for ' + getText(row && row.name, row && row.provider_key) + '...');
	}

	function createDetailErrorPlaceholder(row, error) {
		return createElement('agent-info-topic-startup agent-info-topic-startup-error', 'Failed to load detail for ' + getText(row && row.name, row && row.provider_key) + ': ' + getText(error && error.message ? error.message : error));
	}

	function renderAgentInfoTopicProviderDetail(context) {
		const payload = context && context.payload ? context.payload : null;

		if (!payload || typeof payload !== 'object') {
			return document.createTextNode(getText(payload));
		}

		const wrapper = createElement('agent-info-topic-detail');
		const layout = createElement('agent-info-topic-detail-layout');
		const left = createElement('agent-info-topic-detail-card');
		const right = createElement('agent-info-topic-detail-card');
		const header = createElement('agent-info-topic-detail-header');
		const headerText = createElement();
		const actions = createElement('agent-info-topic-detail-actions');
		const title = createElement('agent-info-topic-detail-title', getText(payload.headline));
		const summary = createElement('agent-info-topic-detail-summary', getText(payload.summary));

		headerText.appendChild(title);
		headerText.appendChild(summary);

		if (payload.description) {
			headerText.appendChild(createElement('agent-info-topic-detail-summary', payload.description));
		}

		actions.appendChild(createFullscreenButton());

		const copyButton = document.createElement('button');
		copyButton.type = 'button';
		copyButton.className = 'agent-info-topic-button';
		copyButton.textContent = 'Copy metadata';
		copyButton.addEventListener('click', async () => {
			await copyText(stringifyJson(payload.provider_meta || {}));
			setLog('Copied provider metadata for ' + getText(payload.provider_key));
		});
		actions.appendChild(copyButton);

		header.appendChild(headerText);
		header.appendChild(actions);
		left.appendChild(header);

		const badgeRow = createElement('agent-info-topic-pill-row');
		(payload.badges || []).forEach((badge) => badgeRow.appendChild(createPill(badge, 'agent-info-topic-pill-strong')));
		left.appendChild(badgeRow);

		const formRoot = createElement();
		const resultRoot = createElement('agent-info-topic-definition-panel');
		left.appendChild(formRoot);
		right.appendChild(createElement('agent-info-topic-detail-title', 'Provider result JSON'));
		right.appendChild(resultRoot);

		const form = new AgentInfoTopicProviderInlineForm(formRoot, {
			payload,
			resultRoot
		});
		form.render();

		const definitionsPanel = createElement('agent-info-topic-definition-panel');
		definitionsPanel.appendChild(createElement('agent-info-topic-detail-title', 'Provider metadata JSON'));
		definitionsPanel.appendChild(createJsonValue(payload.provider_meta || payload.provider_meta_json || {}));
		left.appendChild(definitionsPanel);

		layout.appendChild(left);
		layout.appendChild(right);
		wrapper.appendChild(layout);

		return wrapper;
	}

	class AgentInfoTopicProviderInlineForm {
		constructor(root, options) {
			this.root = root;
			this.options = options || {};
			this.payload = this.options.payload || {};
			this.resultRoot = this.options.resultRoot || null;
			this.fields = new Map();
			this.preview = null;
			this.status = null;
			this.form = null;
		}

		render() {
			this.fields.clear();
			this.root.replaceChildren();

			const wrapper = createElement('agent-info-topic-form');
			const header = createElement('agent-info-topic-form-header');
			const title = createElement('agent-info-topic-form-title', 'Provider test');
			header.appendChild(title);
			wrapper.appendChild(header);
			wrapper.appendChild(this.createDescription());

			this.form = document.createElement('form');
			this.form.className = 'agent-info-topic-form-body';
			this.form.addEventListener('submit', (event) => {
				event.preventDefault();
				this.submit();
			});

			this.form.appendChild(this.createFields());

			const actions = createElement('agent-info-topic-form-actions');
			const submit = document.createElement('button');
			submit.type = 'submit';
			submit.className = 'agent-info-topic-form-button agent-info-topic-form-button-primary';
			submit.textContent = 'Run provider';
			actions.appendChild(submit);

			const copy = document.createElement('button');
			copy.type = 'button';
			copy.className = 'agent-info-topic-form-button';
			copy.textContent = 'Copy request';
			copy.addEventListener('click', () => this.copyRequest());
			actions.appendChild(copy);

			this.form.appendChild(actions);
			wrapper.appendChild(this.form);

			this.preview = document.createElement('pre');
			this.preview.className = 'agent-info-topic-form-preview';
			wrapper.appendChild(createElement('agent-info-topic-form-preview-title', 'AgentInfoRequest preview'));
			wrapper.appendChild(this.preview);

			this.status = createElement('agent-info-topic-form-status');
			wrapper.appendChild(this.status);

			this.root.appendChild(wrapper);
			this.refreshPreview();

			if (this.resultRoot && this.resultRoot.childNodes.length === 0) {
				this.resultRoot.appendChild(createElement('agent-info-topic-form-empty', 'No provider has been executed yet.'));
			}
		}

		createDescription() {
			const wrapper = createElement('agent-info-topic-form-description');
			const strong = document.createElement('strong');
			strong.textContent = getText(this.payload.topic);
			wrapper.appendChild(strong);
			wrapper.appendChild(document.createElement('br'));
			wrapper.appendChild(document.createTextNode('The call below invokes this provider directly through handle(new AgentInfoRequest(...)).'));

			return wrapper;
		}

		createFields() {
			const wrapper = createElement('agent-info-topic-form-fields');
			wrapper.appendChild(this.createInputField('topic', 'Topic', this.payload.topic || '', 'Defaults to the provider topic. You may enter an alias to verify supports().'));
			wrapper.appendChild(this.createScopeField());
			wrapper.appendChild(this.createInputField('limit', 'Limit', '5', 'Allowed range: 1-25.', 'number'));
			wrapper.appendChild(this.createInputField('offset', 'Offset', '0', 'Pagination offset for list-like responses.', 'number'));
			wrapper.appendChild(this.createInputField('query', 'Query', '', 'Free text, id, ref_id, obj_id, login, email or provider-specific test input.', 'textarea'));

			return wrapper;
		}

		createInputField(key, labelText, value, hintText, type = 'text') {
			const row = createElement('agent-info-topic-form-field' + (type === 'textarea' ? ' agent-info-topic-form-field-full' : ''));
			const id = 'agent-info-topic-form-' + key;
			const label = document.createElement('label');
			label.className = 'agent-info-topic-form-label';
			label.htmlFor = id;
			label.textContent = labelText;

			let control = null;

			if (type === 'textarea') {
				control = document.createElement('textarea');
				control.className = 'agent-info-topic-form-input agent-info-topic-form-textarea';
				control.rows = 5;
			} else {
				control = document.createElement('input');
				control.type = type;
				control.className = 'agent-info-topic-form-input';
				if (type === 'number') {
					control.step = '1';
					control.min = key === 'offset' ? '0' : '1';
					if (key === 'limit') {
						control.max = '25';
					}
				}
			}

			control.id = id;
			control.name = key;
			control.value = String(value || '');
			control.addEventListener('input', () => this.refreshPreview());
			control.addEventListener('change', () => this.refreshPreview());

			row.appendChild(label);
			row.appendChild(control);

			if (hintText) {
				row.appendChild(createElement('agent-info-topic-form-hint', hintText));
			}

			this.fields.set(key, control);

			return row;
		}

		createScopeField() {
			const row = createElement('agent-info-topic-form-field');
			const id = 'agent-info-topic-form-scope';
			const label = document.createElement('label');
			label.className = 'agent-info-topic-form-label';
			label.htmlFor = id;
			label.textContent = 'Scope';
			const select = document.createElement('select');
			select.id = id;
			select.name = 'scope';
			select.className = 'agent-info-topic-form-select';

			(this.payload.supported_scopes || ['find', 'summary', 'detail', 'link']).forEach((scope) => {
				const option = document.createElement('option');
				option.value = String(scope);
				option.textContent = String(scope);
				select.appendChild(option);
			});

			select.value = 'summary';
			select.addEventListener('change', () => this.refreshPreview());
			row.appendChild(label);
			row.appendChild(select);
			row.appendChild(createElement('agent-info-topic-form-hint', 'The common info scopes used by GeneralInfoAgentTool.'));
			this.fields.set('scope', select);

			return row;
		}

		readRequest() {
			const topic = String(this.readField('topic') || this.payload.topic || '').trim();
			const query = String(this.readField('query') || '').trim();
			const scope = String(this.readField('scope') || 'summary').trim();
			const limit = Math.max(1, Math.min(25, parseInt(String(this.readField('limit') || '5'), 10) || 5));
			const offset = Math.max(0, parseInt(String(this.readField('offset') || '0'), 10) || 0);

			return {
				provider_key: this.payload.provider_key,
				topic,
				query,
				scope,
				limit,
				offset
			};
		}

		readField(key) {
			const control = this.fields.get(key);

			if (!control) {
				return '';
			}

			return control.value;
		}

		refreshPreview() {
			if (!this.preview) {
				return;
			}

			this.preview.textContent = stringifyJson(this.readRequest());
			this.setStatus('', '');
		}

		setStatus(message, type) {
			if (!this.status) {
				return;
			}

			this.status.className = 'agent-info-topic-form-status';

			if (type) {
				this.status.classList.add('agent-info-topic-form-status-' + type);
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

		async copyRequest() {
			try {
				await copyText(stringifyJson(this.readRequest()));
				this.setStatus('Request copied.', 'ok');
			} catch (error) {
				this.setStatus(error && error.message ? error.message : String(error), 'error');
			}
		}

		async submit() {
			const request = this.readRequest();

			this.setBusy(true);
			this.setStatus('Running provider...', '');
			setLog('Running ' + getText(this.payload.provider_key) + ' with scope ' + request.scope);

			try {
				const response = await postJson({
					mode: 'call_provider',
					provider_key: request.provider_key,
					topic: request.topic,
					query: request.query,
					scope: request.scope,
					limit: request.limit,
					offset: request.offset
				});

				if (this.resultRoot) {
					this.resultRoot.replaceChildren(createJsonValue(response));
				}

				this.setStatus(response && response.ok ? 'Provider executed.' : 'Provider returned an error result.', response && response.ok ? 'ok' : 'error');
				setLog('Executed ' + getText(this.payload.provider_key) + ' with scope ' + request.scope);
			} catch (error) {
				const result = {
					ok: false,
					error: error && error.message ? error.message : String(error)
				};

				if (this.resultRoot) {
					this.resultRoot.replaceChildren(createJsonValue(result));
				}

				this.setStatus(result.error, 'error');
				setLog('Failed to execute ' + getText(this.payload.provider_key) + ': ' + result.error);
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
				const sortKey = request.sortKey || 'priority';
				const sortDirection = request.sortDirection || 'desc';

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
				key: 'priority',
				direction: 'desc'
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
					placeholder: 'Search topic, provider, alias or class'
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
							key: 'copy-provider-row',
							label: 'Copy to clipboard',
							onClick(context) {
								copySingleProvider(context && context.row ? context.row : null);
							}
						}
					]
				},
				rowDetail: {
					rowIdKey: 'provider_key',
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
							return renderAgentInfoTopicProviderDetail(context);
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
					key: 'title',
					label: 'Provider',
					width: 320,
					headerMenu: {
						defaultSortKey: 'title',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'title', label: 'Provider title' },
							{ key: 'name', label: 'Provider name' },
							{ key: 'topic', label: 'Topic' },
							{ key: 'priority', label: 'Priority' }
						]
					},
					render(value, row) {
						return renderProvider(value, row);
					}
				},
				{
					key: 'topic',
					label: 'Topic',
					width: 220,
					headerMenu: {
						defaultSortKey: 'topic',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'topic', label: 'Topic' }
						]
					},
					render(value, row) {
						return renderTopic(value, row);
					}
				},
				{
					key: 'priority',
					label: 'Priority',
					width: 120,
					headerMenu: {
						defaultSortKey: 'priority',
						defaultSortDirection: 'desc',
						sortOptions: [
							{ key: 'priority', label: 'Priority' }
						]
					},
					render(value) {
						return renderPriority(value);
					}
				},
				{
					key: 'aliases',
					label: 'Aliases',
					width: 320,
					visible: true,
					headerMenu: {
						defaultSortKey: 'aliases',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'aliases', label: 'Aliases' },
							{ key: 'alias_count', label: 'Alias count' }
						]
					},
					render(value) {
						return renderPills(value);
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
					key: 'description',
					label: 'Description',
					width: 440,
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
			setLog('Loaded ' + String(appendedCount) + ' more providers. ' + String(totalLoaded) + ' providers are currently loaded.');
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
		setLog('Initial batch loaded. Open a row to test a topic provider.');
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
			setStartupStatus('Agent Info Topic Provider Test display could not be initialized.', message, true);
			setLog('Initialization failed: ' + message);
			console.error(error);
		}
	})();
</script>
