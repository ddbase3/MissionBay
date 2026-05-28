<?php
$resolve = $this->_['resolve'];

$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$chronoPickerCssUrl = (string) $resolve('plugin/ClientStack/assets/chronopicker/styles/chronopicker.css');
$chronoPickerJsUrl = (string) $resolve('plugin/ClientStack/assets/chronopicker/index.js');
$jsonLensCssUrl = (string) $resolve('plugin/ClientStack/assets/jsonlens/styles/jsonlens.css');
$jsonLensJsUrl = (string) $resolve('plugin/ClientStack/assets/jsonlens/index.js');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularGridCssUrl, ENT_QUOTES); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars($chronoPickerCssUrl, ENT_QUOTES); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars($jsonLensCssUrl, ENT_QUOTES); ?>" />

<style>
	.agent-tool-log-shell {
		max-width: 1700px;
	}

	.agent-tool-log-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.agent-tool-log-shell p {
		margin: 0 0 16px 0;
		max-width: 1200px;
		color: #555;
		line-height: 1.45;
	}

	.agent-tool-log-grid .agent-tool-log-panel {
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

	.agent-tool-log-grid .agent-tool-log-panel--filters {
		flex-wrap: wrap;
		align-items: flex-start;
		overflow-x: visible;
	}

	.agent-tool-log-grid .agent-tool-log-panel--filters .mg-control-group {
		flex-wrap: wrap;
		align-items: center;
		row-gap: 8px;
	}

	.agent-tool-log-grid .agent-tool-log-panel > * {
		flex: 0 0 auto;
	}

	.agent-tool-log-grid .agent-tool-log-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.agent-tool-log-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.agent-tool-log-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.agent-tool-log-grid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.agent-tool-log-grid .mg-input,
	.agent-tool-log-grid .mg-select,
	.agent-tool-log-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.agent-tool-log-grid .mg-input {
		width: auto;
	}

	.agent-tool-log-grid input[type="search"].mg-input {
		width: 260px;
	}

	.agent-tool-log-grid .mg-select {
		width: auto;
		min-width: 130px;
	}

	.agent-tool-log-grid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.agent-tool-log-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.agent-tool-log-grid .mg-table thead th.mg-cell-pinned {
		z-index: 14;
	}

	.agent-tool-log-grid .mg-table th,
	.agent-tool-log-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}


	.agent-tool-log-detail {
		min-width: 0;
	}


	.agent-tool-log-detail .mg-row-detail-structured-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		min-width: 0;
	}

	.agent-tool-log-detail .mg-row-detail-badges {
		margin: 8px 0 12px 0;
		padding: 2px 0;
	}

	.agent-tool-log-detail-header-text {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.agent-tool-log-detail-header-actions {
		display: inline-flex;
		align-items: flex-start;
		justify-content: flex-end;
		flex: 0 0 auto;
	}

	.agent-tool-log-detail-fullscreen-button {
		appearance: none;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		color: #222;
		cursor: pointer;
		font: inherit;
		font-size: 12px;
		line-height: 1.3;
		padding: 4px 8px;
		white-space: nowrap;
	}

	.agent-tool-log-detail-fullscreen-button:hover {
		background: #f5f5f5;
	}

	.agent-tool-log-detail-fullscreen-button:focus-visible {
		outline: 2px solid #86a8cf;
		outline-offset: 2px;
	}

	.mg-row-detail:fullscreen,
	.mg-row-detail:-webkit-full-screen {
		display: block;
		align-items: flex-start;
		justify-content: flex-start;
		width: auto;
		height: auto;
		min-height: 100vh;
		padding: 16px;
		background: #fff;
		overflow: auto;
	}

	.mg-row-detail:fullscreen .agent-tool-log-detail,
	.mg-row-detail:-webkit-full-screen .agent-tool-log-detail {
		display: block;
		align-items: flex-start;
		justify-content: flex-start;
		width: auto;
		height: auto;
		min-height: 0;
	}

	.agent-tool-log-detail-layout {
		display: grid;
		grid-template-columns: minmax(260px, 380px) minmax(360px, 1fr);
		align-items: start;
		gap: 16px;
		min-width: 0;
	}

	.agent-tool-log-detail-left,
	.agent-tool-log-detail-right,
	.agent-tool-log-json-column {
		display: grid;
		align-content: start;
		align-items: start;
		gap: 12px;
		min-width: 0;
	}

	.agent-tool-log-detail .mg-row-detail-section,
	.agent-tool-log-detail .mg-row-detail-fields,
	.agent-tool-log-detail .mg-row-detail-fields-item,
	.agent-tool-log-json-block {
		align-self: start;
		min-width: 0;
	}

	.agent-tool-log-detail .mg-row-detail-fields-item {
		align-items: flex-start !important;
	}

	.agent-tool-log-detail .mg-row-detail-fields-label,
	.agent-tool-log-detail .mg-row-detail-fields-value {
		align-self: flex-start !important;
		vertical-align: top !important;
	}

	.agent-tool-log-detail .mg-row-detail-fields-value {
		min-width: 0;
		width: 100%;
	}

	.agent-tool-log-json-block {
		display: grid;
		gap: 6px;
	}

	.agent-tool-log-json-title {
		color: #546274;
		font-size: 12px;
		font-weight: 600;
		letter-spacing: 0.03em;
		line-height: 1.2;
		text-transform: uppercase;
	}

	.agent-tool-log-jsonlens,
	.agent-tool-log-json-fallback {
		align-self: start;
		display: block;
		min-width: 0;
		width: 100%;
	}

	.agent-tool-log-jsonlens .jl-root {
		width: 100%;
	}

	.agent-tool-log-jsonlens .jl-body {
		max-height: 520px;
	}

	.agent-tool-log-json-fallback {
		margin: 0;
		padding: 10px 12px;
		border: 1px solid #e2e2e2;
		border-radius: 4px;
		background: #fafafa;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.45;
		white-space: pre-wrap;
		word-break: break-word;
	}

	.agent-tool-log-cell-stack {
		display: grid;
		gap: 2px;
	}

	.agent-tool-log-cell-main {
		font-weight: 600;
		color: #222;
	}

	.agent-tool-log-cell-sub {
		font-size: 12px;
		color: #666;
	}

	.agent-tool-log-pill-row {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		align-items: center;
	}

	.agent-tool-log-pill {
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

	.agent-tool-log-pill-strong {
		background: #f0f0f0;
		color: #222;
		border-color: #cfcfcf;
	}

	.agent-tool-log-pill-status-finished {
		background: #eef7ee;
		border-color: #bddfbd;
		color: #226622;
	}

	.agent-tool-log-pill-status-error,
	.agent-tool-log-pill-status-failed {
		background: #fff0f0;
		border-color: #e4b9b9;
		color: #8a1f1f;
	}

	.agent-tool-log-output {
		margin-top: 12px;
		padding: 8px 0 0 0;
		border-top: 1px solid #e2e2e2;
		font-size: 13px;
		color: #555;
	}

	.agent-tool-log-output strong {
		color: #222;
	}

	.agent-tool-log-detail-status {
		font-size: 13px;
		color: #5a6980;
	}

	.agent-tool-log-detail-status-error {
		color: #8a1f1f;
	}

	.agent-tool-log-grid .mg-row-detail pre,
	.agent-tool-log-grid .mg-row-detail-value,
	.agent-tool-log-grid .mg-row-detail-field-value {
		white-space: pre-wrap;
		word-break: break-word;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
	}

	@media (max-width: 980px) {
		.agent-tool-log-detail-layout {
			grid-template-columns: minmax(0, 1fr);
		}
	}


	@media (max-width: 720px) {
		.agent-tool-log-shell h1 {
			font-size: 21px;
		}

		.agent-tool-log-grid .mg-table-scroll {
			height: 420px;
		}
	}
</style>

<div class="agent-tool-log-shell">
	<h1>Agent tool log</h1>
	<p>
		Server-side ModularGrid view over <code>base3_missionbay_tooluse</code>.
		Use full-text search, exact tool and status filters, node filtering and a created-at range.
		Click a row to inspect arguments, result payload and error details.
	</p>

	<div class="agent-tool-log-grid">
		<div id="agent-tool-log-grid"></div>
		<div id="agent-tool-log-output" class="agent-tool-log-output"></div>
	</div>
</div>

<script type="module">
	const modularGridModule = await import(new URL(<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href);

	const {                AjaxAdapter,
		BulkActionsPlugin,
		ColumnVisibilityPlugin,
		FiltersPlugin,
		HeaderMenuPlugin,
		InfoPlugin,
		InfiniteScrollPlugin,
		ModularGrid,
		ResetPlugin,
		RowActionsPlugin,
		RowDetailPlugin,
		SearchPlugin,
		SelectionPlugin,
		SessionStoragePlugin
	} = modularGridModule;
	const chronoPickerModule = await import(new URL(<?php echo json_encode($chronoPickerJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href);

	const {                ChronoPicker,
		DatePickerPlugin,
		DateTimePlugin,
		KeyboardPlugin
	} = chronoPickerModule;
	const jsonLensModule = await import(new URL(<?php echo json_encode($jsonLensJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href);

	const {                JsonLens,
		TreeViewPlugin,
		SyntaxHighlightPlugin,
		ClipboardPlugin,
		SearchPlugin: JsonLensSearchPlugin,
		PathPlugin,
		RawViewPlugin
	} = jsonLensModule;

	const ENDPOINT_URL = <?php echo json_encode((string) $this->_['service'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_SELECTOR = '#agent-tool-log-grid';
	const LOG_SELECTOR = '#agent-tool-log-output';
	const TOOL_OPTIONS = <?php echo json_encode($this->_['toolOptions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const STATUS_OPTIONS = <?php echo json_encode($this->_['statusOptions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const BATCH_SIZE = 50;
	const JSON_DETAIL_LABELS = new Set(['Arguments JSON', 'Result JSON']);
	const CHRONO_FILTER_FIELDS = [
		{ key: 'created_from', label: 'Created from' },
		{ key: 'created_to', label: 'Created to' }
	];

	const chronoPickerBindings = new Map();

	const SORT_TYPES = {
		id: 'int',
		tool_name: 'string',
		label: 'string',
		node_id: 'string',
		call_id: 'string',
		iteration: 'int',
		status: 'string',
		created_at: 'datetime',
		updated_at: 'datetime',
		finished_at: 'datetime',
		duration_seconds: 'int'
	};

	const layout = {
		type: 'stack',
		className: 'mg-layout-root',
		children: [
			{
				type: 'zone',
				key: 'topLine1',
				className: 'agent-tool-log-panel agent-tool-log-panel--main'
			},
			{
				type: 'zone',
				key: 'topLine2',
				className: 'agent-tool-log-panel agent-tool-log-panel--filters'
			},
			{
				type: 'view',
				key: 'main',
				className: 'agent-tool-log-main'
			},
			{
				type: 'zone',
				key: 'statusZone',
				className: 'agent-tool-log-panel agent-tool-log-panel--status'
			}
		]
	};

	function setLog(message) {
		const logElement = document.querySelector(LOG_SELECTOR);

		if (!logElement) {
			return;
		}

		logElement.replaceChildren();

		const label = document.createElement('strong');
		label.textContent = 'Last action:';

		logElement.appendChild(label);
		logElement.appendChild(document.createTextNode(' ' + getText(message, 'None')));
	}

	function getText(value, placeholder = '—') {
		if (value === null || value === undefined || value === '') {
			return placeholder;
		}

		return String(value);
	}

	function formatDateTime(value) {
		if (!value) {
			return '—';
		}

		const date = new Date(String(value).replace(' ', 'T'));

		if (Number.isNaN(date.getTime())) {
			return String(value);
		}

		return new Intl.DateTimeFormat(undefined, {
			year: 'numeric',
			month: '2-digit',
			day: '2-digit',
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit'
		}).format(date);
	}

	function formatDuration(value) {
		if (value === null || value === undefined || value === '') {
			return '—';
		}

		const seconds = Number(value);

		if (Number.isNaN(seconds)) {
			return String(value);
		}

		return String(seconds) + ' s';
	}

	function getStatusClass(status) {
		const normalized = String(status || '').toLowerCase();

		if (normalized === 'finished') {
			return 'agent-tool-log-pill-status-finished';
		}

		if (normalized === 'error' || normalized === 'failed') {
			return 'agent-tool-log-pill-status-error';
		}

		return '';
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

	function renderCreatedAt(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-cell-stack';

		const main = document.createElement('div');
		main.className = 'agent-tool-log-cell-main';
		main.textContent = formatDateTime(row.created_at);

		const sub = document.createElement('div');
		sub.className = 'agent-tool-log-cell-sub';
		sub.textContent = 'Finished ' + getText(formatDateTime(row.finished_at)) + ' · ' + formatDuration(row.duration_seconds);

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderTool(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-cell-stack';

		const main = document.createElement('div');
		main.className = 'agent-tool-log-cell-main';
		main.textContent = getText(row.tool_name);

		const sub = document.createElement('div');
		sub.className = 'agent-tool-log-cell-sub';
		sub.textContent = getText(row.label);

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderStatus(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-pill-row';

		const status = document.createElement('span');
		status.className = ('agent-tool-log-pill agent-tool-log-pill-strong ' + getStatusClass(row.status)).trim();
		status.textContent = getText(row.status);

		const iteration = document.createElement('span');
		iteration.className = 'agent-tool-log-pill';
		iteration.textContent = 'Iteration ' + getText(row.iteration, '0');

		wrapper.appendChild(status);
		wrapper.appendChild(iteration);

		if (row.error_type) {
			const errorType = document.createElement('span');
			errorType.className = 'agent-tool-log-pill';
			errorType.textContent = row.error_type;
			wrapper.appendChild(errorType);
		}

		return wrapper;
	}

	function renderNodeCall(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-cell-stack';

		const main = document.createElement('div');
		main.className = 'agent-tool-log-cell-main';
		main.textContent = getText(row.node_id);

		const sub = document.createElement('div');
		sub.className = 'agent-tool-log-cell-sub';
		sub.textContent = getText(row.call_id);

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
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
			throw new Error('Request failed with status ' + String(response.status));
		}

		return response.json();
	}

	function getLogEntryId(context) {
		if (context && typeof context === 'object') {
			if (context.row && context.row.id !== null && context.row.id !== undefined && context.row.id !== '') {
				return context.row.id;
			}

			if (context.rowId !== null && context.rowId !== undefined && context.rowId !== '') {
				return context.rowId;
			}

			if (context.id !== null && context.id !== undefined && context.id !== '') {
				return context.id;
			}
		}

		if (context !== null && context !== undefined && context !== '') {
			return context;
		}

		return null;
	}

	async function loadRemoteDetail(context) {
		const id = getLogEntryId(context);

		if (id === null) {
			throw new Error('Missing log entry id for detail request.');
		}

		const response = await postJson({
			mode: 'detail',
			id
		});

		if (!response || !response.found || !response.detail) {
			throw new Error('No detail data returned for log entry ' + getText(id));
		}

		return response.detail;
	}

	function createDetailLoadingPlaceholder(context) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-detail-status';
		wrapper.textContent = 'Loading detail for log entry #' + getText(getLogEntryId(context)) + '...';

		return wrapper;
	}

	function createDetailErrorPlaceholder(context, error) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-detail-status agent-tool-log-detail-status-error';
		wrapper.textContent = 'Failed to load detail for log entry #' + getText(getLogEntryId(context)) + ': ' + getText(error, 'Unknown error');

		return wrapper;
	}

	function appendSafeContent(parent, content) {
		if (content === null || content === undefined || content === '') {
			parent.textContent = '—';
			return;
		}

		if (content instanceof Node) {
			parent.appendChild(content);
			return;
		}

		parent.textContent = String(content);
	}

	function createElement(className, text = null) {
		const element = document.createElement('div');
		element.className = className;

		if (text !== null && text !== undefined) {
			element.textContent = String(text);
		}

		return element;
	}

	function renderDetailBadges(badges) {
		if (!Array.isArray(badges) || badges.length === 0) {
			return null;
		}

		const wrapper = createElement('mg-row-detail-badges');

		badges.forEach((badge) => {
			if (badge === null || badge === undefined || badge === '') {
				return;
			}

			const item = document.createElement('span');
			item.className = 'mg-row-detail-badge';
			item.textContent = String(badge);
			wrapper.appendChild(item);
		});

		return wrapper.childNodes.length > 0 ? wrapper : null;
	}

	function createDetailSection(title, content) {
		if (!content) {
			return null;
		}

		const section = createElement('mg-row-detail-section');
		const header = createElement('mg-row-detail-section-header');
		const sectionTitle = createElement('mg-row-detail-section-title', title);

		header.appendChild(sectionTitle);
		section.appendChild(header);
		section.appendChild(content);

		return section;
	}

	function isJsonLensSection(row) {
		return !!row && typeof row === 'object' && JSON_DETAIL_LABELS.has(String(row.label || row.key || ''));
	}

	function createJsonLensValue(row) {
		if (typeof row.value === 'string' && typeof JsonLens.canParse === 'function' && JsonLens.canParse(row.value)) {
			try {
				const element = JsonLens.createElement({
					value: row.value,
					mode: 'tree',
					indent: 2,
					collapsedDepth: 2,
					showToolbar: true,
					plugins: [
						TreeViewPlugin,
						SyntaxHighlightPlugin,
						ClipboardPlugin,
						JsonLensSearchPlugin,
						PathPlugin,
						RawViewPlugin
					]
				});

				if (element instanceof Node) {
					const wrapper = createElement('agent-tool-log-jsonlens');
					wrapper.appendChild(element);
					return wrapper;
				}
			} catch (error) {
				const fallback = createElement('agent-tool-log-detail-status agent-tool-log-detail-status-error');
				fallback.textContent = 'Could not render JSON: ' + getText(error && error.message, String(error));
				return fallback;
			}
		}

		const fallback = document.createElement('pre');
		fallback.className = 'agent-tool-log-json-fallback';
		fallback.textContent = getText(row && row.value);

		return fallback;
	}

	function renderDetailFieldValue(row) {
		return document.createTextNode(getText(row && row.value));
	}

	function renderDetailFieldRows(rows, className) {
		if (!Array.isArray(rows) || rows.length === 0) {
			return null;
		}

		const wrapper = createElement(className);

		rows.forEach((row) => {
			if (!row || typeof row !== 'object') {
				return;
			}

			const item = createElement(className + '-item');
			const label = createElement(className + '-label', row.label || row.key || 'Value');
			const value = createElement(className + '-value');

			appendSafeContent(value, renderDetailFieldValue(row));
			item.appendChild(label);
			item.appendChild(value);
			wrapper.appendChild(item);
		});

		return wrapper.childNodes.length > 0 ? wrapper : null;
	}

	function renderJsonDetailBlocks(rows) {
		if (!Array.isArray(rows) || rows.length === 0) {
			return null;
		}

		const column = createElement('agent-tool-log-json-column');

		rows.forEach((row) => {
			if (!row || typeof row !== 'object') {
				return;
			}

			const block = createElement('agent-tool-log-json-block');
			const title = createElement('agent-tool-log-json-title', row.label || row.key || 'JSON');

			block.appendChild(title);
			block.appendChild(createJsonLensValue(row));
			column.appendChild(block);
		});

		return column.childNodes.length > 0 ? column : null;
	}

	function getFullscreenTarget(source) {
		if (source instanceof HTMLElement) {
			return source.closest('.mg-row-detail') || source.closest('.agent-tool-log-detail') || source;
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
				setLog('Opened log detail in fullscreen.');
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
		button.className = 'agent-tool-log-detail-fullscreen-button';
		button.textContent = 'Fullscreen';
		button.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			toggleDetailFullscreen(button);
		});

		return button;
	}

	function renderToolLogDetail(context) {
		const payload = context && context.payload ? context.payload : null;

		if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
			return document.createTextNode(getText(payload));
		}

		const wrapper = createElement('mg-row-detail-structured agent-tool-log-detail');
		const layout = createElement('agent-tool-log-detail-layout');
		const leftColumn = createElement('agent-tool-log-detail-left');
		const rightColumn = createElement('agent-tool-log-detail-right');
		const header = createElement('mg-row-detail-structured-header');
		const headerText = createElement('agent-tool-log-detail-header-text');
		const headerActions = createElement('agent-tool-log-detail-header-actions');

		if (payload.headline) {
			headerText.appendChild(createElement('mg-row-detail-structured-title', payload.headline));
		}

		if (payload.summary) {
			headerText.appendChild(createElement('mg-row-detail-structured-summary', payload.summary));
		}

		headerActions.appendChild(createFullscreenButton());
		header.appendChild(headerText);
		header.appendChild(headerActions);
		leftColumn.appendChild(header);

		const badges = renderDetailBadges(payload.badges || []);
		if (badges) {
			leftColumn.appendChild(badges);
		}

		const sectionRows = Array.isArray(payload.sections) ? payload.sections : [];
		const scalarRows = sectionRows.filter((row) => !isJsonLensSection(row));
		const jsonRows = sectionRows.filter((row) => isJsonLensSection(row));

		const details = createDetailSection('Details', renderDetailFieldRows(scalarRows, 'mg-row-detail-fields'));
		if (details) {
			leftColumn.appendChild(details);
		}

		const activity = createDetailSection('Activity', renderDetailFieldRows(payload.activity || [], 'mg-row-detail-fields'));
		if (activity) {
			leftColumn.appendChild(activity);
		}

		const jsonBlocks = renderJsonDetailBlocks(jsonRows);
		if (jsonBlocks) {
			rightColumn.appendChild(jsonBlocks);
		}

		if (leftColumn.childNodes.length > 0) {
			layout.appendChild(leftColumn);
		}

		if (rightColumn.childNodes.length > 0) {
			layout.appendChild(rightColumn);
		}

		if (layout.childNodes.length > 0) {
			wrapper.appendChild(layout);
		}

		return wrapper;
	}

	function createFallbackClipboardRecord(row) {
		return {
			id: row && row.id ? row.id : 0,
			node_id: getText(row && row.node_id, ''),
			call_id: getText(row && row.call_id, ''),
			tool_name: getText(row && row.tool_name, ''),
			label: getText(row && row.label, ''),
			iteration: row && row.iteration !== undefined ? row.iteration : 0,
			status: getText(row && row.status, ''),
			error_type: getText(row && row.error_type, ''),
			error_code: getText(row && row.error_code, ''),
			created_at: getText(row && row.created_at, ''),
			updated_at: getText(row && row.updated_at, ''),
			finished_at: getText(row && row.finished_at, ''),
			duration_seconds: row && row.duration_seconds !== undefined ? row.duration_seconds : null
		};
	}

	async function loadRemoteRecord(id) {
		const response = await postJson({
			mode: 'record',
			id
		});

		if (!response || !response.found || !response.record) {
			throw new Error('No record data returned for log entry ' + getText(id));
		}

		return response.record;
	}

	async function writeClipboardText(text) {
		if (navigator.clipboard && window.isSecureContext) {
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

		try {
			document.execCommand('copy');
		} finally {
			textarea.remove();
		}
	}

	async function copyPayloadToClipboard(payload) {
		await writeClipboardText(JSON.stringify(payload, null, 2));
	}

	async function copySingleLogEntry(row) {
		try {
			setLog('Copying log entry #' + getText(row && row.id) + '...');
			const record = await loadRemoteRecord(row.id);
			await copyPayloadToClipboard(record);
			setLog('Copied log entry #' + getText(row && row.id) + ' to clipboard.');
		} catch (error) {
			try {
				await copyPayloadToClipboard(createFallbackClipboardRecord(row));
				setLog('Copied visible data for log entry #' + getText(row && row.id) + ' to clipboard. Record lookup failed: ' + getText(error && error.message, String(error)));
			} catch (clipboardError) {
				setLog('Failed to copy log entry #' + getText(row && row.id) + ': ' + getText(clipboardError && clipboardError.message, String(clipboardError)));
			}
		}
	}

	async function copySelectedLogEntries(selectedRowIds) {
		const ids = Array.isArray(selectedRowIds)
			? selectedRowIds.filter((id) => id !== null && id !== undefined && id !== '')
			: [];

		if (ids.length === 0) {
			setLog('No log entries selected.');
			return;
		}

		setLog('Copying ' + String(ids.length) + ' selected log entries...');

		const records = [];
		const failedIds = [];

		for (const id of ids) {
			try {
				records.push(await loadRemoteRecord(id));
			} catch (error) {
				failedIds.push(id);
			}
		}

		if (records.length === 0) {
			setLog('Could not load any selected log entries for clipboard copy.');
			return;
		}

		try {
			await copyPayloadToClipboard(records);
			let message = 'Copied ' + String(records.length) + ' selected log entries to clipboard.';

			if (failedIds.length > 0) {
				message += ' Failed IDs: ' + failedIds.join(', ') + '.';
			}

			setLog(message);
		} catch (error) {
			setLog('Failed to copy selected log entries: ' + getText(error && error.message, String(error)));
		}
	}


	function findChronoFilterInput(root, field, fallbackIndex) {
		const exactSelector = [
			'name="' + field.key + '"',
			'data-key="' + field.key + '"',
			'data-filter-key="' + field.key + '"',
			'data-field-key="' + field.key + '"'
		].map((part) => 'input[' + part + ']').join(',');
		const exactInput = root.querySelector(exactSelector);

		if (exactInput instanceof HTMLInputElement) {
			return exactInput;
		}

		const groups = Array.from(root.querySelectorAll('.mg-control-group, label'));
		for (const group of groups) {
			const label = group.querySelector('.mg-label');
			const labelText = label ? label.textContent.trim() : '';

			if (labelText === field.label) {
				const input = group.querySelector('input.mg-input, input');

				if (input instanceof HTMLInputElement) {
					return input;
				}
			}
		}

		const dateInputs = Array.from(root.querySelectorAll('input.mg-input, input')).filter((input) => {
			if (!(input instanceof HTMLInputElement)) {
				return false;
			}

			return String(input.placeholder || '').includes('YYYY-MM-DD');
		});

		return dateInputs[fallbackIndex] || null;
	}

	function dispatchFilterInputChanged(input) {
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function closeChronoPicker(binding) {
		if (!binding || !binding.picker || typeof binding.picker.execute !== 'function') {
			return;
		}

		binding.picker.execute('close');
	}

	function repositionChronoPicker(binding) {
		if (!binding || !binding.input || !binding.picker) {
			return;
		}

		if (!binding.input.isConnected) {
			destroyChronoPickerBinding(binding.fieldKey);
			return;
		}

		const state = typeof binding.picker.getState === 'function'
			? binding.picker.getState()
			: null;

		if (!state || state.open !== true) {
			return;
		}

		if (typeof binding.picker.positionPopover === 'function') {
			binding.picker.positionPopover();
		}
	}

	function destroyChronoPickerBinding(fieldKey) {
		const binding = chronoPickerBindings.get(fieldKey);

		if (!binding) {
			return;
		}

		if (binding.commitTimer) {
			window.clearTimeout(binding.commitTimer);
		}

		window.removeEventListener('resize', binding.repositionHandler);
		document.removeEventListener('scroll', binding.repositionHandler, true);

		if (binding.picker && typeof binding.picker.destroy === 'function') {
			binding.picker.destroy();
		}

		if (binding.input) {
			delete binding.input.dataset.agentToolLogChronoPicker;
			delete binding.input._agentToolLogChronoPicker;
		}

		chronoPickerBindings.delete(fieldKey);
	}

	function cleanupChronoPickerBindings() {
		Array.from(chronoPickerBindings.values()).forEach((binding) => {
			if (!binding.input || !binding.input.isConnected) {
				destroyChronoPickerBinding(binding.fieldKey);
			}
		});
	}

	function scheduleChronoPickerFilterCommit(binding) {
		if (!binding || !binding.input || !binding.input.isConnected) {
			return;
		}

		if (binding.commitTimer) {
			window.clearTimeout(binding.commitTimer);
		}

		binding.commitTimer = window.setTimeout(() => {
			binding.commitTimer = null;
			dispatchFilterInputChanged(binding.input);
		}, 80);
	}

	function bindChronoPickerInput(root, field, index) {
		const input = findChronoFilterInput(root, field, index);

		if (!(input instanceof HTMLInputElement)) {
			return false;
		}

		const existingBinding = chronoPickerBindings.get(field.key);
		if (existingBinding && existingBinding.input === input) {
			return false;
		}

		if (existingBinding) {
			destroyChronoPickerBinding(field.key);
		}

		input.dataset.agentToolLogChronoPicker = '1';
		input.dataset.filterKey = field.key;
		input.name = input.name || field.key;
		input.classList.add('cp-input-bound');

		const binding = {
			fieldKey: field.key,
			input,
			picker: null,
			commitTimer: null,
			repositionHandler: null
		};

		const picker = new ChronoPicker(input, {
			mode: 'datetime',
			displayMode: 'popover',
			value: input.value || '',
			format: 'YYYY-MM-DD HH:mm',
			minuteStep: 1,
			closeOnSelect: false,
			plugins: [
				DatePickerPlugin,
				DateTimePlugin,
				KeyboardPlugin
			],
			onChange(value) {
				input.value = value || '';
				repositionChronoPicker(binding);
			}
		});

		const originalExecute = picker.execute.bind(picker);
		picker.execute = (commandName, payload) => {
			const result = originalExecute(commandName, payload);

			if (commandName === 'close' || commandName === 'clear') {
				scheduleChronoPickerFilterCommit(binding);
			}

			if (commandName === 'open') {
				window.setTimeout(() => repositionChronoPicker(binding), 0);
			}

			return result;
		};

		picker.init();
		binding.picker = picker;
		binding.repositionHandler = () => repositionChronoPicker(binding);

		window.addEventListener('resize', binding.repositionHandler);
		document.addEventListener('scroll', binding.repositionHandler, true);

		input._agentToolLogChronoPicker = picker;
		chronoPickerBindings.set(field.key, binding);

		return true;
	}

	function bindChronoPickerFilters(root) {
		cleanupChronoPickerBindings();

		CHRONO_FILTER_FIELDS.forEach((field, index) => {
			bindChronoPickerInput(root, field, index);
		});
	}

	function watchChronoPickerFilters(root) {
		bindChronoPickerFilters(root);

		const observer = new MutationObserver(() => {
			bindChronoPickerFilters(root);
		});

		observer.observe(root, {
			childList: true,
			subtree: true
		});
	}

	(async function() {
		const root = document.querySelector(GRID_SELECTOR);

		if (!root || root.dataset.initialized === '1') {
			return;
		}

		root.dataset.initialized = '1';

		let grid = null;

		const adapter = new AjaxAdapter({
			url: ENDPOINT_URL,
			method: 'POST',
			rowsPath: 'data',
			totalPath: 'total',
			mapRequest(request) {
				const state = grid ? grid.getState() : {};
				const filters = buildFilterPayload(state.filters || {});
				const sortKey = request.sortKey || 'created_at';
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
					filters,
					group: []
				};
			}
		});

		grid = new ModularGrid(GRID_SELECTOR, {
			layout,
			adapter,
			dataMode: 'server',
			server: {
				searchDebounceMs: 260,
				watchStateKeys: ['query', 'filters']
			},
			features: {
				paging: false
			},
			pageSize: BATCH_SIZE,
			sort: {
				key: 'created_at',
				direction: 'desc'
			},
			plugins: [
				SearchPlugin,
				FiltersPlugin,
				HeaderMenuPlugin,
				InfoPlugin,
				SelectionPlugin,
				RowActionsPlugin,
				BulkActionsPlugin,
				ColumnVisibilityPlugin,
				ResetPlugin,
				SessionStoragePlugin,
				RowDetailPlugin,
				InfiniteScrollPlugin
			],
			pluginOptions: {
				search: {
					zone: 'topLine1',
					order: 10,
					label: 'Search',
					placeholder: 'Search id, call id, tool, label, status, error'
				},
				filters: {
					zone: 'topLine2',
					order: 10,
					stateKey: 'filters',
					showClearButton: true,
					clearLabel: 'Clear filters',
					fields: [
						{
							key: 'tool_name',
							label: 'Tool',
							type: 'select',
							options: TOOL_OPTIONS
						},
						{
							key: 'status',
							label: 'Status',
							type: 'select',
							options: STATUS_OPTIONS
						},
						{
							key: 'node_id',
							label: 'Node',
							type: 'text',
							placeholder: 'Node id',
							width: 140
						},
						{
							key: 'created_from',
							label: 'Created from',
							type: 'text',
							placeholder: 'YYYY-MM-DD or YYYY-MM-DD HH:MM',
							width: 190
						},
						{
							key: 'created_to',
							label: 'Created to',
							type: 'text',
							placeholder: 'YYYY-MM-DD or YYYY-MM-DD HH:MM',
							width: 190
						}
					]
				},
				headerMenu: {
					showSortActions: true,
					showClearSortAction: true,
					showHideColumnAction: true
				},
				selection: {
					rowIdKey: 'id'
				},
				bulkActions: {
					zone: 'topLine1',
					order: 20,
					selectedLabel: 'Selected log entries',
					items: [
						{
							key: 'copy-selected-clipboard',
							label: 'Copy to clipboard',
							onClick(context) {
								copySelectedLogEntries(context.selectedRowIds || []);
							}
						},
						{
							key: 'clear-selection',
							label: 'Clear selection',
							command: 'clearSelection'
						}
					]
				},
				columnVisibility: {
					zone: ''
				},
				reset: {
					zone: 'topLine1',
					order: 30,
					label: 'Reset',
					sections: ['query', 'filters', 'columns', 'selection', 'detailView']
				},
				sessionStorage: {
					key: 'agent-tool-log-grid',
					sections: ['query', 'filters', 'columns', 'selection', 'detailView']
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
							key: 'copy-clipboard',
							label: 'Copy to clipboard',
							onClick(context) {
								copySingleLogEntry(context.row);
							}
						}
					]
				},
				rowDetail: {
					rowIdKey: 'id',
					clearOnDataReload: true,
					asyncDetail: {
						load(context) {
							return loadRemoteDetail(context);
						},
						renderLoading(context) {
							return createDetailLoadingPlaceholder(context);
						},
						renderError(context) {
							return createDetailErrorPlaceholder(context, context.error);
						},
						render(context) {
							return renderToolLogDetail(context);
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
					key: 'created_at',
					label: 'Created',
					width: 240,
					headerMenu: {
						defaultSortKey: 'created_at',
						defaultSortDirection: 'desc',
						sortOptions: [
							{ key: 'created_at', label: 'Created' },
							{ key: 'finished_at', label: 'Finished' },
							{ key: 'duration_seconds', label: 'Duration' }
						]
					},
					render(value, row) {
						return renderCreatedAt(value, row);
					}
				},
				{
					key: 'tool_name',
					label: 'Tool',
					width: 260,
					headerMenu: {
						defaultSortKey: 'tool_name',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'tool_name', label: 'Tool' },
							{ key: 'label', label: 'Label' }
						]
					},
					render(value, row) {
						return renderTool(value, row);
					}
				},
				{
					key: 'status',
					label: 'Status',
					width: 220,
					headerMenu: {
						defaultSortKey: 'status',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'status', label: 'Status' },
							{ key: 'iteration', label: 'Iteration' }
						]
					},
					render(value, row) {
						return renderStatus(value, row);
					}
				},
				{
					key: 'node_id',
					label: 'Node / Call',
					width: 420,
					textDisplay: {
						strategy: 'clamp',
						lines: 3,
						expandable: true
					},
					headerMenu: {
						defaultSortKey: 'node_id',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'node_id', label: 'Node' },
							{ key: 'call_id', label: 'Call ID' }
						]
					},
					render(value, row) {
						return renderNodeCall(value, row);
					}
				},
				{
					key: 'id',
					label: 'ID',
					width: 90,
					visible: false,
					headerMenu: {
						defaultSortKey: 'id',
						defaultSortDirection: 'desc',
						sortOptions: [
							{ key: 'id', label: 'ID' }
						]
					}
				},
				{
					key: 'call_id',
					label: 'Call ID',
					width: 360,
					visible: false,
					textDisplay: {
						strategy: 'clamp',
						lines: 3,
						expandable: true
					},
					headerMenu: {
						defaultSortKey: 'call_id',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'call_id', label: 'Call ID' }
						]
					}
				},
				{
					key: 'label',
					label: 'Label',
					width: 240,
					visible: false,
					headerMenu: {
						defaultSortKey: 'label',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'label', label: 'Label' }
						]
					}
				}
			]
		});

		grid.on('bulkAction:run', ({ selectedRowIds }) => {
			setLog('Bulk action on IDs: ' + (selectedRowIds.join(', ') || 'none'));
		});

		grid.on('data:appended', ({ appendedCount, totalLoaded }) => {
			setLog('Loaded ' + String(appendedCount) + ' more log entries. ' + String(totalLoaded) + ' log entries are currently loaded.');
		});

		grid.on('detail:loaded', (event) => {
			const detailRowId = event && typeof event === 'object' ? event.rowId : null;

			setLog('Loaded detail for log entry #' + getText(detailRowId));
		});

		grid.on('detail:error', (event) => {
			const detailRowId = event && typeof event === 'object' ? event.rowId : null;
			const detailError = event && typeof event === 'object' ? event.error : null;

			setLog('Failed to load detail for log entry #' + getText(detailRowId) + ': ' + getText(detailError));
		});

		await grid.init();
		watchChronoPickerFilters(root);
		setLog('Initial batch loaded. Scroll to append the next ' + String(BATCH_SIZE) + ' log entries automatically.');
	})();
</script>
