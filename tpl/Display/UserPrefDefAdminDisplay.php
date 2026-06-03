<?php
$modularGridCssUrl = (string) $this->_['modularGridCssUrl'];
$modularGridJsUrl = (string) $this->_['modularGridJsUrl'];
$serviceUrl = (string) $this->_['service'];
$valueTypeOptions = $this->_['valueTypeOptions'];
$scopeOptions = $this->_['scopeOptions'];
$enabledOptions = $this->_['enabledOptions'];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularGridCssUrl, ENT_QUOTES); ?>" />

<style>
	.userpref-def-admin-shell {
		max-width: 1700px;
	}

	.userpref-def-admin-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.userpref-def-admin-shell p {
		margin: 0 0 12px 0;
		max-width: 1200px;
		color: #555;
		line-height: 1.45;
	}

	.userpref-def-admin-grid .userpref-def-admin-panel {
		display: flex;
		align-items: center;
		flex-wrap: nowrap;
		gap: 8px;
		min-width: 0;
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		overflow-x: auto;
	}

	.userpref-def-admin-grid .userpref-def-admin-panel--filters {
		flex-wrap: wrap;
		align-items: flex-start;
		overflow-x: visible;
	}

	.userpref-def-admin-grid .userpref-def-admin-panel > * {
		flex: 0 0 auto;
	}

	.userpref-def-admin-grid .userpref-def-admin-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.userpref-def-admin-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.userpref-def-admin-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.userpref-def-admin-grid .mg-input,
	.userpref-def-admin-grid .mg-select,
	.userpref-def-admin-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.userpref-def-admin-grid input[type="search"].mg-input {
		width: 340px;
	}

	.userpref-def-admin-grid .mg-select {
		width: auto;
		min-width: 128px;
	}

	.userpref-def-admin-grid .mg-table-scroll {
		height: 600px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.userpref-def-admin-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.userpref-def-admin-grid .mg-table th,
	.userpref-def-admin-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.userpref-def-admin-top-actions {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		flex: 0 0 auto;
	}

	.userpref-def-admin-button {
		appearance: none;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		color: #222;
		cursor: pointer;
		font: inherit;
		font-size: 13px;
		line-height: 1.3;
		min-height: 28px;
		padding: 4px 10px;
		white-space: nowrap;
	}

	.userpref-def-admin-button:hover {
		background: #f5f5f5;
	}

	.userpref-def-admin-button:focus-visible {
		outline: 2px solid #86a8cf;
		outline-offset: 2px;
	}

	.userpref-def-admin-button-primary {
		background: #2f5d91;
		border-color: #2f5d91;
		color: #fff;
	}

	.userpref-def-admin-button-primary:hover {
		background: #284f7c;
	}

	.userpref-def-admin-button-danger {
		border-color: #c8a2a2;
		color: #8a1f1f;
	}

	.userpref-def-admin-button-danger:hover {
		background: #fff0f0;
	}

	.userpref-def-admin-output {
		margin-top: 12px;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		font-size: 13px;
		color: #555;
	}

	.userpref-def-admin-output strong {
		color: #222;
	}

	.userpref-def-admin-cell-stack {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	.userpref-def-admin-cell-main {
		font-weight: 600;
		color: #222;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.userpref-def-admin-cell-sub {
		font-size: 12px;
		color: #666;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	.userpref-def-admin-pre {
		margin: 0;
		max-height: 120px;
		overflow: auto;
		color: #333;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		line-height: 1.45;
		white-space: pre-wrap;
		word-break: break-word;
	}

	.userpref-def-admin-pill {
		display: inline-flex;
		align-items: center;
		padding: 1px 6px;
		border: 1px solid #d6d6d6;
		border-radius: 999px;
		background: #fafafa;
		font-size: 11px;
		line-height: 1.35;
		color: #444;
		white-space: nowrap;
	}

	.userpref-def-admin-pill-enum {
		background: #edf6ff;
		border-color: #c3dff5;
	}

	.userpref-def-admin-pill-bool,
	.userpref-def-admin-pill-enabled {
		background: #eef7ee;
		border-color: #bddfbd;
	}

	.userpref-def-admin-pill-disabled {
		background: #f5eeee;
		border-color: #e2c5c5;
		color: #7a3333;
	}

	.userpref-def-admin-modal {
		position: fixed;
		inset: 0;
		z-index: 9000;
		display: none;
		align-items: center;
		justify-content: center;
		padding: 24px;
		background: rgba(0, 0, 0, 0.35);
	}

	.userpref-def-admin-modal.is-open {
		display: flex;
	}

	.userpref-def-admin-dialog {
		display: grid;
		grid-template-rows: auto 1fr auto;
		gap: 12px;
		width: min(980px, 100%);
		max-height: min(820px, 100%);
		border: 1px solid #d6d6d6;
		border-radius: 8px;
		background: #fff;
		box-shadow: 0 16px 50px rgba(0, 0, 0, 0.20);
		padding: 16px;
	}

	.userpref-def-admin-dialog-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
	}

	.userpref-def-admin-dialog-title {
		margin: 0;
		font-size: 18px;
		line-height: 1.25;
		font-weight: 600;
	}

	.userpref-def-admin-dialog-body {
		display: grid;
		gap: 12px;
		min-height: 0;
		overflow: auto;
	}

	.userpref-def-admin-form-row {
		display: grid;
		gap: 5px;
	}

	.userpref-def-admin-form-row-inline {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 12px;
	}

	.userpref-def-admin-form-row-inline-four {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 12px;
	}

	.userpref-def-admin-form-label {
		color: #555;
		font-size: 12px;
		font-weight: 600;
		line-height: 1.3;
	}

	.userpref-def-admin-form-input,
	.userpref-def-admin-form-select,
	.userpref-def-admin-form-textarea {
		width: 100%;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		color: #222;
		font: inherit;
		font-size: 13px;
		line-height: 1.4;
		padding: 7px 9px;
	}

	.userpref-def-admin-form-textarea {
		min-height: 140px;
		resize: vertical;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		white-space: pre;
	}

	.userpref-def-admin-form-textarea-large {
		min-height: 190px;
	}

	.userpref-def-admin-form-hint {
		color: #666;
		font-size: 12px;
		line-height: 1.35;
	}

	.userpref-def-admin-dialog-footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
	}

	.userpref-def-admin-dialog-footer-main,
	.userpref-def-admin-dialog-footer-extra {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.userpref-def-admin-error {
		display: none;
		padding: 8px 10px;
		border: 1px solid #e4b9b9;
		border-radius: 6px;
		background: #fff0f0;
		color: #8a1f1f;
		font-size: 13px;
		line-height: 1.4;
	}

	.userpref-def-admin-error.is-visible {
		display: block;
	}

	@media (max-width: 900px) {
		.userpref-def-admin-form-row-inline,
		.userpref-def-admin-form-row-inline-four {
			grid-template-columns: 1fr;
		}
	}
</style>

<div class="userpref-def-admin-shell">
	<h1>User preference definitions</h1>
	<p>
		Definitions for the UserPrefs agent tool. These records control allowed preference keys, validation, default scope and the injected system prompt text.
	</p>

	<div class="userpref-def-admin-grid">
		<div id="userpref-def-admin-grid"></div>
		<div id="userpref-def-admin-output" class="userpref-def-admin-output"></div>
	</div>
</div>

<div id="userpref-def-admin-modal" class="userpref-def-admin-modal" aria-hidden="true">
	<div class="userpref-def-admin-dialog" role="dialog" aria-modal="true" aria-labelledby="userpref-def-admin-modal-title">
		<div class="userpref-def-admin-dialog-header">
			<h2 id="userpref-def-admin-modal-title" class="userpref-def-admin-dialog-title">Preference definition</h2>
			<button type="button" id="userpref-def-admin-close" class="userpref-def-admin-button">Close</button>
		</div>

		<div class="userpref-def-admin-dialog-body">
			<div id="userpref-def-admin-error" class="userpref-def-admin-error"></div>

			<input type="hidden" id="userpref-def-admin-id" />

			<div class="userpref-def-admin-form-row-inline">
				<label class="userpref-def-admin-form-row">
					<span class="userpref-def-admin-form-label">Preference key</span>
					<input type="text" id="userpref-def-admin-pref-key" class="userpref-def-admin-form-input" autocomplete="off" placeholder="answer_style" />
				</label>

				<label class="userpref-def-admin-form-row">
					<span class="userpref-def-admin-form-label">Description</span>
					<input type="text" id="userpref-def-admin-description" class="userpref-def-admin-form-input" autocomplete="off" placeholder="Short admin description" />
				</label>
			</div>

			<div class="userpref-def-admin-form-row-inline-four">
				<label class="userpref-def-admin-form-row">
					<span class="userpref-def-admin-form-label">Value type</span>
					<select id="userpref-def-admin-value-type" class="userpref-def-admin-form-select">
						<option value="string">String</option>
						<option value="enum">Enum</option>
						<option value="bool">Boolean</option>
					</select>
				</label>

				<label class="userpref-def-admin-form-row">
					<span class="userpref-def-admin-form-label">Default scope</span>
					<select id="userpref-def-admin-default-scope" class="userpref-def-admin-form-select">
						<option value="user">User</option>
						<option value="session">Session</option>
					</select>
				</label>

				<label class="userpref-def-admin-form-row">
					<span class="userpref-def-admin-form-label">Sort order</span>
					<input type="number" id="userpref-def-admin-sort-order" class="userpref-def-admin-form-input" value="100" />
				</label>

				<label class="userpref-def-admin-form-row">
					<span class="userpref-def-admin-form-label">State</span>
					<select id="userpref-def-admin-enabled" class="userpref-def-admin-form-select">
						<option value="1">Enabled</option>
						<option value="0">Disabled</option>
					</select>
				</label>
			</div>

			<label class="userpref-def-admin-form-row">
				<span class="userpref-def-admin-form-label">System template</span>
				<textarea id="userpref-def-admin-system-template" class="userpref-def-admin-form-textarea userpref-def-admin-form-textarea-large" spellcheck="false" placeholder="Antworte im Stil: {{value}}."></textarea>
				<span class="userpref-def-admin-form-hint">Use {{value}} for string and enum values. Boolean templates are injected only when the value is true.</span>
			</label>

			<label class="userpref-def-admin-form-row">
				<span class="userpref-def-admin-form-label">Allowed values</span>
				<textarea id="userpref-def-admin-allowed-values" class="userpref-def-admin-form-textarea" spellcheck="false" placeholder="[
	&quot;kurz&quot;,
	&quot;normal&quot;,
	&quot;ausführlich&quot;
]"></textarea>
				<span id="userpref-def-admin-allowed-values-hint" class="userpref-def-admin-form-hint"></span>
			</label>
		</div>

		<div class="userpref-def-admin-dialog-footer">
			<div class="userpref-def-admin-dialog-footer-extra">
				<button type="button" id="userpref-def-admin-delete-current" class="userpref-def-admin-button userpref-def-admin-button-danger">Delete</button>
			</div>
			<div class="userpref-def-admin-dialog-footer-main">
				<button type="button" id="userpref-def-admin-cancel" class="userpref-def-admin-button">Cancel</button>
				<button type="button" id="userpref-def-admin-save" class="userpref-def-admin-button userpref-def-admin-button-primary">Save</button>
			</div>
		</div>
	</div>
</div>

<script>
	(function() {
		const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const MODULAR_GRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const VALUE_TYPE_OPTIONS = <?php echo json_encode($valueTypeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const SCOPE_OPTIONS = <?php echo json_encode($scopeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const ENABLED_OPTIONS = <?php echo json_encode($enabledOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const GRID_SELECTOR = '#userpref-def-admin-grid';
		const LOG_SELECTOR = '#userpref-def-admin-output';
		const BATCH_SIZE = 50;

		let grid = null;
		let currentEditorRecord = null;

		function getText(value, placeholder = '-') {
			if(value === null || value === undefined || value === '') {
				return placeholder;
			}

			return String(value);
		}

		function setLog(message) {
			const logElement = document.querySelector(LOG_SELECTOR);

			if(!logElement) {
				return;
			}

			logElement.replaceChildren();

			const label = document.createElement('strong');
			label.textContent = 'Last action:';

			logElement.appendChild(label);
			logElement.appendChild(document.createTextNode(' ' + getText(message, 'None')));
		}

		function createElement(className, text = null) {
			const element = document.createElement('div');
			element.className = className;

			if(text !== null && text !== undefined) {
				element.textContent = String(text);
			}

			return element;
		}

		function createButton(className, text) {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = className;
			button.textContent = text;

			return button;
		}

		function renderName(value, row) {
			const wrapper = createElement('userpref-def-admin-cell-stack');
			const main = createElement('userpref-def-admin-cell-main', getText(row.pref_key));
			const sub = createElement('userpref-def-admin-cell-sub', getText(row.description, 'No description'));

			wrapper.appendChild(main);
			wrapper.appendChild(sub);

			return wrapper;
		}

		function renderType(value, row) {
			const wrapper = createElement('userpref-def-admin-cell-stack');
			const type = document.createElement('span');
			const state = document.createElement('span');
			const sub = createElement('userpref-def-admin-cell-sub', 'Scope: ' + getText(row.default_scope));

			type.className = ('userpref-def-admin-pill userpref-def-admin-pill-' + getText(row.value_type, 'string')).trim();
			type.textContent = getText(row.value_type, 'string');

			state.className = row.enabled === 1
				? 'userpref-def-admin-pill userpref-def-admin-pill-enabled'
				: 'userpref-def-admin-pill userpref-def-admin-pill-disabled';
			state.textContent = getText(row.enabled_label);

			const pillRow = document.createElement('div');
			pillRow.style.display = 'flex';
			pillRow.style.gap = '4px';
			pillRow.style.flexWrap = 'wrap';
			pillRow.appendChild(type);
			pillRow.appendChild(state);

			wrapper.appendChild(pillRow);
			wrapper.appendChild(sub);

			return wrapper;
		}

		function renderPre(value) {
			const pre = document.createElement('pre');
			pre.className = 'userpref-def-admin-pre';
			pre.textContent = getText(value);

			return pre;
		}

		function renderMeta(value, row) {
			const wrapper = createElement('userpref-def-admin-cell-stack');
			const main = createElement('userpref-def-admin-cell-main', String(row.sort_order));
			const sub = createElement('userpref-def-admin-cell-sub', 'Updated: ' + getText(row.updated));

			wrapper.appendChild(main);
			wrapper.appendChild(sub);

			return wrapper;
		}

		function buildFilterPayload(filters) {
			const result = {};

			Object.entries(filters || {}).forEach(([key, value]) => {
				if(value === '' || value === null || value === undefined) {
					return;
				}

				result[key] = value;
			});

			return result;
		}

		async function postJson(payload) {
			const response = await fetch(ENDPOINT_URL, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify(payload)
			});

			if(!response.ok) {
				throw new Error('Request failed with status ' + String(response.status));
			}

			return response.json();
		}

		async function refreshGrid() {
			if(!grid) {
				return;
			}

			const commands = ['reloadData', 'reload', 'refreshData', 'refresh'];

			if(typeof grid.execute === 'function') {
				for(const commandName of commands) {
					try {
						const result = grid.execute(commandName);

						if(result && typeof result.then === 'function') {
							await result;
						}

						return;
					}
					catch(error) {}
				}
			}

			for(const methodName of commands) {
				if(typeof grid[methodName] === 'function') {
					const result = grid[methodName]();

					if(result && typeof result.then === 'function') {
						await result;
					}

					return;
				}
			}

			window.location.reload();
		}

		function getModalElement() {
			return document.querySelector('#userpref-def-admin-modal');
		}

		function getEditorElements() {
			return {
				modal: document.querySelector('#userpref-def-admin-modal'),
				title: document.querySelector('#userpref-def-admin-modal-title'),
				error: document.querySelector('#userpref-def-admin-error'),
				id: document.querySelector('#userpref-def-admin-id'),
				prefKey: document.querySelector('#userpref-def-admin-pref-key'),
				description: document.querySelector('#userpref-def-admin-description'),
				valueType: document.querySelector('#userpref-def-admin-value-type'),
				defaultScope: document.querySelector('#userpref-def-admin-default-scope'),
				sortOrder: document.querySelector('#userpref-def-admin-sort-order'),
				enabled: document.querySelector('#userpref-def-admin-enabled'),
				systemTemplate: document.querySelector('#userpref-def-admin-system-template'),
				allowedValues: document.querySelector('#userpref-def-admin-allowed-values'),
				allowedValuesHint: document.querySelector('#userpref-def-admin-allowed-values-hint'),
				deleteButton: document.querySelector('#userpref-def-admin-delete-current')
			};
		}

		function setEditorError(message) {
			const elements = getEditorElements();

			if(!elements.error) {
				return;
			}

			elements.error.textContent = getText(message, '');
			elements.error.classList.toggle('is-visible', getText(message, '') !== '');
		}

		function updateAllowedValuesHint() {
			const elements = getEditorElements();

			if(!elements.valueType || !elements.allowedValues || !elements.allowedValuesHint) {
				return;
			}

			const selectedType = elements.valueType.value || 'string';

			if(selectedType === 'bool') {
				elements.allowedValues.value = '';
				elements.allowedValues.disabled = true;
				elements.allowedValues.placeholder = '';
				elements.allowedValuesHint.textContent = 'Boolean preferences do not use allowed values. False disables system template injection.';
				return;
			}

			elements.allowedValues.disabled = false;
			elements.allowedValues.placeholder = '[\n\t"value1",\n\t"value2"\n]';

			if(selectedType === 'enum') {
				elements.allowedValuesHint.textContent = 'Required for enum. Use a JSON list of allowed scalar values.';
				return;
			}

			elements.allowedValuesHint.textContent = 'Optional for string. Leave empty to allow any non-empty scalar value.';
		}

		function openEditor(record = null) {
			const elements = getEditorElements();

			if(!elements.modal) {
				return;
			}

			currentEditorRecord = record;
			setEditorError('');

			const isExisting = !!record;

			elements.title.textContent = isExisting ? 'Edit preference definition' : 'Add preference definition';
			elements.id.value = isExisting ? getText(record.id, '') : '';
			elements.prefKey.value = isExisting ? getText(record.pref_key, '') : '';
			elements.description.value = isExisting ? getText(record.description, '') : '';
			elements.valueType.value = isExisting ? getText(record.value_type, 'string') : 'string';
			elements.defaultScope.value = isExisting ? getText(record.default_scope, 'user') : 'user';
			elements.sortOrder.value = isExisting ? getText(record.sort_order, '100') : '100';
			elements.enabled.value = isExisting ? getText(record.enabled, '1') : '1';
			elements.systemTemplate.value = isExisting ? getText(record.system_template, '') : '';
			elements.allowedValues.value = isExisting ? getText(record.allowed_values_edit, '') : '';
			elements.deleteButton.hidden = !isExisting;

			updateAllowedValuesHint();

			elements.modal.classList.add('is-open');
			elements.modal.setAttribute('aria-hidden', 'false');

			window.setTimeout(() => {
				if(elements.prefKey.value === '') {
					elements.prefKey.focus();
					return;
				}

				elements.systemTemplate.focus();
			}, 0);
		}

		function closeEditor() {
			const modal = getModalElement();

			if(!modal) {
				return;
			}

			currentEditorRecord = null;
			setEditorError('');
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
		}

		async function loadRecord(row) {
			const response = await postJson({
				mode: 'record',
				id: row && row.id ? row.id : ''
			});

			if(!response || response.ok !== true || !response.record) {
				throw new Error(getText(response && response.error, 'Preference definition not found.'));
			}

			return response.record;
		}

		async function openEditorForRow(row) {
			try {
				setLog('Loading preference definition...');
				const record = await loadRecord(row);
				openEditor(record);
				setLog('Loaded preference definition ' + getText(record.pref_key) + '.');
			}
			catch(error) {
				setLog('Failed to load preference definition: ' + getText(error && error.message, String(error)));
			}
		}

		async function saveEditor() {
			const elements = getEditorElements();

			setEditorError('');

			const payload = {
				mode: 'save',
				id: elements.id.value,
				pref_key: elements.prefKey.value,
				description: elements.description.value,
				value_type: elements.valueType.value,
				default_scope: elements.defaultScope.value,
				sort_order: elements.sortOrder.value,
				enabled: elements.enabled.value,
				system_template: elements.systemTemplate.value,
				allowed_values: elements.allowedValues.value
			};

			try {
				const response = await postJson(payload);

				if(!response || response.ok !== true) {
					throw new Error(getText(response && response.error, 'Save failed.'));
				}

				closeEditor();
				await refreshGrid();

				const record = response.record || payload;
				setLog('Saved preference definition ' + getText(record.pref_key) + '.');
			}
			catch(error) {
				setEditorError(getText(error && error.message, String(error)));
			}
		}

		async function deleteRecord(row) {
			if(!row || !row.id) {
				setLog('Missing preference definition id.');
				return;
			}

			const label = getText(row.pref_key);

			if(!window.confirm('Delete preference definition "' + label + '"?')) {
				return;
			}

			try {
				const response = await postJson({
					mode: 'delete',
					id: row.id
				});

				if(!response || response.ok !== true) {
					throw new Error(getText(response && response.error, 'Delete failed.'));
				}

				await refreshGrid();
				setLog('Deleted preference definition ' + label + '.');
			}
			catch(error) {
				setLog('Failed to delete preference definition ' + label + ': ' + getText(error && error.message, String(error)));
			}
		}

		async function deleteCurrentEditorRecord() {
			if(!currentEditorRecord) {
				return;
			}

			const record = currentEditorRecord;
			closeEditor();
			await deleteRecord(record);
		}

		function bindEditorEvents() {
			const closeButton = document.querySelector('#userpref-def-admin-close');
			const cancelButton = document.querySelector('#userpref-def-admin-cancel');
			const saveButton = document.querySelector('#userpref-def-admin-save');
			const deleteButton = document.querySelector('#userpref-def-admin-delete-current');
			const modal = getModalElement();
			const elements = getEditorElements();

			if(closeButton) {
				closeButton.addEventListener('click', () => closeEditor());
			}

			if(cancelButton) {
				cancelButton.addEventListener('click', () => closeEditor());
			}

			if(saveButton) {
				saveButton.addEventListener('click', () => saveEditor());
			}

			if(deleteButton) {
				deleteButton.addEventListener('click', () => deleteCurrentEditorRecord());
			}

			if(elements.valueType) {
				elements.valueType.addEventListener('change', () => updateAllowedValuesHint());
			}

			if(elements.systemTemplate) {
				elements.systemTemplate.addEventListener('keydown', (event) => {
					if((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
						event.preventDefault();
						saveEditor();
					}

					if(event.key === 'Escape') {
						event.preventDefault();
						closeEditor();
					}
				});
			}

			if(elements.allowedValues) {
				elements.allowedValues.addEventListener('keydown', (event) => {
					if((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
						event.preventDefault();
						saveEditor();
					}
				});
			}

			if(modal) {
				modal.addEventListener('click', (event) => {
					if(event.target === modal) {
						closeEditor();
					}
				});
			}

			document.addEventListener('keydown', (event) => {
				if(event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
					event.preventDefault();
					closeEditor();
				}
			});
		}

		function createPreferenceActionsPlugin() {
			return {
				name: 'preferenceDefinitionActions',

				layoutContributions() {
					return [
						{
							zone: 'topLine1',
							order: 5,
							render() {
								const wrapper = document.createElement('div');
								wrapper.className = 'userpref-def-admin-top-actions';

								const addButton = createButton(
									'userpref-def-admin-button userpref-def-admin-button-primary',
									'Add preference definition'
								);

								addButton.addEventListener('click', () => openEditor(null));
								wrapper.appendChild(addButton);

								return wrapper;
							}
						}
					];
				}
			};
		}

		async function initGrid() {
			const root = document.querySelector(GRID_SELECTOR);

			if(!root || root.dataset.initialized === '1') {
				return;
			}

			root.dataset.initialized = '1';
			bindEditorEvents();

			const modularGridModule = await import(MODULAR_GRID_URL);

			const {
				AjaxAdapter,
				ColumnVisibilityPlugin,
				FiltersPlugin,
				HeaderMenuPlugin,
				InfoPlugin,
				ModularGrid,
				ResetPlugin,
				RowActionsPlugin,
				SearchPlugin,
				SessionStoragePlugin
			} = modularGridModule;

			const sortTypes = {
				pref_key: 'string',
				value_type: 'string',
				default_scope: 'string',
				sort_order: 'number',
				enabled: 'number',
				updated: 'string'
			};

			const layout = {
				type: 'stack',
				className: 'mg-layout-root',
				children: [
					{
						type: 'zone',
						key: 'topLine1',
						className: 'userpref-def-admin-panel userpref-def-admin-panel--main'
					},
					{
						type: 'zone',
						key: 'topLine2',
						className: 'userpref-def-admin-panel userpref-def-admin-panel--filters'
					},
					{
						type: 'view',
						key: 'main',
						className: 'userpref-def-admin-main'
					},
					{
						type: 'zone',
						key: 'statusZone',
						className: 'userpref-def-admin-panel userpref-def-admin-panel--status'
					}
				]
			};

			const adapter = new AjaxAdapter({
				url: ENDPOINT_URL,
				method: 'POST',
				rowsPath: 'data',
				totalPath: 'total',
				mapRequest(request) {
					const state = grid ? grid.getState() : {};
					const filters = buildFilterPayload(state.filters || {});
					const sortKey = request.sortKey || 'sort_order';
					const sortDirection = request.sortDirection || 'asc';

					return {
						mode: 'page',
						page: request.page || 1,
						pageSize: request.pageSize || BATCH_SIZE,
						search: request.search || '',
						sort: [
							{
								key: sortKey,
								dir: sortDirection,
								type: sortTypes[sortKey] || 'string'
							}
						],
						filters,
						group: []
					};
				}
			});

			grid = new ModularGrid(GRID_SELECTOR, {
				layout,
				adapter,
				dataMode: 'server',
				server: {
					searchDebounceMs: 220,
					watchStateKeys: ['query', 'filters']
				},
				features: {
					paging: true
				},
				pageSize: BATCH_SIZE,
				sort: {
					key: 'sort_order',
					direction: 'asc'
				},
				plugins: [
					createPreferenceActionsPlugin(),
					SearchPlugin,
					FiltersPlugin,
					HeaderMenuPlugin,
					InfoPlugin,
					RowActionsPlugin,
					ColumnVisibilityPlugin,
					ResetPlugin,
					SessionStoragePlugin
				],
				pluginOptions: {
					search: {
						zone: 'topLine1',
						order: 10,
						label: 'Search',
						placeholder: 'Search key, description, template or allowed values'
					},
					filters: {
						zone: 'topLine2',
						order: 10,
						stateKey: 'filters',
						showClearButton: true,
						clearLabel: 'Clear filters',
						fields: [
							{
								key: 'pref_key',
								label: 'Key',
								type: 'text',
								placeholder: 'Preference key',
								width: 220
							},
							{
								key: 'value_type',
								label: 'Type',
								type: 'select',
								options: VALUE_TYPE_OPTIONS
							},
							{
								key: 'default_scope',
								label: 'Scope',
								type: 'select',
								options: SCOPE_OPTIONS
							},
							{
								key: 'enabled',
								label: 'State',
								type: 'select',
								options: ENABLED_OPTIONS
							}
						]
					},
					headerMenu: {
						showSortActions: true,
						showClearSortAction: true,
						showHideColumnAction: true
					},
					columnVisibility: {
						zone: ''
					},
					reset: {
						zone: 'topLine1',
						order: 30,
						label: 'Reset',
						sections: ['query', 'filters', 'columns']
					},
					sessionStorage: {
						key: 'userpref-def-admin-grid-v1',
						sections: ['query', 'filters', 'columns']
					},
					info: {
						zone: 'statusZone',
						order: 10,
						displayMode: 'loaded'
					},
					rowActions: {
						headerMenu: {
							enabled: true,
							buttonLabel: '...',
							items: [
								{
									type: 'columnVisibility',
									label: 'Columns',
									showReset: true,
									resetLabel: 'Reset columns'
								}
							]
						},
						items: [
							{
								key: 'edit',
								label: 'Edit',
								onClick(context) {
									openEditorForRow(context.row);
								}
							},
							{
								key: 'delete',
								label: 'Delete',
								onClick(context) {
									deleteRecord(context.row);
								}
							}
						]
					}
				},
				columns: [
					{
						key: 'pref_key',
						label: 'Preference',
						width: 340,
						headerMenu: {
							defaultSortKey: 'pref_key',
							defaultSortDirection: 'asc',
							sortOptions: [
								{ key: 'pref_key', label: 'Key' }
							]
						},
						render(value, row) {
							return renderName(value, row);
						}
					},
					{
						key: 'value_type',
						label: 'Type',
						width: 190,
						headerMenu: {
							defaultSortKey: 'value_type',
							defaultSortDirection: 'asc',
							sortOptions: [
								{ key: 'value_type', label: 'Type' },
								{ key: 'default_scope', label: 'Scope' },
								{ key: 'enabled', label: 'State' }
							]
						},
						render(value, row) {
							return renderType(value, row);
						}
					},
					{
						key: 'system_template_preview',
						label: 'System template',
						width: 540,
						render(value) {
							return renderPre(value);
						}
					},
					{
						key: 'allowed_values_preview',
						label: 'Allowed values',
						width: 320,
						render(value) {
							return renderPre(value || '-');
						}
					},
					{
						key: 'sort_order',
						label: 'Order',
						width: 160,
						headerMenu: {
							defaultSortKey: 'sort_order',
							defaultSortDirection: 'asc',
							sortOptions: [
								{ key: 'sort_order', label: 'Sort order' },
								{ key: 'updated', label: 'Updated' }
							]
						},
						render(value, row) {
							return renderMeta(value, row);
						}
					},
					{
						key: 'default_scope',
						label: 'Scope',
						width: 120,
						visible: false,
						headerMenu: {
							defaultSortKey: 'default_scope',
							defaultSortDirection: 'asc',
							sortOptions: [
								{ key: 'default_scope', label: 'Scope' }
							]
						}
					},
					{
						key: 'enabled_label',
						label: 'State',
						width: 120,
						visible: false,
						headerMenu: {
							defaultSortKey: 'enabled',
							defaultSortDirection: 'desc',
							sortOptions: [
								{ key: 'enabled', label: 'State' }
							]
						}
					}
				]
			});

			await grid.init();
			setLog('Initial preference definitions loaded.');
		}

		initGrid().catch((error) => {
			console.error('UserPrefDefAdminDisplay failed:', error);
			setLog('Preference definition grid failed: ' + getText(error && error.message, String(error)));
		});
	})();
</script>
