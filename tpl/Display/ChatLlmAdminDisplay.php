<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="chat-llm-admin">
	<h3>Chat LLM settings</h3>

	<div class="clad-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Provider group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['providerGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">–</span></div>
		<div data-role="loading" class="clad-loading">Please wait…</div>
	</div>

	<div data-role="output" class="clad-output" style="display:none"></div>

	<div class="clad-layout">
		<div class="clad-listbox">
			<div class="clad-toolbar">
				<button type="button" data-role="new">New model</button>
				<button type="button" data-role="reload">Reload</button>
			</div>

			<table class="clad-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Label</th>
						<th>Provider</th>
						<th>Model</th>
						<th>Params</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="7" class="mono">Loading…</td></tr>
				</tbody>
			</table>
		</div>

		<div class="clad-formbox">
			<form data-role="form">
				<h4 data-role="legend">Create model</h4>

				<div class="clad-hint" data-role="namehint">
					Technical settings name. This is the LLM id used by configuredchatmodelagentresource.
				</div>

				<div class="clad-grid">
					<div class="clad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name">LLM id</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name"
							name="name"
							placeholder="openai_default"
							autocomplete="off"
						>
					</div>

					<div class="clad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-label">Label</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-label"
							name="label"
							placeholder="OpenAI Default"
							autocomplete="off"
						>
					</div>

					<div class="clad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-provider">Provider</label>
						<select
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-provider"
							name="provider"
						>
							<option value="">Loading providers…</option>
						</select>
						<div class="clad-hint clad-inline-hint" data-role="providerhint">
							Provider credentials and endpoint are managed in the AI provider settings.
						</div>
					</div>

					<div class="clad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model">Model name</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model"
							name="model"
							list="<?php echo htmlspecialchars((string)$this->_['modelListId'], ENT_QUOTES); ?>"
							placeholder="gpt-4o-mini"
							autocomplete="off"
						>
					</div>

					<div class="clad-field clad-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-temperature">Temperature</label>
							<input
								type="text"
								id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-temperature"
								name="temperature"
								placeholder="0.3"
								autocomplete="off"
							>
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-maxtokens">Max tokens</label>
							<input
								type="text"
								id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-maxtokens"
								name="max_tokens"
								placeholder="1024"
								autocomplete="off"
							>
						</div>
					</div>

					<div class="clad-field clad-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-topp">Top P</label>
							<input
								type="text"
								id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-topp"
								name="top_p"
								placeholder=""
								autocomplete="off"
							>
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout">Timeout seconds</label>
							<input
								type="text"
								id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout"
								name="timeout_seconds"
								placeholder=""
								autocomplete="off"
							>
						</div>
					</div>

					<div class="clad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout">Connect timeout seconds</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout"
							name="connect_timeout_seconds"
							placeholder=""
							autocomplete="off"
						>
					</div>

					<div class="clad-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-params">Extra params JSON</label>
						<textarea
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-params"
							name="params"
							spellcheck="false"
							placeholder="{&#10;}"
						></textarea>
						<div class="clad-hint clad-inline-hint">
							Optional JSON object for provider-specific parameters. Protected runtime keys like model, endpoint and apikey cannot be overwritten here.
						</div>
					</div>

					<div class="clad-field clad-field-checkbox">
						<label class="clad-checkbox">
							<input type="checkbox" name="enabled" checked>
							<span>Enabled</span>
						</label>
					</div>
				</div>

				<div class="clad-actions">
					<button type="submit" class="primary">Save model</button>
					<button type="button" data-role="delete" disabled>Delete model</button>
				</div>
			</form>
		</div>
	</div>

	<datalist id="<?php echo htmlspecialchars((string)$this->_['modelListId'], ENT_QUOTES); ?>">
		<?php foreach(($this->_['modelSuggestions'] ?? []) as $model): ?>
			<option value="<?php echo htmlspecialchars((string)$model, ENT_QUOTES); ?>"></option>
		<?php endforeach; ?>
	</datalist>
</div>

<style>
.chat-llm-admin {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.chat-llm-admin h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.chat-llm-admin h4 {
	margin-top: 0;
	margin-bottom: 10px;
	font-size: 1em;
}

.clad-meta {
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

.clad-loading {
	display: none;
	color: #666;
	font-style: italic;
}

.clad-output {
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

.clad-output.error {
	border-color: #d88;
	background: #fff5f5;
	color: #a33;
}

.clad-output.success {
	border-color: #8d8;
	background: #f6fff6;
	color: #373;
}

.clad-layout {
	display: grid;
	grid-template-columns: minmax(520px, 1fr) minmax(380px, 500px);
	gap: 16px;
	align-items: start;
}

.clad-listbox,
.clad-formbox {
	border: 1px solid #ddd;
	border-radius: 4px;
	background: #fafafa;
	padding: 12px;
}

.clad-toolbar {
	display: flex;
	gap: 8px;
	margin-bottom: 10px;
}

.clad-toolbar button,
.clad-actions button {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	color: #333;
	border-radius: 6px;
	padding: 8px 12px;
	cursor: pointer;
}

.clad-toolbar button:hover,
.clad-actions button:hover {
	background: #e8e8e8;
}

.clad-actions .primary {
	background: #eaf3ff;
	border-color: #aac6ea;
}

.clad-actions .primary:hover {
	background: #dcecff;
}

.clad-actions button[disabled] {
	opacity: 0.5;
	cursor: not-allowed;
}

.clad-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
}

.clad-table th,
.clad-table td {
	padding: 8px 10px;
	border-bottom: 1px solid #e0e0e0;
	vertical-align: middle;
	text-align: left;
	font-size: 13px;
}

.clad-table th {
	background: #f5f5f5;
	font-weight: 600;
	border-bottom: 2px solid #cfcfcf;
}

.clad-table tr:hover td {
	background: #fafafa;
}

.clad-table tr.selected td {
	background: #eef5ff;
}

.clad-table td.name-col {
	font-family: Consolas, monospace;
	font-size: 12px;
	white-space: nowrap;
}

.clad-table td.model-col {
	max-width: 260px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-family: Consolas, monospace;
	font-size: 12px;
}

.clad-edit-btn {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	border-radius: 6px;
	padding: 5px 8px;
	cursor: pointer;
	font-size: 12px;
}

.clad-edit-btn:hover {
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

.badge.warn {
	border-color: #e0a56b;
	background: #fff4e8;
	color: #8a4f12;
}

.clad-hint {
	margin-bottom: 12px;
	font-size: 12px;
	color: #666;
}

.clad-inline-hint {
	margin-top: 6px;
	margin-bottom: 0;
}

.clad-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 12px;
}

.clad-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 6px;
	font-size: 13px;
}

.clad-field input[type="text"],
.clad-field select,
.clad-field textarea {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid #cfcfcf;
	border-radius: 6px;
	padding: 8px 10px;
	background: #fff;
	color: #333;
}

.clad-field textarea {
	min-height: 130px;
	font-family: Consolas, monospace;
	font-size: 12px;
	resize: vertical;
}

.clad-field input[readonly] {
	background: #f6f6f6;
	color: #666;
}

.clad-field-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}

.clad-field-checkbox {
	padding-top: 4px;
}

.clad-checkbox {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
}

.clad-actions {
	display: flex;
	gap: 8px;
	margin-top: 14px;
}

@media (max-width: 1100px) {
	.clad-layout {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 620px) {
	.clad-field-row {
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
			providerhint: root.querySelector("[data-role='providerhint']"),
			newBtn: root.querySelector("[data-role='new']"),
			reloadBtn: root.querySelector("[data-role='reload']"),
			deleteBtn: root.querySelector("[data-role='delete']"),
			name: root.querySelector("input[name='name']"),
			label: root.querySelector("input[name='label']"),
			provider: root.querySelector("select[name='provider']"),
			model: root.querySelector("input[name='model']"),
			temperature: root.querySelector("input[name='temperature']"),
			maxTokens: root.querySelector("input[name='max_tokens']"),
			topP: root.querySelector("input[name='top_p']"),
			timeoutSeconds: root.querySelector("input[name='timeout_seconds']"),
			connectTimeoutSeconds: root.querySelector("input[name='connect_timeout_seconds']"),
			params: root.querySelector("textarea[name='params']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		const state = {
			llms: [],
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
			refs.output.className = "clad-output";

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
			refs.output.className = "clad-output";
			refs.output.textContent = "";
		}

		function setEditMode(editing) {
			refs.name.readOnly = editing;
			refs.deleteBtn.disabled = !editing;

			if (editing) {
				refs.legend.textContent = "Edit model";
				refs.namehint.textContent = "Technical LLM id is fixed for existing entries. Create a new entry if you need another key.";
			} else {
				refs.legend.textContent = "Create model";
				refs.namehint.textContent = "Technical settings name. This is the LLM id used by configuredchatmodelagentresource.";
			}
		}

		function findProvider(name) {
			name = String(name || "");
			return state.providers.find(function(provider) {
				return String(provider.name || "") === name;
			}) || null;
		}

		function providerLabel(name) {
			const provider = findProvider(name);
			if (!provider) {
				return name || "";
			}

			let label = provider.label || provider.name || "";
			if (provider.driver) {
				label += " (" + provider.driver + ")";
			}

			return label;
		}

		function updateProviderHint() {
			const provider = findProvider(refs.provider.value);

			if (!provider) {
				refs.providerhint.textContent = "Provider credentials and endpoint are managed in the AI provider settings.";
				return;
			}

			let text = "Driver: " + (provider.driver || "unknown") + ". Endpoint: " + (provider.endpoint || "not set") + ".";

			if (!provider.enabled) {
				text += " This provider is currently disabled.";
			}

			refs.providerhint.textContent = text;
		}

		function renderProviderOptions(selected) {
			const providers = Array.isArray(state.providers) ? state.providers : [];
			refs.provider.innerHTML = "";

			if (providers.length === 0) {
				const option = document.createElement("option");
				option.value = "";
				option.textContent = "No providers configured";
				refs.provider.appendChild(option);
				updateProviderHint();
				return;
			}

			const empty = document.createElement("option");
			empty.value = "";
			empty.textContent = "Select provider";
			refs.provider.appendChild(empty);

			for (const provider of providers) {
				const option = document.createElement("option");
				option.value = provider.name || "";
				option.textContent = providerLabel(provider.name);

				if (!provider.enabled) {
					option.textContent += " [disabled]";
				}

				refs.provider.appendChild(option);
			}

			refs.provider.value = selected || refs.provider.value || "";
			updateProviderHint();
		}

		function formatParams(params) {
			if (!params || typeof params !== "object" || Array.isArray(params)) {
				return "{\n}";
			}

			if (Object.keys(params).length === 0) {
				return "{\n}";
			}

			return JSON.stringify(params, null, 2);
		}

		function paramSummary(llm) {
			const parts = [];

			if (llm.temperature !== "") {
				parts.push("temp " + llm.temperature);
			}

			if (llm.max_tokens !== "") {
				parts.push("max " + llm.max_tokens);
			}

			if (llm.top_p !== "") {
				parts.push("top_p " + llm.top_p);
			}

			const params = llm.params && typeof llm.params === "object" && !Array.isArray(llm.params) ? llm.params : {};
			const extraCount = Object.keys(params).length;

			if (extraCount > 0) {
				parts.push(extraCount + " extra");
			}

			return parts.length > 0 ? parts.join(", ") : "default";
		}

		function resetForm() {
			refs.form.reset();
			refs.name.value = "";
			refs.label.value = "";
			refs.provider.value = "";
			refs.model.value = "";
			refs.temperature.value = "";
			refs.maxTokens.value = "";
			refs.topP.value = "";
			refs.timeoutSeconds.value = "";
			refs.connectTimeoutSeconds.value = "";
			refs.params.value = "{\n}";
			refs.enabled.checked = true;

			state.selectedName = "";
			setEditMode(false);
			updateProviderHint();
			highlightSelection();
		}

		function fillForm(llm) {
			if (!llm) {
				resetForm();
				return;
			}

			refs.name.value = llm.name || "";
			refs.label.value = llm.label || "";
			refs.provider.value = llm.provider || "";
			refs.model.value = llm.model || "";
			refs.temperature.value = llm.temperature || "";
			refs.maxTokens.value = llm.max_tokens || "";
			refs.topP.value = llm.top_p || "";
			refs.timeoutSeconds.value = llm.timeout_seconds || "";
			refs.connectTimeoutSeconds.value = llm.connect_timeout_seconds || "";
			refs.params.value = formatParams(llm.params || {});
			refs.enabled.checked = !!llm.enabled;

			state.selectedName = llm.name || "";
			setEditMode(true);
			updateProviderHint();
			highlightSelection();
		}

		function statusBadge(llm) {
			if (!llm.enabled) {
				return "<span class='badge off'>disabled</span>";
			}

			if (!llm.provider_enabled) {
				return "<span class='badge warn'>provider off</span>";
			}

			return "<span class='badge ok'>enabled</span>";
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
			const llms = Array.isArray(state.llms) ? state.llms : [];
			refs.tbody.innerHTML = "";

			if (llms.length === 0) {
				refs.tbody.innerHTML = "<tr><td colspan='7' class='mono'>No models configured.</td></tr>";
				return;
			}

			for (const llm of llms) {
				const tr = document.createElement("tr");
				tr.setAttribute("data-name", String(llm.name || ""));

				tr.innerHTML =
					"<td class='name-col'>" + esc(llm.name) + "</td>" +
					"<td>" + esc(llm.label) + "</td>" +
					"<td title='" + esc(providerLabel(llm.provider)) + "'>" +
						"<span class='mono'>" + esc(llm.provider) + "</span><br>" +
						"<span style='color:#777'>" + esc(llm.provider_driver || "") + "</span>" +
					"</td>" +
					"<td class='model-col' title='" + esc(llm.model) + "'>" + esc(llm.model) + "</td>" +
					"<td class='mono' title='" + esc(JSON.stringify(llm.params || {})) + "'>" + esc(paramSummary(llm)) + "</td>" +
					"<td>" + statusBadge(llm) + "</td>" +
					"<td><button type='button' class='clad-edit-btn' data-action='edit' data-name='" + esc(llm.name) + "'>Edit</button></td>";

				refs.tbody.appendChild(tr);
			}

			highlightSelection();
		}

		function findLlm(name) {
			name = String(name || "");
			return state.llms.find(function(llm) {
				return String(llm.name || "") === name;
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
			state.llms = (json.data && Array.isArray(json.data.llms)) ? json.data.llms : [];

			renderProviderOptions(refs.provider.value || "");
			renderRows();

			if (preselectName) {
				const llm = findLlm(preselectName);
				if (llm) {
					fillForm(llm);
					return;
				}
			}

			if (state.selectedName) {
				const llm = findLlm(state.selectedName);
				if (llm) {
					fillForm(llm);
					return;
				}
			}

			if (state.llms.length === 0) {
				resetForm();
			}
		}

		function readParamsJson() {
			const raw = String(refs.params.value || "").trim();

			if (!raw) {
				return "{}";
			}

			try {
				const parsed = JSON.parse(raw);

				if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
					printOutput("Extra params must be a JSON object.", "error");
					return null;
				}

				return JSON.stringify(parsed);
			} catch (e) {
				printOutput("Extra params must be valid JSON:\n" + e.message, "error");
				return null;
			}
		}

		async function saveCurrent() {
			const name = normalizeKey(refs.name.value);
			const label = String(refs.label.value || "").trim();
			const provider = normalizeKey(refs.provider.value);
			const model = String(refs.model.value || "").trim();
			const temperature = String(refs.temperature.value || "").trim();
			const maxTokens = String(refs.maxTokens.value || "").trim();
			const topP = String(refs.topP.value || "").trim();
			const timeoutSeconds = String(refs.timeoutSeconds.value || "").trim();
			const connectTimeoutSeconds = String(refs.connectTimeoutSeconds.value || "").trim();
			const params = readParamsJson();
			const enabled = refs.enabled.checked ? "1" : "0";

			if (params === null) {
				return;
			}

			refs.name.value = name;
			refs.provider.value = provider;
			updateProviderHint();

			if (!name) {
				printOutput("LLM id is required.", "error");
				return;
			}

			if (!label) {
				printOutput("Label is required.", "error");
				return;
			}

			if (!provider) {
				printOutput("Provider is required.", "error");
				return;
			}

			if (!model) {
				printOutput("Model name is required.", "error");
				return;
			}

			const json = await callApi({
				action: "save",
				name: name,
				label: label,
				provider: provider,
				model: model,
				temperature: temperature,
				max_tokens: maxTokens,
				top_p: topP,
				timeout_seconds: timeoutSeconds,
				connect_timeout_seconds: connectTimeoutSeconds,
				params: params,
				enabled: enabled
			});

			if (!json) {
				return;
			}

			const llm = (json.data && json.data.llm) ? json.data.llm : null;

			printOutput({
				status: "saved",
				group: configGroup,
				llm: llm
			}, "success");

			await loadList(llm && llm.name ? llm.name : name);
		}

		async function removeCurrent() {
			const name = String(state.selectedName || refs.name.value || "").trim();

			if (!name) {
				printOutput("No model selected.", "error");
				return;
			}

			if (!window.confirm("Delete model '" + name + "'?")) {
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

		refs.provider.addEventListener("change", function() {
			refs.provider.value = normalizeKey(refs.provider.value);
			updateProviderHint();
		});

		refs.tbody.addEventListener("click", function(e) {
			const button = e.target.closest("[data-action='edit']");
			const row = e.target.closest("tr[data-name]");

			if (button) {
				const name = button.getAttribute("data-name") || "";
				const llm = findLlm(name);
				if (llm) {
					clearOutput();
					fillForm(llm);
				}
				return;
			}

			if (row) {
				const name = row.getAttribute("data-name") || "";
				const llm = findLlm(name);
				if (llm) {
					clearOutput();
					fillForm(llm);
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
