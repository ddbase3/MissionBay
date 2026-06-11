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
						<th>Driver</th>
						<th>Base URL</th>
						<th>Auth</th>
						<th>Secret</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="8" class="mono">Loading...</td></tr>
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
							<label>Secret source</label>
							<select name="secretMode"><option value="fixed">fixed</option></select>
						</div>
						<div class="ccad-keepsecret" data-role="keepsecretwrap" style="display:none">
							<label class="ccad-checkbox ccad-keepsecret-label">
								<input type="checkbox" name="keepSecret">
								<span>Keep existing fixed secret</span>
							</label>
						</div>
					</div>

					<div data-role="secretfields" class="ccad-secret-fields"></div>

					<div class="ccad-hint ccad-inline-hint" data-role="secrethint">
						Select how the authentication secret should be resolved at runtime.
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

				<div data-role="formfeedback" class="ccad-form-feedback" style="display:none"></div>

				<div class="ccad-actions">
					<button type="submit" class="primary">Save connection</button>
					<button type="button" data-role="delete" disabled>Delete connection</button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
.connection-config-admin{background:#fff;border:1px solid #d6d6d6;padding:16px;border-radius:4px;max-width:100%;font-family:Arial,sans-serif;color:#333}.connection-config-admin h3{margin-top:0;margin-bottom:12px;font-size:1.1em}.connection-config-admin h4{margin-top:0;margin-bottom:10px;font-size:1em}.ccad-meta{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:13px;color:#555}.mono{font-family:Consolas,monospace}.ccad-loading{display:none;color:#666;font-style:italic}.ccad-layout{display:grid;grid-template-columns:minmax(720px,1fr) minmax(380px,520px);gap:16px;align-items:start}.ccad-listbox,.ccad-formbox{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:12px}.ccad-toolbar{display:flex;gap:8px;margin-bottom:10px}.ccad-toolbar button,.ccad-actions button{border:1px solid #c9c9c9;background:#f1f1f1;color:#333;border-radius:6px;padding:8px 12px;cursor:pointer}.ccad-toolbar button:hover,.ccad-actions button:hover{background:#e8e8e8}.ccad-actions .primary{background:#eaf3ff;border-color:#aac6ea}.ccad-actions .primary:hover{background:#dcecff}.ccad-actions button[disabled]{opacity:.5;cursor:not-allowed}.ccad-table{width:100%;border-collapse:collapse;background:#fff;table-layout:fixed}.ccad-table th,.ccad-table td{padding:8px 10px;border-bottom:1px solid #e0e0e0;vertical-align:middle;text-align:left;font-size:13px}.ccad-table th{background:#f5f5f5;font-weight:600;border-bottom:2px solid #cfcfcf}.ccad-table th:nth-child(1){width:80px}.ccad-table th:nth-child(2){width:100px}.ccad-table th:nth-child(3){width:60px}.ccad-table th:nth-child(4){width:190px}.ccad-table th:nth-child(5){width:110px}.ccad-table th:nth-child(6){width:120px}.ccad-table th:nth-child(7){width:80px}.ccad-table th:nth-child(8){width:58px}.ccad-table tr.selected td{background:#eef5ff}.ccad-table td.id-col,.ccad-table td.kind-col,.ccad-table td.url-col,.ccad-table td.auth-col,.ccad-table td.secret-col{font-family:Consolas,monospace;font-size:12px}.ccad-table td.url-col{max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ccad-table td.kind-col,.ccad-table td.auth-col,.ccad-table td.secret-col{line-height:1.35}.ccad-subline{color:#777}.ccad-edit-btn{border:1px solid #c9c9c9;background:#f1f1f1;border-radius:6px;padding:5px 8px;cursor:pointer;font-size:12px}.ccad-edit-btn:hover{background:#e8e8e8}.badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ccc;background:#f6f6f6;color:#333;font-size:12px;white-space:nowrap}.badge.ok{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.badge.off{border-color:#d7c17a;background:#fff8df;color:#876c11}.ccad-hint{margin-bottom:12px;font-size:12px;color:#666}.ccad-inline-hint{margin-top:6px;margin-bottom:0}.ccad-grid{display:grid;grid-template-columns:1fr;gap:12px}.ccad-field label{display:block;font-weight:600;margin-bottom:6px;font-size:13px}.ccad-field input[type=text],.ccad-field select,.ccad-field textarea,.ccad-secret-field input[type=text],.ccad-secret-field textarea,.ccad-secret-field select{width:100%;box-sizing:border-box;border:1px solid #cfcfcf;border-radius:6px;padding:8px 10px;background:#fff;color:#333}.ccad-field input[readonly],.ccad-field input[disabled],.ccad-field select[disabled],.ccad-secret-field input[disabled],.ccad-secret-field select[disabled]{background:#f6f6f6;color:#666}.ccad-field textarea,.ccad-secret-field textarea{min-height:150px;font-family:Consolas,monospace;font-size:12px;resize:vertical}.ccad-field-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.ccad-field-checkbox{padding-top:4px}.ccad-checkbox{display:inline-flex;align-items:center;gap:8px;font-weight:600}.ccad-actions{display:flex;gap:8px;margin-top:14px}.ccad-secret-fields{display:grid;grid-template-columns:1fr;gap:10px}.ccad-secret-field label{display:block;font-weight:600;margin-bottom:6px;font-size:13px}.ccad-secret-field .ccad-description{font-size:12px;color:#777;margin-top:5px}.ccad-keepsecret{display:flex;align-items:end;padding-top:23px}.ccad-keepsecret-label{border:1px solid #ddd;background:#fff;border-radius:6px;padding:8px 10px;box-sizing:border-box;width:100%}.ccad-form-feedback{margin-top:14px;border:1px solid #ddd;border-radius:6px;padding:9px 11px;font-size:13px;line-height:1.4}.ccad-form-feedback.success{border-color:#8d8;background:#f6fff6;color:#2d6b2d}.ccad-form-feedback.error{border-color:#d88;background:#fff5f5;color:#a33}@media (max-width:1200px){.ccad-layout{grid-template-columns:1fr}}@media (max-width:620px){.ccad-field-row{grid-template-columns:1fr}}
</style>

<script>
(function() {
	var instanceId = <?php echo json_encode((string)$this->_['instanceId']); ?>;
	var endpointBase = <?php echo json_encode((string)$this->_['endpoint']); ?>;
	var configGroup = <?php echo json_encode((string)$this->_['configGroup']); ?>;

	function init() {
		var root = document.getElementById(instanceId);

		if(!root || root.dataset.initialized === "1") {
			return;
		}

		root.dataset.initialized = "1";

		var refs = {
			loading: root.querySelector("[data-role='loading']"),
			lastupdate: root.querySelector("[data-role='lastupdate']"),
			formfeedback: root.querySelector("[data-role='formfeedback']"),
			tbody: root.querySelector("[data-role='tbody']"),
			form: root.querySelector("[data-role='form']"),
			legend: root.querySelector("[data-role='legend']"),
			idhint: root.querySelector("[data-role='idhint']"),
			secrethint: root.querySelector("[data-role='secrethint']"),
			secretfields: root.querySelector("[data-role='secretfields']"),
			keepSecretWrap: root.querySelector("[data-role='keepsecretwrap']"),
			newBtn: root.querySelector("[data-role='new']"),
			reloadBtn: root.querySelector("[data-role='reload']"),
			deleteBtn: root.querySelector("[data-role='delete']"),
			id: root.querySelector("input[name='id']"),
			name: root.querySelector("input[name='name']"),
			driver: root.querySelector("select[name='driver']"),
			type: root.querySelector("input[name='type']"),
			baseUrl: root.querySelector("input[name='baseUrl']"),
			authType: root.querySelector("select[name='authType']"),
			authHeaderName: root.querySelector("input[name='authHeaderName']"),
			secretMode: root.querySelector("select[name='secretMode']"),
			keepSecret: root.querySelector("input[name='keepSecret']"),
			timeoutSeconds: root.querySelector("input[name='timeoutSeconds']"),
			scope: root.querySelector("input[name='scope']"),
			options: root.querySelector("textarea[name='options']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		var fallbackSchemas = {
			fixed: {
				type: "object",
				properties: {
					value: {
						type: "string",
						description: "Static value returned by the resolver."
					}
				},
				required: ["value"]
			},
			env: {
				type: "object",
				properties: {
					name: {
						type: "string",
						description: "Environment variable name."
					}
				},
				required: ["name"]
			}
		};

		var state = {
			connections: [],
			drivers: [],
			configValueModes: [],
			configValueModeSchemas: {},
			selectedId: ""
		};

		function esc(s) {
			var map = {
				"&": "&amp;",
				"<": "&lt;",
				">": "&gt;",
				'"': "&quot;",
				"'": "&#039;"
			};

			return String(s === null || s === undefined ? "" : s).replace(/[&<>"']/g, function(c) {
				return map[c];
			});
		}

		function normalizeKey(s) {
			s = String(s === null || s === undefined ? "" : s).trim().toLowerCase();
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
			refs.formfeedback.className = "ccad-form-feedback " + (type === "error" ? "error" : "success");
			refs.formfeedback.textContent = message;
		}

		function clearFeedback() {
			refs.formfeedback.style.display = "none";
			refs.formfeedback.className = "ccad-form-feedback";
			refs.formfeedback.textContent = "";
		}

		function findDriver(driver) {
			var i;

			for(i = 0; i < state.drivers.length; i++) {
				if(String(state.drivers[i].driver || "") === String(driver || "")) {
					return state.drivers[i];
				}
			}

			return null;
		}

		function findConnection(id) {
			var i;

			for(i = 0; i < state.connections.length; i++) {
				if(String(state.connections[i].id || "") === String(id || "")) {
					return state.connections[i];
				}
			}

			return null;
		}

		function formatOptions(options) {
			if(options && typeof options === "object" && !Array.isArray(options) && Object.keys(options).length > 0) {
				return JSON.stringify(options, null, 2);
			}

			return "{\n}";
		}

		function setEditMode(editing) {
			refs.id.readOnly = editing;
			refs.deleteBtn.disabled = !editing;
			refs.legend.textContent = editing ? "Edit connection" : "Create connection";
			refs.idhint.textContent = editing
				? "Technical connection id is fixed for existing entries. Create a new entry if you need another key."
				: "Technical connection id. This becomes the fixed lookup key used by service configurations.";
		}

		function getModes() {
			var modes = [];
			var i;

			for(i = 0; i < state.configValueModes.length; i++) {
				if(normalizeKey(state.configValueModes[i]) !== "") {
					modes.push(normalizeKey(state.configValueModes[i]));
				}
			}

			if(modes.indexOf("fixed") === -1) {
				modes.push("fixed");
			}

			if(modes.indexOf("env") === -1) {
				modes.push("env");
			}

			return modes;
		}

		function getFirstSecretMode(preferred) {
			var modes = getModes();
			var normalized = normalizeKey(preferred);

			if(normalized !== "" && modes.indexOf(normalized) !== -1) {
				return normalized;
			}

			if(modes.indexOf("fixed") !== -1) {
				return "fixed";
			}

			return modes.length > 0 ? modes[0] : "fixed";
		}

		function getModeSchema(mode) {
			mode = normalizeKey(mode);

			if(state.configValueModeSchemas && typeof state.configValueModeSchemas === "object" && state.configValueModeSchemas[mode]) {
				return state.configValueModeSchemas[mode];
			}

			if(fallbackSchemas[mode]) {
				return fallbackSchemas[mode];
			}

			return {
				type: "object",
				properties: {},
				required: []
			};
		}

		function getSchemaProperties(schema) {
			if(schema && schema.properties && typeof schema.properties === "object" && !Array.isArray(schema.properties)) {
				return schema.properties;
			}

			return {};
		}

		function getRequiredFields(schema) {
			if(schema && Array.isArray(schema.required)) {
				return schema.required;
			}

			return [];
		}

		function isRequiredField(schema, key) {
			return getRequiredFields(schema).indexOf(key) !== -1;
		}

		function humanizeKey(key) {
			return String(key || "").replace(/[_-]+/g, " ").replace(/\b\w/g, function(c) {
				return c.toUpperCase();
			});
		}

		function renderSecretModeOptions(selected) {
			var modes = getModes();
			var selectedMode = getFirstSecretMode(selected);
			var i;
			var option;

			refs.secretMode.innerHTML = "";

			for(i = 0; i < modes.length; i++) {
				option = document.createElement("option");
				option.value = modes[i];
				option.textContent = modes[i];
				refs.secretMode.appendChild(option);
			}

			refs.secretMode.value = selectedMode;
		}

		function normalizeSecretConfig(config) {
			var out = {};
			var key;

			if(!config || typeof config !== "object" || Array.isArray(config)) {
				return {
					mode: getFirstSecretMode("")
				};
			}

			for(key in config) {
				if(Object.prototype.hasOwnProperty.call(config, key)) {
					out[key] = config[key];
				}
			}

			out.mode = getFirstSecretMode(out.mode || "");

			return out;
		}

		function getConnectionSecretConfig(connection) {
			var config = {};

			if(connection && connection.authSecretConfig && typeof connection.authSecretConfig === "object" && !Array.isArray(connection.authSecretConfig)) {
				config = normalizeSecretConfig(connection.authSecretConfig);
				config.mode = getFirstSecretMode(config.mode || connection.authSecretMode || connection.secretMode || "");
				return config;
			}

			return {
				mode: getFirstSecretMode(connection && connection.authSecretMode ? connection.authSecretMode : "")
			};
		}

		function getDefaultSecretConfig(auth) {
			if(auth && auth.secret && typeof auth.secret === "object" && !Array.isArray(auth.secret)) {
				return normalizeSecretConfig(auth.secret);
			}

			return {
				mode: getFirstSecretMode(auth && auth.secretMode ? auth.secretMode : "fixed")
			};
		}

		function renderSecretFields(config, keepExisting) {
			var authType = normalizeKey(refs.authType.value);
			var mode;
			var schema;
			var properties;
			var keys;
			var i;

			config = normalizeSecretConfig(config);
			renderSecretModeOptions(config.mode);

			refs.secretfields.innerHTML = "";
			refs.keepSecret.checked = !!keepExisting && normalizeKey(config.mode) === "fixed";
			refs.keepSecretWrap.style.display = "none";

			if(authType === "none") {
				refs.secretMode.disabled = true;
				refs.authHeaderName.disabled = true;
				refs.keepSecret.checked = false;
				refs.secrethint.textContent = "No secret is used when auth type is none.";
				return;
			}

			refs.secretMode.disabled = false;
			refs.authHeaderName.disabled = false;

			if(authType === "bearer" && !refs.authHeaderName.value) {
				refs.authHeaderName.value = "Authorization";
			}

			mode = normalizeKey(refs.secretMode.value);
			schema = getModeSchema(mode);
			properties = getSchemaProperties(schema);
			keys = Object.keys(properties);

			if(mode === "fixed" && !!keepExisting) {
				refs.keepSecretWrap.style.display = "flex";
			}

			for(i = 0; i < keys.length; i++) {
				renderSecretField(mode, keys[i], properties[keys[i]] || {}, config[keys[i]], schema);
			}

			if(keys.length === 0) {
				renderUnknownSecretJson(config);
			}

			updateSecretHint(mode);
		}

		function renderSecretField(mode, key, property, value, schema) {
			var wrap = document.createElement("div");
			var label = document.createElement("label");
			var checkboxLabel;
			var input;
			var span;
			var description;

			wrap.className = "ccad-secret-field";
			label.textContent = humanizeKey(key) + (isRequiredField(schema, key) ? " *" : "");
			wrap.appendChild(label);

			if(property.type === "boolean") {
				checkboxLabel = document.createElement("label");
				checkboxLabel.className = "ccad-checkbox ccad-keepsecret-label";

				input = document.createElement("input");
				input.type = "checkbox";
				input.setAttribute("data-secret-field", key);
				input.checked = value === undefined ? property.default === true : toBool(value, false);

				span = document.createElement("span");
				span.textContent = property.description || humanizeKey(key);

				checkboxLabel.appendChild(input);
				checkboxLabel.appendChild(span);
				wrap.appendChild(checkboxLabel);
				refs.secretfields.appendChild(wrap);
				return;
			}

			input = document.createElement("input");
			input.type = "text";
			input.setAttribute("data-secret-field", key);
			input.autocomplete = "off";

			if(mode === "fixed" && key === "value" && refs.keepSecret.checked) {
				input.value = "";
				input.placeholder = "Leave empty to keep the existing stored secret.";
			}
			else {
				input.value = value === undefined || value === null ? "" : String(value);
				input.placeholder = property.description || "";
			}

			wrap.appendChild(input);

			if(property.description) {
				description = document.createElement("div");
				description.className = "ccad-description";
				description.textContent = property.description;
				wrap.appendChild(description);
			}

			refs.secretfields.appendChild(wrap);
		}

		function renderUnknownSecretJson(config) {
			var wrap = document.createElement("div");
			var label = document.createElement("label");
			var textarea = document.createElement("textarea");
			var payload = {};
			var key;

			wrap.className = "ccad-secret-field";
			label.textContent = "Mode payload JSON";
			wrap.appendChild(label);

			for(key in config) {
				if(Object.prototype.hasOwnProperty.call(config, key) && key !== "mode") {
					payload[key] = config[key];
				}
			}

			textarea.setAttribute("data-secret-json", "1");
			textarea.spellcheck = false;
			textarea.value = JSON.stringify(payload, null, 2);

			wrap.appendChild(textarea);
			refs.secretfields.appendChild(wrap);
		}

		function updateSecretHint(mode) {
			if(mode === "fixed") {
				refs.secrethint.textContent = refs.keepSecret.checked
					? "The existing stored secret will be kept. Enter a new value only if you want to replace it."
					: "Enter the API key directly. The value will be stored in the SettingsStore.";
				return;
			}

			if(mode === "env") {
				refs.secrethint.textContent = "Enter the environment variable name. The runtime resolves it when the connection is used.";
				return;
			}

			if(mode === "configuration") {
				refs.secrethint.textContent = "Enter the BASE3 configuration group and key. The runtime resolves the value through the configuration service.";
				return;
			}

			if(mode === "file") {
				refs.secrethint.textContent = "Enter the file path. The runtime reads the secret from this file.";
				return;
			}

			refs.secrethint.textContent = "This secret source is provided by a config value mode resolver.";
		}

		function updateAuthUi() {
			var config = readRenderedSecretConfig(false);

			if(!config) {
				config = {
					mode: getFirstSecretMode(refs.secretMode.value)
				};
			}

			renderSecretFields(config, refs.keepSecret.checked);
		}

		function renderDriverOptions(selected) {
			var i;
			var option;
			var driver;

			refs.driver.innerHTML = "";

			if(state.drivers.length === 0) {
				option = document.createElement("option");
				option.value = "";
				option.textContent = "No drivers available";
				refs.driver.appendChild(option);
				return;
			}

			for(i = 0; i < state.drivers.length; i++) {
				driver = state.drivers[i];
				option = document.createElement("option");
				option.value = driver.driver || "";
				option.textContent = (driver.label || driver.driver || "") + " (" + (driver.type || "unknown") + ")";
				refs.driver.appendChild(option);
			}

			refs.driver.value = selected || refs.driver.value || state.drivers[0].driver || "";
			applyDriverDefaults(false);
		}

		function applyDriverDefaults(force) {
			var driver = findDriver(refs.driver.value);
			var defaults;
			var auth;

			if(!driver) {
				return;
			}

			defaults = driver.defaultConfig && typeof driver.defaultConfig === "object" ? driver.defaultConfig : {};
			auth = defaults.auth && typeof defaults.auth === "object" ? defaults.auth : {};

			refs.type.value = driver.type || defaults.type || refs.type.value || "";

			if(force) {
				refs.authType.value = auth.type || "bearer";
				refs.authHeaderName.value = auth.headerName || "";
				refs.timeoutSeconds.value = defaults.timeoutSeconds || "60";
				refs.scope.value = defaults.scope || "global";
				refs.enabled.checked = defaults.enabled !== false;
				refs.options.value = formatOptions(defaults.options || {});
				renderSecretFields(getDefaultSecretConfig(auth), false);
				return;
			}

			updateAuthUi();
		}

		function resetForm() {
			refs.form.reset();
			refs.id.value = "";
			refs.name.value = "";
			refs.baseUrl.value = "";
			refs.authHeaderName.value = "";
			refs.authType.value = "bearer";
			refs.timeoutSeconds.value = "60";
			refs.scope.value = "global";
			refs.options.value = "{\n}";
			refs.enabled.checked = true;
			state.selectedId = "";
			setEditMode(false);
			renderDriverOptions("");
			applyDriverDefaults(true);
			highlightSelection();
		}

		function fillForm(connection) {
			var config;
			var keepExisting;

			if(!connection) {
				resetForm();
				return;
			}

			refs.id.value = connection.id || "";
			refs.name.value = connection.name || "";
			refs.driver.value = connection.driver || "";
			refs.type.value = connection.type || "";
			refs.baseUrl.value = connection.baseUrl || "";
			refs.authType.value = connection.authType || "none";
			refs.authHeaderName.value = connection.authHeaderName || "";
			refs.timeoutSeconds.value = connection.timeoutSeconds || "60";
			refs.scope.value = connection.scope || "global";
			refs.options.value = formatOptions(connection.options || {});
			refs.enabled.checked = !!connection.enabled;
			state.selectedId = connection.id || "";

			config = getConnectionSecretConfig(connection);
			keepExisting = normalizeKey(config.mode) === "fixed" && !!connection.authSecretConfigured && String(config.value || "").trim() === "";

			setEditMode(true);
			renderSecretFields(config, keepExisting);
			highlightSelection();
		}

		function statusBadge(enabled) {
			return enabled ? "<span class='badge ok'>enabled</span>" : "<span class='badge off'>disabled</span>";
		}

		function renderRows() {
			var i;
			var connection;
			var tr;
			var driverText;
			var headerText;
			var secretMode;
			var secretSummary;

			refs.tbody.innerHTML = "";

			if(state.connections.length === 0) {
				refs.tbody.innerHTML = "<tr><td colspan='8' class='mono'>No connections configured.</td></tr>";
				return;
			}

			for(i = 0; i < state.connections.length; i++) {
				connection = state.connections[i];
				tr = document.createElement("tr");
				driverText = connection.driverLabel || connection.driver || "";
				headerText = connection.authHeaderName || "";
				secretMode = connection.authSecretMode || connection.secretMode || "";
				secretSummary = connection.authSecretSummary || (connection.authSecretConfigured ? "configured" : "");

				tr.setAttribute("data-id", String(connection.id || ""));
				tr.innerHTML =
					"<td class='id-col'>" + esc(connection.id) + "</td>" +
					"<td>" + esc(connection.name) + "</td>" +
					"<td class='kind-col'><span>" + esc(connection.type) + "</span><br><span class='ccad-subline'>" + esc(driverText) + "</span></td>" +
					"<td class='url-col' title='" + esc(connection.baseUrl) + "'>" + esc(connection.baseUrl) + "</td>" +
					"<td class='auth-col'><span>" + esc(connection.authType) + "</span><br><span class='ccad-subline'>" + esc(headerText) + "</span></td>" +
					"<td class='secret-col' title='" + esc(secretSummary) + "'><span>" + esc(secretMode || "none") + "</span><br><span class='ccad-subline'>" + esc(secretSummary) + "</span></td>" +
					"<td>" + statusBadge(!!connection.enabled) + "</td>" +
					"<td><button type='button' class='ccad-edit-btn' data-action='edit' data-id='" + esc(connection.id) + "'>Edit</button></td>";

				refs.tbody.appendChild(tr);
			}

			highlightSelection();
		}

		function highlightSelection() {
			var rows = root.querySelectorAll("tbody tr[data-id]");
			var i;

			for(i = 0; i < rows.length; i++) {
				if(rows[i].getAttribute("data-id") === state.selectedId) {
					rows[i].classList.add("selected");
				}
				else {
					rows[i].classList.remove("selected");
				}
			}
		}

		function callApi(params) {
			var body;

			if(!endpointBase) {
				showFeedback("The request endpoint is missing. The connection cannot be loaded or saved.", "error");
				return Promise.resolve(null);
			}

			setLoading(true);

			body = new URLSearchParams();

			Object.keys(params || {}).forEach(function(key) {
				body.append(key, params[key]);
			});

			return fetch(endpointBase, {
				method: "POST",
				headers: {
					"Accept": "application/json",
					"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
				},
				body: body.toString()
			}).then(function(response) {
				return response.text();
			}).then(function(text) {
				var json = null;

				try {
					json = JSON.parse(text);
				}
				catch(e) {
					showFeedback("The server response could not be read.", "error");
					return null;
				}

				setLastUpdate(json.timestamp || "");

				if(json.status !== "ok") {
					showFeedback(json.message || "The request could not be completed.", "error");
					return null;
				}

				return json;
			}).catch(function() {
				showFeedback("The request failed. Please try again.", "error");
				return null;
			}).finally(function() {
				setLoading(false);
			});
		}

		function loadList(preselectId) {
			callApi({
				action: "list"
			}).then(function(json) {
				var selected;

				if(!json) {
					refs.tbody.innerHTML = "<tr><td colspan='8' class='mono'>Connections could not be loaded.</td></tr>";
					return;
				}

				state.drivers = json.data && Array.isArray(json.data.drivers) ? json.data.drivers : [];
				state.connections = json.data && Array.isArray(json.data.connections) ? json.data.connections : [];
				state.configValueModes = json.data && Array.isArray(json.data.configValueModes) ? json.data.configValueModes : [];
				state.configValueModeSchemas = json.data && json.data.configValueModeSchemas && typeof json.data.configValueModeSchemas === "object" ? json.data.configValueModeSchemas : {};

				renderDriverOptions(refs.driver.value || "");
				renderRows();

				selected = findConnection(preselectId || state.selectedId);

				if(selected) {
					fillForm(selected);
				}
				else if(state.connections.length === 0) {
					resetForm();
				}
				else {
					updateAuthUi();
				}
			});
		}

		function readOptionsJson() {
			var raw = String(refs.options.value || "").trim();
			var parsed;

			if(!raw) {
				return "{}";
			}

			try {
				parsed = JSON.parse(raw);

				if(!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
					showFeedback("Options must be a JSON object.", "error");
					return null;
				}

				return JSON.stringify(parsed);
			}
			catch(e) {
				showFeedback("Options must be valid JSON.", "error");
				return null;
			}
		}

		function readRenderedSecretConfig(validate) {
			var authType = normalizeKey(refs.authType.value);
			var mode;
			var schema;
			var config;
			var jsonField;
			var payload;
			var fields;
			var i;
			var key;

			if(authType === "none") {
				return {};
			}

			mode = normalizeKey(refs.secretMode.value || getFirstSecretMode(""));
			schema = getModeSchema(mode);
			config = {
				mode: mode
			};

			jsonField = refs.secretfields.querySelector("[data-secret-json]");

			if(jsonField) {
				try {
					payload = JSON.parse(String(jsonField.value || "{}"));

					if(!payload || typeof payload !== "object" || Array.isArray(payload)) {
						if(validate) {
							showFeedback("Secret payload must be a JSON object.", "error");
						}

						return null;
					}

					for(key in payload) {
						if(Object.prototype.hasOwnProperty.call(payload, key)) {
							config[key] = payload[key];
						}
					}
				}
				catch(e) {
					if(validate) {
						showFeedback("Secret payload must be valid JSON.", "error");
					}

					return null;
				}
			}

			fields = refs.secretfields.querySelectorAll("[data-secret-field]");

			for(i = 0; i < fields.length; i++) {
				key = fields[i].getAttribute("data-secret-field") || "";

				if(!key) {
					continue;
				}

				if(fields[i].type === "checkbox") {
					config[key] = !!fields[i].checked;
				}
				else {
					config[key] = String(fields[i].value || "").trim();
				}
			}

			if(mode === "fixed" && refs.keepSecret.checked && String(config.value || "").trim() === "") {
				config.configured = true;
				return config;
			}

			if(validate && !validateSecretConfig(config, schema)) {
				return null;
			}

			return config;
		}

		function validateSecretConfig(config, schema) {
			var required = getRequiredFields(schema);
			var i;
			var key;
			var value;

			for(i = 0; i < required.length; i++) {
				key = required[i];
				value = config[key];

				if(value === undefined || value === null || String(value).trim() === "") {
					showFeedback("Please fill the required secret field: " + humanizeKey(key) + ".", "error");
					return false;
				}
			}

			return true;
		}

		function toBool(value, defaultValue) {
			value = value === undefined ? null : value;

			if(value === null || value === "") {
				return defaultValue;
			}

			if(typeof value === "boolean") {
				return value;
			}

			if(typeof value === "number") {
				return value !== 0;
			}

			return ["1", "true", "yes", "on"].indexOf(String(value).trim().toLowerCase()) !== -1;
		}

		function saveCurrent() {
			var id = normalizeKey(refs.id.value);
			var name = String(refs.name.value || "").trim();
			var driver = normalizeKey(refs.driver.value);
			var type = normalizeKey(refs.type.value);
			var baseUrl = String(refs.baseUrl.value || "").trim();
			var authType = normalizeKey(refs.authType.value);
			var authHeaderName = String(refs.authHeaderName.value || "").trim();
			var timeoutSeconds = String(refs.timeoutSeconds.value || "").trim();
			var scope = normalizeKey(refs.scope.value || "global");
			var options = readOptionsJson();
			var secretConfig = readRenderedSecretConfig(true);

			clearFeedback();

			if(options === null || secretConfig === null) {
				return;
			}

			refs.id.value = id;
			refs.driver.value = driver;
			refs.type.value = type;
			refs.scope.value = scope;

			if(!id || !name || !driver || !type) {
				showFeedback("Connection id, name, driver and type are required.", "error");
				return;
			}

			if(type === "http" && !baseUrl) {
				showFeedback("Base URL is required for HTTP connections.", "error");
				return;
			}

			callApi({
				action: "save",
				id: id,
				name: name,
				driver: driver,
				type: type,
				baseUrl: baseUrl,
				authType: authType,
				authHeaderName: authHeaderName,
				secretConfig: JSON.stringify(secretConfig),
				keepSecret: refs.keepSecret.checked ? "1" : "0",
				timeoutSeconds: timeoutSeconds,
				scope: scope,
				enabled: refs.enabled.checked ? "1" : "0",
				options: options
			}).then(function(json) {
				var connection;

				if(!json) {
					return;
				}

				connection = json.data && json.data.connection ? json.data.connection : null;
				showFeedback("Connection saved.", "success");
				loadList(connection && connection.id ? connection.id : id);
			});
		}

		function removeCurrent() {
			var id = String(state.selectedId || refs.id.value || "").trim();

			clearFeedback();

			if(!id) {
				showFeedback("No connection selected.", "error");
				return;
			}

			if(!window.confirm("Delete connection '" + id + "'?")) {
				return;
			}

			callApi({
				action: "remove",
				id: id
			}).then(function(json) {
				if(!json) {
					return;
				}

				showFeedback("Connection deleted.", "success");
				resetForm();
				loadList();
			});
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

		refs.driver.addEventListener("change", function() {
			applyDriverDefaults(false);
		});

		refs.authType.addEventListener("change", function() {
			updateAuthUi();
		});

		refs.secretMode.addEventListener("change", function() {
			renderSecretFields({
				mode: refs.secretMode.value
			}, false);
		});

		refs.keepSecret.addEventListener("change", function() {
			updateAuthUi();
		});

		refs.tbody.addEventListener("click", function(e) {
			var btn = e.target.closest("button[data-action='edit']");

			if(!btn) {
				return;
			}

			clearFeedback();
			fillForm(findConnection(btn.getAttribute("data-id")));
		});

		loadList();
	}

	if(document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	}
	else {
		init();
	}
})();
</script>
