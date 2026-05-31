<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="connection-config-admin">
	<h3>Connections</h3>

	<div class="ccad-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="ccad-loading">Please wait...</div>
	</div>

	<div class="ccad-hint">
		Connections contain reusable technical access data only: endpoint, authentication header and secret. Services reference these connections.
	</div>

	<div data-role="output" class="ccad-output" style="display:none"></div>

	<div class="ccad-layout">
		<div class="ccad-listbox">
			<div class="ccad-toolbar">
				<button type="button" data-role="new">New connection</button>
				<button type="button" data-role="reload">Reload</button>
			</div>

			<table class="ccad-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Type</th>
						<th>Driver</th>
						<th>Base URL</th>
						<th>Auth</th>
						<th>Header</th>
						<th>Secret</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="10" class="mono">Loading...</td></tr>
				</tbody>
			</table>
		</div>

		<div class="ccad-formbox">
			<form data-role="form">
				<h4 data-role="legend">Create connection</h4>

				<div class="ccad-hint" data-role="idhint">
					Technical connection id. This becomes the fixed lookup key used by service configurations.
				</div>

				<div class="ccad-grid">
					<div class="ccad-field">
						<label>Connection id</label>
						<input type="text" name="id" placeholder="openai" autocomplete="off">
					</div>

					<div class="ccad-field">
						<label>Name</label>
						<input type="text" name="name" placeholder="OpenAI" autocomplete="off">
					</div>

					<div class="ccad-field ccad-field-row">
						<div>
							<label>Driver</label>
							<select name="driver"><option value="">Loading drivers...</option></select>
						</div>
						<div>
							<label>Type</label>
							<input type="text" name="type" placeholder="http" autocomplete="off" readonly>
						</div>
					</div>

					<div class="ccad-field">
						<label>Base URL</label>
						<input type="text" name="baseUrl" placeholder="https://api.openai.com" autocomplete="off">
					</div>

					<div class="ccad-field ccad-field-row">
						<div>
							<label>Auth type</label>
							<select name="authType">
								<option value="none">none</option>
								<option value="bearer">bearer</option>
								<option value="api-key">api-key</option>
								<option value="basic">basic</option>
							</select>
						</div>
						<div>
							<label>Auth header name</label>
							<input type="text" name="authHeaderName" placeholder="Authorization / api-key / X-API-Key" autocomplete="off">
						</div>
					</div>

					<div class="ccad-field ccad-field-row">
						<div>
							<label>Secret mode</label>
							<select name="secretMode">
								<option value="fixed">fixed</option>
								<option value="env">env</option>
							</select>
						</div>
						<div>
							<label data-role="secretvaluelabel">Secret value</label>
							<input type="text" name="secretValue" placeholder="sk-..." autocomplete="off">
						</div>
					</div>

					<div class="ccad-hint ccad-inline-hint" data-role="secrethint">
						For fixed mode, enter the API key directly. For env mode, enter the environment variable name.
					</div>

					<div class="ccad-field ccad-field-row">
						<div>
							<label>Timeout seconds</label>
							<input type="text" name="timeoutSeconds" placeholder="60" autocomplete="off">
						</div>
						<div>
							<label>Scope</label>
							<input type="text" name="scope" placeholder="global" autocomplete="off">
						</div>
					</div>

					<div class="ccad-field">
						<label>Options JSON</label>
						<textarea name="options" spellcheck="false" placeholder="{&#10;}"></textarea>
						<div class="ccad-hint ccad-inline-hint">Optional connection-driver options. Secrets are stored only in the auth block above.</div>
					</div>

					<div class="ccad-field ccad-field-checkbox">
						<label class="ccad-checkbox"><input type="checkbox" name="enabled" checked><span>Enabled</span></label>
					</div>
				</div>

				<div class="ccad-actions">
					<button type="submit" class="primary">Save connection</button>
					<button type="button" data-role="delete" disabled>Delete connection</button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
.connection-config-admin{background:#fff;border:1px solid #d6d6d6;padding:16px;border-radius:4px;max-width:100%;font-family:Arial,sans-serif;color:#333}.connection-config-admin h3{margin-top:0;margin-bottom:12px;font-size:1.1em}.connection-config-admin h4{margin-top:0;margin-bottom:10px;font-size:1em}.ccad-meta{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:13px;color:#555}.mono{font-family:Consolas,monospace}.ccad-loading{display:none;color:#666;font-style:italic}.ccad-output{background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px;font-family:Consolas,monospace;font-size:12px;white-space:pre-wrap;max-height:240px;overflow:auto;color:#444;margin-bottom:12px}.ccad-output.error{border-color:#d88;background:#fff5f5;color:#a33}.ccad-output.success{border-color:#8d8;background:#f6fff6;color:#373}.ccad-layout{display:grid;grid-template-columns:minmax(720px,1fr) minmax(380px,520px);gap:16px;align-items:start}.ccad-listbox,.ccad-formbox{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:12px}.ccad-toolbar{display:flex;gap:8px;margin-bottom:10px}.ccad-toolbar button,.ccad-actions button{border:1px solid #c9c9c9;background:#f1f1f1;color:#333;border-radius:6px;padding:8px 12px;cursor:pointer}.ccad-actions .primary{background:#eaf3ff;border-color:#aac6ea}.ccad-actions button[disabled]{opacity:.5;cursor:not-allowed}.ccad-table{width:100%;border-collapse:collapse;background:#fff}.ccad-table th,.ccad-table td{padding:8px 10px;border-bottom:1px solid #e0e0e0;vertical-align:middle;text-align:left;font-size:13px}.ccad-table th{background:#f5f5f5;font-weight:600;border-bottom:2px solid #cfcfcf}.ccad-table tr.selected td{background:#eef5ff}.ccad-table td.id-col,.ccad-table td.type-col,.ccad-table td.driver-col,.ccad-table td.url-col,.ccad-table td.secret-col,.ccad-table td.header-col{font-family:Consolas,monospace;font-size:12px}.ccad-table td.url-col{max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ccad-edit-btn{border:1px solid #c9c9c9;background:#f1f1f1;border-radius:6px;padding:5px 8px;cursor:pointer;font-size:12px}.badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ccc;background:#f6f6f6;color:#333;font-size:12px;white-space:nowrap}.badge.ok{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.badge.off{border-color:#d7c17a;background:#fff8df;color:#876c11}.ccad-hint{margin-bottom:12px;font-size:12px;color:#666}.ccad-inline-hint{margin-top:6px;margin-bottom:0}.ccad-grid{display:grid;grid-template-columns:1fr;gap:12px}.ccad-field label{display:block;font-weight:600;margin-bottom:6px;font-size:13px}.ccad-field input[type=text],.ccad-field select,.ccad-field textarea{width:100%;box-sizing:border-box;border:1px solid #cfcfcf;border-radius:6px;padding:8px 10px;background:#fff;color:#333}.ccad-field input[readonly],.ccad-field input[disabled],.ccad-field select[disabled]{background:#f6f6f6;color:#666}.ccad-field textarea{min-height:150px;font-family:Consolas,monospace;font-size:12px;resize:vertical}.ccad-field-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.ccad-field-checkbox{padding-top:4px}.ccad-checkbox{display:inline-flex;align-items:center;gap:8px;font-weight:600}.ccad-actions{display:flex;gap:8px;margin-top:14px}@media (max-width:1200px){.ccad-layout{grid-template-columns:1fr}}@media (max-width:620px){.ccad-field-row{grid-template-columns:1fr}}
</style>

<script>
(function() {
	const instanceId = <?php echo json_encode((string)$this->_['instanceId']); ?>;
	const endpointBase = <?php echo json_encode((string)$this->_['endpoint']); ?>;
	const configGroup = <?php echo json_encode((string)$this->_['configGroup']); ?>;

	function init() {
		const root = document.getElementById(instanceId);
		if (!root || root.dataset.initialized === "1") return;
		root.dataset.initialized = "1";

		const refs = {
			loading: root.querySelector("[data-role='loading']"), lastupdate: root.querySelector("[data-role='lastupdate']"), output: root.querySelector("[data-role='output']"), tbody: root.querySelector("[data-role='tbody']"), form: root.querySelector("[data-role='form']"), legend: root.querySelector("[data-role='legend']"), idhint: root.querySelector("[data-role='idhint']"), secretvaluelabel: root.querySelector("[data-role='secretvaluelabel']"), secrethint: root.querySelector("[data-role='secrethint']"), newBtn: root.querySelector("[data-role='new']"), reloadBtn: root.querySelector("[data-role='reload']"), deleteBtn: root.querySelector("[data-role='delete']"),
			id: root.querySelector("input[name='id']"), name: root.querySelector("input[name='name']"), driver: root.querySelector("select[name='driver']"), type: root.querySelector("input[name='type']"), baseUrl: root.querySelector("input[name='baseUrl']"), authType: root.querySelector("select[name='authType']"), authHeaderName: root.querySelector("input[name='authHeaderName']"), secretMode: root.querySelector("select[name='secretMode']"), secretValue: root.querySelector("input[name='secretValue']"), timeoutSeconds: root.querySelector("input[name='timeoutSeconds']"), scope: root.querySelector("input[name='scope']"), options: root.querySelector("textarea[name='options']"), enabled: root.querySelector("input[name='enabled']")
		};

		const state = {connections: [], drivers: [], selectedId: ""};
		const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));
		const normalizeKey = s => String(s ?? "").trim().toLowerCase().replace(/[^a-z0-9._-]+/g, "");
		const findDriver = driver => state.drivers.find(item => String(item.driver || "") === String(driver || "")) || null;
		const findConnection = id => state.connections.find(item => String(item.id || "") === String(id || "")) || null;

		function setLoading(active) { refs.loading.style.display = active ? "block" : "none"; }
		function setLastUpdate(ts) { refs.lastupdate.textContent = ts || "-"; }
		function printOutput(obj, type) { refs.output.style.display = "block"; refs.output.className = "ccad-output"; if (type) refs.output.classList.add(type); refs.output.textContent = typeof obj === "string" ? obj : JSON.stringify(obj, null, 2); }
		function clearOutput() { refs.output.style.display = "none"; refs.output.textContent = ""; }
		function formatOptions(options) { return options && typeof options === "object" && !Array.isArray(options) && Object.keys(options).length ? JSON.stringify(options, null, 2) : "{\n}"; }
		function maskSecret(value, mode) { value = String(value || ""); if (!value) return ""; return String(mode || "fixed") === "env" ? value : "********"; }
		function setEditMode(editing) { refs.id.readOnly = editing; refs.deleteBtn.disabled = !editing; refs.legend.textContent = editing ? "Edit connection" : "Create connection"; refs.idhint.textContent = editing ? "Technical connection id is fixed for existing entries. Create a new entry if you need another key." : "Technical connection id. This becomes the fixed lookup key used by service configurations."; }
		function updateAuthUi() { const authType = normalizeKey(refs.authType.value); const secretMode = normalizeKey(refs.secretMode.value); if (authType === "none") { refs.secretMode.value = "fixed"; refs.secretMode.disabled = true; refs.secretValue.disabled = true; refs.authHeaderName.disabled = true; refs.secretValue.value = ""; refs.authHeaderName.value = ""; refs.secretvaluelabel.textContent = "Secret value"; refs.secrethint.textContent = "No secret is used when auth type is none."; return; } refs.secretMode.disabled = false; refs.secretValue.disabled = false; refs.authHeaderName.disabled = false; if (authType === "bearer" && !refs.authHeaderName.value) refs.authHeaderName.value = "Authorization"; if (secretMode === "env") { refs.secretvaluelabel.textContent = "Environment variable"; refs.secretValue.placeholder = "OPENAI_API_KEY"; refs.secrethint.textContent = "Enter the environment variable name. The runtime resolves it when the connection is used."; return; } refs.secretvaluelabel.textContent = "Secret value"; refs.secretValue.placeholder = "sk-..."; refs.secrethint.textContent = "Enter the API key directly. The value will be stored in the SettingsStore."; }
		function renderDriverOptions(selected) { refs.driver.innerHTML = ""; if (!state.drivers.length) { const option = document.createElement("option"); option.value = ""; option.textContent = "No drivers available"; refs.driver.appendChild(option); return; } state.drivers.forEach(driver => { const option = document.createElement("option"); option.value = driver.driver || ""; option.textContent = (driver.label || driver.driver || "") + " (" + (driver.type || "unknown") + ")"; refs.driver.appendChild(option); }); refs.driver.value = selected || refs.driver.value || state.drivers[0].driver || ""; applyDriverDefaults(false); }
		function applyDriverDefaults(force) { const driver = findDriver(refs.driver.value); if (!driver) return; const defaults = driver.defaultConfig && typeof driver.defaultConfig === "object" ? driver.defaultConfig : {}; const auth = defaults.auth && typeof defaults.auth === "object" ? defaults.auth : {}; refs.type.value = driver.type || defaults.type || refs.type.value || ""; if (force) { refs.authType.value = auth.type || "bearer"; refs.authHeaderName.value = auth.headerName || ""; refs.secretMode.value = auth.secretMode || "fixed"; refs.timeoutSeconds.value = defaults.timeoutSeconds || "60"; refs.scope.value = defaults.scope || "global"; refs.enabled.checked = defaults.enabled !== false; refs.options.value = formatOptions(defaults.options || {}); } updateAuthUi(); }
		function resetForm() { refs.form.reset(); refs.id.value = refs.name.value = refs.baseUrl.value = refs.authHeaderName.value = refs.secretValue.value = ""; refs.authType.value = "bearer"; refs.secretMode.value = "fixed"; refs.timeoutSeconds.value = "60"; refs.scope.value = "global"; refs.options.value = "{\n}"; refs.enabled.checked = true; state.selectedId = ""; setEditMode(false); renderDriverOptions(""); applyDriverDefaults(true); updateAuthUi(); highlightSelection(); }
		function fillForm(connection) { if (!connection) { resetForm(); return; } refs.id.value = connection.id || ""; refs.name.value = connection.name || ""; refs.driver.value = connection.driver || ""; refs.type.value = connection.type || ""; refs.baseUrl.value = connection.baseUrl || ""; refs.authType.value = connection.authType || "none"; refs.authHeaderName.value = connection.authHeaderName || ""; refs.secretMode.value = connection.secretMode || "fixed"; refs.secretValue.value = connection.secretValue || ""; refs.timeoutSeconds.value = connection.timeoutSeconds || "60"; refs.scope.value = connection.scope || "global"; refs.options.value = formatOptions(connection.options || {}); refs.enabled.checked = !!connection.enabled; state.selectedId = connection.id || ""; setEditMode(true); updateAuthUi(); highlightSelection(); }
		function statusBadge(enabled) { return enabled ? "<span class='badge ok'>enabled</span>" : "<span class='badge off'>disabled</span>"; }
		function renderRows() { refs.tbody.innerHTML = ""; if (!state.connections.length) { refs.tbody.innerHTML = "<tr><td colspan='10' class='mono'>No connections configured.</td></tr>"; return; } state.connections.forEach(connection => { const tr = document.createElement("tr"); tr.setAttribute("data-id", String(connection.id || "")); tr.innerHTML = "<td class='id-col'>" + esc(connection.id) + "</td><td>" + esc(connection.name) + "</td><td class='type-col'>" + esc(connection.type) + "</td><td class='driver-col'>" + esc(connection.driverLabel || connection.driver) + "</td><td class='url-col' title='" + esc(connection.baseUrl) + "'>" + esc(connection.baseUrl) + "</td><td><span class='mono'>" + esc(connection.authType) + "</span></td><td class='header-col'>" + esc(connection.authHeaderName || "") + "</td><td class='secret-col' title='" + esc(connection.secretMode || "fixed") + "'><span class='mono'>" + esc(connection.secretMode || "fixed") + "</span><br><span class='mono' style='color:#777'>" + esc(maskSecret(connection.secretValue, connection.secretMode)) + "</span></td><td>" + statusBadge(!!connection.enabled) + "</td><td><button type='button' class='ccad-edit-btn' data-action='edit' data-id='" + esc(connection.id) + "'>Edit</button></td>"; refs.tbody.appendChild(tr); }); highlightSelection(); }
		function highlightSelection() { root.querySelectorAll("tbody tr[data-id]").forEach(row => row.classList.toggle("selected", row.getAttribute("data-id") === state.selectedId)); }
		async function callApi(params) { setLoading(true); try { const body = new URLSearchParams(); Object.keys(params || {}).forEach(key => body.append(key, params[key])); const response = await fetch(endpointBase, {method:"POST", headers:{"Accept":"application/json","Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"}, body:body.toString()}); const text = await response.text(); let json; try { json = JSON.parse(text); } catch (e) { printOutput("Invalid JSON response:\n" + text, "error"); return null; } setLastUpdate(json.timestamp || ""); if (json.status !== "ok") { printOutput(json.message || json, "error"); return null; } return json; } catch (e) { printOutput("Request failed:\n" + e, "error"); return null; } finally { setLoading(false); } }
		async function loadList(preselectId) { const json = await callApi({action:"list"}); if (!json) return; state.drivers = json.data && Array.isArray(json.data.drivers) ? json.data.drivers : []; state.connections = json.data && Array.isArray(json.data.connections) ? json.data.connections : []; renderDriverOptions(refs.driver.value || ""); renderRows(); const selected = findConnection(preselectId || state.selectedId); if (selected) fillForm(selected); else if (!state.connections.length) resetForm(); }
		function readOptionsJson() { const raw = String(refs.options.value || "").trim(); if (!raw) return "{}"; try { const parsed = JSON.parse(raw); if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) { printOutput("Options must be a JSON object.", "error"); return null; } return JSON.stringify(parsed); } catch (e) { printOutput("Options must be valid JSON:\n" + e.message, "error"); return null; } }
		async function saveCurrent() { const id = normalizeKey(refs.id.value); const name = String(refs.name.value || "").trim(); const driver = normalizeKey(refs.driver.value); const type = normalizeKey(refs.type.value); const baseUrl = String(refs.baseUrl.value || "").trim(); const authType = normalizeKey(refs.authType.value); const authHeaderName = String(refs.authHeaderName.value || "").trim(); const secretMode = normalizeKey(refs.secretMode.value || "fixed"); const secretValue = String(refs.secretValue.value || "").trim(); const timeoutSeconds = String(refs.timeoutSeconds.value || "").trim(); const scope = normalizeKey(refs.scope.value || "global"); const options = readOptionsJson(); if (options === null) return; refs.id.value = id; refs.driver.value = driver; refs.type.value = type; refs.scope.value = scope; if (!id || !name || !driver || !type) { printOutput("Connection id, name, driver and type are required.", "error"); return; } if (type === "http" && !baseUrl) { printOutput("Base URL is required for HTTP connections.", "error"); return; } if (authType !== "none" && !secretValue) { printOutput("Secret value is required unless auth type is none.", "error"); return; } const json = await callApi({action:"save", id, name, driver, type, baseUrl, authType, authHeaderName, secretMode, secretValue, timeoutSeconds, scope, enabled: refs.enabled.checked ? "1" : "0", options}); if (!json) return; const connection = json.data && json.data.connection ? json.data.connection : null; printOutput({status:"saved", group:configGroup, connection: connection ? Object.assign({}, connection, {secretValue: maskSecret(connection.secretValue, connection.secretMode)}) : null}, "success"); await loadList(connection && connection.id ? connection.id : id); }
		async function removeCurrent() { const id = String(state.selectedId || refs.id.value || "").trim(); if (!id) { printOutput("No connection selected.", "error"); return; } if (!window.confirm("Delete connection '" + id + "'?")) return; const json = await callApi({action:"remove", id}); if (!json) return; printOutput({status:"removed", group:configGroup, id}, "success"); resetForm(); await loadList(); }
		refs.form.addEventListener("submit", e => { e.preventDefault(); saveCurrent(); }); refs.newBtn.addEventListener("click", () => { clearOutput(); resetForm(); }); refs.reloadBtn.addEventListener("click", () => { clearOutput(); loadList(state.selectedId || ""); }); refs.deleteBtn.addEventListener("click", removeCurrent); refs.driver.addEventListener("change", () => applyDriverDefaults(false)); refs.authType.addEventListener("change", updateAuthUi); refs.secretMode.addEventListener("change", updateAuthUi); refs.tbody.addEventListener("click", e => { const btn = e.target.closest("button[data-action='edit']"); if (!btn) return; clearOutput(); fillForm(findConnection(btn.getAttribute("data-id"))); });
		loadList();
	}

	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
</script>
