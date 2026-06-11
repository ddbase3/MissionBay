<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="embedding-config-admin">
	<h3>Embedding Services</h3>

	<div class="embcfg-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Connection group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="embcfg-loading">Please wait...</div>
	</div>

	<div class="embcfg-hint">
		Embedding services define concrete vector embedding models. Technical endpoint and authentication are taken from the selected connection.
	</div>

	<div class="embcfg-layout">
		<div class="embcfg-listbox">
			<div class="embcfg-toolbar">
				<button type="button" data-role="new">New embedding service</button>
				<button type="button" data-role="reload">Reload</button>
			</div>

			<table class="embcfg-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Connection</th>
						<th>Driver</th>
						<th>Model</th>
						<th>Dimensions</th>
						<th>Batch</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="9" class="mono">Loading...</td></tr>
				</tbody>
			</table>
		</div>

		<div class="embcfg-formbox">
			<form data-role="form">
				<h4 data-role="legend">Create embedding service</h4>

				<div class="embcfg-hint" data-role="idhint">
					Technical embedding service id. Agent resources and vector collections use this id to resolve the configured embedding service.
				</div>

				<div class="embcfg-grid">
					<div class="embcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-id">Embedding service id</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-id"
							name="id"
							placeholder="openai_default_embedding"
							autocomplete="off"
						>
					</div>

					<div class="embcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name">Name</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name"
							name="name"
							placeholder="OpenAI Default Embedding"
							autocomplete="off"
						>
					</div>

					<div class="embcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connection">Connection</label>
						<select
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connection"
							name="connection"
						>
							<option value="">Loading connections...</option>
						</select>
						<div class="embcfg-hint embcfg-inline-hint" data-role="connectionhint">
							Connections contain endpoint and authentication data.
						</div>
					</div>

					<div class="embcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver">Driver</label>
						<select
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver"
							name="driver"
						>
							<option value="">Loading drivers...</option>
						</select>
					</div>

					<div class="embcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model">Model</label>
						<input
							type="text"
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model"
							name="model"
							placeholder="text-embedding-3-small"
							autocomplete="off"
						>
					</div>

					<div class="embcfg-field embcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-dimensions">Dimensions</label>
							<input
								type="text"
								id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-dimensions"
								name="dimensions"
								placeholder="1536"
								autocomplete="off"
							>
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-batchsize">Batch size</label>
							<input
								type="text"
								id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-batchsize"
								name="batchSize"
								placeholder="100"
								autocomplete="off"
							>
						</div>
					</div>

					<div class="embcfg-field embcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout">Timeout seconds</label>
							<input
								type="text"
								id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout"
								name="timeoutSeconds"
								placeholder=""
								autocomplete="off"
							>
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout">Connect timeout seconds</label>
							<input
								type="text"
								id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout"
								name="connectTimeoutSeconds"
								placeholder=""
								autocomplete="off"
							>
						</div>
					</div>

					<div class="embcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-options">Advanced options JSON</label>
						<textarea
							id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-options"
							name="options"
							spellcheck="false"
							placeholder="{&#10;}"
						></textarea>
						<div class="embcfg-hint embcfg-inline-hint">
							Optional provider-specific options. Explicit fields above override duplicate keys in this JSON object.
						</div>
					</div>

					<div class="embcfg-field embcfg-field-checkbox">
						<label class="embcfg-checkbox">
							<input type="checkbox" name="normalizeVectors">
							<span>Normalize vectors</span>
						</label>
					</div>

					<div class="embcfg-field embcfg-field-checkbox">
						<label class="embcfg-checkbox">
							<input type="checkbox" name="enabled" checked>
							<span>Enabled</span>
						</label>
					</div>
				</div>

				<div data-role="formfeedback" class="embcfg-form-feedback" style="display:none"></div>

				<div class="embcfg-actions">
					<button type="submit" class="primary">Save embedding service</button>
					<button type="button" data-role="delete" disabled>Delete embedding service</button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
.embedding-config-admin {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.embedding-config-admin h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.embedding-config-admin h4 {
	margin-top: 0;
	margin-bottom: 10px;
	font-size: 1em;
}

.embcfg-meta {
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

.embcfg-loading {
	display: none;
	color: #666;
	font-style: italic;
}

.embcfg-layout {
	display: grid;
	grid-template-columns: minmax(680px, 1fr) minmax(380px, 520px);
	gap: 16px;
	align-items: start;
}

.embcfg-listbox,
.embcfg-formbox {
	border: 1px solid #ddd;
	border-radius: 4px;
	background: #fafafa;
	padding: 12px;
}

.embcfg-toolbar {
	display: flex;
	gap: 8px;
	margin-bottom: 10px;
}

.embcfg-toolbar button,
.embcfg-actions button {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	color: #333;
	border-radius: 6px;
	padding: 8px 12px;
	cursor: pointer;
}

.embcfg-toolbar button:hover,
.embcfg-actions button:hover {
	background: #e8e8e8;
}

.embcfg-actions .primary {
	background: #eaf3ff;
	border-color: #aac6ea;
}

.embcfg-actions .primary:hover {
	background: #dcecff;
}

.embcfg-actions button[disabled] {
	opacity: 0.5;
	cursor: not-allowed;
}

.embcfg-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
}

.embcfg-table th,
.embcfg-table td {
	padding: 8px 10px;
	border-bottom: 1px solid #e0e0e0;
	vertical-align: middle;
	text-align: left;
	font-size: 13px;
}

.embcfg-table th {
	background: #f5f5f5;
	font-weight: 600;
	border-bottom: 2px solid #cfcfcf;
}

.embcfg-table tr:hover td {
	background: #fafafa;
}

.embcfg-table tr.selected td {
	background: #eef5ff;
}

.embcfg-table td.id-col,
.embcfg-table td.model-col,
.embcfg-table td.connection-col,
.embcfg-table td.driver-col,
.embcfg-table td.number-col {
	font-family: Consolas, monospace;
	font-size: 12px;
}

.embcfg-table td.model-col {
	max-width: 260px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.embcfg-edit-btn {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	border-radius: 6px;
	padding: 5px 8px;
	cursor: pointer;
	font-size: 12px;
}

.embcfg-edit-btn:hover {
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

.embcfg-hint {
	margin-bottom: 12px;
	font-size: 12px;
	color: #666;
}

.embcfg-inline-hint {
	margin-top: 6px;
	margin-bottom: 0;
}

.embcfg-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 12px;
}

.embcfg-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 6px;
	font-size: 13px;
}

.embcfg-field input[type="text"],
.embcfg-field select,
.embcfg-field textarea {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid #cfcfcf;
	border-radius: 6px;
	padding: 8px 10px;
	background: #fff;
	color: #333;
}

.embcfg-field textarea {
	min-height: 150px;
	font-family: Consolas, monospace;
	font-size: 12px;
	resize: vertical;
}

.embcfg-field input[readonly] {
	background: #f6f6f6;
	color: #666;
}

.embcfg-field-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}

.embcfg-field-checkbox {
	padding-top: 4px;
}

.embcfg-checkbox {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
}

.embcfg-form-feedback {
	margin-top: 14px;
	border: 1px solid #ddd;
	border-radius: 6px;
	padding: 9px 11px;
	font-size: 13px;
	line-height: 1.4;
}

.embcfg-form-feedback.success {
	border-color: #8d8;
	background: #f6fff6;
	color: #2d6b2d;
}

.embcfg-form-feedback.error {
	border-color: #d88;
	background: #fff5f5;
	color: #a33;
}

.embcfg-actions {
	display: flex;
	gap: 8px;
	margin-top: 14px;
}

@media (max-width: 1200px) {
	.embcfg-layout {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 620px) {
	.embcfg-field-row {
		grid-template-columns: 1fr;
	}
}
</style>

<script>
(function() {
	const instanceId = <?php echo json_encode((string)$this->_['instanceId']); ?>;
	const endpointBase = <?php echo json_encode((string)$this->_['endpoint']); ?>;

	function init() {
		const root = document.getElementById(instanceId);
		if (!root || root.dataset.initialized === "1") {
			return;
		}

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
			dimensions: root.querySelector("input[name='dimensions']"),
			batchSize: root.querySelector("input[name='batchSize']"),
			normalizeVectors: root.querySelector("input[name='normalizeVectors']"),
			timeoutSeconds: root.querySelector("input[name='timeoutSeconds']"),
			connectTimeoutSeconds: root.querySelector("input[name='connectTimeoutSeconds']"),
			options: root.querySelector("textarea[name='options']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		const state = {
			embeddings: [],
			connections: [],
			drivers: [],
			selectedId: ""
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

			refs.lastupdate.textContent = ts || "-";
		}

		function showFeedback(message, type) {
			if (!refs.formfeedback) {
				return;
			}

			refs.formfeedback.style.display = "block";
			refs.formfeedback.className = "embcfg-form-feedback " + (type === "error" ? "error" : "success");
			refs.formfeedback.textContent = message;
		}

		function clearFeedback() {
			if (!refs.formfeedback) {
				return;
			}

			refs.formfeedback.style.display = "none";
			refs.formfeedback.className = "embcfg-form-feedback";
			refs.formfeedback.textContent = "";
		}

		function setEditMode(editing) {
			refs.id.readOnly = editing;
			refs.deleteBtn.disabled = !editing;

			if (editing) {
				refs.legend.textContent = "Edit embedding service";
				refs.idhint.textContent = "Technical embedding service id is fixed for existing entries. Create a new entry if you need another key.";
			} else {
				refs.legend.textContent = "Create embedding service";
				refs.idhint.textContent = "Technical embedding service id. Agent resources and vector collections use this id to resolve the configured embedding service.";
			}
		}

		function findEmbedding(id) {
			id = String(id || "");
			return state.embeddings.find(function(item) {
				return String(item.id || "") === id;
			}) || null;
		}

		function findConnection(id) {
			id = String(id || "");
			return state.connections.find(function(item) {
				return String(item.id || "") === id;
			}) || null;
		}

		function findDriver(driver) {
			driver = String(driver || "");
			return state.drivers.find(function(item) {
				return String(item.driver || "") === driver;
			}) || null;
		}

		function formatOptions(options) {
			const clean = Object.assign({}, options || {});
			delete clean.dimensions;
			delete clean.batchSize;
			delete clean.normalizeVectors;
			delete clean.timeoutSeconds;
			delete clean.connectTimeoutSeconds;

			if (Object.keys(clean).length === 0) {
				return "{\n}";
			}

			return JSON.stringify(clean, null, 2);
		}

		function connectionLabel(id) {
			const connection = findConnection(id);

			if (!connection) {
				return id || "";
			}

			let label = connection.name || connection.id || "";

			if (connection.type) {
				label += " (" + connection.type + ")";
			}

			return label;
		}

		function updateConnectionHint() {
			const connection = findConnection(refs.connection.value);

			if (!connection) {
				refs.connectionhint.textContent = "Connections contain endpoint and authentication data.";
				return;
			}

			let text = "Type: " + (connection.type || "unknown") + ". Driver: " + (connection.driver || "unknown") + ". Base URL: " + (connection.baseUrl || "not set") + ".";

			if (!connection.enabled) {
				text += " This connection is currently disabled.";
			}

			refs.connectionhint.textContent = text;
		}

		function renderConnectionOptions(selected) {
			const connections = Array.isArray(state.connections) ? state.connections : [];
			refs.connection.innerHTML = "";

			if (connections.length === 0) {
				const option = document.createElement("option");
				option.value = "";
				option.textContent = "No connections configured";
				refs.connection.appendChild(option);
				updateConnectionHint();
				return;
			}

			const empty = document.createElement("option");
			empty.value = "";
			empty.textContent = "Select connection";
			refs.connection.appendChild(empty);

			for (const connection of connections) {
				const option = document.createElement("option");
				option.value = connection.id || "";
				option.textContent = connectionLabel(connection.id);

				if (!connection.enabled) {
					option.textContent += " [disabled]";
				}

				refs.connection.appendChild(option);
			}

			refs.connection.value = selected || refs.connection.value || "";
			updateConnectionHint();
		}

		function renderDriverOptions(selected) {
			const drivers = Array.isArray(state.drivers) ? state.drivers : [];
			refs.driver.innerHTML = "";

			if (drivers.length === 0) {
				const option = document.createElement("option");
				option.value = "";
				option.textContent = "No embedding drivers available";
				refs.driver.appendChild(option);
				return;
			}

			const empty = document.createElement("option");
			empty.value = "";
			empty.textContent = "Select driver";
			refs.driver.appendChild(empty);

			for (const driver of drivers) {
				const option = document.createElement("option");
				option.value = driver.driver || "";
				option.textContent = driver.label || driver.driver || "";
				refs.driver.appendChild(option);
			}

			refs.driver.value = selected || refs.driver.value || "";
		}

		function applyDriverDefaults(force) {
			const driver = findDriver(refs.driver.value);

			if (!driver) {
				return;
			}

			const defaults = driver.defaultConfig && typeof driver.defaultConfig === "object" ? driver.defaultConfig : {};
			const options = defaults.options && typeof defaults.options === "object" ? defaults.options : {};

			if (force || !refs.model.value) {
				refs.model.value = defaults.model || "";
			}

			if (force || !refs.dimensions.value) {
				refs.dimensions.value = options.dimensions ?? "";
			}

			if (force || !refs.batchSize.value) {
				refs.batchSize.value = options.batchSize ?? "";
			}

			if (force) {
				refs.normalizeVectors.checked = !!options.normalizeVectors;
				refs.timeoutSeconds.value = options.timeoutSeconds ?? "";
				refs.connectTimeoutSeconds.value = options.connectTimeoutSeconds ?? "";
				refs.options.value = formatOptions(options);
			}
		}

		function resetForm() {
			refs.form.reset();
			refs.id.value = "";
			refs.name.value = "";
			refs.connection.value = "";
			refs.driver.value = "";
			refs.model.value = "";
			refs.dimensions.value = "";
			refs.batchSize.value = "";
			refs.normalizeVectors.checked = false;
			refs.timeoutSeconds.value = "";
			refs.connectTimeoutSeconds.value = "";
			refs.options.value = "{\n}";
			refs.enabled.checked = true;

			state.selectedId = "";
			setEditMode(false);
			updateConnectionHint();
			highlightSelection();
		}

		function fillForm(embedding) {
			if (!embedding) {
				resetForm();
				return;
			}

			refs.id.value = embedding.id || "";
			refs.name.value = embedding.name || "";
			refs.connection.value = embedding.connection || "";
			refs.driver.value = embedding.driver || "";
			refs.model.value = embedding.model || "";
			refs.dimensions.value = embedding.dimensions || "";
			refs.batchSize.value = embedding.batchSize || "";
			refs.normalizeVectors.checked = !!embedding.normalizeVectors;
			refs.timeoutSeconds.value = embedding.timeoutSeconds || "";
			refs.connectTimeoutSeconds.value = embedding.connectTimeoutSeconds || "";
			refs.options.value = formatOptions(embedding.options || {});
			refs.enabled.checked = !!embedding.enabled;

			state.selectedId = embedding.id || "";
			setEditMode(true);
			updateConnectionHint();
			highlightSelection();
		}

		function statusBadge(embedding) {
			if (!embedding.enabled) {
				return "<span class='badge off'>disabled</span>";
			}

			if (!embedding.connectionEnabled) {
				return "<span class='badge warn'>connection off</span>";
			}

			return "<span class='badge ok'>enabled</span>";
		}

		function renderRows() {
			const embeddings = Array.isArray(state.embeddings) ? state.embeddings : [];
			refs.tbody.innerHTML = "";

			if (embeddings.length === 0) {
				refs.tbody.innerHTML = "<tr><td colspan='9' class='mono'>No embedding services configured.</td></tr>";
				return;
			}

			for (const embedding of embeddings) {
				const tr = document.createElement("tr");
				tr.setAttribute("data-id", String(embedding.id || ""));

				tr.innerHTML =
					"<td class='id-col'>" + esc(embedding.id) + "</td>" +
					"<td>" + esc(embedding.name) + "</td>" +
					"<td class='connection-col' title='" + esc(connectionLabel(embedding.connection)) + "'>" +
						esc(embedding.connection) + "<br><span style='color:#777'>" + esc(embedding.connectionType || "") + "</span>" +
					"</td>" +
					"<td class='driver-col'>" + esc(embedding.driverLabel || embedding.driver) + "</td>" +
					"<td class='model-col' title='" + esc(embedding.model) + "'>" + esc(embedding.model) + "</td>" +
					"<td class='number-col'>" + esc(embedding.dimensions || "") + "</td>" +
					"<td class='number-col'>" + esc(embedding.batchSize || "") + "</td>" +
					"<td>" + statusBadge(embedding) + "</td>" +
					"<td><button type='button' class='embcfg-edit-btn' data-action='edit' data-id='" + esc(embedding.id) + "'>Edit</button></td>";

				refs.tbody.appendChild(tr);
			}

			highlightSelection();
		}

		function highlightSelection() {
			root.querySelectorAll("tbody tr[data-id]").forEach(function(row) {
				if (row.getAttribute("data-id") === state.selectedId) {
					row.classList.add("selected");
				} else {
					row.classList.remove("selected");
				}
			});
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
			const json = await callApi({
				action: "list"
			});

			if (!json) {
				refs.tbody.innerHTML = "<tr><td colspan='9' class='mono'>Embedding services could not be loaded.</td></tr>";
				return;
			}

			state.connections = (json.data && Array.isArray(json.data.connections)) ? json.data.connections : [];
			state.drivers = (json.data && Array.isArray(json.data.drivers)) ? json.data.drivers : [];
			state.embeddings = (json.data && Array.isArray(json.data.embeddings)) ? json.data.embeddings : [];

			renderConnectionOptions(refs.connection.value || "");
			renderDriverOptions(refs.driver.value || "");
			renderRows();

			if (preselectId) {
				const embedding = findEmbedding(preselectId);
				if (embedding) {
					fillForm(embedding);
					return;
				}
			}

			if (state.selectedId) {
				const embedding = findEmbedding(state.selectedId);
				if (embedding) {
					fillForm(embedding);
					return;
				}
			}

			if (state.embeddings.length === 0) {
				resetForm();
			}
		}

		function readOptionsJson() {
			const raw = String(refs.options.value || "").trim();

			if (!raw) {
				return "{}";
			}

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

			const id = normalizeKey(refs.id.value);
			const name = String(refs.name.value || "").trim();
			const connection = normalizeKey(refs.connection.value);
			const driver = normalizeKey(refs.driver.value);
			const model = String(refs.model.value || "").trim();
			const dimensions = String(refs.dimensions.value || "").trim();
			const batchSize = String(refs.batchSize.value || "").trim();
			const normalizeVectors = refs.normalizeVectors.checked ? "1" : "0";
			const timeoutSeconds = String(refs.timeoutSeconds.value || "").trim();
			const connectTimeoutSeconds = String(refs.connectTimeoutSeconds.value || "").trim();
			const options = readOptionsJson();
			const enabled = refs.enabled.checked ? "1" : "0";

			if (options === null) {
				return;
			}

			refs.id.value = id;
			refs.connection.value = connection;
			refs.driver.value = driver;

			if (!id) {
				showFeedback("Embedding service id is required.", "error");
				return;
			}

			if (!name) {
				showFeedback("Name is required.", "error");
				return;
			}

			if (!connection) {
				showFeedback("Connection is required.", "error");
				return;
			}

			if (!driver) {
				showFeedback("Driver is required.", "error");
				return;
			}

			if (!model) {
				showFeedback("Model is required.", "error");
				return;
			}

			const json = await callApi({
				action: "save",
				id: id,
				name: name,
				connection: connection,
				driver: driver,
				model: model,
				dimensions: dimensions,
				batchSize: batchSize,
				normalizeVectors: normalizeVectors,
				timeoutSeconds: timeoutSeconds,
				connectTimeoutSeconds: connectTimeoutSeconds,
				options: options,
				enabled: enabled
			});

			if (!json) {
				return;
			}

			const embedding = (json.data && json.data.embedding) ? json.data.embedding : null;

			showFeedback("Embedding service saved.", "success");

			await loadList(embedding && embedding.id ? embedding.id : id);
		}

		async function removeCurrent() {
			clearFeedback();

			const id = String(state.selectedId || refs.id.value || "").trim();

			if (!id) {
				showFeedback("No embedding service selected.", "error");
				return;
			}

			if (!window.confirm("Delete embedding service '" + id + "'?")) {
				return;
			}

			const json = await callApi({
				action: "remove",
				id: id
			});

			if (!json) {
				return;
			}

			showFeedback("Embedding service deleted.", "success");

			resetForm();
			await loadList();
		}

		refs.form.addEventListener("submit", function(e) {
			e.preventDefault();
			saveCurrent();
		});

		refs.newBtn.addEventListener("click", function() {
			clearFeedback();
			resetForm();
		});

		refs.reloadBtn.addEventListener("click", function() {
			clearFeedback();
			loadList(state.selectedId || "");
		});

		refs.deleteBtn.addEventListener("click", function() {
			removeCurrent();
		});

		refs.connection.addEventListener("change", function() {
			updateConnectionHint();
		});

		refs.driver.addEventListener("change", function() {
			applyDriverDefaults(false);
		});

		refs.tbody.addEventListener("click", function(e) {
			const btn = e.target.closest("button[data-action='edit']");
			if (!btn) {
				return;
			}

			clearFeedback();

			const embedding = findEmbedding(btn.getAttribute("data-id"));
			fillForm(embedding);
		});

		loadList();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
</script>
