<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="ai-provider-admin">
	<h3>AI provider settings</h3>

	<div class="apad-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">–</span></div>
		<div data-role="loading" class="apad-loading">Please wait…</div>
	</div>

	<div data-role="output" class="apad-output" style="display:none"></div>

	<div class="apad-layout">
		<div class="apad-listbox">
			<div class="apad-toolbar">
				<button type="button" data-role="new">New provider</button>
				<button type="button" data-role="reload">Reload</button>
			</div>

			<table class="apad-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Label</th>
						<th>Driver</th>
						<th>Key</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="6" class="mono">Loading…</td></tr>
				</tbody>
			</table>
		</div>

		<div class="apad-formbox">
			<form data-role="form">
				<h4 data-role="legend">Create provider</h4>

				<div class="apad-hint" data-role="namehint">
					Technical settings name. This becomes the fixed lookup key for later agent usage.
				</div>

				<div class="apad-grid">
					<div class="apad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name">Settings name</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name"
							name="name"
							placeholder="openai"
							autocomplete="off"
						>
					</div>

					<div class="apad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-label">Label</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-label"
							name="label"
							placeholder="OpenAI"
							autocomplete="off"
						>
					</div>

					<div class="apad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver">Driver</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver"
							name="driver"
							list="<?php echo htmlspecialchars((string)$this->_['driverListId'], ENT_QUOTES); ?>"
							placeholder="openai"
							autocomplete="off"
						>
					</div>

					<div class="apad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-endpoint">Endpoint</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-endpoint"
							name="endpoint"
							placeholder="https://api.openai.com"
							autocomplete="off"
						>
					</div>

					<div class="apad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-keytype">Key type</label>
						<select
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-keytype"
							name="keytype"
						>
							<?php foreach(($this->_['keyTypeSuggestions'] ?? []) as $keyType): ?>
								<option value="<?php echo htmlspecialchars((string)$keyType, ENT_QUOTES); ?>"><?php echo htmlspecialchars((string)$keyType, ENT_QUOTES); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="apad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-keyvalue" data-role="keyvaluelabel">Key value</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-keyvalue"
							name="keyvalue"
							placeholder="OPENAI_API_KEY"
							autocomplete="off"
						>
						<div class="apad-hint apad-inline-hint" data-role="keytypehint">
							For <span class="mono">env</span>, enter the environment variable name, e.g. <span class="mono">OPENAI_API_KEY</span>.
						</div>
					</div>

					<div class="apad-field apad-field-checkbox">
						<label class="apad-checkbox">
							<input type="checkbox" name="enabled" checked>
							<span>Enabled</span>
						</label>
					</div>
				</div>

				<div class="apad-actions">
					<button type="submit" class="primary">Save provider</button>
					<button type="button" data-role="delete" disabled>Delete provider</button>
				</div>
			</form>
		</div>
	</div>

	<datalist id="<?php echo htmlspecialchars((string)$this->_['driverListId'], ENT_QUOTES); ?>">
		<?php foreach(($this->_['driverSuggestions'] ?? []) as $driver): ?>
			<option value="<?php echo htmlspecialchars((string)$driver, ENT_QUOTES); ?>"></option>
		<?php endforeach; ?>
	</datalist>
</div>

<style>
.ai-provider-admin {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.ai-provider-admin h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.ai-provider-admin h4 {
	margin-top: 0;
	margin-bottom: 10px;
	font-size: 1em;
}

.apad-meta {
	display: flex;
	gap: 16px;
	flex-wrap: wrap;
	align-items: center;
	margin-bottom: 10px;
	font-size: 13px;
	color: #555;
}

.mono {
	font-family: Consolas, monospace;
}

.apad-loading {
	display: none;
	color: #666;
	font-style: italic;
}

.apad-output {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 10px;
	font-family: Consolas, monospace;
	font-size: 12px;
	white-space: pre-wrap;
	max-height: 240px;
	overflow: auto;
	color: #444;
	margin-bottom: 12px;
}

.apad-output.error {
	border-color: #d88;
	background: #fff5f5;
	color: #a33;
}

.apad-output.success {
	border-color: #8d8;
	background: #f6fff6;
	color: #373;
}

.apad-layout {
	display: grid;
	grid-template-columns: minmax(420px, 1fr) minmax(360px, 460px);
	gap: 16px;
	align-items: start;
}

.apad-listbox,
.apad-formbox {
	border: 1px solid #ddd;
	border-radius: 4px;
	background: #fafafa;
	padding: 12px;
}

.apad-toolbar {
	display: flex;
	gap: 8px;
	margin-bottom: 10px;
}

.apad-toolbar button,
.apad-actions button {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	color: #333;
	border-radius: 6px;
	padding: 8px 12px;
	cursor: pointer;
}

.apad-toolbar button:hover,
.apad-actions button:hover {
	background: #e8e8e8;
}

.apad-actions .primary {
	background: #eaf3ff;
	border-color: #aac6ea;
}

.apad-actions .primary:hover {
	background: #dcecff;
}

.apad-actions button[disabled] {
	opacity: 0.5;
	cursor: not-allowed;
}

.apad-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
}

.apad-table th,
.apad-table td {
	padding: 8px 10px;
	border-bottom: 1px solid #e0e0e0;
	vertical-align: middle;
	text-align: left;
	font-size: 13px;
}

.apad-table th {
	background: #f5f5f5;
	font-weight: 600;
	border-bottom: 2px solid #cfcfcf;
}

.apad-table tr:hover td {
	background: #fafafa;
}

.apad-table tr.selected td {
	background: #eef5ff;
}

.apad-table td.name-col {
	font-family: Consolas, monospace;
	font-size: 12px;
	white-space: nowrap;
}

.apad-table td.endpoint-col {
	max-width: 280px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.apad-edit-btn {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	border-radius: 6px;
	padding: 5px 8px;
	cursor: pointer;
	font-size: 12px;
}

.apad-edit-btn:hover {
	background: #e8e8e8;
}

.badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid #ccc;
	background: #f6f6f6;
	color: #333;
	font-size: 12px;
	white-space: nowrap;
}

.badge.ok {
	border-color: #8d8;
	background: #f6fff6;
	color: #2d6b2d;
}

.badge.off {
	border-color: #d7c17a;
	background: #fff8df;
	color: #876c11;
}

.apad-hint {
	margin-bottom: 12px;
	font-size: 12px;
	color: #666;
}

.apad-inline-hint {
	margin-top: 6px;
	margin-bottom: 0;
}

.apad-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 12px;
}

.apad-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 6px;
	font-size: 13px;
}

.apad-field input[type="text"],
.apad-field select {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid #cfcfcf;
	border-radius: 6px;
	padding: 8px 10px;
	background: #fff;
	color: #333;
}

.apad-field input[readonly] {
	background: #f6f6f6;
	color: #666;
}

.apad-field-checkbox {
	padding-top: 4px;
}

.apad-checkbox {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
}

.apad-actions {
	display: flex;
	gap: 8px;
	margin-top: 14px;
}

@media (max-width: 1024px) {
	.apad-layout {
		grid-template-columns: 1fr;
	}
}
</style>

<script>
(function() {
	const instanceId = <?php echo json_encode((string)$this->_['instanceId']); ?>;
	const endpointBase = <?php echo json_encode((string)$this->_['endpoint']); ?>;
	const configGroup = <?php echo json_encode((string)$this->_['configGroup']); ?>;

	function init() {
		const root = document.getElementById(instanceId);
		if (!root || root.dataset.initialized === "1") {
			return;
		}

		root.dataset.initialized = "1";

		const refs = {
			loading: root.querySelector("[data-role='loading']"),
			lastupdate: root.querySelector("[data-role='lastupdate']"),
			output: root.querySelector("[data-role='output']"),
			tbody: root.querySelector("[data-role='tbody']"),
			form: root.querySelector("[data-role='form']"),
			legend: root.querySelector("[data-role='legend']"),
			namehint: root.querySelector("[data-role='namehint']"),
			keyvaluelabel: root.querySelector("[data-role='keyvaluelabel']"),
			keytypehint: root.querySelector("[data-role='keytypehint']"),
			newBtn: root.querySelector("[data-role='new']"),
			reloadBtn: root.querySelector("[data-role='reload']"),
			deleteBtn: root.querySelector("[data-role='delete']"),
			name: root.querySelector("input[name='name']"),
			label: root.querySelector("input[name='label']"),
			driver: root.querySelector("input[name='driver']"),
			endpoint: root.querySelector("input[name='endpoint']"),
			keytype: root.querySelector("select[name='keytype']"),
			keyvalue: root.querySelector("input[name='keyvalue']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		const state = {
			providers: [],
			selectedName: ""
		};

		function esc(s) {
			return String(s ?? "").replace(/[&<>"']/g, function(c) {
				return {
					"&": "&amp;",
					"<": "&lt;",
					">": "&gt;",
					'"': "&quot;",
					"'": "&#039;"
				}[c];
			});
		}

		function normalizeKey(s) {
			s = String(s ?? "").trim().toLowerCase();
			return s.replace(/[^a-z0-9._-]+/g, "");
		}

		function normalizeKeyType(s) {
			s = normalizeKey(s);
			if (s === "direct") {
				return "fixed";
			}
			return s;
		}

		function setLoading(active) {
			if (!refs.loading) {
				return;
			}

			refs.loading.style.display = active ? "block" : "none";
		}

		function setLastUpdate(ts) {
			if (!refs.lastupdate) {
				return;
			}

			refs.lastupdate.textContent = ts || "–";
		}

		function printOutput(obj, type) {
			if (!refs.output) {
				return;
			}

			refs.output.style.display = "block";
			refs.output.className = "apad-output";

			if (type === "error") {
				refs.output.classList.add("error");
			}

			if (type === "success") {
				refs.output.classList.add("success");
			}

			refs.output.textContent = typeof obj === "string" ? obj : JSON.stringify(obj, null, 2);
		}

		function clearOutput() {
			if (!refs.output) {
				return;
			}

			refs.output.style.display = "none";
			refs.output.className = "apad-output";
			refs.output.textContent = "";
		}

		function setEditMode(editing) {
			refs.name.readOnly = editing;
			refs.deleteBtn.disabled = !editing;

			if (editing) {
				refs.legend.textContent = "Edit provider";
				refs.namehint.textContent = "Technical settings name is fixed for existing entries. Create a new entry if you need another key.";
			} else {
				refs.legend.textContent = "Create provider";
				refs.namehint.textContent = "Technical settings name. This becomes the fixed lookup key for later agent usage.";
			}
		}

		function updateKeyTypeUi() {
			const keyType = normalizeKeyType(refs.keytype.value);

			if (keyType === "fixed") {
				refs.keyvaluelabel.textContent = "API key";
				refs.keyvalue.placeholder = "sk-...";
				refs.keytypehint.innerHTML =
					"For <span class='mono'>fixed</span>, enter the API key directly. The value will be stored in settings.";
				return;
			}

			refs.keyvaluelabel.textContent = "Key value";
			refs.keyvalue.placeholder = "OPENAI_API_KEY";
			refs.keytypehint.innerHTML =
				"For <span class='mono'>env</span>, enter the environment variable name, e.g. <span class='mono'>OPENAI_API_KEY</span>.";
		}

		function resetForm() {
			refs.form.reset();
			refs.name.value = "";
			refs.label.value = "";
			refs.driver.value = "";
			refs.endpoint.value = "";
			refs.keytype.value = "env";
			refs.keyvalue.value = "";
			refs.enabled.checked = true;

			state.selectedName = "";
			setEditMode(false);
			updateKeyTypeUi();
			highlightSelection();
		}

		function fillForm(provider) {
			if (!provider) {
				resetForm();
				return;
			}

			refs.name.value = provider.name || "";
			refs.label.value = provider.label || "";
			refs.driver.value = provider.driver || "";
			refs.endpoint.value = provider.endpoint || "";
			refs.keytype.value = normalizeKeyType(provider.keytype || "env");
			refs.keyvalue.value = provider.keyvalue || "";
			refs.enabled.checked = !!provider.enabled;

			state.selectedName = provider.name || "";
			setEditMode(true);
			updateKeyTypeUi();
			highlightSelection();
		}

		function maskKeyValue(provider) {
			const keyType = normalizeKeyType(provider.keytype || "");
			if (keyType === "fixed") {
				return "••••••••";
			}

			return provider.keyvalue || "";
		}

		function statusBadge(enabled) {
			if (enabled) {
				return "<span class='badge ok'>enabled</span>";
			}

			return "<span class='badge off'>disabled</span>";
		}

		function highlightSelection() {
			root.querySelectorAll("tbody tr[data-name]").forEach(function(row) {
				if (row.getAttribute("data-name") === state.selectedName) {
					row.classList.add("selected");
				} else {
					row.classList.remove("selected");
				}
			});
		}

		function renderRows() {
			const providers = Array.isArray(state.providers) ? state.providers : [];
			refs.tbody.innerHTML = "";

			if (providers.length === 0) {
				refs.tbody.innerHTML = "<tr><td colspan='6' class='mono'>No providers configured.</td></tr>";
				return;
			}

			for (const provider of providers) {
				const tr = document.createElement("tr");
				tr.setAttribute("data-name", String(provider.name || ""));

				tr.innerHTML =
					"<td class='name-col'>" + esc(provider.name) + "</td>" +
					"<td>" + esc(provider.label) + "</td>" +
					"<td>" + esc(provider.driver) + "</td>" +
					"<td title='" + esc(normalizeKeyType(provider.keytype) + ": " + maskKeyValue(provider)) + "'>" +
						"<span class='mono'>" + esc(normalizeKeyType(provider.keytype)) + "</span><br>" +
						"<span class='mono' style='color:#777'>" + esc(maskKeyValue(provider)) + "</span>" +
					"</td>" +
					"<td>" + statusBadge(!!provider.enabled) + "</td>" +
					"<td><button type='button' class='apad-edit-btn' data-action='edit' data-name='" + esc(provider.name) + "'>Edit</button></td>";

				refs.tbody.appendChild(tr);
			}

			highlightSelection();
		}

		function findProvider(name) {
			name = String(name || "");
			return state.providers.find(function(provider) {
				return String(provider.name || "") === name;
			}) || null;
		}

		async function callApi(params) {
			setLoading(true);

			try {
				const body = new URLSearchParams();
				Object.keys(params || {}).forEach(function(key) {
					body.append(key, params[key]);
				});

				const response = await fetch(endpointBase, {
					method: "POST",
					headers: {
						"Accept": "application/json",
						"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
					},
					body: body.toString()
				});

				const text = await response.text();
				let json = null;

				try {
					json = JSON.parse(text);
				} catch (e) {
					printOutput("Invalid JSON response:\n" + text, "error");
					return null;
				}

				setLastUpdate(json.timestamp || "");

				if (json.status !== "ok") {
					printOutput(json.message || json, "error");
					return null;
				}

				return json;
			} catch (e) {
				printOutput("Request failed:\n" + e, "error");
				return null;
			} finally {
				setLoading(false);
			}
		}

		async function loadList(preselectName) {
			const json = await callApi({
				action: "list"
			});

			if (!json) {
				return;
			}

			state.providers = (json.data && Array.isArray(json.data.providers)) ? json.data.providers : [];
			renderRows();

			if (preselectName) {
				const provider = findProvider(preselectName);
				if (provider) {
					fillForm(provider);
					return;
				}
			}

			if (state.selectedName) {
				const provider = findProvider(state.selectedName);
				if (provider) {
					fillForm(provider);
					return;
				}
			}

			if (state.providers.length === 0) {
				resetForm();
			}
		}

		async function saveCurrent() {
			const name = normalizeKey(refs.name.value);
			const label = String(refs.label.value || "").trim();
			const driver = normalizeKey(refs.driver.value);
			const endpoint = String(refs.endpoint.value || "").trim();
			const keytype = normalizeKeyType(refs.keytype.value);
			const keyvalue = String(refs.keyvalue.value || "").trim();
			const enabled = refs.enabled.checked ? "1" : "0";

			refs.name.value = name;
			refs.driver.value = driver;
			refs.keytype.value = keytype;
			updateKeyTypeUi();

			if (!name) {
				printOutput("Settings name is required.", "error");
				return;
			}

			if (!label) {
				printOutput("Label is required.", "error");
				return;
			}

			if (!driver) {
				printOutput("Driver is required.", "error");
				return;
			}

			if (!endpoint) {
				printOutput("Endpoint is required.", "error");
				return;
			}

			if (!keytype) {
				printOutput("Key type is required. Allowed: env or fixed.", "error");
				return;
			}

			if (!keyvalue) {
				printOutput("Key value is required.", "error");
				return;
			}

			const json = await callApi({
				action: "save",
				name: name,
				label: label,
				driver: driver,
				endpoint: endpoint,
				keytype: keytype,
				keyvalue: keyvalue,
				enabled: enabled
			});

			if (!json) {
				return;
			}

			const provider = (json.data && json.data.provider) ? json.data.provider : null;

			printOutput({
				status: "saved",
				group: configGroup,
				provider: provider
			}, "success");

			await loadList(provider && provider.name ? provider.name : name);
		}

		async function removeCurrent() {
			const name = String(state.selectedName || refs.name.value || "").trim();

			if (!name) {
				printOutput("No provider selected.", "error");
				return;
			}

			if (!window.confirm("Delete provider '" + name + "'?")) {
				return;
			}

			const json = await callApi({
				action: "remove",
				name: name
			});

			if (!json) {
				return;
			}

			printOutput({
				status: "removed",
				group: configGroup,
				name: name
			}, "success");

			resetForm();
			await loadList();
		}

		refs.form.addEventListener("submit", function(e) {
			e.preventDefault();
			saveCurrent();
		});

		refs.newBtn.addEventListener("click", function() {
			clearOutput();
			resetForm();
		});

		refs.reloadBtn.addEventListener("click", function() {
			clearOutput();
			loadList(state.selectedName || "");
		});

		refs.deleteBtn.addEventListener("click", function() {
			removeCurrent();
		});

		refs.keytype.addEventListener("change", function() {
			refs.keytype.value = normalizeKeyType(refs.keytype.value);
			updateKeyTypeUi();
		});

		refs.tbody.addEventListener("click", function(e) {
			const button = e.target.closest("[data-action='edit']");
			const row = e.target.closest("tr[data-name]");

			if (button) {
				const name = button.getAttribute("data-name") || "";
				const provider = findProvider(name);
				if (provider) {
					clearOutput();
					fillForm(provider);
				}
				return;
			}

			if (row) {
				const name = row.getAttribute("data-name") || "";
				const provider = findProvider(name);
				if (provider) {
					clearOutput();
					fillForm(provider);
				}
			}
		});

		resetForm();
		loadList();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	}

	init();
})();
</script>
