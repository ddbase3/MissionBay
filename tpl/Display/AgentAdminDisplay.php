<?php
        $resolve = $this->_['resolve'];
        $serviceUrl = (string)($this->_['service'] ?? '');
        $settingsGroup = (string)($this->_['settings_group'] ?? 'agent');
        $policyOptions = is_array($this->_['policy_options'] ?? null) ? $this->_['policy_options'] : [];
        $defaultRecord = is_array($this->_['default_record'] ?? null) ? $this->_['default_record'] : [];
        $modularGridCssUrl = (string)$resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
        $modularGridJsUrl = (string)$resolve('plugin/ClientStack/assets/modulargrid/index.js');
        $modularDialogCssUrl = (string)$resolve('plugin/ClientStack/assets/modulardialog/styles/modulardialog.css');
        $modularDialogJsUrl = (string)$resolve('plugin/ClientStack/assets/modulardialog/index.js');
        $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?php echo $e($modularGridCssUrl); ?>" />
<link rel="stylesheet" href="<?php echo $e($modularDialogCssUrl); ?>" />

<style>
        .agent-admin-shell {
                max-width: 1700px;
        }

        .agent-admin-shell h1 {
                margin: 0 0 8px 0;
                font-size: 24px;
                line-height: 1.2;
                font-weight: 600;
        }

        .agent-admin-shell p {
                margin: 0 0 16px 0;
                max-width: 1120px;
                color: #555;
                line-height: 1.45;
        }

        .agent-admin-grid .agent-admin-panel {
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

        .agent-admin-grid .agent-admin-panel--filters {
                flex-wrap: wrap;
                align-items: flex-start;
                overflow-x: visible;
        }

        .agent-admin-grid .agent-admin-panel > * {
                flex: 0 0 auto;
        }

        .agent-admin-main {
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                padding: 4px 0;
        }

        .agent-admin-grid .mg-control-group {
                flex-direction: row;
                align-items: center;
                gap: 6px;
                min-width: auto;
        }

        .agent-admin-grid .mg-label {
                white-space: nowrap;
                color: #666;
                font-size: 12px;
        }

        .agent-admin-grid .mg-inline-buttons {
                flex-wrap: nowrap;
        }

        .agent-admin-grid .mg-input,
        .agent-admin-grid .mg-select,
        .agent-admin-grid .mg-button {
                min-height: 28px;
                font-size: 13px;
        }

        .agent-admin-grid input[type="search"].mg-input {
                width: 300px;
        }

        .agent-admin-grid .mg-table-scroll {
                height: 540px;
                overflow: auto;
                padding-bottom: 4px;
        }

        .agent-admin-grid .mg-table thead th {
                position: sticky;
                top: 0;
                z-index: 12;
                background: #fff;
        }

        .agent-admin-grid .mg-table th,
        .agent-admin-grid .mg-table td {
                padding: 6px 8px;
                font-size: 13px;
                vertical-align: top;
        }

        .agent-admin-top-actions,
        .agent-admin-detail-actions {
                display: inline-flex;
                align-items: center;
                flex-wrap: nowrap;
                gap: 8px;
                flex: 0 0 auto;
        }

        .agent-admin-button {
                appearance: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 28px;
                padding: 4px 10px;
                border: 1px solid #cfcfcf;
                border-radius: 6px;
                background: #fff;
                color: #222;
                font: inherit;
                font-size: 12px;
                line-height: 1.25;
                cursor: pointer;
                white-space: nowrap;
        }

        .agent-admin-button:hover {
                background: #f5f5f5;
        }

        .agent-admin-button-primary {
                background: #2f5d91;
                border-color: #2f5d91;
                color: #fff;
        }

        .agent-admin-button-primary:hover {
                background: #284f7c;
        }

        .agent-admin-button-danger {
                border-color: #c8a2a2;
                color: #8a1f1f;
        }

        .agent-admin-button-danger:hover {
                background: #fff0f0;
        }

        .agent-admin-status,
        .agent-admin-output {
                margin-top: 12px;
                padding: 8px 10px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                font-size: 13px;
                color: #555;
        }

        .agent-admin-status strong,
        .agent-admin-output strong {
                color: #222;
        }

        .agent-admin-startup {
                padding: 16px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                font-size: 13px;
                color: #555;
        }

        .agent-admin-startup-error {
                border-color: #e4b9b9;
                background: #fff8f8;
                color: #8a1f1f;
        }

        .agent-admin-cell-stack {
                display: grid;
                gap: 2px;
                min-width: 0;
        }

        .agent-admin-cell-main {
                font-weight: 600;
                color: #222;
                min-width: 0;
                overflow-wrap: anywhere;
        }

        .agent-admin-cell-sub {
                font-size: 12px;
                color: #666;
                min-width: 0;
                overflow-wrap: anywhere;
        }

        .agent-admin-pill-row {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                align-items: center;
        }

        .agent-admin-pill {
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

        .agent-admin-detail {
                display: grid;
                grid-template-columns: minmax(320px, 1fr) minmax(360px, 1.15fr);
                gap: 14px;
                align-items: start;
                padding: 10px;
                background: #fbfbfb;
        }

        .agent-admin-detail-card {
                min-width: 0;
                padding: 10px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
        }

        .agent-admin-detail-title {
                margin: 0 0 6px 0;
                font-size: 15px;
                font-weight: 600;
                color: #222;
        }

        .agent-admin-detail-row {
                display: grid;
                grid-template-columns: 120px minmax(0, 1fr);
                gap: 6px;
                margin: 0 0 6px 0;
                font-size: 13px;
        }

        .agent-admin-detail-key {
                font-weight: 600;
                color: #444;
        }

        .agent-admin-json,
        .agent-admin-log {
                margin: 0;
                padding: 8px;
                max-height: 360px;
                overflow: auto;
                border: 1px solid #e2e2e2;
                border-radius: 6px;
                background: #fbfbfb;
                white-space: pre-wrap;
                word-break: break-word;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 12px;
                line-height: 1.4;
        }

        .agent-admin-log {
                max-height: 260px;
        }

        .agent-admin-log-details {
                margin-top: 10px;
        }

        .agent-admin-log-details summary {
                padding: 7px 10px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                cursor: pointer;
                font-size: 13px;
                color: #444;
        }


        .agent-admin-dialog-surface {
                width: min(1180px, 100%);
                max-height: min(900px, 100%);
        }

        .agent-admin-runner-dialog-surface {
                width: min(860px, 100%);
                max-height: min(760px, 100%);
        }

        .agent-admin-editor-content,
        .agent-admin-runner-content {
                display: grid;
                gap: 12px;
                min-width: 0;
        }

        #agent-admin-editor-content[hidden],
        #agent-admin-runner-content[hidden] {
                display: none !important;
        }

        .agent-admin-dialog-surface .md-shell-body,
        .agent-admin-runner-dialog-surface .md-shell-body {
                min-height: 0;
                overflow: auto;
        }

        .agent-admin-form-header {
                display: grid;
                grid-template-columns: minmax(260px, 520px);
                gap: 12px;
                margin: 0 0 14px;
                padding: 12px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fbfbfb;
        }

        .agent-admin-label {
                display: block;
                margin: 0 0 4px 0;
                font-size: 12px;
                font-weight: 600;
                color: #333;
        }

        .agent-admin-input {
                width: 100%;
                max-width: 100%;
                min-height: 32px;
                padding: 5px 7px;
                border: 1px solid #cfcfcf;
                border-radius: 6px;
                background: #fff;
                color: #222;
                font: inherit;
                font-size: 13px;
                box-sizing: border-box;
        }

        .agent-admin-help {
                margin: 5px 0 0;
                color: #666;
                font-size: 12px;
        }

        .agent-admin-dialog-status {
                min-height: 18px;
                font-size: 12px;
                color: #666;
        }

        .agent-admin-dialog-status-error {
                color: #8a1f1f;
        }

        .agent-admin-dialog-status-ok {
                color: #276028;
        }

        .agent-admin-run-prompt {
                width: 100%;
                min-height: 190px;
                padding: 8px;
                border: 1px solid #cfcfcf;
                border-radius: 6px;
                background: #fff;
                color: #222;
                font: inherit;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 13px;
                line-height: 1.4;
                resize: vertical;
                box-sizing: border-box;
        }

        .agent-admin-run-result {
                margin: 12px 0 0;
                padding: 10px;
                min-height: 120px;
                max-height: 360px;
                overflow: auto;
                border: 1px solid #e2e2e2;
                border-radius: 6px;
                background: #fbfbfb;
                white-space: pre-wrap;
                word-break: break-word;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 12px;
                line-height: 1.4;
        }

        .agent-admin-run-details {
                margin: 12px 0 0;
        }

        .agent-admin-run-details summary {
                padding: 7px 10px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                cursor: pointer;
                font-size: 13px;
                color: #444;
        }

        .agent-admin-run-details .agent-admin-run-result {
                min-height: 80px;
                max-height: 280px;
        }

        @media (max-width: 980px) {
                .agent-admin-detail,
                .agent-admin-form-header {
                        grid-template-columns: 1fr;
                }
        }
</style>

<div class="agent-admin-shell">
        <h1>Agent Admin</h1>
        <p>
                Manage configured MissionBay agents that can be executed by worker timing policies. The editor uses the agent configuration form and stores records in SettingsStore group <code><?php echo $e($settingsGroup); ?></code>.
        </p>


        <div class="agent-admin-grid">
                <div id="agent-admin-grid" class="agent-admin-grid-shell">
                        <div class="agent-admin-startup">Loading Agent Admin display...</div>
                </div>
                <div id="agent-admin-output" class="agent-admin-status"><strong>Last action:</strong> Waiting for initialization.</div>
                <details class="agent-admin-log-details">
                        <summary>Debug log</summary>
                        <pre id="agent-admin-log" class="agent-admin-log">Status log will appear here.</pre>
                </details>
        </div>
</div>

<div id="agent-admin-editor-content" class="agent-admin-editor-content" hidden>
        <form id="base3_agent_admin_editor_form">
                <input type="hidden" name="mode" value="save" />
                <input type="hidden" name="old_id" />
                <div class="agent-admin-form-header">
                        <div>
                                <label class="agent-admin-label">Agent ID</label>
                                <input type="text" name="agent_id" class="agent-admin-input" />
                                <p class="agent-admin-help">SettingsStore name inside the fixed group <code><?php echo $e($settingsGroup); ?></code>. Existing records keep their ID while editing.</p>
                        </div>
                </div>
<?php include DIR_PLUGIN . 'MissionBay/tpl/Content/AgentFormFields.php'; ?>
        </form>
</div>

<div id="agent-admin-runner-content" class="agent-admin-runner-content" hidden>
        <input type="hidden" id="agent-admin-runner-id" />
        <label class="agent-admin-label" for="agent-admin-runner-prompt">User prompt</label>
        <textarea id="agent-admin-runner-prompt" class="agent-admin-run-prompt"></textarea>
        <p class="agent-admin-help">The configured user prompt is loaded here and can be adjusted for this manual run.</p>
        <pre id="agent-admin-runner-result" class="agent-admin-run-result">Result will appear here.</pre>
        <details class="agent-admin-run-details">
                <summary>Raw flow output</summary>
                <pre id="agent-admin-runner-output" class="agent-admin-run-result">Raw output will appear here.</pre>
        </details>
</div>

<script>
(function() {
        const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const MODULARGRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const MODULAR_DIALOG_URL = <?php echo json_encode($modularDialogJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const DEFAULT_RECORD = <?php echo json_encode($defaultRecord, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const POLICY_OPTIONS = <?php echo json_encode(array_values($policyOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const SETTINGS_GROUP = <?php echo json_encode($settingsGroup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const GRID_SELECTOR = '#agent-admin-grid';
        const LOG_SELECTOR = '#agent-admin-log';
        const OUTPUT_SELECTOR = '#agent-admin-output';
        const BATCH_SIZE = 50;
        const SORT_TYPES = {
                agent_id: 'string',
                label: 'string',
                enabled_label: 'string',
                policy_label: 'string',
                llm: 'string',
                component_count: 'int',
                user_prompt_preview: 'string'
        };

        let grid = null;
        let editorDialog = null;
        let runnerDialog = null;
        let currentEditorAgentId = '';

        const ENABLED_FILTER_OPTIONS = [
                { value: '', label: 'All states' },
                { value: '1', label: 'Enabled' },
                { value: '0', label: 'Disabled' }
        ];

        const POLICY_FILTER_OPTIONS = [{ value: '', label: 'All policies' }].concat(
                (POLICY_OPTIONS || []).map((option) => ({
                        value: String(option.id || ''),
                        label: String(option.label || option.id || '')
                })).filter((option) => option.value !== '')
        );

        const layout = {
                type: 'stack',
                className: 'mg-layout-root',
                children: [
                        {
                                type: 'zone',
                                key: 'topLine1',
                                className: 'agent-admin-panel agent-admin-panel--main'
                        },
                        {
                                type: 'zone',
                                key: 'topLine2',
                                className: 'agent-admin-panel agent-admin-panel--filters'
                        },
                        {
                                type: 'view',
                                key: 'main',
                                className: 'agent-admin-main'
                        },
                        {
                                type: 'zone',
                                key: 'statusZone',
                                className: 'agent-admin-panel'
                        }
                ]
        };

        function log(label, value = undefined) {
                const message = value === undefined ? String(label) : String(label) + ' ' + stringifyJson(value);
                const logElement = document.querySelector(LOG_SELECTOR);

                console.log('[AgentAdmin]', label, value === undefined ? '' : value);

                if (logElement) {
                        logElement.textContent = (logElement.textContent === 'Status log will appear here.' ? '' : logElement.textContent + '\n') + message;
                }
        }

        function setLog(message) {
                const output = document.querySelector(OUTPUT_SELECTOR);

                if (!output) {
                        return;
                }

                output.innerHTML = '';
                const label = document.createElement('strong');
                label.textContent = 'Last action:';
                output.appendChild(label);
                output.appendChild(document.createTextNode(' ' + message));
        }

        function stringifyJson(value) {
                try {
                        return JSON.stringify(value, null, 2);
                } catch (error) {
                        return String(value);
                }
        }

        async function copyText(text) {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        await navigator.clipboard.writeText(String(text || ''));
                        return;
                }

                const textarea = document.createElement('textarea');
                textarea.value = String(text || '');
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
        }

        function createElement(className = '', text = '') {
                const element = document.createElement('div');

                if (className) {
                        element.className = className;
                }

                if (text !== '') {
                        element.textContent = text;
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

        function getText(value, placeholder = '-') {
                if (value === null || value === undefined || value === '') {
                        return placeholder;
                }

                return String(value);
        }

        async function postJson(payload) {
                log('POST JSON start', payload);

                /*
                 * CRITICAL: Do not change this request contract.
                 * ModularGrid and the BASE3/ILIAS endpoint currently rely on this exact fetch setup:
                 * POST + Content-Type application/json + JSON.stringify(payload).
                 */
                const response = await fetch(ENDPOINT_URL, {
                        method: 'POST',
                        headers: {
                                'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                });

                log('POST JSON response status', response.status);

                if (!response.ok) {
                        throw new Error('Request failed with status ' + response.status);
                }

                const json = await response.json();
                log('POST JSON response body', json);

                return json;
        }

        async function refreshGrid() {
                if (!grid) {
                        setLog('Grid is not initialized; refresh skipped.');
                        return;
                }

                const commands = ['reloadData', 'reload', 'refreshData', 'refresh'];

                if (typeof grid.execute === 'function') {
                        for (const commandName of commands) {
                                try {
                                        const result = grid.execute(commandName);

                                        if (result && typeof result.then === 'function') {
                                                await result;
                                        }

                                        return;
                                } catch (error) {}
                        }
                }

                for (const methodName of commands) {
                        if (typeof grid[methodName] === 'function') {
                                const result = grid[methodName]();

                                if (result && typeof result.then === 'function') {
                                        await result;
                                }

                                return;
                        }
                }

                setLog('Grid refresh is not available. Please refresh the page manually.');
        }

        function createPill(text) {
                const pill = document.createElement('span');
                pill.className = 'agent-admin-pill';
                pill.textContent = getText(text);

                return pill;
        }

        function renderPills(value) {
                const wrapper = createElement('agent-admin-pill-row');
                const items = String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

                if (items.length === 0) {
                        wrapper.appendChild(createPill('-'));
                        return wrapper;
                }

                items.forEach((item) => wrapper.appendChild(createPill(item)));

                return wrapper;
        }

        function renderAgent(value, row) {
                const wrapper = createElement('agent-admin-cell-stack');
                const main = createElement('agent-admin-cell-main', getText(row.label || row.agent_id));
                const sub = createElement('agent-admin-cell-sub', getText(row.agent_id) + ' · ' + getText(row.enabled_label));

                wrapper.appendChild(main);
                wrapper.appendChild(sub);

                return wrapper;
        }

        function renderPolicy(value, row) {
                const wrapper = createElement('agent-admin-cell-stack');
                const main = createElement('agent-admin-cell-main', getText(row.policy_label || row.policy));
                const sub = createElement('agent-admin-cell-sub', getText(row.policy_data_text));

                wrapper.appendChild(main);
                wrapper.appendChild(sub);

                return wrapper;
        }

        function renderComponentCount(value, row) {
                return createElement('agent-admin-cell-main', getText(row.component_count, '0'));
        }

        function buildFilterPayload(filters) {
                const result = {};

                Object.entries(filters || {}).forEach(([key, value]) => {
                        if (value === '' || value === null || value === undefined) {
                                return;
                        }

                        result[key] = value;
                });

                return result;
        }

        function getAgentIdFromRow(row) {
                if (!row || typeof row !== 'object') {
                        return '';
                }

                return String(row.agent_id || row.id || '').trim();
        }

        async function loadRemoteRecord(row) {
                const id = getAgentIdFromRow(row);

                if (!id) {
                        throw new Error('Missing agent id for detail request.');
                }

                const response = await postJson({
                        mode: 'record',
                        id
                });

                if (!response || !response.ok || !response.record) {
                        throw new Error(response && response.error ? response.error : 'No record returned for ' + id);
                }

                return response.record;
        }

        function createDetailLoadingPlaceholder(row) {
                return createElement('agent-admin-startup', 'Loading record for ' + getText(getAgentIdFromRow(row)) + '...');
        }

        function createDetailErrorPlaceholder(row, error) {
                return createElement('agent-admin-startup agent-admin-startup-error', 'Failed to load record for ' + getText(getAgentIdFromRow(row)) + ': ' + getText(error && error.message ? error.message : error));
        }

        function createDetailRow(key, value) {
                const row = createElement('agent-admin-detail-row');
                row.appendChild(createElement('agent-admin-detail-key', key));
                row.appendChild(createElement('', getText(value)));

                return row;
        }

        function renderAgentDetail(context) {
                const record = context && context.payload ? context.payload : null;

                if (!record || typeof record !== 'object') {
                        return document.createTextNode(getText(record));
                }

                const wrapper = createElement('agent-admin-detail');
                const left = createElement('agent-admin-detail-card');
                const right = createElement('agent-admin-detail-card');
                const pre = document.createElement('pre');

                left.appendChild(createElement('agent-admin-detail-title', getText(record.label || record.agent_id || record.id)));
                left.appendChild(createDetailRow('ID', record.agent_id || record.id));
                left.appendChild(createDetailRow('Label', record.label));
                left.appendChild(createDetailRow('Enabled', record.enabled ? 'yes' : 'no'));
                left.appendChild(createDetailRow('Policy', record.policy_label || record.policy));
                left.appendChild(createDetailRow('Policy data', record.policy_data_text));
                left.appendChild(createDetailRow('LLM', record.llm));
                left.appendChild(createDetailRow('Components', record.component_count));
                left.appendChild(createDetailRow('User prompt', record.user_prompt_preview));

                right.appendChild(createElement('agent-admin-detail-title', 'Settings JSON'));
                pre.className = 'agent-admin-json';
                pre.textContent = record.settings_json || stringifyJson(record);
                right.appendChild(pre);

                wrapper.appendChild(left);
                wrapper.appendChild(right);

                return wrapper;
        }


        function getEditorElements() {
                const content = document.getElementById('agent-admin-editor-content');

                return {
                        content,
                        form: document.getElementById('base3_agent_admin_editor_form')
                };
        }

        function getRunnerElements() {
                return {
                        content: document.getElementById('agent-admin-runner-content'),
                        id: document.getElementById('agent-admin-runner-id'),
                        prompt: document.getElementById('agent-admin-runner-prompt'),
                        result: document.getElementById('agent-admin-runner-result'),
                        output: document.getElementById('agent-admin-runner-output')
                };
        }


        function setEditorStatus(message, type = '') {
                if (!editorDialog || typeof editorDialog.execute !== 'function') {
                        return;
                }

                editorDialog.execute('setStatus', {
                        message: message || '',
                        type
                });
        }

        function setFormValue(form, name, value) {
                const field = form.elements.namedItem(name);

                if (!field) {
                        return;
                }

                field.value = value === null || value === undefined ? '' : String(value);
        }

        function getFormFieldValue(form, name) {
                const field = form.elements.namedItem(name);

                if (!field) {
                        return '';
                }

                return String(field.value || '').trim();
        }

        function normalizeTechnicalKey(value) {
                return String(value || '').toLowerCase().trim().replace(/[^a-z0-9._-]+/g, '');
        }


        function encodeUtf8Base64(value) {
                value = String(value === null || value === undefined ? '' : value);

                if (window.TextEncoder) {
                        const bytes = new TextEncoder().encode(value);
                        let binary = '';
                        const chunkSize = 0x8000;

                        for (let offset = 0; offset < bytes.length; offset += chunkSize) {
                                const chunk = bytes.subarray(offset, offset + chunkSize);
                                binary += String.fromCharCode.apply(null, Array.prototype.slice.call(chunk));
                        }

                        return btoa(binary);
                }

                return btoa(unescape(encodeURIComponent(value)));
        }

        function getPolicyOptionById(policyId) {
                policyId = String(policyId || '');

                for (let i = 0; i < POLICY_OPTIONS.length; i++) {
                        if (String(POLICY_OPTIONS[i].id || '') === policyId) {
                                return POLICY_OPTIONS[i];
                        }
                }

                return null;
        }

        function getPolicySchemaProperties(schema) {
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

        function getPolicySchemaRequired(schema) {
                if (Array.isArray(schema && schema.required)) {
                        return schema.required.map(String);
                }

                if (schema && schema.data && Array.isArray(schema.data.required)) {
                        return schema.data.required.map(String);
                }

                return [];
        }

        function getPolicySchemaType(schema) {
                let type = schema && schema.type !== undefined ? schema.type : 'string';

                if (Array.isArray(type)) {
                        type = type.find((item) => item !== 'null') || 'string';
                }

                return String(type || 'string');
        }

        function getPolicySchemaDefault(schema) {
                if (schema && Object.prototype.hasOwnProperty.call(schema, 'default')) {
                        return schema.default;
                }

                const type = getPolicySchemaType(schema);

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

        function markAdminPolicyControl(control, key, type) {
                control.setAttribute('data-base3-agent-policy-key', key);
                control.setAttribute('data-base3-agent-policy-type', type);
                control.addEventListener('input', () => syncAdminPolicyData(getEditorElements().form));
                control.addEventListener('change', () => syncAdminPolicyData(getEditorElements().form));

                return control;
        }

        function createAdminPolicyControl(key, property, value) {
                const type = getPolicySchemaType(property);
                const enumValues = Array.isArray(property && property.enum) ? property.enum : [];
                let control;

                if (enumValues.length > 0) {
                        control = document.createElement('select');
                        control.name = 'policy_data[' + key + ']';
                        control.className = 'form-control';

                        enumValues.forEach((item) => {
                                const option = document.createElement('option');
                                option.value = String(item);
                                option.textContent = String(item);
                                control.appendChild(option);
                        });

                        control.value = value === null || value === undefined ? '' : String(value);

                        return markAdminPolicyControl(control, key, type);
                }

                if (type === 'boolean') {
                        const label = document.createElement('label');
                        const hidden = document.createElement('input');
                        const checkbox = document.createElement('input');

                        label.className = 'base3-agent-checkbox-row';
                        hidden.type = 'hidden';
                        hidden.name = 'policy_data[' + key + ']';
                        hidden.value = '0';
                        checkbox.type = 'checkbox';
                        checkbox.name = 'policy_data[' + key + ']';
                        checkbox.value = '1';
                        checkbox.checked = !!value && String(value) !== '0';
                        markAdminPolicyControl(checkbox, key, type);
                        label.appendChild(hidden);
                        label.appendChild(checkbox);
                        label.appendChild(document.createTextNode(' Enabled'));

                        return label;
                }

                if (type === 'object' || type === 'array') {
                        control = document.createElement('textarea');
                        control.name = 'policy_data[' + key + ']';
                        control.className = 'form-control base3-agent-json';
                        control.value = stringifyJson(value === undefined ? getPolicySchemaDefault(property) : value);

                        return markAdminPolicyControl(control, key, type);
                }

                control = document.createElement('input');
                control.type = (type === 'integer' || type === 'number') ? 'number' : 'text';
                control.name = 'policy_data[' + key + ']';
                control.className = 'form-control';
                control.value = value === null || value === undefined ? '' : String(value);

                if (type === 'number') {
                        control.step = 'any';
                }

                return markAdminPolicyControl(control, key, type);
        }

        function renderAdminPolicyFields(form, policyId, data = {}) {
                const root = getAgentFieldsRoot(form);
                const policyFields = root ? root.querySelector('[data-base3-agent-policy-fields]') : null;

                if (!policyFields) {
                        return;
                }

                policyId = String(policyId || '');
                data = data && typeof data === 'object' && !Array.isArray(data) ? data : {};
                policyFields.replaceChildren();

                const option = getPolicyOptionById(policyId);
                const schema = option && option.schema && typeof option.schema === 'object' ? option.schema : {};
                const properties = getPolicySchemaProperties(schema);
                const required = getPolicySchemaRequired(schema);
                const keys = Object.keys(properties);

                if (keys.length === 0) {
                        policyFields.appendChild(createElement('base3-agent-policy-empty', policyId ? 'This policy does not expose configurable fields.' : 'Select a timing policy.'));
                        syncAdminPolicyData(form);
                        return;
                }

                keys.forEach((key) => {
                        const property = properties[key] || {};
                        const row = createElement('base3-agent-policy-field' + (required.indexOf(key) !== -1 ? ' base3-agent-policy-field-required' : ''));
                        const label = createElement('base3-agent-policy-label', key);
                        const controlCell = createElement('base3-agent-policy-control');
                        const value = Object.prototype.hasOwnProperty.call(data, key) ? data[key] : getPolicySchemaDefault(property);
                        const description = String(property.description || '');

                        controlCell.appendChild(createAdminPolicyControl(key, property, value));

                        if (description !== '') {
                                controlCell.appendChild(createElement('base3-agent-help', description));
                        }

                        row.appendChild(label);
                        row.appendChild(controlCell);
                        policyFields.appendChild(row);
                });

                syncAdminPolicyData(form);
        }

        function readAdminPolicyControlValue(control) {
                const type = control.getAttribute('data-base3-agent-policy-type') || 'string';

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
                        } catch (error) {
                                return type === 'array' ? [] : {};
                        }
                }

                return control.value;
        }

        function syncAdminPolicyData(form) {
                const root = getAgentFieldsRoot(form);

                if (!root) {
                        return {};
                }

                const data = {};
                root.querySelectorAll('[data-base3-agent-policy-key]').forEach((control) => {
                        const key = control.getAttribute('data-base3-agent-policy-key') || '';

                        if (key === '') {
                                return;
                        }

                        data[key] = readAdminPolicyControlValue(control);
                });

                const json = stringifyJson(data) || '{}';
                const jsonField = root.querySelector('[data-base3-agent-policy-data-json]');
                const b64Field = root.querySelector('[data-base3-agent-policy-data-b64]');

                if (jsonField) {
                        jsonField.value = json;
                }

                if (b64Field) {
                        b64Field.value = encodeUtf8Base64(json);
                }

                return data;
        }

        function getAgentFieldsRoot(form) {
                return form ? form.querySelector('[data-base3-agent-fields]') : null;
        }


        function setCheckboxField(form, name, checked) {
                const field = form.elements.namedItem(name);

                if (field && typeof field.checked === 'boolean') {
                        field.checked = !!checked;
                }
        }

        function updateAgentConfigFallback(form, values) {
                const agentRoot = form.querySelector('[data-base3-agent-config-root]');

                if (agentRoot && typeof agentRoot.__base3AgentConfigUpdateValues === 'function') {
                        try {
                                agentRoot.__base3AgentConfigUpdateValues(values);
                                log('agent config form helper updated values', { llm: values.llm || '', components: Array.isArray(values.agent_components) ? values.agent_components.length : 0 });
                        } catch (error) {
                                log('agent config form helper failed', error && error.message ? error.message : String(error));
                        }
                }

                setFormValue(form, 'llm', values.llm || '');
                setFormValue(form, 'system_prompt', values.system_prompt || '');
                setFormValue(form, 'agent_flow', values.agent_flow_json || '{}');

                if (agentRoot && typeof agentRoot.__base3AgentConfigPrepareSubmit === 'function') {
                        try {
                                agentRoot.__base3AgentConfigPrepareSubmit();
                        } catch (error) {
                                log('agent config prepare helper failed', error && error.message ? error.message : String(error));
                        }
                }
        }

        function updateAgentFormFallback(form, values) {
                const policyId = String(values.policy_policy || values.policy || '').trim();
                const policyData = values.policy_data && typeof values.policy_data === 'object' && !Array.isArray(values.policy_data) ? values.policy_data : {};
                const policySelect = form.querySelector('[data-base3-agent-policy-select]');
                const groupField = form.elements.namedItem('agent_config_group');
                const nameField = form.elements.namedItem('agent_config_name');

                setCheckboxField(form, 'enabled', !Object.prototype.hasOwnProperty.call(values, 'enabled') || !!values.enabled);
                setFormValue(form, 'label', values.label || '');
                setFormValue(form, 'user_prompt', values.user_prompt || '');

                if (groupField) {
                        groupField.value = SETTINGS_GROUP;
                }

                if (nameField) {
                        nameField.value = values.agent_config_name || values.agent_id || values.id || '';
                }

                if (policySelect) {
                        policySelect.value = policyId;
                        renderAdminPolicyFields(form, policyId, policyData);
                }
                else {
                        syncAdminPolicyData(form);
                }

                updateAgentConfigFallback(form, values);
        }


        function buildEditorButtons(isExisting = false) {
                const buttons = [];

                if (isExisting) {
                        buttons.push({
                                key: 'delete-agent-current',
                                label: 'Delete',
                                danger: true,
                                async action() {
                                        await deleteCurrentAgentFromEditor();
                                }
                        });
                }

                buttons.push(
                        {
                                key: 'copy-payload',
                                label: 'Copy payload',
                                async action() {
                                        await copyEditorPayload();
                                }
                        },
                        {
                                key: 'cancel',
                                label: 'Cancel',
                                action: 'close'
                        },
                        {
                                key: 'save-agent',
                                label: 'Save',
                                primary: true,
                                busyLabel: 'Saving...',
                                async action() {
                                        await saveEditorPayload();
                                }
                        }
                );

                return buttons;
        }

        function initEditorDialog(modularDialogModule) {
                if (editorDialog) {
                        return editorDialog;
                }

                if (!modularDialogModule || typeof modularDialogModule.createStandardDialog !== 'function') {
                        throw new Error('ModularDialog createStandardDialog export not found.');
                }

                const content = document.getElementById('agent-admin-editor-content');

                if (!content) {
                        throw new Error('Agent editor content not found.');
                }

                content.hidden = false;

                editorDialog = modularDialogModule.createStandardDialog({
                        id: 'agent-admin-editor-dialog',
                        className: 'agent-admin-editor-dialog',
                        surfaceClassName: 'agent-admin-dialog-surface',
                        size: 'large',
                        title: 'Agent editor',
                        content,
                        status: '',
                        closeButtonPlugin: {
                                label: 'Close'
                        },
                        statusPlugin: {
                                renderEmpty: true
                        },
                        buttons: buildEditorButtons(false)
                });

                editorDialog.on('afterClose', () => {
                        currentEditorAgentId = '';
                        setEditorStatus('', '');
                });

                editorDialog.init();

                return editorDialog;
        }

        function updateEditorForm(record) {
                const elements = getEditorElements();

                if (!editorDialog || !elements.content || !elements.form) {
                        setLog('Agent editor elements not found.');
                        return;
                }

                const form = elements.form;
                const fieldsRoot = getAgentFieldsRoot(form);
                record = record && typeof record === 'object' ? record : {};

                form.reset();

                const oldId = String(record.old_id || record.agent_id || record.id || '').trim();
                const currentId = String(record.agent_id || record.id || '').trim();
                currentEditorAgentId = oldId;
                setFormValue(form, 'old_id', oldId);
                setFormValue(form, 'agent_id', currentId);

                const agentIdField = form.elements.namedItem('agent_id');
                if (agentIdField) {
                        agentIdField.readOnly = oldId !== '';
                }

                const values = Object.assign({}, DEFAULT_RECORD, record, {
                        agent_config_group: SETTINGS_GROUP,
                        agent_config_name: currentId
                });

                if (fieldsRoot && typeof fieldsRoot.__base3AgentUpdateValues === 'function') {
                        try {
                                fieldsRoot.__base3AgentUpdateValues(values);
                                log('agent form helper updated values', { id: currentId, policy: values.policy_policy || values.policy || '' });
                        } catch (error) {
                                log('agent form helper failed', error && error.message ? error.message : String(error));
                        }
                }
                else {
                        log('agent form helper missing; using admin fallback hydration');
                }

                updateAgentFormFallback(form, values);

                editorDialog.execute('setTitle', oldId === '' ? 'Add agent' : 'Edit agent');
                editorDialog.execute('setButtons', buildEditorButtons(oldId !== ''));
                setEditorStatus(oldId === '' ? 'New agent. Enter an ID, then save.' : 'Editor opened. Save is enabled.', 'ok');
                editorDialog.open({ source: 'agentEditor', agentId: currentId });
                setLog('Opened editor for ' + getText(currentId, 'new agent'));
        }

        function openNewEditor() {
                const record = Object.assign({}, DEFAULT_RECORD, {
                        id: '',
                        agent_id: '',
                        old_id: '',
                        label: '',
                        agent_config_name: ''
                });

                updateEditorForm(record);
        }

        function closeEditor() {
                if (!editorDialog) {
                        return;
                }

                editorDialog.close({ source: 'agentEditor' });
                setLog('Closed editor.');
        }

        async function openEditorFromRow(row) {
                try {
                        setLog('Loading record for editor: ' + getText(getAgentIdFromRow(row)));
                        const record = await loadRemoteRecord(row);
                        updateEditorForm(record);
                } catch (error) {
                        setLog('Could not open editor: ' + getText(error && error.message ? error.message : error));
                }
        }


        function setRunnerStatus(message, type = '') {
                if (!runnerDialog || typeof runnerDialog.execute !== 'function') {
                        return;
                }

                runnerDialog.execute('setStatus', {
                        message: message || '',
                        type
                });
        }

        function initRunnerDialog(modularDialogModule) {
                if (runnerDialog) {
                        return runnerDialog;
                }

                if (!modularDialogModule || typeof modularDialogModule.createStandardDialog !== 'function') {
                        throw new Error('ModularDialog createStandardDialog export not found.');
                }

                const content = document.getElementById('agent-admin-runner-content');

                if (!content) {
                        throw new Error('Agent runner content not found.');
                }

                content.hidden = false;

                runnerDialog = modularDialogModule.createStandardDialog({
                        id: 'agent-admin-runner-dialog',
                        className: 'agent-admin-runner-dialog',
                        surfaceClassName: 'agent-admin-runner-dialog-surface',
                        size: 'large',
                        title: 'Run agent',
                        content,
                        status: 'Ready.',
                        closeButtonPlugin: {
                                label: 'Close'
                        },
                        statusPlugin: {
                                renderEmpty: true
                        },
                        buttons: [
                                {
                                        key: 'close-runner',
                                        label: 'Close',
                                        action: 'close'
                                },
                                {
                                        key: 'run-agent',
                                        label: 'Run agent',
                                        primary: true,
                                        busyLabel: 'Running...',
                                        async action() {
                                                await runAgentFromDialog();
                                        }
                                }
                        ]
                });

                runnerDialog.init();

                return runnerDialog;
        }

        function updateRunnerForm(record) {
                const elements = getRunnerElements();
                record = record && typeof record === 'object' ? record : {};

                if (!runnerDialog || !elements.content || !elements.id || !elements.prompt || !elements.result) {
                        setLog('Agent runner elements not found.');
                        return;
                }

                const id = String(record.agent_id || record.id || '').trim();
                const label = String(record.label || id || 'agent');

                elements.id.value = id;
                elements.prompt.value = String(record.user_prompt || '');
                elements.result.textContent = 'Result will appear here.';

                if (elements.output) {
                        elements.output.textContent = 'Raw output will appear here.';
                }

                runnerDialog.execute('setTitle', 'Run agent: ' + getText(label));
                setRunnerStatus('Ready.', 'ok');
                runnerDialog.open({ source: 'agentRunner', agentId: id });
                setLog('Opened runner for ' + getText(id));
        }

        async function openRunnerFromRow(row) {
                try {
                        setLog('Loading record for runner: ' + getText(getAgentIdFromRow(row)));
                        const record = await loadRemoteRecord(row);
                        updateRunnerForm(record);
                } catch (error) {
                        setLog('Could not open runner: ' + getText(error && error.message ? error.message : error));
                }
        }

        function closeRunner() {
                if (!runnerDialog) {
                        return;
                }

                runnerDialog.close({ source: 'agentRunner' });
                setLog('Closed runner.');
        }

        async function runAgentFromDialog() {
                const elements = getRunnerElements();

                if (!elements.id || !elements.prompt || !elements.result) {
                        setLog('Run failed: agent runner elements not found.');
                        return;
                }

                const id = String(elements.id.value || '').trim();

                if (!id) {
                        setRunnerStatus('Agent ID is missing.', 'error');
                        return;
                }

                elements.result.textContent = 'Running...';
                setRunnerStatus('Running agent...', '');
                setLog('Running agent ' + id);

                try {
                        const response = await postJson({
                                mode: 'run',
                                id,
                                user_prompt: elements.prompt.value || ''
                        });

                        if (!response || !response.ok) {
                                throw new Error(response && response.error ? response.error : 'Run failed.');
                        }

                        renderRunnerResponse(elements, response);
                        setRunnerStatus('Agent run finished.', 'ok');
                        setLog('Agent run finished for ' + id + '.');
                } catch (error) {
                        const message = getText(error && error.message ? error.message : error);
                        elements.result.textContent = message;

                        if (elements.output) {
                                elements.output.textContent = '';
                        }

                        setRunnerStatus(message, 'error');
                        setLog('Run failed: ' + message);
                }
        }

        function renderRunnerResponse(elements, response) {
                const messageText = String(response.message_text || '');
                const resultText = String(response.result_text || '');

                if (messageText !== '') {
                        elements.result.textContent = messageText;
                }
                else if (resultText !== '') {
                        elements.result.textContent = resultText;
                }
                else {
                        elements.result.textContent = stringifyJson(response.output || response);
                }

                if (elements.output) {
                        elements.output.textContent = stringifyJson({
                                assistant_node_id: response.assistant_node_id || '',
                                message: response.message || null,
                                flow_error: response.flow_error || '',
                                warnings: response.warnings || [],
                                output: response.output || {}
                        });
                }
        }

        function validateEditorForm(form) {
                const id = normalizeTechnicalKey(getFormFieldValue(form, 'agent_id'));

                if (!id) {
                        throw new Error('Agent ID is required.');
                }

                return id;
        }

        function prepareConfigFormData(form) {
                const id = validateEditorForm(form);
                const nameField = form.elements.namedItem('agent_config_name');
                const groupField = form.elements.namedItem('agent_config_group');
                const modeField = form.elements.namedItem('mode');
                const fieldsRoot = getAgentFieldsRoot(form);

                if (fieldsRoot && typeof fieldsRoot.__base3AgentPrepareSubmit === 'function') {
                        try {
                                fieldsRoot.__base3AgentPrepareSubmit();
                        } catch (error) {
                                log('agent prepare helper failed', error && error.message ? error.message : String(error));
                        }
                }

                syncAdminPolicyData(form);

                const agentRoot = form.querySelector('[data-base3-agent-config-root]');
                if (agentRoot && typeof agentRoot.__base3AgentConfigPrepareSubmit === 'function') {
                        try {
                                agentRoot.__base3AgentConfigPrepareSubmit();
                        } catch (error) {
                                log('agent config prepare helper failed', error && error.message ? error.message : String(error));
                        }
                }

                const agentFlow = form.elements.namedItem('agent_flow');
                const agentFlowB64 = form.querySelector('[data-base3-agent-config-agent-flow-b64]');
                if (agentFlow && agentFlowB64) {
                        agentFlowB64.value = encodeUtf8Base64(agentFlow.value || '{}');
                }

                if (nameField) {
                        nameField.value = id;
                }

                if (groupField) {
                        groupField.value = SETTINGS_GROUP;
                }

                if (modeField) {
                        modeField.value = 'save';
                }

                const formData = new FormData(form);
                formData.set('mode', 'save');
                formData.set('agent_id', id);
                formData.set('agent_config_group', SETTINGS_GROUP);
                formData.set('agent_config_name', id);

                return formData;
        }

        function buildEditorPayloadPreview() {
                const elements = getEditorElements();
                const form = elements.form;

                if (!form) {
                        throw new Error('Agent editor form not found.');
                }

                const id = validateEditorForm(form);
                const formData = prepareConfigFormData(form);
                const payload = {};

                formData.forEach((value, key) => {
                        if (payload[key] === undefined) {
                                payload[key] = value;
                                return;
                        }

                        if (!Array.isArray(payload[key])) {
                                payload[key] = [payload[key]];
                        }

                        payload[key].push(value);
                });

                payload.agent_id = id;
                payload.old_id = getFormFieldValue(form, 'old_id');

                return payload;
        }

        async function copyEditorPayload() {
                try {
                        const payload = buildEditorPayloadPreview();
                        await copyText(stringifyJson(payload));
                        setEditorStatus('Payload copied.', 'ok');
                        setLog('Copied editor payload for ' + getText(payload.agent_id, 'new agent'));
                } catch (error) {
                        setEditorStatus(error && error.message ? error.message : String(error), 'error');
                }
        }

        async function saveEditorPayload() {
                try {
                        const elements = getEditorElements();

                        if (!elements.form) {
                                throw new Error('Agent editor form not found.');
                        }

                        const id = validateEditorForm(elements.form);
                        const oldId = getFormFieldValue(elements.form, 'old_id');

                        if (oldId !== '' && oldId !== id) {
                                throw new Error('Renaming agents is not supported in this editor. Duplicate/create a new record and delete the old one if needed.');
                        }

                        const formData = prepareConfigFormData(elements.form);

                        setEditorStatus('Saving agent...', '');
                        setLog('Saving agent ' + id);

                        const response = await fetch(ENDPOINT_URL, {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin',
                                headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                }
                        });

                        if (!response.ok) {
                                throw new Error('Save failed with status ' + response.status);
                        }

                        const json = await response.json();
                        log('Save response body', json);

                        if (!json || !json.ok) {
                                throw new Error(json && json.error ? json.error : 'Save failed.');
                        }

                        setEditorStatus('Agent saved. Updating grid...', 'ok');
                        closeEditor();
                        await refreshGrid();
                        setLog('Saved agent ' + id + '.');
                } catch (error) {
                        setEditorStatus(error && error.message ? error.message : String(error), 'error');
                        setLog('Save failed: ' + getText(error && error.message ? error.message : error));
                }
        }

        async function deleteAgentById(id) {
                id = String(id || '').trim();

                if (!id) {
                        throw new Error('Missing agent id.');
                }

                const response = await postJson({
                        mode: 'delete',
                        id
                });

                if (!response || !response.ok) {
                        throw new Error(response && response.error ? response.error : 'Delete failed.');
                }

                return response;
        }

        async function deleteAgentFromRow(row) {
                try {
                        const id = getAgentIdFromRow(row);

                        if (!id) {
                                throw new Error('Missing agent id.');
                        }

                        if (!window.confirm('Delete agent "' + id + '"?')) {
                                setLog('Delete cancelled for ' + id);
                                return;
                        }

                        setLog('Deleting agent ' + id);
                        const response = await deleteAgentById(id);
                        setLog('Deleted agent ' + getText(response.id || id, id) + '. Updating grid...');
                        await refreshGrid();
                        setLog('Deleted agent ' + getText(response.id || id, id) + '.');
                } catch (error) {
                        setLog('Delete failed: ' + getText(error && error.message ? error.message : error));
                }
        }

        async function deleteCurrentAgentFromEditor() {
                const elements = getEditorElements();
                const id = currentEditorAgentId || (elements.form ? getFormFieldValue(elements.form, 'old_id') : '');

                if (!id) {
                        setEditorStatus('Only existing agents can be deleted from the editor.', 'error');
                        return;
                }

                if (!window.confirm('Delete agent "' + id + '"?')) {
                        setEditorStatus('Delete cancelled.', '');
                        return;
                }

                try {
                        setEditorStatus('Deleting agent...', '');
                        const response = await deleteAgentById(id);
                        closeEditor();
                        await refreshGrid();
                        setLog('Deleted agent ' + getText(response.id || id, id) + '.');
                } catch (error) {
                        setEditorStatus(getText(error && error.message ? error.message : error), 'error');
                }
        }

        async function reloadStore() {
                try {
                        setLog('Reloading agent store.');
                        const response = await postJson({
                                mode: 'reload'
                        });

                        if (!response || !response.ok) {
                                throw new Error(response && response.error ? response.error : 'Reload failed.');
                        }

                        await refreshGrid();
                        setLog('Agent store reloaded.');
                } catch (error) {
                        setLog('Reload failed: ' + getText(error && error.message ? error.message : error));
                }
        }


        function bindEditorEvents() {
                const elements = getEditorElements();

                if (elements.form) {
                        elements.form.addEventListener('submit', (event) => {
                                event.preventDefault();
                                saveEditorPayload();
                        });

                        const policySelect = elements.form.querySelector('[data-base3-agent-policy-select]');
                        if (policySelect) {
                                policySelect.addEventListener('change', () => {
                                        renderAdminPolicyFields(elements.form, policySelect.value, {});
                                        setLog('Timing policy form rendered for ' + getText(policySelect.value));
                                });
                        }
                }

                log('editor events bound');
        }

        function createAgentAdminActionsPlugin() {
                return {
                        name: 'agentAdminActions',

                        layoutContributions() {
                                return [
                                        {
                                                zone: 'topLine1',
                                                order: 5,
                                                render() {
                                                        const wrapper = document.createElement('div');
                                                        wrapper.className = 'agent-admin-top-actions';

                                                        const addButton = createButton(
                                                                'agent-admin-button agent-admin-button-primary',
                                                                'Add agent'
                                                        );

                                                        const reloadButton = createButton(
                                                                'agent-admin-button',
                                                                'Reload'
                                                        );

                                                        addButton.addEventListener('click', (event) => {
                                                                event.preventDefault();
                                                                openNewEditor();
                                                        });

                                                        reloadButton.addEventListener('click', (event) => {
                                                                event.preventDefault();
                                                                reloadStore();
                                                        });

                                                        wrapper.appendChild(addButton);
                                                        wrapper.appendChild(reloadButton);

                                                        return wrapper;
                                                }
                                        }
                                ];
                        }
                };
        }

        async function initGrid(modularGridModule, modularDialogModule) {
                log('initGrid start');
                initEditorDialog(modularDialogModule);
                initRunnerDialog(modularDialogModule);
                bindEditorEvents();

                const {
                        AjaxAdapter,
                        ColumnVisibilityPlugin,
                        FiltersPlugin,
                        HeaderMenuPlugin,
                        InfoPlugin,
                        InfiniteScrollPlugin,
                        ModularGrid,
                        ResetPlugin,
                        RowActionsPlugin,
                        RowDetailPlugin,
                        SearchPlugin
                } = modularGridModule;

                if (!AjaxAdapter || !ModularGrid) {
                        throw new Error('ModularGrid module was loaded, but AjaxAdapter or ModularGrid export is missing.');
                }

                const adapter = new AjaxAdapter({
                        url: ENDPOINT_URL,
                        method: 'POST',
                        rowsPath: 'data',
                        totalPath: 'total',
                        mapRequest(request) {
                                const state = grid ? grid.getState() : {};
                                const filters = buildFilterPayload(state.filters || {});
                                const sortKey = request.sortKey || 'agent_id';
                                const sortDirection = request.sortDirection || 'asc';
                                const payload = {
                                        mode: 'page',
                                        page: request.page || 1,
                                        pageSize: request.pageSize || BATCH_SIZE,
                                        search: request.search || '',
                                        sort: [
                                                {
                                                        key: sortKey,
                                                        dir: sortDirection,
                                                        type: SORT_TYPES[sortKey] || 'string'
                                                }
                                        ],
                                        filters
                                };

                                log('mapRequest payload', payload);

                                return payload;
                        }
                });

                log('selected exports', {
                        AjaxAdapter: !!AjaxAdapter,
                        ColumnVisibilityPlugin: !!ColumnVisibilityPlugin,
                        FiltersPlugin: !!FiltersPlugin,
                        HeaderMenuPlugin: !!HeaderMenuPlugin,
                        InfoPlugin: !!InfoPlugin,
                        InfiniteScrollPlugin: !!InfiniteScrollPlugin,
                        ModularGrid: !!ModularGrid,
                        ResetPlugin: !!ResetPlugin,
                        RowActionsPlugin: !!RowActionsPlugin,
                        RowDetailPlugin: !!RowDetailPlugin,
                        SearchPlugin: !!SearchPlugin
                });

                log('adapter created');

                grid = new ModularGrid(GRID_SELECTOR, {
                        layout,
                        adapter,
                        dataMode: 'server',
                        server: {
                                searchDebounceMs: 220,
                                watchStateKeys: ['query', 'filters']
                        },
                        features: {
                                paging: false
                        },
                        pageSize: BATCH_SIZE,
                        sort: {
                                key: 'agent_id',
                                direction: 'asc'
                        },
                        plugins: [
                                createAgentAdminActionsPlugin(),
                                SearchPlugin,
                                FiltersPlugin,
                                HeaderMenuPlugin,
                                InfoPlugin,
                                ColumnVisibilityPlugin,
                                ResetPlugin,
                                RowActionsPlugin,
                                RowDetailPlugin,
                                InfiniteScrollPlugin
                        ].filter(Boolean),
                        pluginOptions: {
                                search: {
                                        zone: 'topLine1',
                                        order: 10,
                                        label: 'Search',
                                        placeholder: 'Search agent id, policy or prompt'
                                },
                                filters: {
                                        zone: 'topLine2',
                                        order: 10,
                                        stateKey: 'filters',
                                        showClearButton: true,
                                        clearLabel: 'Clear filters',
                                        fields: [
                                                {
                                                        key: 'enabled',
                                                        label: 'State',
                                                        type: 'select',
                                                        options: ENABLED_FILTER_OPTIONS
                                                },
                                                {
                                                        key: 'policy',
                                                        label: 'Policy',
                                                        type: 'select',
                                                        options: POLICY_FILTER_OPTIONS
                                                },
                                                {
                                                        key: 'llm',
                                                        label: 'LLM',
                                                        type: 'text',
                                                        placeholder: 'LLM',
                                                        width: 220
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
                                        order: 20,
                                        label: 'Reset',
                                        sections: ['query', 'filters', 'columns', 'detailView']
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
                                                        key: 'run-agent',
                                                        label: 'Run agent',
                                                        onClick(context) {
                                                                openRunnerFromRow(context && context.row ? context.row : null);
                                                        }
                                                },
                                                {
                                                        key: 'edit-agent',
                                                        label: 'Edit agent',
                                                        onClick(context) {
                                                                openEditorFromRow(context && context.row ? context.row : null);
                                                        }
                                                },
                                                {
                                                        key: 'delete-agent',
                                                        label: 'Delete agent',
                                                        onClick(context) {
                                                                deleteAgentFromRow(context && context.row ? context.row : null);
                                                        }
                                                }
                                        ]
                                },
                                rowDetail: {
                                        rowIdKey: 'agent_id',
                                        clearOnDataReload: true,
                                        asyncDetail: {
                                                load(context) {
                                                        log('row detail load', context && context.row ? context.row : null);
                                                        return loadRemoteRecord(context.row);
                                                },
                                                renderLoading(context) {
                                                        return createDetailLoadingPlaceholder(context.row);
                                                },
                                                renderError(context) {
                                                        return createDetailErrorPlaceholder(context.row, context.error);
                                                },
                                                render(context) {
                                                        return renderAgentDetail(context);
                                                }
                                        }
                                },
                                infiniteScroll: {
                                        threshold: 180,
                                        pageSize: BATCH_SIZE,
                                        containerSelector: '.mg-table-scroll'
                                }
                        },
                        columns: [
                                {
                                        key: 'agent_id',
                                        label: 'Agent',
                                        width: 300,
                                        headerMenu: {
                                                defaultSortKey: 'label',
                                                defaultSortDirection: 'asc',
                                                sortOptions: [
                                                        { key: 'label', label: 'Label' },
                                                        { key: 'agent_id', label: 'Agent ID' }
                                                ]
                                        },
                                        render(value, row) {
                                                return renderAgent(value, row);
                                        }
                                },
                                {
                                        key: 'policy_label',
                                        label: 'Timing policy',
                                        width: 300,
                                        headerMenu: {
                                                defaultSortKey: 'policy_label',
                                                defaultSortDirection: 'asc'
                                        },
                                        render(value, row) {
                                                return renderPolicy(value, row);
                                        }
                                },
                                {
                                        key: 'llm',
                                        label: 'LLM',
                                        width: 180,
                                        render(value) {
                                                return renderPills(value);
                                        }
                                },
                                {
                                        key: 'component_count',
                                        label: 'Components',
                                        width: 120,
                                        headerMenu: {
                                                defaultSortKey: 'component_count',
                                                defaultSortDirection: 'desc'
                                        },
                                        render(value, row) {
                                                return renderComponentCount(value, row);
                                        }
                                },
                                {
                                        key: 'user_prompt_preview',
                                        label: 'User prompt',
                                        width: 420,
                                        render(value) {
                                                return createElement('agent-admin-cell-sub', getText(value));
                                        }
                                }
                        ]
                });

                log('grid created');

                if (typeof grid.on === 'function') {
                        grid.on('data:loaded', (event) => {
                                log('event data:loaded', event);
                        });

                        grid.on('data:appended', (event) => {
                                log('event data:appended', event);
                        });

                        grid.on('detail:loaded', (event) => {
                                log('event detail:loaded', event);
                                setLog('Loaded detail for ' + getText(event && event.rowId));
                        });

                        grid.on('detail:error', (event) => {
                                log('event detail:error', event);
                                setLog('Failed to load detail: ' + getText(event && event.error));
                        });
                }

                log('grid.init start');
                await grid.init();
                log('grid.init finished');
                setLog('Agent Admin loaded.');
        }

        async function importFirst(url, moduleLabel) {
                log('import start: ' + moduleLabel, url);

                try {
                        const absoluteUrl = new URL(url, document.baseURI).href;
                        const module = await import(absoluteUrl);
                        log('import success: ' + moduleLabel, Object.keys(module || {}));
                        return module;
                } catch (error) {
                        log('import failed: ' + moduleLabel, error && error.message ? error.message : String(error));
                        throw error;
                }
        }

        function setStartupStatus(message, details = '', isError = false) {
                const root = document.querySelector(GRID_SELECTOR);
                log('startup: ' + message, details || undefined);

                if (!root) {
                        return;
                }

                const box = createElement('agent-admin-startup' + (isError ? ' agent-admin-startup-error' : ''));
                box.appendChild(document.createTextNode(message));

                if (details) {
                        const pre = document.createElement('pre');
                        pre.textContent = details;
                        box.appendChild(pre);
                }

                root.replaceChildren(box);
        }

        (async function boot() {
                const root = document.querySelector(GRID_SELECTOR);

                log('bootstrap start', {
                        rootFound: !!root,
                        initialized: root ? root.dataset.initialized || '' : null,
                        endpoint: ENDPOINT_URL,
                        modularGridUrl: MODULARGRID_URL,
                        modularDialogUrl: MODULAR_DIALOG_URL
                });

                if (!root || root.dataset.initialized === '1') {
                        return;
                }

                root.dataset.initialized = '1';
                setStartupStatus('Loading ModularGrid module.');

                try {
                        if (!ENDPOINT_URL) {
                                throw new Error('Missing Agent Admin endpoint URL.');
                        }

                        const modularGridModule = await importFirst(MODULARGRID_URL, 'ModularGrid');
                        const modularDialogModule = await importFirst(MODULAR_DIALOG_URL, 'ModularDialog');
                        setStartupStatus('Initializing agent grid.');
                        await initGrid(modularGridModule, modularDialogModule);
                } catch (error) {
                        const message = error && error.message ? error.message : String(error);
                        setStartupStatus('Agent Admin could not be initialized.', message, true);
                        setLog('Initialization failed: ' + message);
                        console.error(error);
                }
        })();
})();
</script>
