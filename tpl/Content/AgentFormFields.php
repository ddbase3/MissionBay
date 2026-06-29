<?php
        $values = is_array($this->_['values'] ?? null) ? $this->_['values'] : [];
        $policyOptions = is_array($this->_['policy_options'] ?? null) ? $this->_['policy_options'] : [];
        $formId = (string)($this->_['form_id'] ?? 'base3_agent_config');
        $group = 'agent';
        $name = (string)($this->_['name'] ?? 'default');
        $rootId = $formId . '_agent_fields';
        $currentPolicy = (string)($values['policy_policy'] ?? '');
        $currentPolicyData = is_array($values['policy_data'] ?? null) ? $values['policy_data'] : [];

        if ($currentPolicy === '' && $policyOptions !== []) {
                $currentPolicy = (string)($policyOptions[0]['id'] ?? '');
        }

        $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $checked = static fn($value): string => !empty($value) ? ' checked="checked"' : '';
        $selected = static fn($current, $value): string => (string)$current === (string)$value ? ' selected="selected"' : '';
?>

<style>
        .base3-agent-fields,
        .base3-agent-fields * {
                box-sizing: border-box;
        }

        .base3-agent-section {
                margin: 0 0 20px;
                padding: 16px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
        }

        .base3-agent-section h3 {
                margin: 0 0 14px;
                font-size: 18px;
        }

        .base3-agent-row {
                display: grid;
                grid-template-columns: minmax(150px, 220px) minmax(0, 1fr);
                gap: 8px 18px;
                margin: 0 0 14px;
        }

        .base3-agent-row:last-child {
                margin-bottom: 0;
        }

        .base3-agent-label {
                padding-top: 7px;
                font-weight: bold;
        }

        .base3-agent-fields input[type="text"],
        .base3-agent-fields input[type="number"],
        .base3-agent-fields select,
        .base3-agent-fields textarea {
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

        .base3-agent-fields textarea {
                max-width: 760px;
                resize: vertical;
                font-family: monospace;
        }

        .base3-agent-user-prompt {
                min-height: 150px;
        }

        .base3-agent-json {
                min-height: 110px;
        }

        .base3-agent-help {
                max-width: 760px;
                margin: 5px 0 0;
                color: #666;
                font-size: 12px;
        }

        .base3-agent-checkbox-row {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                min-height: 34px;
        }

        .base3-agent-policy-fields {
                display: grid;
                gap: 10px;
                max-width: 760px;
                margin-top: 10px;
        }

        .base3-agent-policy-field {
                display: grid;
                grid-template-columns: minmax(140px, 220px) minmax(0, 1fr);
                gap: 8px 12px;
                align-items: start;
                padding: 8px;
                border: 1px solid #e2e2e2;
                border-radius: 4px;
                background: #fafafa;
        }

        .base3-agent-policy-field-required .base3-agent-policy-label::after {
                content: " *";
                color: #8a1f1f;
        }

        .base3-agent-policy-label {
                padding-top: 7px;
                font-weight: bold;
                font-size: 12px;
                word-break: break-word;
        }

        .base3-agent-policy-control {
                min-width: 0;
        }

        .base3-agent-policy-empty {
                padding: 8px;
                border: 1px solid #e2e2e2;
                border-radius: 4px;
                background: #fafafa;
                color: #666;
                font-size: 12px;
        }

        @media (max-width: 700px) {
                .base3-agent-section {
                        padding: 12px;
                }

                .base3-agent-row,
                .base3-agent-policy-field {
                        display: block;
                }

                .base3-agent-label,
                .base3-agent-policy-label {
                        display: block;
                        padding-top: 0;
                        margin: 0 0 5px;
                }

                .base3-agent-fields input[type="text"],
                .base3-agent-fields input[type="number"],
                .base3-agent-fields select,
                .base3-agent-fields textarea {
                        max-width: none;
                }
        }
</style>

<div
        id="<?php echo $e($rootId); ?>"
        class="base3-agent-fields"
        data-base3-agent-fields="1"
>
        <input type="hidden" name="agent_config_action" value="save" />
        <input type="hidden" name="agent_config_group" value="<?php echo $e($group); ?>" />
        <input type="hidden" name="agent_config_name" value="<?php echo $e($name); ?>" />

        <div class="base3-agent-section">
                <h3>Agent</h3>

                <div class="base3-agent-row">
                        <div class="base3-agent-label">Active</div>
                        <div>
                                <label class="base3-agent-checkbox-row">
                                        <input type="checkbox" name="enabled" value="1"<?php echo $checked($values['enabled'] ?? true); ?> />
                                        Run this configured agent when the timing policy allows it.
                                </label>
                        </div>
                </div>

                <div class="base3-agent-row">
                        <label for="<?php echo $e($formId); ?>_label" class="base3-agent-label">Label</label>
                        <div>
                                <input id="<?php echo $e($formId); ?>_label" type="text" name="label" class="form-control" value="<?php echo $e($values['label'] ?? ''); ?>" />
                                <p class="base3-agent-help">
                                        Human-readable title shown in administration lists.
                                </p>
                        </div>
                </div>

                <div class="base3-agent-row">
                        <label for="<?php echo $e($formId); ?>_user_prompt" class="base3-agent-label">User prompt</label>
                        <div>
                                <textarea id="<?php echo $e($formId); ?>_user_prompt" name="user_prompt" class="form-control base3-agent-user-prompt"><?php echo $e($values['user_prompt'] ?? ''); ?></textarea>
                                <p class="base3-agent-help">
                                        Prompt sent as the user message when this agent is executed.
                                </p>
                        </div>
                </div>
        </div>

        <div class="base3-agent-section">
                <h3>Timing</h3>

                <div class="base3-agent-row">
                        <label for="<?php echo $e($formId); ?>_policy" class="base3-agent-label">Policy</label>
                        <div>
                                <select id="<?php echo $e($formId); ?>_policy" name="policy" class="form-control" data-base3-agent-policy-select="1">
                                        <option value="">Select timing policy</option>
<?php foreach ($policyOptions as $policyOption) {
        $policyId = (string)($policyOption['id'] ?? '');
        $policyLabel = trim((string)($policyOption['label'] ?? ''));
        if ($policyId === '') {
                continue;
        }
        if ($policyLabel === '') {
                $policyLabel = $policyId;
        }
?>
                                        <option value="<?php echo $e($policyId); ?>"<?php echo $selected($currentPolicy, $policyId); ?>><?php echo $e($policyLabel); ?> (<?php echo $e($policyId); ?>)</option>
<?php } ?>
                                </select>
                                <p class="base3-agent-help">
                                        Policy configuration is generated from the policy schema.
                                </p>
                                <input type="hidden" name="policy_data_json" data-base3-agent-policy-data-json="1" value="{}" />
                                <input type="hidden" name="policy_data_b64" data-base3-agent-policy-data-b64="1" value="" />
                                <div class="base3-agent-policy-fields" data-base3-agent-policy-fields="1"></div>
                        </div>
                </div>
        </div>
<?php include DIR_PLUGIN . 'MissionBay/tpl/Content/AgentConfigFormSection.php'; ?>
</div>

<script>
(function() {
        var root = document.getElementById(<?php echo json_encode($rootId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);

        if (!root || root.getAttribute('data-base3-agent-ready') === '1') {
                return;
        }

        root.setAttribute('data-base3-agent-ready', '1');

        var policyOptions = <?php echo json_encode(array_values($policyOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var currentPolicyData = <?php echo json_encode($currentPolicyData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var policySelect = root.querySelector('[data-base3-agent-policy-select]');
        var policyFields = root.querySelector('[data-base3-agent-policy-fields]');
        var policyDataJson = root.querySelector('[data-base3-agent-policy-data-json]');
        var policyDataB64 = root.querySelector('[data-base3-agent-policy-data-b64]');

        function getPolicyOption(id) {
                id = String(id || '');

                for (var i = 0; i < policyOptions.length; i++) {
                        if (String(policyOptions[i].id || '') === id) {
                                return policyOptions[i];
                        }
                }

                return null;
        }

        function getSchemaProperties(schema) {
                if (schema && schema.properties && typeof schema.properties === 'object' && !Array.isArray(schema.properties)) {
                        return schema.properties;
                }

                if (schema && schema.fields && typeof schema.fields === 'object' && !Array.isArray(schema.fields)) {
                        return schema.fields;
                }

                if (schema && schema.data && schema.data.properties && typeof schema.data.properties === 'object' && !Array.isArray(schema.data.properties)) {
                        return schema.data.properties;
                }

                return {};
        }

        function getSchemaRequired(schema) {
                return Array.isArray(schema && schema.required) ? schema.required.map(String) : [];
        }

        function getSchemaType(schema) {
                var type = schema && schema.type !== undefined ? schema.type : 'string';

                if (Array.isArray(type)) {
                        return String(type.find(function(item) { return item !== 'null'; }) || 'string');
                }

                return String(type || 'string');
        }

        function getSchemaDefault(schema) {
                if (schema && Object.prototype.hasOwnProperty.call(schema, 'default')) {
                        return schema.default;
                }

                var type = getSchemaType(schema);

                if (type === 'boolean') {
                        return false;
                }

                if (type === 'array') {
                        return [];
                }

                if (type === 'object') {
                        return {};
                }

                return '';
        }

        function createElement(className, text) {
                var element = document.createElement('div');

                if (className) {
                        element.className = className;
                }

                if (text !== undefined && text !== '') {
                        element.appendChild(document.createTextNode(String(text)));
                }

                return element;
        }

        function stringifyJson(value) {
                try {
                        return JSON.stringify(value, null, 2);
                }
                catch (error) {
                        return '';
                }
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

        function markPolicyControl(control, key, type) {
                if (!control) {
                        return control;
                }

                control.setAttribute('data-base3-agent-policy-key', key);
                control.setAttribute('data-base3-agent-policy-type', type);
                control.addEventListener('input', syncPolicyDataJson);
                control.addEventListener('change', syncPolicyDataJson);

                return control;
        }

        function readPolicyControlValue(control) {
                var type = control.getAttribute('data-base3-agent-policy-type') || 'string';

                if (type === 'boolean') {
                        return !!control.checked;
                }

                if (type === 'integer') {
                        return control.value === '' ? null : parseInt(control.value, 10);
                }

                if (type === 'number') {
                        return control.value === '' ? null : Number(control.value);
                }

                if (type === 'object' || type === 'array') {
                        try {
                                return JSON.parse(control.value || (type === 'array' ? '[]' : '{}'));
                        }
                        catch (error) {
                                return type === 'array' ? [] : {};
                        }
                }

                return control.value;
        }

        function buildPolicyDataFromControls() {
                var data = {};
                var controls = root.querySelectorAll('[data-base3-agent-policy-key]');

                controls.forEach(function(control) {
                        var key = control.getAttribute('data-base3-agent-policy-key') || '';

                        if (key === '') {
                                return;
                        }

                        data[key] = readPolicyControlValue(control);
                });

                return data;
        }

        function syncPolicyDataJson() {
                var json = stringifyJson(buildPolicyDataFromControls()) || '{}';

                if (policyDataJson) {
                        policyDataJson.value = json;
                }

                if (policyDataB64) {
                        policyDataB64.value = encodeUtf8Base64(json);
                }
        }

        function renderPolicyControl(key, property, value) {
                var type = getSchemaType(property);
                var enumValues = Array.isArray(property && property.enum) ? property.enum : [];
                var control;

                if (enumValues.length > 0) {
                        control = document.createElement('select');
                        control.name = 'policy_data[' + key + ']';
                        control.className = 'form-control';

                        enumValues.forEach(function(item) {
                                var option = document.createElement('option');
                                option.value = String(item);
                                option.textContent = String(item);
                                control.appendChild(option);
                        });

                        control.value = value === null || value === undefined ? '' : String(value);
                        return markPolicyControl(control, key, type);
                }

                if (type === 'boolean') {
                        var wrapper = document.createElement('label');
                        var hidden = document.createElement('input');
                        var checkbox = document.createElement('input');

                        wrapper.className = 'base3-agent-checkbox-row';
                        hidden.type = 'hidden';
                        hidden.name = 'policy_data[' + key + ']';
                        hidden.value = '0';
                        checkbox.type = 'checkbox';
                        checkbox.name = 'policy_data[' + key + ']';
                        checkbox.value = '1';
                        checkbox.checked = !!value && String(value) !== '0';
                        markPolicyControl(checkbox, key, type);
                        wrapper.appendChild(hidden);
                        wrapper.appendChild(checkbox);
                        wrapper.appendChild(document.createTextNode(' Enabled'));

                        return wrapper;
                }

                if (type === 'object' || type === 'array') {
                        control = document.createElement('textarea');
                        control.name = 'policy_data[' + key + ']';
                        control.className = 'form-control base3-agent-json';
                        control.value = stringifyJson(value === undefined ? getSchemaDefault(property) : value);
                        return markPolicyControl(control, key, type);
                }

                control = document.createElement('input');
                control.type = (type === 'integer' || type === 'number') ? 'number' : 'text';
                control.name = 'policy_data[' + key + ']';
                control.className = 'form-control';
                control.value = value === null || value === undefined ? '' : String(value);
                markPolicyControl(control, key, type);

                if (type === 'number') {
                        control.step = 'any';
                }

                return control;
        }

        function renderPolicyFields(policyId, data) {
                if (!policyFields) {
                        return;
                }

                data = data && typeof data === 'object' && !Array.isArray(data) ? data : {};
                policyFields.innerHTML = '';

                var option = getPolicyOption(policyId);
                var schema = option && option.schema && typeof option.schema === 'object' ? option.schema : {};
                var properties = getSchemaProperties(schema);
                var required = getSchemaRequired(schema);
                var keys = Object.keys(properties);

                if (keys.length === 0) {
                        policyFields.appendChild(createElement('base3-agent-policy-empty', 'This policy does not expose configurable fields.'));
                        syncPolicyDataJson();
                        return;
                }

                keys.forEach(function(key) {
                        var property = properties[key] || {};
                        var row = createElement('base3-agent-policy-field' + (required.indexOf(key) !== -1 ? ' base3-agent-policy-field-required' : ''));
                        var label = createElement('base3-agent-policy-label', key);
                        var controlCell = createElement('base3-agent-policy-control');
                        var value = Object.prototype.hasOwnProperty.call(data, key) ? data[key] : getSchemaDefault(property);
                        var description = String(property.description || '');

                        controlCell.appendChild(renderPolicyControl(key, property, value));

                        if (description !== '') {
                                controlCell.appendChild(createElement('base3-agent-help', description));
                        }

                        row.appendChild(label);
                        row.appendChild(controlCell);
                        policyFields.appendChild(row);
                });

                syncPolicyDataJson();
        }

        root.__base3AgentRenderPolicyFields = function(policyId, data) {
                renderPolicyFields(policyId, data || {});
        };

        root.__base3AgentUpdateValues = function(values) {
                values = values && typeof values === 'object' ? values : {};

                var enabled = root.querySelector('[name="enabled"]');
                var label = root.querySelector('[name="label"]');
                var userPrompt = root.querySelector('[name="user_prompt"]');
                var group = root.querySelector('[name="agent_config_group"]');
                var name = root.querySelector('[name="agent_config_name"]');
                var agentRoot = root.querySelector('[data-base3-agent-config-root]');
                var policyId = String(values.policy_policy || '');
                var policyData = values.policy_data && typeof values.policy_data === 'object' ? values.policy_data : {};

                if (enabled) {
                        enabled.checked = !Object.prototype.hasOwnProperty.call(values, 'enabled') || !!values.enabled;
                }

                if (label) {
                        label.value = values.label || '';
                }

                if (userPrompt) {
                        userPrompt.value = values.user_prompt || '';
                }

                if (group && values.agent_config_group) {
                        group.value = values.agent_config_group;
                }

                if (name && values.agent_config_name) {
                        name.value = values.agent_config_name;
                }

                if (policySelect) {
                        policySelect.value = policyId;
                }

                renderPolicyFields(policyId, policyData);

                if (agentRoot && typeof agentRoot.__base3AgentConfigUpdateValues === 'function') {
                        agentRoot.__base3AgentConfigUpdateValues(values);
                }

                root.__base3AgentPrepareSubmit();
        };

        root.__base3AgentPrepareSubmit = function() {
                syncPolicyDataJson();

                var agentRoot = root.querySelector('[data-base3-agent-config-root]');

                if (agentRoot && typeof agentRoot.__base3AgentConfigPrepareSubmit === 'function') {
                        agentRoot.__base3AgentConfigPrepareSubmit();
                }

                return true;
        };

        if (policySelect) {
                policySelect.addEventListener('change', function() {
                        renderPolicyFields(policySelect.value, {});
                });
        }

        renderPolicyFields(policySelect ? policySelect.value : '', currentPolicyData);
        root.__base3AgentPrepareSubmit();
})();
</script>
