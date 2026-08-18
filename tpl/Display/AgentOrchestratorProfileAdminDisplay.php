<?php
$this->loadBricks('Administration');
$mbUiText = is_array($this->_['bricks']['missionbay_admin'] ?? null) ? $this->_['bricks']['missionbay_admin'] : [];
$mbText = static fn(string $key, string $fallback): string => trim((string)($mbUiText[$key] ?? '')) !== '' ? (string)$mbUiText[$key] : $fallback;
$mbTextEsc = static fn(string $key, string $fallback): string => htmlspecialchars($mbText($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php
$resolve = $this->_['resolve'];
$serviceUrl = (string)($this->_['service'] ?? '');
$modeOptions = is_array($this->_['mode_options'] ?? null) ? $this->_['mode_options'] : [];
$modelDecisionStrategyOptions = is_array($this->_['model_decision_strategy_options'] ?? null) ? $this->_['model_decision_strategy_options'] : [];
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
	.orchestrator-profile-grid .orchestrator-profile-panel { display: flex; gap: 8px; align-items: center; flex-wrap: nowrap; min-width: 0; width: 100%; padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; overflow-x: auto; }
	.orchestrator-profile-grid .orchestrator-profile-panel > * { flex: 0 0 auto; }
	.orchestrator-profile-grid .mg-control-group { flex-direction: row; align-items: center; gap: 6px; min-width: auto; }
	.orchestrator-profile-grid .mg-label { white-space: nowrap; color: #666; font-size: 12px; }
	.orchestrator-profile-grid .mg-inline-buttons { flex-wrap: nowrap; }
	.orchestrator-profile-grid .mg-input, .orchestrator-profile-grid .mg-select, .orchestrator-profile-grid .mg-button { min-height: 28px; font-size: 13px; }
	.orchestrator-profile-grid input[type="search"].mg-input { width: 300px; }
	.orchestrator-profile-grid .mg-select { width: auto; min-width: 105px; }
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
	<h1><?php echo $mbTextEsc('orchestrator_profiles', 'Orchestrator Profiles'); ?></h1>
	<p>
		Configure safe orchestration modes, limits and optional stages. The core stage order is fixed by MissionBay and cannot be reordered in this UI.
	</p>
	<div class="orchestrator-profile-actions">
		<button type="button" id="orchestrator-profile-add" class="orchestrator-profile-button orchestrator-profile-button-primary"><?php echo $mbTextEsc('add_custom_profile', 'Add custom profile'); ?></button>
		<button type="button" id="orchestrator-profile-reload" class="orchestrator-profile-button"><?php echo $mbTextEsc('reload', 'Reload'); ?></button>
	</div>
	<div id="orchestrator-profile-grid" class="orchestrator-profile-grid"><div class="orchestrator-profile-panel"><?php echo $mbTextEsc('loading_profiles', 'Loading profiles...'); ?></div></div>
	<div id="orchestrator-profile-status" class="orchestrator-profile-status"><strong><?php echo $mbTextEsc('last_action', 'Last action:'); ?></strong> <?php echo $mbTextEsc('waiting_for_initialization', 'Waiting for initialization.'); ?></div>
</div>

<template id="orchestrator-profile-editor-template">
	<div id="orchestrator-profile-editor-content">
		<form id="orchestrator-profile-form" class="orchestrator-profile-form">
			<input type="hidden" name="old_id" />
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('profile_id', 'Profile ID'); ?></label>
				<input type="text" name="id" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('label', 'Label'); ?></label>
				<input type="text" name="label" class="orchestrator-profile-input" />
			</div>
			<div class="orchestrator-profile-field-full">
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('description', 'Description'); ?></label>
				<textarea name="description" class="orchestrator-profile-input"></textarea>
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('mode', 'Mode'); ?></label>
				<select name="profile_mode" class="orchestrator-profile-select">
<?php foreach($modeOptions as $option): ?>
					<option value="<?php echo $e($option['id'] ?? ''); ?>"><?php echo $e($option['label'] ?? $option['id'] ?? ''); ?></option>
<?php endforeach; ?>
				</select>
				<button type="button" class="orchestrator-profile-button" data-action="apply-mode-defaults" style="margin-top:6px"><?php echo $mbTextEsc('apply_mode_defaults', 'Apply mode defaults'); ?></button>
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('state', 'State'); ?></label>
				<label class="orchestrator-profile-check"><input type="checkbox" name="enabled" value="1" /><span><strong><?php echo $mbTextEsc('enabled', 'Enabled'); ?></strong><span><?php echo $mbTextEsc('agents_may_select_this_profile', 'Agents may select this profile.'); ?></span></span></label>
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('maximum_tool_loops', 'Maximum tool loops'); ?></label>
				<input type="number" name="max_tool_loops" min="1" max="100" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('model_decision_strategy', 'Model decision strategy'); ?></label>
				<select name="model_decision_strategy" class="orchestrator-profile-select">
<?php foreach($modelDecisionStrategyOptions as $option): ?>
					<option value="<?php echo $e($option['id'] ?? ''); ?>"><?php echo $e($option['label'] ?? $option['id'] ?? ''); ?></option>
<?php endforeach; ?>
				</select>
				<div class="orchestrator-profile-hint" data-model-decision-hint></div>
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('decision_confidence_threshold', 'Decision confidence threshold'); ?></label>
				<input type="number" name="model_decision_confidence_threshold" min="0" max="1" step="0.05" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('deterministic_candidate_strategy', 'Deterministic candidate strategy'); ?></label>
				<select name="selection_strategy" class="orchestrator-profile-select"><option value="hybrid"><?php echo $mbTextEsc('hybrid_ranking', 'Hybrid ranking'); ?></option><option value="all"><?php echo $mbTextEsc('all_allowed_tools', 'All allowed tools'); ?></option></select>
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('ai_selection_unit', 'AI selection unit'); ?></label>
				<select name="selection_unit" class="orchestrator-profile-select"><option value="function"><?php echo $mbTextEsc('individual_functions', 'Individual functions'); ?></option><option value="source"><?php echo $mbTextEsc('complete_tool_sources', 'Complete tool sources'); ?></option></select>
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('maximum_tools_per_model_call', 'Maximum tools per model call'); ?></label>
				<input type="number" name="max_tools" min="1" max="512" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('maximum_selected_sources', 'Maximum selected sources'); ?></label>
				<input type="number" name="max_sources" min="1" max="128" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('select_all_threshold', 'Select-all threshold'); ?></label>
				<input type="number" name="select_all_threshold" min="0" max="512" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('semantic_candidate_tools', 'Semantic candidate tools'); ?></label>
				<input type="number" name="semantic_candidate_tools" min="1" max="512" class="orchestrator-profile-input" />
			</div>
			<div>
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('semantic_prompt_character_limit', 'Semantic prompt character limit'); ?></label>
				<input type="number" name="semantic_max_prompt_characters" min="8000" max="200000" class="orchestrator-profile-input" />
			</div>
			<div class="orchestrator-profile-field-full">
				<label class="orchestrator-profile-label"><?php echo $mbTextEsc('optional_behavior_and_stages', 'Optional behavior and stages'); ?></label>
				<div class="orchestrator-profile-checks">
					<label class="orchestrator-profile-check"><input type="checkbox" name="capability_discovery" /><span><strong><?php echo $mbTextEsc('capability_discovery', 'Capability discovery'); ?></strong><span><?php echo $mbTextEsc('build_the_allowed_run_specific_capability_pool_from_configured_profiles_and_providers', 'Build the allowed run-specific capability pool from configured profiles and providers.'); ?></span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="capability_selection" /><span><strong><?php echo $mbTextEsc('capability_selection', 'Capability selection'); ?></strong><span><?php echo $mbTextEsc('preselect_tools_through_deterministic_filters_and_ranking_without_an_additional_ai_call', 'Preselect tools through deterministic filters and ranking without an additional AI call.'); ?></span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="ai_capability_selection" /><span><strong><?php echo $mbTextEsc('ai_capability_selection', 'AI capability selection'); ?></strong><span><?php echo $mbTextEsc('use_the_active_chat_model_to_rerank_a_bounded_deterministic_candidate_pool_mutually_exclusive_with_capability_', 'Use the active chat model to rerank a bounded deterministic candidate pool. Mutually exclusive with Capability selection.'); ?></span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="context_compaction" /><span><strong><?php echo $mbTextEsc('context_compaction', 'Context compaction'); ?></strong><span><?php echo $mbTextEsc('compact_large_contexts_before_the_next_tool_observation_model_step', 'Compact large contexts before the next tool observation/model step.'); ?></span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="semantic_verification" /><span><strong><?php echo $mbTextEsc('semantic_verification', 'Semantic verification'); ?></strong><span><?php echo $mbTextEsc('check_whether_enough_information_exists_before_producing_the_final_answer', 'Check whether enough information exists before producing the final answer.'); ?></span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="deliberate_planning" /><span><strong><?php echo $mbTextEsc('deliberate_planning', 'Deliberate planning'); ?></strong><span><?php echo $mbTextEsc('build_a_concise_typed_execution_plan_from_the_normalized_task_without_an_extra_model_call_or_a_separate_planni', 'Build a concise typed execution plan from the normalized task without an extra model call or a separate planning stage.'); ?></span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="model_decision_repair_enabled" /><span><strong><?php echo $mbTextEsc('decision_repair', 'Decision repair'); ?></strong><span><?php echo $mbTextEsc('allow_one_guarded_ai_retry_when_the_first_model_decision_emits_neither_a_real_tool_call_nor_a_reliable_termina', 'Allow one guarded AI retry when the first model decision emits neither a real tool call nor a reliable terminal decision.'); ?></span></span></label>
					<label class="orchestrator-profile-check"><input type="checkbox" name="sticky" /><span><strong><?php echo $mbTextEsc('sticky_selection', 'Sticky selection'); ?></strong><span><?php echo $mbTextEsc('keep_recently_selected_or_used_tools_stable_across_adjacent_loops', 'Keep recently selected or used tools stable across adjacent loops.'); ?></span></span></label>
				</div>
			</div>
			<div class="orchestrator-profile-field-full orchestrator-profile-core">
				<div class="orchestrator-profile-core-title"><?php echo $mbTextEsc('effective_fixed_pipeline', 'Effective fixed pipeline'); ?></div>
				<div class="orchestrator-profile-pipeline" data-pipeline-preview></div>
				<div class="orchestrator-profile-hint"><?php echo $mbTextEsc('required_stages_are_always_active_and_ordered_model_decision_action_policy_tool_execution_tool_observation_opt', 'Required stages are always active and ordered: model-decision → action-policy → tool-execution → tool-observation. Optional stages are inserted only at their canonical positions. Capability selection and AI capability selection are alternatives. Deliberate planning is a profile behavior inside the existing model-decision flow, not another model call or stage.'); ?></div>
			</div>
		</form>
	</div>
</template>

<script>
(function() {
	const MB_UI_TEXT = <?php echo json_encode($mbUiText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const mbText = (key, fallback, replacements = {}) => {
		let value = String(MB_UI_TEXT[key] || fallback || '');
		Object.entries(replacements).forEach(([name, replacement]) => {
			value = value.split('{' + name + '}').join(String(replacement));
		});
		return value;
	};
	const mbStringSet = (prefix) => Object.fromEntries(
		Object.entries(MB_UI_TEXT)
			.filter(([key, value]) => key.startsWith(prefix) && String(value || '').trim() !== '')
			.map(([key, value]) => [key.slice(prefix.length), value])
	);

	const ENDPOINT = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_JS = <?php echo json_encode($gridJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const DIALOG_JS = <?php echo json_encode($dialogJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_SELECTOR = '#orchestrator-profile-grid';
	const BATCH_SIZE = 50;
	const MODE_DEFAULTS = {
		simple: { max_tool_loops: 1, deliberate_planning: false, capability_discovery: false, capability_selection: true, ai_capability_selection: false, context_compaction: false, semantic_verification: false, selection_strategy: 'hybrid', selection_unit: 'function', max_tools: 12, max_sources: 8, select_all_threshold: 12, semantic_candidate_tools: 48, semantic_max_prompt_characters: 48000, sticky: false, model_decision_strategy: 'ai-guarded-model-decision', model_decision_repair_enabled: true, model_decision_confidence_threshold: 0.7 },
		standard: { max_tool_loops: 10, deliberate_planning: false, capability_discovery: true, capability_selection: true, ai_capability_selection: false, context_compaction: true, semantic_verification: true, selection_strategy: 'hybrid', selection_unit: 'function', max_tools: 16, max_sources: 8, select_all_threshold: 16, semantic_candidate_tools: 48, semantic_max_prompt_characters: 48000, sticky: true, model_decision_strategy: 'ai-guarded-model-decision', model_decision_repair_enabled: true, model_decision_confidence_threshold: 0.7 },
		deliberate: { max_tool_loops: 4, deliberate_planning: true, capability_discovery: true, capability_selection: true, ai_capability_selection: false, context_compaction: true, semantic_verification: true, selection_strategy: 'hybrid', selection_unit: 'function', max_tools: 12, max_sources: 8, select_all_threshold: 12, semantic_candidate_tools: 48, semantic_max_prompt_characters: 48000, sticky: true, model_decision_strategy: 'ai-guarded-model-decision', model_decision_repair_enabled: true, model_decision_confidence_threshold: 0.7 },
		governed: { max_tool_loops: 10, deliberate_planning: false, capability_discovery: true, capability_selection: true, ai_capability_selection: false, context_compaction: true, semantic_verification: true, selection_strategy: 'hybrid', selection_unit: 'function', max_tools: 16, max_sources: 8, select_all_threshold: 16, semantic_candidate_tools: 48, semantic_max_prompt_characters: 48000, sticky: true, model_decision_strategy: 'ai-guarded-model-decision', model_decision_repair_enabled: true, model_decision_confidence_threshold: 0.7 },
	};
	let grid = null;
	let dialog = null;
	let editorContent = null;
	let currentRecord = null;

	function text(value, fallback = '-') { return value === null || value === undefined || value === '' ? fallback : String(value); }
	function setStatus(message) {
		const node = document.querySelector('#orchestrator-profile-status');
		if (!node) return;
		node.innerHTML = '';
		const strong = document.createElement('strong');
		strong.textContent = mbText('last_action', 'Last action:');
		node.appendChild(strong);
		node.appendChild(document.createTextNode(' ' + text(message, '')));
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
	function renderModelDecision(value, row) {
		const wrapper = element('orchestrator-profile-cell');
		wrapper.appendChild(element('orchestrator-profile-cell-main', text(row.model_decision_strategy)));
		wrapper.appendChild(element('orchestrator-profile-cell-sub', 'repair ' + (row.model_decision_repair_enabled ? 'enabled' : 'disabled') + '; threshold ' + text(row.model_decision_confidence_threshold)));
		return wrapper;
	}
	function renderSelection(value, row) {
		const wrapper = element('orchestrator-profile-cell');
		const stage = text(row.selection_stage, 'none');
		wrapper.appendChild(element('orchestrator-profile-cell-main', stage + ', max ' + text(row.max_tools)));
		const detail = stage === 'ai-capability-selection'
			? 'AI reranking; candidates ' + text(row.semantic_candidate_tools) + '; select all ≤ ' + text(row.select_all_threshold)
			: text(row.selection_strategy) + '; select all ≤ ' + text(row.select_all_threshold) + '; sticky ' + (row.sticky ? 'yes' : 'no');
		wrapper.appendChild(element('orchestrator-profile-cell-sub', detail));
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
		if (getChecked(form, 'ai_capability_selection')) ids.push('ai-capability-selection');
		ids.push('model-decision', 'action-policy', 'tool-execution');
		if (getChecked(form, 'context_compaction')) ids.push('context-compaction');
		ids.push('tool-observation');
		if (getChecked(form, 'semantic_verification')) ids.push('semantic-verification');
		return ids;
	}
	function enforceCapabilitySelectionStageChoice(form, changedName = '') {
		if (changedName === 'capability_selection' && getChecked(form, 'capability_selection')) {
			setChecked(form, 'ai_capability_selection', false);
			setValue(form, 'selection_unit', 'function');
		}
		if (changedName === 'ai_capability_selection' && getChecked(form, 'ai_capability_selection')) {
			setChecked(form, 'capability_selection', false);
			setValue(form, 'selection_strategy', 'hybrid');
		}
	}
	function updateCapabilitySelectionControls(form, readonly = false) {
		const aiEnabled = getChecked(form, 'ai_capability_selection');
		const strategy = field(form, 'selection_strategy');
		const selectionUnit = field(form, 'selection_unit');
		const maxSources = field(form, 'max_sources');
		const semanticCandidates = field(form, 'semantic_candidate_tools');
		const semanticPromptLimit = field(form, 'semantic_max_prompt_characters');
		if (aiEnabled) setValue(form, 'selection_strategy', 'hybrid');
		if (strategy) strategy.disabled = readonly || aiEnabled;
		if (selectionUnit) selectionUnit.disabled = readonly || !aiEnabled;
		if (maxSources) maxSources.disabled = readonly || !aiEnabled || getValue(form, 'selection_unit') !== 'source';
		if (semanticCandidates) semanticCandidates.disabled = readonly || !aiEnabled;
		if (semanticPromptLimit) semanticPromptLimit.disabled = readonly || !aiEnabled;
	}
	function updateModelDecisionControls(form, readonly = false) {
		const strategy = getValue(form, 'model_decision_strategy');
		const native = strategy === 'native-model-decision';
		const legacy = strategy === 'simple-model-decision';
		const repair = field(form, 'model_decision_repair_enabled');
		const threshold = field(form, 'model_decision_confidence_threshold');
		const verification = field(form, 'semantic_verification');
		const hint = form.querySelector('[data-model-decision-hint]');
		if (native) {
			setChecked(form, 'model_decision_repair_enabled', false);
			setChecked(form, 'semantic_verification', false);
		}
		if (repair) repair.disabled = readonly || native;
		if (threshold) threshold.disabled = readonly || native;
		if (verification) verification.disabled = readonly || native;
		if (hint) {
			hint.textContent = native
				? 'Native mode streams a normal terminal assistant response directly, skips the separate final-response model call, and requires semantic verification to be disabled.'
				: legacy
					? 'Compatibility only. This strategy uses the textual TOOL_PHASE_COMPLETE sentinel and a separate final-response call. Existing saved profiles remain supported; use AI-guarded or native model decision for new profiles.'
					: 'Controlled strategies terminate the tool phase explicitly and generate the visible response afterwards.';
		}
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
		updateCapabilitySelectionControls(form);
		updateModelDecisionControls(form);
		updatePipelinePreview(form);
	}
	function setDialogStatus(message, type = '') { if (dialog) dialog.execute('setStatus', { message, type }); }
	function buildPayload(validate = true) {
		const form = getEditorForm();
		if (!form) throw new Error('Editor form unavailable.');
		const payload = {
			mode: 'save', old_id: getValue(form, 'old_id'), id: getValue(form, 'id'), label: getValue(form, 'label'), description: getValue(form, 'description'),
			profile_mode: getValue(form, 'profile_mode'), enabled: getChecked(form, 'enabled'), max_tool_loops: Number(getValue(form, 'max_tool_loops') || 0),
			model_decision_strategy: getValue(form, 'model_decision_strategy'), model_decision_repair_enabled: getChecked(form, 'model_decision_repair_enabled'), model_decision_confidence_threshold: Number(getValue(form, 'model_decision_confidence_threshold') || 0),
			deliberate_planning: getChecked(form, 'deliberate_planning'), capability_discovery: getChecked(form, 'capability_discovery'), capability_selection: getChecked(form, 'capability_selection'), ai_capability_selection: getChecked(form, 'ai_capability_selection'), context_compaction: getChecked(form, 'context_compaction'), semantic_verification: getChecked(form, 'semantic_verification'),
			selection_strategy: getValue(form, 'selection_strategy'), selection_unit: getValue(form, 'selection_unit'), max_tools: Number(getValue(form, 'max_tools') || 0), max_sources: Number(getValue(form, 'max_sources') || 0), select_all_threshold: Number(getValue(form, 'select_all_threshold') || 0), semantic_candidate_tools: Number(getValue(form, 'semantic_candidate_tools') || 0), semantic_max_prompt_characters: Number(getValue(form, 'semantic_max_prompt_characters') || 0), sticky: getChecked(form, 'sticky')
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
			setStatus(mbText('saved_prefix', 'Saved ') + payload.id + '.');
		} catch (error) { setDialogStatus(error.message || String(error), 'error'); }
	}
	async function deleteRecord(id) {
		if (!id || !window.confirm(mbText('delete_orchestrator_profile_confirm', 'Delete orchestrator profile \"{id}\"?', {id}))) return;
		const response = await postJson({ mode: 'delete', id });
		if (!response.ok) throw new Error(response.error || 'Delete failed.');
		await refreshGrid();
		setStatus(mbText('deleted_prefix', 'Deleted ') + id + '.');
	}
	function dialogButtons(record) {
		const buttons = [];
		if (record && record.builtin) {
			buttons.push({ key: 'duplicate', label: mbText('duplicate_as_custom_profile', 'Duplicate as custom profile'), primary: true, action() { openEditor(duplicateRecord(record)); } });
			return buttons;
		}
		if (record && record.profile_id) buttons.push({ key: 'delete', label: mbText('delete', 'Delete'), danger: true, async action() { await deleteRecord(record.profile_id); dialog.close(); } });
		buttons.push({ key: 'save', label: mbText('save', 'Save'), primary: true, busyLabel: 'Saving...', async action() { await saveEditor(); } });
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
		setValue(form, 'model_decision_strategy', record.model_decision_strategy ?? 'ai-guarded-model-decision');
		setValue(form, 'model_decision_confidence_threshold', record.model_decision_confidence_threshold ?? 0.7);
		setValue(form, 'selection_strategy', record.selection_strategy ?? 'hybrid');
		setValue(form, 'selection_unit', record.selection_unit ?? 'function');
		setValue(form, 'max_tools', record.max_tools ?? 16);
		setValue(form, 'max_sources', record.max_sources ?? 8);
		setValue(form, 'select_all_threshold', record.select_all_threshold ?? 16);
		setValue(form, 'semantic_candidate_tools', record.semantic_candidate_tools ?? 48);
		setValue(form, 'semantic_max_prompt_characters', record.semantic_max_prompt_characters ?? 48000);
		setChecked(form, 'enabled', record.enabled !== false);
		setChecked(form, 'deliberate_planning', !!record.deliberate_planning);
		setChecked(form, 'capability_discovery', !!record.capability_discovery);
		setChecked(form, 'capability_selection', !!record.capability_selection);
		setChecked(form, 'ai_capability_selection', !!record.ai_capability_selection);
		setChecked(form, 'context_compaction', !!record.context_compaction);
		setChecked(form, 'semantic_verification', !!record.semantic_verification);
		setChecked(form, 'model_decision_repair_enabled', record.model_decision_repair_enabled !== false);
		setChecked(form, 'sticky', record.sticky !== false);
		const readonly = !!record.builtin;
		form.querySelectorAll('input, select, textarea, button[data-action="apply-mode-defaults"]').forEach((node) => node.disabled = readonly);
		updateCapabilitySelectionControls(form, readonly);
		updateModelDecisionControls(form, readonly);
		updatePipelinePreview(form);
		dialog.execute('setTitle', readonly ? 'Built-in orchestrator profile' : (record.profile_id ? 'Edit orchestrator profile' : 'Add orchestrator profile'));
		dialog.execute('setButtons', dialogButtons(record));
		setDialogStatus(readonly ? 'Built-in profiles are read-only. Duplicate to customize.' : 'Core stage order is fixed and validated. Capability selection stages are mutually exclusive.', readonly ? '' : 'ok');
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
		[['ID', record.profile_id], [mbText('mode', 'Mode'), record.mode], [mbText('model_decision', 'Model decision'), record.model_decision_strategy], [mbText('decision_repair', 'Decision repair'), record.model_decision_repair_enabled ? 'yes' : 'no'], ['Decision threshold', record.model_decision_confidence_threshold], ['Kind', record.builtin_label], ['Enabled', record.enabled ? 'yes' : 'no'], ['Max loops', record.max_tool_loops], ['Pipeline', record.stage_text]].forEach(([key, value]) => {
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
			dialog = dialogModule.createStandardDialog({ strings: mbStringSet('cs_dialog_'), id: 'orchestrator-profile-dialog', className: 'orchestrator-profile-dialog', surfaceClassName: 'orchestrator-profile-dialog-surface', size: 'large', title: mbText('orchestrator_profile', 'Orchestrator profile'), content: getEditorContent(), status: '', closeButtonPlugin: { label: mbText('close', 'Close') }, statusPlugin: { renderEmpty: false }, buttons: [] });
			dialog.init();
			const form = getEditorForm();
			form.addEventListener('change', (event) => {
				const name = event.target && event.target.name ? String(event.target.name) : '';
				enforceCapabilitySelectionStageChoice(form, name);
				updateCapabilitySelectionControls(form);
				updateModelDecisionControls(form);
				updatePipelinePreview(form);
			});
			form.addEventListener('click', (event) => { const button = event.target.closest('[data-action="apply-mode-defaults"]'); if (button) applyModeDefaults(form); });
			const adapter = new AjaxAdapter({ url: ENDPOINT, method: 'POST', rowsPath: 'data', totalPath: 'total', mapRequest(request) {
				const state = grid ? grid.getState() : {};
				return { mode: 'page', page: request.page || 1, pageSize: request.pageSize || BATCH_SIZE, search: request.search || '', sort: [{ key: request.sortKey || 'profile_id', dir: request.sortDirection || 'asc' }], filters: state.filters || {} };
			} });
			grid = new ModularGrid(GRID_SELECTOR, {
			strings: mbStringSet('cs_grid_'),
				layout: { type: 'stack', children: [{ type: 'zone', key: 'topLine', className: 'orchestrator-profile-panel' }, { type: 'zone', key: 'filterLine', className: 'orchestrator-profile-panel' }, { type: 'view', key: 'main', className: 'orchestrator-profile-main' }, { type: 'zone', key: 'status', className: 'orchestrator-profile-panel' }] },
				adapter, dataMode: 'server', server: { searchDebounceMs: 220, watchStateKeys: ['query', 'filters'] }, features: { paging: false }, pageSize: BATCH_SIZE, sort: { key: 'profile_id', direction: 'asc' },
				plugins: [SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, ResetPlugin, RowActionsPlugin, RowDetailPlugin, InfiniteScrollPlugin].filter(Boolean),
				pluginOptions: {
					search: { zone: 'topLine', order: 10, label: mbText('search', 'Search'), placeholder: mbText('search_profiles_and_stages', 'Search profiles and stages') },
					filters: { zone: 'filterLine', order: 10, stateKey: 'filters', showClearButton: true, fields: [
						{ key: 'mode', label: mbText('mode', 'Mode'), type: 'select', options: [{ value: '', label: mbText('all_modes', 'All modes') }, { value: 'simple', label: mbText('simple', 'Simple') }, { value: 'standard', label: mbText('standard', 'Standard') }, { value: 'governed', label: mbText('governed', 'Governed') }] },
						{ key: 'enabled', label: mbText('state', 'State'), type: 'select', options: [{ value: '', label: mbText('all_states', 'All states') }, { value: '1', label: mbText('enabled', 'Enabled') }, { value: '0', label: mbText('disabled', 'Disabled') }] }
					] },
					reset: { zone: 'topLine', order: 20, label: mbText('reset', 'Reset'), sections: ['query', 'filters', 'detailView'] }, info: { zone: 'status', order: 10, displayMode: 'loaded' },
					rowActions: { items: [
						{ key: 'edit', label: mbText('open_profile', 'Open profile'), onClick(context) { openRow(context.row).catch((error) => setStatus(error.message)); } },
						{ key: 'duplicate', label: mbText('duplicate_profile', 'Duplicate profile'), onClick(context) { loadRowRecord(context.row).then((record) => openEditor(duplicateRecord(record))).catch((error) => setStatus(error.message)); } },
						{ key: 'delete', label: mbText('delete_custom_profile', 'Delete custom profile'), onClick(context) { deleteRecord(context.row.profile_id).catch((error) => setStatus(error.message)); } }
					] },
					rowDetail: { rowIdKey: 'profile_id', clearOnDataReload: true, asyncDetail: { load(context) { return postJson({ mode: 'record', id: context.row.profile_id }).then((response) => { if (!response.ok) throw new Error(response.error); return response.record; }); }, renderLoading() { return element('orchestrator-profile-panel', 'Loading profile...'); }, renderError(context) { return element('orchestrator-profile-panel orchestrator-profile-startup-error', text(context.error)); }, render(context) { return renderDetail(context); } } },
					infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' }
				},
				columns: [
					{ key: 'profile_id', label: mbText('profile', 'Profile'), width: 290, render: renderProfile },
					{ key: 'mode', label: mbText('mode_state', 'Mode / state'), width: 320, render: renderMode },
					{ key: 'stage_text', label: mbText('fixed_pipeline', 'Fixed pipeline'), width: 610, render: renderPipeline },
					{ key: 'max_tool_loops', label: mbText('loops', 'Loops'), width: 90 },
					{ key: 'model_decision_strategy', label: mbText('model_decision', 'Model decision'), width: 250, render: renderModelDecision },
					{ key: 'selection_strategy', label: mbText('tool_selection', 'Tool selection'), width: 230, render: renderSelection }
				]
			});
			grid.init();
			document.querySelector('#orchestrator-profile-add').addEventListener('click', () => { const record = { mode: 'standard', enabled: true }; openEditor(record); applyModeDefaults(getEditorForm()); });
			document.querySelector('#orchestrator-profile-reload').addEventListener('click', async () => { const response = await postJson({ mode: 'reload' }); if (!response.ok) throw new Error(response.error); await refreshGrid(); setStatus(mbText('profile_store_reloaded', 'Profile store reloaded.')); });
			setStatus(mbText('initialized', 'Initialized.'));
		} catch (error) {
			const root = document.querySelector(GRID_SELECTOR);
			root.replaceChildren(element('orchestrator-profile-panel orchestrator-profile-startup-error', error.message || String(error)));
			setStatus(mbText('initialization_failed', 'Initialization failed.'));
		}
	}
	init();
})();
</script>
