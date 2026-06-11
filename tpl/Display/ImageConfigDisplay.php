<div id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>" class="image-config-admin">
	<h3>Image Generation Services</h3>

	<div class="imgcfg-meta">
		<div><strong>Settings group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['configGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Connection group:</strong> <span class="mono"><?php echo htmlspecialchars((string)$this->_['connectionGroup'], ENT_QUOTES); ?></span></div>
		<div><strong>Last update:</strong> <span data-role="lastupdate" class="mono">-</span></div>
		<div data-role="loading" class="imgcfg-loading">Please wait...</div>
	</div>

	<div class="imgcfg-hint">
		Image services define concrete image generation models. Technical endpoint and authentication are taken from the selected connection.
	</div>

	<div class="imgcfg-layout">
		<div class="imgcfg-listbox">
			<div class="imgcfg-toolbar">
				<button type="button" data-role="new">New image service</button>
				<button type="button" data-role="reload">Reload</button>
			</div>

			<table class="imgcfg-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Connection</th>
						<th>Driver</th>
						<th>Model</th>
						<th>Size</th>
						<th>Quality</th>
						<th>Format</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody data-role="tbody">
					<tr><td colspan="10" class="mono">Loading...</td></tr>
				</tbody>
			</table>
		</div>

		<div class="imgcfg-formbox">
			<form data-role="form">
				<h4 data-role="legend">Create image service</h4>

				<div class="imgcfg-hint" data-role="idhint">
					Technical image service id. Agent resources use this id to resolve the configured image generation service.
				</div>

				<div class="imgcfg-grid">
					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-id">Image service id</label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-id" name="id" placeholder="openai_default_image" autocomplete="off">
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name">Name</label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-name" name="name" placeholder="OpenAI Default Image" autocomplete="off">
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connection">Connection</label>
						<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connection" name="connection">
							<option value="">Loading connections...</option>
						</select>
						<div class="imgcfg-hint imgcfg-inline-hint" data-role="connectionhint">
							Connections contain endpoint and authentication data.
						</div>
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver">Driver</label>
						<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-driver" name="driver">
							<option value="">Loading drivers...</option>
						</select>
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model">Model</label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-model" name="model" placeholder="gpt-image-2" autocomplete="off">
					</div>

					<div class="imgcfg-field imgcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-size">Size</label>
							<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-size" name="size" placeholder="1024x1024" autocomplete="off">
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-quality">Quality</label>
							<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-quality" name="quality">
								<option value="">default</option>
								<option value="auto">auto</option>
								<option value="low">low</option>
								<option value="medium">medium</option>
								<option value="high">high</option>
							</select>
						</div>
					</div>

					<div class="imgcfg-field imgcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-outputformat">Output format</label>
							<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-outputformat" name="outputFormat">
								<option value="">default</option>
								<option value="png">png</option>
								<option value="jpeg">jpeg</option>
								<option value="webp">webp</option>
							</select>
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-background">Background</label>
							<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-background" name="background">
								<option value="">default</option>
								<option value="auto">auto</option>
								<option value="opaque">opaque</option>
								<option value="transparent">transparent</option>
							</select>
						</div>
					</div>

					<div class="imgcfg-field imgcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-moderation">Moderation</label>
							<select id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-moderation" name="moderation">
								<option value="">default</option>
								<option value="auto">auto</option>
								<option value="low">low</option>
							</select>
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-numberofimages">Number of images</label>
							<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-numberofimages" name="numberOfImages" placeholder="1" autocomplete="off">
						</div>
					</div>

					<div class="imgcfg-field imgcfg-field-row">
						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-compression">Output compression</label>
							<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-compression" name="outputCompression" placeholder="" autocomplete="off">
						</div>

						<div>
							<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout">Timeout seconds</label>
							<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-timeout" name="timeoutSeconds" placeholder="120" autocomplete="off">
						</div>
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout">Connect timeout seconds</label>
						<input type="text" id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-connecttimeout" name="connectTimeoutSeconds" placeholder="" autocomplete="off">
					</div>

					<div class="imgcfg-field">
						<label for="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-options">Advanced options JSON</label>
						<textarea id="<?php echo htmlspecialchars((string)$this->_['instanceId'], ENT_QUOTES); ?>-options" name="options" spellcheck="false" placeholder="{&#10;}"></textarea>
						<div class="imgcfg-hint imgcfg-inline-hint">
							Optional provider-specific options. Explicit fields above override duplicate keys in this JSON object.
						</div>
					</div>

					<div class="imgcfg-field imgcfg-field-checkbox">
						<label class="imgcfg-checkbox">
							<input type="checkbox" name="enabled" checked>
							<span>Enabled</span>
						</label>
					</div>
				</div>

				<div data-role="formfeedback" class="imgcfg-form-feedback" style="display:none"></div>

				<div class="imgcfg-actions">
					<button type="submit" class="primary">Save image service</button>
					<button type="button" data-role="delete" disabled>Delete image service</button>
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

.image-config-admin h3 {
	margin-top: 0;
	margin-bottom: 12px;
	font-size: 1.1em;
}

.image-config-admin h4 {
	margin-top: 0;
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
	grid-template-columns: minmax(760px, 1fr) minmax(380px, 520px);
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

.imgcfg-toolbar {
	display: flex;
	gap: 8px;
	margin-bottom: 10px;
}

.imgcfg-toolbar button,
.imgcfg-actions button {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	color: #333;
	border-radius: 6px;
	padding: 8px 12px;
	cursor: pointer;
}

.imgcfg-toolbar button:hover,
.imgcfg-actions button:hover {
	background: #e8e8e8;
}

.imgcfg-actions .primary {
	background: #eaf3ff;
	border-color: #aac6ea;
}

.imgcfg-actions .primary:hover {
	background: #dcecff;
}

.imgcfg-actions button[disabled] {
	opacity: 0.5;
	cursor: not-allowed;
}

.imgcfg-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
}

.imgcfg-table th,
.imgcfg-table td {
	padding: 8px 10px;
	border-bottom: 1px solid #e0e0e0;
	vertical-align: middle;
	text-align: left;
	font-size: 13px;
}

.imgcfg-table th {
	background: #f5f5f5;
	font-weight: 600;
	border-bottom: 2px solid #cfcfcf;
}

.imgcfg-table tr:hover td {
	background: #fafafa;
}

.imgcfg-table tr.selected td {
	background: #eef5ff;
}

.imgcfg-table td.id-col,
.imgcfg-table td.model-col,
.imgcfg-table td.connection-col,
.imgcfg-table td.driver-col,
.imgcfg-table td.option-col {
	font-family: Consolas, monospace;
	font-size: 12px;
}

.imgcfg-table td.model-col {
	max-width: 220px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.imgcfg-edit-btn {
	border: 1px solid #c9c9c9;
	background: #f1f1f1;
	border-radius: 6px;
	padding: 5px 8px;
	cursor: pointer;
	font-size: 12px;
}

.imgcfg-edit-btn:hover {
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

.imgcfg-hint {
	margin-bottom: 12px;
	font-size: 12px;
	color: #666;
}

.imgcfg-inline-hint {
	margin-top: 6px;
	margin-bottom: 0;
}

.imgcfg-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 12px;
}

.imgcfg-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 6px;
	font-size: 13px;
}

.imgcfg-field input[type="text"],
.imgcfg-field select,
.imgcfg-field textarea {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid #cfcfcf;
	border-radius: 6px;
	padding: 8px 10px;
	background: #fff;
	color: #333;
}

.imgcfg-field textarea {
	min-height: 150px;
	font-family: Consolas, monospace;
	font-size: 12px;
	resize: vertical;
}

.imgcfg-field input[readonly] {
	background: #f6f6f6;
	color: #666;
}

.imgcfg-field-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}

.imgcfg-field-checkbox {
	padding-top: 4px;
}

.imgcfg-checkbox {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
}

.imgcfg-form-feedback {
	margin-top: 14px;
	border: 1px solid #ddd;
	border-radius: 6px;
	padding: 9px 11px;
	font-size: 13px;
	line-height: 1.4;
}

.imgcfg-form-feedback.success {
	border-color: #8d8;
	background: #f6fff6;
	color: #2d6b2d;
}

.imgcfg-form-feedback.error {
	border-color: #d88;
	background: #fff5f5;
	color: #a33;
}

.imgcfg-actions {
	display: flex;
	gap: 8px;
	margin-top: 14px;
}

@media (max-width: 1200px) {
	.imgcfg-layout {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 620px) {
	.imgcfg-field-row {
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
			size: root.querySelector("input[name='size']"),
			quality: root.querySelector("select[name='quality']"),
			outputFormat: root.querySelector("select[name='outputFormat']"),
			background: root.querySelector("select[name='background']"),
			moderation: root.querySelector("select[name='moderation']"),
			numberOfImages: root.querySelector("input[name='numberOfImages']"),
			outputCompression: root.querySelector("input[name='outputCompression']"),
			timeoutSeconds: root.querySelector("input[name='timeoutSeconds']"),
			connectTimeoutSeconds: root.querySelector("input[name='connectTimeoutSeconds']"),
			options: root.querySelector("textarea[name='options']"),
			enabled: root.querySelector("input[name='enabled']")
		};

		const state = {
			images: [],
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
			refs.formfeedback.className = "imgcfg-form-feedback " + (type === "error" ? "error" : "success");
			refs.formfeedback.textContent = message;
		}

		function clearFeedback() {
			refs.formfeedback.style.display = "none";
			refs.formfeedback.className = "imgcfg-form-feedback";
			refs.formfeedback.textContent = "";
		}

		function findImage(id) {
			id = String(id || "");
			return state.images.find(function(item) {
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
			delete clean.size;
			delete clean.quality;
			delete clean.outputFormat;
			delete clean.background;
			delete clean.moderation;
			delete clean.numberOfImages;
			delete clean.outputCompression;
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
				refs.legend.textContent = "Edit image service";
				refs.idhint.textContent = "Technical image service id is fixed for existing entries. Create a new entry if you need another key.";
			} else {
				refs.legend.textContent = "Create image service";
				refs.idhint.textContent = "Technical image service id. Agent resources use this id to resolve the configured image generation service.";
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
				option.textContent = "No image drivers available";
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

			if (force || !refs.size.value) {
				refs.size.value = options.size ?? "";
			}

			if (force) {
				refs.quality.value = options.quality ?? "";
				refs.outputFormat.value = options.outputFormat ?? "";
				refs.background.value = options.background ?? "";
				refs.moderation.value = options.moderation ?? "";
				refs.numberOfImages.value = options.numberOfImages ?? "";
				refs.outputCompression.value = options.outputCompression ?? "";
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
			refs.size.value = "";
			refs.quality.value = "";
			refs.outputFormat.value = "";
			refs.background.value = "";
			refs.moderation.value = "";
			refs.numberOfImages.value = "";
			refs.outputCompression.value = "";
			refs.timeoutSeconds.value = "";
			refs.connectTimeoutSeconds.value = "";
			refs.options.value = "{\n}";
			refs.enabled.checked = true;

			state.selectedId = "";
			setEditMode(false);
			updateConnectionHint();
			highlightSelection();
		}

		function fillForm(image) {
			if (!image) {
				resetForm();
				return;
			}

			refs.id.value = image.id || "";
			refs.name.value = image.name || "";
			refs.connection.value = image.connection || "";
			refs.driver.value = image.driver || "";
			refs.model.value = image.model || "";
			refs.size.value = image.size || "";
			refs.quality.value = image.quality || "";
			refs.outputFormat.value = image.outputFormat || "";
			refs.background.value = image.background || "";
			refs.moderation.value = image.moderation || "";
			refs.numberOfImages.value = image.numberOfImages || "";
			refs.outputCompression.value = image.outputCompression || "";
			refs.timeoutSeconds.value = image.timeoutSeconds || "";
			refs.connectTimeoutSeconds.value = image.connectTimeoutSeconds || "";
			refs.options.value = formatOptions(image.options || {});
			refs.enabled.checked = !!image.enabled;

			state.selectedId = image.id || "";
			setEditMode(true);
			updateConnectionHint();
			highlightSelection();
		}

		function statusBadge(image) {
			if (!image.enabled) {
				return "<span class='badge off'>disabled</span>";
			}

			if (!image.connectionEnabled) {
				return "<span class='badge warn'>connection off</span>";
			}

			return "<span class='badge ok'>enabled</span>";
		}

		function renderRows() {
			const images = Array.isArray(state.images) ? state.images : [];
			refs.tbody.innerHTML = "";

			if (images.length === 0) {
				refs.tbody.innerHTML = "<tr><td colspan='10' class='mono'>No image services configured.</td></tr>";
				return;
			}

			for (const image of images) {
				const tr = document.createElement("tr");
				tr.setAttribute("data-id", String(image.id || ""));

				tr.innerHTML =
					"<td class='id-col'>" + esc(image.id) + "</td>" +
					"<td>" + esc(image.name) + "</td>" +
					"<td class='connection-col' title='" + esc(connectionLabel(image.connection)) + "'>" +
						esc(image.connection) + "<br><span style='color:#777'>" + esc(image.connectionType || "") + "</span>" +
					"</td>" +
					"<td class='driver-col'>" + esc(image.driverLabel || image.driver) + "</td>" +
					"<td class='model-col' title='" + esc(image.model) + "'>" + esc(image.model) + "</td>" +
					"<td class='option-col'>" + esc(image.size || "") + "</td>" +
					"<td class='option-col'>" + esc(image.quality || "") + "</td>" +
					"<td class='option-col'>" + esc(image.outputFormat || "") + "</td>" +
					"<td>" + statusBadge(image) + "</td>" +
					"<td><button type='button' class='imgcfg-edit-btn' data-action='edit' data-id='" + esc(image.id) + "'>Edit</button></td>";

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
				refs.tbody.innerHTML = "<tr><td colspan='10' class='mono'>Image services could not be loaded.</td></tr>";
				return;
			}

			state.connections = (json.data && Array.isArray(json.data.connections)) ? json.data.connections : [];
			state.drivers = (json.data && Array.isArray(json.data.drivers)) ? json.data.drivers : [];
			state.images = (json.data && Array.isArray(json.data.images)) ? json.data.images : [];

			renderConnectionOptions(refs.connection.value || "");
			renderDriverOptions(refs.driver.value || "");
			renderRows();

			if (preselectId) {
				const image = findImage(preselectId);
				if (image) {
					fillForm(image);
					return;
				}
			}

			if (state.selectedId) {
				const image = findImage(state.selectedId);
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
				showFeedback("Image service id is required.", "error");
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
				size: String(refs.size.value || "").trim(),
				quality: String(refs.quality.value || "").trim(),
				outputFormat: String(refs.outputFormat.value || "").trim(),
				background: String(refs.background.value || "").trim(),
				moderation: String(refs.moderation.value || "").trim(),
				numberOfImages: String(refs.numberOfImages.value || "").trim(),
				outputCompression: String(refs.outputCompression.value || "").trim(),
				timeoutSeconds: String(refs.timeoutSeconds.value || "").trim(),
				connectTimeoutSeconds: String(refs.connectTimeoutSeconds.value || "").trim(),
				options: options,
				enabled: refs.enabled.checked ? "1" : "0"
			});

			if (!json) {
				return;
			}

			const image = (json.data && json.data.image) ? json.data.image : null;

			showFeedback("Image service saved.", "success");

			await loadList(image && image.id ? image.id : id);
		}

		async function removeCurrent() {
			clearFeedback();

			const id = String(state.selectedId || refs.id.value || "").trim();

			if (!id) {
				showFeedback("No image service selected.", "error");
				return;
			}

			if (!window.confirm("Delete image service '" + id + "'?")) {
				return;
			}

			const json = await callApi({
				action: "remove",
				id: id
			});

			if (!json) {
				return;
			}

			showFeedback("Image service deleted.", "success");

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

			const image = findImage(btn.getAttribute("data-id"));
			fillForm(image);
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
