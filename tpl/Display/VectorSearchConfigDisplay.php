<?php
$this->loadBricks('Administration');
$mbUiText = is_array($this->_['bricks']['missionbay_admin'] ?? null) ? $this->_['bricks']['missionbay_admin'] : [];
$mbText = static fn(string $key, string $fallback): string => trim((string)($mbUiText[$key] ?? '')) !== '' ? (string)$mbUiText[$key] : $fallback;
$mbTextEsc = static fn(string $key, string $fallback): string => htmlspecialchars($mbText($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="vectorsearch-config-admin">
	<h3><?php echo $mbTextEsc('vector_search_services', 'Vector Search Services'); ?></h3>

	<div class="vectorsearchcfg-meta">
		<div><strong><?php echo $mbTextEsc('settings_group', 'Settings group:'); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong><?php echo $mbTextEsc('connection_group', 'Connection group:'); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong><?php echo $mbTextEsc('last_update', 'Last update:'); ?></strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="vectorsearchcfg-loading"><?php echo $mbTextEsc('please_wait', 'Please wait...'); ?></div>
	</div>

	<div class="vectorsearchcfg-hint">
		Vector-search services configure similarity-search adapters. The selected connection is the only source for endpoint, authentication header and secret.
	</div>

	<div class="vectorsearchcfg-layout">
		<div class="vectorsearchcfg-listbox">
			<div class="vectorsearchcfg-toolbar">
				<button type="button" data-role="new"><?php echo $mbTextEsc('new_vector_search', 'New vector search'); ?></button>
				<button type="button" data-role="reload"><?php echo $mbTextEsc('reload', 'Reload'); ?></button>
			</div>

			<table class="vectorsearchcfg-table">
				<thead>
					<tr>
						<th><?php echo $mbTextEsc('id', 'ID'); ?></th>
						<th><?php echo $mbTextEsc('name', 'Name'); ?></th>
						<th><?php echo $mbTextEsc('connection', 'Connection'); ?></th>
						<th><?php echo $mbTextEsc('driver', 'Driver'); ?></th>
						<th><?php echo $mbTextEsc('collection', 'Collection'); ?></th>
						<th><?php echo $mbTextEsc('status', 'Status'); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="7" class="mono"><?php echo $mbTextEsc('loading', 'Loading...'); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="vectorsearchcfg-formbox">
			<form data-role="form">
				<h4 data-role="legend"><?php echo $mbTextEsc('create_vector_search', 'Create vector search'); ?></h4>

				<div class="vectorsearchcfg-hint" data-role="idhint">
					Technical vector-search id. Agent resources use this id to resolve the configured vector search.
				</div>

				<div class="vectorsearchcfg-grid">
					<div class="vectorsearchcfg-field">
						<label><?php echo $mbTextEsc('vector_search_id', 'Vector-search id'); ?></label>
						<input type="text" name="id" placeholder="qualitus_qdrant" autocomplete="off">
					</div>

					<div class="vectorsearchcfg-field">
						<label><?php echo $mbTextEsc('name', 'Name'); ?></label>
						<input type="text" name="name" placeholder="Qualitus Qdrant Search" autocomplete="off">
					</div>

					<div class="vectorsearchcfg-field">
						<label><?php echo $mbTextEsc('connection', 'Connection'); ?></label>
						<select name="connection"><option value=""><?php echo $mbTextEsc('loading_connections', 'Loading connections...'); ?></option></select>
						<div class="vectorsearchcfg-hint vectorsearchcfg-inline-hint"><?php echo $mbTextEsc('endpoint_and_credentials_are_configured_in_the_selected_connection', 'Endpoint and credentials are configured in the selected connection.'); ?></div>
					</div>

					<div class="vectorsearchcfg-field">
						<label><?php echo $mbTextEsc('driver', 'Driver'); ?></label>
						<select name="driver"><option value=""><?php echo $mbTextEsc('loading_drivers', 'Loading drivers...'); ?></option></select>
					</div>

					<input type="hidden" name="model" value="qdrant">

					<div class="vectorsearchcfg-field">
						<label><?php echo $mbTextEsc('collection', 'Collection'); ?></label>
						<input type="text" name="collection" placeholder="courses" autocomplete="off">
					</div>


					<div class="vectorsearchcfg-field">
						<label><?php echo $mbTextEsc('advanced_options_json', 'Advanced options JSON'); ?></label>
						<textarea name="options" spellcheck="false" placeholder="{&#10;}"></textarea>
						<div class="vectorsearchcfg-hint vectorsearchcfg-inline-hint"><?php echo $mbTextEsc('endpoint_secret_and_authentication_header_belong_exclusively_to_the_selected_connection', 'Endpoint, secret and authentication header belong exclusively to the selected connection.'); ?></div>
					</div>

					<div class="vectorsearchcfg-field vectorsearchcfg-field-checkbox">
						<label class="vectorsearchcfg-checkbox"><input type="checkbox" name="enabled" checked><span><?php echo $mbTextEsc('enabled', 'Enabled'); ?></span></label>
					</div>
				</div>

				<div data-role="formfeedback" class="vectorsearchcfg-form-feedback" style="display:none"></div>

				<div class="vectorsearchcfg-actions">
					<button type="submit" class="primary"><?php echo $mbTextEsc('save_vector_search', 'Save vector search'); ?></button>
					<button type="button" data-role="delete" disabled><?php echo $mbTextEsc('delete_vector_search', 'Delete vector search'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
.vectorsearch-config-admin{background:#fff;border:1px solid #d6d6d6;padding:16px;border-radius:4px;max-width:100%;font-family:Arial,sans-serif;color:#333}.vectorsearch-config-admin h3{margin-top:0;margin-bottom:12px;font-size:1.1em}.vectorsearch-config-admin h4{margin-top:0;margin-bottom:10px;font-size:1em}.vectorsearchcfg-meta{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:13px;color:#555}.mono{font-family:Consolas,monospace}.vectorsearchcfg-loading{display:none;color:#666;font-style:italic}.vectorsearchcfg-layout{display:grid;grid-template-columns:minmax(720px,1fr) minmax(380px,520px);gap:16px;align-items:start}.vectorsearchcfg-listbox,.vectorsearchcfg-formbox{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:12px}.vectorsearchcfg-toolbar{display:flex;gap:8px;margin-bottom:10px}.vectorsearchcfg-toolbar button,.vectorsearchcfg-actions button{border:1px solid #c9c9c9;background:#f1f1f1;color:#333;border-radius:6px;padding:8px 12px;cursor:pointer}.vectorsearchcfg-toolbar button:hover,.vectorsearchcfg-actions button:hover{background:#e8e8e8}.vectorsearchcfg-actions .primary{background:#eaf3ff;border-color:#aac6ea}.vectorsearchcfg-actions .primary:hover{background:#dcecff}.vectorsearchcfg-actions button[disabled]{opacity:.5;cursor:not-allowed}.vectorsearchcfg-table{width:100%;border-collapse:collapse;background:#fff}.vectorsearchcfg-table th,.vectorsearchcfg-table td{padding:8px 10px;border-bottom:1px solid #e0e0e0;vertical-align:middle;text-align:left;font-size:13px}.vectorsearchcfg-table th{background:#f5f5f5;font-weight:600;border-bottom:2px solid #cfcfcf}.vectorsearchcfg-table tr.selected td{background:#eef5ff}.vectorsearchcfg-table td.id-col,.vectorsearchcfg-table td.connection-col,.vectorsearchcfg-table td.driver-col,.vectorsearchcfg-table td.option-col{font-family:Consolas,monospace;font-size:12px}.vectorsearchcfg-edit-btn{border:1px solid #c9c9c9;background:#f1f1f1;border-radius:6px;padding:5px 8px;cursor:pointer;font-size:12px}.vectorsearchcfg-edit-btn:hover{background:#e8e8e8}.badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ccc;background:#f6f6f6;color:#333;font-size:12px;white-space:nowrap}.badge.ok{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.badge.off{border-color:#d7c17a;background:#fff8df;color:#876c11}.badge.warn{border-color:#e0a56b;background:#fff4e8;color:#8a4f12}.vectorsearchcfg-hint{margin-bottom:12px;font-size:12px;color:#666}.vectorsearchcfg-inline-hint{margin-top:6px;margin-bottom:0}.vectorsearchcfg-grid{display:grid;grid-template-columns:1fr;gap:12px}.vectorsearchcfg-field label{display:block;font-weight:600;margin-bottom:6px;font-size:13px}.vectorsearchcfg-field input[type=text],.vectorsearchcfg-field select,.vectorsearchcfg-field textarea{width:100%;box-sizing:border-box;border:1px solid #cfcfcf;border-radius:6px;padding:8px 10px;background:#fff;color:#333}.vectorsearchcfg-field textarea{min-height:150px;font-family:Consolas,monospace;font-size:12px;resize:vertical}.vectorsearchcfg-field input[readonly]{background:#f6f6f6;color:#666}.vectorsearchcfg-field-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.vectorsearchcfg-field-checkbox{padding-top:4px}.vectorsearchcfg-checkbox{display:inline-flex;align-items:center;gap:8px;font-weight:600}.vectorsearchcfg-form-feedback{margin-top:14px;border:1px solid #ddd;border-radius:6px;padding:9px 11px;font-size:13px;line-height:1.4}.vectorsearchcfg-form-feedback.success{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.vectorsearchcfg-form-feedback.error{border-color:#d88;background:#fff5f5;color:#a33}.vectorsearchcfg-actions{display:flex;gap:8px;margin-top:14px}@media (max-width:1200px){.vectorsearchcfg-layout{grid-template-columns:1fr}}@media (max-width:620px){.vectorsearchcfg-field-row{grid-template-columns:1fr}}
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
			newBtn: root.querySelector("[data-role='new']"),
			reloadBtn: root.querySelector("[data-role='reload']"),
			deleteBtn: root.querySelector("[data-role='delete']"),
			id: root.querySelector("input[name='id']"),
			name: root.querySelector("input[name='name']"),
			connection: root.querySelector("select[name='connection']"),
			driver: root.querySelector("select[name='driver']"),
			model: root.querySelector("input[name='model']"),
			collection: root.querySelector("input[name='collection']"),
			options: root.querySelector("textarea[name='options']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		const state = {vectorsearches: [], connections: [], drivers: [], selectedId: ""};
		const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));
		const normalizeKey = s => String(s ?? "").trim().toLowerCase().replace(/[^a-z0-9._-]+/g, "");
		const findVectorSearch = id => state.vectorsearches.find(item => String(item.id || "") === String(id || "")) || null;
		const findConnection = id => state.connections.find(item => String(item.id || "") === String(id || "")) || null;
		const findDriver = driver => state.drivers.find(item => String(item.driver || "") === String(driver || "")) || null;

		function setLoading(active) { refs.loading.style.display = active ? "block" : "none"; }
		function setLastUpdate(ts) { refs.lastupdate.textContent = ts || "-"; }
		function showFeedback(message, type) { refs.formfeedback.style.display = "block"; refs.formfeedback.className = "vectorsearchcfg-form-feedback " + (type === "error" ? "error" : "success"); refs.formfeedback.textContent = message; }
		function clearFeedback() { refs.formfeedback.style.display = "none"; refs.formfeedback.className = "vectorsearchcfg-form-feedback"; refs.formfeedback.textContent = ""; }
		function formatOptions(options) { const clean = Object.assign({}, options || {}); ["collection"].forEach(k => delete clean[k]); return Object.keys(clean).length ? JSON.stringify(clean, null, 2) : "{\n}"; }

		function connectionLabel(id) {
			const c = findConnection(id);
			if (!c) return id || "";

			let label = c.name || c.id || "";
			if (c.type) label += " (" + c.type + ")";

			return label;
		}

		function setEditMode(editing) {
			refs.id.readOnly = editing;
			refs.deleteBtn.disabled = !editing;
			refs.legend.textContent = editing ? "Edit vector search" : mbText('create_vector_search', 'Create vector search');
			refs.idhint.textContent = editing ? "Technical vector-search id is fixed for existing entries. Create a new entry if you need another key." : "Technical vector-search id. Agent resources use this id to resolve the configured vector search.";
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
		}

		function renderDriverOptions(selected) {
			refs.driver.innerHTML = "";

			const empty = document.createElement("option");
			empty.value = "";
			empty.textContent = state.drivers.length > 0 ? mbText('select_driver', 'Select driver') : "No vector-search drivers available";
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
				refs.collection.value = options.collection || "";
				refs.options.value = formatOptions(options);
			}
		}

		function resetForm() {
			refs.form.reset();
			refs.id.value = refs.name.value = refs.connection.value = refs.driver.value = "";
			refs.model.value = "qdrant";
			refs.collection.value = "";
			refs.options.value = "{\n}";
			refs.enabled.checked = true;
			state.selectedId = "";
			setEditMode(false);
			highlightSelection();
		}

		function fillForm(v) {
			if (!v) {
				resetForm();
				return;
			}

			refs.id.value = v.id || "";
			refs.name.value = v.name || "";
			refs.connection.value = v.connection || "";
			refs.driver.value = v.driver || "";
			refs.model.value = v.model || "qdrant";
			refs.collection.value = v.collection || "";
			refs.options.value = formatOptions(v.options || {});
			refs.enabled.checked = !!v.enabled;

			state.selectedId = v.id || "";
			setEditMode(true);
			highlightSelection();
		}

		function statusBadge(row) {
			if (!row.enabled) return "<span class='badge off'><?php echo $mbTextEsc('disabled', 'disabled'); ?></span>";
			if (!row.connectionEnabled) return "<span class='badge warn'><?php echo $mbTextEsc('connection_off', 'connection off'); ?></span>";
			return "<span class='badge ok'><?php echo $mbTextEsc('enabled_2', 'enabled'); ?></span>";
		}

		function renderRows() {
			refs.tbody.innerHTML = "";
			if (!state.vectorsearches.length) {
				refs.tbody.innerHTML = "<tr><td colspan='7' class='mono'><?php echo $mbTextEsc('no_vector_searches_configured', 'No vector searches configured.'); ?></td></tr>";
				return;
			}

			state.vectorsearches.forEach(v => {
				const tr = document.createElement("tr");
				tr.setAttribute("data-id", v.id || "");
				tr.innerHTML =
					"<td class='id-col'>" + esc(v.id) + "</td>" +
					"<td>" + esc(v.name) + "</td>" +
					"<td class='connection-col' title='" + esc(connectionLabel(v.connection)) + "'>" + esc(v.connection) + "<br><span style='color:#777'>" + esc(v.connectionType || "") + "</span></td>" +
					"<td class='driver-col'>" + esc(v.driverLabel || v.driver) + "</td>" +
					"<td class='option-col'>" + esc(v.collection || "-") + "</td>" +
					"<td>" + statusBadge(v) + "</td>" +
					"<td><button type='button' class='vectorsearchcfg-edit-btn' data-action='edit' data-id='" + esc(v.id) + "'><?php echo $mbTextEsc('edit', 'Edit'); ?></button></td>";
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
				refs.tbody.innerHTML = "<tr><td colspan='7' class='mono'><?php echo $mbTextEsc('vector_searches_could_not_be_loaded', 'Vector searches could not be loaded.'); ?></td></tr>";
				return;
			}

			state.connections = json.data && Array.isArray(json.data.connections) ? json.data.connections : [];
			state.drivers = json.data && Array.isArray(json.data.drivers) ? json.data.drivers : [];
			state.vectorsearches = json.data && Array.isArray(json.data.vectorsearches) ? json.data.vectorsearches : [];

			renderConnectionOptions(refs.connection.value || "");
			renderDriverOptions(refs.driver.value || "");
			renderRows();

			const selected = findVectorSearch(preselectId || state.selectedId);
			if (selected) fillForm(selected);
			else if (!state.vectorsearches.length) resetForm();
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
				showFeedback(mbText('vector_search_id_name_connection_and_driver_are_required', 'Vector-search id, name, connection and driver are required.'), "error");
				return;
			}

			const json = await callApi({
				action: "save",
				id,
				name,
				connection,
				driver,
				model,
				collection: refs.collection.value.trim(),
				options,
				enabled: refs.enabled.checked ? "1" : "0"
			});

			if (!json) return;

			const vectorsearch = json.data && json.data.vectorsearch ? json.data.vectorsearch : null;
			showFeedback(mbText('vector_search_saved', 'Vector search saved.'), "success");
			await loadList(vectorsearch && vectorsearch.id ? vectorsearch.id : id);
		}

		async function removeCurrent() {
			clearFeedback();

			const id = String(state.selectedId || refs.id.value || "").trim();
			if (!id) {
				showFeedback(mbText('no_vector_search_selected', 'No vector search selected.'), "error");
				return;
			}

			if (!window.confirm(mbText('delete_vector_search_confirm', "Delete vector search '{id}'?", {id}))) return;

			const json = await callApi({action:"remove", id});
			if (!json) return;

			showFeedback(mbText('vector_search_deleted', 'Vector search deleted.'), "success");
			resetForm();
			await loadList();
		}

		refs.form.addEventListener("submit", e => { e.preventDefault(); saveCurrent(); });
		refs.newBtn.addEventListener("click", () => { clearFeedback(); resetForm(); });
		refs.reloadBtn.addEventListener("click", () => { clearFeedback(); loadList(state.selectedId || ""); });
		refs.deleteBtn.addEventListener("click", removeCurrent);
		refs.driver.addEventListener("change", () => applyDriverDefaults(true));
		refs.tbody.addEventListener("click", e => {
			const btn = e.target.closest("button[data-action='edit']");
			if (!btn) return;
			clearFeedback();
			fillForm(findVectorSearch(btn.getAttribute("data-id")));
		});

		loadList();
	}

	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
</script>
