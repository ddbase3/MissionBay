<?php
$instanceId = htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES);
?>
<div id="<?php echo $instanceId; ?>" class="tts-config-admin">
	<h3>Text-to-Speech Services</h3>
	<div class="ttscfg-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Connection group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="ttscfg-loading">Please wait...</div>
	</div>
	<p class="ttscfg-hint">Text-to-speech services generate audio through the configured server-side connection. Permanent provider credentials are never exposed to the browser.</p>
	<div class="ttscfg-layout">
		<section class="ttscfg-panel">
			<div class="ttscfg-toolbar">
				<button type="button" data-role="new">New service</button>
				<button type="button" data-role="reload">Reload</button>
			</div>
			<table class="ttscfg-table">
				<thead><tr><th>ID</th><th>Name</th><th>Connection</th><th>Driver</th><th>Model</th><th>Voice</th><th>Format</th><th>Status</th><th></th></tr></thead>
				<tbody data-role="tbody"><tr><td colspan="9" class="mono">Loading...</td></tr></tbody>
			</table>
		</section>
		<section class="ttscfg-panel">
			<form data-role="form">
				<h4 data-role="legend">Create text-to-speech service</h4>
				<div class="ttscfg-grid">
					<label>Service id<input type="text" name="id" placeholder="openai-default" autocomplete="off"></label>
					<label>Name<input type="text" name="name" placeholder="OpenAI Default Voice" autocomplete="off"></label>
					<label>Connection<select name="connection"><option value="">Loading connections...</option></select></label>
					<label>Driver<select name="driver"><option value="">Loading drivers...</option></select></label>
					<label>Model<input type="text" name="model" placeholder="gpt-4o-mini-tts" autocomplete="off"></label>
					<label>Voice<input type="text" name="voice" placeholder="alloy" autocomplete="off"></label>
					<div class="ttscfg-row">
						<label>Audio format<select name="responseFormat"><option value="mp3">mp3</option><option value="opus">opus</option><option value="aac">aac</option><option value="flac">flac</option><option value="wav">wav</option></select></label>
						<label>Speed<input type="text" name="speed" placeholder="1.0" inputmode="decimal"><small>Allowed range: 0.25 to 4.0.</small></label>
					</div>
					<label>Voice instructions<textarea name="instructions" spellcheck="true" placeholder="Speak clearly and warmly in German."></textarea></label>
					<label>Advanced options JSON<textarea name="options" spellcheck="false" placeholder="{&#10;}"></textarea></label>
					<label class="ttscfg-checkbox"><input type="checkbox" name="enabled" checked><span>Enabled</span></label>
				</div>
				<div data-role="formfeedback" class="ttscfg-feedback" hidden></div>
				<div class="ttscfg-actions"><button type="submit" class="primary">Save service</button><button type="button" data-role="delete" disabled>Delete service</button></div>
			</form>
		</section>
	</div>
</div>
<style>
.tts-config-admin{background:#fff;border:1px solid #d6d6d6;padding:16px;border-radius:4px;font-family:Arial,sans-serif;color:#333}.tts-config-admin h3,.tts-config-admin h4{margin-top:0}.ttscfg-meta,.ttscfg-toolbar,.ttscfg-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.ttscfg-meta{font-size:13px;color:#555;margin-bottom:10px}.ttscfg-loading{display:none}.ttscfg-hint,.ttscfg-grid small{font-size:12px;color:#666}.ttscfg-layout{display:grid;grid-template-columns:minmax(760px,1fr) minmax(360px,500px);gap:16px;align-items:start}.ttscfg-panel{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:12px}.ttscfg-toolbar{margin-bottom:10px}.ttscfg-table{width:100%;border-collapse:collapse;background:#fff}.ttscfg-table th,.ttscfg-table td{padding:8px;border-bottom:1px solid #e0e0e0;text-align:left;font-size:13px}.ttscfg-table th{background:#f5f5f5}.ttscfg-table tr.selected td{background:#eef5ff}.mono,.ttscfg-table .technical{font-family:Consolas,monospace;font-size:12px}.ttscfg-grid{display:grid;gap:12px}.ttscfg-grid label{display:grid;gap:6px;font-weight:600;font-size:13px}.ttscfg-grid input[type=text],.ttscfg-grid select,.ttscfg-grid textarea{width:100%;box-sizing:border-box;border:1px solid #cfcfcf;border-radius:6px;padding:8px 10px;background:#fff}.ttscfg-grid textarea{min-height:90px}.ttscfg-grid textarea[name=options]{font-family:Consolas,monospace}.ttscfg-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.ttscfg-checkbox{display:flex!important;grid-template-columns:auto 1fr!important;align-items:center}.ttscfg-toolbar button,.ttscfg-actions button,.ttscfg-edit{border:1px solid #c9c9c9;background:#f1f1f1;border-radius:6px;padding:7px 10px;cursor:pointer}.ttscfg-actions{margin-top:14px}.ttscfg-actions .primary{background:#eaf3ff;border-color:#aac6ea}.ttscfg-feedback{margin-top:12px;padding:9px;border-radius:6px}.ttscfg-feedback.success{background:#f6fff6;color:#2d6b2d;border:1px solid #8d8}.ttscfg-feedback.error{background:#fff5f5;color:#a33;border:1px solid #d88}.badge{display:inline-block;padding:2px 7px;border-radius:999px;border:1px solid #ccc;font-size:12px}.badge.ok{background:#f6fff6;color:#2d6b2d;border-color:#8d8}.badge.off{background:#fff8df;color:#876c11;border-color:#d7c17a}.badge.warn{background:#fff4e8;color:#8a4f12;border-color:#e0a56b}@media(max-width:1280px){.ttscfg-layout{grid-template-columns:1fr}}@media(max-width:620px){.ttscfg-row{grid-template-columns:1fr}}
</style>
<script>
(function() {
	const instanceId = <?php echo json_encode((string)$this->_['instanceId']); ?>;
	const endpoint = <?php echo json_encode((string)$this->_['endpoint']); ?>;
	const root = document.getElementById(instanceId);
	if(!root || root.dataset.initialized === '1') return;
	root.dataset.initialized = '1';

	const q = selector => root.querySelector(selector);
	const refs = {
		loading:q('[data-role="loading"]'),lastupdate:q('[data-role="lastupdate"]'),tbody:q('[data-role="tbody"]'),form:q('[data-role="form"]'),legend:q('[data-role="legend"]'),feedback:q('[data-role="formfeedback"]'),newBtn:q('[data-role="new"]'),reloadBtn:q('[data-role="reload"]'),deleteBtn:q('[data-role="delete"]'),id:q('[name="id"]'),name:q('[name="name"]'),connection:q('[name="connection"]'),driver:q('[name="driver"]'),model:q('[name="model"]'),voice:q('[name="voice"]'),responseFormat:q('[name="responseFormat"]'),speed:q('[name="speed"]'),instructions:q('[name="instructions"]'),options:q('[name="options"]'),enabled:q('[name="enabled"]')
	};
	const state = {services:[],connections:[],drivers:[],selectedId:''};
	const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
	const key = value => String(value || '').toLowerCase().trim().replace(/[^a-z0-9._-]+/g, '');
	const find = id => state.services.find(item => item.id === id) || null;
	const driver = id => state.drivers.find(item => item.driver === id) || null;
	const formatOptions = options => JSON.stringify(options && typeof options === 'object' ? options : {}, null, 2);

	function feedback(message, type) { refs.feedback.hidden = false; refs.feedback.className = 'ttscfg-feedback ' + type; refs.feedback.textContent = message; }
	function clearFeedback() { refs.feedback.hidden = true; refs.feedback.textContent = ''; }
	function setLoading(value) { refs.loading.style.display = value ? 'block' : 'none'; }
	function status(row) { if(!row.enabled) return '<span class="badge off">disabled</span>'; if(!row.connectionEnabled) return '<span class="badge warn">connection off</span>'; return '<span class="badge ok">enabled</span>'; }
	function renderSelect(select, rows, selected, label, valueKey, labelKey) {
		select.innerHTML = '<option value="">' + esc(rows.length ? label : 'No entries available') + '</option>';
		for(const row of rows) { const option = document.createElement('option'); option.value = row[valueKey] || ''; option.textContent = row[labelKey] || row[valueKey] || ''; if(row.enabled === false) option.textContent += ' [disabled]'; select.appendChild(option); }
		select.value = selected || '';
	}
	function renderRows() {
		if(!state.services.length) { refs.tbody.innerHTML = '<tr><td colspan="9" class="mono">No text-to-speech services configured.</td></tr>'; return; }
		refs.tbody.innerHTML = state.services.map(row => '<tr data-id="' + esc(row.id) + '"><td class="technical">' + esc(row.id) + '</td><td>' + esc(row.name) + '</td><td class="technical">' + esc(row.connection) + '</td><td class="technical">' + esc(row.driverLabel || row.driver) + '</td><td class="technical">' + esc(row.model) + '</td><td>' + esc(row.voice) + '</td><td class="technical">' + esc(row.responseFormat) + '</td><td>' + status(row) + '</td><td><button type="button" class="ttscfg-edit" data-edit="' + esc(row.id) + '">Edit</button></td></tr>').join('');
		highlight();
	}
	function highlight() { root.querySelectorAll('tr[data-id]').forEach(row => row.classList.toggle('selected', row.dataset.id === state.selectedId)); }
	function applyDefaults(force) {
		const definition = driver(refs.driver.value); if(!definition) return;
		const defaults = definition.defaultConfig || {}; const options = defaults.options || {};
		if(force || !refs.model.value) refs.model.value = defaults.model || '';
		if(force) { refs.voice.value = options.voice ?? ''; refs.responseFormat.value = options.responseFormat ?? 'mp3'; refs.speed.value = options.speed ?? '1'; refs.instructions.value = options.instructions ?? ''; refs.options.value = formatOptions(options); }
	}
	function reset() {
		refs.form.reset(); state.selectedId = ''; refs.id.readOnly = false; refs.legend.textContent = 'Create text-to-speech service'; refs.deleteBtn.disabled = true; refs.id.value = refs.name.value = refs.connection.value = refs.driver.value = refs.model.value = refs.voice.value = refs.speed.value = refs.instructions.value = ''; refs.responseFormat.value = 'mp3'; refs.options.value = '{\n}'; refs.enabled.checked = true; highlight();
	}
	function fill(row) {
		if(!row) { reset(); return; }
		state.selectedId = row.id || ''; refs.legend.textContent = 'Edit text-to-speech service'; refs.id.readOnly = true; refs.deleteBtn.disabled = false; refs.id.value = row.id || ''; refs.name.value = row.name || ''; refs.connection.value = row.connection || ''; refs.driver.value = row.driver || ''; refs.model.value = row.model || ''; refs.voice.value = row.voice || ''; refs.responseFormat.value = row.responseFormat || 'mp3'; refs.speed.value = row.speed || '1'; refs.instructions.value = row.instructions || ''; refs.options.value = formatOptions(row.options); refs.enabled.checked = !!row.enabled; highlight();
	}
	async function api(params) {
		setLoading(true);
		try {
			const body = new URLSearchParams(); Object.entries(params).forEach(([name,value]) => body.append(name, value));
			const response = await fetch(endpoint, {method:'POST',headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()});
			const json = await response.json(); refs.lastupdate.textContent = json.timestamp || '-'; if(json.status !== 'ok') { feedback(json.message || 'Request failed.', 'error'); return null; } return json;
		} catch(error) { feedback('The request failed.', 'error'); return null; } finally { setLoading(false); }
	}
	async function load(preselect) {
		const json = await api({action:'list'}); if(!json) return;
		state.services = json.data?.textToSpeechServices || []; state.connections = json.data?.connections || []; state.drivers = json.data?.drivers || [];
		renderSelect(refs.connection, state.connections, refs.connection.value, 'Select connection', 'id', 'name'); renderSelect(refs.driver, state.drivers, refs.driver.value, 'Select driver', 'driver', 'label'); renderRows();
		const selected = find(preselect || state.selectedId); if(selected) fill(selected); else if(!state.services.length) reset();
	}
	async function save() {
		clearFeedback(); let advanced;
		try { advanced = JSON.parse(refs.options.value.trim() || '{}'); if(!advanced || Array.isArray(advanced)) throw new Error(); } catch(error) { feedback('Advanced options must be a JSON object.', 'error'); return; }
		const data = {action:'save',id:key(refs.id.value),name:refs.name.value.trim(),connection:key(refs.connection.value),driver:key(refs.driver.value),model:refs.model.value.trim(),voice:refs.voice.value.trim(),responseFormat:refs.responseFormat.value,speed:refs.speed.value.trim(),instructions:refs.instructions.value.trim(),options:JSON.stringify(advanced),enabled:refs.enabled.checked?'1':'0'};
		if(!data.id || !data.name || !data.connection || !data.driver || !data.model || !data.voice) { feedback('Id, name, connection, driver, model and voice are required.', 'error'); return; }
		const json = await api(data); if(!json) return; feedback('Text-to-speech service saved.', 'success'); await load(json.data?.textToSpeechService?.id || data.id);
	}
	async function remove() {
		const id = state.selectedId; if(!id || !window.confirm("Delete text-to-speech service '" + id + "'?")) return;
		const json = await api({action:'remove',id}); if(!json) return; feedback('Text-to-speech service deleted.', 'success'); reset(); await load();
	}
	refs.form.addEventListener('submit', event => { event.preventDefault(); save(); }); refs.newBtn.addEventListener('click', () => { clearFeedback(); reset(); }); refs.reloadBtn.addEventListener('click', () => load(state.selectedId)); refs.deleteBtn.addEventListener('click', remove); refs.driver.addEventListener('change', () => applyDefaults(true)); refs.tbody.addEventListener('click', event => { const button = event.target.closest('[data-edit]'); if(button) fill(find(button.dataset.edit)); });
	load();
})();
</script>
