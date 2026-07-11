<?php
$resolve = $this->_['resolve'];
$serviceUrl = (string)($this->_['service'] ?? '');
$modeOptions = is_array($this->_['mode_options'] ?? null) ? $this->_['mode_options'] : [];
$gridCss = (string)$resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$gridJs = (string)$resolve('plugin/ClientStack/assets/modulargrid/index.js');
$dialogCss = (string)$resolve('plugin/ClientStack/assets/modulardialog/styles/modulardialog.css');
$dialogJs = (string)$resolve('plugin/ClientStack/assets/modulardialog/index.js');
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?php echo $e($gridCss); ?>" />
<link rel="stylesheet" href="<?php echo $e($dialogCss); ?>" />

<style>
	.orchestrator-profile-shell { max-width: 1700px; }
	.orchestrator-profile-shell h1 { margin: 0 0 8px; font-size: 24px; font-weight: 600; }
	.orchestrator-profile-shell > p { max-width: 1100px; color: #555; line-height: 1.45; }
	.orchestrator-profile-actions { display: flex; gap: 8px; margin: 12px 0; }
	.orchestrator-profile-button { border: 1px solid #cfcfcf; border-radius: 4px; background: #fff; min-height: 30px; padding: 4px 10px; cursor: pointer; }
	.orchestrator-profile-button-primary { background: #2f5d91; border-color: #2f5d91; color: #fff; }
	.orchestrator-profile-main { border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; padding: 4px 0; }
	.orchestrator-profile-panel { display: flex; gap: 8px; align-items: center; padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; }
	.orchestrator-profile-grid .mg-table-scroll { height: 540px; overflow: auto; }
	.orchestrator-profile-grid .mg-table thead th { position: sticky; top: 0; z-index: 12; background: #fff; }
	.orchestrator-profile-grid .mg-table th, .orchestrator-profile-grid .mg-table td { padding: 6px 8px; font-size: 13px; vertical-align: top; }
	.orchestrator-profile-cell { display: grid; gap: 2px; min-width: 0; }
	.orchestrator-profile-cell-main { font-weight: 600; overflow-wrap: anywhere; }
	.orchestrator-profile-cell-sub { color: #666; font-size: 12px; overflow-wrap: anywhere; }
	.orchestrator-profile-pills { display: flex; flex-wrap: wrap; gap: 4px; }
	.orchestrator-profile-pill { display: inline-flex; padding: 1px 6px; border: 1px solid #d6d6d6; border-radius: 999px; background: #fafafa; font-size: 11px; }
	.orchestrator-profile-pill-enabled, .orchestrator-profile-pill-built-in { background: #eef7ee; border-color: #bddfbd; }
	.orchestrator-profile-pill-disabled { background: #f5eeee; border-color: #e2c5c5; color: #7a3333; }
	.orchestrator-profile-status { margin-top: 12px; padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; font-size: 13px; }
	.orchestrator-profile-startup-error { border-color: #e4b9b9; background: #fff0f0; color: #8a1f1f; }
	.orchestrator-profile-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
	.orchestrator-profile-field-full { grid-column: 1 / -1; }
	.orchestrator-profile-label { display: block; margin-bottom: 5px; color: #555; font-size: 12px; font-weight: 600; }
	.orchestrator-profile-input, .orchestrator-profile-select { width: 100%; min-height: 34px; border: 1px solid #cfcfcf; border-radius: 4px; padding: 6px 8px; box-sizing: border-box; }
	textarea.orchestrator-profile-input { min-height: 80px; resize: vertical; }
	.orchestrator-profile-checks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
	.orchestrator-profile-check { display: flex; align-items: flex-start; gap: 8px; padding: 8px; border: 1px solid #e2e2e2; border-radius: 6px; }
	.orchestrator-profile-check strong { display: block; font-size: 13px; }
	.orchestrator-profile-check span { display: block; color: #666; font-size: 12px; line-height: 1.35; }
	.orchestrator-profile-core { padding: 10px; border: 1px solid #d8e1eb; border-radius: 7px; background: #f7f9fb; }
	.orchestrator-profile-core-title { font-weight: 600; margin-bottom: 6px; }
	.orchestrator-profile-pipeline { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
	.orchestrator-profile-arrow { color: #777; }
	.orchestrator-profile-hint { margin-top: 5px; color: #666; font-size: 12px; line-height: 1.35; }
	.orchestrator-profile-detail { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; padding: 12px; background: #fafafa; border-top: 1px solid #e7e7e7; }
	.orchestrator-profile-card { border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; padding: 10px; }
	.orchestrator-profile-detail-row { display: grid; grid-template-columns: 130px minmax(0, 1fr); gap: 8px; margin-bottom: 5px; font-size: 13px; }
	.orchestrator-profile-json { max-height: 320px; overflow: auto; white-space: pre-wrap; word-break: break-word; font-size: 12px; }
	.orchestrator-profile-dialog-surface { width: min(820px, 100%); max-height: min(850px, 100%); }
	.orchestrator-profile-dialog-surface .md-shell-body { display: grid; gap: 12px; }
	@media (max-width: 900px) {
		.orchestrator-profile-form, .orchestrator-profile-detail, .orchestrator-profile-checks { grid-template-columns: 1fr; }
		.orchestrator-profile-grid .mg-table-scroll { height: 420px; }
	}
</style>

<div class="orchestrator-profile-shell">
	<h1>Orchestrator Profiles</h1>
	<p>
		Configure safe orchestration modes, limits and optional stages. The core stage order is fixed by MissionBay and cannot be reordered in this UI.
	</p>
	<div class="orchestrator-profile-actions">
		<button type="button" id="orchestrator-profile-add" class="orchestrator-profile-button orchestrator-profile-button-primary">Add custom profile</button>
		<button type="button" id="orchestrator-profile-reload" class="orchestrator-profile-button">Reload</button>
	</div>
	<div id="orchestrator-profile-grid" class="orchestrator-profile-grid"><div class="orchestrator-profile-panel">Loading profiles...</div></div>
	<div id="orchestrator-profile-status" class="orchestrator-profile-status"><strong>Last action:</strong> Waiting for initialization.</div>
</div>

<template id="orchestrator-profile-editor-template">
	<div id="orchestrator-profile-editor-content">
		<form id="orchestrator-profile-form" class="orchestrator-profile-form">
			<input type="hidden" name="old_id" />
			<div>
				<label class="orchestrator-profile-label">Profile ID</label>
				<input type="text" name="id" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label">Label</label>
				<input type="text" name="label" class="orchestrator-profile-input" />
			</div>
			<div class="orchestrator-profile-field-full">
				<label class="orchestrator-profile-label">Description</label>
				<textarea name="description" class="orchestrator-profile-input"></textarea>
			</div>
			<div>
				<label class="orchestrator-profile-label">Mode</label>
				<select name="profile_mode" class="orchestrator-profile-select">
<?php foreach($modeOptions as $option): ?>
					<option value="<?php echo $e($option['id'] ?? ''); ?>"><?php echo $e($option['label'] ?? $option['id'] ?? ''); ?></option>
<?php endforeach; ?>
				</select>
				<button type="button" class="orchestrator-profile-button" data-action="apply-mode-defaults" style="margin-top:6px">Apply mode defaults</button>
			</div>
			<div>
				<label class="orchestrator-profile-label">State</label>
				<label class="orchestrator-profile-check"><input type="checkbox" name="enabled" value="1" /><span><strong>Enabled</strong><span>Agents may select this profile.</span></span></label>
			</div>
			<div>
				<label class="orchestrator-profile-label">Maximum tool loops</label>
				<input type="number" name="max_tool_loops" min="1" max="100" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label">Selection strategy</label>
				<select name="selection_strategy" class="orchestrator-profile-select"><option value="hybrid">Hybrid ranking</option><option value="all">All allowed tools</option></select>
			</div>
			<div>
				<label class="orchestrator-profile-label">Maximum tools per model call</label>
				<input type="number" name="max_tools" min="1" max="512" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label">Select-all threshold</label>
				<input type="number" name="select_all_threshold" min="0" max="512" class="orchestrator-profile-input" />
			</div>
			<div class="orchestrator-profile-field-full">
				<label class="orchestrator-profile-label">Optional stages</label>
				<div class="orchestrator-profile-checks">
					<label class="orchestrator-profile-check"><input type="checkbox" name="capability_discovery" /><span><strong>Capability discovery</strong><span>Build the allowed run-specific capability pool from configured profiles and providers.</span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="capability_selection" /><span><strong>Capability selection</strong><span>Preselect a bounded relevant tool set before each model decision.</span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="context_compaction" /><span><strong>Context compaction</strong><span>Compact large contexts before the next tool observation/model step.</span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="semantic_verification" /><span><strong>Semantic verification</strong><span>Check whether enough information exists before producing the final answer.</span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="sticky" /><span><strong>Sticky selection</strong><span>Keep recently selected or used tools stable across adjacent loops.</span></span></label>
				</div>
			</div>
			<div class="orchestrator-profile-field-full orchestrator-profile-core">
				<div class="orchestrator-profile-core-title">Effective fixed pipeline</div>
				<div class="orchestrator-profile-pipeline" data-pipeline-preview></div>
				<div class="orchestrator-profile-hint">Required stages are always active and ordered: model-decision → action-policy → tool-execution → tool-observation. Optional stages are inserted only at their canonical positions.</div>
			</div>
		</form>
	</div>
</template>

<script>
(function() {
	const ENDPOINT = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_JS = <?php echo json_encode($gridJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const DIALOG_JS = <?php echo json_encode($dialogJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_SELECTOR = '#orchestrator-profile-grid';
	const BATCH_SIZE = 50;
	const MODE_DEFAULTS = {
		simple: { max_tool_loops: 1, capability_discovery: false, capability_selection: true, context_compaction: false, semantic_verification: false, selection_strategy: 'hybrid', max_tools: 12, select_all_threshold: 12, sticky: false },
		standard: { max_tool_loops: 10, capability_discovery: true, capability_selection: true, context_compaction: true, semantic_verification: true, selection_strategy: 'hybrid', max_tools: 16, select_all_threshold: 16, sticky: true },
		governed: { max_tool_loops: 10, capability_discovery: true, capability_selection: true, context_compaction: true, semantic_verification: true, selection_strategy: 'hybrid', max_tools: 16, select_all_threshold: 16, sticky: true }
	};
	let grid = null;
	let dialog = null;
	let editorContent = null;
	let currentRecord = null;

	function text(value, fallback = '-') { return value === null || value === undefined || value === '' ? fallback : String(value); }
	function setStatus(message) {
		const node = document.querySelector('#orchestrator-profile-status');
		if (node) node.innerHTML = '<strong>Last action:</strong> ' + text(message, '');
	}
	function element(className = '', value = '') {
		const node = document.createElement('div');
		node.className = className;
		if (value !== '') node.textContent = String(value);
		return node;
	}
	function pill(value) {
		const node = document.createElement('span');
		node.className = 'orchestrator-profile-pill orchestrator-profile-pill-' + String(value || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
		node.textContent = text(value);
		return node;
	}
	function pills(values) {
		const wrapper = element('orchestrator-profile-pills');
		(Array.isArray(values) ? values : [values]).filter(Boolean).forEach((value) => wrapper.appendChild(pill(value)));
		if (!wrapper.children.length) wrapper.appendChild(pill('-'));
		return wrapper;
	}
	function renderProfile(value, row) {
		const wrapper = element('orchestrator-profile-cell');
		wrapper.appendChild(element('orchestrator-profile-cell-main', text(row.label || row.profile_id)));
		wrapper.appendChild(element('orchestrator-profile-cell-sub', text(row.profile_id)));
		return wrapper;
	}
	function renderMode(value, row) {
		const wrapper = element('orchestrator-profile-cell');
		wrapper.appendChild(pills([row.mode, row.builtin_label, row.enabled_label]));
		wrapper.appendChild(element('orchestrator-profile-cell-sub', text(row.description, 'No description')));
		return wrapper;
	}
	function renderPipeline(value, row) {
		const wrapper = element('orchestrator-profile-cell');
		wrapper.appendChild(element('orchestrator-profile-cell-main', String(row.stage_count || 0) + ' stages'));
		wrapper.appendChild(element('orchestrator-profile-cell-sub', text(row.stage_text)));
		return wrapper;
	}
	function renderSelection(value, row) {
		const wrapper = element('orchestrator-profile-cell');
		wrapper.appendChild(element('orchestrator-profile-cell-main', text(row.selection_strategy) + ', max ' + text(row.max_tools)));
		wrapper.appendChild(element('orchestrator-profile-cell-sub', 'select all ≤ ' + text(row.select_all_threshold) + '; sticky ' + (row.sticky ? 'yes' : 'no')));
		return wrapper;
	}
	async function postJson(payload) {
		const response = await fetch(ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
		if (!response.ok) throw new Error('Request failed with status ' + response.status);
		return response.json();
	}
	async function importModule(url) { return import(new URL(url, document.baseURI).href); }
	function field(form, name) { return form.elements.namedItem(name); }
	function setValue(form, name, value) { const node = field(form, name); if (node) node.value = value === null || value === undefined ? '' : String(value); }
	function setChecked(form, name, value) { const node = field(form, name); if (node) node.checked = value === true || value === 1 || value === '1'; }
	function getValue(form, name) { const node = field(form, name); return node ? String(node.value || '').trim() : ''; }
	function getChecked(form, name) { const node = field(form, name); return !!(node && node.checked); }
	function getEditorForm() { return editorContent ? editorContent.querySelector('#orchestrator-profile-form') : null; }
	function getEditorContent() {
		if (editorContent) return editorContent;
		const template = document.querySelector('#orchestrator-profile-editor-template');
		const fragment = template.content.cloneNode(true);
		editorContent = fragment.querySelector('#orchestrator-profile-editor-content');
		return editorContent;
	}
	function stageIdsFromForm(form) {
		const ids = [];
		if (getChecked(form, 'capability_discovery')) ids.push('capability-discovery');
		if (getChecked(form, 'capability_selection')) ids.push('capability-selection');
		ids.push('model-decision', 'action-policy', 'tool-execution');
		if (getChecked(form, 'context_compaction')) ids.push('context-compaction');
		ids.push('tool-observation');
		if (getChecked(form, 'semantic_verification')) ids.push('semantic-verification');
		return ids;
	}
	function updatePipelinePreview(form) {
		const preview = form.querySelector('[data-pipeline-preview]');
		if (!preview) return;
		preview.replaceChildren();
		stageIdsFromForm(form).forEach((id, index) => {
			if (index > 0) preview.appendChild(element('orchestrator-profile-arrow', '→'));
			preview.appendChild(pill(id));
		});
	}
	function applyModeDefaults(form) {
		const defaults = MODE_DEFAULTS[getValue(form, 'profile_mode')] || MODE_DEFAULTS.standard;
		Object.entries(defaults).forEach(([name, value]) => {
			if (typeof value === 'boolean') setChecked(form, name, value); else setValue(form, name, value);
		});
		updatePipelinePreview(form);
	}
	function setDialogStatus(message, type = '') { if (dialog) dialog.execute('setStatus', { message, type }); }
	function buildPayload(validate = true) {
		const form = getEditorForm();
		if (!form) throw new Error('Editor form unavailable.');
		const payload = {
			mode: 'save', old_id: getValue(form, 'old_id'), id: getValue(form, 'id'), label: getValue(form, 'label'), description: getValue(form, 'description'),
			profile_mode: getValue(form, 'profile_mode'), enabled: getChecked(form, 'enabled'), max_tool_loops: Number(getValue(form, 'max_tool_loops') || 0),
			capability_discovery: getChecked(form, 'capability_discovery'), capability_selection: getChecked(form, 'capability_selection'), context_compaction: getChecked(form, 'context_compaction'), semantic_verification: getChecked(form, 'semantic_verification'),
			selection_strategy: getValue(form, 'selection_strategy'), max_tools: Number(getValue(form, 'max_tools') || 0), select_all_threshold: Number(getValue(form, 'select_all_threshold') || 0), sticky: getChecked(form, 'sticky')
		};
		if (validate && !payload.id) throw new Error('Profile ID is required.');
		if (validate && !payload.label) throw new Error('Label is required.');
		return payload;
	}
	async function refreshGrid() {
		if (!grid) return;
		for (const method of ['reloadData', 'reload', 'refreshData', 'refresh']) {
			try {
				if (typeof grid.execute === 'function') { const result = grid.execute(method); if (result && typeof result.then === 'function') await result; return; }
				if (typeof grid[method] === 'function') { const result = grid[method](); if (result && typeof result.then === 'function') await result; return; }
			} catch (error) {}
		}
	}
	async function saveEditor() {
		try {
			const payload = buildPayload(true);
			setDialogStatus('Saving profile...');
			const response = await postJson(payload);
			if (!response.ok) throw new Error(response.error || 'Save failed.');
			dialog.close();
			await refreshGrid();
			setStatus('Saved ' + payload.id + '.');
		} catch (error) { setDialogStatus(error.message || String(error), 'error'); }
	}
	async function deleteRecord(id) {
		if (!id || !window.confirm('Delete orchestrator profile "' + id + '"?')) return;
		const response = await postJson({ mode: 'delete', id });
		if (!response.ok) throw new Error(response.error || 'Delete failed.');
		await refreshGrid();
		setStatus('Deleted ' + id + '.');
	}
	function dialogButtons(record) {
		const buttons = [];
		if (record && record.builtin) {
			buttons.push({ key: 'duplicate', label: 'Duplicate as custom profile', primary: true, action() { openEditor(duplicateRecord(record)); } });
			return buttons;
		}
		if (record && record.profile_id) buttons.push({ key: 'delete', label: 'Delete', danger: true, async action() { await deleteRecord(record.profile_id); dialog.close(); } });
		buttons.push({ key: 'save', label: 'Save', primary: true, busyLabel: 'Saving...', async action() { await saveEditor(); } });
		return buttons;
	}
	function duplicateRecord(record) {
		const copy = Object.assign({}, record);
		copy.old_id = '';
		copy.builtin = false;
		copy.profile_id = (record.profile_id || record.id || '') + '-custom';
		copy.id = copy.profile_id;
		copy.label = 'Copy of ' + text(record.label || record.profile_id, 'profile');
		return copy;
	}
	function openEditor(record = {}) {
		const form = getEditorForm();
		form.reset();
		currentRecord = record;
		setValue(form, 'old_id', record.old_id ?? record.profile_id ?? record.id ?? '');
		setValue(form, 'id', record.profile_id ?? record.id ?? '');
		setValue(form, 'label', record.label ?? '');
		setValue(form, 'description', record.description ?? '');
		setValue(form, 'profile_mode', record.mode ?? 'standard');
		setValue(form, 'max_tool_loops', record.max_tool_loops ?? 10);
		setValue(form, 'selection_strategy', record.selection_strategy ?? 'hybrid');
		setValue(form, 'max_tools', record.max_tools ?? 16);
		setValue(form, 'select_all_threshold', record.select_all_threshold ?? 16);
		setChecked(form, 'enabled', record.enabled !== false);
		setChecked(form, 'capability_discovery', !!record.capability_discovery);
		setChecked(form, 'capability_selection', record.capability_selection !== false);
		setChecked(form, 'context_compaction', !!record.context_compaction);
		setChecked(form, 'semantic_verification', !!record.semantic_verification);
		setChecked(form, 'sticky', record.sticky !== false);
		const readonly = !!record.builtin;
		form.querySelectorAll('input, select, textarea, button[data-action="apply-mode-defaults"]').forEach((node) => node.disabled = readonly);
		updatePipelinePreview(form);
		dialog.execute('setTitle', readonly ? 'Built-in orchestrator profile' : (record.profile_id ? 'Edit orchestrator profile' : 'Add orchestrator profile'));
		dialog.execute('setButtons', dialogButtons(record));
		setDialogStatus(readonly ? 'Built-in profiles are read-only. Duplicate to customize.' : 'Core stage order is fixed and validated.', readonly ? '' : 'ok');
		dialog.open({ source: 'orchestratorProfileEditor', record });
	}
	async function loadRowRecord(row) {
		const id = String(row && (row.profile_id || row.id) || '');
		const response = await postJson({ mode: 'record', id });
		if (!response.ok) throw new Error(response.error || 'Profile could not be loaded.');
		return response.record;
	}
	async function openRow(row) {
		openEditor(await loadRowRecord(row));
	}
	function renderDetail(context) {
		const record = context.payload || {};
		const wrapper = element('orchestrator-profile-detail');
		const left = element('orchestrator-profile-card');
		const right = element('orchestrator-profile-card');
		[['ID', record.profile_id], ['Mode', record.mode], ['Kind', record.builtin_label], ['Enabled', record.enabled ? 'yes' : 'no'], ['Max loops', record.max_tool_loops], ['Pipeline', record.stage_text]].forEach(([key, value]) => {
			const row = element('orchestrator-profile-detail-row'); row.appendChild(element('', key)); row.appendChild(element('', text(value))); left.appendChild(row);
		});
		const pre = document.createElement('pre'); pre.className = 'orchestrator-profile-json'; pre.textContent = record.profile_json || JSON.stringify(record, null, 2); right.appendChild(pre);
		wrapper.append(left, right); return wrapper;
	}
	async function init() {
		try {
			const [gridModule, dialogModule] = await Promise.all([importModule(GRID_JS), importModule(DIALOG_JS)]);
			const { AjaxAdapter, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, ModularGrid, ResetPlugin, RowActionsPlugin, RowDetailPlugin, SearchPlugin } = gridModule;
			if (!AjaxAdapter || !ModularGrid || !dialogModule.createStandardDialog) throw new Error('Required ClientStack exports are missing.');
			dialog = dialogModule.createStandardDialog({ id: 'orchestrator-profile-dialog', className: 'orchestrator-profile-dialog', surfaceClassName: 'orchestrator-profile-dialog-surface', size: 'large', title: 'Orchestrator profile', content: getEditorContent(), status: '', closeButtonPlugin: { label: 'Close' }, statusPlugin: { renderEmpty: false }, buttons: [] });
			dialog.init();
			const form = getEditorForm();
			form.addEventListener('change', () => updatePipelinePreview(form));
			form.addEventListener('click', (event) => { const button = event.target.closest('[data-action="apply-mode-defaults"]'); if (button) applyModeDefaults(form); });
			const adapter = new AjaxAdapter({ url: ENDPOINT, method: 'POST', rowsPath: 'data', totalPath: 'total', mapRequest(request) {
				const state = grid ? grid.getState() : {};
				return { mode: 'page', page: request.page || 1, pageSize: request.pageSize || BATCH_SIZE, search: request.search || '', sort: [{ key: request.sortKey || 'profile_id', dir: request.sortDirection || 'asc' }], filters: state.filters || {} };
			} });
			grid = new ModularGrid(GRID_SELECTOR, {
				layout: { type: 'stack', children: [{ type: 'zone', key: 'topLine', className: 'orchestrator-profile-panel' }, { type: 'zone', key: 'filterLine', className: 'orchestrator-profile-panel' }, { type: 'view', key: 'main', className: 'orchestrator-profile-main' }, { type: 'zone', key: 'status', className: 'orchestrator-profile-panel' }] },
				adapter, dataMode: 'server', server: { searchDebounceMs: 220, watchStateKeys: ['query', 'filters'] }, features: { paging: false }, pageSize: BATCH_SIZE, sort: { key: 'profile_id', direction: 'asc' },
				plugins: [SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, ResetPlugin, RowActionsPlugin, RowDetailPlugin, InfiniteScrollPlugin].filter(Boolean),
				pluginOptions: {
					search: { zone: 'topLine', order: 10, label: 'Search', placeholder: 'Search profiles and stages' },
					filters: { zone: 'filterLine', order: 10, stateKey: 'filters', showClearButton: true, fields: [
						{ key: 'mode', label: 'Mode', type: 'select', options: [{ value: '', label: 'All modes' }, { value: 'simple', label: 'Simple' }, { value: 'standard', label: 'Standard' }, { value: 'governed', label: 'Governed' }] },
						{ key: 'enabled', label: 'State', type: 'select', options: [{ value: '', label: 'All states' }, { value: '1', label: 'Enabled' }, { value: '0', label: 'Disabled' }] }
					] },
					reset: { zone: 'topLine', order: 20, label: 'Reset', sections: ['query', 'filters', 'detailView'] }, info: { zone: 'status', order: 10, displayMode: 'loaded' },
					rowActions: { items: [
						{ key: 'edit', label: 'Open profile', onClick(context) { openRow(context.row).catch((error) => setStatus(error.message)); } },
						{ key: 'duplicate', label: 'Duplicate profile', onClick(context) { loadRowRecord(context.row).then((record) => openEditor(duplicateRecord(record))).catch((error) => setStatus(error.message)); } },
						{ key: 'delete', label: 'Delete custom profile', onClick(context) { deleteRecord(context.row.profile_id).catch((error) => setStatus(error.message)); } }
					] },
					rowDetail: { rowIdKey: 'profile_id', clearOnDataReload: true, asyncDetail: { load(context) { return postJson({ mode: 'record', id: context.row.profile_id }).then((response) => { if (!response.ok) throw new Error(response.error); return response.record; }); }, renderLoading() { return element('orchestrator-profile-panel', 'Loading profile...'); }, renderError(context) { return element('orchestrator-profile-panel orchestrator-profile-startup-error', text(context.error)); }, render(context) { return renderDetail(context); } } },
					infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' }
				},
				columns: [
					{ key: 'profile_id', label: 'Profile', width: 290, render: renderProfile },
					{ key: 'mode', label: 'Mode / state', width: 320, render: renderMode },
					{ key: 'stage_text', label: 'Fixed pipeline', width: 610, render: renderPipeline },
					{ key: 'max_tool_loops', label: 'Loops', width: 90 },
					{ key: 'selection_strategy', label: 'Tool selection', width: 230, render: renderSelection }
				]
			});
			grid.init();
			document.querySelector('#orchestrator-profile-add').addEventListener('click', () => { const record = { mode: 'standard', enabled: true }; openEditor(record); applyModeDefaults(getEditorForm()); });
			document.querySelector('#orchestrator-profile-reload').addEventListener('click', async () => { const response = await postJson({ mode: 'reload' }); if (!response.ok) throw new Error(response.error); await refreshGrid(); setStatus('Profile store reloaded.'); });
			setStatus('Initialized.');
		} catch (error) {
			const root = document.querySelector(GRID_SELECTOR);
			root.replaceChildren(element('orchestrator-profile-panel orchestrator-profile-startup-error', error.message || String(error)));
			setStatus('Initialization failed.');
		}
	}
	init();
})();
</script>
