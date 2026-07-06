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
		gap: 6px;
		flex: 0 0 auto;
	}

	.agent-tool-log-button {
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

	.agent-tool-log-button:hover {
		background: #f5f5f5;
	}

	.agent-tool-log-button:focus-visible {
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
		grid-template-columns: minmax(260px, 420px) minmax(360px, 1fr);
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

	.agent-tool-log-group-detail {
		display: grid;
		gap: 10px;
	}

	.agent-tool-log-group-detail-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
	}

	.agent-tool-log-group-detail-title {
		font-size: 15px;
		font-weight: 600;
		color: #1f2c3c;
	}

	.agent-tool-log-group-detail-subtitle {
		font-size: 12px;
		color: #5f6d7e;
	}

	.agent-tool-log-child-table-scroll {
		overflow-x: auto;
		border: 1px solid #dce4ed;
		border-radius: 6px;
		background: rgba(255, 255, 255, 0.82);
	}

	.agent-tool-log-child-table {
		width: 100%;
		min-width: 900px;
		border-collapse: collapse;
	}

	.agent-tool-log-child-table th,
	.agent-tool-log-child-table td {
		padding: 7px 10px;
		border-bottom: 1px solid #e3e8ef;
		text-align: left;
		vertical-align: top;
		font-size: 12px;
	}

	.agent-tool-log-child-table th {
		background: #f7f9fc;
		font-weight: 600;
		color: #425466;
		white-space: nowrap;
	}

	.agent-tool-log-child-table tbody tr:nth-child(even) {
		background: rgba(248, 250, 253, 0.8);
	}

	.agent-tool-log-child-table-empty {
		text-align: center;
		color: #667587;
	}

	.agent-tool-log-grid .mg-compact-filters {
		align-items: center;
		flex-wrap: wrap;
		row-gap: 8px;
	}

	.agent-tool-log-grid .mg-compact-filter-picker .mg-select {
		min-width: 170px;
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
		Use search, filters, grouping and clipboard actions to inspect, copy and share tool-call traces.
	</p>

	<div class="agent-tool-log-grid">
		<div id="agent-tool-log-grid"></div>
		<div id="agent-tool-log-output" class="agent-tool-log-output"></div>
	</div>
</div>

<script type="module">
	const modularGridModule = await import(new URL(<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href);

	const {
		AjaxAdapter,
		BulkActionsPlugin,
		ColumnVisibilityPlugin,
		CompactFiltersPlugin,
		GroupingPlugin,
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

	const {
		ChronoPicker,
		DatePickerPlugin,
		DateTimePlugin,
		KeyboardPlugin
	} = chronoPickerModule;

	const jsonLensModule = await import(new URL(<?php echo json_encode($jsonLensJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href);

	const {
		JsonLens,
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
	const GROUP_FIELD_OPTIONS = <?php echo json_encode($this->_['groupOptions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const BATCH_SIZE = 50;
	const JSON_DETAIL_LABELS = new Set(['Arguments JSON', 'Result JSON', 'Meta JSON']);
	const GROUP_METRIC_SORT_KEYS = new Set([
		'group_count',
		'group_last_created',
		'group_last_changed',
		'group_finished_count',
		'group_error_count',
		'group_duration_sum',
		'group_tools_preview',
		'group_users_preview',
		'group_anchor_id'
	]);
	const GROUP_NORMAL_SORT_KEYS = new Set([
		'id',
		'created_at',
		'updated_at',
		'finished_at',
		'duration_seconds'
	]);
	const NORMAL_SORT_FALLBACK = {
		key: 'created_at',
		direction: 'desc',
		type: 'datetime'
	};
	const FILTER_FIELDS = [
		{
			key: 'tool_name',
			label: 'Tool',
			type: 'select',
			defaultValue: '',
			visibility: 'always',
			options: TOOL_OPTIONS
		},
		{
			key: 'user',
			label: 'User',
			type: 'text',
			defaultValue: '',
			visibility: 'always',
			placeholder: 'User id or login',
			width: 160
		},
		{
			key: 'status',
			label: 'Status',
			type: 'select',
			defaultValue: '',
			visibility: 'optional',
			options: STATUS_OPTIONS
		},
		{
			key: 'turn_id',
			label: 'Turn',
			type: 'text',
			defaultValue: '',
			visibility: 'optional',
			placeholder: 'Turn id',
			width: 190
		},
		{
			key: 'chatbot_key',
			label: 'Chatbot',
			type: 'text',
			defaultValue: '',
			visibility: 'optional',
			placeholder: 'Chatbot key',
			width: 190
		},
		{
			key: 'config_name',
			label: 'Config',
			type: 'text',
			defaultValue: '',
			visibility: 'optional',
			placeholder: 'Config name',
			width: 180
		},
		{
			key: 'node_id',
			label: 'Node',
			type: 'text',
			defaultValue: '',
			visibility: 'optional',
			placeholder: 'Node id',
			width: 140
		},
		{
			key: 'created_from',
			label: 'Created from',
			type: 'custom',
			defaultValue: '',
			visibility: 'optional',
			placeholder: 'YYYY-MM-DD or YYYY-MM-DD HH:MM',
			width: 190,
			renderControl: renderChronoFilterControl
		},
		{
			key: 'created_to',
			label: 'Created to',
			type: 'custom',
			defaultValue: '',
			visibility: 'optional',
			placeholder: 'YYYY-MM-DD or YYYY-MM-DD HH:MM',
			width: 190,
			renderControl: renderChronoFilterControl
		}
	];
	const chronoPickerBindings = new Map();

	const SORT_TYPES = {
		id: 'int',
		turn_id: 'string',
		tool_name: 'string',
		label: 'string',
		node_id: 'string',
		call_id: 'string',
		call_index: 'int',
		iteration: 'int',
		status: 'string',
		chatbot_key: 'string',
		config_group: 'string',
		config_name: 'string',
		user_id: 'int',
		user_login: 'string',
		created_at: 'datetime',
		updated_at: 'datetime',
		finished_at: 'datetime',
		duration_seconds: 'int',
		group_count: 'int',
		group_last_created: 'datetime',
		group_last_changed: 'datetime',
		group_finished_count: 'int',
		group_error_count: 'int',
		group_duration_sum: 'int',
		group_tools_preview: 'string',
		group_users_preview: 'string',
		group_anchor_id: 'int'
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

	function isEmptyValue(value) {
		return value === null || value === undefined || value === '';
	}

	function getText(value, placeholder = '-') {
		if (isEmptyValue(value)) {
			return placeholder;
		}

		return String(value);
	}

	function getGroupFieldLabel(key) {
		const found = GROUP_FIELD_OPTIONS.find((option) => option.key === key);
		return found ? found.label : key;
	}

	function normalizeGroupFields(fields) {
		const allowedKeys = new Set(GROUP_FIELD_OPTIONS.map((option) => option.key));
		const normalized = [];
		const used = new Set();

		(fields || []).forEach((field) => {
			const key = String(field || '').trim();

			if (!key || !allowedKeys.has(key) || used.has(key)) {
				return;
			}

			used.add(key);
			normalized.push(key);
		});

		return normalized;
	}

	function getGroupFields() {
		if (!grid) {
			return [];
		}

		const fields = grid.getState().toolLogGrouping?.fields;

		return normalizeGroupFields(fields);
	}

	function buildGroupPayload(fields) {
		return normalizeGroupFields(fields).map((key) => {
			return {
				key,
				dir: 'asc'
			};
		});
	}

	function resolveSortForRequest(request) {
		const activeGroupFields = getGroupFields();
		const hasGrouping = activeGroupFields.length > 0;
		let sortKey = request.sortKey || NORMAL_SORT_FALLBACK.key;
		let sortDirection = request.sortDirection || NORMAL_SORT_FALLBACK.direction;

		if (hasGrouping) {
			const allowedGroupedSorts = new Set([
				...activeGroupFields,
				...GROUP_METRIC_SORT_KEYS,
				...GROUP_NORMAL_SORT_KEYS
			]);

			if (!allowedGroupedSorts.has(sortKey)) {
				sortKey = activeGroupFields[0];
				sortDirection = 'asc';
			}
		} else if (GROUP_METRIC_SORT_KEYS.has(sortKey)) {
			sortKey = NORMAL_SORT_FALLBACK.key;
			sortDirection = NORMAL_SORT_FALLBACK.direction;
		}

		return {
			key: sortKey,
			direction: sortDirection,
			type: SORT_TYPES[sortKey] || 'string'
		};
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

	function formatDuration(value) {
		if (value === null || value === undefined || value === '') {
			return '-';
		}

		const seconds = Number(value);

		if (Number.isNaN(seconds)) {
			return String(value);
		}

		return String(seconds) + ' s';
	}

	function formatNumber(value) {
		if (value === null || value === undefined || value === '') {
			return '-';
		}

		const number = Number(value);

		if (Number.isNaN(number)) {
			return String(value);
		}

		return new Intl.NumberFormat(undefined).format(number);
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

	function buildCurrentServerContext() {
		const state = grid ? grid.getState() : {};

		return {
			search: state.query?.search || '',
			filters: buildFilterPayload(state.filters || {}),
			group: buildGroupPayload(getGroupFields())
		};
	}

	function renderCreatedAt(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-cell-stack';

		if (row.is_group_row === true) {
			const main = document.createElement('div');
			main.className = 'agent-tool-log-cell-main';
			main.textContent = formatDateTime(row.group_last_created);

			const sub = document.createElement('div');
			sub.className = 'agent-tool-log-cell-sub';
			sub.textContent = formatNumber(row.group_count) + ' entries · last change ' + formatDateTime(row.group_last_changed);

			wrapper.appendChild(main);
			wrapper.appendChild(sub);

			return wrapper;
		}

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

	function renderTurn(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-cell-stack';

		if (row.is_group_row === true) {
			const main = document.createElement('div');
			main.className = 'agent-tool-log-cell-main';
			main.textContent = getText(row.group_title, 'Grouped entries');

			const sub = document.createElement('div');
			sub.className = 'agent-tool-log-cell-sub';
			sub.textContent = Array.isArray(row.group_labels) ? row.group_labels.join(' · ') : '';

			wrapper.appendChild(main);
			wrapper.appendChild(sub);

			return wrapper;
		}

		const main = document.createElement('div');
		main.className = 'agent-tool-log-cell-main';
		main.textContent = getText(row.turn_id);

		const sub = document.createElement('div');
		sub.className = 'agent-tool-log-cell-sub';
		sub.textContent = 'Call #' + getText(row.call_index, '0') + ' · ID ' + getText(row.id, '0');

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderTool(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-cell-stack';

		if (row.is_group_row === true) {
			const main = document.createElement('div');
			main.className = 'agent-tool-log-cell-main';
			main.textContent = getText(row.group_tools_preview, row.tool_name || 'Grouped tools');

			const sub = document.createElement('div');
			sub.className = 'agent-tool-log-cell-sub';
			sub.textContent = formatNumber(row.group_finished_count) + ' finished · ' + formatNumber(row.group_error_count) + ' errors';

			wrapper.appendChild(main);
			wrapper.appendChild(sub);

			return wrapper;
		}

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

		if (row.is_group_row === true) {
			const count = document.createElement('span');
			count.className = 'agent-tool-log-pill agent-tool-log-pill-strong';
			count.textContent = formatNumber(row.group_count) + ' entries';
			wrapper.appendChild(count);

			const errors = document.createElement('span');
			errors.className = 'agent-tool-log-pill ' + (Number(row.group_error_count) > 0 ? 'agent-tool-log-pill-status-error' : '');
			errors.textContent = formatNumber(row.group_error_count) + ' errors';
			wrapper.appendChild(errors);

			const finished = document.createElement('span');
			finished.className = 'agent-tool-log-pill agent-tool-log-pill-status-finished';
			finished.textContent = formatNumber(row.group_finished_count) + ' finished';
			wrapper.appendChild(finished);

			return wrapper;
		}

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

	function renderContext(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-cell-stack';

		const main = document.createElement('div');
		main.className = 'agent-tool-log-cell-main';
		main.textContent = row.is_group_row === true
			? getText(row.chatbot_key || row.config_name || row.group_title)
			: getText(row.chatbot_key);

		const sub = document.createElement('div');
		sub.className = 'agent-tool-log-cell-sub';
		sub.textContent = row.is_group_row === true
			? getText(row.group_users_preview, 'Grouped users')
			: getText(row.user_login, 'unknown_user') + ' · #' + getText(row.user_id, '0');

		wrapper.appendChild(main);
		wrapper.appendChild(sub);

		return wrapper;
	}

	function renderNodeCall(value, row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-cell-stack';

		if (row.is_group_row === true) {
			const main = document.createElement('div');
			main.className = 'agent-tool-log-cell-main';
			main.textContent = 'Group';

			const sub = document.createElement('div');
			sub.className = 'agent-tool-log-cell-sub';
			sub.textContent = 'Anchor row #' + getText(row.group_anchor_id, '0');

			wrapper.appendChild(main);
			wrapper.appendChild(sub);

			return wrapper;
		}

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
		const row = context && context.row ? context.row : null;

		if (row && row.is_group_row === true) {
			const serverContext = buildCurrentServerContext();
			const response = await postJson({
				mode: 'grouped-detail',
				search: serverContext.search,
				filters: serverContext.filters,
				group: serverContext.group,
				groupValues: row.group_values || {}
			});

			if (!response || !response.found || !response.detail) {
				throw new Error('No grouped detail data returned for ' + getText(row.group_title, row.id));
			}

			return response.detail;
		}

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
		const row = context && context.row ? context.row : null;
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-detail-status';

		if (row && row.is_group_row === true) {
			wrapper.textContent = 'Loading grouped entries for ' + getText(row.group_title, row.id) + '...';
			return wrapper;
		}

		wrapper.textContent = 'Loading detail for log entry #' + getText(getLogEntryId(context)) + '...';

		return wrapper;
	}

	function createDetailErrorPlaceholder(context, error) {
		const row = context && context.row ? context.row : null;
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-detail-status agent-tool-log-detail-status-error';

		if (row && row.is_group_row === true) {
			wrapper.textContent = 'Failed to load grouped entries for ' + getText(row.group_title, row.id) + ': ' + getText(error, 'Unknown error');
			return wrapper;
		}

		wrapper.textContent = 'Failed to load detail for log entry #' + getText(getLogEntryId(context)) + ': ' + getText(error, 'Unknown error');

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

	function createButton(label, onClick) {
		const button = document.createElement('button');

		button.type = 'button';
		button.className = 'agent-tool-log-button';
		button.textContent = label;
		button.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			onClick(button);
		});

		return button;
	}

	function renderToolLogDetail(context) {
		const payload = context && context.payload ? context.payload : null;

		if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
			return document.createTextNode(getText(payload));
		}

		if (payload.kind === 'grouped-child-table') {
			return renderGroupChildTable(context);
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

		headerActions.appendChild(createButton('Copy record', () => copySingleLogEntry(payload.record || context.row)));
		headerActions.appendChild(createButton('Fullscreen', (button) => toggleDetailFullscreen(button)));
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

	function renderGroupChildTable(context) {
		const payload = context.payload || {};
		const row = context.row || {};
		const wrapper = document.createElement('div');
		wrapper.className = 'agent-tool-log-group-detail';

		const header = document.createElement('div');
		header.className = 'agent-tool-log-group-detail-header';

		const headerText = document.createElement('div');

		const title = document.createElement('div');
		title.className = 'agent-tool-log-group-detail-title';
		title.textContent = getText(payload.headline, 'Grouped log entries');
		headerText.appendChild(title);

		const subtitle = document.createElement('div');
		subtitle.className = 'agent-tool-log-group-detail-subtitle';
		subtitle.textContent = getText(payload.summary, 'Matching log entries');
		headerText.appendChild(subtitle);

		const actions = document.createElement('div');
		actions.className = 'agent-tool-log-detail-header-actions';
		actions.appendChild(createButton('Copy group', () => copyGroupLogEntries(row)));
		actions.appendChild(createButton('Copy visible child rows', () => copyPayloadToClipboard(payload.rows || [])));

		header.appendChild(headerText);
		header.appendChild(actions);
		wrapper.appendChild(header);

		const tableScroll = document.createElement('div');
		tableScroll.className = 'agent-tool-log-child-table-scroll';

		const table = document.createElement('table');
		table.className = 'agent-tool-log-child-table';

		const thead = document.createElement('thead');
		const headRow = document.createElement('tr');

		(payload.columns || []).forEach((column) => {
			const th = document.createElement('th');
			th.textContent = column.label || column.key;
			headRow.appendChild(th);
		});

		thead.appendChild(headRow);
		table.appendChild(thead);

		const tbody = document.createElement('tbody');
		const rows = Array.isArray(payload.rows) ? payload.rows : [];

		if (rows.length === 0) {
			const emptyRow = document.createElement('tr');
			const emptyCell = document.createElement('td');
			emptyCell.colSpan = Math.max((payload.columns || []).length, 1);
			emptyCell.className = 'agent-tool-log-child-table-empty';
			emptyCell.textContent = 'No rows found for this group.';
			emptyRow.appendChild(emptyCell);
			tbody.appendChild(emptyRow);
		} else {
			rows.forEach((childRow) => {
				const tr = document.createElement('tr');

				(payload.columns || []).forEach((column) => {
					const td = document.createElement('td');
					td.textContent = getText(childRow[column.key]);
					tr.appendChild(td);
				});

				tbody.appendChild(tr);
			});
		}

		table.appendChild(tbody);
		tableScroll.appendChild(table);
		wrapper.appendChild(tableScroll);

		return wrapper;
	}

	function createFallbackClipboardRecord(row) {
		return {
			id: row && row.id ? row.id : 0,
			turn_id: getText(row && row.turn_id, ''),
			node_id: getText(row && row.node_id, ''),
			call_id: getText(row && row.call_id, ''),
			call_index: row && row.call_index !== undefined ? row.call_index : 0,
			chatbot_key: getText(row && row.chatbot_key, ''),
			config_group: getText(row && row.config_group, ''),
			config_name: getText(row && row.config_name, ''),
			user_id: row && row.user_id !== undefined ? row.user_id : 0,
			user_login: getText(row && row.user_login, ''),
			tool_name: getText(row && row.tool_name, ''),
			label: getText(row && row.label, ''),
			iteration: row && row.iteration !== undefined ? row.iteration : 0,
			status: getText(row && row.status, ''),
			error_type: getText(row && row.error_type, ''),
			error_code: getText(row && row.error_code, ''),
			error_message: getText(row && row.error_message, ''),
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

	async function loadRemoteGroupRecords(row) {
		const serverContext = buildCurrentServerContext();

		const response = await postJson({
			mode: 'group-records',
			search: serverContext.search,
			filters: serverContext.filters,
			group: serverContext.group,
			groupValues: row.group_values || {}
		});

		if (!response || !response.found || !Array.isArray(response.records)) {
			throw new Error('No grouped records returned for ' + getText(row.group_title, row.id));
		}

		return response.records;
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
		if (!row) {
			setLog('No log entry available for clipboard copy.');
			return;
		}

		if (row.is_group_row === true) {
			await copyGroupLogEntries(row);
			return;
		}

		try {
			setLog('Copying log entry #' + getText(row && row.id) + '...');
			const record = row.arguments_json !== undefined ? row : await loadRemoteRecord(row.id);
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

	async function copyGroupLogEntries(row) {
		if (!row || row.is_group_row !== true) {
			setLog('Selected row is not a group.');
			return;
		}

		try {
			setLog('Copying group "' + getText(row.group_title, row.id) + '"...');
			const records = await loadRemoteGroupRecords(row);
			await copyPayloadToClipboard({
				group: {
					title: row.group_title,
					values: row.group_values || {},
					count: row.group_count || records.length
				},
				records
			});
			setLog('Copied group "' + getText(row.group_title, row.id) + '" with ' + String(records.length) + ' log entries to clipboard.');
		} catch (error) {
			setLog('Failed to copy group: ' + getText(error && error.message, String(error)));
		}
	}

	async function copySelectedLogEntries(selectedRowIds) {
		const ids = Array.isArray(selectedRowIds)
			? selectedRowIds.filter((id) => id !== null && id !== undefined && id !== '' && String(id).indexOf('group_') !== 0)
			: [];

		if (ids.length === 0) {
			setLog('No single log entries selected. Use the row action "Copy group" for grouped rows.');
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

	function buildGroupingStatePatch({ currentState, nextGroupingState }) {
		const normalizedFields = normalizeGroupFields(nextGroupingState.fields || []);
		const currentQuery = currentState.query || {};
		let nextSortKey = currentQuery.sortKey || NORMAL_SORT_FALLBACK.key;
		let nextSortDirection = currentQuery.sortDirection || NORMAL_SORT_FALLBACK.direction;

		if (normalizedFields.length > 0) {
			const allowedGroupedSorts = new Set([
				...normalizedFields,
				...GROUP_METRIC_SORT_KEYS,
				...GROUP_NORMAL_SORT_KEYS
			]);

			if (!allowedGroupedSorts.has(nextSortKey)) {
				nextSortKey = 'created_at';
				nextSortDirection = 'desc';
			}
		} else if (GROUP_METRIC_SORT_KEYS.has(nextSortKey)) {
			nextSortKey = NORMAL_SORT_FALLBACK.key;
			nextSortDirection = NORMAL_SORT_FALLBACK.direction;
		}

		return {
			query: {
				sortKey: nextSortKey,
				sortDirection: nextSortDirection
			},
			selection: {
				selectedRowIds: []
			}
		};
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

		if (binding.input) {
			binding.input.removeEventListener('change', binding.inputChangeHandler);
			binding.input.removeEventListener('keydown', binding.inputKeyDownHandler);
			delete binding.input._agentToolLogChronoPicker;
		}

		if (binding.picker && typeof binding.picker.destroy === 'function') {
			binding.picker.destroy();
		}

		chronoPickerBindings.delete(fieldKey);
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
			binding.setValue(binding.input.value || '');
		}, 80);
	}

	function renderChronoFilterControl(api) {
		const input = document.createElement('input');
		input.type = 'text';
		input.className = 'mg-input mg-compact-filter-control cp-input-bound';
		input.placeholder = api.field.placeholder || '';
		input.value = api.value || '';
		input.name = api.field.name || api.field.key;
		input.dataset.key = api.field.key;
		input.dataset.filterKey = api.field.key;
		input.dataset.mgFocusKey = 'filter-filters-' + api.field.key;

		if (api.field.width) {
			input.style.width = String(api.field.width) + 'px';
		}

		const binding = {
			fieldKey: api.field.key,
			input,
			picker: null,
			commitTimer: null,
			repositionHandler: null,
			inputChangeHandler: null,
			inputKeyDownHandler: null,
			setValue: api.setValue
		};

		if (chronoPickerBindings.has(api.field.key)) {
			destroyChronoPickerBinding(api.field.key);
		}

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

		binding.picker = picker;
		binding.repositionHandler = () => repositionChronoPicker(binding);
		binding.inputChangeHandler = () => api.setValue(input.value || '');
		binding.inputKeyDownHandler = (event) => {
			if (event.key === 'Enter') {
				api.setValue(input.value || '');
			}
		};

		input.addEventListener('change', binding.inputChangeHandler);
		input.addEventListener('keydown', binding.inputKeyDownHandler);
		window.addEventListener('resize', binding.repositionHandler);
		document.addEventListener('scroll', binding.repositionHandler, true);

		picker.init();
		input._agentToolLogChronoPicker = picker;

		chronoPickerBindings.set(api.field.key, binding);

		return {
			element: input,
			destroy() {
				destroyChronoPickerBinding(api.field.key);
			}
		};
	}

	let grid = null;

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
				const groupFields = getGroupFields();
				const sort = resolveSortForRequest(request);

				return {
					mode: groupFields.length > 0 ? 'grouped-page' : 'page',
					page: request.page || 1,
					pageSize: request.pageSize || BATCH_SIZE,
					search: request.search || '',
					sort: [
						{
							key: sort.key,
							dir: sort.direction,
							type: sort.type
						}
					],
					filters,
					group: buildGroupPayload(groupFields)
				};
			}
		});

		grid = new ModularGrid(GRID_SELECTOR, {
			layout,
			adapter,
			dataMode: 'server',
			server: {
				searchDebounceMs: 260,
				watchStateKeys: ['query', 'filters', 'toolLogGrouping']
			},
			features: {
				paging: false
			},
			pageSize: BATCH_SIZE,
			sort: {
				key: NORMAL_SORT_FALLBACK.key,
				direction: NORMAL_SORT_FALLBACK.direction
			},
			plugins: [
				SearchPlugin,
				CompactFiltersPlugin,
				GroupingPlugin,
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
					placeholder: 'Search id, turn, user, chatbot, call id, tool, label, status, error'
				},
				compactFilters: {
					zone: 'topLine2',
					order: 10,
					stateKey: 'filters',
					visibilityStateKey: 'filterVisibility',
					showClearButton: true,
					clearLabel: 'Clear filters',
					addLabel: 'Add filter',
					addPlaceholder: 'Select optional filter',
					removeLabel: 'Remove this filter',
					fields: FILTER_FIELDS
				},
				grouping: {
					zone: 'topLine1',
					order: 20,
					stateKey: 'toolLogGrouping',
					control: 'dropdown',
					multi: true,
					fields: GROUP_FIELD_OPTIONS,
					onStateChangePatch: buildGroupingStatePatch,
					dropdown: {
						summaryLabel: 'Grouping',
						emptyLabel: 'No grouping',
						headline: 'Group log rows by',
						copy: 'Turn is usually the most useful grouping. Tool, user and chatbot are useful for debugging.',
						clearLabel: 'Clear grouping',
						preferredAlign: 'end',
						stateKey: 'toolLogGroupingDropdown',
						wrapperClassName: 'agent-tool-log-grouping-toolbar',
						detailsClassName: 'agent-tool-log-grouping-dropdown',
						summaryClassName: 'agent-tool-log-grouping-dropdown-summary',
						summaryLabelClassName: 'agent-tool-log-grouping-dropdown-label',
						summaryValueClassName: 'agent-tool-log-grouping-dropdown-value',
						menuClassName: 'agent-tool-log-grouping-dropdown-menu',
						headlineClassName: 'agent-tool-log-grouping-dropdown-headline',
						copyClassName: 'agent-tool-log-grouping-dropdown-copy',
						listClassName: 'agent-tool-log-grouping-checkbox-list',
						rowClassName: 'agent-tool-log-grouping-checkbox-row',
						badgeClassName: 'agent-tool-log-grouping-order-badge',
						actionsClassName: 'agent-tool-log-grouping-dropdown-actions',
						clearButtonClassName: 'agent-tool-log-grouping-clear-button'
					}
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
					order: 30,
					selectedLabel: 'Selected log entries',
					items: [
						{
							key: 'copy-selected-clipboard',
							label: 'Copy selected entries',
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
					order: 40,
					label: 'Reset',
					sections: ['query', 'filters', 'filterVisibility', 'columns', 'selection', 'detailView', 'toolLogGrouping']
				},
				sessionStorage: {
					key: 'agent-tool-log-grid',
					sections: ['query', 'filters', 'filterVisibility', 'columns', 'selection', 'detailView', 'toolLogGrouping']
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
					width: 230,
					headerMenu: {
						defaultSortKey: 'created_at',
						defaultSortDirection: 'desc',
						sortOptions: [
							{ key: 'created_at', label: 'Created' },
							{ key: 'finished_at', label: 'Finished' },
							{ key: 'duration_seconds', label: 'Duration' },
							{ key: 'group_last_created', label: 'Group last created' },
							{ key: 'group_last_changed', label: 'Group last changed' },
							{ key: 'group_count', label: 'Group count' }
						]
					},
					render(value, row) {
						return renderCreatedAt(value, row);
					}
				},
				{
					key: 'turn_id',
					label: 'Turn',
					width: 280,
					headerMenu: {
						defaultSortKey: 'turn_id',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'turn_id', label: 'Turn' },
							{ key: 'call_index', label: 'Call index' },
							{ key: 'id', label: 'ID' },
							{ key: 'created_at', label: 'Created' },
							{ key: 'group_count', label: 'Group count' }
						]
					},
					render(value, row) {
						return renderTurn(value, row);
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
							{ key: 'label', label: 'Label' },
							{ key: 'created_at', label: 'Created' },
							{ key: 'group_tools_preview', label: 'Group tools' }
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
							{ key: 'iteration', label: 'Iteration' },
							{ key: 'created_at', label: 'Created' },
							{ key: 'group_error_count', label: 'Group errors' },
							{ key: 'group_finished_count', label: 'Group finished' }
						]
					},
					render(value, row) {
						return renderStatus(value, row);
					}
				},
				{
					key: 'chatbot_key',
					label: 'Chatbot / User',
					width: 320,
					headerMenu: {
						defaultSortKey: 'chatbot_key',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'chatbot_key', label: 'Chatbot' },
							{ key: 'config_name', label: 'Config' },
							{ key: 'user_login', label: 'User login' },
							{ key: 'user_id', label: 'User ID' },
							{ key: 'created_at', label: 'Created' },
							{ key: 'group_users_preview', label: 'Group users' }
						]
					},
					render(value, row) {
						return renderContext(value, row);
					}
				},
				{
					key: 'node_id',
					label: 'Node / Call',
					width: 380,
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
							{ key: 'call_id', label: 'Call ID' },
							{ key: 'created_at', label: 'Created' },
							{ key: 'group_anchor_id', label: 'Group anchor' }
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
				},
				{
					key: 'config_name',
					label: 'Config name',
					width: 280,
					visible: false,
					textDisplay: {
						strategy: 'clamp',
						lines: 2,
						expandable: true
					},
					headerMenu: {
						defaultSortKey: 'config_name',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'config_name', label: 'Config name' },
							{ key: 'config_group', label: 'Config group' }
						]
					}
				},
				{
					key: 'user_login',
					label: 'User',
					width: 220,
					visible: false,
					headerMenu: {
						defaultSortKey: 'user_login',
						defaultSortDirection: 'asc',
						sortOptions: [
							{ key: 'user_login', label: 'User login' },
							{ key: 'user_id', label: 'User ID' }
						]
					}
				},
				{
					key: 'prompt_text',
					label: 'Prompt',
					width: 360,
					visible: false,
					textDisplay: {
						strategy: 'clamp',
						lines: 3,
						expandable: true
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

		grid.on('grouping:changed', ({ fields }) => {
			const normalizedFields = normalizeGroupFields(fields || []);

			setLog(
				normalizedFields.length > 0
					? 'Grouping active: ' + normalizedFields.map(getGroupFieldLabel).join(' -> ')
					: 'Grouping cleared. Back to the normal infinite table.'
			);
		});

		grid.on('detail:loaded', (event) => {
			const detailRowId = event && typeof event === 'object' ? event.rowId : null;
			const row = event && typeof event === 'object' ? event.row : null;
			const payload = event && typeof event === 'object' ? event.payload : null;

			if (row && row.is_group_row === true && payload && payload.kind === 'grouped-child-table') {
				setLog('Loaded grouped child table for ' + getText(row.group_title, detailRowId) + ' with ' + String(payload.rows ? payload.rows.length : 0) + ' child rows.');
				return;
			}

			setLog('Loaded detail for log entry #' + getText(detailRowId));
		});

		grid.on('detail:error', (event) => {
			const detailRowId = event && typeof event === 'object' ? event.rowId : null;
			const detailError = event && typeof event === 'object' ? event.error : null;

			setLog('Failed to load detail for log entry #' + getText(detailRowId) + ': ' + getText(detailError));
		});

		await grid.init();
		setLog('Initial batch loaded. Use grouping by Turn for request-level debugging.');
	})();
</script>
