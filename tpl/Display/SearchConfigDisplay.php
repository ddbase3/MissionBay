<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="search-config-admin">
	<h3>Search Services</h3>

	<div class="searchcfg-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Connection group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="searchcfg-loading">Please wait...</div>
	</div>

	<div class="searchcfg-hint">
		Search services configure external or provider-based web search. They can be used by agent flows as independent retrieval services.
	</div>

	<div class="searchcfg-layout">
		<div class="searchcfg-listbox">
			<div class="searchcfg-toolbar">
				<button type="button" data-role="new">New search service</button>
				<button type="button" data-role="reload">Reload</button>
			</div>

			<table class="searchcfg-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Connection</th>
						<th>Driver</th>
						<th>Model</th>
						<th>Context</th>
						<th>Web</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="9" class="mono">Loading...</td></tr>
				</tbody>
			</table>
		</div>

		<div class="searchcfg-formbox">
			<form data-role="form">
				<h4 data-role="legend">Create search service</h4>

				<div class="searchcfg-hint" data-role="idhint">
					Technical search service id. Agent resources use this id to resolve the configured search service.
				</div>

				<div class="searchcfg-grid">
					<div class="searchcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-id">Search service id</label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-id" name="id" placeholder="openai_websearch" autocomplete="off">
					</div>

					<div class="searchcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name">Name</label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name" name="name" placeholder="OpenAI Web Search" autocomplete="off">
					</div>

					<div class="searchcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connection">Connection</label>
						<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connection" name="connection">
							<option value="">Loading connections...</option>
						</select>
						<div class="searchcfg-hint searchcfg-inline-hint" data-role="connectionhint">
							Connections contain endpoint and authentication data.
						</div>
					</div>

					<div class="searchcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver">Driver</label>
						<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver" name="driver">
							<option value="">Loading drivers...</option>
						</select>
					</div>

					<div class="searchcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model">
							Model <span data-role="modelrequiredlabel" style="font-weight:normal;color:#777">(optional)</span>
						</label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model" name="model" placeholder="optional" autocomplete="off">
						<div class="searchcfg-hint searchcfg-inline-hint" data-role="modelhint">
							Model is optional unless the selected driver requires it.
						</div>
					</div>

					<div class="searchcfg-field searchcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-context">Search context size</label>
							<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-context" name="searchContextSize">
								<option value="">default</option>
								<option value="low">low</option>
								<option value="medium">medium</option>
								<option value="high">high</option>
							</select>
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-toolchoice">Tool choice</label>
							<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-toolchoice" name="toolChoice">
								<option value="">default</option>
								<option value="auto">auto</option>
								<option value="required">required</option>
							</select>
						</div>
					</div>

					<div class="searchcfg-field searchcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-returntokenbudget">Return token budget</label>
							<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-returntokenbudget" name="returnTokenBudget">
								<option value="">default</option>
								<option value="default">default</option>
								<option value="unlimited">unlimited</option>
							</select>
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-maxresults">Max results</label>
							<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-maxresults" name="maxResults" placeholder="10" autocomplete="off">
						</div>
					</div>

					<div class="searchcfg-field searchcfg-field-checkbox">
						<label class="searchcfg-checkbox">
							<input type="checkbox" name="externalWebAccess" checked>
							<span>External web access</span>
						</label>
					</div>

					<div class="searchcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-alloweddomains">Allowed domains</label>
						<textarea id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-alloweddomains" name="allowedDomains" spellcheck="false" placeholder="example.org&#10;openai.com"></textarea>
						<div class="searchcfg-hint searchcfg-inline-hint">
							Optional. One domain per line. Leave empty to allow all domains.
						</div>
					</div>

					<div class="searchcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-blockeddomains">Blocked domains</label>
						<textarea id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-blockeddomains" name="blockedDomains" spellcheck="false" placeholder="example.org"></textarea>
						<div class="searchcfg-hint searchcfg-inline-hint">
							Optional. One domain per line.
						</div>
					</div>

					<div class="searchcfg-field searchcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout">Timeout seconds</label>
							<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout" name="timeoutSeconds" placeholder="120" autocomplete="off">
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout">Connect timeout seconds</label>
							<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout" name="connectTimeoutSeconds" placeholder="" autocomplete="off">
						</div>
					</div>

					<div class="searchcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-options">Advanced options JSON</label>
						<textarea id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-options" name="options" spellcheck="false" placeholder="{&#10;}"></textarea>
						<div class="searchcfg-hint searchcfg-inline-hint">
							Optional provider-specific options. Explicit fields above override duplicate keys in this JSON object.
						</div>
					</div>

					<div class="searchcfg-field searchcfg-field-checkbox">
						<label class="searchcfg-checkbox">
							<input type="checkbox" name="enabled" checked>
							<span>Enabled</span>
						</label>
					</div>
				</div>

				<div data-role="formfeedback" class="searchcfg-form-feedback" style="display:none"></div>

				<div class="searchcfg-actions">
					<button type="submit" class="primary">Save search service</button>
					<button type="button" data-role="delete" disabled>Delete search service</button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
.search-config-admin {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.search-config-admin h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.search-config-admin h4 {
	margin-top: 0;
	margin-bottom: 10px;
	font-size: 1em;
}

.searchcfg-meta {
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

.searchcfg-loading {
	display: none;
	color: #666;
	font-style: italic;
}

.searchcfg-layout {
	display: grid;
	grid-template-columns: minmax(760px, 1fr) minmax(380px, 520px);
	gap: 16px;
	align-items: start;
}

.searchcfg-listbox,
.searchcfg-formbox {
	border: 1px solid #ddd;
	border-radius: 4px;
	background: #fafafa;
	padding: 12px;
}

.searchcfg-toolbar {
	display: flex;
	gap: 8px;
	margin-bottom: 10px;
}

.searchcfg-toolbar button,
.searchcfg-actions button {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	color: #333;
	border-radius: 6px;
	padding: 8px 12px;
	cursor: pointer;
}

.searchcfg-toolbar button:hover,
.searchcfg-actions button:hover {
	background: #e8e8e8;
}

.searchcfg-actions .primary {
	background: #eaf3ff;
	border-color: #aac6ea;
}

.searchcfg-actions .primary:hover {
	background: #dcecff;
}

.searchcfg-actions button[disabled] {
	opacity: 0.5;
	cursor: not-allowed;
}

.searchcfg-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
}

.searchcfg-table th,
.searchcfg-table td {
	padding: 8px 10px;
	border-bottom: 1px solid #e0e0e0;
	vertical-align: middle;
	text-align: left;
	font-size: 13px;
}

.searchcfg-table th {
	background: #f5f5f5;
	font-weight: 600;
	border-bottom: 2px solid #cfcfcf;
}

.searchcfg-table tr:hover td {
	background: #fafafa;
}

.searchcfg-table tr.selected td {
	background: #eef5ff;
}

.searchcfg-table td.id-col,
.searchcfg-table td.model-col,
.searchcfg-table td.connection-col,
.searchcfg-table td.driver-col,
.searchcfg-table td.option-col {
	font-family: Consolas, monospace;
	font-size: 12px;
}

.searchcfg-table td.model-col {
	max-width: 220px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.searchcfg-edit-btn {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	border-radius: 6px;
	padding: 5px 8px;
	cursor: pointer;
	font-size: 12px;
}

.searchcfg-edit-btn:hover {
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

.searchcfg-hint {
	margin-bottom: 12px;
	font-size: 12px;
	color: #666;
}

.searchcfg-inline-hint {
	margin-top: 6px;
	margin-bottom: 0;
}

.searchcfg-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 12px;
}

.searchcfg-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 6px;
	font-size: 13px;
}

.searchcfg-field input[type="text"],
.searchcfg-field select,
.searchcfg-field textarea {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid #cfcfcf;
	border-radius: 6px;
	padding: 8px 10px;
	background: #fff;
	color: #333;
}

.searchcfg-field textarea {
	min-height: 110px;
	font-family: Consolas, monospace;
	font-size: 12px;
	resize: vertical;
}

.searchcfg-field input[readonly] {
	background: #f6f6f6;
	color: #666;
}

.searchcfg-field-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}

.searchcfg-field-checkbox {
	padding-top: 4px;
}

.searchcfg-checkbox {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
}

.searchcfg-form-feedback {
	margin-top: 14px;
	border: 1px solid #ddd;
	border-radius: 6px;
	padding: 9px 11px;
	font-size: 13px;
	line-height: 1.4;
}

.searchcfg-form-feedback.success {
	border-color: #8d8;
	background: #f6fff6;
	color: #2d6b2d;
}

.searchcfg-form-feedback.error {
	border-color: #d88;
	background: #fff5f5;
	color: #a33;
}

.searchcfg-actions {
	display: flex;
	gap: 8px;
	margin-top: 14px;
}

@media (max-width: 1200px) {
	.searchcfg-layout {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 620px) {
	.searchcfg-field-row {
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
			modelrequiredlabel: root.querySelector("[data-role='modelrequiredlabel']"),
			modelhint: root.querySelector("[data-role='modelhint']"),
			newBtn: root.querySelector("[data-role='new']"),
			reloadBtn: root.querySelector("[data-role='reload']"),
			deleteBtn: root.querySelector("[data-role='delete']"),
			id: root.querySelector("input[name='id']"),
			name: root.querySelector("input[name='name']"),
			connection: root.querySelector("select[name='connection']"),
			driver: root.querySelector("select[name='driver']"),
			model: root.querySelector("input[name='model']"),
			searchContextSize: root.querySelector("select[name='searchContextSize']"),
			returnTokenBudget: root.querySelector("select[name='returnTokenBudget']"),
			toolChoice: root.querySelector("select[name='toolChoice']"),
			maxResults: root.querySelector("input[name='maxResults']"),
			externalWebAccess: root.querySelector("input[name='externalWebAccess']"),
			allowedDomains: root.querySelector("textarea[name='allowedDomains']"),
			blockedDomains: root.querySelector("textarea[name='blockedDomains']"),
			timeoutSeconds: root.querySelector("input[name='timeoutSeconds']"),
			connectTimeoutSeconds: root.querySelector("input[name='connectTimeoutSeconds']"),
			options: root.querySelector("textarea[name='options']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		const state = {
			searches: [],
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
			refs.loading.style.display = active ? "block" : "none";
		}

		function setLastUpdate(ts) {
			refs.lastupdate.textContent = ts || "-";
		}

		function showFeedback(message, type) {
			refs.formfeedback.style.display = "block";
			refs.formfeedback.className = "searchcfg-form-feedback " + (type === "error" ? "error" : "success");
			refs.formfeedback.textContent = message;
		}

		function clearFeedback() {
			refs.formfeedback.style.display = "none";
			refs.formfeedback.className = "searchcfg-form-feedback";
			refs.formfeedback.textContent = "";
		}

		function findSearch(id) {
			id = String(id || "");
			return state.searches.find(function(item) {
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

		function isModelRequiredForDriver(driverName) {
			const driver = findDriver(driverName);
			const config = driver && driver.defaultConfig && typeof driver.defaultConfig === "object" ? driver.defaultConfig : {};
			const normalized = String(driverName || "").toLowerCase();

			if (driver && (driver.modelRequired === true || driver.requiresModel === true)) {
				return true;
			}

			if (driver && (driver.modelRequired === false || driver.requiresModel === false)) {
				return false;
			}

			if (config.modelRequired === true || config.requiresModel === true) {
				return true;
			}

			if (config.modelRequired === false || config.requiresModel === false) {
				return false;
			}

			return [
				"openai_websearch",
				"openai-websearch",
				"openai_responses_websearch",
				"openai-responses-websearch",
				"openai_chat_websearch",
				"openai-chat-websearch",
				"openai_search",
				"openai-search"
			].indexOf(normalized) !== -1;
		}

		function updateModelState() {
			const required = isModelRequiredForDriver(refs.driver.value);

			if (refs.modelrequiredlabel) {
				refs.modelrequiredlabel.textContent = required ? "(required)" : "(optional)";
			}

			if (refs.modelhint) {
				refs.modelhint.textContent = required
					? "This driver requires a model. Example: gpt-5.5 or gpt-5-search-api."
					: "This driver does not require a model. Leave empty if the service works without one.";
			}

			if (!refs.model.value) {
				refs.model.placeholder = required ? "gpt-5.5" : "optional";
			}
		}

		function formatOptions(options) {
			const clean = Object.assign({}, options || {});
			delete clean.searchContextSize;
			delete clean.externalWebAccess;
			delete clean.returnTokenBudget;
			delete clean.toolChoice;
			delete clean.maxResults;
			delete clean.allowedDomains;
			delete clean.blockedDomains;
			delete clean.timeoutSeconds;
			delete clean.connectTimeoutSeconds;

			if (Object.keys(clean).length === 0) {
				return "{\n}";
			}

			return JSON.stringify(clean, null, 2);
		}

		function setEditMode(editing) {
			refs.id.readOnly = editing;
			refs.deleteBtn.disabled = !editing;

			if (editing) {
				refs.legend.textContent = "Edit search service";
				refs.idhint.textContent = "Technical search service id is fixed for existing entries. Create a new entry if you need another key.";
			} else {
				refs.legend.textContent = "Create search service";
				refs.idhint.textContent = "Technical search service id. Agent resources use this id to resolve the configured search service.";
			}
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
				option.textContent = "No search drivers available";
				refs.driver.appendChild(option);
				updateModelState();
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
			updateModelState();
		}

		function applyDriverDefaults(force) {
			const driver = findDriver(refs.driver.value);

			if (!driver) {
				updateModelState();
				return;
			}

			const defaults = driver.defaultConfig && typeof driver.defaultConfig === "object" ? driver.defaultConfig : {};
			const options = defaults.options && typeof defaults.options === "object" ? defaults.options : {};

			if (force || !refs.model.value) {
				refs.model.value = defaults.model || "";
			}

			if (force) {
				refs.searchContextSize.value = options.searchContextSize ?? "";
				refs.externalWebAccess.checked = options.externalWebAccess !== false;
				refs.returnTokenBudget.value = options.returnTokenBudget ?? "";
				refs.toolChoice.value = options.toolChoice ?? "";
				refs.maxResults.value = options.maxResults ?? "";
				refs.allowedDomains.value = Array.isArray(options.allowedDomains) ? options.allowedDomains.join("\n") : "";
				refs.blockedDomains.value = Array.isArray(options.blockedDomains) ? options.blockedDomains.join("\n") : "";
				refs.timeoutSeconds.value = options.timeoutSeconds ?? "";
				refs.connectTimeoutSeconds.value = options.connectTimeoutSeconds ?? "";
				refs.options.value = formatOptions(options);
			}

			updateModelState();
		}

		function resetForm() {
			refs.form.reset();
			refs.id.value = "";
			refs.name.value = "";
			refs.connection.value = "";
			refs.driver.value = "";
			refs.model.value = "";
			refs.searchContextSize.value = "";
			refs.returnTokenBudget.value = "";
			refs.toolChoice.value = "";
			refs.maxResults.value = "";
			refs.externalWebAccess.checked = true;
			refs.allowedDomains.value = "";
			refs.blockedDomains.value = "";
			refs.timeoutSeconds.value = "";
			refs.connectTimeoutSeconds.value = "";
			refs.options.value = "{\n}";
			refs.enabled.checked = true;

			state.selectedId = "";
			setEditMode(false);
			updateConnectionHint();
			updateModelState();
			highlightSelection();
		}

		function fillForm(search) {
			if (!search) {
				resetForm();
				return;
			}

			refs.id.value = search.id || "";
			refs.name.value = search.name || "";
			refs.connection.value = search.connection || "";
			refs.driver.value = search.driver || "";
			refs.model.value = search.model || "";
			refs.searchContextSize.value = search.searchContextSize || "";
			refs.externalWebAccess.checked = search.externalWebAccess !== false;
			refs.returnTokenBudget.value = search.returnTokenBudget || "";
			refs.toolChoice.value = search.toolChoice || "";
			refs.maxResults.value = search.maxResults || "";
			refs.allowedDomains.value = search.allowedDomainsText || "";
			refs.blockedDomains.value = search.blockedDomainsText || "";
			refs.timeoutSeconds.value = search.timeoutSeconds || "";
			refs.connectTimeoutSeconds.value = search.connectTimeoutSeconds || "";
			refs.options.value = formatOptions(search.options || {});
			refs.enabled.checked = !!search.enabled;

			state.selectedId = search.id || "";
			setEditMode(true);
			updateConnectionHint();
			updateModelState();
			highlightSelection();
		}

		function statusBadge(search) {
			if (!search.enabled) {
				return "<span class='badge off'>disabled</span>";
			}

			if (!search.connectionEnabled) {
				return "<span class='badge warn'>connection off</span>";
			}

			return "<span class='badge ok'>enabled</span>";
		}

		function renderRows() {
			const searches = Array.isArray(state.searches) ? state.searches : [];
			refs.tbody.innerHTML = "";

			if (searches.length === 0) {
				refs.tbody.innerHTML = "<tr><td colspan='9' class='mono'>No search services configured.</td></tr>";
				return;
			}

			for (const search of searches) {
				const tr = document.createElement("tr");
				tr.setAttribute("data-id", String(search.id || ""));

				tr.innerHTML =
					"<td class='id-col'>" + esc(search.id) + "</td>" +
					"<td>" + esc(search.name) + "</td>" +
					"<td class='connection-col' title='" + esc(connectionLabel(search.connection)) + "'>" +
						esc(search.connection) + "<br><span style='color:#777'>" + esc(search.connectionType || "") + "</span>" +
					"</td>" +
					"<td class='driver-col'>" + esc(search.driverLabel || search.driver) + "</td>" +
					"<td class='model-col' title='" + esc(search.model) + "'>" + esc(search.model) + "</td>" +
					"<td class='option-col'>" + esc(search.searchContextSize || "") + "</td>" +
					"<td class='option-col'>" + (search.externalWebAccess === false ? "cache" : "live") + "</td>" +
					"<td>" + statusBadge(search) + "</td>" +
					"<td><button type='button' class='searchcfg-edit-btn' data-action='edit' data-id='" + esc(search.id) + "'>Edit</button></td>";

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
				refs.tbody.innerHTML = "<tr><td colspan='9' class='mono'>Search services could not be loaded.</td></tr>";
				return;
			}

			state.connections = (json.data && Array.isArray(json.data.connections)) ? json.data.connections : [];
			state.drivers = (json.data && Array.isArray(json.data.drivers)) ? json.data.drivers : [];
			state.searches = (json.data && Array.isArray(json.data.searches)) ? json.data.searches : [];

			renderConnectionOptions(refs.connection.value || "");
			renderDriverOptions(refs.driver.value || "");
			renderRows();

			if (preselectId) {
				const search = findSearch(preselectId);
				if (search) {
					fillForm(search);
					return;
				}
			}

			if (state.selectedId) {
				const search = findSearch(state.selectedId);
				if (search) {
					fillForm(search);
					return;
				}
			}

			if (state.searches.length === 0) {
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
			const options = readOptionsJson();

			if (options === null) {
				return;
			}

			refs.id.value = id;
			refs.connection.value = connection;
			refs.driver.value = driver;

			if (!id) {
				showFeedback("Search service id is required.", "error");
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

			if (!model && isModelRequiredForDriver(driver)) {
				showFeedback("Model is required for the selected driver.", "error");
				return;
			}

			const json = await callApi({
				action: "save",
				id: id,
				name: name,
				connection: connection,
				driver: driver,
				model: model,
				searchContextSize: String(refs.searchContextSize.value || "").trim(),
				externalWebAccess: refs.externalWebAccess.checked ? "1" : "0",
				returnTokenBudget: String(refs.returnTokenBudget.value || "").trim(),
				toolChoice: String(refs.toolChoice.value || "").trim(),
				maxResults: String(refs.maxResults.value || "").trim(),
				allowedDomains: String(refs.allowedDomains.value || "").trim(),
				blockedDomains: String(refs.blockedDomains.value || "").trim(),
				timeoutSeconds: String(refs.timeoutSeconds.value || "").trim(),
				connectTimeoutSeconds: String(refs.connectTimeoutSeconds.value || "").trim(),
				options: options,
				enabled: refs.enabled.checked ? "1" : "0"
			});

			if (!json) {
				return;
			}

			const search = (json.data && json.data.search) ? json.data.search : null;

			showFeedback("Search service saved.", "success");

			await loadList(search && search.id ? search.id : id);
		}

		async function removeCurrent() {
			clearFeedback();

			const id = String(state.selectedId || refs.id.value || "").trim();

			if (!id) {
				showFeedback("No search service selected.", "error");
				return;
			}

			if (!window.confirm("Delete search service '" + id + "'?")) {
				return;
			}

			const json = await callApi({
				action: "remove",
				id: id
			});

			if (!json) {
				return;
			}

			showFeedback("Search service deleted.", "success");

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
			updateModelState();
		});

		refs.tbody.addEventListener("click", function(e) {
			const btn = e.target.closest("button[data-action='edit']");
			if (!btn) {
				return;
			}

			clearFeedback();

			const search = findSearch(btn.getAttribute("data-id"));
			fillForm(search);
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
