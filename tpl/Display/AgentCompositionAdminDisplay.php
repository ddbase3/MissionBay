<?php
$resolve = $this->_['resolve'];
$serviceUrl = (string)($this->_['service'] ?? '');
$orchestratorOptions = is_array($this->_['orchestrator_options'] ?? null) ? $this->_['orchestrator_options'] : [];
$gridCss = (string)$resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$gridJs = (string)$resolve('plugin/ClientStack/assets/modulargrid/index.js');
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?php echo $e($gridCss); ?>" />

<style>
	.agent-composition-shell { max-width: 1750px; }
	.agent-composition-shell h1 { margin: 0 0 8px; font-size: 24px; font-weight: 600; }
	.agent-composition-shell > p { max-width: 1180px; color: #555; line-height: 1.45; }
	.agent-composition-actions { display: flex; gap: 8px; margin: 12px 0; }
	.agent-composition-button { border: 1px solid #cfcfcf; border-radius: 4px; background: #fff; min-height: 30px; padding: 4px 10px; cursor: pointer; }
	.agent-composition-grid .agent-composition-panel { display: flex; align-items: center; flex-wrap: nowrap; gap: 8px; min-width: 0; width: 100%; padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; overflow-x: auto; }
	.agent-composition-grid .agent-composition-panel > * { flex: 0 0 auto; }
	.agent-composition-grid .mg-control-group { flex-direction: row; align-items: center; gap: 6px; min-width: auto; }
	.agent-composition-grid .mg-label { white-space: nowrap; color: #666; font-size: 12px; }
	.agent-composition-grid .mg-inline-buttons { flex-wrap: nowrap; }
	.agent-composition-grid .mg-input, .agent-composition-grid .mg-select, .agent-composition-grid .mg-button { min-height: 28px; font-size: 13px; }
	.agent-composition-grid input[type="search"].mg-input { width: 300px; }
	.agent-composition-grid .mg-select { width: auto; min-width: 105px; }
	.agent-composition-main { border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; padding: 4px 0; }
	.agent-composition-grid .mg-table-scroll { height: 540px; overflow: auto; }
	.agent-composition-grid .mg-table thead th { position: sticky; top: 0; z-index: 12; background: #fff; }
	.agent-composition-grid .mg-table th, .agent-composition-grid .mg-table td { padding: 6px 8px; font-size: 13px; vertical-align: top; }
	.agent-composition-cell { display: grid; gap: 2px; min-width: 0; }
	.agent-composition-cell-main { font-weight: 600; overflow-wrap: anywhere; }
	.agent-composition-cell-sub { color: #666; font-size: 12px; overflow-wrap: anywhere; }
	.agent-composition-pills { display: flex; flex-wrap: wrap; gap: 4px; }
	.agent-composition-pill { display: inline-flex; padding: 1px 6px; border: 1px solid #d6d6d6; border-radius: 999px; background: #fafafa; font-size: 11px; white-space: nowrap; }
	.agent-composition-pill-valid, .agent-composition-pill-enabled, .agent-composition-pill-active { background: #eef7ee; border-color: #bddfbd; }
	.agent-composition-pill-warning { background: #fff8e6; border-color: #e6cf92; color: #705200; }
	.agent-composition-pill-error, .agent-composition-pill-disabled, .agent-composition-pill-missing, .agent-composition-pill-inactive { background: #fff0f0; border-color: #e4b9b9; color: #8a1f1f; }
	.agent-composition-status { margin-top: 12px; padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; font-size: 13px; }
	.agent-composition-detail { display: grid; gap: 12px; padding: 12px; background: #f7f8fa; border-top: 1px solid #e3e3e3; }
	.agent-composition-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
	.agent-composition-card { min-width: 0; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; padding: 10px; }
	.agent-composition-card h3 { margin: 0 0 8px; font-size: 14px; }
	.agent-composition-card h4 { margin: 12px 0 6px; font-size: 13px; }
	.agent-composition-kpi { font-size: 22px; font-weight: 600; line-height: 1.1; }
	.agent-composition-kpi-label { margin-top: 3px; color: #666; font-size: 12px; }
	.agent-composition-section { display: grid; gap: 8px; }
	.agent-composition-row { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 8px; padding: 4px 0; border-bottom: 1px solid #efefef; font-size: 12px; }
	.agent-composition-row:last-child { border-bottom: 0; }
	.agent-composition-row-key { color: #666; }
	.agent-composition-list { display: grid; gap: 6px; }
	.agent-composition-item { border: 1px solid #e7e7e7; border-radius: 6px; padding: 7px 8px; background: #fff; }
	.agent-composition-item-head { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; font-weight: 600; }
	.agent-composition-item-sub { margin-top: 3px; color: #666; font-size: 12px; overflow-wrap: anywhere; }
	.agent-composition-alert { padding: 8px 10px; border-radius: 6px; font-size: 12px; }
	.agent-composition-alert-warning { border: 1px solid #e6cf92; background: #fff8e6; color: #705200; }
	.agent-composition-alert-error { border: 1px solid #e4b9b9; background: #fff0f0; color: #8a1f1f; }
	.agent-composition-json { max-height: 360px; overflow: auto; white-space: pre-wrap; word-break: break-word; font-size: 11px; }
	.agent-composition-details summary { cursor: pointer; font-weight: 600; }
	@media (max-width: 1050px) {
		.agent-composition-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.agent-composition-grid .mg-table-scroll { height: 430px; }
	}
	@media (max-width: 650px) {
		.agent-composition-summary { grid-template-columns: 1fr; }
		.agent-composition-row { grid-template-columns: 1fr; }
	}
</style>

<div class="agent-composition-shell">
	<h1>Effective Agent Composition</h1>
	<p>
		Read-only inspection of the configuration that MissionBay will actually use: orchestrator profile, fixed and module-mounted stages, tool profiles, component presets, capability sources, callable tool names and memory roles. Prompt-specific tool preselection still happens during each model decision.
	</p>
	<div class="agent-composition-actions">
		<button type="button" id="agent-composition-reload" class="agent-composition-button">Reload settings</button>
	</div>
	<div id="agent-composition-grid" class="agent-composition-grid"><div class="agent-composition-panel">Loading agents...</div></div>
	<div id="agent-composition-status" class="agent-composition-status"><strong>Last action:</strong> Waiting for initialization.</div>
</div>

<script>
(function() {
	const ENDPOINT = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_JS = <?php echo json_encode($gridJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const ORCHESTRATOR_OPTIONS = <?php echo json_encode($orchestratorOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_SELECTOR = '#agent-composition-grid';
	const BATCH_SIZE = 50;
	let grid = null;

	function text(value, fallback = '-') { return value === null || value === undefined || value === '' ? fallback : String(value); }
	function element(className = '', value = '') {
		const node = document.createElement('div');
		node.className = className;
		if (value !== '') node.textContent = String(value);
		return node;
	}
	function setStatus(message) {
		const node = document.querySelector('#agent-composition-status');
		if (node) node.innerHTML = '<strong>Last action:</strong> ' + text(message, '');
	}
	function pill(value, extra = '') {
		const node = document.createElement('span');
		node.className = 'agent-composition-pill agent-composition-pill-' + String(value || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-') + (extra ? ' ' + extra : '');
		node.textContent = text(value);
		return node;
	}
	function pills(values) {
		const wrapper = element('agent-composition-pills');
		(Array.isArray(values) ? values : [values]).filter(Boolean).forEach((value) => wrapper.appendChild(pill(value)));
		if (!wrapper.children.length) wrapper.appendChild(pill('-'));
		return wrapper;
	}
	function renderAgent(value, row) {
		const wrapper = element('agent-composition-cell');
		wrapper.appendChild(element('agent-composition-cell-main', text(row.label || row.agent_id)));
		wrapper.appendChild(element('agent-composition-cell-sub', text(row.agent_id)));
		return wrapper;
	}
	function renderState(value, row) {
		const wrapper = element('agent-composition-cell');
		wrapper.appendChild(pills([row.status, row.enabled_label, row.expert_overrides ? 'expert overrides' : 'profile based']));
		wrapper.appendChild(element('agent-composition-cell-sub', text(row.status_detail)));
		return wrapper;
	}
	function renderProfiles(value, row) {
		const wrapper = element('agent-composition-cell');
		wrapper.appendChild(element('agent-composition-cell-main', text(row.orchestrator_profile)));
		wrapper.appendChild(element('agent-composition-cell-sub', row.tool_profile_count + ' tool profile(s): ' + text(row.tool_profile_text, 'none')));
		return wrapper;
	}
	function renderLlm(value, row) {
		const wrapper = element('agent-composition-cell');
		wrapper.appendChild(element('agent-composition-cell-main', text(row.llm, 'not selected')));
		return wrapper;
	}
	async function postJson(payload) {
		const response = await fetch(ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
		if (!response.ok) throw new Error('Request failed with status ' + response.status);
		return response.json();
	}
	async function importModule(url) { return import(new URL(url, document.baseURI).href); }
	async function refreshGrid() {
		if (!grid) return;
		for (const method of ['reloadData', 'reload', 'refreshData', 'refresh']) {
			try {
				if (typeof grid.execute === 'function') { const result = grid.execute(method); if (result && typeof result.then === 'function') await result; return; }
				if (typeof grid[method] === 'function') { const result = grid[method](); if (result && typeof result.then === 'function') await result; return; }
			} catch (error) {}
		}
	}
	function appendKeyValue(parent, key, value) {
		const row = element('agent-composition-row');
		row.appendChild(element('agent-composition-row-key', key));
		if (value instanceof Node) row.appendChild(value); else row.appendChild(element('', text(value)));
		parent.appendChild(row);
	}
	function buildKpi(value, label) {
		const card = element('agent-composition-card');
		card.appendChild(element('agent-composition-kpi', text(value, '0')));
		card.appendChild(element('agent-composition-kpi-label', label));
		return card;
	}
	function buildAlert(type, message) {
		return element('agent-composition-alert agent-composition-alert-' + type, message);
	}
	function buildItem(title, labels, subtitle) {
		const item = element('agent-composition-item');
		const head = element('agent-composition-item-head');
		head.appendChild(document.createTextNode(text(title)));
		(Array.isArray(labels) ? labels : []).filter(Boolean).forEach((label) => head.appendChild(pill(label)));
		item.appendChild(head);
		if (subtitle) item.appendChild(element('agent-composition-item-sub', subtitle));
		return item;
	}
	function renderDetail(context) {
		const record = context.payload || {};
		const root = element('agent-composition-detail');
		const summary = element('agent-composition-summary');
		summary.appendChild(buildKpi((record.final_stage_ids || []).length, 'effective stages'));
		summary.appendChild(buildKpi((record.tools || []).length, 'catalog tools'));
		summary.appendChild(buildKpi((record.memories || []).length, 'memory resources'));
		summary.appendChild(buildKpi(record.resource_count || 0, 'flow resources'));
		root.appendChild(summary);

		(record.errors || []).forEach((message) => root.appendChild(buildAlert('error', message)));
		(record.warnings || []).forEach((message) => root.appendChild(buildAlert('warning', message)));

		const overview = element('agent-composition-card');
		const title = document.createElement('h3'); title.textContent = 'Orchestrator and stages'; overview.prepend(title);
		appendKeyValue(overview, 'Agent', record.label + ' (' + record.agent_id + ')');
		appendKeyValue(overview, 'LLM', record.llm || 'not selected');
		appendKeyValue(overview, 'Profile', text(record.orchestrator && record.orchestrator.label) + ' [' + text(record.orchestrator && record.orchestrator.id) + ']');
		appendKeyValue(overview, 'Mode', record.orchestrator && record.orchestrator.mode);
		appendKeyValue(overview, 'Max tool loops', record.orchestrator && record.orchestrator.max_tool_loops);
		const selection = record.capability_selection || {};
		appendKeyValue(overview, 'Capability selection', [
			text(selection.enabled, true) === 'false' ? 'disabled' : text(selection.strategy, 'hybrid'),
			'max ' + text(selection.max_tools ?? selection.maxTools, '16'),
			'all threshold ' + text(selection.select_all_threshold ?? selection.selectAllThreshold, '16'),
			(selection.sticky === false ? 'not sticky' : 'sticky')
		].join(' · '));
		appendKeyValue(overview, 'Core stages', pills(record.core_stage_ids || []));
		appendKeyValue(overview, 'Module stage mounts', pills((record.module_stage_mounts || []).map((mount) => text(mount.slot, 'slot') + ': ' + text(mount.stage_id, mount.stage_name))));
		appendKeyValue(overview, 'Final stages', pills(record.final_stage_ids || []));
		root.appendChild(overview);

		const profileCard = element('agent-composition-card');
		const profileTitle = document.createElement('h3'); profileTitle.textContent = 'Tool profiles and components'; profileCard.appendChild(profileTitle);
		const profileList = element('agent-composition-list');
		(record.tool_profiles || []).forEach((profile) => profileList.appendChild(buildItem(profile.label + ' [' + profile.id + ']', [profile.status, profile.mcp_enabled ? 'MCP' : 'internal'], (profile.tools || []).length + ' preset(s): ' + text((profile.tools || []).join(', '), 'none'))));
		if (!profileList.children.length) profileList.appendChild(element('agent-composition-item-sub', 'No tool profiles selected.'));
		profileCard.appendChild(profileList);
		const componentTitle = document.createElement('h4'); componentTitle.textContent = 'Resolved component presets'; profileCard.appendChild(componentTitle);
		const componentList = element('agent-composition-list');
		(record.components || []).forEach((component) => {
			const details = [
				'type ' + text(component.type, 'unknown'),
				'sources ' + text((component.sources || []).join(', '), 'unknown'),
				'tools ' + text((component.tool_names || []).join(', '), 'none'),
				'memory wrappers ' + text((component.memory_resources || []).join(', '), 'none')
			].join(' · ');
			componentList.appendChild(buildItem(component.label + ' [' + component.preset_id + ']', component.roles || [], details));
		});
		if (!componentList.children.length) componentList.appendChild(element('agent-composition-item-sub', 'No component presets resolved.'));
		profileCard.appendChild(componentList);
		root.appendChild(profileCard);

		const toolsCard = element('agent-composition-card');
		const toolsTitle = document.createElement('h3'); toolsTitle.textContent = 'Callable capability catalog'; toolsCard.appendChild(toolsTitle);
		const toolsList = element('agent-composition-list');
		(record.tools || []).forEach((tool) => {
			const labels = [];
			if (tool.category) labels.push(tool.category);
			if (tool.mutation) labels.push('mutation');
			if (tool.requires_approval) labels.push('approval');
			if (tool.always_available) labels.push('always');
			(tool.tags || []).forEach((tag) => labels.push(tag));
			const source = text(tool.source_id || tool.source_name, 'unknown source');
			toolsList.appendChild(buildItem(tool.name, labels, source + ' · ' + text(tool.description, 'No description')));
		});
		if (!toolsList.children.length) toolsList.appendChild(element('agent-composition-item-sub', 'No callable tools resolved.'));
		toolsCard.appendChild(toolsList);
		root.appendChild(toolsCard);

		const memoryCard = element('agent-composition-card');
		const memoryTitle = document.createElement('h3'); memoryTitle.textContent = 'Memory and capability sources'; memoryCard.appendChild(memoryTitle);
		const memoryList = element('agent-composition-list');
		(record.memories || []).forEach((memory) => memoryList.appendChild(buildItem(memory.name + ' [' + memory.resource_id + ']', ['priority ' + memory.priority, memory.preset_id || 'flow'], memory.class)));
		if (!memoryList.children.length) memoryList.appendChild(element('agent-composition-item-sub', 'No memory resource attached.'));
		memoryCard.appendChild(memoryList);
		const sourceTitle = document.createElement('h4'); sourceTitle.textContent = 'Configured source allow-list'; memoryCard.appendChild(sourceTitle);
		const sources = record.capability_sources || {};
		['tools', 'providers', 'modules', 'resourceProviders', 'promptProviders'].forEach((key) => appendKeyValue(memoryCard, key, pills(sources[key] || [])));
		appendKeyValue(memoryCard, 'strict', sources.strict ? 'yes' : 'no');
		const resolved = record.discovery && record.discovery.resolved ? record.discovery.resolved : {};
		const resolvedTitle = document.createElement('h4'); resolvedTitle.textContent = 'Resolved runtime sources'; memoryCard.appendChild(resolvedTitle);
		['tools', 'providers', 'modules', 'resourceProviders', 'promptProviders'].forEach((key) => appendKeyValue(memoryCard, key, pills(resolved[key] || [])));
		root.appendChild(memoryCard);

		const raw = document.createElement('details'); raw.className = 'agent-composition-card agent-composition-details';
		const rawSummary = document.createElement('summary'); rawSummary.textContent = 'Redacted diagnostic JSON'; raw.appendChild(rawSummary);
		const pre = document.createElement('pre'); pre.className = 'agent-composition-json'; pre.textContent = record.composition_json || JSON.stringify(record, null, 2); raw.appendChild(pre);
		root.appendChild(raw);
		return root;
	}
	async function init() {
		try {
			const gridModule = await importModule(GRID_JS);
			const { AjaxAdapter, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, ModularGrid, ResetPlugin, RowDetailPlugin, SearchPlugin } = gridModule;
			if (!AjaxAdapter || !ModularGrid) throw new Error('Required ClientStack exports are missing.');
			const profileFilterOptions = [{ value: '', label: 'All orchestrators' }].concat((ORCHESTRATOR_OPTIONS || []).map((option) => ({ value: option.id, label: option.label || option.id })));
			const adapter = new AjaxAdapter({ url: ENDPOINT, method: 'POST', rowsPath: 'data', totalPath: 'total', mapRequest(request) {
				const state = grid ? grid.getState() : {};
				return { mode: 'page', page: request.page || 1, pageSize: request.pageSize || BATCH_SIZE, search: request.search || '', sort: [{ key: request.sortKey || 'agent_id', dir: request.sortDirection || 'asc' }], filters: state.filters || {} };
			} });
			grid = new ModularGrid(GRID_SELECTOR, {
				layout: { type: 'stack', children: [
					{ type: 'zone', key: 'topLine', className: 'agent-composition-panel' },
					{ type: 'zone', key: 'filterLine', className: 'agent-composition-panel' },
					{ type: 'view', key: 'main', className: 'agent-composition-main' },
					{ type: 'zone', key: 'status', className: 'agent-composition-panel' }
				] },
				adapter,
				dataMode: 'server',
				server: { searchDebounceMs: 220, watchStateKeys: ['query', 'filters'] },
				features: { paging: false },
				pageSize: BATCH_SIZE,
				sort: { key: 'agent_id', direction: 'asc' },
				plugins: [SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, ResetPlugin, RowDetailPlugin, InfiniteScrollPlugin].filter(Boolean),
				pluginOptions: {
					search: { zone: 'topLine', order: 10, label: 'Search', placeholder: 'Search agents, profiles and status' },
					filters: { zone: 'filterLine', order: 10, stateKey: 'filters', showClearButton: true, fields: [
						{ key: 'status', label: 'Status', type: 'select', options: [{ value: '', label: 'All states' }, { value: 'valid', label: 'Valid' }, { value: 'error', label: 'Invalid' }] },
						{ key: 'enabled', label: 'Agent', type: 'select', options: [{ value: '', label: 'All agents' }, { value: '1', label: 'Enabled' }, { value: '0', label: 'Disabled' }] },
						{ key: 'orchestrator_profile', label: 'Orchestrator', type: 'select', options: profileFilterOptions }
					] },
					reset: { zone: 'topLine', order: 20, label: 'Reset', sections: ['query', 'filters', 'detailView'] },
					info: { zone: 'status', order: 10, displayMode: 'loaded' },
					rowDetail: { rowIdKey: 'agent_id', clearOnDataReload: true, asyncDetail: {
						load(context) { return postJson({ mode: 'record', id: context.row.agent_id }).then((response) => { if (!response.ok) throw new Error(response.error); return response.record; }); },
						renderLoading() { return element('agent-composition-panel', 'Resolving effective composition...'); },
						renderError(context) { return buildAlert('error', text(context.error)); },
						render(context) { return renderDetail(context); }
					} },
					infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' }
				},
				columns: [
					{ key: 'agent_id', label: 'Agent', width: 300, render: renderAgent },
					{ key: 'status', label: 'Configuration state', width: 430, render: renderState },
					{ key: 'orchestrator_profile', label: 'Profiles', width: 420, render: renderProfiles },
					{ key: 'llm', label: 'LLM', width: 260, render: renderLlm }
				]
			});
			grid.init();
			document.querySelector('#agent-composition-reload').addEventListener('click', async () => {
				const response = await postJson({ mode: 'reload' });
				if (!response.ok) throw new Error(response.error || 'Reload failed.');
				await refreshGrid();
				setStatus('Settings reloaded.');
			});
			setStatus('Initialized. Expand an agent row to resolve its effective composition.');
		}
		catch (error) {
			const root = document.querySelector(GRID_SELECTOR);
			root.replaceChildren(buildAlert('error', error.message || String(error)));
			setStatus('Initialization failed.');
		}
	}
	init();
})();
</script>
