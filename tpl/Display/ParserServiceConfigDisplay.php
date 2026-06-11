<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="parser-config-admin">
	<h3>Parsers</h3>

	<div class="parsercfg-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Connection group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="parsercfg-loading">Please wait...</div>
	</div>

	<div class="parsercfg-hint">
		Parsers receive files, text or streams and return extracted text plus structured payload data. Endpoint, secret and authentication header are taken from the selected connection.
	</div>

	<div class="parsercfg-layout">
		<div class="parsercfg-listbox">
			<div class="parsercfg-toolbar">
				<button type="button" data-role="new">New parser</button>
				<button type="button" data-role="reload">Reload</button>
			</div>

			<table class="parsercfg-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Connection</th>
						<th>Driver</th>
						<th>Types</th>
						<th>Priority</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="8" class="mono">Loading...</td></tr>
				</tbody>
			</table>
		</div>

		<div class="parsercfg-formbox">
			<form data-role="form">
				<h4 data-role="legend">Create parser</h4>

				<div class="parsercfg-hint" data-role="idhint">
					Technical parser id. Agent resources use this id to resolve the configured parser.
				</div>

				<div class="parsercfg-grid">
					<div class="parsercfg-field">
						<label>Parser id</label>
						<input type="text" name="id" placeholder="docling_default" autocomplete="off">
					</div>

					<div class="parsercfg-field">
						<label>Name</label>
						<input type="text" name="name" placeholder="Docling Default Parser" autocomplete="off">
					</div>

					<div class="parsercfg-field">
						<label>Connection</label>
						<select name="connection"><option value="">Loading connections...</option></select>
						<div class="parsercfg-hint parsercfg-inline-hint" data-role="connectionhint">Connections contain endpoint, authentication header and secret.</div>
					</div>

					<div class="parsercfg-field">
						<label>Driver</label>
						<select name="driver"><option value="">Loading drivers...</option></select>
					</div>

					<input type="hidden" name="model" value="default">

					<div class="parsercfg-field">
						<label>Content type</label>
						<input type="text" name="contentType" placeholder="application/x-agent-content-json" autocomplete="off">
					</div>

					<div class="parsercfg-field">
						<label>Supported types</label>
						<textarea name="supportedTypes" spellcheck="false" placeholder="file&#10;text&#10;stream"></textarea>
						<div class="parsercfg-hint parsercfg-inline-hint">One type per line. File-based parser backends usually use only <span class="mono">file</span>.</div>
					</div>

					<div class="parsercfg-field parsercfg-field-row">
						<div>
							<label>Priority</label>
							<input type="text" name="priority" placeholder="45" autocomplete="off">
						</div>
						<div>
							<label>Max bytes</label>
							<input type="text" name="maxBytes" placeholder="0" autocomplete="off">
						</div>
					</div>

					<div class="parsercfg-field">
						<label>Multipart file field</label>
						<input type="text" name="fileField" placeholder="file" autocomplete="off">
					</div>

					<div class="parsercfg-field parsercfg-field-row">
						<div>
							<label>Timeout seconds</label>
							<input type="text" name="timeoutSeconds" placeholder="90" autocomplete="off">
						</div>
						<div>
							<label>Connect timeout seconds</label>
							<input type="text" name="connectTimeoutSeconds" placeholder="20" autocomplete="off">
						</div>
					</div>

					<div class="parsercfg-field">
						<label>Advanced options JSON</label>
						<textarea name="options" spellcheck="false" placeholder="{&#10;}"></textarea>
						<div class="parsercfg-hint parsercfg-inline-hint">No endpoint, secret or auth header here. Those belong to the selected connection.</div>
					</div>

					<div class="parsercfg-field parsercfg-field-checkbox">
						<label class="parsercfg-checkbox"><input type="checkbox" name="enabled" checked><span>Enabled</span></label>
					</div>
				</div>

				<div data-role="formfeedback" class="parsercfg-form-feedback" style="display:none"></div>

				<div class="parsercfg-actions">
					<button type="submit" class="primary">Save parser</button>
					<button type="button" data-role="delete" disabled>Delete parser</button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
.parser-config-admin{background:#fff;border:1px solid #d6d6d6;padding:16px;border-radius:4px;max-width:100%;font-family:Arial,sans-serif;color:#333}.parser-config-admin h3{margin-top:0;margin-bottom:12px;font-size:1.1em}.parser-config-admin h4{margin-top:0;margin-bottom:10px;font-size:1em}.parsercfg-meta{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:13px;color:#555}.mono{font-family:Consolas,monospace}.parsercfg-loading{display:none;color:#666;font-style:italic}.parsercfg-layout{display:grid;grid-template-columns:minmax(720px,1fr) minmax(380px,520px);gap:16px;align-items:start}.parsercfg-listbox,.parsercfg-formbox{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:12px}.parsercfg-toolbar{display:flex;gap:8px;margin-bottom:10px}.parsercfg-toolbar button,.parsercfg-actions button{border:1px solid #c9c9c9;background:#f1f1f1;color:#333;border-radius:6px;padding:8px 12px;cursor:pointer}.parsercfg-toolbar button:hover,.parsercfg-actions button:hover{background:#e8e8e8}.parsercfg-actions .primary{background:#eaf3ff;border-color:#aac6ea}.parsercfg-actions .primary:hover{background:#dcecff}.parsercfg-actions button[disabled]{opacity:.5;cursor:not-allowed}.parsercfg-table{width:100%;border-collapse:collapse;background:#fff}.parsercfg-table th,.parsercfg-table td{padding:8px 10px;border-bottom:1px solid #e0e0e0;vertical-align:middle;text-align:left;font-size:13px}.parsercfg-table th{background:#f5f5f5;font-weight:600;border-bottom:2px solid #cfcfcf}.parsercfg-table tr.selected td{background:#eef5ff}.parsercfg-table td.id-col,.parsercfg-table td.connection-col,.parsercfg-table td.driver-col,.parsercfg-table td.option-col{font-family:Consolas,monospace;font-size:12px}.parsercfg-edit-btn{border:1px solid #c9c9c9;background:#f1f1f1;border-radius:6px;padding:5px 8px;cursor:pointer;font-size:12px}.parsercfg-edit-btn:hover{background:#e8e8e8}.badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ccc;background:#f6f6f6;color:#333;font-size:12px;white-space:nowrap}.badge.ok{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.badge.off{border-color:#d7c17a;background:#fff8df;color:#876c11}.badge.warn{border-color:#e0a56b;background:#fff4e8;color:#8a4f12}.parsercfg-hint{margin-bottom:12px;font-size:12px;color:#666}.parsercfg-inline-hint{margin-top:6px;margin-bottom:0}.parsercfg-grid{display:grid;grid-template-columns:1fr;gap:12px}.parsercfg-field label{display:block;font-weight:600;margin-bottom:6px;font-size:13px}.parsercfg-field input[type=text],.parsercfg-field select,.parsercfg-field textarea{width:100%;box-sizing:border-box;border:1px solid #cfcfcf;border-radius:6px;padding:8px 10px;background:#fff;color:#333}.parsercfg-field textarea{min-height:110px;font-family:Consolas,monospace;font-size:12px;resize:vertical}.parsercfg-field input[readonly]{background:#f6f6f6;color:#666}.parsercfg-field-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.parsercfg-field-checkbox{padding-top:4px}.parsercfg-checkbox{display:inline-flex;align-items:center;gap:8px;font-weight:600}.parsercfg-form-feedback{margin-top:14px;border:1px solid #ddd;border-radius:6px;padding:9px 11px;font-size:13px;line-height:1.4}.parsercfg-form-feedback.success{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.parsercfg-form-feedback.error{border-color:#d88;background:#fff5f5;color:#a33}.parsercfg-actions{display:flex;gap:8px;margin-top:14px}@media (max-width:1200px){.parsercfg-layout{grid-template-columns:1fr}}@media (max-width:620px){.parsercfg-field-row{grid-template-columns:1fr}}
</style>

<script>
(function() {
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
			contentType: root.querySelector("input[name='contentType']"),
			supportedTypes: root.querySelector("textarea[name='supportedTypes']"),
			priority: root.querySelector("input[name='priority']"),
			fileField: root.querySelector("input[name='fileField']"),
			timeoutSeconds: root.querySelector("input[name='timeoutSeconds']"),
			connectTimeoutSeconds: root.querySelector("input[name='connectTimeoutSeconds']"),
			maxBytes: root.querySelector("input[name='maxBytes']"),
			options: root.querySelector("textarea[name='options']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		const state = {parsers: [], connections: [], drivers: [], selectedId: ""};
		const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));
		const normalizeKey = s => String(s ?? "").trim().toLowerCase().replace(/[^a-z0-9._-]+/g, "");
		const findParser = id => state.parsers.find(item => String(item.id || "") === String(id || "")) || null;
		const findConnection = id => state.connections.find(item => String(item.id || "") === String(id || "")) || null;
		const findDriver = driver => state.drivers.find(item => String(item.driver || "") === String(driver || "")) || null;

		function setLoading(active) { refs.loading.style.display = active ? "block" : "none"; }
		function setLastUpdate(ts) { refs.lastupdate.textContent = ts || "-"; }
		function showFeedback(message, type) { refs.formfeedback.style.display = "block"; refs.formfeedback.className = "parsercfg-form-feedback " + (type === "error" ? "error" : "success"); refs.formfeedback.textContent = message; }
		function clearFeedback() { refs.formfeedback.style.display = "none"; refs.formfeedback.className = "parsercfg-form-feedback"; refs.formfeedback.textContent = ""; }
		function formatOptions(options) { const clean = Object.assign({}, options || {}); ["contentType","supportedTypes","priority","fileField","timeoutSeconds","connectTimeoutSeconds","maxBytes"].forEach(k => delete clean[k]); return Object.keys(clean).length ? JSON.stringify(clean, null, 2) : "{\n}"; }

		function connectionLabel(id) {
			const c = findConnection(id);
			if (!c) return id || "";

			let label = c.name || c.id || "";
			if (c.type) label += " (" + c.type + ")";

			return label;
		}

		function updateConnectionHint() {
			const c = findConnection(refs.connection.value);
			refs.connectionhint.textContent = c ? "Type: " + (c.type || "unknown") + ". Driver: " + (c.driver || "unknown") + ". Auth header: " + (c.authHeaderName || "driver default") + ". Base URL: " + (c.baseUrl || "not set") + "." + (!c.enabled ? " This connection is currently disabled." : "") : "Connections contain endpoint, authentication header and secret.";
		}

		function setEditMode(editing) {
			refs.id.readOnly = editing;
			refs.deleteBtn.disabled = !editing;
			refs.legend.textContent = editing ? "Edit parser" : "Create parser";
			refs.idhint.textContent = editing ? "Technical parser id is fixed for existing entries. Create a new entry if you need another key." : "Technical parser id. Agent resources use this id to resolve the configured parser.";
		}

		function renderConnectionOptions(selected) {
			refs.connection.innerHTML = "";

			const empty = document.createElement("option");
			empty.value = "";
			empty.textContent = state.connections.length > 0 ? "Select connection" : "No connections configured";
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
			empty.textContent = state.drivers.length > 0 ? "Select driver" : "No parser drivers available";
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

			if (force || !refs.model.value) refs.model.value = defaults.model || "default";

			if (force) {
				refs.contentType.value = options.contentType ?? "";
				refs.supportedTypes.value = Array.isArray(options.supportedTypes) ? options.supportedTypes.join("\n") : "";
				refs.priority.value = options.priority ?? "";
				refs.fileField.value = options.fileField ?? "";
				refs.timeoutSeconds.value = options.timeoutSeconds ?? "";
				refs.connectTimeoutSeconds.value = options.connectTimeoutSeconds ?? "";
				refs.maxBytes.value = options.maxBytes ?? "";
				refs.options.value = formatOptions(options);
			}
		}

		function resetForm() {
			refs.form.reset();
			refs.id.value = refs.name.value = refs.connection.value = refs.driver.value = "";
			refs.model.value = "default";
			refs.contentType.value = refs.supportedTypes.value = refs.priority.value = refs.fileField.value = refs.timeoutSeconds.value = refs.connectTimeoutSeconds.value = refs.maxBytes.value = "";
			refs.options.value = "{\n}";
			refs.enabled.checked = true;
			state.selectedId = "";
			setEditMode(false);
			updateConnectionHint();
			highlightSelection();
		}

		function fillForm(parser) {
			if (!parser) {
				resetForm();
				return;
			}

			refs.id.value = parser.id || "";
			refs.name.value = parser.name || "";
			refs.connection.value = parser.connection || "";
			refs.driver.value = parser.driver || "";
			refs.model.value = parser.model || "default";
			refs.contentType.value = parser.contentType || "";
			refs.supportedTypes.value = parser.supportedTypesText || "";
			refs.priority.value = parser.priority || "";
			refs.fileField.value = parser.fileField || "";
			refs.timeoutSeconds.value = parser.timeoutSeconds || "";
			refs.connectTimeoutSeconds.value = parser.connectTimeoutSeconds || "";
			refs.maxBytes.value = parser.maxBytes || "";
			refs.options.value = formatOptions(parser.options || {});
			refs.enabled.checked = !!parser.enabled;

			state.selectedId = parser.id || "";
			setEditMode(true);
			updateConnectionHint();
			highlightSelection();
		}

		function statusBadge(row) {
			if (!row.enabled) return "<span class='badge off'>disabled</span>";
			if (!row.connectionEnabled) return "<span class='badge warn'>connection off</span>";
			return "<span class='badge ok'>enabled</span>";
		}

		function renderRows() {
			refs.tbody.innerHTML = "";
			if (!state.parsers.length) {
				refs.tbody.innerHTML = "<tr><td colspan='8' class='mono'>No parsers configured.</td></tr>";
				return;
			}

			state.parsers.forEach(parser => {
				const tr = document.createElement("tr");
				tr.setAttribute("data-id", parser.id || "");
				tr.innerHTML =
					"<td class='id-col'>" + esc(parser.id) + "</td>" +
					"<td>" + esc(parser.name) + "</td>" +
					"<td class='connection-col' title='" + esc(connectionLabel(parser.connection)) + "'>" + esc(parser.connection) + "<br><span style='color:#777'>" + esc(parser.connectionType || "") + "</span></td>" +
					"<td class='driver-col'>" + esc(parser.driverLabel || parser.driver) + "</td>" +
					"<td class='option-col'>" + esc((parser.supportedTypes || []).join(", ")) + "</td>" +
					"<td class='option-col'>" + esc(parser.priority || "") + "</td>" +
					"<td>" + statusBadge(parser) + "</td>" +
					"<td><button type='button' class='parsercfg-edit-btn' data-action='edit' data-id='" + esc(parser.id) + "'>Edit</button></td>";
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
					showFeedback("The server response could not be read.", "error");
					return null;
				}

				setLastUpdate(json.timestamp || "");

				if (json.status !== "ok") {
					showFeedback(json.message || "The request could not be completed.", "error");
					return null;
				}

				return json;
			} catch (e) {
				showFeedback("The request failed. Please try again.", "error");
				return null;
			} finally {
				setLoading(false);
			}
		}

		async function loadList(preselectId) {
			const json = await callApi({action: "list"});
			if (!json) {
				refs.tbody.innerHTML = "<tr><td colspan='8' class='mono'>Parsers could not be loaded.</td></tr>";
				return;
			}

			state.connections = json.data && Array.isArray(json.data.connections) ? json.data.connections : [];
			state.drivers = json.data && Array.isArray(json.data.drivers) ? json.data.drivers : [];
			state.parsers = json.data && Array.isArray(json.data.parsers) ? json.data.parsers : [];

			renderConnectionOptions(refs.connection.value || "");
			renderDriverOptions(refs.driver.value || "");
			renderRows();

			const selected = findParser(preselectId || state.selectedId);
			if (selected) fillForm(selected);
			else if (!state.parsers.length) resetForm();
		}

		function readOptionsJson() {
			const raw = String(refs.options.value || "").trim();
			if (!raw) return "{}";

			try {
				const parsed = JSON.parse(raw);
				if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
					showFeedback("Advanced options must be a JSON object.", "error");
					return null;
				}
				return JSON.stringify(parsed);
			} catch (e) {
				showFeedback("Advanced options must be valid JSON.", "error");
				return null;
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
			const model = String(refs.model.value || "").trim() || "default";

			refs.id.value = id;
			refs.model.value = model;

			if (!id || !name || !connection || !driver) {
				showFeedback("Parser id, name, connection and driver are required.", "error");
				return;
			}

			const json = await callApi({
				action: "save",
				id,
				name,
				connection,
				driver,
				model,
				contentType: refs.contentType.value.trim(),
				supportedTypes: refs.supportedTypes.value.trim(),
				priority: refs.priority.value.trim(),
				fileField: refs.fileField.value.trim(),
				timeoutSeconds: refs.timeoutSeconds.value.trim(),
				connectTimeoutSeconds: refs.connectTimeoutSeconds.value.trim(),
				maxBytes: refs.maxBytes.value.trim(),
				options,
				enabled: refs.enabled.checked ? "1" : "0"
			});

			if (!json) return;

			const parser = json.data && json.data.parser ? json.data.parser : null;
			showFeedback("Parser saved.", "success");
			await loadList(parser && parser.id ? parser.id : id);
		}

		async function removeCurrent() {
			clearFeedback();

			const id = String(state.selectedId || refs.id.value || "").trim();
			if (!id) {
				showFeedback("No parser selected.", "error");
				return;
			}

			if (!window.confirm("Delete parser '" + id + "'?")) return;

			const json = await callApi({action:"remove", id});
			if (!json) return;

			showFeedback("Parser deleted.", "success");
			resetForm();
			await loadList();
		}

		refs.form.addEventListener("submit", e => { e.preventDefault(); saveCurrent(); });
		refs.newBtn.addEventListener("click", () => { clearFeedback(); resetForm(); });
		refs.reloadBtn.addEventListener("click", () => { clearFeedback(); loadList(state.selectedId || ""); });
		refs.deleteBtn.addEventListener("click", removeCurrent);
		refs.connection.addEventListener("change", updateConnectionHint);
		refs.driver.addEventListener("change", () => applyDriverDefaults(true));
		refs.tbody.addEventListener("click", e => {
			const btn = e.target.closest("button[data-action='edit']");
			if (!btn) return;
			clearFeedback();
			fillForm(findParser(btn.getAttribute("data-id")));
		});

		loadList();
	}

	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
</script>
