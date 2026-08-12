<?php
$service = (string)$this->_['service'];
$instanceId = 'retrieval_search_' . str_replace('.', '_', uniqid('', true));
?>

<div id="<?php echo htmlspecialchars($instanceId, ENT_QUOTES); ?>" class="retrieval-search-admin">
	<div class="retrieval-search-header">
		<div>
			<h1>Retrieval Search</h1>
			<p>Interactive workbench for configured MissionBay retrieval tools. Searches use the materialized preset including its configured resources and mandatory filters.</p>
		</div>
		<div class="retrieval-search-status" data-role="status">Loading…</div>
	</div>

	<div class="retrieval-search-card retrieval-search-form-card">
		<div class="retrieval-search-grid retrieval-search-grid-main">
			<label class="retrieval-search-field retrieval-search-field-wide">
				<span>Retrieval preset</span>
				<select data-role="preset"></select>
			</label>

			<label class="retrieval-search-field retrieval-search-field-wide">
				<span>Query</span>
				<input data-role="query" type="search" autocomplete="off" placeholder="Search query…" />
			</label>

			<label class="retrieval-search-field">
				<span>Mode</span>
				<select data-role="mode"></select>
			</label>

			<label class="retrieval-search-field">
				<span>Top K</span>
				<input data-role="top-k" type="number" min="1" value="5" />
			</label>
		</div>

		<details class="retrieval-search-advanced">
			<summary>Advanced search options</summary>
			<div class="retrieval-search-grid">
				<label class="retrieval-search-field retrieval-search-field-wide" data-option="phrases">
					<span>Phrases <small>one per line</small></span>
					<textarea data-role="phrases" rows="3" placeholder="exact or phonetic phrase"></textarea>
				</label>

				<label class="retrieval-search-field" data-option="required_terms">
					<span>Required terms <small>one per line</small></span>
					<textarea data-role="required-terms" rows="3"></textarea>
				</label>

				<label class="retrieval-search-field" data-option="excluded_terms">
					<span>Excluded terms <small>one per line</small></span>
					<textarea data-role="excluded-terms" rows="3"></textarea>
				</label>

				<label class="retrieval-search-field retrieval-search-field-wide" data-option="filters">
					<span>Filters <small>JSON, according to the selected tool schema</small></span>
					<textarea data-role="filters" rows="5" spellcheck="false" placeholder="[]"></textarea>
				</label>
			</div>

			<details class="retrieval-search-schema">
				<summary>Selected search function schema</summary>
				<pre data-role="schema"></pre>
			</details>
		</details>

		<div class="retrieval-search-actions">
			<button type="button" data-role="search">Search</button>
			<button type="button" class="retrieval-search-secondary" data-role="clear">Clear</button>
		</div>
	</div>

	<div class="retrieval-search-summary" data-role="summary" style="display:none"></div>
	<div class="retrieval-search-results" data-role="results"></div>

	<details class="retrieval-search-raw" data-role="raw-holder" style="display:none">
		<summary>Raw execution response</summary>
		<div class="retrieval-search-raw-actions">
			<button type="button" class="retrieval-search-secondary" data-role="copy-raw">Copy</button>
		</div>
		<pre data-role="raw"></pre>
	</details>
</div>

<style>
.retrieval-search-admin {
	max-width: 1380px;
	color: #333;
}

.retrieval-search-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 18px;
	margin-bottom: 14px;
}

.retrieval-search-header h1 {
	margin: 0 0 5px 0;
	font-size: 24px;
	font-weight: 600;
}

.retrieval-search-header p {
	max-width: 980px;
	margin: 0;
	color: #666;
	line-height: 1.45;
}

.retrieval-search-status {
	flex: 0 0 auto;
	padding: 5px 9px;
	border: 1px solid #d5d5d5;
	border-radius: 999px;
	background: #fafafa;
	font-size: 12px;
	white-space: nowrap;
}

.retrieval-search-status.is-error {
	border-color: #d8aaaa;
	background: #fff7f7;
	color: #842626;
}

.retrieval-search-card,
.retrieval-search-result,
.retrieval-search-summary,
.retrieval-search-raw {
	border: 1px solid #dedede;
	border-radius: 8px;
	background: #fff;
}

.retrieval-search-form-card {
	padding: 14px;
}

.retrieval-search-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 10px;
}

.retrieval-search-grid-main {
	grid-template-columns: minmax(280px, .8fr) minmax(160px, .3fr) minmax(110px, .2fr);
}

.retrieval-search-grid-main .retrieval-search-field-wide:first-child {
	grid-column: 1 / -1;
}

.retrieval-search-grid-main .retrieval-search-field-wide:nth-child(2) {
	grid-column: span 1;
}

.retrieval-search-field {
	display: grid;
	gap: 5px;
	min-width: 0;
}

.retrieval-search-field-wide {
	grid-column: 1 / -1;
}

.retrieval-search-field > span {
	font-size: 12px;
	font-weight: 600;
	color: #444;
}

.retrieval-search-field small {
	font-weight: 400;
	color: #777;
}

.retrieval-search-field input,
.retrieval-search-field select,
.retrieval-search-field textarea {
	box-sizing: border-box;
	width: 100%;
	min-height: 34px;
	padding: 6px 8px;
	border: 1px solid #cfcfcf;
	border-radius: 6px;
	background: #fff;
	font: inherit;
	font-size: 13px;
}

.retrieval-search-field textarea {
	resize: vertical;
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.retrieval-search-advanced,
.retrieval-search-schema,
.retrieval-search-raw {
	margin-top: 12px;
}

.retrieval-search-advanced > summary,
.retrieval-search-schema > summary,
.retrieval-search-raw > summary {
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
}

.retrieval-search-advanced[open] > summary {
	margin-bottom: 10px;
}

.retrieval-search-schema pre,
.retrieval-search-raw pre {
	margin: 8px 0 0 0;
	padding: 10px;
	border-radius: 6px;
	background: #f7f7f7;
	white-space: pre-wrap;
	overflow-wrap: anywhere;
	font-size: 12px;
}

.retrieval-search-actions,
.retrieval-search-raw-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 12px;
}

.retrieval-search-actions button,
.retrieval-search-result button,
.retrieval-search-raw-actions button {
	min-height: 31px;
	padding: 5px 11px;
	border: 1px solid #222;
	border-radius: 6px;
	background: #222;
	color: #fff;
	font-size: 12px;
	cursor: pointer;
}

.retrieval-search-actions button:hover,
.retrieval-search-result button:hover,
.retrieval-search-raw-actions button:hover {
	background: #444;
}

.retrieval-search-actions button:disabled,
.retrieval-search-result button:disabled {
	opacity: .55;
	cursor: default;
}

.retrieval-search-secondary {
	border-color: #cfcfcf !important;
	background: #fff !important;
	color: #333 !important;
}

.retrieval-search-secondary:hover {
	background: #f5f5f5 !important;
}

.retrieval-search-summary {
	margin-top: 12px;
	padding: 9px 11px;
	font-size: 13px;
	color: #555;
}

.retrieval-search-results {
	display: grid;
	gap: 10px;
	margin-top: 12px;
}

.retrieval-search-result {
	padding: 12px;
}

.retrieval-search-result-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
}

.retrieval-search-result-title {
	font-size: 15px;
	font-weight: 600;
	color: #222;
}

.retrieval-search-result-sub {
	margin-top: 3px;
	font-size: 11px;
	color: #777;
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
	overflow-wrap: anywhere;
}

.retrieval-search-score {
	flex: 0 0 auto;
	padding: 3px 7px;
	border: 1px solid #d6d6d6;
	border-radius: 999px;
	background: #fafafa;
	font-size: 11px;
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.retrieval-search-meta {
	display: flex;
	flex-wrap: wrap;
	gap: 5px;
	margin-top: 9px;
}

.retrieval-search-pill {
	padding: 2px 7px;
	border: 1px solid #dedede;
	border-radius: 999px;
	background: #fafafa;
	font-size: 11px;
	color: #555;
	max-width: 100%;
	overflow-wrap: anywhere;
}

.retrieval-search-text {
	margin-top: 10px;
	padding: 10px;
	border: 1px solid #e5e5e5;
	border-radius: 6px;
	background: #fafafa;
	white-space: pre-wrap;
	line-height: 1.45;
	font-size: 13px;
}

.retrieval-search-result-actions {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 6px;
	margin-top: 10px;
}

.retrieval-search-neighbor-count {
	width: 64px;
	min-height: 31px;
	padding: 4px 6px;
	border: 1px solid #cfcfcf;
	border-radius: 6px;
	background: #fff;
	font-size: 12px;
}

.retrieval-search-context {
	margin-top: 10px;
	padding-top: 10px;
	border-top: 1px solid #ededed;
}

.retrieval-search-empty,
.retrieval-search-error {
	padding: 14px;
	border: 1px solid #dedede;
	border-radius: 8px;
	background: #fff;
	color: #666;
}

.retrieval-search-error {
	border-color: #d8aaaa;
	background: #fff7f7;
	color: #842626;
}

@media (max-width: 900px) {
	.retrieval-search-header,
	.retrieval-search-result-head {
		flex-direction: column;
	}

	.retrieval-search-grid,
	.retrieval-search-grid-main {
		grid-template-columns: 1fr;
	}

	.retrieval-search-grid-main .retrieval-search-field-wide,
	.retrieval-search-grid-main .retrieval-search-field-wide:first-child,
	.retrieval-search-grid-main .retrieval-search-field-wide:nth-child(2),
	.retrieval-search-field-wide {
		grid-column: auto;
	}
}
</style>

<script>
(function() {
	const root = document.getElementById(<?php echo json_encode($instanceId); ?>);
	if (!root) return;

	const service = <?php echo json_encode($service); ?>;
	const state = {
		presets: [],
		preset: null,
		lastResponse: null
	};

	const el = role => root.querySelector('[data-role="' + role + '"]');
	const status = el('status');
	const presetSelect = el('preset');
	const queryInput = el('query');
	const modeSelect = el('mode');
	const topKInput = el('top-k');
	const phrasesInput = el('phrases');
	const requiredInput = el('required-terms');
	const excludedInput = el('excluded-terms');
	const filtersInput = el('filters');
	const schemaPre = el('schema');
	const results = el('results');
	const summary = el('summary');
	const rawHolder = el('raw-holder');
	const rawPre = el('raw');
	const searchButton = el('search');

	function setStatus(text, error) {
		status.textContent = text || '–';
		status.classList.toggle('is-error', !!error);
	}

	function escapeHtml(value) {
		return String(value ?? '').replace(/[&<>"']/g, char => ({
			'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
		}[char]));
	}

	function pretty(value) {
		try {
			return JSON.stringify(value, null, 2);
		}
		catch (_) {
			return String(value ?? '');
		}
	}

	function lines(value) {
		return String(value ?? '')
			.split(/\r?\n/)
			.map(item => item.trim())
			.filter(Boolean);
	}

	function functionShape(definition) {
		if (!definition || typeof definition !== 'object') return {};
		return definition.function && typeof definition.function === 'object'
			? definition.function
			: definition;
	}

	function propertiesForPreset(preset) {
		const fn = functionShape(preset && preset.search_definition);
		const params = fn.parameters && typeof fn.parameters === 'object' ? fn.parameters : {};
		return params.properties && typeof params.properties === 'object' ? params.properties : {};
	}

	function setOptionVisibility(name, visible) {
		const holder = root.querySelector('[data-option="' + name + '"]');
		if (holder) holder.style.display = visible ? '' : 'none';
	}

	function updatePresetUi() {
		state.preset = state.presets.find(item => item.id === presetSelect.value) || null;
		const props = propertiesForPreset(state.preset);
		const modeSchema = props.mode && typeof props.mode === 'object' ? props.mode : {};
		const modes = Array.isArray(modeSchema.enum) && modeSchema.enum.length
			? modeSchema.enum
			: ['auto'];

		modeSelect.innerHTML = '';
		for (const mode of modes) {
			const option = document.createElement('option');
			option.value = String(mode);
			option.textContent = String(mode);
			modeSelect.appendChild(option);
		}
		if (modes.includes('auto')) modeSelect.value = 'auto';

		modeSelect.closest('.retrieval-search-field').style.display = props.mode ? '' : 'none';
		topKInput.closest('.retrieval-search-field').style.display = props.top_k ? '' : 'none';
		setOptionVisibility('phrases', !!props.phrases);
		setOptionVisibility('required_terms', !!props.required_terms);
		setOptionVisibility('excluded_terms', !!props.excluded_terms);
		setOptionVisibility('filters', !!props.filters);

		if (props.top_k && Number.isFinite(Number(props.top_k.maximum))) {
			topKInput.max = String(props.top_k.maximum);
		}
		if (props.top_k && Number.isFinite(Number(props.top_k.minimum))) {
			topKInput.min = String(props.top_k.minimum);
		}

		schemaPre.textContent = pretty(state.preset ? state.preset.search_definition : {});
	}

	async function request(payload) {
		const response = await fetch(service, {
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(payload)
		});

		const text = await response.text();
		let data;
		try {
			data = JSON.parse(text);
		}
		catch (_) {
			throw new Error(text || 'Invalid JSON response.');
		}

		if (!response.ok) {
			throw new Error(data.error || ('HTTP ' + response.status));
		}

		return data;
	}

	function parseFilters() {
		const text = filtersInput.value.trim();
		if (!text) return null;

		try {
			return JSON.parse(text);
		}
		catch (error) {
			throw new Error('Filters are not valid JSON: ' + error.message);
		}
	}

	function buildArguments() {
		const props = propertiesForPreset(state.preset);
		const args = {};
		const query = queryInput.value.trim();

		if (props.query) args.query = query;
		if (props.mode && modeSelect.value) args.mode = modeSelect.value;
		if (props.top_k) args.top_k = Math.max(1, Number.parseInt(topKInput.value || '5', 10) || 5);

		const phrases = lines(phrasesInput.value);
		const requiredTerms = lines(requiredInput.value);
		const excludedTerms = lines(excludedInput.value);
		const filters = parseFilters();

		if (props.phrases && phrases.length) args.phrases = phrases;
		if (props.required_terms && requiredTerms.length) args.required_terms = requiredTerms;
		if (props.excluded_terms && excludedTerms.length) args.excluded_terms = excludedTerms;
		if (props.filters && filters !== null) args.filters = filters;

		return args;
	}

	function outputFromResponse(response) {
		return response && typeof response.output === 'object' && response.output !== null
			? response.output
			: {};
	}

	function renderRaw(response) {
		state.lastResponse = response;
		rawHolder.style.display = '';
		rawPre.textContent = pretty(response);
	}

	function scalarMeta(context) {
		const meta = [];
		for (const [key, value] of Object.entries(context || {})) {
			if (key === 'text') continue;
			if (value === null || value === undefined || value === '') continue;
			if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
				meta.push([key, value]);
			}
		}
		return meta;
	}

	function resultCard(item, index) {
		const context = item && typeof item.context === 'object' && item.context !== null ? item.context : {};
		const title = context.title || ('Result ' + (index + 1));
		const ref = String(item.retrieval_ref || '');
		const score = item.score === undefined || item.score === null ? '' : String(item.score);
		const card = document.createElement('div');
		card.className = 'retrieval-search-result';

		const pills = scalarMeta(context).map(([key, value]) =>
			'<span class="retrieval-search-pill"><strong>' + escapeHtml(key) + ':</strong> ' + escapeHtml(value) + '</span>'
		).join('');

		card.innerHTML =
			'<div class="retrieval-search-result-head">' +
				'<div>' +
					'<div class="retrieval-search-result-title">' + escapeHtml(title) + '</div>' +
					'<div class="retrieval-search-result-sub">' + escapeHtml(ref) + '</div>' +
				'</div>' +
				(score ? '<div class="retrieval-search-score">score ' + escapeHtml(score) + '</div>' : '') +
			'</div>' +
			(pills ? '<div class="retrieval-search-meta">' + pills + '</div>' : '') +
			(context.text ? '<div class="retrieval-search-text">' + escapeHtml(context.text) + '</div>' : '') +
			'<div class="retrieval-search-result-actions">' +
				'<button type="button" class="retrieval-search-secondary" data-action="copy">Copy</button>' +
				(state.preset && state.preset.has_context && ref
					? '<span>before</span><input class="retrieval-search-neighbor-count" data-role-local="before" type="number" min="0" max="20" value="1" />' +
					  '<span>after</span><input class="retrieval-search-neighbor-count" data-role-local="after" type="number" min="0" max="20" value="1" />' +
					  '<button type="button" data-action="context">Load context</button>'
					: '') +
			'</div>' +
			'<div class="retrieval-search-context" data-role-local="context" style="display:none"></div>';

		const copyButton = card.querySelector('[data-action="copy"]');
		copyButton.addEventListener('click', async () => {
			const data = {
				retrieval_ref: item.retrieval_ref || '',
				score: item.score ?? null,
				context
			};
			await navigator.clipboard.writeText(pretty(data));
			const original = copyButton.textContent;
			copyButton.textContent = 'Copied';
			setTimeout(() => { copyButton.textContent = original; }, 900);
		});

		const contextButton = card.querySelector('[data-action="context"]');
		if (contextButton) {
			contextButton.addEventListener('click', async () => {
				const holder = card.querySelector('[data-role-local="context"]');
				const before = Math.max(0, Number.parseInt(card.querySelector('[data-role-local="before"]').value || '1', 10) || 0);
				const after = Math.max(0, Number.parseInt(card.querySelector('[data-role-local="after"]').value || '1', 10) || 0);
				contextButton.disabled = true;
				holder.style.display = '';
				holder.innerHTML = '<div class="retrieval-search-empty">Loading context…</div>';

				try {
					const response = await request({
						action: 'context',
						preset_id: presetSelect.value,
						arguments: {
							retrieval_ref: ref,
							before,
							after
						}
					});
					const output = outputFromResponse(response);
					const contextResults = Array.isArray(output.chunks) ? output.chunks : [];
					if (!response.ok) {
						holder.innerHTML = '<div class="retrieval-search-error">' + escapeHtml(response.error || 'Context lookup failed.') + '</div>';
					}
					else if (!contextResults.length) {
						holder.innerHTML = '<div class="retrieval-search-empty">No context chunks.</div>';
					}
					else {
						holder.innerHTML = '';
						for (const neighbor of contextResults) {
							const neighborContext = neighbor && typeof neighbor.context === 'object' && neighbor.context !== null
								? neighbor.context
								: neighbor;
							const block = document.createElement('div');
							block.className = 'retrieval-search-text';
							block.textContent = neighborContext && neighborContext.text
								? neighborContext.text
								: pretty(neighborContext);
							holder.appendChild(block);
						}
					}
					renderRaw(response);
				}
				catch (error) {
					holder.innerHTML = '<div class="retrieval-search-error">' + escapeHtml(error.message) + '</div>';
				}
				finally {
					contextButton.disabled = false;
				}
			});
		}

		return card;
	}

	function renderSearch(response) {
		results.innerHTML = '';
		renderRaw(response);
		const output = outputFromResponse(response);
		const items = Array.isArray(output.results) ? output.results : [];
		const channels = Array.isArray(output.channels) ? output.channels : [];
		const mode = output.mode || (response.arguments && response.arguments.mode) || '';

		summary.style.display = '';
		summary.innerHTML =
			'<strong>' + items.length + ' result(s)</strong>' +
			(mode ? ' · mode ' + escapeHtml(mode) : '') +
			(channels.length ? ' · channels ' + escapeHtml(channels.join(', ')) : '');

		if (!response.ok) {
			results.innerHTML = '<div class="retrieval-search-error">' + escapeHtml(
				(response.execution && response.execution.error) || 'Search failed.'
			) + '</div>';
			return;
		}

		if (!items.length) {
			results.innerHTML = '<div class="retrieval-search-empty">No results.</div>';
			return;
		}

		items.forEach((item, index) => results.appendChild(resultCard(item, index)));
	}

	async function runSearch() {
		if (!state.preset) return;
		if (!queryInput.value.trim()) {
			queryInput.focus();
			return;
		}

		searchButton.disabled = true;
		setStatus('Searching…', false);

		try {
			const response = await request({
				action: 'search',
				preset_id: presetSelect.value,
				arguments: buildArguments()
			});
			renderSearch(response);
			setStatus(response.ok ? 'Ready' : 'Search failed', !response.ok);
		}
		catch (error) {
			results.innerHTML = '<div class="retrieval-search-error">' + escapeHtml(error.message) + '</div>';
			setStatus('Search failed', true);
		}
		finally {
			searchButton.disabled = false;
		}
	}

	function clearSearch() {
		queryInput.value = '';
		phrasesInput.value = '';
		requiredInput.value = '';
		excludedInput.value = '';
		filtersInput.value = '';
		results.innerHTML = '';
		summary.style.display = 'none';
		rawHolder.style.display = 'none';
		state.lastResponse = null;
		queryInput.focus();
	}

	async function bootstrap() {
		setStatus('Loading…', false);
		try {
			const response = await request({ action: 'bootstrap' });
			state.presets = Array.isArray(response.presets) ? response.presets : [];
			presetSelect.innerHTML = '';

			for (const preset of state.presets) {
				const option = document.createElement('option');
				option.value = preset.id;
				option.textContent = preset.label + (preset.id === preset.label ? '' : ' [' + preset.id + ']');
				presetSelect.appendChild(option);
			}

			if (!state.presets.length) {
				setStatus('No retrieval preset found', true);
				searchButton.disabled = true;
				results.innerHTML = '<div class="retrieval-search-empty">No configured tool preset exposing retrieval_search was found.</div>';
				return;
			}

			updatePresetUi();
			setStatus('Ready', false);
		}
		catch (error) {
			setStatus('Initialization failed', true);
			results.innerHTML = '<div class="retrieval-search-error">' + escapeHtml(error.message) + '</div>';
		}
	}

	presetSelect.addEventListener('change', updatePresetUi);
	searchButton.addEventListener('click', runSearch);
	el('clear').addEventListener('click', clearSearch);
	queryInput.addEventListener('keydown', event => {
		if (event.key === 'Enter' && !event.shiftKey) {
			event.preventDefault();
			runSearch();
		}
	});
	el('copy-raw').addEventListener('click', async () => {
		if (state.lastResponse === null) return;
		await navigator.clipboard.writeText(pretty(state.lastResponse));
	});

	bootstrap();
})();
</script>
