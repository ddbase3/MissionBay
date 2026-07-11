<?php
$agentConfigForm = is_array($this->_['agent_config_form'] ?? null) ? $this->_['agent_config_form'] : [];
$values = is_array($agentConfigForm['values'] ?? null) ? $agentConfigForm['values'] : [];
$llmOptions = is_array($agentConfigForm['llm_options'] ?? null) ? $agentConfigForm['llm_options'] : [];
$orchestratorProfiles = is_array($agentConfigForm['orchestrator_profile_options'] ?? null) ? $agentConfigForm['orchestrator_profile_options'] : [];
$toolProfiles = is_array($agentConfigForm['tool_profile_options'] ?? null) ? $agentConfigForm['tool_profile_options'] : [];
$agentComponentPresets = is_array($agentConfigForm['agent_component_presets'] ?? null) ? $agentConfigForm['agent_component_presets'] : [];
$agentComponents = is_array($values['agent_components'] ?? null) ? $values['agent_components'] : [];
$capabilityComponentOptions = is_array($agentConfigForm['capability_component_options'] ?? null) ? $agentConfigForm['capability_component_options'] : [];
$capabilitySources = is_array($values['capability_sources'] ?? null) ? $values['capability_sources'] : [];
$capabilitySelection = is_array($values['capability_selection'] ?? null) ? $values['capability_selection'] : [];
$formId = (string)($agentConfigForm['form_id'] ?? 'base3_agent_config');
$rootId = $formId . '_agent_config_section';
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$checked = static fn($value): string => !empty($value) ? ' checked="checked"' : '';
$selected = static fn($current, $value): string => (string)$current === (string)$value ? ' selected="selected"' : '';
$selectedIn = static fn($current, $value): string => in_array((string)$value, is_array($current) ? array_map('strval', $current) : [], true) ? ' selected="selected"' : '';
$listText = static fn($value): string => is_array($value) ? implode("\n", array_map('strval', $value)) : '';
?>
<style>
.base3-agent-config-root * { box-sizing: border-box; }
.base3-agent-config-section { margin:0 0 18px; padding:16px; border:1px solid #ddd; border-radius:6px; background:#fff; }
.base3-agent-config-section h3 { margin:0 0 14px; font-size:18px; }
.base3-agent-config-row { display:grid; grid-template-columns:minmax(150px,220px) minmax(0,1fr); gap:8px 18px; margin:0 0 14px; }
.base3-agent-config-row:last-child { margin-bottom:0; }
.base3-agent-config-label { padding-top:7px; font-weight:600; }
.base3-agent-config-root input[type="text"], .base3-agent-config-root input[type="number"], .base3-agent-config-root select, .base3-agent-config-root textarea { width:100%; max-width:760px; min-height:34px; padding:6px 8px; border:1px solid #bbb; border-radius:3px; background:#fff; font:inherit; }
.base3-agent-config-root textarea { resize:vertical; font-family:monospace; }
.base3-agent-config-root select[multiple] { min-height:130px; }
.base3-agent-config-system-prompt { min-height:220px; }
.base3-agent-config-agent-flow { min-height:360px; }
.base3-agent-config-help { max-width:800px; margin:5px 0 0; color:#666; font-size:12px; line-height:1.4; }
.base3-agent-config-profile-card { max-width:900px; padding:12px; border:1px solid #e0e0e0; border-radius:6px; background:#fafafa; }
.base3-agent-config-stage-preview { display:flex; flex-wrap:wrap; gap:5px; margin-top:8px; }
.base3-agent-config-stage-pill { padding:2px 7px; border:1px solid #d7d7d7; border-radius:999px; background:#fff; font-size:11px; }
.base3-agent-config-expert { margin:0 0 18px; border:1px solid #d7d7d7; border-radius:6px; background:#fafafa; }
.base3-agent-config-expert > summary { padding:12px 14px; cursor:pointer; font-weight:600; }
.base3-agent-config-expert-body { padding:0 14px 14px; }
.base3-agent-config-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px 18px; }
.base3-agent-config-component-row { display:grid; grid-template-columns:minmax(160px,1fr) 80px 90px auto; gap:8px; align-items:center; margin:0 0 8px; padding:8px; border:1px solid #ddd; border-radius:4px; background:#fff; }
.base3-agent-config-component-row button { min-height:32px; }
@media(max-width:700px){ .base3-agent-config-row,.base3-agent-config-grid,.base3-agent-config-component-row{display:block}.base3-agent-config-label{display:block;padding:0;margin:0 0 5px}.base3-agent-config-component-row>*{margin-bottom:7px} }
</style>

<div id="<?php echo $e($rootId); ?>" class="base3-agent-config-root" data-base3-agent-config-root="1">
	<div class="base3-agent-config-section">
		<h3>Model and instructions</h3>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_llm">LLM</label>
			<div><select id="<?php echo $e($formId); ?>_llm" name="llm"><option value="">Use AgentFlow value</option>
<?php foreach($llmOptions as $llm): $id=(string)($llm['id']??''); if($id==='') continue; $label=(string)($llm['label']??$id); ?>
				<option value="<?php echo $e($id); ?>"<?php echo $selected($values['llm']??'', $id); ?>><?php echo $e($label . (!empty($llm['model']) ? ' / '.$llm['model'] : '') . (empty($llm['enabled']) ? ' [disabled]' : '')); ?></option>
<?php endforeach; ?></select></div>
		</div>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_system">System prompt</label>
			<div><textarea id="<?php echo $e($formId); ?>_system" name="system_prompt" class="base3-agent-config-system-prompt"><?php echo $e($values['system_prompt']??''); ?></textarea></div>
		</div>
	</div>

	<div class="base3-agent-config-section">
		<h3>Profiles</h3>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_orchestrator">Orchestrator profile</label>
			<div>
				<select id="<?php echo $e($formId); ?>_orchestrator" name="orchestrator_profile" data-orchestrator-profile>
<?php foreach($orchestratorProfiles as $profile): $id=(string)($profile['id']??''); if($id==='') continue; ?>
					<option value="<?php echo $e($id); ?>" data-description="<?php echo $e($profile['description']??''); ?>" data-stages="<?php echo $e(implode(',', (array)($profile['stage_ids']??[]))); ?>"<?php echo $selected($values['orchestrator_profile']??'standard',$id); ?>><?php echo $e(($profile['label']??$id) . (!empty($profile['builtin']) ? ' [built-in]' : '') . (empty($profile['enabled']) ? ' [disabled]' : '')); ?></option>
<?php endforeach; ?>
				</select>
				<div class="base3-agent-config-profile-card" data-orchestrator-profile-summary></div>
				<p class="base3-agent-config-help">Stage order is fixed and validated. Profiles only enable optional stages and set limits.</p>
			</div>
		</div>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_tool_profiles">Tool profiles</label>
			<div>
				<select id="<?php echo $e($formId); ?>_tool_profiles" name="tool_profiles[]" multiple>
<?php foreach($toolProfiles as $profile): $id=(string)($profile['id']??''); if($id==='') continue; ?>
					<option value="<?php echo $e($id); ?>"<?php echo $selectedIn($values['tool_profiles']??[],$id); ?>><?php echo $e(($profile['label']??$id) . ' (' . (int)($profile['tool_count']??0) . ')' . (!empty($profile['mcp_enabled']) ? ' [MCP]' : '')); ?></option>
<?php endforeach; ?>
				</select>
				<p class="base3-agent-config-help">Profiles define the allowed component presets. A preset that implements both <code>tool</code> and <code>memory</code> is attached in both roles automatically.</p>
			</div>
		</div>
	</div>

	<details class="base3-agent-config-expert"<?php echo !empty($values['expert_overrides_enabled']) ? ' open="open"' : ''; ?>>
		<summary>Expert / legacy configuration</summary>
		<div class="base3-agent-config-expert-body">
			<div class="base3-agent-config-row"><div class="base3-agent-config-label">Enable overrides</div><div><input type="hidden" name="expert_overrides_enabled" value="0"><label><input type="checkbox" name="expert_overrides_enabled" value="1"<?php echo $checked($values['expert_overrides_enabled']??false); ?>> Apply direct capability source and selection overrides below</label></div></div>

			<h4>Direct capability sources</h4>
			<div class="base3-agent-config-grid">
<?php $sourceGroups=['tools'=>'Configured tools','providers'=>'Capability providers','modules'=>'Modules','resourceProviders'=>'Resource providers','promptProviders'=>'Prompt providers']; foreach($sourceGroups as $key=>$label): $options=(array)($capabilityComponentOptions[$key]??[]); ?>
				<div><label><?php echo $e($label); ?></label><select name="capability_sources[<?php echo $e($key); ?>][]" multiple>
<?php foreach($options as $option): $id=(string)($option['id']??''); if($id==='') continue; ?><option value="<?php echo $e($id); ?>"<?php echo $selectedIn($capabilitySources[$key]??[],$id); ?>><?php echo $e(($option['label']??$id).' ('.$id.')'); ?></option><?php endforeach; ?>
				</select></div>
<?php endforeach; ?>
			</div>
			<input type="hidden" name="capability_sources[strict]" value="0"><label><input type="checkbox" name="capability_sources[strict]" value="1"<?php echo $checked($capabilitySources['strict']??true); ?>> Strict source resolution</label>

			<h4>Direct selection override</h4>
			<div class="base3-agent-config-grid">
				<div><label>Enabled</label><input type="hidden" name="capability_selection[enabled]" value="0"><label><input type="checkbox" name="capability_selection[enabled]" value="1"<?php echo $checked($capabilitySelection['enabled']??true); ?>> Preselect tools</label></div>
				<div><label>Strategy</label><select name="capability_selection[strategy]"><option value="hybrid"<?php echo $selected($capabilitySelection['strategy']??'hybrid','hybrid'); ?>>Hybrid</option><option value="all"<?php echo $selected($capabilitySelection['strategy']??'hybrid','all'); ?>>All</option></select></div>
				<div><label>Maximum tools</label><input type="number" min="1" max="512" name="capability_selection[max_tools]" value="<?php echo $e($capabilitySelection['max_tools']??16); ?>"></div>
				<div><label>Select-all threshold</label><input type="number" min="0" max="512" name="capability_selection[select_all_threshold]" value="<?php echo $e($capabilitySelection['select_all_threshold']??16); ?>"></div>
<?php foreach(['include_tools'=>'Include tools','exclude_tools'=>'Exclude tools','include_tags'=>'Include tags','exclude_tags'=>'Exclude tags','include_categories'=>'Include categories','exclude_categories'=>'Exclude categories','always_available'=>'Always available'] as $key=>$label): ?>
				<div><label><?php echo $e($label); ?></label><textarea name="capability_selection[<?php echo $e($key); ?>]" rows="3"><?php echo $e($listText($capabilitySelection[$key]??[])); ?></textarea></div>
<?php endforeach; ?>
				<div><label>Sticky</label><input type="hidden" name="capability_selection[sticky]" value="0"><label><input type="checkbox" name="capability_selection[sticky]" value="1"<?php echo $checked($capabilitySelection['sticky']??true); ?>> Keep selection stable</label></div>
			</div>

			<h4>Direct component presets</h4>
			<div data-agent-components>
				<div data-agent-component-items></div>
				<button type="button" class="btn btn-default" data-agent-component-add>Add direct component</button>
			</div>
			<input type="hidden" name="agent_components_json" data-agent-components-json value="[]">
			<input type="hidden" name="agent_components_json_b64" data-agent-components-b64 value="">

			<h4>Raw AgentFlow</h4>
			<textarea name="agent_flow" class="base3-agent-config-agent-flow"><?php echo $e($values['agent_flow_json']??'{}'); ?></textarea>
			<input type="hidden" name="agent_flow_b64" data-agent-flow-b64 value="">
		</div>
	</details>
</div>

<script>
(function(){
	var root=document.getElementById(<?php echo json_encode($rootId); ?>); if(!root||root.dataset.ready==='1')return; root.dataset.ready='1';
	var presets=<?php echo json_encode(array_values($agentComponentPresets), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	var initialComponents=<?php echo json_encode(array_values($agentComponents), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	var items=root.querySelector('[data-agent-component-items]'); var nextIndex=0;
	function enc(v){v=String(v||''); return btoa(unescape(encodeURIComponent(v)));}
	function selectedValues(name){var f=root.querySelector('[name="'+name+'"]'); if(!f)return[]; return Array.prototype.filter.call(f.options,function(o){return o.selected}).map(function(o){return o.value});}
	function fixed(v){return {mode:'fixed',value:v};}
	function presetById(id){return presets.find(function(p){return String(p.id||'')===String(id||'')})||null;}
	function addComponent(component){component=component||{}; var row=document.createElement('div'); row.className='base3-agent-config-component-row'; row.dataset.componentRow='1';
		var select=document.createElement('select'); select.dataset.field='preset'; select.innerHTML='<option value="">Select preset</option>'; presets.forEach(function(p){var o=document.createElement('option');o.value=p.id;o.textContent=(p.label||p.id)+' ['+(Array.isArray(p.capabilities)?p.capabilities.join('+'):'')+']';o.selected=String(component.preset||'')===String(p.id||'');select.appendChild(o)}); row.appendChild(select);
		var active=document.createElement('label'); active.innerHTML='<input type="checkbox" data-field="enabled" '+(component.enabled===false?'':'checked')+'> active'; row.appendChild(active);
		var order=document.createElement('input'); order.type='number'; order.placeholder='order'; order.dataset.field='order'; order.value=component.order||''; row.appendChild(order);
		var remove=document.createElement('button'); remove.type='button'; remove.textContent='Remove'; remove.onclick=function(){row.remove();sync()}; row.appendChild(remove); items.appendChild(row); row.addEventListener('change',sync); row.addEventListener('input',sync); nextIndex++;
	}
	function buildComponents(){if(!items)return[]; return Array.prototype.map.call(items.querySelectorAll('[data-component-row]'),function(row){var preset=row.querySelector('[data-field="preset"]').value; if(!preset)return null; var meta=presetById(preset)||{}; var caps=Array.isArray(meta.capabilities)?meta.capabilities:[]; var order=row.querySelector('[data-field="order"]').value; var c={preset:preset,attach_as:caps,enabled:row.querySelector('[data-field="enabled"]').checked}; if(order!=='')c.order=parseInt(order,10); return c}).filter(Boolean);}
	function sync(){var components=buildComponents(); var text=JSON.stringify(components); var cj=root.querySelector('[data-agent-components-json]');var cb=root.querySelector('[data-agent-components-b64]');var flow=root.querySelector('[name="agent_flow"]');var fb=root.querySelector('[data-agent-flow-b64]');if(cj)cj.value=text;if(cb)cb.value=enc(text);if(flow&&fb)fb.value=enc(flow.value||'{}');}
	function renderComponents(list){if(!items)return;items.innerHTML='';(Array.isArray(list)?list:[]).forEach(addComponent);sync();}
	var add=root.querySelector('[data-agent-component-add]'); if(add)add.onclick=function(){addComponent({enabled:true});sync()};
	var profileSelect=root.querySelector('[data-orchestrator-profile]'); var summary=root.querySelector('[data-orchestrator-profile-summary]');
	function updateSummary(){if(!profileSelect||!summary)return;var o=profileSelect.options[profileSelect.selectedIndex];summary.innerHTML='';if(!o)return;var d=document.createElement('div');d.textContent=o.dataset.description||'';summary.appendChild(d);var stages=document.createElement('div');stages.className='base3-agent-config-stage-preview';String(o.dataset.stages||'').split(',').filter(Boolean).forEach(function(id){var p=document.createElement('span');p.className='base3-agent-config-stage-pill';p.textContent=id;stages.appendChild(p)});summary.appendChild(stages)}
	if(profileSelect)profileSelect.addEventListener('change',updateSummary); updateSummary();
	root.addEventListener('input',sync);root.addEventListener('change',sync);renderComponents(initialComponents);
	function setValue(name,value){var fields=root.querySelectorAll('[name="'+name.replace(/"/g,'\\"')+'"]');fields.forEach(function(f){if(f.type==='checkbox'){f.checked=!!value}else{f.value=value==null?'':String(value)}})}
	function setMulti(name,values){var f=root.querySelector('select[multiple][name="'+name.replace(/"/g,'\\"')+'"]');if(!f)return;values=Array.isArray(values)?values.map(String):[];Array.prototype.forEach.call(f.options,function(o){o.selected=values.indexOf(String(o.value))!==-1})}
	root.__base3AgentConfigUpdateValues=function(v){if(!v||typeof v!=='object')return;setValue('llm',v.llm||'');setValue('system_prompt',v.system_prompt||'');setValue('orchestrator_profile',v.orchestrator_profile||'standard');setMulti('tool_profiles[]',v.tool_profiles||[]);setValue('expert_overrides_enabled',!!v.expert_overrides_enabled);var flow=root.querySelector('[name="agent_flow"]');if(flow)flow.value=v.agent_flow_json||'{}';renderComponents(v.agent_components||[]);var src=v.capability_sources||{};['tools','providers','modules','resourceProviders','promptProviders'].forEach(function(k){setMulti('capability_sources['+k+'][]',src[k]||[])});var sel=v.capability_selection||{};setValue('capability_selection[strategy]',sel.strategy||'hybrid');setValue('capability_selection[max_tools]',sel.max_tools==null?16:sel.max_tools);setValue('capability_selection[select_all_threshold]',sel.select_all_threshold==null?16:sel.select_all_threshold);['include_tools','exclude_tools','include_tags','exclude_tags','include_categories','exclude_categories','always_available'].forEach(function(k){setValue('capability_selection['+k+']',Array.isArray(sel[k])?sel[k].join('\n'):'')});updateSummary();sync()};
	root.__base3AgentConfigPrepareSubmit=function(){sync();return true};
})();
</script>
