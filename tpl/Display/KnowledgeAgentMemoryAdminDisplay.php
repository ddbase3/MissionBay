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
	.knowledge-agent-memory-shell {
		max-width: 1700px;
	}

	.knowledge-agent-memory-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.knowledge-agent-memory-shell p {
		margin: 0 0 16px 0;
		max-width: 1200px;
		color: #555;
		line-height: 1.45;
	}

	.knowledge-agent-memory-grid .knowledge-agent-memory-panel {
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

	.knowledge-agent-memory-grid .knowledge-agent-memory-panel--filters {
		flex-wrap: wrap;
		align-items: flex-start;
		overflow-x: visible;
	}

	.knowledge-agent-memory-grid .knowledge-agent-memory-panel--filters .mg-control-group {
		flex-wrap: wrap;
		align-items: center;
		row-gap: 8px;
	}

	.knowledge-agent-memory-grid .knowledge-agent-memory-panel > * {
		flex: 0 0 auto;
	}

	.knowledge-agent-memory-grid .knowledge-agent-memory-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.knowledge-agent-memory-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.knowledge-agent-memory-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.knowledge-agent-memory-grid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.knowledge-agent-memory-grid .mg-input,
	.knowledge-agent-memory-grid .mg-select,
	.knowledge-agent-memory-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.knowledge-agent-memory-grid .mg-input {
		width: auto;
	}

	.knowledge-agent-memory-grid input[type="search"].mg-input {
		width: 320px;
	}

	.knowledge-agent-memory-grid .mg-select {
		width: auto;
		min-width: 128px;
	}

	.knowledge-agent-memory-grid .mg-table-scroll {
		height: 570px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.knowledge-agent-memory-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.knowledge-agent-memory-grid .mg-table thead th.mg-cell-pinned {
		z-index: 14;
	}

	.knowledge-agent-memory-grid .mg-table th,
	.knowledge-agent-memory-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.knowledge-agent-memory-cell-stack {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.knowledge-agent-memory-cell-main {
		font-weight: 600;
		color: #222;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.knowledge-agent-memory-cell-sub {
		font-size: 12px;
		color: #666;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.knowledge-agent-memory-pill-row {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		align-items: center;
	}

	.knowledge-agent-memory-pill {
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

	.knowledge-agent-memory-pill-strong {
		background: #f0f0f0;
		color: #222;
		border-color: #cfcfcf;
	}

	.knowledge-agent-memory-pill-type-task {
		background: #f4f1ff;
		border-color: #d8cdf6;
	}

	.knowledge-agent-memory-pill-type-episodic {
		background: #fff7e8;
		border-color: #ead6a8;
	}

	.knowledge-agent-memory-pill-type-semantic {
		background: #edf6ff;
		border-color: #c3dff5;
	}

	.knowledge-agent-memory-pill-type-procedural {
		background: #eef7ee;
		border-color: #bddfbd;
	}

	.knowledge-agent-memory-pill-status-deleted,
	.knowledge-agent-memory-pill-status-inactive {
		background: #f2f2f2;
		border-color: #d4d4d4;
		color: #666;
	}

	.knowledge-agent-memory-pill-status-locked,
	.knowledge-agent-memory-pill-status-expired {
		background: #fff0f0;
		border-color: #e4b9b9;
		color: #8a1f1f;
	}

	.knowledge-agent-memory-output {
		margin-top: 12px;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.knowledge-agent-memory-output strong {
		color: #222;
	}

	.knowledge-agent-memory-detail {
		min-width: 0;
	}

	.knowledge-agent-memory-detail .mg-row-detail-structured-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		min-width: 0;
	}

	.knowledge-agent-memory-detail .mg-row-detail-badges {
		margin: 8px 0 12px 0;
		padding: 2px 0;
	}

	.knowledge-agent-memory-detail-header-text {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.knowledge-agent-memory-detail-header-actions {
		display: inline-flex;
		align-items: flex-start;
		justify-content: flex-end;
		flex: 0 0 auto;
		gap: 6px;
	}

	.knowledge-agent-memory-detail-button {
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

	.knowledge-agent-memory-detail-button:hover {
		background: #f5f5f5;
	}

	.knowledge-agent-memory-detail-button:focus-visible {
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

	.mg-row-detail:fullscreen .knowledge-agent-memory-detail,
	.mg-row-detail:-webkit-full-screen .knowledge-agent-memory-detail {
		display: block;
		align-items: flex-start;
		justify-content: flex-start;
		width: auto;
		height: auto;
		min-height: 0;
	}

	.knowledge-agent-memory-detail-layout {
		display: grid;
		grid-template-columns: minmax(280px, 430px) minmax(420px, 1fr);
		align-items: start;
		gap: 16px;
		min-width: 0;
	}

	.knowledge-agent-memory-detail-left,
	.knowledge-agent-memory-detail-right,
	.knowledge-agent-memory-json-column {
		display: grid;
		align-content: start;
		align-items: start;
		gap: 12px;
		min-width: 0;
	}

	.knowledge-agent-memory-detail .mg-row-detail-section,
	.knowledge-agent-memory-detail .mg-row-detail-fields,
	.knowledge-agent-memory-detail .mg-row-detail-fields-item,
	.knowledge-agent-memory-json-block {
		align-self: start;
		min-width: 0;
	}

	.knowledge-agent-memory-detail .mg-row-detail-fields-item {
		align-items: flex-start !important;
	}

	.knowledge-agent-memory-detail .mg-row-detail-fields-label,
	.knowledge-agent-memory-detail .mg-row-detail-fields-value {
		align-self: flex-start !important;
		vertical-align: top !important;
	}

	.knowledge-agent-memory-detail .mg-row-detail-fields-value {
		min-width: 0;
		width: 100%;
	}

	.knowledge-agent-memory-json-block {
		display: grid;
		gap: 6px;
	}

	.knowledge-agent-memory-json-title {
		color: #546274;
		font-size: 12px;
		font-weight: 600;
		letter-spacing: 0.03em;
		line-height: 1.2;
		text-transform: uppercase;
	}

	.knowledge-agent-memory-jsonlens,
	.knowledge-agent-memory-json-fallback {
		align-self: start;
		display: block;
		min-width: 0;
		width: 100%;
	}

	.knowledge-agent-memory-jsonlens .jl-root {
		width: 100%;
	}

	.knowledge-agent-memory-jsonlens .jl-body {
		max-height: 520px;
	}

	.knowledge-agent-memory-json-fallback {
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

	.knowledge-agent-memory-detail-status {
		font-size: 13px;
		color: #5a6980;
	}

	.knowledge-agent-memory-detail-status-error {
		color: #8a1f1f;
	}

	.knowledge-agent-memory-grid .mg-row-detail pre,
	.knowledge-agent-memory-grid .mg-row-detail-value,
	.knowledge-agent-memory-grid .mg-row-detail-field-value,
	.knowledge-agent-memory-detail .mg-row-detail-fields-value {
		white-space: pre-wrap;
		word-break: break-word;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
	}



	.knowledge-agent-memory-summary-cell {
		display: grid;
		gap: 4px;
		min-width: 0;
		color: #444;
		font-size: 13px;
		line-height: 1.4;
		overflow-wrap: anywhere;
		white-space: normal;
	}

	.knowledge-agent-memory-summary-text {
		min-width: 0;
		overflow: hidden;
		overflow-wrap: anywhere;
		white-space: pre-wrap;
	}

	.knowledge-agent-memory-summary-cell:not(.knowledge-agent-memory-summary-cell-expanded) .knowledge-agent-memory-summary-text {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 3;
	}

	.knowledge-agent-memory-summary-toggle {
		justify-self: start;
		appearance: none;
		border: 0;
		background: transparent;
		color: #2f5d91;
		cursor: pointer;
		font: inherit;
		font-size: 12px;
		line-height: 1.2;
		padding: 0;
	}

	.knowledge-agent-memory-summary-toggle:hover {
		text-decoration: underline;
	}

	.knowledge-agent-memory-summary-toggle:focus-visible {
		outline: 2px solid #86a8cf;
		outline-offset: 2px;
	}

	.knowledge-agent-memory-filter-picker {
		order: -100;
	}

	.knowledge-agent-memory-filter-picker .mg-select {
		min-width: 170px;
	}

	.knowledge-agent-memory-optional-filter-remove {
		appearance: none;
		border: 1px solid #d4d4d4;
		border-radius: 999px;
		background: #fff;
		color: #555;
		cursor: pointer;
		font: inherit;
		font-size: 11px;
		line-height: 1;
		min-height: 22px;
		min-width: 22px;
		padding: 0 6px;
	}

	.knowledge-agent-memory-optional-filter-remove:hover {
		background: #f5f5f5;
		color: #222;
	}

	.knowledge-agent-memory-grid .mg-text-toggle,
	.knowledge-agent-memory-grid .mg-text-display-toggle,
	.knowledge-agent-memory-grid .mg-cell-expand-toggle,
	.knowledge-agent-memory-grid .mg-cell-collapse-toggle,
	.knowledge-agent-memory-grid .mg-more-less,
	.knowledge-agent-memory-grid .mg-more,
	.knowledge-agent-memory-grid .mg-less {
		display: none !important;
	}

	.knowledge-agent-memory-detail-meta {
		display: grid;
		gap: 10px;
		min-width: 0;
	}

	.knowledge-agent-memory-detail-meta-group {
		display: grid;
		gap: 5px;
		min-width: 0;
	}

	.knowledge-agent-memory-detail-meta-title {
		color: #546274;
		font-size: 12px;
		font-weight: 600;
		letter-spacing: 0.03em;
		line-height: 1.2;
		text-transform: uppercase;
	}

	.knowledge-agent-memory-content-section {
		display: grid;
		gap: 8px;
		min-width: 0;
		align-self: start;
		padding: 12px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
	}

	.knowledge-agent-memory-content-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		min-width: 0;
	}

	.knowledge-agent-memory-content-title {
		color: #222;
		font-size: 14px;
		font-weight: 600;
		line-height: 1.25;
	}

	.knowledge-agent-memory-content-actions {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		flex: 0 0 auto;
	}

	.knowledge-agent-memory-content-view {
		display: block;
		margin: 0;
		min-height: 180px;
		max-height: 560px;
		min-width: 0;
		width: 100%;
		overflow: auto;
		padding: 10px 12px;
		border: 1px solid #e2e2e2;
		border-radius: 6px;
		background: #fafafa;
		color: #222;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.45;
		white-space: pre-wrap;
		word-break: break-word;
		cursor: text;
	}

	.knowledge-agent-memory-content-editor {
		display: none;
		min-height: 360px;
		max-height: 640px;
		min-width: 0;
		width: 100%;
		resize: vertical;
		padding: 10px 12px;
		border: 1px solid #b8c6d8;
		border-radius: 6px;
		background: #fff;
		color: #222;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.45;
	}

	.knowledge-agent-memory-content-hint {
		color: #666;
		font-size: 12px;
		line-height: 1.35;
	}

	.knowledge-agent-memory-content-section.is-editing .knowledge-agent-memory-content-view {
		display: none;
	}

	.knowledge-agent-memory-content-section.is-editing .knowledge-agent-memory-content-editor {
		display: block;
	}

	.knowledge-agent-memory-content-section.is-saving {
		opacity: 0.72;
		pointer-events: none;
	}

	@media (max-width: 980px) {
		.knowledge-agent-memory-detail-layout {
			grid-template-columns: minmax(0, 1fr);
		}
	}

	@media (max-width: 720px) {
		.knowledge-agent-memory-shell h1 {
			font-size: 21px;
		}

		.knowledge-agent-memory-grid .mg-table-scroll {
			height: 430px;
		}
	}
</style>

<div class="knowledge-agent-memory-shell">
	<h1>Knowledge agent memory</h1>
	<p>
		Server-side ModularGrid view over <code>base3_agent_knowledge</code>.
		Search and filter task, episodic, semantic and procedural memory entries, inspect the full content and JSON metadata, copy entries to clipboard and manage common admin flags.
	</p>

	<div class="knowledge-agent-memory-grid">
		<div id="knowledge-agent-memory-grid"></div>
		<div id="knowledge-agent-memory-output" class="knowledge-agent-memory-output"></div>
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
	const GRID_SELECTOR = '#knowledge-agent-memory-grid';
	const LOG_SELECTOR = '#knowledge-agent-memory-output';
	const MEMORY_TYPE_OPTIONS = <?php echo json_encode($this->_['memoryTypeOptions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const STATUS_OPTIONS = <?php echo json_encode($this->_['statusOptions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const SOURCE_OPTIONS = <?php echo json_encode($this->_['sourceOptions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const SCOPE_OPTIONS = <?php echo json_encode($this->_['scopeOptions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const BATCH_SIZE = 50;
	const JSON_DETAIL_LABELS = new Set(['Meta JSON']);
	const CHRONO_FILTER_FIELDS = [
		{ key: 'created_from', label: 'Created from' },
		{ key: 'created_to', label: 'Created to' },
		{ key: 'updated_from', label: 'Updated from' },
		{ key: 'updated_to', label: 'Updated to' }
	];
	const FILTER_FIELDS = [
		{ key: 'memory_type', label: 'Type', defaultValue: '', alwaysVisible: true },
		{ key: 'status', label: 'Status', defaultValue: '' },
		{ key: 'source', label: 'Source', defaultValue: '' },
		{ key: 'scope', label: 'Scope', defaultValue: '' },
		{ key: 'deleted', label: 'Deleted', defaultValue: 'active', alwaysVisible: true },
		{ key: 'locked', label: 'Locked', defaultValue: '' },
		{ key: 'expired', label: 'Expiry', defaultValue: '' },
		{ key: 'memory_key', label: 'Key', defaultValue: '' },
		{ key: 'ident', label: 'Ident', defaultValue: '' },
		{ key: 'scope_ref', label: 'Scope ref', defaultValue: '' },
		{ key: 'userid', label: 'User', defaultValue: '' },
		{ key: 'tag', label: 'Tag', defaultValue: '' },
		{ key: 'entity_ref', label: 'Entity ref', defaultValue: '' },
		{ key: 'created_from', label: 'Created from', defaultValue: '' },
		{ key: 'created_to', label: 'Created to', defaultValue: '' },
		{ key: 'updated_from', label: 'Updated from', defaultValue: '' },
		{ key: 'updated_to', label: 'Updated to', defaultValue: '' }
	];
	const OPTIONAL_FILTER_FIELDS = FILTER_FIELDS.filter((field) => !field.alwaysVisible);
	const FILTER_DEFAULTS = FILTER_FIELDS.reduce((carry, field) => {
		carry[field.key] = field.defaultValue || '';
		return carry;
	}, {});

	const chronoPickerBindings = new Map();
	const visibleOptionalFilters = new Set();
	let grid = null;

	const SORT_TYPES = {
		id: 'int',
		memory_type: 'string',
		memory_key: 'string',
		memory_subtype: 'string',
		status: 'string',
		title: 'string',
		summary: 'string',
		source: 'string',
		scope: 'string',
		scope_ref: 'string',
		ident: 'string',
		userid: 'string',
		priority: 'int',
		confidence: 'float',
		valid_from: 'datetime',
		valid_to: 'datetime',
		expires_at: 'datetime',
		last_accessed_at: 'datetime',
		created_at: 'datetime',
		updated_at: 'datetime'
	};

	const layout = {
		type: 'stack',
		className: 'mg-layout-root',
		children: [
			{
				type: 'zone',
				key: 'topLine1',
				className: 'knowledge-agent-memory-panel knowledge-agent-memory-panel--main'
			},
			{
				type: 'zone',
				key: 'topLine2',
				className: 'knowledge-agent-memory-panel knowledge-agent-memory-panel--filters'
			},
			{
				type: 'view',
				key: 'main',
				className: 'knowledge-agent-memory-main'
			},
			{
				type: 'zone',
				key: 'statusZone',
				className: 'knowledge-agent-memory-panel knowledge-agent-memory-panel--status'
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

	function getText(value, placeholder = '-') {
		if (value === null || value === undefined || value === '') {
			return placeholder;
		}

		return String(value);
	}

	function formatDateTime(value) {
		if (!value) {
			return '-';
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

	function formatBool(value) {
		return value ? 'yes' : 'no';
	}

	function formatConfidence(value) {
		if (value === null || value === undefined || value === '') {
			return '-';
		}

		const numeric = Number(value);

		if (Number.isNaN(numeric)) {
			return String(value);
		}

		return numeric.toFixed(4);
	}

	function getTypeClass(memoryType) {
		return 'knowledge-agent-memory-pill-type-' + String(memoryType || '').toLowerCase();
	}

	function getStatusClass(row) {
		if (row && row.is_deleted) {
			return 'knowledge-agent-memory-pill-status-deleted';
		}

		if (row && row.is_locked) {
			return 'knowledge-agent-memory-pill-status-locked';
		}

		if (row && row.is_expired) {
			return 'knowledge-agent-memory-pill-status-expired';
		}

		const normalized = String(row && row.status || '').toLowerCase();

		if (normalized === 'inactive') {
			return 'knowledge-agent-memory-pill-status-inactive';
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

		if (!Object.prototype.hasOwnProperty.call(result, 'deleted')) {
			result.deleted = 'active';
		}

		return result;
	}

	function createElement(className, text = null) {
		const element = document.createElement('div');
		element.className = className;

		if (text !== null && text !== undefined) {
			element.textContent = String(text);
		}

		return element;
	}

	function createPill(text, className = '') {
		const pill = document.createElement('span');
		pill.className = ('knowledge-agent-memory-pill ' + className).trim();
		pill.textContent = text;

		return pill;
	}

	function appendLimitedPills(wrapper, values, maxCount) {
		const list = Array.isArray(values) ? values : [];
		list.slice(0, maxCount).forEach((value) => {
			wrapper.appendChild(createPill(getText(value)));
		});

		if (list.length > maxCount) {
			wrapper.appendChild(createPill('+' + String(list.length - maxCount)));
		}
	}

	function renderTitle(value, row) {
		const wrapper = createElement('knowledge-agent-memory-cell-stack');
		const main = createElement('knowledge-agent-memory-cell-main', getText(row.title));
		const keyText = row.memory_key ? 'Key: ' + row.memory_key : 'Key: -';
		const sub = createElement('knowledge-agent-memory-cell-sub', keyText);

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function isLongSummary(text) {
		const value = String(text || '');

		if (value.length > 240) {
			return true;
		}

		return value.split(/\r?\n/).length > 3;
	}

	function renderSummary(value, row) {
		const wrapper = createElement('knowledge-agent-memory-summary-cell');
		const rawText = row && row.summary !== null && row.summary !== undefined ? String(row.summary) : '';
		const text = rawText !== '' ? rawText : '-';
		const body = createElement('knowledge-agent-memory-summary-text', text);

		wrapper.appendChild(body);

		if (rawText !== '') {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'knowledge-agent-memory-summary-toggle';
			button.textContent = 'More';
			button.addEventListener('click', (event) => {
				event.preventDefault();
				event.stopPropagation();

				const expanded = wrapper.classList.toggle('knowledge-agent-memory-summary-cell-expanded');
				button.textContent = expanded ? 'Less' : 'More';
			});

			wrapper.appendChild(button);
		}

		return wrapper;
	}

	function appendLabeledPillGroup(parent, label, values, maxCount) {
		const list = Array.isArray(values) ? values : [];
		const group = createElement('knowledge-agent-memory-cell-stack');
		const title = createElement('knowledge-agent-memory-cell-sub', label);
		const pills = createElement('knowledge-agent-memory-pill-row');

		appendLimitedPills(pills, list, maxCount);

		if (pills.childNodes.length === 0) {
			pills.appendChild(createPill('-'));
		}

		group.appendChild(title);
		group.appendChild(pills);
		parent.appendChild(group);
	}

	function renderTagsRefs(value, row) {
		const wrapper = createElement('knowledge-agent-memory-cell-stack');

		appendLabeledPillGroup(wrapper, 'Tags', row.tags || [], 4);
		appendLabeledPillGroup(wrapper, 'Entity refs', row.entity_refs || [], 3);

		return wrapper;
	}

	function renderTypeStatus(value, row) {
		const wrapper = createElement('knowledge-agent-memory-pill-row');
		wrapper.appendChild(createPill(getText(row.memory_type), ('knowledge-agent-memory-pill-strong ' + getTypeClass(row.memory_type)).trim()));
		wrapper.appendChild(createPill(getText(row.status), ('knowledge-agent-memory-pill-strong ' + getStatusClass(row)).trim()));

		if (row.memory_subtype) {
			wrapper.appendChild(createPill(row.memory_subtype));
		}

		if (row.is_deleted) {
			wrapper.appendChild(createPill('deleted', 'knowledge-agent-memory-pill-status-deleted'));
		}

		if (row.is_expired) {
			wrapper.appendChild(createPill('expired', 'knowledge-agent-memory-pill-status-expired'));
		}

		return wrapper;
	}

	function renderScope(value, row) {
		const wrapper = createElement('knowledge-agent-memory-cell-stack');
		const main = createElement('knowledge-agent-memory-cell-main', getText(row.scope));
		const subParts = [];

		if (row.scope_ref) {
			subParts.push('scope ref ' + row.scope_ref);
		}

		if (row.ident) {
			subParts.push('ident ' + row.ident);
		}

		if (row.userid) {
			subParts.push('user ' + row.userid);
		}

		const sub = createElement('knowledge-agent-memory-cell-sub', subParts.join(' | ') || '-');
		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderFlags(value, row) {
		const wrapper = createElement('knowledge-agent-memory-pill-row');
		wrapper.appendChild(createPill(row.is_locked ? 'locked' : 'unlocked', row.is_locked ? 'knowledge-agent-memory-pill-status-locked' : ''));
		wrapper.appendChild(createPill(row.is_mutable_by_llm ? 'mutable' : 'immutable'));
		wrapper.appendChild(createPill(row.is_deletable_by_llm ? 'deletable' : 'not deletable'));

		if (row.always_inject) {
			wrapper.appendChild(createPill('always inject'));
		}

		return wrapper;
	}

	function renderPriority(value, row) {
		const wrapper = createElement('knowledge-agent-memory-cell-stack');
		wrapper.appendChild(createElement('knowledge-agent-memory-cell-main', 'P ' + getText(row.priority, '0')));
		wrapper.appendChild(createElement('knowledge-agent-memory-cell-sub', 'Confidence ' + formatConfidence(row.confidence)));

		return wrapper;
	}

	function renderDates(value, row) {
		const wrapper = createElement('knowledge-agent-memory-cell-stack');
		wrapper.appendChild(createElement('knowledge-agent-memory-cell-main', 'Updated ' + formatDateTime(row.updated_at)));
		wrapper.appendChild(createElement('knowledge-agent-memory-cell-sub', 'Created ' + formatDateTime(row.created_at)));
		wrapper.appendChild(createElement('knowledge-agent-memory-cell-sub', 'Expires ' + formatDateTime(row.expires_at)));

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

	function getMemoryEntryId(context) {
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
		const id = getMemoryEntryId(context);

		if (id === null) {
			throw new Error('Missing knowledge entry id for detail request.');
		}

		const response = await postJson({
			mode: 'detail',
			id
		});

		if (!response || !response.found || !response.detail) {
			throw new Error('No detail data returned for knowledge entry ' + getText(id));
		}

		return response.detail;
	}

	function createDetailLoadingPlaceholder(context) {
		const wrapper = createElement('knowledge-agent-memory-detail-status');
		wrapper.textContent = 'Loading detail for knowledge entry #' + getText(getMemoryEntryId(context)) + '...';

		return wrapper;
	}

	function createDetailErrorPlaceholder(context, error) {
		const wrapper = createElement('knowledge-agent-memory-detail-status knowledge-agent-memory-detail-status-error');
		wrapper.textContent = 'Failed to load detail for knowledge entry #' + getText(getMemoryEntryId(context)) + ': ' + getText(error, 'Unknown error');

		return wrapper;
	}

	function appendSafeContent(parent, content) {
		if (content === null || content === undefined || content === '') {
			parent.textContent = '-';
			return;
		}

		if (content instanceof Node) {
			parent.appendChild(content);
			return;
		}

		parent.textContent = String(content);
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
					const wrapper = createElement('knowledge-agent-memory-jsonlens');
					wrapper.appendChild(element);
					return wrapper;
				}
			} catch (error) {
				const fallback = createElement('knowledge-agent-memory-detail-status knowledge-agent-memory-detail-status-error');
				fallback.textContent = 'Could not render JSON: ' + getText(error && error.message, String(error));
				return fallback;
			}
		}

		const fallback = document.createElement('pre');
		fallback.className = 'knowledge-agent-memory-json-fallback';
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

		const column = createElement('knowledge-agent-memory-json-column');

		rows.forEach((row) => {
			if (!row || typeof row !== 'object') {
				return;
			}

			const block = createElement('knowledge-agent-memory-json-block');
			const title = createElement('knowledge-agent-memory-json-title', row.label || row.key || 'JSON');

			block.appendChild(title);
			block.appendChild(createJsonLensValue(row));
			column.appendChild(block);
		});

		return column.childNodes.length > 0 ? column : null;
	}

	function getFullscreenTarget(source) {
		if (source instanceof HTMLElement) {
			return source.closest('.mg-row-detail') || source.closest('.knowledge-agent-memory-detail') || source;
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
				setLog('Opened knowledge entry detail in fullscreen.');
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
		button.className = 'knowledge-agent-memory-detail-button';
		button.textContent = 'Fullscreen';
		button.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			toggleDetailFullscreen(button);
		});

		return button;
	}

	function createCopyDetailButton(payload) {
		const button = document.createElement('button');

		button.type = 'button';
		button.className = 'knowledge-agent-memory-detail-button';
		button.textContent = 'Copy record';
		button.addEventListener('click', async(event) => {
			event.preventDefault();
			event.stopPropagation();

			if (!payload || !payload.id) {
				setLog('Cannot copy detail without entry id.');
				return;
			}

			await copySingleMemoryEntry({ id: payload.id });
		});

		return button;
	}

	async function updateRemoteContent(id, content) {
		const response = await postJson({
			mode: 'update_content',
			id,
			content
		});

		if (!response || response.ok !== true) {
			throw new Error(getText(response && (response.error || response.details), 'Unknown content update error'));
		}

		return response;
	}

	function createDetailMetaGroup(title, values) {
		const group = createElement('knowledge-agent-memory-detail-meta-group');
		const groupTitle = createElement('knowledge-agent-memory-detail-meta-title', title);
		const pills = createElement('knowledge-agent-memory-pill-row');
		const list = Array.isArray(values) ? values : [];

		appendLimitedPills(pills, list, 12);

		if (pills.childNodes.length === 0) {
			pills.appendChild(createPill('-'));
		}

		group.appendChild(groupTitle);
		group.appendChild(pills);

		return group;
	}

	function renderDetailMetadata(payload) {
		const wrapper = createElement('knowledge-agent-memory-detail-meta');

		wrapper.appendChild(createDetailMetaGroup('Tags', payload.tags || []));
		wrapper.appendChild(createDetailMetaGroup('Entity refs', payload.entity_refs || []));

		return wrapper;
	}

	function createEditableContentSection(payload) {
		const section = createElement('knowledge-agent-memory-content-section');
		const header = createElement('knowledge-agent-memory-content-header');
		const title = createElement('knowledge-agent-memory-content-title', 'Content');
		const actions = createElement('knowledge-agent-memory-content-actions');
		const editButton = document.createElement('button');
		const saveButton = document.createElement('button');
		const cancelButton = document.createElement('button');
		const view = document.createElement('pre');
		const textarea = document.createElement('textarea');
		const hint = createElement('knowledge-agent-memory-content-hint', 'Double click the text to edit. Use Save or Ctrl+Enter to store changes.');
		let currentValue = getText(payload.content, '');

		editButton.type = 'button';
		editButton.className = 'knowledge-agent-memory-detail-button';
		editButton.textContent = 'Edit';

		saveButton.type = 'button';
		saveButton.className = 'knowledge-agent-memory-detail-button';
		saveButton.textContent = 'Save';
		saveButton.hidden = true;

		cancelButton.type = 'button';
		cancelButton.className = 'knowledge-agent-memory-detail-button';
		cancelButton.textContent = 'Cancel';
		cancelButton.hidden = true;

		view.className = 'knowledge-agent-memory-content-view';
		view.tabIndex = 0;
		view.textContent = getText(currentValue);
		view.title = 'Double click to edit content';

		textarea.className = 'knowledge-agent-memory-content-editor';
		textarea.value = currentValue;

		function setEditing(isEditing) {
			section.classList.toggle('is-editing', isEditing);
			editButton.hidden = isEditing;
			saveButton.hidden = !isEditing;
			cancelButton.hidden = !isEditing;

			if (isEditing) {
				textarea.value = currentValue;
				window.setTimeout(() => textarea.focus(), 0);
			}
		}

		async function saveContent() {
			if (!payload.id) {
				setLog('Cannot update content without entry id.');
				return;
			}

			const nextValue = textarea.value;
			section.classList.add('is-saving');

			try {
				const response = await updateRemoteContent(payload.id, nextValue);
				currentValue = typeof response.content === 'string' ? response.content : nextValue;
				payload.content = currentValue;
				view.textContent = getText(currentValue);
				setEditing(false);
				setLog('Updated content for knowledge entry #' + getText(payload.id) + '.');
			} catch (error) {
				setLog('Failed to update content for knowledge entry #' + getText(payload.id) + ': ' + getText(error && error.message, String(error)));
			} finally {
				section.classList.remove('is-saving');
			}
		}

		editButton.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			setEditing(true);
		});

		view.addEventListener('dblclick', (event) => {
			event.preventDefault();
			event.stopPropagation();
			setEditing(true);
		});

		saveButton.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			saveContent();
		});

		cancelButton.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			textarea.value = currentValue;
			setEditing(false);
		});

		textarea.addEventListener('keydown', (event) => {
			if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
				event.preventDefault();
				saveContent();
			}

			if (event.key === 'Escape') {
				event.preventDefault();
				textarea.value = currentValue;
				setEditing(false);
			}
		});

		actions.appendChild(editButton);
		actions.appendChild(saveButton);
		actions.appendChild(cancelButton);
		header.appendChild(title);
		header.appendChild(actions);
		section.appendChild(header);
		section.appendChild(view);
		section.appendChild(textarea);
		section.appendChild(hint);

		return section;
	}

	function renderKnowledgeDetail(context) {
		const payload = context && context.payload ? context.payload : null;

		if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
			return document.createTextNode(getText(payload));
		}

		const wrapper = createElement('mg-row-detail-structured knowledge-agent-memory-detail');
		const detailLayout = createElement('knowledge-agent-memory-detail-layout');
		const leftColumn = createElement('knowledge-agent-memory-detail-left');
		const rightColumn = createElement('knowledge-agent-memory-detail-right');
		const header = createElement('mg-row-detail-structured-header');
		const headerText = createElement('knowledge-agent-memory-detail-header-text');
		const headerActions = createElement('knowledge-agent-memory-detail-header-actions');

		if (payload.headline) {
			headerText.appendChild(createElement('mg-row-detail-structured-title', payload.headline));
		}

		if (payload.summary) {
			headerText.appendChild(createElement('mg-row-detail-structured-summary', payload.summary));
		}

		headerActions.appendChild(createCopyDetailButton(payload));
		headerActions.appendChild(createFullscreenButton());
		header.appendChild(headerText);
		header.appendChild(headerActions);
		leftColumn.appendChild(header);

		const badges = renderDetailBadges(payload.badges || []);
		if (badges) {
			leftColumn.appendChild(badges);
		}

		const metadata = renderDetailMetadata(payload);
		if (metadata) {
			leftColumn.appendChild(metadata);
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

		const contentEditor = createEditableContentSection(payload);
		if (contentEditor) {
			rightColumn.appendChild(contentEditor);
		}

		const jsonBlocks = renderJsonDetailBlocks(jsonRows);
		if (jsonBlocks) {
			rightColumn.appendChild(jsonBlocks);
		}

		if (leftColumn.childNodes.length > 0) {
			detailLayout.appendChild(leftColumn);
		}

		if (rightColumn.childNodes.length > 0) {
			detailLayout.appendChild(rightColumn);
		}

		if (detailLayout.childNodes.length > 0) {
			wrapper.appendChild(detailLayout);
		}

		return wrapper;
	}

	function createFallbackClipboardRecord(row) {
		return {
			id: row && row.id ? row.id : 0,
			memory_type: getText(row && row.memory_type, ''),
			memory_key: getText(row && row.memory_key, ''),
			memory_subtype: getText(row && row.memory_subtype, ''),
			status: getText(row && row.status, ''),
			title: getText(row && row.title, ''),
			summary: getText(row && row.summary, ''),
			tags: row && Array.isArray(row.tags) ? row.tags : [],
			entity_refs: row && Array.isArray(row.entity_refs) ? row.entity_refs : [],
			source: getText(row && row.source, ''),
			scope: getText(row && row.scope, ''),
			scope_ref: getText(row && row.scope_ref, ''),
			ident: getText(row && row.ident, ''),
			userid: getText(row && row.userid, ''),
			session: getText(row && row.session, ''),
			is_locked: !!(row && row.is_locked),
			is_mutable_by_llm: !!(row && row.is_mutable_by_llm),
			is_deletable_by_llm: !!(row && row.is_deletable_by_llm),
			is_deleted: !!(row && row.is_deleted),
			priority: row && row.priority !== undefined ? row.priority : 0,
			confidence: row && row.confidence !== undefined ? row.confidence : null,
			created_at: getText(row && row.created_at, ''),
			updated_at: getText(row && row.updated_at, '')
		};
	}

	async function loadRemoteRecord(id) {
		const response = await postJson({
			mode: 'record',
			id
		});

		if (!response || !response.found || !response.record) {
			throw new Error('No record data returned for knowledge entry ' + getText(id));
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

	async function copySingleMemoryEntry(row) {
		try {
			setLog('Copying knowledge entry #' + getText(row && row.id) + '...');
			const record = await loadRemoteRecord(row.id);
			await copyPayloadToClipboard(record);
			setLog('Copied knowledge entry #' + getText(row && row.id) + ' to clipboard.');
		} catch (error) {
			try {
				await copyPayloadToClipboard(createFallbackClipboardRecord(row));
				setLog('Copied visible data for knowledge entry #' + getText(row && row.id) + ' to clipboard. Record lookup failed: ' + getText(error && error.message, String(error)));
			} catch (clipboardError) {
				setLog('Failed to copy knowledge entry #' + getText(row && row.id) + ': ' + getText(clipboardError && clipboardError.message, String(clipboardError)));
			}
		}
	}

	async function copySelectedMemoryEntries(selectedRowIds) {
		const ids = Array.isArray(selectedRowIds)
			? selectedRowIds.filter((id) => id !== null && id !== undefined && id !== '')
			: [];

		if (ids.length === 0) {
			setLog('No knowledge entries selected.');
			return;
		}

		setLog('Copying ' + String(ids.length) + ' selected knowledge entries...');

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
			setLog('Could not load any selected knowledge entries for clipboard copy.');
			return;
		}

		try {
			await copyPayloadToClipboard(records);
			let message = 'Copied ' + String(records.length) + ' selected knowledge entries to clipboard.';

			if (failedIds.length > 0) {
				message += ' Failed IDs: ' + failedIds.join(', ') + '.';
			}

			setLog(message);
		} catch (error) {
			setLog('Failed to copy selected knowledge entries: ' + getText(error && error.message, String(error)));
		}
	}

	async function refreshGrid() {
		if (!grid) {
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
				} catch (error) {
					// Try the next known command name.
				}
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

		window.location.reload();
	}

	async function performMemoryAction(mode, row, options = {}) {
		const id = row && row.id ? row.id : null;

		if (!id) {
			setLog('Missing knowledge entry id.');
			return false;
		}

		if (options.confirmMessage && !window.confirm(options.confirmMessage)) {
			return false;
		}

		try {
			const response = await postJson({
				mode,
				id
			});

			if (!response || response.ok !== true) {
				throw new Error(getText(response && (response.error || response.details), 'Unknown update error'));
			}

			setLog('Knowledge entry #' + getText(id) + ' ' + getText(response.action, mode) + '.');
			await refreshGrid();
			return true;
		} catch (error) {
			setLog('Failed to update knowledge entry #' + getText(id) + ': ' + getText(error && error.message, String(error)));
			return false;
		}
	}

	async function performBulkMemoryAction(mode, selectedRowIds, options = {}) {
		const ids = Array.isArray(selectedRowIds)
			? selectedRowIds.filter((id) => id !== null && id !== undefined && id !== '')
			: [];

		if (ids.length === 0) {
			setLog('No knowledge entries selected.');
			return;
		}

		if (options.confirmMessage && !window.confirm(options.confirmMessage.replace('{count}', String(ids.length)))) {
			return;
		}

		let successCount = 0;
		const failedIds = [];

		for (const id of ids) {
			try {
				const response = await postJson({
					mode,
					id
				});

				if (!response || response.ok !== true) {
					failedIds.push(id);
					continue;
				}

				successCount += 1;
			} catch (error) {
				failedIds.push(id);
			}
		}

		let message = 'Updated ' + String(successCount) + ' selected knowledge entries.';

		if (failedIds.length > 0) {
			message += ' Failed IDs: ' + failedIds.join(', ') + '.';
		}

		setLog(message);
		await refreshGrid();
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
			delete binding.input.dataset.knowledgeAgentMemoryChronoPicker;
			delete binding.input._knowledgeAgentMemoryChronoPicker;
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

		input.dataset.knowledgeAgentMemoryChronoPicker = '1';
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

		input._knowledgeAgentMemoryChronoPicker = picker;
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

	function getFilterPanel(root) {
		return root.querySelector('.knowledge-agent-memory-panel--filters');
	}

	function getFilterFieldByKey(key) {
		return FILTER_FIELDS.find((field) => field.key === key) || null;
	}

	function getFilterFieldByLabel(label) {
		return FILTER_FIELDS.find((field) => field.label === label) || null;
	}

	function getControlValue(control) {
		if (!control) {
			return '';
		}

		return String(control.value || '');
	}

	function isFilterValueDefault(key, value) {
		return getControlValue({ value }) === String(FILTER_DEFAULTS[key] || '');
	}

	function getFilterControlFromGroup(group) {
		const control = group.querySelector('select, input');

		if (control instanceof HTMLSelectElement || control instanceof HTMLInputElement) {
			return control;
		}

		return null;
	}

	function getFilterKeyFromGroup(group) {
		const control = getFilterControlFromGroup(group);

		if (control) {
			const key = control.getAttribute('name')
				|| control.dataset.key
				|| control.dataset.filterKey
				|| control.dataset.fieldKey
				|| '';

			if (key && getFilterFieldByKey(key)) {
				return key;
			}
		}

		const label = group.querySelector('.mg-label');
		const labelText = label ? label.textContent.trim() : '';
		const field = getFilterFieldByLabel(labelText);

		return field ? field.key : '';
	}

	function dispatchFilterControlChanged(control) {
		control.dispatchEvent(new Event('input', { bubbles: true }));
		control.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function resetFilterGroup(group, key) {
		const control = getFilterControlFromGroup(group);

		if (!control) {
			return;
		}

		control.value = String(FILTER_DEFAULTS[key] || '');
		dispatchFilterControlChanged(control);
	}

	function ensureOptionalFilterPicker(panel) {
		let picker = panel.querySelector('.knowledge-agent-memory-filter-picker');

		if (picker) {
			return picker;
		}

		picker = document.createElement('label');
		picker.className = 'mg-control-group knowledge-agent-memory-filter-picker';

		const label = document.createElement('span');
		label.className = 'mg-label';
		label.textContent = 'Add filter';

		const select = document.createElement('select');
		select.className = 'mg-select';

		picker.appendChild(label);
		picker.appendChild(select);
		panel.prepend(picker);

		select.addEventListener('change', () => {
			const key = select.value;

			if (key !== '') {
				visibleOptionalFilters.add(key);
				applyOptionalFilterVisibility(panel);
			}

			select.value = '';
		});

		return picker;
	}

	function updateOptionalFilterPickerOptions(panel) {
		const picker = ensureOptionalFilterPicker(panel);
		const select = picker.querySelector('select');

		if (!(select instanceof HTMLSelectElement)) {
			return;
		}

		const optionKeys = OPTIONAL_FILTER_FIELDS
			.filter((field) => !visibleOptionalFilters.has(field.key))
			.map((field) => field.key);
		const signature = optionKeys.join('|');

		if (select.dataset.optionSignature === signature) {
			return;
		}

		const current = select.value;
		select.replaceChildren();

		const placeholder = document.createElement('option');
		placeholder.value = '';
		placeholder.textContent = 'Select optional filter';
		select.appendChild(placeholder);

		optionKeys.forEach((key) => {
			const field = getFilterFieldByKey(key);

			if (!field) {
				return;
			}

			const option = document.createElement('option');
			option.value = field.key;
			option.textContent = field.label;
			select.appendChild(option);
		});

		select.dataset.optionSignature = signature;
		select.value = optionKeys.includes(current) ? current : '';
	}

	function ensureOptionalFilterRemoveButton(group, key, panel) {
		if (group.querySelector('.knowledge-agent-memory-optional-filter-remove')) {
			return;
		}

		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'knowledge-agent-memory-optional-filter-remove';
		button.title = 'Remove this filter';
		button.textContent = '×';
		button.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();

			resetFilterGroup(group, key);
			visibleOptionalFilters.delete(key);
			applyOptionalFilterVisibility(panel);
		});

		group.appendChild(button);
	}

	function applyOptionalFilterVisibility(panel) {
		if (!panel) {
			return;
		}

		ensureOptionalFilterPicker(panel);

		Array.from(panel.querySelectorAll('.mg-control-group')).forEach((group) => {
			if (group.classList.contains('knowledge-agent-memory-filter-picker')) {
				return;
			}

			const key = getFilterKeyFromGroup(group);
			const field = key !== '' ? getFilterFieldByKey(key) : null;

			if (!field) {
				return;
			}

			const control = getFilterControlFromGroup(group);
			const value = getControlValue(control);
			const hasNonDefaultValue = !isFilterValueDefault(key, value);

			if (field.alwaysVisible) {
				group.style.display = '';
				return;
			}

			if (hasNonDefaultValue) {
				visibleOptionalFilters.add(key);
			}

			ensureOptionalFilterRemoveButton(group, key, panel);
			group.style.display = visibleOptionalFilters.has(key) ? '' : 'none';
		});

		updateOptionalFilterPickerOptions(panel);
	}

	function watchOptionalFilterControls(root) {
		const panel = getFilterPanel(root);

		if (!panel) {
			return;
		}

		applyOptionalFilterVisibility(panel);

		const observer = new MutationObserver(() => {
			applyOptionalFilterVisibility(panel);
		});

		observer.observe(panel, {
			childList: true,
			subtree: true
		});
	}

	function cleanupNativeMoreLessButtons(root) {
		const labels = new Set(['more', 'less', 'show more', 'show less', 'mehr', 'weniger']);

		Array.from(root.querySelectorAll('button, a')).forEach((element) => {
			if (element.closest('.knowledge-agent-memory-summary-cell')) {
				return;
			}

			const label = String(element.textContent || '').trim().toLowerCase();

			if (labels.has(label)) {
				element.remove();
			}
		});
	}

	function watchNativeMoreLessCleanup(root) {
		cleanupNativeMoreLessButtons(root);

		const observer = new MutationObserver(() => {
			cleanupNativeMoreLessButtons(root);
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

		const adapter = new AjaxAdapter({
			url: ENDPOINT_URL,
			method: 'POST',
			rowsPath: 'data',
			totalPath: 'total',
			mapRequest(request) {
				const state = grid ? grid.getState() : {};
				const filters = buildFilterPayload(state.filters || {});
				const sortKey = request.sortKey || 'updated_at';
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
				key: 'updated_at',
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
					placeholder: 'Search title, content, key, tags, ident, user, session'
				},
				filters: {
					zone: 'topLine2',
					order: 10,
					stateKey: 'filters',
					showClearButton: true,
					clearLabel: 'Clear filters',
					fields: [
						{
							key: 'memory_type',
							label: 'Type',
							type: 'select',
							options: MEMORY_TYPE_OPTIONS
						},
						{
							key: 'status',
							label: 'Status',
							type: 'select',
							options: STATUS_OPTIONS
						},
						{
							key: 'source',
							label: 'Source',
							type: 'select',
							options: SOURCE_OPTIONS
						},
						{
							key: 'scope',
							label: 'Scope',
							type: 'select',
							options: SCOPE_OPTIONS
						},
						{
							key: 'deleted',
							label: 'Deleted',
							type: 'select',
							options: [
								{ value: 'active', label: 'Active only' },
								{ value: 'deleted', label: 'Deleted only' },
								{ value: 'all', label: 'All entries' }
							]
						},
						{
							key: 'locked',
							label: 'Locked',
							type: 'select',
							options: [
								{ value: '', label: 'All lock states' },
								{ value: 'locked', label: 'Locked only' },
								{ value: 'unlocked', label: 'Unlocked only' }
							]
						},
						{
							key: 'expired',
							label: 'Expiry',
							type: 'select',
							options: [
								{ value: '', label: 'All expiry states' },
								{ value: 'current', label: 'Current only' },
								{ value: 'expired', label: 'Expired only' },
								{ value: 'no_expiry', label: 'No expiry' }
							]
						},
						{
							key: 'memory_key',
							label: 'Key',
							type: 'text',
							placeholder: 'Memory key',
							width: 170
						},
						{
							key: 'ident',
							label: 'Ident',
							type: 'text',
							placeholder: 'Ident',
							width: 140
						},
						{
							key: 'scope_ref',
							label: 'Scope ref',
							type: 'text',
							placeholder: 'Scope ref',
							width: 140
						},
						{
							key: 'userid',
							label: 'User',
							type: 'text',
							placeholder: 'User ID',
							width: 100
						},
						{
							key: 'tag',
							label: 'Tag',
							type: 'text',
							placeholder: 'Tag',
							width: 140
						},
						{
							key: 'entity_ref',
							label: 'Entity ref',
							type: 'text',
							placeholder: 'Entity ref',
							width: 150
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
						},
						{
							key: 'updated_from',
							label: 'Updated from',
							type: 'text',
							placeholder: 'YYYY-MM-DD or YYYY-MM-DD HH:MM',
							width: 190
						},
						{
							key: 'updated_to',
							label: 'Updated to',
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
					selectedLabel: 'Selected memory entries',
					items: [
						{
							key: 'copy-selected-clipboard',
							label: 'Copy to clipboard',
							onClick(context) {
								copySelectedMemoryEntries(context.selectedRowIds || []);
							}
						},
						{
							key: 'soft-delete-selected',
							label: 'Soft delete selected',
							onClick(context) {
								performBulkMemoryAction('soft_delete', context.selectedRowIds || [], {
									confirmMessage: 'Soft delete {count} selected knowledge entries?'
								});
							}
						},
						{
							key: 'restore-selected',
							label: 'Restore selected',
							onClick(context) {
								performBulkMemoryAction('restore', context.selectedRowIds || []);
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
					key: 'knowledge-agent-memory-grid-v4',
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
								copySingleMemoryEntry(context.row);
							}
						},
						{
							key: 'lock',
							label: 'Lock',
							onClick(context) {
								performMemoryAction('lock', context.row);
							}
						},
						{
							key: 'unlock',
							label: 'Unlock',
							onClick(context) {
								performMemoryAction('unlock', context.row);
							}
						},
						{
							key: 'toggle-mutable',
							label: 'Toggle mutable by LLM',
							onClick(context) {
								performMemoryAction('toggle_mutable', context.row);
							}
						},
						{
							key: 'toggle-deletable',
							label: 'Toggle deletable by LLM',
							onClick(context) {
								performMemoryAction('toggle_deletable', context.row);
							}
						},
						{
							key: 'soft-delete',
							label: 'Soft delete',
							onClick(context) {
								performMemoryAction('soft_delete', context.row, {
									confirmMessage: 'Soft delete knowledge entry #' + getText(context.row && context.row.id) + '?'
								});
							}
						},
						{
							key: 'restore',
							label: 'Restore',
							onClick(context) {
								performMemoryAction('restore', context.row);
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
							return renderKnowledgeDetail(context);
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
					label: 'Memory',
					width: 330,
					headerMenu: {
						defaultSortKey: 'title',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'title', label: 'Title' },
							{ key: 'memory_key', label: 'Memory key' }
						]
					},
					render(value, row) {
						return renderTitle(value, row);
					}
				},
				{
					key: 'summary',
					label: 'Summary',
					width: 380,
					headerMenu: {
						defaultSortKey: 'summary',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'summary', label: 'Summary' }
						]
					},
					render(value, row) {
						return renderSummary(value, row);
					}
				},
				{
					key: 'tags_text',
					label: 'Tags / Entity refs',
					width: 320,
					sortable: false,
					render(value, row) {
						return renderTagsRefs(value, row);
					}
				},
				{
					key: 'memory_type',
					label: 'Type / Status',
					width: 240,
					headerMenu: {
						defaultSortKey: 'memory_type',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'memory_type', label: 'Type' },
							{ key: 'memory_subtype', label: 'Subtype' },
							{ key: 'status', label: 'Status' }
						]
					},
					render(value, row) {
						return renderTypeStatus(value, row);
					}
				},
				{
					key: 'scope',
					label: 'Scope / Identity',
					width: 290,
					headerMenu: {
						defaultSortKey: 'scope',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'scope', label: 'Scope' },
							{ key: 'scope_ref', label: 'Scope ref' },
							{ key: 'ident', label: 'Ident' },
							{ key: 'userid', label: 'User' }
						]
					},
					render(value, row) {
						return renderScope(value, row);
					}
				},
				{
					key: 'priority',
					label: 'Priority',
					width: 140,
					headerMenu: {
						defaultSortKey: 'priority',
						defaultSortDirection: 'desc',
						sortOptions: [
							{ key: 'priority', label: 'Priority' },
							{ key: 'confidence', label: 'Confidence' }
						]
					},
					render(value, row) {
						return renderPriority(value, row);
					}
				},
				{
					key: 'flags',
					label: 'Flags',
					width: 240,
					sortable: false,
					render(value, row) {
						return renderFlags(value, row);
					}
				},
				{
					key: 'updated_at',
					label: 'Dates',
					width: 260,
					headerMenu: {
						defaultSortKey: 'updated_at',
						defaultSortDirection: 'desc',
						sortOptions: [
							{ key: 'updated_at', label: 'Updated' },
							{ key: 'created_at', label: 'Created' },
							{ key: 'expires_at', label: 'Expires' }
						]
					},
					render(value, row) {
						return renderDates(value, row);
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
					key: 'memory_key',
					label: 'Memory key',
					width: 320,
					visible: false,
					headerMenu: {
						defaultSortKey: 'memory_key',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'memory_key', label: 'Memory key' }
						]
					}
				},
				{
					key: 'source',
					label: 'Source',
					width: 130,
					visible: false,
					headerMenu: {
						defaultSortKey: 'source',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'source', label: 'Source' }
						]
					}
				},
				{
					key: 'entity_refs_text',
					label: 'Entity refs',
					width: 300,
					visible: false,
					sortable: false
				}
			]
		});

		grid.on('bulkAction:run', ({ selectedRowIds }) => {
			setLog('Bulk action on IDs: ' + (selectedRowIds.join(', ') || 'none'));
		});

		grid.on('data:appended', ({ appendedCount, totalLoaded }) => {
			setLog('Loaded ' + String(appendedCount) + ' more knowledge entries. ' + String(totalLoaded) + ' entries are currently loaded.');
		});

		grid.on('detail:loaded', (event) => {
			const detailRowId = event && typeof event === 'object' ? event.rowId : null;

			setLog('Loaded detail for knowledge entry #' + getText(detailRowId));
		});

		grid.on('detail:error', (event) => {
			const detailRowId = event && typeof event === 'object' ? event.rowId : null;
			const detailError = event && typeof event === 'object' ? event.error : null;

			setLog('Failed to load detail for knowledge entry #' + getText(detailRowId) + ': ' + getText(detailError));
		});

		await grid.init();
		watchChronoPickerFilters(root);
		watchOptionalFilterControls(root);
		watchNativeMoreLessCleanup(root);
		setLog('Initial batch loaded. Scroll to append the next ' + String(BATCH_SIZE) + ' knowledge entries automatically.');
	})();
</script>
