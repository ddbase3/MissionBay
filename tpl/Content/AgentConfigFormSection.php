<?php
	$agentConfigForm = is_array($this->_['agent_config_form'] ?? null) ? $this->_['agent_config_form'] : [];
	$values = is_array($agentConfigForm['values'] ?? null) ? $agentConfigForm['values'] : [];
	$llmOptions = is_array($agentConfigForm['llm_options'] ?? null) ? $agentConfigForm['llm_options'] : [];
	$agentComponentPresets = is_array($agentConfigForm['agent_component_presets'] ?? null) ? $agentConfigForm['agent_component_presets'] : [];
	$agentComponents = is_array($values['agent_components'] ?? null) ? $values['agent_components'] : [];
	$formId = (string)($agentConfigForm['form_id'] ?? 'base3_agent_config');
	$rootId = $formId . '_agent_config_section';

	$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$checked = static fn($value): string => !empty($value) ? ' checked="checked"' : '';
	$selected = static fn($current, $value): string => (string)$current === (string)$value ? ' selected="selected"' : '';
	$fixedConfigValue = static function(array $config, string $key, $default = '') {
		$value = $config[$key] ?? null;

		if (!is_array($value) || (string)($value['mode'] ?? '') !== 'fixed') {
			return $default;
		}

		return $value['value'] ?? $default;
	};

	$presetCapabilities = [];
	foreach ($agentComponentPresets as $presetOption) {
		$presetOptionId = (string)($presetOption['id'] ?? '');

		if ($presetOptionId === '') {
			continue;
		}

		$presetOptionCapabilities = is_array($presetOption['capabilities'] ?? null) ? $presetOption['capabilities'] : [];
		$presetCapabilities[$presetOptionId] = array_values(array_filter(array_map('strval', $presetOptionCapabilities)));
	}

	$capabilityText = static function(array $capabilities): string {
		$capabilities = array_values(array_filter(array_map('strval', $capabilities)));

		return $capabilities === [] ? '-' : implode(', ', $capabilities);
	};

	$componentCapabilityText = static function(array $component) use ($presetCapabilities, $capabilityText): string {
		$presetId = (string)($component['preset'] ?? '');

		if ($presetId !== '' && isset($presetCapabilities[$presetId])) {
			return $capabilityText($presetCapabilities[$presetId]);
		}

		return $capabilityText(is_array($component['attach_as'] ?? null) ? $component['attach_as'] : []);
	};
?>

<style>
	.base3-agent-config-section {
		margin: 0 0 20px;
		padding: 16px;
		border: 1px solid #ddd;
		border-radius: 4px;
		background: #fff;
	}

	.base3-agent-config-section h3 {
		margin: 0 0 14px;
		font-size: 18px;
	}

	.base3-agent-config-row {
		display: grid;
		grid-template-columns: minmax(150px, 220px) minmax(0, 1fr);
		gap: 8px 18px;
		margin: 0 0 14px;
	}

	.base3-agent-config-row:last-child {
		margin-bottom: 0;
	}

	.base3-agent-config-label {
		padding-top: 7px;
		font-weight: bold;
	}

	.base3-agent-config-root input[type="text"],
	.base3-agent-config-root select,
	.base3-agent-config-root textarea {
		width: 100%;
		max-width: 620px;
		min-height: 34px;
		padding: 6px 8px;
		border: 1px solid #bbb;
		border-radius: 3px;
		background: #fff;
		color: inherit;
		font: inherit;
		line-height: 1.4;
	}

	.base3-agent-config-root textarea {
		max-width: 760px;
		resize: vertical;
		font-family: monospace;
	}

	.base3-agent-config-system-prompt {
		min-height: 320px;
	}

	.base3-agent-config-agent-flow {
		min-height: 420px;
	}

	.base3-agent-config-collapsible {
		max-width: 760px;
		margin: 14px 0 0;
		border: 1px solid #ddd;
		border-radius: 4px;
		background: #fafafa;
	}

	.base3-agent-config-collapsible summary {
		padding: 9px 12px;
		cursor: pointer;
		font-weight: bold;
	}

	.base3-agent-config-collapsible-content {
		padding: 0 12px 12px;
	}

	.base3-agent-config-help {
		max-width: 760px;
		margin: 5px 0 0;
		color: #666;
		font-size: 12px;
	}

	.base3-agent-config-components {
		max-width: none;
	}

	.base3-agent-config-component-row {
		display: grid;
		grid-template-columns: minmax(150px, 1.5fr) 74px minmax(80px, 0.8fr) minmax(70px, 0.8fr) minmax(95px, 1fr) minmax(110px, 1.2fr) minmax(150px, 1.8fr) auto;
		gap: 7px;
		align-items: start;
		margin: 0 0 8px;
		padding: 8px;
		border: 1px solid #ddd;
		border-radius: 4px;
		background: #fafafa;
	}

	.base3-agent-config-component-row label {
		display: block;
		margin: 0 0 4px;
		color: #666;
		font-size: 11px;
		font-weight: normal;
	}

	.base3-agent-config-component-row input[type="text"],
	.base3-agent-config-component-row select {
		max-width: none;
	}

	.base3-agent-config-component-capabilities {
		min-height: 34px;
		padding: 7px 0 0;
		color: #333;
		font-size: 12px;
		line-height: 1.35;
	}

	.base3-agent-config-component-muted {
		opacity: 0.55;
	}

	.base3-agent-config-component-check {
		padding-top: 24px;
		text-align: center;
	}

	.base3-agent-config-component-remove,
	.base3-agent-config-component-add {
		min-height: 34px;
		padding: 6px 10px;
		cursor: pointer;
		white-space: nowrap;
	}

	@media (max-width: 700px) {
		.base3-agent-config-section {
			padding: 12px;
		}

		.base3-agent-config-row {
			display: block;
		}

		.base3-agent-config-label {
			display: block;
			padding-top: 0;
			margin: 0 0 5px;
		}

		.base3-agent-config-root input[type="text"],
		.base3-agent-config-root select,
		.base3-agent-config-root textarea {
			max-width: none;
		}

		.base3-agent-config-component-row {
			display: block;
		}

		.base3-agent-config-component-row > div {
			margin: 0 0 7px;
		}

		.base3-agent-config-component-check {
			padding-top: 0;
			text-align: left;
		}
	}
</style>

<div id="<?php echo $e($rootId); ?>" class="base3-agent-config-root" data-base3-agent-config-root="1">
	<div class="base3-agent-config-section">
		<h3>LLM</h3>

		<div class="base3-agent-config-row">
			<label for="<?php echo $e($formId); ?>_llm" class="base3-agent-config-label">LLM</label>
			<div>
				<select id="<?php echo $e($formId); ?>_llm" name="llm" class="form-control">
					<option value="">Use AgentFlow JSON value</option>
<?php foreach ($llmOptions as $llm) {
	$llmId = (string)($llm['id'] ?? '');
	if ($llmId === '') {
		continue;
	}

	$parts = [];
	$label = trim((string)($llm['label'] ?? ''));

	if ($label === '') {
		$label = $llmId;
	}

	$parts[] = $label;

	if (!empty($llm['model'])) {
		$parts[] = (string)$llm['model'];
	}

	if (!empty($llm['driver'])) {
		$parts[] = (string)$llm['driver'];
	}

	$text = implode(' / ', $parts);

	if (empty($llm['enabled'])) {
		$text .= ' [disabled]';
	}
?>
					<option value="<?php echo $e($llmId); ?>"<?php echo $selected($values['llm'] ?? '', $llmId); ?>><?php echo $e($text); ?></option>
<?php } ?>
				</select>
				<p class="base3-agent-config-help">
					If an LLM is selected here, the AgentFlow resource <code>chatllm</code> is updated to use this configured LLM. Leave empty to keep the raw AgentFlow value unchanged.
				</p>
			</div>
		</div>
	</div>

	<div class="base3-agent-config-section">
		<h3>Tools &amp; Memory</h3>

		<div class="base3-agent-config-components" data-base3-agent-config-components>
			<div data-base3-agent-config-component-items>
<?php foreach ($agentComponents as $componentIndex => $component) {
	if (!is_array($component)) {
		continue;
	}

	$presetId = (string)($component['preset'] ?? '');
	$memoryConfig = is_array($component['memory_config'] ?? null) ? $component['memory_config'] : [];
	$toolConfig = is_array($component['tool_config'] ?? null) ? $component['tool_config'] : [];
	$order = (string)($component['order'] ?? $fixedConfigValue($memoryConfig, 'priority', ''));
	$namespace = (string)$fixedConfigValue($toolConfig, 'namespace', '');
	$label = (string)$fixedConfigValue($toolConfig, 'label', '');
	$description = (string)$fixedConfigValue($toolConfig, 'description', '');
?>
				<div class="base3-agent-config-component-row" data-base3-agent-config-component-row="1">
					<div>
						<label>Preset</label>
						<select name="agent_components[<?php echo $e($componentIndex); ?>][preset]" class="form-control">
							<option value="">Select preset</option>
<?php foreach ($agentComponentPresets as $preset) {
	$optionId = (string)($preset['id'] ?? '');
	if ($optionId === '') {
		continue;
	}

	$optionLabel = trim((string)($preset['label'] ?? ''));
	if ($optionLabel === '') {
		$optionLabel = $optionId;
	}
?>
							<option value="<?php echo $e($optionId); ?>"<?php echo $selected($presetId, $optionId); ?>><?php echo $e($optionLabel); ?> (<?php echo $e($optionId); ?>)</option>
<?php } ?>
						</select>
					</div>
					<div class="base3-agent-config-component-check">
						<input type="hidden" name="agent_components[<?php echo $e($componentIndex); ?>][enabled]" value="0" />
						<label>
							<input type="checkbox" name="agent_components[<?php echo $e($componentIndex); ?>][enabled]" value="1"<?php echo $checked($component['enabled'] ?? true); ?> />
							Active
						</label>
					</div>
					<div>
						<label>Use as</label>
						<div class="base3-agent-config-component-capabilities" data-base3-agent-config-component-capabilities="1"><?php echo $e($componentCapabilityText($component)); ?></div>
					</div>
					<div data-base3-agent-config-component-memory-fields="1">
						<label>Order</label>
						<input type="text" name="agent_components[<?php echo $e($componentIndex); ?>][order]" class="form-control" value="<?php echo $e($order); ?>" placeholder="10" />
					</div>
					<div data-base3-agent-config-component-tool-fields="1">
						<label>Namespace</label>
						<input type="text" name="agent_components[<?php echo $e($componentIndex); ?>][namespace]" class="form-control" value="<?php echo $e($namespace); ?>" placeholder="web" />
					</div>
					<div data-base3-agent-config-component-tool-fields="1">
						<label>Label</label>
						<input type="text" name="agent_components[<?php echo $e($componentIndex); ?>][label]" class="form-control" value="<?php echo $e($label); ?>" placeholder="Visible tool label" />
					</div>
					<div data-base3-agent-config-component-tool-fields="1">
						<label>Description</label>
						<input type="text" name="agent_components[<?php echo $e($componentIndex); ?>][description]" class="form-control" value="<?php echo $e($description); ?>" placeholder="Visible tool description" />
					</div>
					<div>
						<label>&nbsp;</label>
						<button type="button" class="btn btn-default base3-agent-config-component-remove" data-base3-agent-config-component-remove="1">Remove</button>
					</div>
				</div>
<?php } ?>
			</div>

			<button type="button" class="btn btn-default base3-agent-config-component-add" data-base3-agent-config-component-add="1">
				Add component
			</button>

			<input type="hidden" name="agent_components_json_b64" data-base3-agent-config-components-b64="1" value="" />
			<input type="hidden" name="agent_components_json" data-base3-agent-config-components-json="1" value="[]" />

			<p class="base3-agent-config-help">
				Components are stored as <code>agent_components</code>. Memory/tool exposure is derived from the selected preset resource implementation; the runtime builds the configured wrappers during flow construction.
			</p>
<?php if ($agentComponentPresets === []) { ?>
			<p class="base3-agent-config-help">
				No presets found in SettingsStore group <code>agent-component-preset</code>.
			</p>
<?php } ?>
		</div>
	</div>

	<div class="base3-agent-config-section">
		<h3>Prompt &amp; Flow</h3>

		<div class="base3-agent-config-row">
			<label for="<?php echo $e($formId); ?>_system_prompt" class="base3-agent-config-label">System prompt</label>
			<div>
				<textarea id="<?php echo $e($formId); ?>_system_prompt" name="system_prompt" class="form-control base3-agent-config-system-prompt"><?php echo $e($values['system_prompt'] ?? ''); ?></textarea>
				<p class="base3-agent-config-help">
					Server-side prompt for the agent. This value should not be rendered into client-side chatbot configuration.
				</p>
			</div>
		</div>

		<div class="base3-agent-config-row">
			<div class="base3-agent-config-label">AgentFlow</div>
			<div>
				<details class="base3-agent-config-collapsible">
					<summary>AgentFlow configuration JSON</summary>
					<div class="base3-agent-config-collapsible-content">
						<textarea id="<?php echo $e($formId); ?>_agent_flow" name="agent_flow" class="form-control base3-agent-config-agent-flow"><?php echo $e($values['agent_flow_json'] ?? '{}'); ?></textarea>
						<input type="hidden" name="agent_flow_b64" data-base3-agent-config-agent-flow-b64="1" value="" />
						<p class="base3-agent-config-help">
							Raw JSON configuration for the service-side AgentFlow. Selecting an LLM above updates only the <code>chatllm</code> resource during save.
						</p>
					</div>
				</details>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	var root = document.getElementById(<?php echo json_encode($rootId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);

	if (!root || root.getAttribute('data-base3-agent-config-ready') === '1') {
		return;
	}

	root.setAttribute('data-base3-agent-config-ready', '1');

	var agentComponentsRoot = root.querySelector('[data-base3-agent-config-components]');
	var agentComponentsItems = root.querySelector('[data-base3-agent-config-component-items]');
	var agentComponentsAdd = root.querySelector('[data-base3-agent-config-component-add]');
	var agentComponentPresets = <?php echo json_encode(array_values($agentComponentPresets), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	var nextAgentComponentIndex = <?php echo json_encode(count($agentComponents), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

	function getFixedConfigValue(config, key, defaultValue) {
		var value = config && typeof config === 'object' ? config[key] : null;

		if (!value || typeof value !== 'object' || value.mode !== 'fixed') {
			return defaultValue;
		}

		return Object.prototype.hasOwnProperty.call(value, 'value') ? value.value : defaultValue;
	}

	function getAgentComponentPresetById(id) {
		id = String(id || '');

		for (var i = 0; i < agentComponentPresets.length; i++) {
			if (String(agentComponentPresets[i].id || '') === id) {
				return agentComponentPresets[i];
			}
		}

		return null;
	}

	function getAgentComponentCapabilities(id, fallbackComponent) {
		var preset = getAgentComponentPresetById(id);

		if (preset && Array.isArray(preset.capabilities)) {
			return preset.capabilities.map(String).filter(Boolean);
		}

		if (fallbackComponent && Array.isArray(fallbackComponent.attach_as)) {
			return fallbackComponent.attach_as.map(String).filter(Boolean);
		}

		return [];
	}

	function formatAgentComponentCapabilities(capabilities) {
		capabilities = Array.isArray(capabilities) ? capabilities.map(String).filter(Boolean) : [];

		return capabilities.length ? capabilities.join(', ') : '-';
	}

	function encodeUtf8Base64(value) {
		value = String(value === null || value === undefined ? '' : value);

		if (window.TextEncoder) {
			var bytes = new TextEncoder().encode(value);
			var binary = '';
			var chunkSize = 0x8000;

			for (var offset = 0; offset < bytes.length; offset += chunkSize) {
				var chunk = bytes.subarray(offset, offset + chunkSize);
				binary += String.fromCharCode.apply(null, Array.prototype.slice.call(chunk));
			}

			return btoa(binary);
		}

		return btoa(unescape(encodeURIComponent(value)));
	}

	function readNamedValue(row, suffix) {
		var field = row.querySelector('[name$="[' + suffix + ']"]');

		return field ? String(field.value || '').trim() : '';
	}

	function readNamedCheckbox(row, suffix) {
		var checkbox = row.querySelector('input[type="checkbox"][name$="[' + suffix + ']"]');

		return checkbox ? !!checkbox.checked : false;
	}

	function buildAgentComponentsFromDom() {
		var items = [];

		if (!agentComponentsItems) {
			return items;
		}

		agentComponentsItems.querySelectorAll('[data-base3-agent-config-component-row]').forEach(function(row) {
			var preset = readNamedValue(row, 'preset');

			if (!preset) {
				return;
			}

			items.push({
				preset: preset,
				enabled: readNamedCheckbox(row, 'enabled'),
				order: readNamedValue(row, 'order'),
				namespace: readNamedValue(row, 'namespace'),
				label: readNamedValue(row, 'label'),
				description: readNamedValue(row, 'description')
			});
		});

		return items;
	}

	function syncEncodedAgentConfigFields() {
		var agentFlow = root.querySelector('[name="agent_flow"]');
		var agentFlowB64 = root.querySelector('[data-base3-agent-config-agent-flow-b64]');
		var componentsJson = root.querySelector('[data-base3-agent-config-components-json]');
		var componentsB64 = root.querySelector('[data-base3-agent-config-components-b64]');
		var components = buildAgentComponentsFromDom();
		var componentsText = JSON.stringify(components);

		if (agentFlow && agentFlowB64) {
			agentFlowB64.value = encodeUtf8Base64(agentFlow.value || '{}');
		}

		if (componentsJson) {
			componentsJson.value = componentsText;
		}

		if (componentsB64) {
			componentsB64.value = encodeUtf8Base64(componentsText);
		}
	}

	function updateAgentComponentRowState(row) {
		if (!row) {
			return;
		}

		var select = row.querySelector('select[name$="[preset]"]');
		var capabilitiesNode = row.querySelector('[data-base3-agent-config-component-capabilities]');
		var capabilities = getAgentComponentCapabilities(select ? select.value : '', null);
		var hasMemory = capabilities.indexOf('memory') !== -1;
		var hasTool = capabilities.indexOf('tool') !== -1;

		if (capabilitiesNode) {
			capabilitiesNode.textContent = formatAgentComponentCapabilities(capabilities);
		}

		row.querySelectorAll('[data-base3-agent-config-component-memory-fields]').forEach(function(cell) {
			cell.classList.toggle('base3-agent-config-component-muted', !hasMemory);
		});

		row.querySelectorAll('[data-base3-agent-config-component-tool-fields]').forEach(function(cell) {
			cell.classList.toggle('base3-agent-config-component-muted', !hasTool);
		});
	}

	function createAgentComponentRow(component) {
		component = component && typeof component === 'object' ? component : {};

		var index = nextAgentComponentIndex++;
		var row = document.createElement('div');
		var memoryConfig = component.memory_config && typeof component.memory_config === 'object' ? component.memory_config : {};
		var toolConfig = component.tool_config && typeof component.tool_config === 'object' ? component.tool_config : {};
		var order = Object.prototype.hasOwnProperty.call(component, 'order') ? component.order : getFixedConfigValue(memoryConfig, 'priority', '');
		var namespace = getFixedConfigValue(toolConfig, 'namespace', '');
		var label = getFixedConfigValue(toolConfig, 'label', '');
		var description = getFixedConfigValue(toolConfig, 'description', '');
		var enabled = !Object.prototype.hasOwnProperty.call(component, 'enabled') || !!component.enabled;

		row.className = 'base3-agent-config-component-row';
		row.setAttribute('data-base3-agent-config-component-row', '1');

		function fieldName(name) {
			return 'agent_components[' + index + '][' + name + ']';
		}

		function makeCell(labelText) {
			var cell = document.createElement('div');
			var labelNode = document.createElement('label');
			labelNode.appendChild(document.createTextNode(labelText));
			cell.appendChild(labelNode);
			return cell;
		}

		function makeInput(name, value, placeholder) {
			var input = document.createElement('input');
			input.type = 'text';
			input.name = fieldName(name);
			input.className = 'form-control';
			input.value = value || '';
			input.placeholder = placeholder || '';
			return input;
		}

		function makeHidden(name, value) {
			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = fieldName(name);
			hidden.value = value;
			return hidden;
		}

		function makeCheckbox(name, checkedValue, labelText, isChecked) {
			var cell = document.createElement('div');
			var labelNode = document.createElement('label');
			var checkbox = document.createElement('input');

			cell.className = 'base3-agent-config-component-check';
			checkbox.type = 'checkbox';
			checkbox.name = fieldName(name);
			checkbox.value = checkedValue;
			checkbox.checked = !!isChecked;

			cell.appendChild(makeHidden(name, '0'));
			labelNode.appendChild(checkbox);
			labelNode.appendChild(document.createTextNode(' ' + labelText));
			cell.appendChild(labelNode);

			return cell;
		}

		var presetCell = makeCell('Preset');
		var select = document.createElement('select');
		var emptyOption = document.createElement('option');

		select.name = fieldName('preset');
		select.className = 'form-control';
		emptyOption.value = '';
		emptyOption.appendChild(document.createTextNode('Select preset'));
		select.appendChild(emptyOption);

		agentComponentPresets.forEach(function(preset) {
			var id = String(preset.id || '');
			var text = String(preset.label || id);
			var option = document.createElement('option');

			if (!id) {
				return;
			}

			option.value = id;
			option.setAttribute('data-capabilities', Array.isArray(preset.capabilities) ? preset.capabilities.join(',') : '');
			option.appendChild(document.createTextNode(text + ' (' + id + ')'));

			if (String(component.preset || '') === id) {
				option.selected = true;
			}

			select.appendChild(option);
		});

		select.addEventListener('change', function() {
			updateAgentComponentRowState(row);
			syncEncodedAgentConfigFields();
		});

		presetCell.appendChild(select);
		row.appendChild(presetCell);
		row.appendChild(makeCheckbox('enabled', '1', 'Active', enabled));

		var capabilitiesCell = makeCell('Use as');
		var capabilitiesValue = document.createElement('div');
		capabilitiesValue.className = 'base3-agent-config-component-capabilities';
		capabilitiesValue.setAttribute('data-base3-agent-config-component-capabilities', '1');
		capabilitiesValue.appendChild(document.createTextNode(formatAgentComponentCapabilities(getAgentComponentCapabilities(component.preset || '', component))));
		capabilitiesCell.appendChild(capabilitiesValue);
		row.appendChild(capabilitiesCell);

		var orderCell = makeCell('Order');
		orderCell.setAttribute('data-base3-agent-config-component-memory-fields', '1');
		orderCell.appendChild(makeInput('order', order, '10'));
		row.appendChild(orderCell);

		var namespaceCell = makeCell('Namespace');
		namespaceCell.setAttribute('data-base3-agent-config-component-tool-fields', '1');
		namespaceCell.appendChild(makeInput('namespace', namespace, 'web'));
		row.appendChild(namespaceCell);

		var labelCell = makeCell('Label');
		labelCell.setAttribute('data-base3-agent-config-component-tool-fields', '1');
		labelCell.appendChild(makeInput('label', label, 'Visible tool label'));
		row.appendChild(labelCell);

		var descriptionCell = makeCell('Description');
		descriptionCell.setAttribute('data-base3-agent-config-component-tool-fields', '1');
		descriptionCell.appendChild(makeInput('description', description, 'Visible tool description'));
		row.appendChild(descriptionCell);

		var actionCell = makeCell('\u00a0');
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'btn btn-default base3-agent-config-component-remove';
		remove.setAttribute('data-base3-agent-config-component-remove', '1');
		remove.appendChild(document.createTextNode('Remove'));
		remove.addEventListener('click', function() {
			if (row.parentNode) {
				row.parentNode.removeChild(row);
			}

			syncEncodedAgentConfigFields();
		});
		actionCell.appendChild(remove);
		row.appendChild(actionCell);
		updateAgentComponentRowState(row);

		return row;
	}

	function renderAgentComponents(items) {
		if (!agentComponentsItems) {
			return;
		}

		if (!Array.isArray(items)) {
			items = [];
		}

		nextAgentComponentIndex = 0;
		agentComponentsItems.innerHTML = '';

		items.forEach(function(item) {
			agentComponentsItems.appendChild(createAgentComponentRow(item));
		});

		syncEncodedAgentConfigFields();
	}

	root.__base3AgentConfigUpdateValues = function(values) {
		if (!values || typeof values !== 'object') {
			return;
		}

		var map = {
			llm: 'llm',
			system_prompt: 'system_prompt',
			agent_flow_json: 'agent_flow'
		};

		Object.keys(map).forEach(function(key) {
			if (!Object.prototype.hasOwnProperty.call(values, key)) {
				return;
			}

			var field = root.querySelector('[name="' + map[key] + '"]');

			if (field) {
				field.value = values[key];
			}
		});

		if (Object.prototype.hasOwnProperty.call(values, 'agent_components')) {
			renderAgentComponents(values.agent_components);
		}

		syncEncodedAgentConfigFields();
	};

	root.__base3AgentConfigPrepareSubmit = function() {
		syncEncodedAgentConfigFields();

		return true;
	};

	root.addEventListener('input', syncEncodedAgentConfigFields);
	root.addEventListener('change', syncEncodedAgentConfigFields);

	if (agentComponentsRoot && agentComponentsAdd && agentComponentsItems) {
		agentComponentsAdd.addEventListener('click', function() {
			agentComponentsItems.appendChild(createAgentComponentRow({ enabled: true, attach_as: [] }));
		});

		agentComponentsRoot.querySelectorAll('[data-base3-agent-config-component-row]').forEach(function(row) {
			var select = row.querySelector('select[name$="[preset]"]');

			if (select) {
				select.addEventListener('change', function() {
					updateAgentComponentRowState(row);
					syncEncodedAgentConfigFields();
				});
			}

			updateAgentComponentRowState(row);
		});

		agentComponentsRoot.querySelectorAll('[data-base3-agent-config-component-remove]').forEach(function(remove) {
			remove.addEventListener('click', function() {
				var row = remove.closest('[data-base3-agent-config-component-row]');

				if (row && row.parentNode) {
					row.parentNode.removeChild(row);
				}

				syncEncodedAgentConfigFields();
			});
		});
	}

	syncEncodedAgentConfigFields();
})();
</script>
