<?php
$this->loadBricks('Administration');
$mbUiText = is_array($this->_['bricks']['missionbay_admin'] ?? null) ? $this->_['bricks']['missionbay_admin'] : [];
$mbText = static fn(string $key, string $fallback): string => trim((string)($mbUiText[$key] ?? '')) !== '' ? (string)$mbUiText[$key] : $fallback;
$mbTextEsc = static fn(string $key, string $fallback): string => htmlspecialchars($mbText($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php
$service = (string)$this->_['service'];
$instanceId = 'retrieval_collections_' . str_replace('.', '_', uniqid('', true));
?>

<div id="<?php echo htmlspecialchars($instanceId, ENT_QUOTES); ?>" class="rc-admin">
	<div class="rc-header">
		<div>
			<h3><?php echo $mbTextEsc('retrieval_collections', 'Retrieval Collections'); ?></h3>
			<p><?php echo $mbTextEsc('logical_collection_keys_stay_stable_while_backend_collection_names_can_be_changed_or_versioned_independently', 'Logical collection keys stay stable while backend collection names can be changed or versioned independently.'); ?></p>
		</div>
		<button type="button" data-role="new"><?php echo $mbTextEsc('new_mapping', 'New mapping'); ?></button>
	</div>

	<div class="rc-status" data-role="status"><?php echo $mbTextEsc('loading', 'Loading...'); ?></div>

	<div class="rc-layout">
		<div class="rc-list" data-role="list"></div>
		<div class="rc-editor">
			<input type="hidden" data-role="old-key" value="">
			<label>
				<span><?php echo $mbTextEsc('collection_key', 'Collection key'); ?></span>
				<input type="text" data-role="collection-key" placeholder="ilias">
			</label>
			<label>
				<span><?php echo $mbTextEsc('backend_collection', 'Backend collection'); ?></span>
				<input type="text" data-role="backend-collection" placeholder="reporting10_ilias_v2">
			</label>
			<div class="rc-actions">
				<button type="button" data-role="save"><?php echo $mbTextEsc('save_mapping', 'Save mapping'); ?></button>
				<button type="button" data-role="remove" class="danger"><?php echo $mbTextEsc('remove_mapping', 'Remove mapping'); ?></button>
			</div>

			<hr>
			<div class="rc-runtime">
				<div><span><?php echo $mbTextEsc('backend_status', 'Backend status'); ?></span><strong data-role="backend-status">-</strong></div>
				<div><span><?php echo $mbTextEsc('active_in_orchestrator', 'Active in orchestrator'); ?></span><strong data-role="active-status">-</strong></div>
			</div>
			<div class="rc-actions">
				<button type="button" data-role="refresh"><?php echo $mbTextEsc('refresh_backend_info', 'Refresh backend info'); ?></button>
				<button type="button" data-role="create"><?php echo $mbTextEsc('create_backend_collection', 'Create backend collection'); ?></button>
				<button type="button" data-role="delete" class="danger"><?php echo $mbTextEsc('delete_backend_collection', 'Delete backend collection'); ?></button>
			</div>
			<pre data-role="info"><?php echo $mbTextEsc('select_a_mapping', 'Select a mapping.'); ?></pre>
		</div>
	</div>
</div>

<style>
.rc-admin { max-width: 1180px; background: #fff; border: 1px solid #d6d6d6; border-radius: 6px; padding: 16px; color: #333; }
.rc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 12px; }
.rc-header h3 { margin: 0 0 5px; }
.rc-header p { margin: 0; color: #666; }
.rc-status { margin-bottom: 14px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; background: #fafafa; }
.rc-status.error { border-color: #d88; background: #fff5f5; color: #933; }
.rc-status.success { border-color: #8d8; background: #f6fff6; color: #373; }
.rc-layout { display: grid; grid-template-columns: minmax(260px, 0.8fr) minmax(420px, 1.6fr); gap: 16px; }
.rc-list { border: 1px solid #ddd; border-radius: 5px; overflow: hidden; min-height: 160px; }
.rc-row { width: 100%; display: block; text-align: left; border: 0; border-bottom: 1px solid #eee; background: #fff; padding: 10px 12px; cursor: pointer; }
.rc-row:last-child { border-bottom: 0; }
.rc-row:hover, .rc-row.active { background: #f4f7fa; }
.rc-row strong, .rc-row span { display: block; }
.rc-row span { margin-top: 3px; color: #777; font-family: Consolas, monospace; font-size: 12px; overflow-wrap: anywhere; }
.rc-empty { padding: 18px; color: #777; }
.rc-editor { border: 1px solid #ddd; border-radius: 5px; padding: 14px; }
.rc-editor label { display: grid; gap: 5px; margin-bottom: 12px; }
.rc-editor label span, .rc-runtime span { font-size: 12px; font-weight: 600; color: #555; }
.rc-editor input { min-height: 36px; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; }
.rc-actions { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
.rc-admin button { padding: 7px 12px; border: 1px solid #bbb; border-radius: 4px; background: #f1f1f1; cursor: pointer; }
.rc-admin button.danger { border-color: #c99; color: #922; background: #fff8f8; }
.rc-runtime { display: flex; gap: 24px; flex-wrap: wrap; margin: 12px 0; }
.rc-runtime div { display: grid; gap: 3px; }
.rc-editor pre { max-height: 340px; overflow: auto; background: #fafafa; border: 1px solid #eee; border-radius: 4px; padding: 10px; font-size: 12px; }
@media (max-width: 850px) { .rc-layout { grid-template-columns: 1fr; } }
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
	let rows = [];
	let orchestrator = {};

	function node(role) { return root.querySelector('[data-role="' + role + '"]'); }
	function status(message, type = '') {
		const el = node('status');
		el.className = 'rc-status' + (type ? ' ' + type : '');
		el.textContent = message;
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
	function currentKey() { return node('old-key').value || node('collection-key').value.trim(); }
	function renderList() {
		const list = node('list');
		list.innerHTML = '';
		if (!rows.length) {
			const empty = document.createElement('div');
			empty.className = 'rc-empty';
			empty.textContent = mbText('no_collection_mappings_configured', 'No collection mappings configured.');
			list.appendChild(empty);
			return;
		}
		rows.forEach((row) => {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'rc-row' + (row.key === node('old-key').value ? ' active' : '');
			const key = document.createElement('strong');
			key.textContent = row.key;
			const backend = document.createElement('span');
			backend.textContent = row.backend_collection;
			button.append(key, backend);
			button.addEventListener('click', () => selectRow(row));
			list.appendChild(button);
		});
	}
	function selectRow(row) {
		node('old-key').value = row.key;
		node('collection-key').value = row.key;
		node('backend-collection').value = row.backend_collection;
		node('active-status').textContent = orchestrator.collection_key === row.key ? 'yes' : 'no';
		node('backend-status').textContent = mbText('unknown', 'Unknown');
		node('info').textContent = mbText('backend_info_not_loaded', 'Backend info not loaded.');
		renderList();
		refreshInfo();
	}
	function clearEditor() {
		node('old-key').value = '';
		node('collection-key').value = '';
		node('backend-collection').value = '';
		node('active-status').textContent = mbText('no_label', 'No');
		node('backend-status').textContent = '-';
		node('info').textContent = mbText('enter_a_collection_key_and_backend_name', 'Enter a collection key and backend name.');
		renderList();
	}
	async function load(preferredKey = '') {
		status(mbText('loading', 'Loading...'));
		try {
			const data = await post({ action: 'bootstrap' });
			if (!data.ok) throw new Error(data.error || 'Unable to load collections.');
			rows = Array.isArray(data.collections) ? data.collections : [];
			orchestrator = data.orchestrator || {};
			renderList();
			const key = preferredKey || node('old-key').value || orchestrator.collection_key || '';
			const row = rows.find((item) => item.key === key) || rows[0];
			if (row) selectRow(row); else clearEditor();
			status(mbText('collection_mappings_loaded', 'Collection mappings loaded.'));
		} catch (error) { status(error.message || String(error), 'error'); }
	}
	async function save() {
		status(mbText('saving', 'Saving…'));
		try {
			const data = await post({
				action: 'save',
				old_key: node('old-key').value,
				collection_key: node('collection-key').value,
				backend_collection: node('backend-collection').value
			});
			if (!data.ok) throw new Error(data.error || 'Unable to save mapping.');
			const key = node('collection-key').value.trim().toLowerCase();
			status(data.message || mbText('saved', 'Saved.'), 'success');
			await load(key);
		} catch (error) { status(error.message || String(error), 'error'); }
	}
	async function removeMapping() {
		const key = currentKey();
		if (!key || !confirm(mbText('remove_collection_mapping_confirm', 'Remove logical collection mapping \"{key}\"? The backend collection will remain untouched.', {key}))) return;
		status(mbText('removing_mapping', 'Removing mapping…'));
		try {
			const data = await post({ action: 'remove', collection_key: key });
			if (!data.ok) throw new Error(data.error || 'Unable to remove mapping.');
			status(data.message || mbText('removed', 'Removed.'), 'success');
			node('old-key').value = '';
			await load();
		} catch (error) { status(error.message || String(error), 'error'); }
	}
	async function refreshInfo() {
		const key = currentKey();
		if (!key || !rows.some((row) => row.key === key)) return;
		node('backend-status').textContent = mbText('checking', 'Checking...');
		try {
			const data = await post({ action: 'info', collection_key: key });
			if (!data.ok) throw new Error(data.error || 'Unable to load backend info.');
			node('backend-status').textContent = data.exists ? 'exists' : 'missing';
			node('info').textContent = data.info ? JSON.stringify(data.info, null, 2) : (data.note || 'Collection does not exist.');
		} catch (error) {
			node('backend-status').textContent = mbText('error', 'Error');
			node('info').textContent = error.message || String(error);
		}
	}
	async function backendAction(action) {
		const key = currentKey();
		if (!key || !rows.some((row) => row.key === key)) {
			status(mbText('save_the_collection_mapping_first', 'Save the collection mapping first.'), 'error');
			return;
		}
		if (action === 'delete' && !confirm(mbText('delete_backend_collection_confirm', 'Delete the physical backend collection for \"{key}\" and all contained vectors?', {key}))) return;
		status(action === 'create' ? mbText('creating_backend_collection', 'Creating backend collection…') : mbText('deleting_backend_collection', 'Deleting backend collection…'));
		try {
			const data = await post({ action, collection_key: key });
			if (!data.ok) throw new Error(data.error || 'Backend operation failed.');
			status(data.message || mbText('done', 'Done.'), 'success');
			await refreshInfo();
		} catch (error) { status(error.message || String(error), 'error'); }
	}

	node('new').addEventListener('click', clearEditor);
	node('save').addEventListener('click', save);
	node('remove').addEventListener('click', removeMapping);
	node('refresh').addEventListener('click', refreshInfo);
	node('create').addEventListener('click', () => backendAction('create'));
	node('delete').addEventListener('click', () => backendAction('delete'));
	load();
})();
</script>
