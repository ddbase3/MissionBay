<?php
$instanceId = htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES);
?>
<div id="<?php echo $instanceId; ?>" class="stt-config-admin">
	<h3>Speech-to-Text Services</h3>
	<div class="sttcfg-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Connection group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="sttcfg-loading">Please wait...</div>
	</div>
	<p class="sttcfg-hint">Speech-to-text services support complete transcription and, where the provider supports it, realtime microphone transcription through a short-lived browser session. Permanent connection secrets remain on the server.</p>
	<div class="sttcfg-layout">
		<section class="sttcfg-panel">
			<div class="sttcfg-toolbar">
				<button type="button" data-role="new">New service</button>
				<button type="button" data-role="reload">Reload</button>
			</div>
			<table class="sttcfg-table">
				<thead><tr><th>ID</th><th>Name</th><th>Connection</th><th>Driver</th><th>Model</th><th>Language</th><th>Status</th><th></th></tr></thead>
				<tbody data-role="tbody"><tr><td colspan="8" class="mono">Loading...</td></tr></tbody>
			</table>
		</section>
		<section class="sttcfg-panel">
			<form data-role="form">
				<h4 data-role="legend">Create speech-to-text service</h4>
				<div class="sttcfg-grid">
					<label>Service id<input type="text" name="id" placeholder="mistral-default" autocomplete="off"></label>
					<label>Name<input type="text" name="name" placeholder="Mistral Realtime" autocomplete="off"></label>
					<label>Connection<select name="connection"><option value="">Loading connections...</option></select></label>
					<label>Driver<select name="driver"><option value="">Loading drivers...</option></select></label>
					<label>Transcription model<input type="text" name="model" placeholder="voxtral-mini-latest" autocomplete="off"></label>
					<label>Realtime model<input type="text" name="realtimeModel" placeholder="voxtral-mini-transcribe-realtime-2602" autocomplete="off"><small>Optional. Leave empty when the provider uses the same model for complete and realtime transcription.</small></label>
					<label>Language<input type="text" name="language" placeholder="de" autocomplete="off"><small>Use an empty value for provider auto-detection.</small></label>
					<div class="sttcfg-row">
						<label>Sample rate<input type="text" name="sampleRate" placeholder="16000" inputmode="numeric"></label>
						<label>Target delay (ms)<input type="text" name="targetStreamingDelayMs" placeholder="480" inputmode="numeric"></label>
					</div>
					<label>Silence before auto-stop (ms)<input type="text" name="silenceDurationMs" placeholder="900" inputmode="numeric"></label>
					<label>No-speech timeout (ms)<input type="text" name="noSpeechTimeoutMs" placeholder="10000" inputmode="numeric"></label>
					<label>Advanced options JSON<textarea name="options" spellcheck="false" placeholder="{&#10;}"></textarea></label>
					<label class="sttcfg-checkbox"><input type="checkbox" name="enabled" checked><span>Enabled</span></label>
				</div>
				<div data-role="formfeedback" class="sttcfg-feedback" hidden></div>

				<div data-role="testresult" class="sttcfg-test-result" style="display:none">
					<div data-role="testmeta" class="sttcfg-test-meta"></div>
					<pre data-role="testpreview" class="sttcfg-test-preview"></pre>
				</div>
				<div class="sttcfg-actions"><button type="submit" class="primary">Save service</button><button type="button" data-role="test">Test service</button><button type="button" data-role="delete" disabled>Delete service</button></div>
			</form>
		</section>
	</div>
</div>
<style>
.stt-config-admin{background:#fff;border:1px solid #d6d6d6;padding:16px;border-radius:4px;font-family:Arial,sans-serif;color:#333}.stt-config-admin h3,.stt-config-admin h4{margin-top:0}.sttcfg-meta,.sttcfg-toolbar,.sttcfg-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.sttcfg-meta{font-size:13px;color:#555;margin-bottom:10px}.sttcfg-loading{display:none}.sttcfg-hint,.sttcfg-grid small{font-size:12px;color:#666}.sttcfg-layout{display:grid;grid-template-columns:minmax(680px,1fr) minmax(360px,500px);gap:16px;align-items:start}.sttcfg-panel{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:12px}.sttcfg-toolbar{margin-bottom:10px}.sttcfg-table{width:100%;border-collapse:collapse;background:#fff}.sttcfg-table th,.sttcfg-table td{padding:8px;border-bottom:1px solid #e0e0e0;text-align:left;font-size:13px}.sttcfg-table th{background:#f5f5f5}.sttcfg-table tr.selected td{background:#eef5ff}.mono,.sttcfg-table .technical{font-family:Consolas,monospace;font-size:12px}.sttcfg-grid{display:grid;gap:12px}.sttcfg-grid label{display:grid;gap:6px;font-weight:600;font-size:13px}.sttcfg-grid input[type=text],.sttcfg-grid select,.sttcfg-grid textarea{width:100%;box-sizing:border-box;border:1px solid #cfcfcf;border-radius:6px;padding:8px 10px;background:#fff}.sttcfg-grid textarea{min-height:90px;font-family:Consolas,monospace}.sttcfg-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.sttcfg-checkbox{display:flex!important;grid-template-columns:auto 1fr!important;align-items:center}.sttcfg-toolbar button,.sttcfg-actions button,.sttcfg-edit{border:1px solid #c9c9c9;background:#f1f1f1;border-radius:6px;padding:7px 10px;cursor:pointer}.sttcfg-actions{margin-top:14px}.sttcfg-actions .primary{background:#eaf3ff;border-color:#aac6ea}.sttcfg-feedback{margin-top:12px;padding:9px;border-radius:6px}.sttcfg-feedback.success{background:#f6fff6;color:#2d6b2d;border:1px solid #8d8}.sttcfg-feedback.error{background:#fff5f5;color:#a33;border:1px solid #d88}.badge{display:inline-block;padding:2px 7px;border-radius:999px;border:1px solid #ccc;font-size:12px}.badge.ok{background:#f6fff6;color:#2d6b2d;border-color:#8d8}.badge.off{background:#fff8df;color:#876c11;border-color:#d7c17a}.badge.warn{background:#fff4e8;color:#8a4f12;border-color:#e0a56b}@media(max-width:1200px){.sttcfg-layout{grid-template-columns:1fr}}@media(max-width:620px){.sttcfg-row{grid-template-columns:1fr}}

.sttcfg-test-result{margin-top:12px;border:1px solid #b9d3b9;background:#f8fff8;border-radius:6px;padding:10px 12px}.sttcfg-test-result.failed{border-color:#d88;background:#fff5f5}.sttcfg-test-meta{font-size:12px;color:#466846;margin-bottom:8px}.sttcfg-test-result.failed .sttcfg-test-meta{color:#8a3a3a}.sttcfg-test-preview{margin:0;max-height:240px;overflow:auto;white-space:pre-wrap;word-break:break-word;border:1px solid #d9e6d9;background:#fff;padding:10px;border-radius:4px;font-family:Consolas,monospace;font-size:12px;color:#333}
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
		loading:q('[data-role="loading"]'),lastupdate:q('[data-role="lastupdate"]'),tbody:q('[data-role="tbody"]'),form:q('[data-role="form"]'),legend:q('[data-role="legend"]'),feedback:q('[data-role="formfeedback"]'),newBtn:q('[data-role="new"]'),reloadBtn:q('[data-role="reload"]'),deleteBtn:q('[data-role="delete"]'),id:q('[name="id"]'),name:q('[name="name"]'),connection:q('[name="connection"]'),driver:q('[name="driver"]'),model:q('[name="model"]'),realtimeModel:q('[name="realtimeModel"]'),language:q('[name="language"]'),sampleRate:q('[name="sampleRate"]'),targetDelay:q('[name="targetStreamingDelayMs"]'),silence:q('[name="silenceDurationMs"]'),noSpeech:q('[name="noSpeechTimeoutMs"]'),options:q('[name="options"]'),enabled:q('[name="enabled"]')
	};
	const state = {services:[],connections:[],drivers:[],selectedId:''};
	const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
	const key = value => String(value || '').toLowerCase().trim().replace(/[^a-z0-9._-]+/g, '');
	const find = id => state.services.find(item => item.id === id) || null;
	const driver = id => state.drivers.find(item => item.driver === id) || null;
	const formatOptions = options => JSON.stringify(options && typeof options === 'object' ? options : {}, null, 2);

	function feedback(message, type) { refs.feedback.hidden = false; refs.feedback.className = 'sttcfg-feedback ' + type; refs.feedback.textContent = message; }
	function clearFeedback() { refs.feedback.hidden = true; refs.feedback.textContent = ''; }
	function setLoading(value) { refs.loading.style.display = value ? 'block' : 'none'; }
	function status(row) { if(!row.enabled) return '<span class="badge off">disabled</span>'; if(!row.connectionEnabled) return '<span class="badge warn">connection off</span>'; return '<span class="badge ok">enabled</span>'; }
	function renderSelect(select, rows, selected, label, valueKey, labelKey) {
		select.innerHTML = '<option value="">' + esc(rows.length ? label : 'No entries available') + '</option>';
		for(const row of rows) { const option = document.createElement('option'); option.value = row[valueKey] || ''; option.textContent = row[labelKey] || row[valueKey] || ''; if(row.enabled === false) option.textContent += ' [disabled]'; select.appendChild(option); }
		select.value = selected || '';
	}
	function renderRows() {
		if(!state.services.length) { refs.tbody.innerHTML = '<tr><td colspan="8" class="mono">No speech-to-text services configured.</td></tr>'; return; }
		refs.tbody.innerHTML = state.services.map(row => '<tr data-id="' + esc(row.id) + '"><td class="technical">' + esc(row.id) + '</td><td>' + esc(row.name) + '</td><td class="technical">' + esc(row.connection) + '</td><td class="technical">' + esc(row.driverLabel || row.driver) + '</td><td class="technical">' + esc(row.model) + '</td><td>' + esc(row.language) + '</td><td>' + status(row) + '</td><td><button type="button" class="sttcfg-edit" data-edit="' + esc(row.id) + '">Edit</button></td></tr>').join('');
		highlight();
	}
	function highlight() { root.querySelectorAll('tr[data-id]').forEach(row => row.classList.toggle('selected', row.dataset.id === state.selectedId)); }
	function applyDefaults(force) {
		const definition = driver(refs.driver.value); if(!definition) return;
		const defaults = definition.defaultConfig || {}; const options = defaults.options || {};
		if(force || !refs.model.value) refs.model.value = defaults.model || '';
		if(force) { refs.realtimeModel.value = options.realtimeModel ?? ''; refs.language.value = options.language ?? ''; refs.sampleRate.value = options.sampleRate ?? ''; refs.targetDelay.value = options.targetStreamingDelayMs ?? ''; refs.silence.value = options.silenceDurationMs ?? ''; refs.noSpeech.value = options.noSpeechTimeoutMs ?? ''; refs.options.value = formatOptions(options); }
	}
	function reset() {
		clearTestResult();
		refs.form.reset(); state.selectedId = ''; refs.id.readOnly = false; refs.legend.textContent = 'Create speech-to-text service'; refs.deleteBtn.disabled = true; refs.id.value = refs.name.value = refs.connection.value = refs.driver.value = refs.model.value = refs.realtimeModel.value = refs.language.value = refs.sampleRate.value = refs.targetDelay.value = refs.silence.value = refs.noSpeech.value = ''; refs.options.value = '{\n}'; refs.enabled.checked = true; highlight();
	}
	function fill(row) {
		clearTestResult();
		if(!row) { reset(); return; }
		state.selectedId = row.id || ''; refs.legend.textContent = 'Edit speech-to-text service'; refs.id.readOnly = true; refs.deleteBtn.disabled = false; refs.id.value = row.id || ''; refs.name.value = row.name || ''; refs.connection.value = row.connection || ''; refs.driver.value = row.driver || ''; refs.model.value = row.model || ''; refs.realtimeModel.value = row.realtimeModel || ''; refs.language.value = row.language || ''; refs.sampleRate.value = row.sampleRate || ''; refs.targetDelay.value = row.targetStreamingDelayMs || ''; refs.silence.value = row.silenceDurationMs || ''; refs.noSpeech.value = row.noSpeechTimeoutMs || ''; refs.options.value = formatOptions(row.options); refs.enabled.checked = !!row.enabled; highlight();
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
		state.services = json.data?.speechToTextServices || []; state.connections = json.data?.connections || []; state.drivers = json.data?.drivers || [];
		renderSelect(refs.connection, state.connections, refs.connection.value, 'Select connection', 'id', 'name'); renderSelect(refs.driver, state.drivers, refs.driver.value, 'Select driver', 'driver', 'label'); renderRows();
		const selected = find(preselect || state.selectedId); if(selected) fill(selected); else if(!state.services.length) reset();
	}
	function clearTestResult() {
		const panel = root.querySelector("[data-role='testresult']");
		if (!panel) return;
		panel.style.display = "none";
		panel.classList.remove("failed");
		root.querySelector("[data-role='testmeta']").textContent = "";
		root.querySelector("[data-role='testpreview']").textContent = "";
	}

	function renderTestResult(result) {
		const panel = root.querySelector("[data-role='testresult']");
		const meta = root.querySelector("[data-role='testmeta']");
		const preview = root.querySelector("[data-role='testpreview']");
		const details = result && result.details && typeof result.details === "object" ? result.details : {};
		const parts = [
			result && result.ok ? "Status: OK" : "Status: failed",
			"Driver: " + (details.driver || "-"),
			"Connection: " + (details.connectionId || "-"),
			"Model: " + (details.realtimeModel || details.model || details.resolvedModel || "-"),
			"Duration: " + String(details.durationMs ?? "-") + " ms"
		];
		meta.textContent = parts.join(" | ");

		const extra = Object.assign({}, details);
		delete extra.preview;
		const detailText = Object.keys(extra).length ? JSON.stringify(extra, null, 2) : "";
		preview.textContent = [details.preview || (result ? result.message : ""), detailText].filter(Boolean).join("\n\n");
		panel.classList.toggle("failed", !(result && result.ok));
		panel.style.display = "block";
	}

	function buildTestRequest() {
		const request = {action: "test"};
		const formData = new FormData(refs.form);
		formData.forEach((value, name) => { request[name] = String(value); });
		refs.form.querySelectorAll("input[type='checkbox'][name]").forEach(input => {
			request[input.name] = input.checked ? "1" : "0";
		});
		return request;
	}

	async function testCurrent() {
		clearFeedback();
		clearTestResult();
		const testBtn = root.querySelector("[data-role='test']");
		testBtn.disabled = true;
		const originalLabel = testBtn.textContent;
		testBtn.textContent = "Testing...";

		try {
			const json = await api(buildTestRequest());
			if (!json) return;

			const result = json.data && json.data.test ? json.data.test : null;
			if (!result) {
				feedback("Service test returned no result.", "error");
				return;
			}

			renderTestResult(result);
			feedback(result.message || (result.ok ? "Service test succeeded." : "Service test failed."), result.ok ? "success" : "error");
	} finally {
			testBtn.disabled = false;
			testBtn.textContent = originalLabel;
	}
	}

	async function save() {
		clearFeedback(); let advanced;
		try { advanced = JSON.parse(refs.options.value.trim() || '{}'); if(!advanced || Array.isArray(advanced)) throw new Error(); } catch(error) { feedback('Advanced options must be a JSON object.', 'error'); return; }
		const data = {action:'save',id:key(refs.id.value),name:refs.name.value.trim(),connection:key(refs.connection.value),driver:key(refs.driver.value),model:refs.model.value.trim(),realtimeModel:refs.realtimeModel.value.trim(),language:refs.language.value.trim(),sampleRate:refs.sampleRate.value.trim(),targetStreamingDelayMs:refs.targetDelay.value.trim(),silenceDurationMs:refs.silence.value.trim(),noSpeechTimeoutMs:refs.noSpeech.value.trim(),options:JSON.stringify(advanced),enabled:refs.enabled.checked?'1':'0'};
		if(!data.id || !data.name || !data.connection || !data.driver || !data.model) { feedback('Id, name, connection, driver and model are required.', 'error'); return; }
		const json = await api(data); if(!json) return; feedback('Speech-to-text service saved.', 'success'); await load(json.data?.speechToTextService?.id || data.id);
	}
	async function remove() {
		const id = state.selectedId; if(!id || !window.confirm("Delete speech-to-text service '" + id + "'?")) return;
		const json = await api({action:'remove',id}); if(!json) return; feedback('Speech-to-text service deleted.', 'success'); reset(); await load();
	}
	refs.form.addEventListener('submit', event => { event.preventDefault(); save(); }); q('[data-role="test"]').addEventListener('click', testCurrent); refs.newBtn.addEventListener('click', () => { clearFeedback(); reset(); }); refs.reloadBtn.addEventListener('click', () => load(state.selectedId)); refs.deleteBtn.addEventListener('click', remove); refs.driver.addEventListener('change', () => applyDefaults(true)); refs.tbody.addEventListener('click', event => { const button = event.target.closest('[data-edit]'); if(button) fill(find(button.dataset.edit)); });
	load();
})();
</script>
