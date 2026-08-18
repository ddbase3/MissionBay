<?php
$this->loadBricks('Administration');
$mbUiText = is_array($this->_['bricks']['missionbay_admin'] ?? null) ? $this->_['bricks']['missionbay_admin'] : [];
$mbText = static fn(string $key, string $fallback): string => trim((string)($mbUiText[$key] ?? '')) !== '' ? (string)$mbUiText[$key] : $fallback;
$mbTextEsc = static fn(string $key, string $fallback): string => htmlspecialchars($mbText($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="vectorstore-config-admin">
	<h3><?php echo $mbTextEsc('vector_stores', 'Vector Stores'); ?></h3>

	<div class="vectorstorecfg-meta">
		<div><strong><?php echo $mbTextEsc('settings_group', 'Settings group:'); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong><?php echo $mbTextEsc('connection_group', 'Connection group:'); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong><?php echo $mbTextEsc('last_update', 'Last update:'); ?></strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="vectorstorecfg-loading"><?php echo $mbTextEsc('please_wait', 'Please wait...'); ?></div>
	</div>

	<div class="vectorstorecfg-hint">
		Vector stores configure backend storage for embeddings, similarity search and duplicate detection. Endpoint, secret and authentication header are taken from the selected connection.
	</div>

	<div class="vectorstorecfg-layout">
		<div class="vectorstorecfg-listbox">
			<div class="vectorstorecfg-toolbar">
				<button type="button" data-role="new"><?php echo $mbTextEsc('new_vector_store', 'New vector store'); ?></button>
				<button type="button" data-role="reload"><?php echo $mbTextEsc('reload', 'Reload'); ?></button>
			</div>

			<table class="vectorstorecfg-table">
				<thead>
					<tr>
						<th><?php echo $mbTextEsc('id', 'ID'); ?></th>
						<th><?php echo $mbTextEsc('name', 'Name'); ?></th>
						<th><?php echo $mbTextEsc('connection', 'Connection'); ?></th>
						<th><?php echo $mbTextEsc('driver', 'Driver'); ?></th>
						<th><?php echo $mbTextEsc('indexes', 'Indexes'); ?></th>
						<th><?php echo $mbTextEsc('status', 'Status'); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="7" class="mono"><?php echo $mbTextEsc('loading', 'Loading...'); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="vectorstorecfg-formbox">
			<form data-role="form">
				<h4 data-role="legend"><?php echo $mbTextEsc('create_vector_store', 'Create vector store'); ?></h4>

				<div class="vectorstorecfg-hint" data-role="idhint">
					Technical vector store id. Agent resources use this id to resolve the configured vector store.
				</div>

				<div class="vectorstorecfg-grid">
					<div class="vectorstorecfg-field">
						<label><?php echo $mbTextEsc('vector_store_id', 'Vector store id'); ?></label>
						<input type="text" name="id" placeholder="qdrant_default" autocomplete="off">
					</div>

					<div class="vectorstorecfg-field">
						<label><?php echo $mbTextEsc('name', 'Name'); ?></label>
						<input type="text" name="name" placeholder="Qdrant Default" autocomplete="off">
					</div>

					<div class="vectorstorecfg-field">
						<label><?php echo $mbTextEsc('connection', 'Connection'); ?></label>
						<select name="connection"><option value=""><?php echo $mbTextEsc('loading_connections', 'Loading connections...'); ?></option></select>
						<div class="vectorstorecfg-hint vectorstorecfg-inline-hint" data-role="connectionhint"><?php echo $mbTextEsc('connections_contain_endpoint_authentication_header_and_secret', 'Connections contain endpoint, authentication header and secret.'); ?></div>
					</div>

					<div class="vectorstorecfg-field">
						<label><?php echo $mbTextEsc('driver', 'Driver'); ?></label>
						<select name="driver"><option value=""><?php echo $mbTextEsc('loading_drivers', 'Loading drivers...'); ?></option></select>
					</div>

					<input type="hidden" name="model" value="qdrant">

					<div class="vectorstorecfg-field vectorstorecfg-field-checkbox">
						<label class="vectorstorecfg-checkbox"><input type="checkbox" name="createPayloadIndexes" checked><span><?php echo $mbTextEsc('create_payload_indexes', 'Create payload indexes'); ?></span></label>
					</div>

					<div class="vectorstorecfg-field vectorstorecfg-field-row">
						<div>
							<label><?php echo $mbTextEsc('timeout_seconds', 'Timeout seconds'); ?></label>
							<input type="text" name="timeoutSeconds" placeholder="90" autocomplete="off">
						</div>
						<div>
							<label><?php echo $mbTextEsc('connect_timeout_seconds', 'Connect timeout seconds'); ?></label>
							<input type="text" name="connectTimeoutSeconds" placeholder="20" autocomplete="off">
						</div>
					</div>

					<div class="vectorstorecfg-field">
						<label><?php echo $mbTextEsc('advanced_options_json', 'Advanced options JSON'); ?></label>
						<textarea name="options" spellcheck="false" placeholder="{&#10;}"></textarea>
						<div class="vectorstorecfg-hint vectorstorecfg-inline-hint"><?php echo $mbTextEsc('no_endpoint_secret_auth_header_or_proxy_option_here_those_belong_to_the_selected_connection_or_to_a_future_con', 'No endpoint, secret, auth header or proxy option here. Those belong to the selected connection or to a future connection driver.'); ?></div>
					</div>

					<div class="vectorstorecfg-field vectorstorecfg-field-checkbox">
						<label class="vectorstorecfg-checkbox"><input type="checkbox" name="enabled" checked><span><?php echo $mbTextEsc('enabled', 'Enabled'); ?></span></label>
					</div>
				</div>

				<div data-role="formfeedback" class="vectorstorecfg-form-feedback" style="display:none"></div>

				<div data-role="testresult" class="vectorstorecfg-test-result" style="display:none">
					<div data-role="testmeta" class="vectorstorecfg-test-meta"></div>
					<pre data-role="testpreview" class="vectorstorecfg-test-preview"></pre>
				</div>

				<div class="vectorstorecfg-actions">
					<button type="submit" class="primary"><?php echo $mbTextEsc('save_vector_store', 'Save vector store'); ?></button><button type="button" data-role="test"><?php echo $mbTextEsc('test_vector_store', 'Test vector store'); ?></button>
					<button type="button" data-role="delete" disabled><?php echo $mbTextEsc('delete_vector_store', 'Delete vector store'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
.vectorstore-config-admin{background:#fff;border:1px solid #d6d6d6;padding:16px;border-radius:4px;max-width:100%;font-family:Arial,sans-serif;color:#333}.vectorstore-config-admin h3{margin-top:0;margin-bottom:12px;font-size:1.1em}.vectorstore-config-admin h4{margin-top:0;margin-bottom:10px;font-size:1em}.vectorstorecfg-meta{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:13px;color:#555}.mono{font-family:Consolas,monospace}.vectorstorecfg-loading{display:none;color:#666;font-style:italic}.vectorstorecfg-layout{display:grid;grid-template-columns:minmax(720px,1fr) minmax(380px,520px);gap:16px;align-items:start}.vectorstorecfg-listbox,.vectorstorecfg-formbox{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:12px}.vectorstorecfg-toolbar{display:flex;gap:8px;margin-bottom:10px}.vectorstorecfg-toolbar button,.vectorstorecfg-actions button{border:1px solid #c9c9c9;background:#f1f1f1;color:#333;border-radius:6px;padding:8px 12px;cursor:pointer}.vectorstorecfg-toolbar button:hover,.vectorstorecfg-actions button:hover{background:#e8e8e8}.vectorstorecfg-actions .primary{background:#eaf3ff;border-color:#aac6ea}.vectorstorecfg-actions .primary:hover{background:#dcecff}.vectorstorecfg-actions button[disabled]{opacity:.5;cursor:not-allowed}.vectorstorecfg-table{width:100%;border-collapse:collapse;background:#fff}.vectorstorecfg-table th,.vectorstorecfg-table td{padding:8px 10px;border-bottom:1px solid #e0e0e0;vertical-align:middle;text-align:left;font-size:13px}.vectorstorecfg-table th{background:#f5f5f5;font-weight:600;border-bottom:2px solid #cfcfcf}.vectorstorecfg-table tr.selected td{background:#eef5ff}.vectorstorecfg-table td.id-col,.vectorstorecfg-table td.connection-col,.vectorstorecfg-table td.driver-col,.vectorstorecfg-table td.option-col{font-family:Consolas,monospace;font-size:12px}.vectorstorecfg-edit-btn{border:1px solid #c9c9c9;background:#f1f1f1;border-radius:6px;padding:5px 8px;cursor:pointer;font-size:12px}.vectorstorecfg-edit-btn:hover{background:#e8e8e8}.badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ccc;background:#f6f6f6;color:#333;font-size:12px;white-space:nowrap}.badge.ok{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.badge.off{border-color:#d7c17a;background:#fff8df;color:#876c11}.badge.warn{border-color:#e0a56b;background:#fff4e8;color:#8a4f12}.vectorstorecfg-hint{margin-bottom:12px;font-size:12px;color:#666}.vectorstorecfg-inline-hint{margin-top:6px;margin-bottom:0}.vectorstorecfg-grid{display:grid;grid-template-columns:1fr;gap:12px}.vectorstorecfg-field label{display:block;font-weight:600;margin-bottom:6px;font-size:13px}.vectorstorecfg-field input[type=text],.vectorstorecfg-field select,.vectorstorecfg-field textarea{width:100%;box-sizing:border-box;border:1px solid #cfcfcf;border-radius:6px;padding:8px 10px;background:#fff;color:#333}.vectorstorecfg-field textarea{min-height:150px;font-family:Consolas,monospace;font-size:12px;resize:vertical}.vectorstorecfg-field input[readonly]{background:#f6f6f6;color:#666}.vectorstorecfg-field-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.vectorstorecfg-field-checkbox{padding-top:4px}.vectorstorecfg-checkbox{display:inline-flex;align-items:center;gap:8px;font-weight:600}.vectorstorecfg-form-feedback{margin-top:14px;border:1px solid #ddd;border-radius:6px;padding:9px 11px;font-size:13px;line-height:1.4}.vectorstorecfg-form-feedback.success{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.vectorstorecfg-form-feedback.error{border-color:#d88;background:#fff5f5;color:#a33}.vectorstorecfg-actions{display:flex;gap:8px;margin-top:14px}@media (max-width:1200px){.vectorstorecfg-layout{grid-template-columns:1fr}}@media (max-width:620px){.vectorstorecfg-field-row{grid-template-columns:1fr}}

.vectorstorecfg-test-result{margin-top:12px;border:1px solid #b9d3b9;background:#f8fff8;border-radius:6px;padding:10px 12px}.vectorstorecfg-test-result.failed{border-color:#d88;background:#fff5f5}.vectorstorecfg-test-meta{font-size:12px;color:#466846;margin-bottom:8px}.vectorstorecfg-test-result.failed .vectorstorecfg-test-meta{color:#8a3a3a}.vectorstorecfg-test-preview{margin:0;max-height:240px;overflow:auto;white-space:pre-wrap;word-break:break-word;border:1px solid #d9e6d9;background:#fff;padding:10px;border-radius:4px;font-family:Consolas,monospace;font-size:12px;color:#333}
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
	const mbStringSet = (prefix) => Object.fromEntries(
		Object.entries(MB_UI_TEXT)
			.filter(([key, value]) => key.startsWith(prefix) && String(value || '').trim() !== '')
			.map(([key, value]) => [key.slice(prefix.length), value])
	);

	const instanceId = <?php echo json_encode((string)$this->_['instanceId']); ?>;
	const endpointBase = <?php echo json_encode((string)$this->_['endpoint']); ?>;

	function init() {
		const root = document.getElementById(instanceId);
		if (!root || root.dataset.initialized === "1") return;
		root.dataset.initialized = "1";

		const refs = {
			loading: root.querySelector("[data-role='loading']"),
			lastupdate: root.querySelector("[data-role='lastupdate']"),
			formfeedback: root.querySelector("[data-role='formfeedback']"),
			tbody: root.querySelector("[data-role='tbody']"),
			form: root.querySelector("[data-role='form']"),
			legend: root.querySelector("[data-role='legend']"),
			idhint: root.querySelector("[data-role='idhint']"),
			connectionhint: root.querySelector("[data-role='connectionhint']"),
			newBtn: root.querySelector("[data-role='new']"),
			reloadBtn: root.querySelector("[data-role='reload']"),
			deleteBtn: root.querySelector("[data-role='delete']"),
			id: root.querySelector("input[name='id']"),
			name: root.querySelector("input[name='name']"),
			connection: root.querySelector("select[name='connection']"),
			driver: root.querySelector("select[name='driver']"),
			model: root.querySelector("input[name='model']"),
			createPayloadIndexes: root.querySelector("input[name='createPayloadIndexes']"),
			timeoutSeconds: root.querySelector("input[name='timeoutSeconds']"),
			connectTimeoutSeconds: root.querySelector("input[name='connectTimeoutSeconds']"),
			options: root.querySelector("textarea[name='options']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		const state = {vectorstores: [], connections: [], drivers: [], selectedId: ""};
		const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));
		const normalizeKey = s => String(s ?? "").trim().toLowerCase().replace(/[^a-z0-9._-]+/g, "");
		const findVectorStore = id => state.vectorstores.find(item => String(item.id || "") === String(id || "")) || null;
		const findConnection = id => state.connections.find(item => String(item.id || "") === String(id || "")) || null;
		const findDriver = driver => state.drivers.find(item => String(item.driver || "") === String(driver || "")) || null;

		function setLoading(active) { refs.loading.style.display = active ? "block" : "none"; }
		function setLastUpdate(ts) { refs.lastupdate.textContent = ts || "-"; }
		function showFeedback(message, type) { refs.formfeedback.style.display = "block"; refs.formfeedback.className = "vectorstorecfg-form-feedback " + (type === "error" ? "error" : "success"); refs.formfeedback.textContent = message; }
		function clearFeedback() { refs.formfeedback.style.display = "none"; refs.formfeedback.className = "vectorstorecfg-form-feedback"; refs.formfeedback.textContent = ""; }
		function formatOptions(options) { const clean = Object.assign({}, options || {}); ["createPayloadIndexes","timeoutSeconds","connectTimeoutSeconds"].forEach(k => delete clean[k]); return Object.keys(clean).length ? JSON.stringify(clean, null, 2) : "{\n}"; }

		function connectionLabel(id) {
			const c = findConnection(id);
			if (!c) return id || "";

			let label = c.name || c.id || "";
			if (c.type) label += " (" + c.type + ")";

			return label;
		}

		function updateConnectionHint() {
			const c = findConnection(refs.connection.value);
			refs.connectionhint.textContent = c ? "Type: " + (c.type || "unknown") + ". Driver: " + (c.driver || "unknown") + ". Auth header: " + (c.authHeaderName || "driver default") + ". Base URL: " + (c.baseUrl || "not set") + "." + (!c.enabled ? " This connection is currently disabled." : "") : mbText('connections_contain_endpoint_authentication_header_and_secret', 'Connections contain endpoint, authentication header and secret.');
		}

		function setEditMode(editing) {
			refs.id.readOnly = editing;
			refs.deleteBtn.disabled = !editing;
			refs.legend.textContent = editing ? "Edit vector store" : mbText('create_vector_store', 'Create vector store');
			refs.idhint.textContent = editing ? "Technical vector store id is fixed for existing entries. Create a new entry if you need another key." : "Technical vector store id. Agent resources use this id to resolve the configured vector store.";
		}

		function renderConnectionOptions(selected) {
			refs.connection.innerHTML = "";

			const empty = document.createElement("option");
			empty.value = "";
			empty.textContent = state.connections.length > 0 ? mbText('select_connection', 'Select connection') : mbText('no_connections_configured', 'No connections configured');
			refs.connection.appendChild(empty);

			for (const connection of state.connections) {
				const option = document.createElement("option");
				option.value = connection.id || "";
				option.textContent = connectionLabel(connection.id);
				if (connection.enabled === false) option.textContent += " [disabled]";
				refs.connection.appendChild(option);
			}

			refs.connection.value = selected || "";
			updateConnectionHint();
		}

		function renderDriverOptions(selected) {
			refs.driver.innerHTML = "";

			const empty = document.createElement("option");
			empty.value = "";
			empty.textContent = state.drivers.length > 0 ? mbText('select_driver', 'Select driver') : "No vector store drivers available";
			refs.driver.appendChild(empty);

			for (const driver of state.drivers) {
				const option = document.createElement("option");
				option.value = driver.driver || "";
				option.textContent = driver.label || driver.driver || "";
				refs.driver.appendChild(option);
			}

			refs.driver.value = selected || "";
		}

		function applyDriverDefaults(force) {
			const driver = findDriver(refs.driver.value);
			if (!driver) return;

			const defaults = driver.defaultConfig || {};
			const options = defaults.options || {};

			if (force || !refs.model.value) refs.model.value = defaults.model || "qdrant";

			if (force) {
				refs.createPayloadIndexes.checked = options.createPayloadIndexes !== false;
				refs.timeoutSeconds.value = options.timeoutSeconds ?? "";
				refs.connectTimeoutSeconds.value = options.connectTimeoutSeconds ?? "";
				refs.options.value = formatOptions(options);
			}
		}

		function resetForm() {
			clearTestResult();
			refs.form.reset();
			refs.id.value = refs.name.value = refs.connection.value = refs.driver.value = "";
			refs.model.value = "qdrant";
			refs.timeoutSeconds.value = refs.connectTimeoutSeconds.value = "";
			refs.createPayloadIndexes.checked = true;
			refs.options.value = "{\n}";
			refs.enabled.checked = true;
			state.selectedId = "";
			setEditMode(false);
			updateConnectionHint();
			highlightSelection();
		}

		function fillForm(v) {
			clearTestResult();
			if (!v) {
				resetForm();
				return;
			}

			refs.id.value = v.id || "";
			refs.name.value = v.name || "";
			refs.connection.value = v.connection || "";
			refs.driver.value = v.driver || "";
			refs.model.value = v.model || "qdrant";
			refs.createPayloadIndexes.checked = v.createPayloadIndexes !== false;
			refs.timeoutSeconds.value = v.timeoutSeconds || "";
			refs.connectTimeoutSeconds.value = v.connectTimeoutSeconds || "";
			refs.options.value = formatOptions(v.options || {});
			refs.enabled.checked = !!v.enabled;

			state.selectedId = v.id || "";
			setEditMode(true);
			updateConnectionHint();
			highlightSelection();
		}

		function statusBadge(row) {
			if (!row.enabled) return "<span class='badge off'><?php echo $mbTextEsc('disabled', 'disabled'); ?></span>";
			if (!row.connectionEnabled) return "<span class='badge warn'><?php echo $mbTextEsc('connection_off', 'connection off'); ?></span>";
			return "<span class='badge ok'><?php echo $mbTextEsc('enabled_2', 'enabled'); ?></span>";
		}

		function renderRows() {
			refs.tbody.innerHTML = "";
			if (!state.vectorstores.length) {
				refs.tbody.innerHTML = "<tr><td colspan='7' class='mono'><?php echo $mbTextEsc('no_vector_stores_configured', 'No vector stores configured.'); ?></td></tr>";
				return;
			}

			state.vectorstores.forEach(v => {
				const tr = document.createElement("tr");
				tr.setAttribute("data-id", v.id || "");
				tr.innerHTML =
					"<td class='id-col'>" + esc(v.id) + "</td>" +
					"<td>" + esc(v.name) + "</td>" +
					"<td class='connection-col' title='" + esc(connectionLabel(v.connection)) + "'>" + esc(v.connection) + "<br><span style='color:#777'>" + esc(v.connectionType || "") + "</span></td>" +
					"<td class='driver-col'>" + esc(v.driverLabel || v.driver) + "</td>" +
					"<td class='option-col'>" + (v.createPayloadIndexes === false ? "off" : "on") + "</td>" +
					"<td>" + statusBadge(v) + "</td>" +
					"<td><button type='button' class='vectorstorecfg-edit-btn' data-action='edit' data-id='" + esc(v.id) + "'><?php echo $mbTextEsc('edit', 'Edit'); ?></button></td>";
				refs.tbody.appendChild(tr);
			});

			highlightSelection();
		}

		function highlightSelection() {
			root.querySelectorAll("tbody tr[data-id]").forEach(row => row.classList.toggle("selected", row.getAttribute("data-id") === state.selectedId));
		}

		async function callApi(params) {
			setLoading(true);
			try {
				const body = new URLSearchParams();
				Object.keys(params || {}).forEach(key => body.append(key, params[key]));

				const response = await fetch(endpointBase, {method: "POST", headers: {"Accept":"application/json","Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"}, body: body.toString()});
				const text = await response.text();
				let json;

				try {
					json = JSON.parse(text);
				} catch (e) {
					showFeedback(mbText('the_server_response_could_not_be_read', 'The server response could not be read.'), "error");
					return null;
				}

				setLastUpdate(json.timestamp || "");

				if (json.status !== "ok") {
					showFeedback(json.message || "The request could not be completed.", "error");
					return null;
				}

				return json;
			} catch (e) {
				showFeedback(mbText('the_request_failed_please_try_again', 'The request failed. Please try again.'), "error");
				return null;
			} finally {
				setLoading(false);
			}
		}

		async function loadList(preselectId) {
			const json = await callApi({action: "list"});
			if (!json) {
				refs.tbody.innerHTML = "<tr><td colspan='7' class='mono'><?php echo $mbTextEsc('vector_stores_could_not_be_loaded', 'Vector stores could not be loaded.'); ?></td></tr>";
				return;
			}

			state.connections = json.data && Array.isArray(json.data.connections) ? json.data.connections : [];
			state.drivers = json.data && Array.isArray(json.data.drivers) ? json.data.drivers : [];
			state.vectorstores = json.data && Array.isArray(json.data.vectorstores) ? json.data.vectorstores : [];

			renderConnectionOptions(refs.connection.value || "");
			renderDriverOptions(refs.driver.value || "");
			renderRows();

			const selected = findVectorStore(preselectId || state.selectedId);
			if (selected) fillForm(selected);
			else if (!state.vectorstores.length) resetForm();
		}

		function readOptionsJson() {
			const raw = String(refs.options.value || "").trim();
			if (!raw) return "{}";

			try {
				const parsed = JSON.parse(raw);
				if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
					showFeedback(mbText('advanced_options_must_be_a_json_object', 'Advanced options must be a JSON object.'), "error");
					return null;
				}
				return JSON.stringify(parsed);
			} catch (e) {
				showFeedback(mbText('advanced_options_must_be_valid_json', 'Advanced options must be valid JSON.'), "error");
				return null;
			}
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
				"Model: " + (details.model || details.resolvedModel || "-"),
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
			testBtn.textContent = mbText('testing', 'Testing...');

			try {
				const json = await callApi(buildTestRequest());
				if (!json) return;

				const result = json.data && json.data.test ? json.data.test : null;
				if (!result) {
					showFeedback(mbText('service_test_returned_no_result', 'Service test returned no result.'), "error");
					return;
				}

				renderTestResult(result);
				showFeedback(result.message || (result.ok ? "Service test succeeded." : "Service test failed."), result.ok ? "success" : "error");
			} finally {
				testBtn.disabled = false;
				testBtn.textContent = originalLabel;
			}
		}

		async function saveCurrent() {
			clearFeedback();

			const options = readOptionsJson();
			if (options === null) return;

			const id = normalizeKey(refs.id.value);
			const name = String(refs.name.value || "").trim();
			const connection = normalizeKey(refs.connection.value);
			const driver = normalizeKey(refs.driver.value);
			const model = String(refs.model.value || "").trim() || "qdrant";

			refs.id.value = id;
			refs.model.value = model;

			if (!id || !name || !connection || !driver) {
				showFeedback(mbText('vector_store_id_name_connection_and_driver_are_required', 'Vector store id, name, connection and driver are required.'), "error");
				return;
			}

			const json = await callApi({
				action: "save",
				id,
				name,
				connection,
				driver,
				model,
				createPayloadIndexes: refs.createPayloadIndexes.checked ? "1" : "0",
				timeoutSeconds: refs.timeoutSeconds.value.trim(),
				connectTimeoutSeconds: refs.connectTimeoutSeconds.value.trim(),
				options,
				enabled: refs.enabled.checked ? "1" : "0"
			});

			if (!json) return;

			const vectorstore = json.data && json.data.vectorstore ? json.data.vectorstore : null;
			showFeedback(mbText('vector_store_saved', 'Vector store saved.'), "success");
			await loadList(vectorstore && vectorstore.id ? vectorstore.id : id);
		}

		async function removeCurrent() {
			clearFeedback();

			const id = String(state.selectedId || refs.id.value || "").trim();
			if (!id) {
				showFeedback(mbText('no_vector_store_selected', 'No vector store selected.'), "error");
				return;
			}

			if (!window.confirm(mbText('delete_vector_store_confirm', "Delete vector store '{id}'?", {id}))) return;

			const json = await callApi({action:"remove", id});
			if (!json) return;

			showFeedback(mbText('vector_store_deleted', 'Vector store deleted.'), "success");
			resetForm();
			await loadList();
		}

		refs.form.addEventListener("submit", e => { e.preventDefault(); saveCurrent(); });
		root.querySelector("[data-role='test']").addEventListener("click", testCurrent);
		refs.newBtn.addEventListener("click", () => { clearFeedback(); resetForm(); });
		refs.reloadBtn.addEventListener("click", () => { clearFeedback(); loadList(state.selectedId || ""); });
		refs.deleteBtn.addEventListener("click", removeCurrent);
		refs.connection.addEventListener("change", updateConnectionHint);
		refs.driver.addEventListener("change", () => applyDriverDefaults(true));
		refs.tbody.addEventListener("click", e => {
			const btn = e.target.closest("button[data-action='edit']");
			if (!btn) return;
			clearFeedback();
			fillForm(findVectorStore(btn.getAttribute("data-id")));
		});

		loadList();
	}

	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
</script>
