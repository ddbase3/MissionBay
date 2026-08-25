<?php
$this->loadBricks('Administration');
$mbUiText = is_array($this->_['bricks']['missionbay_admin'] ?? null) ? $this->_['bricks']['missionbay_admin'] : [];
$mbText = static fn(string $key, string $fallback): string => trim((string)($mbUiText[$key] ?? '')) !== '' ? (string)$mbUiText[$key] : $fallback;
$mbTextEsc = static fn(string $key, string $fallback): string => htmlspecialchars($mbText($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$instanceId = htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div id="<?php echo $instanceId; ?>" class="stt-config-admin">
	<h3><?php echo $mbTextEsc('speech_to_text_services', 'Speech-to-Text Services'); ?></h3>
	<div class="sttcfg-meta">
		<div><strong><?php echo $mbTextEsc('settings_group', 'Settings group:'); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></div>
		<div><strong><?php echo $mbTextEsc('connection_group', 'Connection group:'); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></div>
		<div><strong><?php echo $mbTextEsc('last_update', 'Last update:'); ?></strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="sttcfg-loading"><?php echo $mbTextEsc('please_wait', 'Please wait...'); ?></div>
	</div>
	<p class="sttcfg-hint"><?php echo $mbTextEsc('speech_to_text_realtime_hint', 'These services create short-lived browser sessions for realtime microphone transcription. Permanent connection secrets remain on the server. OpenAI uses direct WebRTC. Mistral uses two parallel realtime streams.'); ?></p>
	<div class="sttcfg-layout">
		<section class="sttcfg-panel">
			<div class="sttcfg-toolbar">
				<button type="button" data-role="new"><?php echo $mbTextEsc('new_service', 'New service'); ?></button>
				<button type="button" data-role="reload"><?php echo $mbTextEsc('reload', 'Reload'); ?></button>
			</div>
			<table class="sttcfg-table">
				<thead>
					<tr>
						<th><?php echo $mbTextEsc('id', 'ID'); ?></th>
						<th><?php echo $mbTextEsc('name', 'Name'); ?></th>
						<th><?php echo $mbTextEsc('connection', 'Connection'); ?></th>
						<th><?php echo $mbTextEsc('driver', 'Driver'); ?></th>
						<th><?php echo $mbTextEsc('model', 'Model'); ?></th>
						<th><?php echo $mbTextEsc('required_words', 'Required words'); ?></th>
						<th><?php echo $mbTextEsc('status', 'Status'); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody"><tr><td colspan="8" class="mono"><?php echo $mbTextEsc('loading', 'Loading...'); ?></td></tr></tbody>
			</table>
		</section>
		<section class="sttcfg-panel">
			<form data-role="form">
				<h4 data-role="legend"><?php echo $mbTextEsc('create_speech_to_text_service', 'Create speech-to-text service'); ?></h4>
				<div class="sttcfg-grid">
					<label><?php echo $mbTextEsc('service_id', 'Service id'); ?><input type="text" name="id" placeholder="mistral-default" autocomplete="off"></label>
					<label><?php echo $mbTextEsc('name', 'Name'); ?><input type="text" name="name" placeholder="Mistral Speech-to-Text" autocomplete="off"></label>
					<label><?php echo $mbTextEsc('connection', 'Connection'); ?><select name="connection"><option value=""><?php echo $mbTextEsc('loading_connections', 'Loading connections...'); ?></option></select></label>
					<label><?php echo $mbTextEsc('driver', 'Driver'); ?><select name="driver"><option value=""><?php echo $mbTextEsc('loading_drivers', 'Loading drivers...'); ?></option></select></label>
					<label><?php echo $mbTextEsc('realtime_transcription_model', 'Realtime transcription model'); ?><input type="text" name="model" autocomplete="off"></label>
					<label><?php echo $mbTextEsc('required_words', 'Required words'); ?><textarea name="requiredWords" spellcheck="false" placeholder="ILIAS"></textarea><small><?php echo $mbTextEsc('required_words_help', 'One word or fixed expression per line. OpenAI receives these as keywords. Mistral additionally normalizes matching recognition variants to the configured spelling.'); ?></small></label>

					<div class="sttcfg-provider" data-driver-group="openai-stt" hidden>
						<h5>OpenAI WebRTC</h5>
						<label><?php echo $mbTextEsc('languages', 'Languages'); ?><textarea name="languages" spellcheck="false" placeholder="de"></textarea><small><?php echo $mbTextEsc('languages_help', 'One ISO language code per line.'); ?></small></label>
						<div class="sttcfg-row">
							<label><?php echo $mbTextEsc('transcription_delay', 'Transcription delay'); ?><select name="delay"><option value="low">low</option><option value="medium">medium</option><option value="high">high</option></select></label>
							<label><?php echo $mbTextEsc('noise_reduction', 'Noise reduction'); ?><select name="noiseReduction"><option value="far_field">far_field</option><option value="near_field">near_field</option></select></label>
						</div>
						<div class="sttcfg-row">
							<label><?php echo $mbTextEsc('client_secret_ttl_seconds', 'Client secret TTL (seconds)'); ?><input type="text" name="clientSecretTtlSeconds" inputmode="numeric"></label>
							<label><?php echo $mbTextEsc('finalization_timeout_ms', 'Finalization timeout (ms)'); ?><input type="text" name="openAiFinalizationTimeoutMs" inputmode="numeric"></label>
						</div>
						<label><?php echo $mbTextEsc('transcription_prompt', 'Transcription prompt'); ?><textarea name="prompt"></textarea></label>
					</div>

					<div class="sttcfg-provider" data-driver-group="mistral-stt" hidden>
						<h5>Mistral dual stream</h5>
						<div class="sttcfg-row">
							<label><?php echo $mbTextEsc('fast_stream_delay_ms', 'Fast stream delay (ms)'); ?><input type="text" name="fastStreamingDelayMs" inputmode="numeric"></label>
							<label><?php echo $mbTextEsc('correction_stream_delay_ms', 'Correction stream delay (ms)'); ?><input type="text" name="slowStreamingDelayMs" inputmode="numeric"></label>
						</div>
						<div class="sttcfg-row">
							<label><?php echo $mbTextEsc('audio_chunk_duration_ms', 'Audio chunk duration (ms)'); ?><input type="text" name="chunkDurationMs" inputmode="numeric"></label>
							<label><?php echo $mbTextEsc('session_timeout_ms', 'Session initialization timeout (ms)'); ?><input type="text" name="sessionTimeoutMs" inputmode="numeric"></label>
						</div>
						<label><?php echo $mbTextEsc('finalization_timeout_ms', 'Finalization timeout (ms)'); ?><input type="text" name="mistralFinalizationTimeoutMs" inputmode="numeric"></label>
					</div>

					<label><?php echo $mbTextEsc('advanced_options_json', 'Advanced options JSON'); ?><textarea name="options" spellcheck="false" placeholder="{&#10;}"></textarea><small><?php echo $mbTextEsc('advanced_options_stt_help', 'Only additional provider options belong here. Fields shown above are stored by the form.'); ?></small></label>
					<label class="sttcfg-checkbox"><input type="checkbox" name="enabled" checked><span><?php echo $mbTextEsc('enabled', 'Enabled'); ?></span></label>
				</div>
				<div data-role="formfeedback" class="sttcfg-feedback" hidden></div>
				<div data-role="testresult" class="sttcfg-test-result" hidden>
					<div data-role="testmeta" class="sttcfg-test-meta"></div>
					<pre data-role="testpreview" class="sttcfg-test-preview"></pre>
				</div>
				<div class="sttcfg-actions">
					<button type="submit" class="primary"><?php echo $mbTextEsc('save_service', 'Save service'); ?></button>
					<button type="button" data-role="test"><?php echo $mbTextEsc('test_service', 'Test service'); ?></button>
					<button type="button" data-role="delete" disabled><?php echo $mbTextEsc('delete_service', 'Delete service'); ?></button>
				</div>
			</form>
		</section>
	</div>
</div>
<style>
.stt-config-admin{background:#fff;border:1px solid #d6d6d6;padding:16px;border-radius:4px;font-family:Arial,sans-serif;color:#333}.stt-config-admin h3,.stt-config-admin h4,.stt-config-admin h5{margin-top:0}.sttcfg-meta,.sttcfg-toolbar,.sttcfg-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.sttcfg-meta{font-size:13px;color:#555;margin-bottom:10px}.sttcfg-loading{display:none}.sttcfg-hint,.sttcfg-grid small{font-size:12px;color:#666}.sttcfg-layout{display:grid;grid-template-columns:minmax(680px,1fr) minmax(390px,540px);gap:16px;align-items:start}.sttcfg-panel{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:12px}.sttcfg-toolbar{margin-bottom:10px}.sttcfg-table{width:100%;border-collapse:collapse;background:#fff}.sttcfg-table th,.sttcfg-table td{padding:8px;border-bottom:1px solid #e0e0e0;text-align:left;font-size:13px}.sttcfg-table th{background:#f5f5f5}.sttcfg-table tr.selected td{background:#eef5ff}.mono,.sttcfg-table .technical{font-family:Consolas,monospace;font-size:12px}.sttcfg-grid{display:grid;gap:12px}.sttcfg-grid label{display:grid;gap:6px;font-weight:600;font-size:13px}.sttcfg-grid input[type=text],.sttcfg-grid select,.sttcfg-grid textarea{width:100%;box-sizing:border-box;border:1px solid #cfcfcf;border-radius:6px;padding:8px 10px;background:#fff}.sttcfg-grid textarea{min-height:78px;font-family:Consolas,monospace}.sttcfg-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.sttcfg-provider{display:grid;gap:12px;padding:12px;border:1px solid #d7dfe8;border-radius:6px;background:#fff}.sttcfg-provider[hidden]{display:none}.sttcfg-checkbox{display:flex!important;grid-template-columns:auto 1fr!important;align-items:center}.sttcfg-toolbar button,.sttcfg-actions button,.sttcfg-edit{border:1px solid #c9c9c9;background:#f1f1f1;border-radius:6px;padding:7px 10px;cursor:pointer}.sttcfg-actions{margin-top:14px}.sttcfg-actions .primary{background:#eaf3ff;border-color:#aac6ea}.sttcfg-feedback{margin-top:12px;padding:9px;border-radius:6px}.sttcfg-feedback.success{background:#f6fff6;color:#2d6b2d;border:1px solid #8d8}.sttcfg-feedback.error{background:#fff5f5;color:#a33;border:1px solid #d88}.badge{display:inline-block;padding:2px 7px;border-radius:999px;border:1px solid #ccc;font-size:12px}.badge.ok{background:#f6fff6;color:#2d6b2d;border-color:#8d8}.badge.off{background:#fff8df;color:#876c11;border-color:#d7c17a}.badge.warn{background:#fff4e8;color:#8a4f12;border-color:#e0a56b}.sttcfg-test-result{margin-top:12px;border:1px solid #b9d3b9;background:#f8fff8;border-radius:6px;padding:10px 12px}.sttcfg-test-result.failed{border-color:#d88;background:#fff5f5}.sttcfg-test-meta{font-size:12px;color:#466846;margin-bottom:8px}.sttcfg-test-result.failed .sttcfg-test-meta{color:#8a3a3a}.sttcfg-test-preview{margin:0;max-height:240px;overflow:auto;white-space:pre-wrap;word-break:break-word;border:1px solid #d9e6d9;background:#fff;padding:10px;border-radius:4px;font-family:Consolas,monospace;font-size:12px;color:#333}@media(max-width:1200px){.sttcfg-layout{grid-template-columns:1fr}}@media(max-width:620px){.sttcfg-row{grid-template-columns:1fr}}
</style>
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
	const root = document.getElementById(<?php echo json_encode((string)$this->_['instanceId']); ?>);
	const endpoint = <?php echo json_encode((string)$this->_['endpoint']); ?>;
	if(!root) return;

	const q = selector => root.querySelector(selector);
	const refs = {
		form:q('[data-role="form"]'), tbody:q('[data-role="tbody"]'), loading:q('[data-role="loading"]'), lastupdate:q('[data-role="lastupdate"]'),
		legend:q('[data-role="legend"]'), feedback:q('[data-role="formfeedback"]'), newBtn:q('[data-role="new"]'), reloadBtn:q('[data-role="reload"]'),
		deleteBtn:q('[data-role="delete"]'), testBtn:q('[data-role="test"]'), id:q('[name="id"]'), name:q('[name="name"]'), connection:q('[name="connection"]'),
		driver:q('[name="driver"]'), model:q('[name="model"]'), requiredWords:q('[name="requiredWords"]'), languages:q('[name="languages"]'),
		delay:q('[name="delay"]'), noiseReduction:q('[name="noiseReduction"]'), clientSecretTtlSeconds:q('[name="clientSecretTtlSeconds"]'),
		prompt:q('[name="prompt"]'), openAiFinalization:q('[name="openAiFinalizationTimeoutMs"]'), fastDelay:q('[name="fastStreamingDelayMs"]'),
		slowDelay:q('[name="slowStreamingDelayMs"]'), chunkDuration:q('[name="chunkDurationMs"]'), sessionTimeout:q('[name="sessionTimeoutMs"]'),
		mistralFinalization:q('[name="mistralFinalizationTimeoutMs"]'), options:q('[name="options"]'), enabled:q('[name="enabled"]')
	};
	const state = {services:[],connections:[],drivers:[],selectedId:''};
	const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
	const key = value => String(value || '').trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '');
	const find = id => state.services.find(row => row.id === id);
	const driver = id => state.drivers.find(row => row.driver === id);
	const formatOptions = value => JSON.stringify(value && typeof value === 'object' && !Array.isArray(value) ? value : {}, null, 2);
	const listText = value => Array.isArray(value) ? value.join('\n') : '';

	function feedback(message, type) { refs.feedback.textContent = message; refs.feedback.className = 'sttcfg-feedback ' + type; refs.feedback.hidden = false; }
	function clearFeedback() { refs.feedback.hidden = true; refs.feedback.textContent = ''; }
	function setLoading(value) { refs.loading.style.display = value ? 'block' : 'none'; }
	function status(row) { if(!row.enabled) return '<span class="badge off">' + esc(mbText('disabled', 'disabled')) + '</span>'; if(!row.connectionEnabled) return '<span class="badge warn">' + esc(mbText('connection_off', 'connection off')) + '</span>'; return '<span class="badge ok">' + esc(mbText('enabled_2', 'enabled')) + '</span>'; }
	function renderSelect(select, rows, selected, label, valueKey, labelKey) {
		select.innerHTML = '<option value="">' + esc(rows.length ? label : mbText('no_entries_available', 'No entries available')) + '</option>';
		for(const row of rows) {
			const option = document.createElement('option');
			option.value = row[valueKey] || '';
			option.textContent = row[labelKey] || row[valueKey] || '';
			if(row.enabled === false) option.textContent += ' [disabled]';
			select.appendChild(option);
		}
		select.value = selected || '';
	}
	function renderRows() {
		if(!state.services.length) {
			refs.tbody.innerHTML = '<tr><td colspan="8" class="mono">' + esc(mbText('no_speech_to_text_services_configured', 'No speech-to-text services configured.')) + '</td></tr>';
			return;
		}
		refs.tbody.innerHTML = state.services.map(row => '<tr data-id="' + esc(row.id) + '"><td class="technical">' + esc(row.id) + '</td><td>' + esc(row.name) + '</td><td class="technical">' + esc(row.connection) + '</td><td class="technical">' + esc(row.driverLabel || row.driver) + '</td><td class="technical">' + esc(row.model) + '</td><td>' + esc(String(row.requiredWords || '').replace(/\n+/g, ', ')) + '</td><td>' + status(row) + '</td><td><button type="button" class="sttcfg-edit" data-edit="' + esc(row.id) + '">' + esc(mbText('edit', 'Edit')) + '</button></td></tr>').join('');
		highlight();
	}
	function highlight() { root.querySelectorAll('tr[data-id]').forEach(row => row.classList.toggle('selected', row.dataset.id === state.selectedId)); }
	function updateDriverGroups() { root.querySelectorAll('[data-driver-group]').forEach(group => { group.hidden = group.dataset.driverGroup !== refs.driver.value; }); }
	function clearSpecificFields() {
		refs.requiredWords.value = refs.languages.value = refs.prompt.value = refs.clientSecretTtlSeconds.value = refs.openAiFinalization.value = '';
		refs.fastDelay.value = refs.slowDelay.value = refs.chunkDuration.value = refs.sessionTimeout.value = refs.mistralFinalization.value = '';
		refs.delay.value = 'low';
		refs.noiseReduction.value = 'far_field';
	}
	function applyProviderOptions(options) {
		const values = options && typeof options === 'object' ? options : {};
		refs.requiredWords.value = listText(refs.driver.value === 'openai-stt' ? values.keywords : values.vocabulary);
		refs.languages.value = listText(values.languages);
		refs.delay.value = values.delay || 'low';
		refs.noiseReduction.value = values.noiseReduction || 'far_field';
		refs.clientSecretTtlSeconds.value = values.clientSecretTtlSeconds ?? '';
		refs.prompt.value = values.prompt || '';
		refs.openAiFinalization.value = refs.driver.value === 'openai-stt' ? (values.finalizationTimeoutMs ?? '') : '';
		refs.fastDelay.value = values.fastStreamingDelayMs ?? '';
		refs.slowDelay.value = values.slowStreamingDelayMs ?? '';
		refs.chunkDuration.value = values.chunkDurationMs ?? '';
		refs.sessionTimeout.value = values.sessionTimeoutMs ?? '';
		refs.mistralFinalization.value = refs.driver.value === 'mistral-stt' ? (values.finalizationTimeoutMs ?? '') : '';
	}
	function applyDefaults(force) {
		const definition = driver(refs.driver.value);
		updateDriverGroups();
		if(!definition) return;
		const defaults = definition.defaultConfig || {};
		if(force || !refs.model.value) refs.model.value = defaults.model || '';
		if(force) {
			clearSpecificFields();
			applyProviderOptions(defaults.options || {});
			refs.options.value = '{\n}';
		}
	}
	function reset() {
		clearTestResult();
		refs.form.reset();
		state.selectedId = '';
		refs.id.readOnly = false;
		refs.legend.textContent = mbText('create_speech_to_text_service', 'Create speech-to-text service');
		refs.deleteBtn.disabled = true;
		refs.id.value = refs.name.value = refs.connection.value = refs.driver.value = refs.model.value = '';
		clearSpecificFields();
		refs.options.value = '{\n}';
		refs.enabled.checked = true;
		updateDriverGroups();
		highlight();
	}
	function fill(row) {
		clearTestResult();
		if(!row) { reset(); return; }
		state.selectedId = row.id || '';
		refs.legend.textContent = mbText('edit_speech_to_text_service', 'Edit speech-to-text service');
		refs.id.readOnly = true;
		refs.deleteBtn.disabled = false;
		refs.id.value = row.id || '';
		refs.name.value = row.name || '';
		refs.connection.value = row.connection || '';
		refs.driver.value = row.driver || '';
		refs.model.value = row.model || '';
		clearSpecificFields();
		refs.requiredWords.value = row.requiredWords || '';
		refs.languages.value = row.languages || '';
		refs.delay.value = row.delay || 'low';
		refs.noiseReduction.value = row.noiseReduction || 'far_field';
		refs.clientSecretTtlSeconds.value = row.clientSecretTtlSeconds || '';
		refs.prompt.value = row.prompt || '';
		refs.openAiFinalization.value = row.driver === 'openai-stt' ? (row.finalizationTimeoutMs || '') : '';
		refs.fastDelay.value = row.fastStreamingDelayMs || '';
		refs.slowDelay.value = row.slowStreamingDelayMs || '';
		refs.chunkDuration.value = row.chunkDurationMs || '';
		refs.sessionTimeout.value = row.sessionTimeoutMs || '';
		refs.mistralFinalization.value = row.driver === 'mistral-stt' ? (row.finalizationTimeoutMs || '') : '';
		refs.options.value = formatOptions(row.advancedOptions);
		refs.enabled.checked = !!row.enabled;
		updateDriverGroups();
		highlight();
	}
	async function api(params) {
		setLoading(true);
		try {
			const body = new URLSearchParams();
			Object.entries(params).forEach(([name,value]) => body.append(name, value));
			const response = await fetch(endpoint, {method:'POST',headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()});
			const json = await response.json();
			refs.lastupdate.textContent = json.timestamp || '-';
			if(json.status !== 'ok') { feedback(json.message || 'Request failed.', 'error'); return null; }
			return json;
		} catch(error) {
			feedback('The request failed.', 'error');
			return null;
		} finally {
			setLoading(false);
		}
	}
	async function load(preselect) {
		const json = await api({action:'list'});
		if(!json) return;
		state.services = json.data?.speechToTextServices || [];
		state.connections = json.data?.connections || [];
		state.drivers = json.data?.drivers || [];
		renderSelect(refs.connection, state.connections, refs.connection.value, mbText('select_connection', 'Select connection'), 'id', 'name');
		renderSelect(refs.driver, state.drivers, refs.driver.value, mbText('select_driver', 'Select driver'), 'driver', 'label');
		renderRows();
		const selected = find(preselect || state.selectedId);
		if(selected) fill(selected); else if(!state.services.length) reset();
	}
	function clearTestResult() {
		const panel = q('[data-role="testresult"]');
		panel.hidden = true;
		panel.classList.remove('failed');
		q('[data-role="testmeta"]').textContent = '';
		q('[data-role="testpreview"]').textContent = '';
	}
	function renderTestResult(result) {
		const panel = q('[data-role="testresult"]');
		const details = result && result.details && typeof result.details === 'object' ? result.details : {};
		q('[data-role="testmeta"]').textContent = [result?.ok ? 'Status: OK' : 'Status: failed','Driver: ' + (details.driver || '-'),'Connection: ' + (details.connectionId || '-'),'Model: ' + (details.realtimeModel || details.model || details.resolvedModel || '-'),'Duration: ' + String(details.durationMs ?? '-') + ' ms'].join(' | ');
		const extra = Object.assign({}, details);
		delete extra.preview;
		q('[data-role="testpreview"]').textContent = [details.preview || result?.message || '', Object.keys(extra).length ? JSON.stringify(extra, null, 2) : ''].filter(Boolean).join('\n\n');
		panel.classList.toggle('failed', !result?.ok);
		panel.hidden = false;
	}
	function buildRequest(action) {
		let advanced;
		try {
			advanced = JSON.parse(refs.options.value.trim() || '{}');
			if(!advanced || Array.isArray(advanced)) throw new Error();
		} catch(error) {
			feedback(mbText('advanced_options_must_be_a_json_object', 'Advanced options must be a JSON object.'), 'error');
			return null;
		}
		const data = Object.fromEntries(new FormData(refs.form).entries());
		data.action = action;
		data.id = key(refs.id.value);
		data.connection = key(refs.connection.value);
		data.driver = key(refs.driver.value);
		data.enabled = refs.enabled.checked ? '1' : '0';
		data.options = JSON.stringify(advanced);
		data.finalizationTimeoutMs = refs.driver.value === 'openai-stt' ? refs.openAiFinalization.value.trim() : refs.mistralFinalization.value.trim();
		delete data.openAiFinalizationTimeoutMs;
		delete data.mistralFinalizationTimeoutMs;
		return data;
	}
	async function testCurrent() {
		clearFeedback();
		clearTestResult();
		const data = buildRequest('test');
		if(!data) return;
		refs.testBtn.disabled = true;
		const originalLabel = refs.testBtn.textContent;
		refs.testBtn.textContent = mbText('testing', 'Testing...');
		try {
			const json = await api(data);
			if(!json) return;
			const result = json.data?.test;
			if(!result) { feedback(mbText('service_test_returned_no_result', 'Service test returned no result.'), 'error'); return; }
			renderTestResult(result);
			feedback(result.message || (result.ok ? 'Service test succeeded.' : 'Service test failed.'), result.ok ? 'success' : 'error');
		} finally {
			refs.testBtn.disabled = false;
			refs.testBtn.textContent = originalLabel;
		}
	}
	async function save() {
		clearFeedback();
		const data = buildRequest('save');
		if(!data) return;
		if(!data.id || !String(data.name || '').trim() || !data.connection || !data.driver || !String(data.model || '').trim()) {
			feedback('Id, name, connection, driver and model are required.', 'error');
			return;
		}
		const json = await api(data);
		if(!json) return;
		feedback('Speech-to-text service saved.', 'success');
		await load(json.data?.speechToTextService?.id || data.id);
	}
	async function remove() {
		const id = state.selectedId;
		if(!id || !window.confirm(mbText('delete_speech_to_text_service_confirm', "Delete speech-to-text service '{id}'?", {id}))) return;
		const json = await api({action:'remove',id});
		if(!json) return;
		feedback('Speech-to-text service deleted.', 'success');
		reset();
		await load();
	}

	refs.form.addEventListener('submit', event => { event.preventDefault(); save(); });
	refs.testBtn.addEventListener('click', testCurrent);
	refs.newBtn.addEventListener('click', () => { clearFeedback(); reset(); });
	refs.reloadBtn.addEventListener('click', () => load(state.selectedId));
	refs.deleteBtn.addEventListener('click', remove);
	refs.driver.addEventListener('change', () => applyDefaults(true));
	refs.tbody.addEventListener('click', event => { const button = event.target.closest('[data-edit]'); if(button) fill(find(button.dataset.edit)); });
	load();
})();
</script>
