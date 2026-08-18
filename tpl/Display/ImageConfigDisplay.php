<?php
$this->loadBricks('Administration');
$mbUiText = is_array($this->_['bricks']['missionbay_admin'] ?? null) ? $this->_['bricks']['missionbay_admin'] : [];
$mbText = static fn(string $key, string $fallback): string => trim((string)($mbUiText[$key] ?? '')) !== '' ? (string)$mbUiText[$key] : $fallback;
$mbTextEsc = static fn(string $key, string $fallback): string => htmlspecialchars($mbText($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="image-config-admin">
	<h3><?php echo $mbTextEsc('image_generation_services', 'Image Generation Services'); ?></h3>

	<div class="imgcfg-meta">
		<div><strong><?php echo $mbTextEsc('settings_group', 'Settings group:'); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong><?php echo $mbTextEsc('connection_group', 'Connection group:'); ?></strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong><?php echo $mbTextEsc('last_update', 'Last update:'); ?></strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="imgcfg-loading"><?php echo $mbTextEsc('please_wait', 'Please wait...'); ?></div>
	</div>

	<div class="imgcfg-hint">
		Image services reference an existing connection and contain only model and generation settings.
	</div>

	<div class="imgcfg-layout">
		<div class="imgcfg-listbox">
			<div class="imgcfg-toolbar">
				<button type="button" data-role="new"><?php echo $mbTextEsc('new_image_service', 'New image service'); ?></button>
				<button type="button" data-role="reload"><?php echo $mbTextEsc('reload', 'Reload'); ?></button>
			</div>

			<table class="imgcfg-table">
				<thead>
					<tr>
						<th><?php echo $mbTextEsc('id', 'ID'); ?></th>
						<th><?php echo $mbTextEsc('name', 'Name'); ?></th>
						<th><?php echo $mbTextEsc('connection', 'Connection'); ?></th>
						<th><?php echo $mbTextEsc('driver', 'Driver'); ?></th>
						<th><?php echo $mbTextEsc('model', 'Model'); ?></th>
						<th><?php echo $mbTextEsc('status', 'Status'); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="7" class="mono"><?php echo $mbTextEsc('loading', 'Loading...'); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="imgcfg-formbox">
			<form data-role="form">
				<h4 data-role="legend"><?php echo $mbTextEsc('create_image_service', 'Create image service'); ?></h4>

				<div class="imgcfg-hint">
					The technical id is used by configured image resources to resolve this service.
				</div>

				<div class="imgcfg-grid">
					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-id"><?php echo $mbTextEsc('image_service_id', 'Image service id'); ?></label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-id" name="id" placeholder="mistral_course_images" autocomplete="off">
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name"><?php echo $mbTextEsc('name', 'Name'); ?></label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name" name="name" placeholder="Mistral Course Images" autocomplete="off">
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver"><?php echo $mbTextEsc('driver', 'Driver'); ?></label>
						<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver" name="driver">
							<option value=""><?php echo $mbTextEsc('loading_drivers', 'Loading drivers...'); ?></option>
						</select>
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connection"><?php echo $mbTextEsc('connection', 'Connection'); ?></label>
						<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connection" name="connection">
							<option value=""><?php echo $mbTextEsc('loading_connections', 'Loading connections...'); ?></option>
						</select>
					</div>

					<div class="imgcfg-field imgcfg-field-wide">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model"><?php echo $mbTextEsc('model', 'Model'); ?></label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model" name="model" autocomplete="off">
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout"><?php echo $mbTextEsc('timeout_seconds', 'Timeout seconds'); ?></label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout" name="timeoutSeconds" placeholder="<?php echo $mbTextEsc('connection_default', 'connection default'); ?>" autocomplete="off">
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout"><?php echo $mbTextEsc('connect_timeout_seconds', 'Connect timeout seconds'); ?></label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout" name="connectTimeoutSeconds" placeholder="15" autocomplete="off">
					</div>
				</div>

				<div class="imgcfg-hint imgcfg-inline-hint">
					Leave the request timeout empty to use the referenced connection timeout. Connect timeout defaults to 15 seconds when not set.
				</div>

				<div data-role="driveroptions" class="imgcfg-grid imgcfg-driver-options"></div>

				<div class="imgcfg-grid">
					<div class="imgcfg-field imgcfg-field-wide">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-options"><?php echo $mbTextEsc('advanced_options_json', 'Advanced options JSON'); ?></label>
						<textarea id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-options" name="options" spellcheck="false" placeholder="{&#10;}"></textarea>
						<div class="imgcfg-hint imgcfg-inline-hint">
							Only generation options not represented by the selected driver schema belong here. Connection fields such as endpoint, authentication and API key are rejected.
						</div>
					</div>

					<div class="imgcfg-field imgcfg-field-checkbox imgcfg-field-wide">
						<label class="imgcfg-checkbox">
							<input type="checkbox" name="enabled" checked>
							<span><?php echo $mbTextEsc('enabled', 'Enabled'); ?></span>
						</label>
					</div>
				</div>

				<div data-role="formfeedback" class="imgcfg-form-feedback" style="display:none"></div>

				<div data-role="testresult" class="imgcfg-test-result" style="display:none">
					<div data-role="testmeta" class="imgcfg-test-meta"></div>
					<pre data-role="testpreview" class="imgcfg-test-preview"></pre>
				</div>

				<div class="imgcfg-actions">
					<button type="submit" class="primary"><?php echo $mbTextEsc('save_image_service', 'Save image service'); ?></button><button type="button" data-role="test"><?php echo $mbTextEsc('test_image_service', 'Test image service'); ?></button>
					<button type="button" data-role="delete" disabled><?php echo $mbTextEsc('delete_image_service', 'Delete image service'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
.image-config-admin {
	background: #ffffff;
	border: 1px solid #d6d6d6;
	padding: 16px;
	border-radius: 4px;
	max-width: 100%;
	font-family: Arial, sans-serif;
	color: #333;
}

.image-config-admin h3,
.image-config-admin h4 {
	margin-top: 0;
}

.image-config-admin h3 {
	margin-bottom: 12px;
	font-size: 1.1em;
}

.image-config-admin h4 {
	margin-bottom: 10px;
	font-size: 1em;
}

.imgcfg-meta {
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

.imgcfg-loading {
	display: none;
	color: #666;
	font-style: italic;
}

.imgcfg-layout {
	display: grid;
	grid-template-columns: minmax(620px, 1fr) minmax(380px, 520px);
	gap: 16px;
	align-items: start;
}

.imgcfg-listbox,
.imgcfg-formbox {
	border: 1px solid #ddd;
	border-radius: 4px;
	background: #fafafa;
	padding: 12px;
}

.imgcfg-toolbar,
.imgcfg-actions {
	display: flex;
	gap: 8px;
}

.imgcfg-toolbar {
	margin-bottom: 10px;
}

.imgcfg-toolbar button,
.imgcfg-actions button,
.imgcfg-edit-btn {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	color: #333;
	border-radius: 6px;
	padding: 8px 12px;
	cursor: pointer;
}

.imgcfg-edit-btn {
	padding: 5px 8px;
}

.imgcfg-toolbar button:hover,
.imgcfg-actions button:hover,
.imgcfg-edit-btn:hover {
	background: #e8e8e8;
}

.imgcfg-actions .primary {
	background: #eaf3ff;
	border-color: #aac6ea;
}

.imgcfg-actions button[disabled] {
	opacity: 0.5;
	cursor: not-allowed;
}

.imgcfg-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.imgcfg-table th,
.imgcfg-table td {
	border-bottom: 1px solid #ddd;
	padding: 8px;
	text-align: left;
	vertical-align: top;
}

.imgcfg-table th {
	background: #f1f1f1;
	white-space: nowrap;
}

.imgcfg-table tr.selected td {
	background: #edf5ff;
}

.imgcfg-table .id-col,
.imgcfg-table .driver-col,
.imgcfg-table .model-col,
.imgcfg-table .connection-col {
	font-family: Consolas, monospace;
}

.imgcfg-table .model-col {
	max-width: 190px;
	word-break: break-word;
}

.imgcfg-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 10px;
	margin-bottom: 12px;
}

.imgcfg-driver-options:empty {
	display: none;
}

.imgcfg-field {
	display: flex;
	flex-direction: column;
	gap: 5px;
	min-width: 0;
}

.imgcfg-field-wide {
	grid-column: 1 / -1;
}

.imgcfg-field label {
	font-size: 13px;
	font-weight: bold;
}

.imgcfg-field input[type="text"],
.imgcfg-field input[type="number"],
.imgcfg-field select,
.imgcfg-field textarea {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid #cfcfcf;
	border-radius: 4px;
	padding: 8px;
	background: #fff;
	color: #333;
}

.imgcfg-field textarea {
	min-height: 110px;
	font-family: Consolas, monospace;
	resize: vertical;
}

.imgcfg-field-checkbox {
	justify-content: center;
}

.imgcfg-checkbox {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-weight: normal !important;
}

.imgcfg-hint {
	margin-bottom: 12px;
	padding: 9px 10px;
	border-left: 3px solid #b9c9dc;
	background: #f5f8fb;
	font-size: 13px;
	line-height: 1.4;
	color: #4f5d6b;
}

.imgcfg-inline-hint {
	margin: 0;
	padding: 0;
	border: 0;
	background: transparent;
	font-size: 12px;
}

.imgcfg-form-feedback {
	margin: 10px 0;
	padding: 9px 10px;
	border-radius: 4px;
	font-size: 13px;
}

.imgcfg-form-feedback.success {
	background: #edf8ef;
	border: 1px solid #b9d9bf;
	color: #2c6335;
}

.imgcfg-form-feedback.error {
	background: #fff0f0;
	border: 1px solid #e0b4b4;
	color: #8a3030;
}

.badge {
	display: inline-block;
	border-radius: 10px;
	padding: 3px 8px;
	font-size: 11px;
	white-space: nowrap;
}

.badge.ok {
	background: #e5f4e8;
	color: #276334;
}

.badge.off {
	background: #ededed;
	color: #666;
}

.badge.warn {
	background: #fff3d6;
	color: #795500;
}

@media (max-width: 1150px) {
	.imgcfg-layout {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 680px) {
	.imgcfg-grid {
		grid-template-columns: 1fr;
	}

	.imgcfg-field-wide {
		grid-column: auto;
	}

	.imgcfg-listbox {
		overflow-x: auto;
	}
}

.imgcfg-test-result{margin-top:12px;border:1px solid #b9d3b9;background:#f8fff8;border-radius:6px;padding:10px 12px}.imgcfg-test-result.failed{border-color:#d88;background:#fff5f5}.imgcfg-test-meta{font-size:12px;color:#466846;margin-bottom:8px}.imgcfg-test-result.failed .imgcfg-test-meta{color:#8a3a3a}.imgcfg-test-preview{margin:0;max-height:240px;overflow:auto;white-space:pre-wrap;word-break:break-word;border:1px solid #d9e6d9;background:#fff;padding:10px;border-radius:4px;font-family:Consolas,monospace;font-size:12px;color:#333}
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

	"use strict";

	const rootId = <?php echo json_encode((string)$this->_['instanceId']); ?>;
	const endpointBase = <?php echo json_encode((string)$this->_['endpoint']); ?>;

	function init() {
		const root = document.getElementById(rootId);
		if (!root) {
			return;
		}

		const refs = {
			loading: root.querySelector("[data-role='loading']"),
			lastUpdate: root.querySelector("[data-role='lastupdate']"),
			tbody: root.querySelector("[data-role='tbody']"),
			form: root.querySelector("[data-role='form']"),
			legend: root.querySelector("[data-role='legend']"),
			feedback: root.querySelector("[data-role='formfeedback']"),
			newBtn: root.querySelector("[data-role='new']"),
			reloadBtn: root.querySelector("[data-role='reload']"),
			deleteBtn: root.querySelector("[data-role='delete']"),
			driverOptions: root.querySelector("[data-role='driveroptions']"),
			id: root.querySelector("[name='id']"),
			name: root.querySelector("[name='name']"),
			connection: root.querySelector("[name='connection']"),
			driver: root.querySelector("[name='driver']"),
			model: root.querySelector("[name='model']"),
			timeoutSeconds: root.querySelector("[name='timeoutSeconds']"),
			connectTimeoutSeconds: root.querySelector("[name='connectTimeoutSeconds']"),
			options: root.querySelector("[name='options']"),
			enabled: root.querySelector("[name='enabled']")
		};

		const state = {
			images: [],
			connections: [],
			drivers: [],
			selectedId: ""
		};

		function esc(value) {
			return String(value ?? "")
				.replace(/&/g, "&amp;")
				.replace(/</g, "&lt;")
				.replace(/>/g, "&gt;")
				.replace(/"/g, "&quot;")
				.replace(/'/g, "&#039;");
		}

		function normalizeKey(value) {
			return String(value || "")
				.toLowerCase()
				.trim()
				.replace(/[^a-z0-9._-]+/g, "");
		}

		function setLoading(active) {
			refs.loading.style.display = active ? "inline" : "none";
		}

		function setLastUpdate(timestamp) {
			refs.lastUpdate.textContent = timestamp || new Date().toISOString();
		}

		function clearFeedback() {
			refs.feedback.style.display = "none";
			refs.feedback.className = "imgcfg-form-feedback";
			refs.feedback.textContent = "";
		}

		function showFeedback(message, type) {
			refs.feedback.textContent = message;
			refs.feedback.className = "imgcfg-form-feedback " + type;
			refs.feedback.style.display = "block";
		}

		function findImage(id) {
			return state.images.find(function(image) {
				return String(image.id || "") === String(id || "");
			}) || null;
		}

		function findConnection(id) {
			return state.connections.find(function(connection) {
				return String(connection.id || "") === String(id || "");
			}) || null;
		}

		function findDriver(id) {
			return state.drivers.find(function(driver) {
				return String(driver.driver || "") === String(id || "");
			}) || null;
		}

		function driverProperties(driver) {
			const schema = driver && driver.configSchema && typeof driver.configSchema === "object"
				? driver.configSchema
				: {};
			return schema.properties && typeof schema.properties === "object"
				? schema.properties
				: {};
		}

		function driverDefaults(driver) {
			const config = driver && driver.defaultConfig && typeof driver.defaultConfig === "object"
				? driver.defaultConfig
				: {};
			return {
				model: String(config.model || ""),
				options: config.options && typeof config.options === "object" && !Array.isArray(config.options)
					? config.options
					: {}
			};
		}

		function modelRequired(driver) {
			const property = driverProperties(driver).model;
			return !!(property && property.required);
		}

		function renderDriverOptions(values, useDefaults) {
			const driver = findDriver(refs.driver.value);
			const properties = driverProperties(driver);
			const defaults = driverDefaults(driver).options;
			const source = values && typeof values === "object" ? values : {};

			refs.driverOptions.innerHTML = "";

			Object.keys(properties).forEach(function(key) {
				if (key === "model") {
					return;
				}

				const schema = properties[key] && typeof properties[key] === "object" ? properties[key] : {};
				const type = String(schema.type || "string").toLowerCase();
				const labelText = String(schema.label || key);
				const field = document.createElement("div");
				field.className = "imgcfg-field";

				let value = Object.prototype.hasOwnProperty.call(source, key)
					? source[key]
					: (useDefaults && Object.prototype.hasOwnProperty.call(defaults, key)
						? defaults[key]
						: schema.default);

				if (type === "boolean") {
					field.classList.add("imgcfg-field-checkbox");
					const label = document.createElement("label");
					label.className = "imgcfg-checkbox";
					const input = document.createElement("input");
					input.type = "checkbox";
					input.name = key;
					input.setAttribute("data-driver-option", key);
					input.checked = value === true || value === 1 || value === "1" || value === "true" || value === "yes" || value === "on";
					const text = document.createElement("span");
					text.textContent = labelText;
					label.appendChild(input);
					label.appendChild(text);
					field.appendChild(label);
				} else {
					const label = document.createElement("label");
					label.textContent = labelText + (schema.required ? " *" : "");
					field.appendChild(label);

					let input;
					if (Array.isArray(schema.enum) && schema.enum.length > 0) {
						input = document.createElement("select");
						if (!schema.required) {
							const emptyOption = document.createElement("option");
							emptyOption.value = "";
							emptyOption.textContent = mbText('default', 'Default');
							input.appendChild(emptyOption);
						}

						schema.enum.forEach(function(entry) {
							const option = document.createElement("option");
							option.value = String(entry);
							option.textContent = String(entry);
							input.appendChild(option);
						});
					} else {
						input = document.createElement("input");
						input.type = type === "integer" || type === "number" ? "number" : "text";
						if (type === "number") {
							input.step = "any";
						}
						if (schema.minimum !== undefined) {
							input.min = String(schema.minimum);
						}
						if (schema.maximum !== undefined) {
							input.max = String(schema.maximum);
						}
					}

					input.name = key;
					input.setAttribute("data-driver-option", key);
					input.value = value === undefined || value === null ? "" : String(value);
					field.appendChild(input);
				}

				if (schema.description) {
					const description = document.createElement("div");
					description.className = "imgcfg-hint imgcfg-inline-hint";
					description.textContent = String(schema.description);
					field.appendChild(description);
				}

				refs.driverOptions.appendChild(field);
			});
		}

		function renderDriverSelect(selected) {
			refs.driver.innerHTML = "<option value=''><?php echo $mbTextEsc('select_driver_2', mbText('select_driver_2', 'Select driver...')); ?></option>";

			state.drivers.forEach(function(driver) {
				const option = document.createElement("option");
				option.value = String(driver.driver || "");
				option.textContent = String(driver.label || driver.driver || "");
				refs.driver.appendChild(option);
			});

			refs.driver.value = selected || "";
		}

		function renderConnectionSelect(selected) {
			const driver = findDriver(refs.driver.value);
			const supported = driver && Array.isArray(driver.supportedConnectionTypes)
				? driver.supportedConnectionTypes
				: [];

			refs.connection.innerHTML = "<option value=''><?php echo $mbTextEsc('select_connection_2', mbText('select_connection_2', 'Select connection...')); ?></option>";

			state.connections.forEach(function(connection) {
				if (supported.length > 0 && !supported.includes(connection.type)) {
					return;
				}

				const option = document.createElement("option");
				option.value = String(connection.id || "");
				option.textContent = String(connection.name || connection.id || "")
					+ " [" + String(connection.type || "") + "]"
					+ (connection.enabled ? "" : " (disabled)");
				refs.connection.appendChild(option);
			});

			refs.connection.value = selected || "";
		}


		function knownOptionKeys(driver) {
			const known = new Set(Object.keys(driverProperties(driver)).filter(function(key) {
				return key !== "model";
			}));
			known.add("timeoutSeconds");
			known.add("connectTimeoutSeconds");
			return known;
		}

		function advancedOptions(options, driver) {
			const known = knownOptionKeys(driver);
			const out = {};
			Object.keys(options || {}).forEach(function(key) {
				if (!known.has(key)) {
					out[key] = options[key];
				}
			});
			return out;
		}

		function formatOptions(options) {
			return JSON.stringify(options && typeof options === "object" ? options : {}, null, 2);
		}

		function setEditMode(editing) {
			refs.id.readOnly = editing;
			refs.legend.textContent = editing ? "Edit image service" : mbText('create_image_service', 'Create image service');
			refs.deleteBtn.disabled = !editing;
		}

		function applyDriverDefaults() {
			const driver = findDriver(refs.driver.value);
			const defaults = driverDefaults(driver);
			refs.model.value = defaults.model || String(driverProperties(driver).model?.default || "");
			renderConnectionSelect(refs.connection.value);
			renderDriverOptions({}, true);
			refs.options.value = "{\n}";
		}

		function resetForm() {
			clearTestResult();
			refs.form.reset();
			refs.id.value = "";
			refs.name.value = "";
			refs.driver.value = "";
			refs.connection.value = "";
			refs.model.value = "";
			refs.timeoutSeconds.value = "";
			refs.connectTimeoutSeconds.value = "";
			refs.options.value = "{\n}";
			refs.enabled.checked = true;
			refs.driverOptions.innerHTML = "";
			state.selectedId = "";
			setEditMode(false);
			renderConnectionSelect("");
			highlightSelection();
		}

		function fillForm(image) {
			clearTestResult();
			if (!image) {
				resetForm();
				return;
			}

			refs.id.value = image.id || "";
			refs.name.value = image.name || "";
			refs.driver.value = image.driver || "";
			renderConnectionSelect(image.connection || "");
			refs.model.value = image.model || "";
			refs.timeoutSeconds.value = image.timeoutSeconds || "";
			refs.connectTimeoutSeconds.value = image.connectTimeoutSeconds || "";
			renderDriverOptions(image.options || {}, false);
			refs.options.value = formatOptions(advancedOptions(image.options || {}, findDriver(image.driver)));
			refs.enabled.checked = !!image.enabled;
			state.selectedId = image.id || "";
			setEditMode(true);
			highlightSelection();
		}

		function statusBadge(image) {
			if (!image.enabled) {
				return "<span class='badge off'><?php echo $mbTextEsc('disabled', 'disabled'); ?></span>";
			}
			if (!image.connectionEnabled) {
				return "<span class='badge warn'><?php echo $mbTextEsc('connection_off', mbText('connection_off', 'connection off')); ?></span>";
			}
			return "<span class='badge ok'><?php echo $mbTextEsc('enabled_2', 'enabled'); ?></span>";
		}

		function renderRows() {
			refs.tbody.innerHTML = "";
			if (state.images.length === 0) {
				refs.tbody.innerHTML = "<tr><td colspan='7' class='mono'><?php echo $mbTextEsc('no_image_services_configured', mbText('no_image_services_configured', 'No image services configured.')); ?></td></tr>";
				return;
			}

			state.images.forEach(function(image) {
				const tr = document.createElement("tr");
				tr.setAttribute("data-id", String(image.id || ""));
				tr.innerHTML =
					"<td class='id-col'>" + esc(image.id) + "</td>" +
					"<td>" + esc(image.name) + "</td>" +
					"<td class='connection-col'>" + esc(image.connection) + "<br><span style='color:#777'>" + esc(image.connectionType || "") + "</span></td>" +
					"<td class='driver-col'>" + esc(image.driverLabel || image.driver) + "</td>" +
					"<td class='model-col'>" + esc(image.model) + "</td>" +
					"<td>" + statusBadge(image) + "</td>" +
					"<td><button type='button' class='imgcfg-edit-btn' data-action='edit' data-id='" + esc(image.id) + "'><?php echo $mbTextEsc('edit', mbText('edit', 'Edit')); ?></button></td>";
				refs.tbody.appendChild(tr);
			});

			highlightSelection();
		}

		function highlightSelection() {
			root.querySelectorAll("tbody tr[data-id]").forEach(function(row) {
				row.classList.toggle("selected", row.getAttribute("data-id") === state.selectedId);
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
				let json;
				try {
					json = JSON.parse(text);
				} catch (error) {
					showFeedback(mbText('the_server_response_could_not_be_read', 'The server response could not be read.'), "error");
					return null;
				}

				setLastUpdate(json.timestamp || "");
				if (json.status !== "ok") {
					showFeedback(json.message || "The request could not be completed.", "error");
					return null;
				}
				return json;
			} catch (error) {
				showFeedback(mbText('the_request_failed_please_try_again', 'The request failed. Please try again.'), "error");
				return null;
			} finally {
				setLoading(false);
			}
		}

		async function loadList(preselectId) {
			const json = await callApi({action: "list"});
			if (!json) {
				refs.tbody.innerHTML = "<tr><td colspan='7' class='mono'><?php echo $mbTextEsc('image_services_could_not_be_loaded', mbText('image_services_could_not_be_loaded', 'Image services could not be loaded.')); ?></td></tr>";
				return;
			}

			state.connections = json.data && Array.isArray(json.data.connections) ? json.data.connections : [];
			state.drivers = json.data && Array.isArray(json.data.drivers) ? json.data.drivers : [];
			state.images = json.data && Array.isArray(json.data.images) ? json.data.images : [];

			renderDriverSelect(refs.driver.value || "");
			renderConnectionSelect(refs.connection.value || "");
			renderRows();

			const target = preselectId || state.selectedId;
			if (target) {
				const image = findImage(target);
				if (image) {
					fillForm(image);
					return;
				}
			}

			if (state.images.length === 0) {
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
					showFeedback(mbText('advanced_options_must_be_a_json_object', 'Advanced options must be a JSON object.'), "error");
					return null;
				}
				return JSON.stringify(parsed);
			} catch (error) {
				showFeedback(mbText('advanced_options_must_be_valid_json', 'Advanced options must be valid JSON.'), "error");
				return null;
			}
		}

		function appendDriverOptionParams(params) {
			refs.driverOptions.querySelectorAll("[data-driver-option]").forEach(function(input) {
				const key = input.getAttribute("data-driver-option");
				params[key] = input.type === "checkbox"
					? (input.checked ? "1" : "0")
					: String(input.value || "").trim();
			});
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
			const id = normalizeKey(refs.id.value);
			const name = String(refs.name.value || "").trim();
			const connection = normalizeKey(refs.connection.value);
			const driverId = normalizeKey(refs.driver.value);
			const model = String(refs.model.value || "").trim();
			const timeoutSeconds = String(refs.timeoutSeconds.value || "").trim();
			const connectTimeoutSeconds = String(refs.connectTimeoutSeconds.value || "").trim();
			const options = readOptionsJson();

			if (options === null) {
				return;
			}

			refs.id.value = id;
			if (!id || !name || !connection || !driverId) {
				showFeedback(mbText('image_service_id_name_connection_and_driver_are_required', 'Image service id, name, connection and driver are required.'), "error");
				return;
			}
			if (modelRequired(findDriver(driverId)) && !model) {
				showFeedback(mbText('model_is_required', 'Model is required.'), "error");
				return;
			}

			const params = {
				action: "save",
				id: id,
				name: name,
				connection: connection,
				driver: driverId,
				model: model,
				timeoutSeconds: timeoutSeconds,
				connectTimeoutSeconds: connectTimeoutSeconds,
				options: options,
				enabled: refs.enabled.checked ? "1" : "0"
			};
			appendDriverOptionParams(params);

			const json = await callApi(params);
			if (!json) {
				return;
			}

			const image = json.data && json.data.image ? json.data.image : null;
			showFeedback(mbText('image_service_saved', 'Image service saved.'), "success");
			await loadList(image && image.id ? image.id : id);
		}

		async function removeCurrent() {
			clearFeedback();
			const id = String(state.selectedId || refs.id.value || "").trim();
			if (!id) {
				showFeedback(mbText('no_image_service_selected', 'No image service selected.'), "error");
				return;
			}
			if (!window.confirm(mbText('delete_image_service_confirm', "Delete image service '{id}'?", {id}))) {
				return;
			}

			const json = await callApi({action: "remove", id: id});
			if (!json) {
				return;
			}
			showFeedback(mbText('image_service_deleted', 'Image service deleted.'), "success");
			resetForm();
			await loadList();
		}

		refs.form.addEventListener("submit", function(event) {
			event.preventDefault();
			saveCurrent();
		});

		root.querySelector("[data-role='test']").addEventListener("click", testCurrent);
		refs.newBtn.addEventListener("click", function() {
			clearFeedback();
			resetForm();
		});

		refs.reloadBtn.addEventListener("click", function() {
			clearFeedback();
			loadList(state.selectedId || "");
		});

		refs.deleteBtn.addEventListener("click", removeCurrent);
		refs.driver.addEventListener("change", applyDriverDefaults);
		refs.tbody.addEventListener("click", function(event) {
			const button = event.target.closest("button[data-action='edit']");
			if (!button) {
				return;
			}
			clearFeedback();
			fillForm(findImage(button.getAttribute("data-id")));
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
