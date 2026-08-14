<?php
$service = (string)$this->_['service'];
$instanceId = 'retrieval_points_' . str_replace('.', '_', uniqid('', true));
?>

<div id="<?php echo htmlspecialchars($instanceId, ENT_QUOTES); ?>" class="rvp-admin">
	<h3>Vector Points</h3>
	<p class="rvp-description">Inspect stored points through the configured retrieval index. The optional filter uses the backend-neutral retrieval filter structure.</p>
	<div class="rvp-status" data-role="status">Loading…</div>

	<div class="rvp-controls">
		<label><span>Collection</span><select data-role="collection"></select></label>
		<label><span>Limit</span><input data-role="limit" type="number" min="1" max="100" value="10"></label>
		<label class="wide"><span>Filter JSON (optional)</span><textarea data-role="filter" rows="5" placeholder='{"must":[{"field":"source_kind","operator":"eq","value":"wiki"}]}'></textarea></label>
		<label><span>Offset (optional)</span><input data-role="offset" type="text" value=""></label>
	</div>
	<div class="rvp-actions"><button type="button" data-role="inspect">Inspect points</button></div>
	<div class="rvp-meta"><span>Backend: <strong data-role="backend">–</strong></span><span>Next offset: <strong data-role="next-offset">–</strong></span></div>
	<div class="rvp-grid" data-role="points"></div>
</div>

<style>
.rvp-admin { max-width: 1180px; background: #fff; border: 1px solid #d6d6d6; border-radius: 6px; padding: 16px; color: #333; }
.rvp-admin h3 { margin: 0 0 5px; }
.rvp-description { color: #666; margin: 0 0 12px; }
.rvp-status { margin-bottom: 14px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; background: #fafafa; }
.rvp-status.error { border-color: #d88; background: #fff5f5; color: #933; }
.rvp-status.success { border-color: #8d8; background: #f6fff6; color: #373; }
.rvp-controls { display: grid; grid-template-columns: minmax(240px, 1fr) 120px; gap: 12px; }
.rvp-controls label { display: grid; gap: 5px; }
.rvp-controls label.wide { grid-column: 1 / -1; }
.rvp-controls span { font-size: 12px; font-weight: 600; color: #555; }
.rvp-controls select, .rvp-controls input, .rvp-controls textarea { padding: 7px 8px; border: 1px solid #ccc; border-radius: 4px; background: #fff; font-family: inherit; }
.rvp-controls textarea { font-family: Consolas, monospace; resize: vertical; }
.rvp-actions { margin: 12px 0; }
.rvp-actions button { padding: 8px 16px; border: 1px solid #bbb; border-radius: 4px; background: #f1f1f1; cursor: pointer; }
.rvp-meta { display: flex; gap: 24px; flex-wrap: wrap; color: #666; font-size: 12px; margin-bottom: 12px; }
.rvp-meta strong { font-family: Consolas, monospace; font-weight: 400; color: #333; }
.rvp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.rvp-card { min-width: 0; border: 1px solid #ddd; border-radius: 6px; padding: 12px; background: #fff; }
.rvp-card h4 { margin: 0 0 8px; font-family: Consolas, monospace; font-size: 13px; overflow-wrap: anywhere; }
.rvp-card details { margin-top: 8px; }
.rvp-card summary { cursor: pointer; font-weight: 600; font-size: 12px; }
.rvp-card pre { max-height: 300px; overflow: auto; background: #fafafa; border: 1px solid #eee; border-radius: 4px; padding: 8px; font-size: 11px; white-space: pre-wrap; overflow-wrap: anywhere; }
.rvp-empty { grid-column: 1 / -1; color: #777; padding: 16px 0; }
@media (max-width: 900px) { .rvp-grid { grid-template-columns: 1fr; } .rvp-controls { grid-template-columns: 1fr; } .rvp-controls label.wide { grid-column: auto; } }
</style>

<script>
(function() {
	const root = document.getElementById(<?php echo json_encode($instanceId); ?>);
	const endpoint = <?php echo json_encode($service); ?>;
	let collections = [];

	function node(role) { return root.querySelector('[data-role="' + role + '"]'); }
	function status(message, type = '') {
		const el = node('status');
		el.className = 'rvp-status' + (type ? ' ' + type : '');
		el.textContent = message;
	}
	async function post(payload) {
		const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
		if (!response.ok) throw new Error('Request failed with status ' + response.status);
		return response.json();
	}
	function updateBackend() {
		const row = collections.find((item) => item.key === node('collection').value);
		node('backend').textContent = row ? row.backend_collection : '–';
	}
	function parseFilter() {
		const value = node('filter').value.trim();
		if (!value) return null;
		const parsed = JSON.parse(value);
		if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') throw new Error('Filter JSON must be an object.');
		return parsed;
	}
	function renderPoints(points) {
		const grid = node('points');
		grid.innerHTML = '';
		if (!Array.isArray(points) || !points.length) {
			const empty = document.createElement('div');
			empty.className = 'rvp-empty';
			empty.textContent = 'No points returned.';
			grid.appendChild(empty);
			return;
		}
		points.forEach((point) => {
			const card = document.createElement('div');
			card.className = 'rvp-card';
			const title = document.createElement('h4');
			title.textContent = String(point.id ?? '(no id)');
			card.appendChild(title);
			[['Payload', point.payload ?? {}], ['Vector summary', point.vector_summary ?? point.vectors ?? {}]].forEach(([label, value]) => {
				const details = document.createElement('details');
				details.open = label === 'Payload';
				const summary = document.createElement('summary'); summary.textContent = label;
				const pre = document.createElement('pre'); pre.textContent = JSON.stringify(value, null, 2);
				details.append(summary, pre); card.appendChild(details);
			});
			grid.appendChild(card);
		});
	}
	async function load() {
		status('Loading…');
		try {
			const data = await post({ action: 'bootstrap' });
			if (!data.ok) throw new Error(data.error || 'Unable to load configuration.');
			collections = Array.isArray(data.collections) ? data.collections : [];
			const select = node('collection'); select.innerHTML = '';
			collections.forEach((row) => {
				const option = document.createElement('option'); option.value = row.key; option.textContent = row.key;
				option.selected = row.key === data.default_collection_key; select.appendChild(option);
			});
			updateBackend();
			status(collections.length ? 'Ready.' : 'No collection mapping configured.');
		} catch (error) { status(error.message || String(error), 'error'); }
	}
	async function inspect() {
		status('Loading points…');
		try {
			const data = await post({
				action: 'inspect', collection_key: node('collection').value,
				limit: Number(node('limit').value || 10), filter: parseFilter(), offset: node('offset').value.trim() || null
			});
			if (!data.ok) throw new Error(data.error || 'Unable to inspect points.');
			const result = data.result || {};
			node('backend').textContent = result.collection || node('backend').textContent;
			node('next-offset').textContent = result.next_offset ?? '–';
			renderPoints(result.points || []);
			status('Points loaded.', 'success');
		} catch (error) { status(error.message || String(error), 'error'); }
	}

	node('collection').addEventListener('change', updateBackend);
	node('inspect').addEventListener('click', inspect);
	load();
})();
</script>
