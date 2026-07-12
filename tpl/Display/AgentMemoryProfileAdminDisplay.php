<?php
$serviceUrl = (string)($this->_['service'] ?? '');
$settingsGroup = (string)($this->_['settings_group'] ?? 'agent-memory-profile');
$profileKind = (string)($this->_['profile_kind'] ?? 'memory');
$profileTitle = (string)($this->_['profile_title'] ?? 'Memory Profiles');
$profileDescription = (string)($this->_['profile_description'] ?? '');
$presetLabel = (string)($this->_['preset_label'] ?? 'Configured presets');
$emptyPresetText = (string)($this->_['empty_preset_text'] ?? 'No presets are available.');
$componentPresetAdminUrl = (string)($this->_['component_preset_admin_url'] ?? '');
$presetOptions = is_array($this->_['preset_options'] ?? null) ? $this->_['preset_options'] : [];
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<style>
.agent-preset-profile-admin-shell { max-width:1700px; }
.agent-preset-profile-admin-shell * { box-sizing:border-box; }
.agent-preset-profile-admin-shell h1 { margin:0 0 8px; font-size:24px; line-height:1.2; font-weight:600; }
.agent-preset-profile-admin-shell > p { margin:0 0 16px; max-width:1120px; color:#555; line-height:1.45; }
.agent-preset-profile-admin-panel { display:flex; align-items:center; flex-wrap:nowrap; gap:8px; min-width:0; width:100%; padding:8px 10px; border:1px solid #e2e2e2; border-radius:8px; background:#fff; overflow-x:auto; }
.agent-preset-profile-admin-panel--filters { margin-top:8px; flex-wrap:wrap; align-items:flex-start; overflow-x:visible; }
.agent-preset-profile-admin-panel > * { flex:0 0 auto; }
.agent-preset-profile-admin-panel label { display:inline-flex; align-items:center; gap:6px; margin:0; white-space:nowrap; color:#666; font-size:12px; }
.agent-preset-profile-admin-spacer { flex:1 1 auto; }
.agent-preset-profile-admin-input,.agent-preset-profile-admin-select,.agent-preset-profile-admin-button,.agent-preset-profile-admin-textarea { min-height:28px; padding:4px 10px; border:1px solid #cfcfcf; border-radius:4px; background:#fff; color:#222; font:inherit; font-size:13px; line-height:1.3; }
.agent-preset-profile-admin-input[type="search"] { width:300px; }
.agent-preset-profile-admin-button { appearance:none; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; white-space:nowrap; text-decoration:none; }
.agent-preset-profile-admin-button:hover { background:#f5f5f5; text-decoration:none; }
.agent-preset-profile-admin-button-primary { background:#2f5d91; border-color:#2f5d91; color:#fff; }
.agent-preset-profile-admin-button-primary:hover { background:#284f7c; color:#fff; }
.agent-preset-profile-admin-button-danger { border-color:#c8a2a2; color:#8a1f1f; }
.agent-preset-profile-admin-main { margin-top:10px; border:1px solid #e2e2e2; border-radius:8px; background:#fff; padding:4px 0; }
.agent-preset-profile-admin-table-scroll { height:540px; overflow:auto; padding-bottom:4px; }
.agent-preset-profile-admin-table { width:100%; border-collapse:collapse; table-layout:fixed; }
.agent-preset-profile-admin-table th,.agent-preset-profile-admin-table td { padding:6px 8px; border-bottom:1px solid #ececec; font-size:13px; vertical-align:top; text-align:left; }
.agent-preset-profile-admin-table thead th { position:sticky; top:0; z-index:12; background:#fff; }
.agent-preset-profile-admin-table tbody tr:hover { background:#fafcff; }
.agent-preset-profile-admin-cell-stack { display:grid; gap:2px; min-width:0; }
.agent-preset-profile-admin-cell-main { font-weight:600; color:#222; min-width:0; overflow-wrap:anywhere; }
.agent-preset-profile-admin-cell-sub { font-size:12px; color:#666; min-width:0; overflow-wrap:anywhere; }
.agent-preset-profile-admin-pill-row { display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
.agent-preset-profile-admin-pill { display:inline-flex; align-items:center; padding:1px 6px; border:1px solid #d6d6d6; border-radius:999px; background:#fafafa; font-size:11px; line-height:1.35; color:#444; white-space:nowrap; }
.agent-preset-profile-admin-pill-enabled { background:#eef7ee; border-color:#bddfbd; }
.agent-preset-profile-admin-pill-disabled { background:#f5eeee; border-color:#e2c5c5; color:#7a3333; }
.agent-preset-profile-admin-actions { display:flex; gap:5px; justify-content:flex-end; }
.agent-preset-profile-admin-status { margin-top:12px; padding:8px 10px; border:1px solid #e2e2e2; border-radius:8px; background:#fff; font-size:13px; color:#555; }
.agent-preset-profile-admin-status[data-error="1"] { border-color:#e4b9b9; background:#fff0f0; color:#8a1f1f; }
.agent-preset-profile-admin-empty { padding:18px; text-align:center; color:#666; }
.agent-preset-profile-admin-modal[hidden] { display:none!important; }
.agent-preset-profile-admin-modal { position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; background:rgba(0,0,0,.42); }
.agent-preset-profile-admin-dialog { width:min(760px,100%); max-height:min(760px,100%); display:flex; flex-direction:column; border:1px solid #cfcfcf; border-radius:10px; background:#fff; box-shadow:0 18px 60px rgba(0,0,0,.28); }
.agent-preset-profile-admin-dialog-header,.agent-preset-profile-admin-dialog-footer { display:flex; align-items:center; gap:8px; padding:10px 12px; border-bottom:1px solid #e2e2e2; }
.agent-preset-profile-admin-dialog-footer { justify-content:flex-end; border-top:1px solid #e2e2e2; border-bottom:0; }
.agent-preset-profile-admin-dialog-title { margin:0; font-size:18px; font-weight:600; }
.agent-preset-profile-admin-dialog-body { min-height:0; overflow:auto; padding:12px; }
.agent-preset-profile-admin-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.agent-preset-profile-admin-field-full { grid-column:1/-1; }
.agent-preset-profile-admin-label { display:block; margin-bottom:5px; color:#555; font-size:12px; font-weight:600; line-height:1.3; }
.agent-preset-profile-admin-form .agent-preset-profile-admin-input,.agent-preset-profile-admin-form .agent-preset-profile-admin-textarea { width:100%; min-height:34px; padding:7px 9px; }
.agent-preset-profile-admin-textarea { min-height:90px; resize:vertical; }
.agent-preset-profile-admin-checkbox-list { display:grid; gap:4px; max-height:280px; overflow:auto; padding:8px; border:1px solid #cfcfcf; border-radius:4px; background:#fff; }
.agent-preset-profile-admin-preset-checkbox { display:flex; align-items:flex-start; gap:8px; padding:5px 6px; border-radius:4px; cursor:pointer; font-size:13px; line-height:1.35; }
.agent-preset-profile-admin-preset-checkbox:hover { background:#f6f6f6; }
.agent-preset-profile-admin-preset-checkbox input { margin-top:2px; flex:0 0 auto; }
.agent-preset-profile-admin-preset-text { display:grid; gap:2px; min-width:0; }
.agent-preset-profile-admin-preset-main { font-weight:600; overflow-wrap:anywhere; }
.agent-preset-profile-admin-preset-sub { color:#666; font-size:12px; overflow-wrap:anywhere; }
.agent-preset-profile-admin-help { margin:5px 0 0; color:#666; font-size:12px; line-height:1.4; }
@media(max-width:700px){ .agent-preset-profile-admin-form{grid-template-columns:1fr}.agent-preset-profile-admin-panel{flex-wrap:wrap}.agent-preset-profile-admin-table-scroll{height:420px} }
</style>

<div class="agent-preset-profile-admin-shell" data-agent-preset-profile-admin data-profile-kind="<?php echo $e($profileKind); ?>">
	<h1><?php echo $e($profileTitle); ?></h1>
	<p><?php echo $e($profileDescription); ?></p>
	<div class="agent-preset-profile-admin-panel">
		<label>Search <input type="search" class="agent-preset-profile-admin-input" data-search placeholder="ID, label or preset"></label>
		<button type="button" class="agent-preset-profile-admin-button" data-clear>Clear</button>
		<span class="agent-preset-profile-admin-spacer"></span>
		<a class="agent-preset-profile-admin-button" href="<?php echo $e($componentPresetAdminUrl); ?>">Component Presets</a>
		<button type="button" class="agent-preset-profile-admin-button" data-reload>Reload</button>
		<button type="button" class="agent-preset-profile-admin-button agent-preset-profile-admin-button-primary" data-new>New profile</button>
	</div>
	<div class="agent-preset-profile-admin-panel agent-preset-profile-admin-panel--filters">
		<label>Status <select class="agent-preset-profile-admin-select" data-enabled-filter><option value="">All states</option><option value="1">Enabled</option><option value="0">Disabled</option></select></label>
	</div>
	<div class="agent-preset-profile-admin-main">
		<div class="agent-preset-profile-admin-table-scroll">
			<table class="agent-preset-profile-admin-table">
				<thead><tr><th style="width:25%">Profile</th><th style="width:12%">Status</th><th style="width:32%">Configured presets</th><th>Description</th><th style="width:150px">Actions</th></tr></thead>
				<tbody data-table-body><tr><td colspan="5" class="agent-preset-profile-admin-empty">Loading profiles...</td></tr></tbody>
			</table>
		</div>
	</div>
	<div class="agent-preset-profile-admin-status" data-status><strong>Last action:</strong> Waiting for initialization.</div>
</div>

<div class="agent-preset-profile-admin-modal" data-modal data-profile-kind="<?php echo $e($profileKind); ?>" hidden>
	<div class="agent-preset-profile-admin-dialog" role="dialog" aria-modal="true">
		<div class="agent-preset-profile-admin-dialog-header">
			<h2 class="agent-preset-profile-admin-dialog-title" data-dialog-title><?php echo $e($profileTitle); ?></h2>
			<span class="agent-preset-profile-admin-spacer"></span>
			<button type="button" class="agent-preset-profile-admin-button" data-close>Close</button>
		</div>
		<div class="agent-preset-profile-admin-dialog-body">
			<form data-form>
				<input type="hidden" name="old_id" value="">
				<div class="agent-preset-profile-admin-form">
					<div><label class="agent-preset-profile-admin-label">Technical ID</label><input class="agent-preset-profile-admin-input" name="id" required pattern="[a-z0-9._-]+"></div>
					<div><label class="agent-preset-profile-admin-label">Label</label><input class="agent-preset-profile-admin-input" name="label"></div>
					<div class="agent-preset-profile-admin-field-full"><label><input type="checkbox" name="enabled" checked> enabled</label></div>
					<div class="agent-preset-profile-admin-field-full"><label class="agent-preset-profile-admin-label">Description</label><textarea class="agent-preset-profile-admin-textarea" name="description"></textarea></div>
					<div class="agent-preset-profile-admin-field-full">
						<label class="agent-preset-profile-admin-label"><?php echo $e($presetLabel); ?></label>
						<div class="agent-preset-profile-admin-checkbox-list" data-preset-list></div>
						<p class="agent-preset-profile-admin-help">Only already configured Component Presets are listed. Their saved configuration is used unchanged at runtime.</p>
					</div>
				</div>
			</form>
		</div>
		<div class="agent-preset-profile-admin-dialog-footer">
			<button type="button" class="agent-preset-profile-admin-button agent-preset-profile-admin-button-danger" data-delete hidden>Delete</button>
			<span class="agent-preset-profile-admin-spacer"></span>
			<button type="button" class="agent-preset-profile-admin-button" data-cancel>Cancel</button>
			<button type="button" class="agent-preset-profile-admin-button agent-preset-profile-admin-button-primary" data-save>Save</button>
		</div>
	</div>
</div>

<script>
(function(){
	const ENDPOINT=<?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	const SETTINGS_GROUP=<?php echo json_encode($settingsGroup, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	const PROFILE_TITLE=<?php echo json_encode($profileTitle, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	const EMPTY_PRESET_TEXT=<?php echo json_encode($emptyPresetText, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	let PRESETS=<?php echo json_encode(array_values($presetOptions), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	const PROFILE_KIND=<?php echo json_encode($profileKind, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	const root=Array.from(document.querySelectorAll('[data-agent-preset-profile-admin]')).find(function(node){return node.dataset.profileKind===PROFILE_KIND;});
	if(!root||root.dataset.ready==='1')return;root.dataset.ready='1';
	const body=root.querySelector('[data-table-body]');
	const search=root.querySelector('[data-search]');
	const enabledFilter=root.querySelector('[data-enabled-filter]');
	const status=root.querySelector('[data-status]');
	const modal=Array.from(document.querySelectorAll('[data-modal]')).find(function(node){return node.dataset.profileKind===PROFILE_KIND;});
	if(!modal)return;
	const form=modal.querySelector('[data-form]');
	const presetList=modal.querySelector('[data-preset-list]');
	const deleteButton=modal.querySelector('[data-delete]');
	const dialogTitle=modal.querySelector('[data-dialog-title]');
	let timer=null;

	function setStatus(message,error){status.dataset.error=error?'1':'0';status.innerHTML='';const strong=document.createElement('strong');strong.textContent=error?'Error: ':'Last action: ';status.appendChild(strong);status.appendChild(document.createTextNode(String(message||'')));}
	async function post(payload){const response=await fetch(ENDPOINT,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});if(!response.ok)throw new Error('Request failed with status '+response.status);const json=await response.json();if(!json||json.ok!==true)throw new Error(json&&json.error?json.error:'Request failed.');return json;}
	function cell(main,sub){const wrap=document.createElement('div');wrap.className='agent-preset-profile-admin-cell-stack';const a=document.createElement('div');a.className='agent-preset-profile-admin-cell-main';a.textContent=String(main||'');wrap.appendChild(a);if(sub){const b=document.createElement('div');b.className='agent-preset-profile-admin-cell-sub';b.textContent=String(sub);wrap.appendChild(b);}return wrap;}
	function pill(text,cls){const p=document.createElement('span');p.className='agent-preset-profile-admin-pill '+(cls||'');p.textContent=String(text);return p;}
	function button(text,handler,cls){const b=document.createElement('button');b.type='button';b.className='agent-preset-profile-admin-button '+(cls||'');b.textContent=text;b.addEventListener('click',handler);return b;}
	function renderRows(rows){body.innerHTML='';if(!Array.isArray(rows)||rows.length===0){const tr=document.createElement('tr');const td=document.createElement('td');td.colSpan=5;td.className='agent-preset-profile-admin-empty';td.textContent='No profiles match the current filter.';tr.appendChild(td);body.appendChild(tr);return;}rows.forEach(function(row){const tr=document.createElement('tr');let td=document.createElement('td');td.appendChild(cell(row.label||row.profile_id,row.profile_id));tr.appendChild(td);td=document.createElement('td');const pills=document.createElement('div');pills.className='agent-preset-profile-admin-pill-row';pills.appendChild(pill(row.enabled_label,row.enabled?'agent-preset-profile-admin-pill-enabled':'agent-preset-profile-admin-pill-disabled'));if(row.legacy_derived)pills.appendChild(pill('legacy derived',''));td.appendChild(pills);tr.appendChild(td);td=document.createElement('td');td.appendChild(cell(String(row.preset_count||0)+' preset'+(Number(row.preset_count||0)===1?'':'s'),row.preset_text||'No presets'));tr.appendChild(td);td=document.createElement('td');td.textContent=row.description||'';tr.appendChild(td);td=document.createElement('td');const actions=document.createElement('div');actions.className='agent-preset-profile-admin-actions';actions.appendChild(button('Edit',function(){openExisting(row.profile_id);}));actions.appendChild(button('Copy',function(){openCopy(row.profile_id);}));td.appendChild(actions);tr.appendChild(td);body.appendChild(tr);});}
	async function loadPage(){try{const json=await post({mode:'page',page:1,pageSize:250,search:search.value.trim(),filters:{enabled:enabledFilter.value},sort:[{key:'profile_id',dir:'asc'}]});renderRows(json.data||[]);setStatus('Loaded '+String(json.total||0)+' profile(s) from '+SETTINGS_GROUP+'.');}catch(error){body.innerHTML='<tr><td colspan="5" class="agent-preset-profile-admin-empty">Unable to load profiles.</td></tr>';setStatus(error.message,true);}}
	function renderPresetList(selected){presetList.innerHTML='';const selectedSet=new Set((Array.isArray(selected)?selected:[]).map(String));if(!Array.isArray(PRESETS)||PRESETS.length===0){const div=document.createElement('div');div.className='agent-preset-profile-admin-empty';div.textContent=EMPTY_PRESET_TEXT;presetList.appendChild(div);return;}PRESETS.forEach(function(preset){const label=document.createElement('label');label.className='agent-preset-profile-admin-preset-checkbox';const input=document.createElement('input');input.type='checkbox';input.value=String(preset.id||'');input.checked=selectedSet.has(input.value);input.dataset.preset='1';label.appendChild(input);const text=document.createElement('span');text.className='agent-preset-profile-admin-preset-text';const main=document.createElement('span');main.className='agent-preset-profile-admin-preset-main';main.textContent=String(preset.label||preset.id)+' ('+String(preset.id||'')+')';text.appendChild(main);const sub=document.createElement('span');sub.className='agent-preset-profile-admin-preset-sub';const parts=[String(preset.type||'')];if(preset.config_summary)parts.push(String(preset.config_summary));if(preset.description)parts.push(String(preset.description));sub.textContent=parts.filter(Boolean).join(' — ');text.appendChild(sub);label.appendChild(text);presetList.appendChild(label);});}
	function collectPresets(){return Array.from(presetList.querySelectorAll('[data-preset]:checked')).map(function(input){return input.value;});}
	function fillForm(record,copy){record=record||{};form.reset();form.elements.old_id.value=copy?'':String(record.profile_id||record.id||'');form.elements.id.value=copy?'':String(record.profile_id||record.id||'');form.elements.label.value=copy?(String(record.label||'')+' Copy'):String(record.label||'');form.elements.description.value=String(record.description||'');form.elements.enabled.checked=record.enabled!==false;renderPresetList(record.presets||[]);deleteButton.hidden=copy||!record.profile_id||record.legacy_derived===true;dialogTitle.textContent=(record.profile_id&&!copy?'Edit ':'New ')+PROFILE_TITLE.replace(/s$/,'');modal.hidden=false;setTimeout(function(){form.elements.id.focus();},0);}
	async function openExisting(id){try{const json=await post({mode:'record',id:id});if(Array.isArray(json.preset_options))PRESETS=json.preset_options;fillForm(json.record||{},false);}catch(error){setStatus(error.message,true);}}
	async function openCopy(id){try{const json=await post({mode:'record',id:id});if(Array.isArray(json.preset_options))PRESETS=json.preset_options;fillForm(json.record||{},true);}catch(error){setStatus(error.message,true);}}
	function closeModal(){modal.hidden=true;}
	async function save(){const payload={mode:'save',old_id:form.elements.old_id.value,id:form.elements.id.value,label:form.elements.label.value,description:form.elements.description.value,enabled:form.elements.enabled.checked,presets:collectPresets()};try{const json=await post(payload);closeModal();setStatus('Profile '+json.action+'.');await loadPage();}catch(error){setStatus(error.message,true);}}
	async function remove(){const id=form.elements.old_id.value;if(!id||!window.confirm('Delete profile '+id+'?'))return;try{await post({mode:'delete',id:id});closeModal();setStatus('Profile deleted.');await loadPage();}catch(error){setStatus(error.message,true);}}
	root.querySelector('[data-new]').addEventListener('click',function(){fillForm({},false);});
	root.querySelector('[data-clear]').addEventListener('click',function(){search.value='';enabledFilter.value='';loadPage();});
	root.querySelector('[data-reload]').addEventListener('click',async function(){try{const json=await post({mode:'reload'});if(Array.isArray(json.preset_options))PRESETS=json.preset_options;await loadPage();}catch(error){setStatus(error.message,true);}});
	search.addEventListener('input',function(){window.clearTimeout(timer);timer=window.setTimeout(loadPage,180);});
	enabledFilter.addEventListener('change',loadPage);
	modal.querySelector('[data-close]').addEventListener('click',closeModal);
	modal.querySelector('[data-cancel]').addEventListener('click',closeModal);
	modal.querySelector('[data-save]').addEventListener('click',save);
	deleteButton.addEventListener('click',remove);
	modal.addEventListener('click',function(event){if(event.target===modal)closeModal();});
	loadPage();
})();
</script>
