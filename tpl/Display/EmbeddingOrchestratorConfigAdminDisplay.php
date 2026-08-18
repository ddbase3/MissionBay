<?php
$this->loadBricks('Administration');
$mbUiText = is_array($this->_['bricks']['missionbay_admin'] ?? null) ? $this->_['bricks']['missionbay_admin'] : [];
$mbText = static fn(string $key, string $fallback): string => trim((string)($mbUiText[$key] ?? '')) !== '' ? (string)$mbUiText[$key] : $fallback;
$mbTextEsc = static fn(string $key, string $fallback): string => htmlspecialchars($mbText($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php
$service = (string)$this->_['service'];
$instanceId = 'embedding_orchestrator_' . str_replace('.', '_', uniqid('', true));
?>

<div id="<?php echo htmlspecialchars($instanceId, ENT_QUOTES); ?>" class="embedding-orchestrator-config">
	<h3><?php echo $mbTextEsc('embedding_orchestrator', 'Embedding Orchestrator'); ?></h3>
	<p class="eoc-description"><?php echo $mbTextEsc('select_the_reusable_missionbay_resources_used_by_the_indexing_flow_collection_names_are_managed_separately_thr', 'Select the reusable MissionBay resources used by the indexing flow. Collection names are managed separately through the Collections tab.'); ?></p>

	<div class="eoc-status" data-role="status"><?php echo $mbTextEsc('loading', 'Loading...'); ?></div>

	<div class="eoc-form">
		<label>
			<span><?php echo $mbTextEsc('embedding_resource_preset', 'Embedding resource preset'); ?></span>
			<select data-role="embedding-preset"></select>
		</label>

		<label>
			<span><?php echo $mbTextEsc('vector_store_resource_preset', 'Vector-store resource preset'); ?></span>
			<select data-role="vector-preset"></select>
		</label>

		<label>
			<span><?php echo $mbTextEsc('collection_key', 'Collection key'); ?></span>
			<select data-role="collection-key"></select>
		</label>

		<div class="eoc-readonly">
			<span><?php echo $mbTextEsc('backend_collection', 'Backend collection'); ?></span>
			<strong data-role="backend-collection">-</strong>
		</div>
	</div>

	<div class="eoc-actions">
		<button type="button" data-role="save"><?php echo $mbTextEsc('save', 'Save'); ?></button>
	</div>
</div>

<style>
.embedding-orchestrator-config {
	max-width: 980px;
	background: #fff;
	border: 1px solid #d6d6d6;
	border-radius: 6px;
	padding: 16px;
	color: #333;
}
.embedding-orchestrator-config h3 { margin: 0 0 6px; }
.eoc-description { margin: 0 0 14px; color: #666; line-height: 1.45; }
.eoc-status { margin-bottom: 14px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; background: #fafafa; }
.eoc-status.error { border-color: #d88; background: #fff5f5; color: #933; }
.eoc-status.success { border-color: #8d8; background: #f6fff6; color: #373; }
.eoc-form { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.eoc-form label, .eoc-readonly { display: grid; gap: 5px; }
.eoc-form label > span, .eoc-readonly > span { font-size: 12px; font-weight: 600; color: #555; }
.eoc-form select { min-height: 36px; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; background: #fff; }
.eoc-readonly { padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 4px; background: #fafafa; }
.eoc-readonly strong { font-family: Consolas, monospace; font-weight: 400; }
.eoc-actions { margin-top: 14px; }
.eoc-actions button { padding: 8px 16px; border: 1px solid #bbb; border-radius: 4px; background: #f0f0f0; cursor: pointer; }
@media (max-width: 760px) { .eoc-form { grid-template-columns: 1fr; } }
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

	const root = document.getElementById(<?php echo json_encode($instanceId); ?>);
	const endpoint = <?php echo json_encode($service); ?>;
	let collections = [];

	function node(role) { return root.querySelector('[data-role="' + role + '"]'); }
	function status(message, type = '') {
		const el = node('status');
		el.className = 'eoc-status' + (type ? ' ' + type : '');
		el.textContent = message;
	}
	function fillSelect(select, rows, selected, placeholder) {
		select.innerHTML = '';
		const empty = document.createElement('option');
		empty.value = '';
		empty.textContent = placeholder;
		select.appendChild(empty);
		rows.forEach((row) => {
			const option = document.createElement('option');
			option.value = row.id || row.key || '';
			option.textContent = row.label ? row.label + ' (' + row.id + ')' : row.key;
			option.selected = option.value === selected;
			select.appendChild(option);
		});
	}
	function updateBackend() {
		const key = node('collection-key').value;
		const row = collections.find((item) => item.key === key);
		node('backend-collection').textContent = row ? row.backend_collection : '-';
	}
	async function post(payload) {
		const response = await fetch(endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		});
		if (!response.ok) throw new Error('Request failed with status ' + response.status);
		return response.json();
	}
	async function load() {
		status(mbText('loading', 'Loading...'));
		try {
			const data = await post({ action: 'bootstrap' });
			if (!data.ok) throw new Error(data.error || 'Unable to load configuration.');
			collections = Array.isArray(data.collections) ? data.collections : [];
			const config = data.config || {};
			fillSelect(node('embedding-preset'), data.embedding_presets || [], config.embedding_preset || '', 'Select embedding preset…');
			fillSelect(node('vector-preset'), data.vector_store_presets || [], config.vector_store_preset || '', 'Select vector-store preset…');
			fillSelect(node('collection-key'), collections, config.collection_key || '', collections.length ? 'Select collection…' : 'Configure a collection first…');
			updateBackend();
			status(config.embedding_preset && config.vector_store_preset && config.collection_key ? mbText('configuration_loaded', 'Configuration loaded.') : mbText('not_configured_yet_select_all_three_values_and_save', 'Not configured yet. Select all three values and save.'));
		}
		catch (error) {
			status(error.message || String(error), 'error');
		}
	}
	async function save() {
		status(mbText('saving', 'Saving…'));
		try {
			const data = await post({
				action: 'save',
				embedding_preset: node('embedding-preset').value,
				vector_store_preset: node('vector-preset').value,
				collection_key: node('collection-key').value
			});
			if (!data.ok) throw new Error(data.error || 'Unable to save configuration.');
			status(data.message || mbText('saved', 'Saved.'), 'success');
			updateBackend();
		}
		catch (error) {
			status(error.message || String(error), 'error');
		}
	}

	node('collection-key').addEventListener('change', updateBackend);
	node('save').addEventListener('click', save);
	load();
})();
</script>
