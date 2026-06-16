<?php
$resolve = $this->_['resolve'];

$serviceUrl = (string) ($this->_['service'] ?? '');
$resourceOptions = is_array($this->_['resource_options'] ?? null) ? $this->_['resource_options'] : [];
$categoryOptions = ['context', 'web', 'ai', 'memory', 'tool', 'storage', 'integration', 'system', 'experimental'];
$statusOptions = ['draft', 'ready', 'disabled', 'deprecated'];
$riskOptions = ['none', 'read_external_url', 'reads_context', 'writes_memory', 'writes_settings', 'external_api', 'destructive', 'experimental'];
$capabilityOptions = ['memory', 'tool'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$timestamp = date('c');
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?php echo $e($modularGridCssUrl); ?>" />

<style>
        .agent-component-preset-step5-shell {
                max-width: 1700px;
        }

        .agent-component-preset-step5-shell h1 {
                margin: 0 0 8px 0;
                font-size: 24px;
                line-height: 1.2;
                font-weight: 600;
        }

        .agent-component-preset-step5-shell p {
                margin: 0 0 16px 0;
                max-width: 1120px;
                color: #555;
                line-height: 1.45;
        }

        .agent-component-preset-step5-grid .agent-component-preset-step5-panel {
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

        .agent-component-preset-step5-grid .agent-component-preset-step5-panel > * {
                flex: 0 0 auto;
        }

        .agent-component-preset-step5-main {
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                padding: 4px 0;
        }

        .agent-component-preset-step5-grid .mg-control-group {
                flex-direction: row;
                align-items: center;
                gap: 6px;
                min-width: auto;
        }

        .agent-component-preset-step5-grid .mg-label {
                white-space: nowrap;
                color: #666;
                font-size: 12px;
        }

        .agent-component-preset-step5-grid .mg-inline-buttons {
                flex-wrap: nowrap;
        }

        .agent-component-preset-step5-grid .mg-input,
        .agent-component-preset-step5-grid .mg-select,
        .agent-component-preset-step5-grid .mg-button {
                min-height: 28px;
                font-size: 13px;
        }

        .agent-component-preset-step5-grid input[type="search"].mg-input {
                width: 300px;
        }

        .agent-component-preset-step5-grid .mg-select {
                width: auto;
                min-width: 96px;
        }

        .agent-component-preset-step5-grid .mg-table-scroll {
                height: 540px;
                overflow: auto;
                padding-bottom: 4px;
        }

        .agent-component-preset-step5-grid .mg-table thead th {
                position: sticky;
                top: 0;
                z-index: 12;
                background: #fff;
        }

        .agent-component-preset-step5-grid .mg-table thead th.mg-cell-pinned {
                z-index: 14;
        }

        .agent-component-preset-step5-grid .mg-table th,
        .agent-component-preset-step5-grid .mg-table td {
                padding: 6px 8px;
                font-size: 13px;
                vertical-align: top;
        }

        .agent-component-preset-step5-grid .mg-row-actions-cell,
        .agent-component-preset-step5-grid .mg-row-actions-header {
                width: 54px;
                min-width: 54px;
                text-align: center;
        }

        .agent-component-preset-step5-top-actions,
        .agent-component-preset-step5-detail-actions {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
        }

        .agent-component-preset-step5-cell-stack {
                display: grid;
                gap: 2px;
                min-width: 0;
        }

        .agent-component-preset-step5-cell-main {
                font-weight: 600;
                color: #222;
                min-width: 0;
                overflow-wrap: anywhere;
        }

        .agent-component-preset-step5-cell-sub {
                font-size: 12px;
                color: #666;
                min-width: 0;
                overflow-wrap: anywhere;
        }

        .agent-component-preset-step5-pill-row {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                align-items: center;
        }

        .agent-component-preset-step5-pill {
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

        .agent-component-preset-step5-output,
        .agent-component-preset-step5-status {
                margin-top: 12px;
                padding: 8px 10px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                font-size: 13px;
                color: #555;
        }

        .agent-component-preset-step5-output strong,
        .agent-component-preset-step5-status strong {
                color: #222;
        }

        .agent-component-preset-step5-startup {
                padding: 16px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                font-size: 13px;
                color: #555;
        }

        .agent-component-preset-step5-startup-error {
                border-color: #e4b9b9;
                background: #fff8f8;
                color: #8a1f1f;
        }

        .agent-component-preset-step5-startup pre {
                white-space: pre-wrap;
                word-break: break-word;
                margin: 8px 0 0 0;
                font-size: 12px;
        }

        .agent-component-preset-step5-detail {
                min-width: 0;
        }

        .agent-component-preset-step5-detail-layout,
        .agent-component-preset-step5-detail {
                display: grid;
                grid-template-columns: minmax(320px, 1fr) minmax(360px, 1.15fr);
                gap: 14px;
                align-items: start;
                padding: 10px;
                background: #fbfbfb;
        }

        .agent-component-preset-step5-detail-card {
                min-width: 0;
                padding: 10px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
        }

        .agent-component-preset-step5-detail-title {
                margin: 0 0 6px 0;
                font-size: 15px;
                font-weight: 600;
                color: #222;
        }

        .agent-component-preset-step5-detail-row {
                display: grid;
                grid-template-columns: 120px minmax(0, 1fr);
                gap: 6px;
                margin: 0 0 6px 0;
                font-size: 13px;
        }

        .agent-component-preset-step5-detail-key {
                font-weight: 600;
                color: #444;
        }

        .agent-component-preset-step5-json,
        .agent-component-preset-step5-log {
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

        .agent-component-preset-step5-log {
                max-height: 260px;
        }

        .agent-component-preset-step5-log-details {
                margin-top: 10px;
        }

        .agent-component-preset-step5-log-details summary {
                padding: 7px 10px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                cursor: pointer;
                font-size: 13px;
                color: #444;
        }

        .agent-component-preset-step5-button {
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

        .agent-component-preset-step5-button:hover {
                background: #f5f5f5;
        }

        .agent-component-preset-step5-button:focus-visible {
                outline: 2px solid #86a8cf;
                outline-offset: 2px;
        }

        .agent-component-preset-step5-button-primary {
                background: #222;
                border-color: #222;
                color: #fff;
        }

        .agent-component-preset-step5-button-primary:hover {
                background: #444;
        }

        .agent-component-preset-step5-button-danger {
                border-color: #c8a2a2;
                color: #8a1f1f;
        }

        .agent-component-preset-step5-button-danger:hover {
                background: #fff0f0;
        }

        .agent-component-preset-step5-modal-backdrop {
                position: fixed;
                inset: 0;
                z-index: 9000;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(0, 0, 0, 0.35);
        }

        .agent-component-preset-step5-modal-backdrop.is-open {
                display: flex;
        }

        .agent-component-preset-step5-modal {
                display: grid;
                grid-template-rows: auto 1fr auto;
                gap: 12px;
                width: min(1120px, 100%);
                max-height: min(860px, 100%);
                border: 1px solid #d6d6d6;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 16px 50px rgba(0, 0, 0, 0.20);
                padding: 16px;
        }

        .agent-component-preset-step5-modal-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
        }

        .agent-component-preset-step5-modal-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
        }

        .agent-component-preset-step5-modal-title {
                font-size: 18px;
                line-height: 1.25;
                font-weight: 600;
                color: #222;
        }

        .agent-component-preset-step5-modal-body {
                min-height: 0;
                overflow: auto;
        }

        .agent-component-preset-step5-form {
                display: grid;
                grid-template-columns: repeat(2, minmax(260px, 1fr));
                gap: 12px;
        }

        .agent-component-preset-step5-field-full {
                grid-column: 1 / -1;
        }

        .agent-component-preset-step5-label {
                display: block;
                margin: 0 0 4px 0;
                font-size: 12px;
                font-weight: 600;
                color: #333;
        }

        .agent-component-preset-step5-input,
        .agent-component-preset-step5-select,
        .agent-component-preset-step5-textarea {
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

        .agent-component-preset-step5-select[multiple] {
                min-height: 72px;
        }

        .agent-component-preset-step5-textarea {
                min-height: 110px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 12px;
                line-height: 1.4;
                resize: vertical;
        }

        .agent-component-preset-step5-checkbox-row {
                display: flex;
                align-items: center;
                gap: 10px;
                min-height: 32px;
                font-size: 13px;
        }

        .agent-component-preset-step5-modal-status {
                min-height: 18px;
                font-size: 12px;
                color: #666;
        }

        .agent-component-preset-step5-modal-status-error {
                color: #8a1f1f;
        }

        .agent-component-preset-step5-modal-status-ok {
                color: #276028;
        }

        @media (max-width: 980px) {
                .agent-component-preset-step5-detail,
                .agent-component-preset-step5-form {
                        grid-template-columns: 1fr;
                }
        }

        @media (max-width: 720px) {
                .agent-component-preset-step5-shell h1 {
                        font-size: 21px;
                }

                .agent-component-preset-step5-grid .mg-table-scroll {
                        height: 420px;
                }
        }
</style>

<div class="agent-component-preset-step5-shell">
        <h1>Agent Component Preset Admin</h1>
        <p>
                Manage reusable presets for dockable MissionBay agent components. Resource types, capabilities and meta fields are selected through controlled editor fields; Config JSON and Docks JSON stay editable as structured JSON.
        </p>

        <div class="agent-component-preset-step5-grid">
                <div class="agent-component-preset-step5-top-actions">
                        <button type="button" id="agent-component-preset-step5-add" class="agent-component-preset-step5-button agent-component-preset-step5-button-primary">Add preset</button>
                        <button type="button" id="agent-component-preset-step5-reload" class="agent-component-preset-step5-button">Reload defaults</button>
                </div>

                <div id="agent-component-preset-step5-grid" class="agent-component-preset-step5-grid-shell">
                        <div class="agent-component-preset-step5-startup">Loading Agent Component Preset Admin display...</div>
                </div>
                <div id="agent-component-preset-step5-output" class="agent-component-preset-step5-status"><strong>Last action:</strong> Waiting for initialization.</div>
                <details class="agent-component-preset-step5-log-details">
                        <summary>Debug log</summary>
                        <pre id="agent-component-preset-step5-log" class="agent-component-preset-step5-log">Status log will appear here.</pre>
                </details>
        </div>
</div>

<div id="agent-component-preset-step5-editor" class="agent-component-preset-step5-modal-backdrop" aria-hidden="true">
        <div class="agent-component-preset-step5-modal" role="dialog" aria-modal="true" aria-labelledby="agent-component-preset-step5-editor-title">
                <div class="agent-component-preset-step5-modal-header">
                        <div id="agent-component-preset-step5-editor-title" class="agent-component-preset-step5-modal-title">Preset editor</div>
                        <button type="button" class="agent-component-preset-step5-button" data-editor-close="1">Close</button>
                </div>
                <div class="agent-component-preset-step5-modal-body">
                        <form id="agent-component-preset-step5-form" class="agent-component-preset-step5-form">
                                <input type="hidden" name="old_id" />

                                <div>
                                        <label class="agent-component-preset-step5-label">Preset ID</label>
                                        <input type="text" name="id" class="agent-component-preset-step5-input" />
                                </div>

                                <div>
                                        <label class="agent-component-preset-step5-label">Label</label>
                                        <input type="text" name="label" class="agent-component-preset-step5-input" />
                                </div>

                                <div>
                                        <label class="agent-component-preset-step5-label">Resource type</label>
                                        <select name="type" class="agent-component-preset-step5-select">
                                                <option value="">Select resource type</option>
<?php foreach($resourceOptions as $resourceOption): ?>
<?php
        $resourceId = is_array($resourceOption) ? (string)($resourceOption['id'] ?? '') : (string)$resourceOption;
        $resourceClass = is_array($resourceOption) ? (string)($resourceOption['class'] ?? '') : '';
        if($resourceId === '') {
                continue;
        }
?>
                                                <option value="<?php echo $e($resourceId); ?>" title="<?php echo $e($resourceClass); ?>"><?php echo $e($resourceId); ?></option>
<?php endforeach; ?>
                                        </select>
                                </div>

                                <div>
                                        <label class="agent-component-preset-step5-label">Enabled</label>
                                        <label class="agent-component-preset-step5-checkbox-row"><input type="checkbox" name="enabled" value="1" /> enabled</label>
                                </div>

                                <div>
                                        <label class="agent-component-preset-step5-label">Capabilities</label>
                                        <select name="capabilities[]" class="agent-component-preset-step5-select" multiple>
<?php foreach($capabilityOptions as $capabilityOption): ?>
                                                <option value="<?php echo $e($capabilityOption); ?>"><?php echo $e($capabilityOption); ?></option>
<?php endforeach; ?>
                                        </select>
                                </div>

                                <div>
                                        <label class="agent-component-preset-step5-label">Category</label>
                                        <select name="category" class="agent-component-preset-step5-select">
                                                <option value="">Select category</option>
<?php foreach($categoryOptions as $categoryOption): ?>
                                                <option value="<?php echo $e($categoryOption); ?>"><?php echo $e($categoryOption); ?></option>
<?php endforeach; ?>
                                        </select>
                                </div>

                                <div>
                                        <label class="agent-component-preset-step5-label">Status</label>
                                        <select name="status" class="agent-component-preset-step5-select">
                                                <option value="">Select status</option>
<?php foreach($statusOptions as $statusOption): ?>
                                                <option value="<?php echo $e($statusOption); ?>"><?php echo $e($statusOption); ?></option>
<?php endforeach; ?>
                                        </select>
                                </div>

                                <div>
                                        <label class="agent-component-preset-step5-label">Risk</label>
                                        <select name="risk" class="agent-component-preset-step5-select">
                                                <option value="">Select risk</option>
<?php foreach($riskOptions as $riskOption): ?>
                                                <option value="<?php echo $e($riskOption); ?>"><?php echo $e($riskOption); ?></option>
<?php endforeach; ?>
                                        </select>
                                </div>

                                <div>
                                        <label class="agent-component-preset-step5-label">Version</label>
                                        <input type="text" name="version" class="agent-component-preset-step5-input" />
                                </div>

                                <div class="agent-component-preset-step5-field-full">
                                        <label class="agent-component-preset-step5-label">Description</label>
                                        <textarea name="description" class="agent-component-preset-step5-textarea"></textarea>
                                </div>

                                <div class="agent-component-preset-step5-field-full">
                                        <label class="agent-component-preset-step5-label">Config JSON</label>
                                        <textarea name="config_json" class="agent-component-preset-step5-textarea"></textarea>
                                </div>

                                <div class="agent-component-preset-step5-field-full">
                                        <label class="agent-component-preset-step5-label">Docks JSON</label>
                                        <textarea name="docks_json" class="agent-component-preset-step5-textarea"></textarea>
                                </div>

                                <div class="agent-component-preset-step5-field-full">
                                        <label class="agent-component-preset-step5-label">Meta JSON</label>
                                        <textarea name="meta_json" class="agent-component-preset-step5-textarea"></textarea>
                                </div>
                        </form>
                </div>
                <div class="agent-component-preset-step5-modal-footer">
                        <div id="agent-component-preset-step5-editor-status" class="agent-component-preset-step5-modal-status">Save is enabled.</div>
                        <div>
                                <button type="button" class="agent-component-preset-step5-button" id="agent-component-preset-step5-copy-payload">Copy payload</button>
                                <button type="button" class="agent-component-preset-step5-button agent-component-preset-step5-button-primary" id="agent-component-preset-step5-save">Save</button>
                        </div>
                </div>
        </div>
</div>


<script type="module">
console.log('[AgentComponentPresetAdmin] module script entered');

const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const MODULARGRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const RESOURCE_OPTIONS = <?php echo json_encode($resourceOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const GRID_SELECTOR = '#agent-component-preset-step5-grid';
const LOG_SELECTOR = '#agent-component-preset-step5-log';
const OUTPUT_SELECTOR = '#agent-component-preset-step5-output';
const BATCH_SIZE = 50;
const SORT_TYPES = {
        preset_id: 'string',
        label: 'string',
        type: 'string',
        capability_text: 'string',
        status: 'string',
        category: 'string'
};

const layout = {
        type: 'stack',
        className: 'mg-layout-root',
        children: [
                {
                        type: 'zone',
                        key: 'topLine',
                        className: 'agent-component-preset-step5-panel'
                },
                {
                        type: 'view',
                        key: 'main',
                        className: 'agent-component-preset-step5-main'
                },
                {
                        type: 'zone',
                        key: 'statusZone',
                        className: 'agent-component-preset-step5-panel'
                }
        ]
};

function log(label, value = undefined) {
        const message = value === undefined ? String(label) : String(label) + ' ' + stringifyJson(value);
        const logElement = document.querySelector(LOG_SELECTOR);

        console.log('[AgentComponentPresetAdminStep5]', label, value === undefined ? '' : value);

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

function getText(value, placeholder = '-') {
        if (value === null || value === undefined || value === '') {
                return placeholder;
        }

        return String(value);
}

function setStartupStatus(message, details = '', isError = false) {
        const root = document.querySelector(GRID_SELECTOR);

        log('startup: ' + message, details || undefined);

        if (!root) {
                return;
        }

        const box = createElement('agent-component-preset-step5-startup' + (isError ? ' agent-component-preset-step5-startup-error' : ''));
        box.appendChild(document.createTextNode(message));

        if (details) {
                const pre = document.createElement('pre');
                pre.textContent = details;
                box.appendChild(pre);
        }

        root.replaceChildren(box);
}

async function importFirst(url, moduleLabel) {
        log('import start: ' + moduleLabel, url);

        try {
                const absoluteUrl = new URL(url, document.baseURI).href;
                log('import attempt: ' + moduleLabel, absoluteUrl);
                const module = await import(absoluteUrl);
                log('import success: ' + moduleLabel, Object.keys(module || {}));
                return module;
        } catch (error) {
                log('import failed: ' + moduleLabel, error && error.message ? error.message : String(error));
                throw error;
        }
}

async function postJson(payload) {
        log('POST JSON start', payload);

	/*
	 * CRITICAL: Do not change this request contract.
	 * ModularGrid and the BASE3/ILIAS endpoint currently rely on this exact fetch setup:
	 * POST + Content-Type application/json + JSON.stringify(payload).
	 * Do not add credentials, mode, cache, FormData, query params, CSRF handling,
	 * wrappers, adapter changes, or any other request architecture here.
	 * Any change to this block requires an explicit user request and a separate runtime test.
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

function renderPreset(value, row) {
        const wrapper = createElement('agent-component-preset-step5-cell-stack');
        const main = createElement('agent-component-preset-step5-cell-main', getText(row.label || row.preset_id));
        const sub = createElement('agent-component-preset-step5-cell-sub', getText(row.preset_id));

        wrapper.appendChild(main);
        wrapper.appendChild(sub);

        return wrapper;
}

function renderType(value, row) {
        const wrapper = createElement('agent-component-preset-step5-cell-stack');
        const main = createElement('agent-component-preset-step5-cell-main', getText(row.type));
        const sub = createElement('agent-component-preset-step5-cell-sub', getText(row.category));

        wrapper.appendChild(main);
        wrapper.appendChild(sub);

        return wrapper;
}

function renderPills(value) {
        const wrapper = createElement('agent-component-preset-step5-pill-row');
        const items = String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

        if (items.length === 0) {
                wrapper.appendChild(createPill('-'));
                return wrapper;
        }

        items.forEach((item) => wrapper.appendChild(createPill(item)));

        return wrapper;
}

function createPill(text) {
        const pill = document.createElement('span');
        pill.className = 'agent-component-preset-step5-pill';
        pill.textContent = getText(text);

        return pill;
}

function getPresetIdFromRow(row) {
        if (!row || typeof row !== 'object') {
                return '';
        }

        return String(row.preset_id || row.id || '').trim();
}

async function loadRemoteRecord(row) {
        const id = getPresetIdFromRow(row);

        if (!id) {
                throw new Error('Missing preset id for detail request.');
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
        return createElement('agent-component-preset-step5-startup', 'Loading record for ' + getText(getPresetIdFromRow(row)) + '...');
}

function createDetailErrorPlaceholder(row, error) {
        return createElement('agent-component-preset-step5-startup agent-component-preset-step5-startup-error', 'Failed to load record for ' + getText(getPresetIdFromRow(row)) + ': ' + getText(error && error.message ? error.message : error));
}

function createDetailRow(key, value) {
        const row = createElement('agent-component-preset-step5-detail-row');
        row.appendChild(createElement('agent-component-preset-step5-detail-key', key));
        row.appendChild(createElement('', getText(value)));

        return row;
}

function renderPresetDetail(context) {
        const record = context && context.payload ? context.payload : null;

        if (!record || typeof record !== 'object') {
                return document.createTextNode(getText(record));
        }

        const wrapper = createElement('agent-component-preset-step5-detail');
        const left = createElement('agent-component-preset-step5-detail-card');
        const right = createElement('agent-component-preset-step5-detail-card');
        const pre = document.createElement('pre');

        left.appendChild(createElement('agent-component-preset-step5-detail-title', getText(record.label || record.preset_id)));
        left.appendChild(createDetailRow('ID', record.preset_id || record.id));
        left.appendChild(createDetailRow('Type', record.type));
        left.appendChild(createDetailRow('Enabled', record.enabled ? 'yes' : 'no'));
        left.appendChild(createDetailRow('Capabilities', record.capability_text));
        left.appendChild(createDetailRow('Category', record.category));
        left.appendChild(createDetailRow('Status', record.status));
        left.appendChild(createDetailRow('Risk', record.risk));
        left.appendChild(createDetailRow('Version', record.version));
        left.appendChild(createDetailRow('Description', record.description));

        right.appendChild(createElement('agent-component-preset-step5-detail-title', 'Record JSON'));
        pre.className = 'agent-component-preset-step5-json';
        pre.textContent = record.preset_json || stringifyJson(record);
        right.appendChild(pre);

        wrapper.appendChild(left);
        wrapper.appendChild(right);

        return wrapper;
}


function getEditorElements() {
        return {
                modal: document.getElementById('agent-component-preset-step5-editor'),
                form: document.getElementById('agent-component-preset-step5-form'),
                status: document.getElementById('agent-component-preset-step5-editor-status')
        };
}

function setEditorStatus(message, type = '') {
        const elements = getEditorElements();

        if (!elements.status) {
                return;
        }

        elements.status.className = 'agent-component-preset-step5-modal-status';

        if (type) {
                elements.status.classList.add('agent-component-preset-step5-modal-status-' + type);
        }

        elements.status.textContent = message || '';
}

function setFormValue(form, name, value) {
        const field = form.elements.namedItem(name);

        if (!field) {
                return;
        }

        field.value = value === null || value === undefined ? '' : String(value);
}

function ensureSelectOption(form, name, value, label = '') {
        const field = form.elements.namedItem(name);
        const normalizedValue = value === null || value === undefined ? '' : String(value);

        if (!(field instanceof HTMLSelectElement) || normalizedValue === '') {
                return;
        }

        const exists = Array.from(field.options).some((option) => option.value === normalizedValue);

        if (exists) {
                return;
        }

        const option = document.createElement('option');
        option.value = normalizedValue;
        option.textContent = label || normalizedValue;
        option.dataset.generated = '1';
        field.appendChild(option);
}

function setSelectValue(form, name, value) {
        const field = form.elements.namedItem(name);
        const normalizedValue = value === null || value === undefined ? '' : String(value);

        if (!(field instanceof HTMLSelectElement)) {
                setFormValue(form, name, normalizedValue);
                return;
        }

        ensureSelectOption(form, name, normalizedValue);
        field.value = normalizedValue;
}

function setCapabilityCheckboxes(form, capabilities) {
        const values = new Set(Array.isArray(capabilities) ? capabilities.map(String) : []);
        const fields = form.querySelectorAll('[name="capabilities[]"]');
        const select = Array.from(fields).find((field) => field instanceof HTMLSelectElement);

        if (select) {
                values.forEach((value) => ensureSelectOption(form, 'capabilities[]', value));
                Array.from(select.options).forEach((option) => {
                        option.selected = values.has(option.value);
                });
                return;
        }

        fields.forEach((field) => {
                field.checked = values.has(field.value);
        });
}

function getCapabilityValues(form) {
        const fields = form.querySelectorAll('[name="capabilities[]"]');
        const select = Array.from(fields).find((field) => field instanceof HTMLSelectElement);

        if (select) {
                return Array.from(select.selectedOptions)
                        .map((option) => String(option.value || '').trim())
                        .filter(Boolean);
        }

        const capabilities = [];
        fields.forEach((field) => {
                if (field.checked) {
                        capabilities.push(field.value);
                }
        });

        return capabilities;
}

function buildMetaJsonFromForm(form) {
        const meta = parseEditorJsonField(form, 'meta_json', 'Meta JSON', true);
        const versionRaw = getFormFieldValue(form, 'version');

        meta.description = getFormFieldValue(form, 'description');
        meta.category = getFormFieldValue(form, 'category');
        meta.risk = getFormFieldValue(form, 'risk');
        meta.status = getFormFieldValue(form, 'status');

        if (versionRaw === '') {
                delete meta.version;
        } else {
                const numericVersion = Number(versionRaw);
                meta.version = Number.isFinite(numericVersion) && String(numericVersion) === versionRaw ? numericVersion : versionRaw;
        }

        return meta;
}

function openPresetEditor(record) {
        const elements = getEditorElements();

        if (!elements.modal || !elements.form) {
                setLog('Preset editor elements not found.');
                return;
        }

        const form = elements.form;
        form.reset();

        record = record && typeof record === 'object' ? record : {};

        const oldIdValue = Object.prototype.hasOwnProperty.call(record, 'old_id') ? record.old_id : (record.preset_id || record.id || '');
        setFormValue(form, 'old_id', oldIdValue);
        setFormValue(form, 'id', record.preset_id || record.id || '');
        setFormValue(form, 'label', record.label || '');
        setSelectValue(form, 'type', record.type || '');
        setSelectValue(form, 'category', record.category || '');
        setSelectValue(form, 'status', record.status || '');
        setSelectValue(form, 'risk', record.risk || '');
        setFormValue(form, 'version', record.version || '');
        setFormValue(form, 'description', record.description || '');
        setFormValue(form, 'config_json', record.config_json || '{}');
        setFormValue(form, 'docks_json', record.docks_json || '{}');
        setFormValue(form, 'meta_json', record.meta_json || '{}');

        const enabled = form.elements.namedItem('enabled');
        if (enabled) {
                enabled.checked = record.enabled !== false;
        }

        setCapabilityCheckboxes(form, record.capabilities || []);

        elements.modal.classList.add('is-open');
        elements.modal.setAttribute('aria-hidden', 'false');
        setEditorStatus('Editor opened. Save is enabled.', 'ok');
        setLog('Opened editor test for ' + getText(record.preset_id || record.id, 'new preset'));
}

function openNewPresetEditor() {
        openPresetEditor({
                preset_id: '',
                id: '',
                label: '',
                type: '',
                enabled: true,
                capabilities: ['tool'],
                category: '',
                status: 'draft',
                risk: '',
                version: 1,
                description: '',
                config_json: '{}',
                docks_json: '{}',
                meta_json: stringifyJson({
                        description: '',
                        category: '',
                        risk: '',
                        status: 'draft',
                        version: 1
                })
        });
}

function createDuplicatePresetRecord(record) {
        record = record && typeof record === 'object' ? record : {};

        const sourceId = String(record.preset_id || record.id || '').trim();
        const sourceLabel = String(record.label || sourceId || '').trim();
        const duplicateId = sourceId ? sourceId + '_copy' : '';
        const duplicate = Object.assign({}, record);

        duplicate.old_id = '';
        duplicate.preset_id = duplicateId;
        duplicate.id = duplicateId;
        duplicate.label = sourceLabel ? 'Copy of ' + sourceLabel : '';

        return duplicate;
}

function closePresetEditor() {
        const elements = getEditorElements();

        if (!elements.modal) {
                return;
        }

        elements.modal.classList.remove('is-open');
        elements.modal.setAttribute('aria-hidden', 'true');
        setLog('Closed editor.');
}

function getFormFieldValue(form, name) {
        const field = form.elements.namedItem(name);

        if (!field) {
                return '';
        }

        return String(field.value || '').trim();
}

function parseEditorJsonField(form, fieldName, label, requirePlainObject = false) {
        const field = form.elements.namedItem(fieldName);
        const value = field ? String(field.value || '').trim() : '';
        let decoded;

        try {
                decoded = JSON.parse(value || '{}');
        } catch (error) {
                throw new Error(label + ': ' + (error && error.message ? error.message : String(error)));
        }

        if (decoded === null || typeof decoded !== 'object') {
                throw new Error(label + ' must decode to a JSON object or array.');
        }

        if (requirePlainObject && Array.isArray(decoded)) {
                throw new Error(label + ' must decode to a JSON object.');
        }

        return decoded;
}

function getSelectedCapabilities(form) {
        return getCapabilityValues(form);
}

function validateEditorRequiredFields(form, capabilities) {
        const id = getFormFieldValue(form, 'id');
        const label = getFormFieldValue(form, 'label');
        const type = getFormFieldValue(form, 'type');

        if (!id) {
                throw new Error('Preset ID is required.');
        }

        if (!label) {
                throw new Error('Label is required.');
        }

        if (!type) {
                throw new Error('Resource type is required.');
        }

        if (!capabilities.length) {
                throw new Error('At least one capability is required.');
        }
}

function syncVisibleMetaFields(form) {
        const meta = buildMetaJsonFromForm(form);
        form.elements.namedItem('meta_json').value = stringifyJson(meta);

        return meta;
}

function buildEditorPayload(options = {}) {
        const settings = Object.assign({ validateRequired: false, syncMeta: true }, options || {});
        const elements = getEditorElements();
        const form = elements.form;

        if (!form) {
                throw new Error('Preset editor form not found.');
        }

        const capabilities = getSelectedCapabilities(form);

        parseEditorJsonField(form, 'config_json', 'Config JSON');
        parseEditorJsonField(form, 'docks_json', 'Docks JSON');

        if (settings.syncMeta) {
                syncVisibleMetaFields(form);
        } else {
                parseEditorJsonField(form, 'meta_json', 'Meta JSON', true);
        }

        if (settings.validateRequired) {
                validateEditorRequiredFields(form, capabilities);
        }

        return {
                mode: 'save',
                old_id: getFormFieldValue(form, 'old_id'),
                id: getFormFieldValue(form, 'id'),
                label: getFormFieldValue(form, 'label'),
                type: getFormFieldValue(form, 'type'),
                enabled: form.elements.namedItem('enabled').checked,
                capabilities,
                category: getFormFieldValue(form, 'category'),
                status: getFormFieldValue(form, 'status'),
                risk: getFormFieldValue(form, 'risk'),
                version: getFormFieldValue(form, 'version'),
                description: getFormFieldValue(form, 'description'),
                config_json: form.elements.namedItem('config_json').value,
                docks_json: form.elements.namedItem('docks_json').value,
                meta_json: form.elements.namedItem('meta_json').value
        };
}

async function copyEditorPayload() {
        try {
                const payload = buildEditorPayload({ validateRequired: false, syncMeta: true });
                await copyText(stringifyJson(payload));
                setEditorStatus('Payload copied. Visible meta fields were synchronized.', 'ok');
                setLog('Copied editor payload for ' + getText(payload.id, 'new preset'));
        } catch (error) {
                setEditorStatus(error && error.message ? error.message : String(error), 'error');
        }
}

async function saveEditorPayload() {
        try {
                const payload = buildEditorPayload({ validateRequired: true, syncMeta: true });

                setEditorStatus('Saving preset...', '');
                setLog('Saving preset ' + getText(payload.id, 'new preset'));

                const response = await postJson(payload);

                if (!response || !response.ok) {
                        throw new Error(response && response.error ? response.error : 'Save failed.');
                }

                setEditorStatus('Preset saved. Reloading page...', 'ok');
                setLog('Saved preset ' + getText(response.id || payload.id, payload.id));

                window.setTimeout(() => {
                        window.location.reload();
                }, 500);
        } catch (error) {
                setEditorStatus(error && error.message ? error.message : String(error), 'error');
                setLog('Save failed: ' + getText(error && error.message ? error.message : error));
        }
}

async function deletePresetById(id) {
        id = String(id || '').trim();

        if (!id) {
                throw new Error('Missing preset id.');
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

async function deletePresetFromRow(row) {
        try {
                const id = getPresetIdFromRow(row);

                if (!id) {
                        throw new Error('Missing preset id.');
                }

                if (!window.confirm('Delete preset "' + id + '"?')) {
                        setLog('Delete cancelled for ' + id);
                        return;
                }

                setLog('Deleting preset ' + id);
                const response = await deletePresetById(id);
                setLog('Deleted preset ' + getText(response.id || id, id) + '. Reloading page...');

                window.setTimeout(() => {
                        window.location.reload();
                }, 500);
        } catch (error) {
                setLog('Delete failed: ' + getText(error && error.message ? error.message : error));
        }
}

async function reloadPresetDefaults() {
        try {
                if (!window.confirm('Reload preset defaults from SettingsStore?')) {
                        setLog('Reload defaults cancelled.');
                        return;
                }

                setLog('Reloading preset defaults.');
                setLog('Available resource options', RESOURCE_OPTIONS);

                const response = await postJson({
                        mode: 'reload'
                });

                if (!response || !response.ok) {
                        throw new Error(response && response.error ? response.error : 'Reload failed.');
                }

                setLog('Preset defaults reloaded.');
                setLog('Reload response', response);

                window.setTimeout(() => {
                        window.location.reload();
                }, 500);
        } catch (error) {
                setLog('Reload defaults failed: ' + getText(error && error.message ? error.message : error));
        }
}

async function openEditorFromRow(row) {
        try {
                setLog('Loading record for editor: ' + getText(getPresetIdFromRow(row)));
                const record = await loadRemoteRecord(row);
                openPresetEditor(record);
        } catch (error) {
                setLog('Could not open editor: ' + getText(error && error.message ? error.message : error));
        }
}

async function openDuplicateEditorFromRow(row) {
        try {
                setLog('Loading record for duplicate: ' + getText(getPresetIdFromRow(row)));
                const record = await loadRemoteRecord(row);
                const duplicate = createDuplicatePresetRecord(record);

                openPresetEditor(duplicate);
                setEditorStatus('Duplicate opened. Review the preset id, then save as a new preset.', 'ok');
                setLog('Opened duplicate editor for ' + getText(record.preset_id || record.id));
        } catch (error) {
                setLog('Could not duplicate preset: ' + getText(error && error.message ? error.message : error));
        }
}

function bindEditorEvents() {
        const addButton = document.getElementById('agent-component-preset-step5-add');
        const reloadButton = document.getElementById('agent-component-preset-step5-reload');
        const copyButton = document.getElementById('agent-component-preset-step5-copy-payload');
        const saveButton = document.getElementById('agent-component-preset-step5-save');
        const elements = getEditorElements();

        if (addButton) {
                addButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        openNewPresetEditor();
                });
        }

        if (reloadButton) {
                reloadButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        reloadPresetDefaults();
                });
        }

        if (copyButton) {
                copyButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        copyEditorPayload();
                });
        }

        if (saveButton) {
                saveButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        saveEditorPayload();
                });
        }

        if (elements.modal) {
                elements.modal.querySelectorAll('[data-editor-close]').forEach((button) => {
                        button.addEventListener('click', (event) => {
                                event.preventDefault();
                                closePresetEditor();
                        });
                });

                elements.modal.addEventListener('click', (event) => {
                        if (event.target === elements.modal) {
                                closePresetEditor();
                        }
                });
        }

        log('editor events bound');
}

async function initGrid(modularGridModule) {
        log('initGrid start');
        bindEditorEvents();

        const {
                AjaxAdapter,
                ColumnVisibilityPlugin,
                HeaderMenuPlugin,
                InfoPlugin,
                InfiniteScrollPlugin,
                ModularGrid,
                ResetPlugin,
                RowActionsPlugin,
                RowDetailPlugin,
                SearchPlugin
        } = modularGridModule;

        log('selected exports', {
                AjaxAdapter: !!AjaxAdapter,
                ColumnVisibilityPlugin: !!ColumnVisibilityPlugin,
                HeaderMenuPlugin: !!HeaderMenuPlugin,
                InfoPlugin: !!InfoPlugin,
                InfiniteScrollPlugin: !!InfiniteScrollPlugin,
                ModularGrid: !!ModularGrid,
                ResetPlugin: !!ResetPlugin,
                RowActionsPlugin: !!RowActionsPlugin,
                RowDetailPlugin: !!RowDetailPlugin,
                SearchPlugin: !!SearchPlugin
        });

        if (!AjaxAdapter || !ModularGrid) {
                throw new Error('ModularGrid module was loaded, but AjaxAdapter or ModularGrid export is missing.');
        }

        const adapter = new AjaxAdapter({
                url: ENDPOINT_URL,
                method: 'POST',
                rowsPath: 'data',
                totalPath: 'total',
                mapRequest(request) {
                        const sortKey = request.sortKey || 'preset_id';
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
                                filters: {}
                        };

                        log('mapRequest payload', payload);

                        return payload;
                }
        });

        log('adapter created');

        const grid = new ModularGrid(GRID_SELECTOR, {
                layout,
                adapter,
                dataMode: 'server',
                server: {
                        searchDebounceMs: 220,
                        watchStateKeys: ['query']
                },
                features: {
                        paging: false
                },
                pageSize: BATCH_SIZE,
                sort: {
                        key: 'preset_id',
                        direction: 'asc'
                },
                plugins: [
                        SearchPlugin,
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
                                zone: 'topLine',
                                order: 10,
                                label: 'Search',
                                placeholder: 'Search preset id, label or type'
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
                                zone: 'topLine',
                                order: 20,
                                label: 'Reset',
                                sections: ['query', 'columns', 'detailView']
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
                                                key: 'edit-preset',
                                                label: 'Edit preset',
                                                onClick(context) {
                                                        openEditorFromRow(context && context.row ? context.row : null);
                                                }
                                        },
                                        {
                                                key: 'duplicate-preset',
                                                label: 'Duplicate preset',
                                                onClick(context) {
                                                        openDuplicateEditorFromRow(context && context.row ? context.row : null);
                                                }
                                        },
                                        {
                                                key: 'delete-preset',
                                                label: 'Delete preset',
                                                onClick(context) {
                                                        deletePresetFromRow(context && context.row ? context.row : null);
                                                }
                                        }
                                ]
                        },
                        rowDetail: {
                                rowIdKey: 'preset_id',
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
                                                log('row detail render', context && context.payload ? context.payload.preset_id || context.payload.id : null);
                                                return renderPresetDetail(context);
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
                                key: 'preset_id',
                                label: 'Preset',
                                width: 300,
                                headerMenu: {
                                        defaultSortKey: 'preset_id',
                                        defaultSortDirection: 'asc',
                                        sortOptions: [
                                                { key: 'preset_id', label: 'Preset ID' },
                                                { key: 'label', label: 'Label' }
                                        ]
                                },
                                render(value, row) {
                                        return renderPreset(value, row);
                                }
                        },
                        {
                                key: 'type',
                                label: 'Type',
                                width: 300,
                                headerMenu: {
                                        defaultSortKey: 'type',
                                        defaultSortDirection: 'asc',
                                        sortOptions: [
                                                { key: 'type', label: 'Type' },
                                                { key: 'category', label: 'Category' }
                                        ]
                                },
                                render(value, row) {
                                        return renderType(value, row);
                                }
                        },
                        {
                                key: 'capability_text',
                                label: 'Capabilities',
                                width: 180,
                                render(value) {
                                        return renderPills(value);
                                }
                        },
                        {
                                key: 'enabled_label',
                                label: 'Enabled',
                                width: 120,
                                visible: true,
                                render(value) {
                                        return renderPills(value);
                                }
                        },
                        {
                                key: 'status',
                                label: 'Status',
                                width: 160,
                                headerMenu: {
                                        defaultSortKey: 'status',
                                        defaultSortDirection: 'asc',
                                        sortOptions: [
                                                { key: 'status', label: 'Status' }
                                        ]
                                }
                        },
                        {
                                key: 'category',
                                label: 'Category',
                                width: 160,
                                visible: false
                        },
                        {
                                key: 'risk',
                                label: 'Risk',
                                width: 220,
                                visible: false,
                                textDisplay: {
                                        strategy: 'clamp',
                                        lines: 2,
                                        expandable: true
                                }
                        },
                        {
                                key: 'description',
                                label: 'Description',
                                width: 380,
                                visible: false,
                                textDisplay: {
                                        strategy: 'clamp',
                                        lines: 3,
                                        expandable: true
                                }
                        },
                        {
                                key: 'config_count',
                                label: 'Config',
                                width: 110,
                                visible: false
                        },
                        {
                                key: 'dock_count',
                                label: 'Docks',
                                width: 110,
                                visible: false
                        }
                ]
        });

        log('grid created');

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

        log('grid.init start');
        await grid.init();
        log('grid.init finished');
        setLog('Agent Component Preset Admin loaded. Column visibility and infinite scroll are enabled.');
}

(async function() {
        const root = document.querySelector(GRID_SELECTOR);

        log('bootstrap start', {
                rootFound: !!root,
                initialized: root ? root.dataset.initialized || '' : null,
                endpoint: ENDPOINT_URL,
                modularGridUrl: MODULARGRID_URL
        });

        if (!root || root.dataset.initialized === '1') {
                return;
        }

        root.dataset.initialized = '1';
        setStartupStatus('Loading ModularGrid module.');

        try {
                const modularGridModule = await importFirst(MODULARGRID_URL, 'ModularGrid');
                setStartupStatus('Initializing preset grid.');
                await initGrid(modularGridModule);
        } catch (error) {
                const message = error && error.message ? error.message : String(error);
                setStartupStatus('Agent Component Preset Admin could not be initialized.', message, true);
                setLog('Initialization failed: ' + message);
                console.error(error);
        }
})();
</script>
