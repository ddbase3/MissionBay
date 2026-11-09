// Run with: node test/Display/AgentAdminSaveTransportTest.js
// Exercises the actual editor save function without a browser or ILIAS runtime.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const template = fs.readFileSync(new URL('../../tpl/Display/AgentAdminDisplay.php', import.meta.url), 'utf8');
const begin = template.indexOf('        async function saveEditorPayload() {');
const end = template.indexOf('        async function deleteAgentById(', begin);
assert.ok(begin >= 0 && end > begin, 'save handler must be present');
const saveFunction = template.slice(begin, end);

async function runScenario(serverBody, options = {}) {
    const calls = { requests: [], statuses: [], closes: 0, refreshes: 0, waited: 0 };
    const form = {
        reportValidity: () => options.valid !== false,
        __base3AgentResourcesFileManager: {
            async waitForUploads() { calls.waited++; }
        }
    };
    const context = {
        FormData,
        URLSearchParams,
        savingAgent: false,
        ENDPOINT_URL: '/base3-agent-admin.json',
        getEditorElements: () => ({ form }),
        validateEditorForm: () => 'agent-uuid',
        getFormFieldValue: () => 'agent-uuid',
        prepareConfigFormData: () => {
            const data = new FormData();
            data.set('mode', 'save');
            data.set('agent_id', 'agent-uuid');
            data.set('old_id', 'agent-uuid');
            data.append('tool_profiles[]', 'tools-a');
            data.append('tool_profiles[]', 'tools-b');
            data.append('capability_sources[tools][]', 'tool-1');
            data.set('chatmodel', 'model-1');
            data.set('system_prompt', 'Hello & welcome');
            if (options.file) {
                data.set('unexpected_file', new Blob(['file']), 'file.txt');
            }
            return data;
        },
        setEditorStatus: (msg, type) => calls.statuses.push({ msg, type }),
        setLog: () => undefined,
        log: () => undefined,
        mbText: (key, fallback) => fallback,
        getText: (text) => String(text),
        closeEditor: () => calls.closes++,
        refreshGrid: async () => { calls.refreshes++; },
        fetch: async (url, init) => {
            calls.requests.push({ url, init });
            return { ok: true, json: async () => serverBody };
        }
    };
    vm.runInNewContext(saveFunction + '; globalThis.runSave = saveEditorPayload;', context);
    if (options.concurrent) {
        await Promise.all([context.runSave(), context.runSave()]);
    } else {
        await context.runSave();
    }
    return calls;
}

const ack = { ok: true, mode: 'save', action: 'saved', record: { agent_id: 'agent-uuid' } };
const saved = await runScenario(ack);
assert.equal(saved.closes, 1, 'exact save acknowledgement closes the editor');
assert.equal(saved.refreshes, 1);
assert.equal(saved.waited, 1);
assert.equal(saved.requests.length, 1);
assert.equal(saved.requests[0].init.method, 'POST');
assert.equal(saved.requests[0].init.credentials, 'same-origin');
assert.equal(saved.requests[0].init.headers['X-Requested-With'], 'XMLHttpRequest');
assert.equal(saved.requests[0].init.headers['Content-Type'], undefined, 'browser sets URL-encoded content type');
assert.ok(saved.requests[0].init.body instanceof URLSearchParams);
assert.deepEqual(saved.requests[0].init.body.getAll('tool_profiles[]'), ['tools-a', 'tools-b']);
assert.equal(saved.requests[0].init.body.get('capability_sources[tools][]'), 'tool-1');
assert.equal(saved.requests[0].init.body.get('system_prompt'), 'Hello & welcome');

const concurrent = await runScenario(ack, { concurrent: true });
assert.equal(concurrent.requests.length, 1, 'concurrent submit and button actions send one save');
assert.equal(concurrent.closes, 1);

const mismatchedMode = await runScenario({ ok: true, mode: 'page', data: [] });
assert.equal(mismatchedMode.closes, 0, 'a grid response is not a save acknowledgement');
assert.equal(mismatchedMode.refreshes, 0);
assert.ok(mismatchedMode.statuses.some(({ type }) => type === 'error'));

const mismatchedId = await runScenario({ ok: true, mode: 'save', action: 'saved', record: { agent_id: 'other-agent' } });
assert.equal(mismatchedId.closes, 0, 'a different agent identity cannot acknowledge this save');
assert.ok(mismatchedId.statuses.some(({ type }) => type === 'error'));

const denied = await runScenario({ ok: false, mode: 'save', error: 'Validation failed' });
assert.equal(denied.closes, 0, 'server validation failures keep the editor open');
assert.ok(denied.statuses.some(({ msg }) => msg === 'Validation failed'));

const invalid = await runScenario(ack, { valid: false });
assert.equal(invalid.closes, 0);
assert.equal(invalid.requests.length, 0, 'native required fields are validated before AJAX');

const unexpectedFile = await runScenario(ack, { file: true });
assert.equal(unexpectedFile.closes, 0);
assert.equal(unexpectedFile.requests.length, 0, 'file payloads must not reach the settings endpoint');
assert.ok(unexpectedFile.statuses.some(({ type }) => type === 'error'));

assert.match(template, /<form id="base3_agent_admin_editor_form" method="post" novalidate>/);
assert.match(template, /event\.stopImmediatePropagation\(\);\s*void saveEditorPayload\(\);\s*\}, true\)/);
console.log('OK: agent save transport and acknowledgement checks (7 scenarios)');
