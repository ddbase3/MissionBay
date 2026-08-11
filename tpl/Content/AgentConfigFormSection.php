<?php
$agentConfigForm = is_array($runtimeAgentConfigForm ?? null)
	? $runtimeAgentConfigForm
	: (is_array($this->_['agent_config_form'] ?? null) ? $this->_['agent_config_form'] : []);
$values = is_array($agentConfigForm['values'] ?? null) ? $agentConfigForm['values'] : [];
$chatModelPresets = is_array($agentConfigForm['chatmodel_preset_options'] ?? null) ? $agentConfigForm['chatmodel_preset_options'] : [];
$orchestratorProfiles = is_array($agentConfigForm['orchestrator_profile_options'] ?? null) ? $agentConfigForm['orchestrator_profile_options'] : [];
$toolProfiles = is_array($agentConfigForm['tool_profile_options'] ?? null) ? $agentConfigForm['tool_profile_options'] : [];
$memoryProfiles = is_array($agentConfigForm['memory_profile_options'] ?? null) ? $agentConfigForm['memory_profile_options'] : [];
$contextProfiles = is_array($agentConfigForm['context_profile_options'] ?? null) ? $agentConfigForm['context_profile_options'] : [];
$agentComponentPresets = is_array($agentConfigForm['agent_component_presets'] ?? null) ? $agentConfigForm['agent_component_presets'] : [];
$agentComponents = is_array($values['agent_components'] ?? null) ? $values['agent_components'] : [];
$capabilityComponentOptions = is_array($agentConfigForm['capability_component_options'] ?? null) ? $agentConfigForm['capability_component_options'] : [];
$capabilitySources = is_array($values['capability_sources'] ?? null) ? $values['capability_sources'] : [];
$capabilitySelection = is_array($values['capability_selection'] ?? null) ? $values['capability_selection'] : [];
$exportCatalog = is_array($agentConfigForm['export_catalog'] ?? null) ? $agentConfigForm['export_catalog'] : [];
$translations = is_array($agentConfigForm['translations'] ?? null) ? $agentConfigForm['translations'] : [];
$formId = (string)($agentConfigForm['form_id'] ?? 'base3_agent_config');
$rootId = $formId . '_agent_config_section';
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$t = static fn(string $key, string $fallback): string => trim((string)($translations[$key] ?? '')) !== '' ? (string)$translations[$key] : $fallback;
$checked = static fn($value): string => !empty($value) ? ' checked="checked"' : '';
$selected = static fn($current, $value): string => (string)$current === (string)$value ? ' selected="selected"' : '';
$selectedIn = static fn($current, $value): string => in_array((string)$value, is_array($current) ? array_map('strval', $current) : [], true) ? ' selected="selected"' : '';
$checkedIn = static fn($current, $value): string => in_array((string)$value, is_array($current) ? array_map('strval', $current) : [], true) ? ' checked="checked"' : '';
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
.base3-agent-config-help { max-width:800px; margin:5px 0 0; color:#666; font-size:12px; line-height:1.4; }
.base3-agent-config-profile-card { max-width:900px; padding:12px; border:1px solid #e0e0e0; border-radius:6px; background:#fafafa; }
.base3-agent-config-fieldset { min-width:0; margin:0 0 14px; padding:0; border:0; }
.base3-agent-config-fieldset > legend { float:left; width:100%; margin:0; padding:7px 0 0; font-size:inherit; font-weight:600; line-height:inherit; }
.base3-agent-config-profile-options { display:grid; gap:8px; max-width:900px; }
.base3-agent-config-profile-option { display:flex; align-items:flex-start; gap:9px; margin:0; padding:10px 12px; border:1px solid #ddd; border-radius:5px; background:#fafafa; cursor:pointer; font-weight:normal; }
.base3-agent-config-profile-option:hover { border-color:#bbb; background:#fff; }
.base3-agent-config-profile-option input { flex:0 0 auto; margin:3px 0 0; }
.base3-agent-config-profile-option-body { display:block; min-width:0; }
.base3-agent-config-profile-option-title { display:flex; flex-wrap:wrap; align-items:baseline; gap:5px 8px; }
.base3-agent-config-profile-option-title code { color:#666; font-size:11px; }
.base3-agent-config-profile-option-meta { color:#666; font-size:11px; }
.base3-agent-config-profile-option-description { display:block; margin-top:3px; color:#555; font-size:12px; line-height:1.4; }
.base3-agent-config-empty { margin:0; color:#666; }
.base3-agent-config-stage-preview { display:flex; flex-wrap:wrap; gap:5px; margin-top:8px; }
.base3-agent-config-stage-pill { padding:2px 7px; border:1px solid #d7d7d7; border-radius:999px; background:#fff; font-size:11px; }
.base3-agent-config-expert { margin:0 0 18px; border:1px solid #d7d7d7; border-radius:6px; background:#fafafa; }
.base3-agent-config-expert > summary { padding:12px 14px; cursor:pointer; font-weight:600; }
.base3-agent-config-expert-body { padding:0 14px 14px; }
.base3-agent-config-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px 18px; }
.base3-agent-config-component-row { display:grid; grid-template-columns:minmax(160px,1fr) 80px 90px auto; gap:8px; align-items:center; margin:0 0 8px; padding:8px; border:1px solid #ddd; border-radius:4px; background:#fff; }
.base3-agent-config-component-row button { min-height:32px; }
.base3-agent-config-export { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:0 0 18px; padding:12px 14px; border:1px solid #cfd8e3; border-radius:6px; background:#f7f9fc; }
.base3-agent-config-export-status { color:#555; font-size:12px; }
.base3-agent-config-export-status[data-state="error"] { color:#a94442; }
.base3-agent-config-export-status[data-state="warning"] { color:#8a6d3b; }
.base3-agent-config-expert-body > .base3-agent-config-export:last-child { margin:18px 0 0; }
@media(max-width:700px){ .base3-agent-config-section{padding:12px}.base3-agent-config-row,.base3-agent-config-grid,.base3-agent-config-component-row{display:block}.base3-agent-config-label,.base3-agent-config-fieldset > legend{display:block;float:none;width:auto;padding:0;margin:0 0 5px}.base3-agent-config-component-row>*{margin-bottom:7px} }
</style>

<div id="<?php echo $e($rootId); ?>" class="base3-agent-config-root" data-base3-agent-runtime-config-root="missionbay">
	<div class="base3-agent-config-section">
		<h3><?php echo $e($t('model_section', 'Model and instructions')); ?></h3>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_chatmodel"><?php echo $e($t('chatmodel_label', 'Chat model')); ?></label>
			<div>
				<select id="<?php echo $e($formId); ?>_chatmodel" name="chatmodel" required>
					<option value=""<?php echo $selected($values['chatmodel']??'',''); ?>><?php echo $e($t('select_chatmodel', 'Select chat model preset')); ?></option>
<?php foreach($chatModelPresets as $preset): $id=(string)($preset['id']??''); if($id==='') continue; $label=(string)($preset['label']??$id); $type=(string)($preset['type']??''); ?>
					<option value="<?php echo $e($id); ?>"<?php echo $selected($values['chatmodel']??'', $id); ?>><?php echo $e($label . ($type !== '' ? ' / ' . $type : '') . (empty($preset['enabled']) ? ' [' . $t('disabled_marker', 'disabled') . ']' : '')); ?></option>
<?php endforeach; ?>
				</select>
				<p class="base3-agent-config-help"><?php echo $e($t('chatmodel_help', 'Selects exactly one chat-model resource preset. Usually this is a configured chat model; composed resources such as a chat-model router can be selected in the same slot.')); ?></p>
			</div>
		</div>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_system"><?php echo $e($t('system_prompt_label', 'System prompt')); ?></label>
			<div><textarea id="<?php echo $e($formId); ?>_system" name="system_prompt" class="base3-agent-config-system-prompt"><?php echo $e($values['system_prompt']??''); ?></textarea></div>
		</div>
	</div>

	<div class="base3-agent-config-section">
		<h3><?php echo $e($t('profiles_section', 'Profiles')); ?></h3>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_orchestrator"><?php echo $e($t('orchestrator_profile_label', 'Orchestrator profile')); ?></label>
			<div>
				<select id="<?php echo $e($formId); ?>_orchestrator" name="orchestrator_profile" data-orchestrator-profile>
<?php foreach($orchestratorProfiles as $profile): $id=(string)($profile['id']??''); if($id==='') continue; ?>
					<option value="<?php echo $e($id); ?>" data-description="<?php echo $e($profile['description']??''); ?>" data-stages="<?php echo $e(implode(',', (array)($profile['stage_ids']??[]))); ?>"<?php echo $selected($values['orchestrator_profile']??'standard',$id); ?>><?php echo $e(($profile['label']??$id) . (!empty($profile['builtin']) ? ' [' . $t('builtin_marker', 'built-in') . ']' : '') . (empty($profile['enabled']) ? ' [' . $t('disabled_marker', 'disabled') . ']' : '')); ?></option>
<?php endforeach; ?>
				</select>
				<div class="base3-agent-config-profile-card" data-orchestrator-profile-summary></div>
				<p class="base3-agent-config-help"><?php echo $e($t('stage_order_help', 'Stage order is fixed and validated. Profiles only enable optional stages and set limits.')); ?></p>
			</div>
		</div>
		<fieldset class="base3-agent-config-row base3-agent-config-fieldset">
			<legend><?php echo $e($t('tool_profiles_label', 'Tool profiles')); ?></legend>
			<div>
				<div class="base3-agent-config-profile-options">
<?php if ($toolProfiles === []) { ?>
					<p class="base3-agent-config-empty"><?php echo $e($t('no_tool_profiles', 'No tool profiles are available for internal agents.')); ?></p>
<?php } ?>
<?php foreach($toolProfiles as $profile):
	$id=(string)($profile['id']??'');
	if($id==='') continue;
	$label=trim((string)($profile['label']??'')) ?: $id;
	$description=trim((string)($profile['description']??''));
	$toolCount=(int)($profile['tool_count']??0);
?>
					<label class="base3-agent-config-profile-option">
						<input type="checkbox" name="tool_profiles[]" value="<?php echo $e($id); ?>"<?php echo $checkedIn($values['tool_profiles']??[],$id); ?> />
						<span class="base3-agent-config-profile-option-body">
							<span class="base3-agent-config-profile-option-title">
								<strong><?php echo $e($label); ?></strong>
								<code><?php echo $e($id); ?></code>
								<span class="base3-agent-config-profile-option-meta"><?php echo $e((string)$toolCount); ?> <?php echo $e($toolCount === 1 ? $t('tool_preset_singular', 'tool preset') : $t('tool_preset_plural', 'tool presets')); ?><?php echo !empty($profile['mcp_enabled']) ? ' · MCP' : ''; ?></span>
							</span>
<?php if ($description !== '') { ?>
							<span class="base3-agent-config-profile-option-description"><?php echo $e($description); ?></span>
<?php } ?>
						</span>
					</label>
<?php endforeach; ?>
				</div>
				<p class="base3-agent-config-help"><?php echo $e($t('tool_profiles_help', 'Select any number of profiles. Tool profiles define callable tool presets only. Conversation memory and context contributors are selected separately below.')); ?></p>
			</div>
		</fieldset>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_memory_profile"><?php echo $e($t('memory_profile_label', 'Memory profile')); ?></label>
			<div>
				<select id="<?php echo $e($formId); ?>_memory_profile" name="memory_profile">
					<option value=""<?php echo $selected($values['memory_profile']??'',''); ?>><?php echo $e($t('no_memory_profile', 'No conversation-memory profile')); ?></option>
<?php foreach($memoryProfiles as $profile): $id=(string)($profile['id']??''); if($id==='') continue; ?>
					<option value="<?php echo $e($id); ?>"<?php echo $selected($values['memory_profile']??'',$id); ?>><?php echo $e(($profile['label']??$id) . ' (' . (int)($profile['preset_count']??$profile['memory_count']??0) . ')'); ?></option>
<?php endforeach; ?>
				</select>
				<p class="base3-agent-config-help"><?php echo $e($t('memory_profile_help', 'Selects configured conversation-memory presets. Their namespace, history limit and other preset values are used unchanged.')); ?></p>
			</div>
		</div>
		<div class="base3-agent-config-row">
			<label class="base3-agent-config-label" for="<?php echo $e($formId); ?>_context_profile"><?php echo $e($t('context_profile_label', 'Context profile')); ?></label>
			<div>
				<select id="<?php echo $e($formId); ?>_context_profile" name="context_profile">
					<option value=""<?php echo $selected($values['context_profile']??'',''); ?>><?php echo $e($t('no_context_profile', 'No context profile')); ?></option>
<?php foreach($contextProfiles as $profile): $id=(string)($profile['id']??''); if($id==='') continue; ?>
					<option value="<?php echo $e($id); ?>"<?php echo $selected($values['context_profile']??'',$id); ?>><?php echo $e(($profile['label']??$id) . ' (' . (int)($profile['preset_count']??$profile['context_count']??0) . ')'); ?></option>
<?php endforeach; ?>
				</select>
				<p class="base3-agent-config-help"><?php echo $e($t('context_profile_help', 'Selects configured context-contributor presets. These add system context but do not store conversation history.')); ?></p>
			</div>
		</div>
	</div>


	<details class="base3-agent-config-expert"<?php echo !empty($values['expert_overrides_enabled']) ? ' open="open"' : ''; ?>>
		<summary><?php echo $e($t('expert_summary', 'Expert / legacy configuration')); ?></summary>
		<div class="base3-agent-config-expert-body">
			<div class="base3-agent-config-row"><div class="base3-agent-config-label"><?php echo $e($t('enable_overrides_label', 'Enable overrides')); ?></div><div><input type="hidden" name="expert_overrides_enabled" value="0"><label><input type="checkbox" name="expert_overrides_enabled" value="1"<?php echo $checked($values['expert_overrides_enabled']??false); ?>><?php echo $e($t('apply_overrides', 'Apply direct capability source and selection overrides below')); ?></label></div></div>

			<h4><?php echo $e($t('direct_capability_sources', 'Direct capability sources')); ?></h4>
			<div class="base3-agent-config-grid">
<?php $sourceGroups=['tools'=>$t('configured_tools', 'Configured tools'),'providers'=>$t('capability_providers', 'Capability providers'),'modules'=>$t('modules', 'Modules'),'resourceProviders'=>$t('resource_providers', 'Resource providers'),'promptProviders'=>$t('prompt_providers', 'Prompt providers')]; foreach($sourceGroups as $key=>$label): $options=(array)($capabilityComponentOptions[$key]??[]); ?>
				<div><label><?php echo $e($label); ?></label><select name="capability_sources[<?php echo $e($key); ?>][]" multiple>
<?php foreach($options as $option): $id=(string)($option['id']??''); if($id==='') continue; ?><option value="<?php echo $e($id); ?>"<?php echo $selectedIn($capabilitySources[$key]??[],$id); ?>><?php echo $e(($option['label']??$id).' ('.$id.')'); ?></option><?php endforeach; ?>
				</select></div>
<?php endforeach; ?>
			</div>
			<input type="hidden" name="capability_sources[strict]" value="0"><label><input type="checkbox" name="capability_sources[strict]" value="1"<?php echo $checked($capabilitySources['strict']??true); ?>><?php echo $e($t('strict_source_resolution', 'Strict source resolution')); ?></label>

			<h4><?php echo $e($t('direct_selection_override', 'Direct selection override')); ?></h4>
			<div class="base3-agent-config-grid">
				<div><label><?php echo $e($t('enabled_label', 'Enabled')); ?></label><input type="hidden" name="capability_selection[enabled]" value="0"><label><input type="checkbox" name="capability_selection[enabled]" value="1"<?php echo $checked($capabilitySelection['enabled']??true); ?>><?php echo $e($t('preselect_tools', 'Preselect tools')); ?></label></div>
				<div><label><?php echo $e($t('strategy_label', 'Strategy')); ?></label><select name="capability_selection[strategy]"><option value="hybrid"<?php echo $selected($capabilitySelection['strategy']??'hybrid','hybrid'); ?>><?php echo $e($t('strategy_hybrid', 'Hybrid')); ?></option><option value="all"<?php echo $selected($capabilitySelection['strategy']??'hybrid','all'); ?>><?php echo $e($t('strategy_all', 'All')); ?></option></select></div>
				<div><label><?php echo $e($t('maximum_tools', 'Maximum tools')); ?></label><input type="number" min="1" max="512" name="capability_selection[max_tools]" value="<?php echo $e($capabilitySelection['max_tools']??16); ?>"></div>
				<div><label><?php echo $e($t('select_all_threshold', 'Select-all threshold')); ?></label><input type="number" min="0" max="512" name="capability_selection[select_all_threshold]" value="<?php echo $e($capabilitySelection['select_all_threshold']??16); ?>"></div>
<?php foreach(['include_tools'=>$t('include_tools', 'Include tools'),'exclude_tools'=>$t('exclude_tools', 'Exclude tools'),'include_tags'=>$t('include_tags', 'Include tags'),'exclude_tags'=>$t('exclude_tags', 'Exclude tags'),'include_categories'=>$t('include_categories', 'Include categories'),'exclude_categories'=>$t('exclude_categories', 'Exclude categories'),'always_available'=>$t('always_available', 'Always available')] as $key=>$label): ?>
				<div><label><?php echo $e($label); ?></label><textarea name="capability_selection[<?php echo $e($key); ?>]" rows="3"><?php echo $e($listText($capabilitySelection[$key]??[])); ?></textarea></div>
<?php endforeach; ?>
				<div><label><?php echo $e($t('sticky_label', 'Sticky')); ?></label><input type="hidden" name="capability_selection[sticky]" value="0"><label><input type="checkbox" name="capability_selection[sticky]" value="1"<?php echo $checked($capabilitySelection['sticky']??true); ?>><?php echo $e($t('keep_selection_stable', 'Keep selection stable')); ?></label></div>
			</div>

			<h4><?php echo $e($t('direct_component_presets', 'Direct component presets')); ?></h4>
			<div data-agent-components>
				<div data-agent-component-items></div>
				<button type="button" class="btn btn-default" data-agent-component-add><?php echo $e($t('add_direct_component', 'Add direct component')); ?></button>
			</div>
			<input type="hidden" name="agent_components_json" data-agent-components-json value="[]">
			<input type="hidden" name="agent_components_json_b64" data-agent-components-b64 value="">

			<div class="base3-agent-config-export">
				<button type="button" class="btn btn-default" data-agent-config-export><?php echo $e($t('copy_complete_configuration', 'Copy complete agent configuration')); ?></button>
				<span class="base3-agent-config-export-status" data-agent-config-export-status><?php echo $e($t('export_help', 'Exports current form values plus resolved chat-model preset, orchestrator, tool, memory, context and component-preset configuration. Secret-like fields are redacted.')); ?></span>
			</div>
		</div>
	</details>
</div>

<script>
(function(){
	var root=document.getElementById(<?php echo json_encode($rootId); ?>); if(!root||root.dataset.ready==='1')return; root.dataset.ready='1';
	var presets=<?php echo json_encode(array_values($agentComponentPresets), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	var initialComponents=<?php echo json_encode(array_values($agentComponents), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	var exportCatalog=<?php echo json_encode($exportCatalog, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	var i18n=<?php echo json_encode([
		'selectPreset'=>$t('select_preset', 'Select preset'),
		'active'=>$t('active', 'active'),
		'order'=>$t('order', 'order'),
		'remove'=>$t('remove', 'Remove'),
		'clipboardRejected'=>$t('clipboard_rejected', 'Clipboard copy was rejected by the browser.'),
		'exportCopied'=>$t('export_copied', 'Complete configuration copied to clipboard.'),
		'exportCopiedWarnings'=>$t('export_copied_warnings', 'Complete configuration copied to clipboard with %d diagnostic warning(s).'),
		'exportFailedPrefix'=>$t('export_failed_prefix', 'Export failed:'),
		'memoryProfileEmpty'=>$t('memory_profile_empty_warning', 'The selected memory profile contains no conversation-memory preset.'),
		'memoryPresetMaxTooSmall'=>$t('memory_preset_max_too_small_warning', 'Conversation-memory preset "%s" is configured with max=%s. One complete user/assistant turn requires at least 2 messages; 10 or more is recommended.'),
		'memoryPresetWindowSmall'=>$t('memory_preset_window_small_warning', 'Conversation-memory preset "%s" keeps only %s messages. Multi-turn recall will be very limited; 10 or more is recommended.'),
		'memoryProfileMultiple'=>$t('memory_profile_multiple_warning', 'The selected memory profile contains %s conversation-memory presets. Writes may be duplicated.')
	], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
	var items=root.querySelector('[data-agent-component-items]');
	function enc(v){v=String(v||''); return btoa(unescape(encodeURIComponent(v)));}
	function selectedValues(name){var fields=root.querySelectorAll('[name="'+name+'"]');var values=[];Array.prototype.forEach.call(fields,function(field){if(field.tagName==='SELECT'){Array.prototype.forEach.call(field.options,function(option){if(option.selected)values.push(option.value)})}else if((field.type==='checkbox'||field.type==='radio')&&field.checked){values.push(field.value)}});return values;}
	function presetById(id){return presets.find(function(p){return String(p.id||'')===String(id||'')})||null;}
	function addComponent(component){component=component||{}; var row=document.createElement('div'); row.className='base3-agent-config-component-row'; row.dataset.componentRow='1';
		var select=document.createElement('select'); select.dataset.field='preset'; select.innerHTML='<option value="">'+i18n.selectPreset+'</option>'; presets.forEach(function(p){var o=document.createElement('option');o.value=p.id;o.textContent=(p.label||p.id)+' ['+(Array.isArray(p.capabilities)?p.capabilities.join('+'):'')+']';o.selected=String(component.preset||'')===String(p.id||'');select.appendChild(o)}); row.appendChild(select);
		var active=document.createElement('label'); active.innerHTML='<input type="checkbox" data-field="enabled" '+(component.enabled===false?'':'checked')+'> '+i18n.active; row.appendChild(active);
		var order=document.createElement('input'); order.type='number'; order.placeholder=i18n.order; order.dataset.field='order'; order.value=component.order||''; row.appendChild(order);
		var remove=document.createElement('button'); remove.type='button'; remove.textContent=i18n.remove; remove.onclick=function(){row.remove();sync()}; row.appendChild(remove); items.appendChild(row); row.addEventListener('change',sync); row.addEventListener('input',sync);
	}
	function buildComponents(){if(!items)return[]; return Array.prototype.map.call(items.querySelectorAll('[data-component-row]'),function(row){var preset=row.querySelector('[data-field="preset"]').value; if(!preset)return null; var meta=presetById(preset)||{}; var caps=Array.isArray(meta.capabilities)?meta.capabilities:[]; var order=row.querySelector('[data-field="order"]').value; var c={preset:preset,attach_as:caps,enabled:row.querySelector('[data-field="enabled"]').checked}; if(order!=='')c.order=parseInt(order,10); return c}).filter(Boolean);}
	function sync(){var components=buildComponents(); var text=JSON.stringify(components); var cj=root.querySelector('[data-agent-components-json]');var cb=root.querySelector('[data-agent-components-b64]');if(cj)cj.value=text;if(cb)cb.value=enc(text);}
	function renderComponents(list){if(!items)return;items.innerHTML='';(Array.isArray(list)?list:[]).forEach(addComponent);sync();}
	var add=root.querySelector('[data-agent-component-add]'); if(add)add.onclick=function(){addComponent({enabled:true});sync()};
	var profileSelect=root.querySelector('[data-orchestrator-profile]'); var summary=root.querySelector('[data-orchestrator-profile-summary]');
	function updateSummary(){if(!profileSelect||!summary)return;var o=profileSelect.options[profileSelect.selectedIndex];summary.innerHTML='';if(!o)return;var d=document.createElement('div');d.textContent=o.dataset.description||'';summary.appendChild(d);var stages=document.createElement('div');stages.className='base3-agent-config-stage-preview';String(o.dataset.stages||'').split(',').filter(Boolean).forEach(function(id){var p=document.createElement('span');p.className='base3-agent-config-stage-pill';p.textContent=id;stages.appendChild(p)});summary.appendChild(stages)}
	if(profileSelect)profileSelect.addEventListener('change',updateSummary); updateSummary();
	root.addEventListener('input',sync);root.addEventListener('change',sync);renderComponents(initialComponents);
	function field(name,scope){return (scope||root).querySelector('[name="'+name+'"]');}
	function fieldValue(name,scope){var f=field(name,scope);if(!f)return'';if(f.type==='checkbox')return!!f.checked;return f.value==null?'':String(f.value);}
	function boolField(name){var fields=root.querySelectorAll('[name="'+name+'"]');for(var i=0;i<fields.length;i++){if(fields[i].type==='checkbox'&&fields[i].checked)return true}return false;}
	function parseJson(text,fallback){try{var value=JSON.parse(String(text||''));return value&&typeof value==='object'?value:fallback}catch(error){return fallback}}
	function catalogRecord(group,id){var records=exportCatalog&&exportCatalog[group]&&typeof exportCatalog[group]==='object'?exportCatalog[group]:{};return id&&records[id]?records[id]:null;}
	function clone(value){if(value===undefined)return null;return JSON.parse(JSON.stringify(value));}
	function isSensitiveKey(key){key=String(key||'').toLowerCase();var names=['password','passwd','passphrase','secret','secretvalue','clientsecret','token','accesstoken','refreshtoken','apikey','api_key','privatekey','private_key','authorization','credential','credentials'];return names.some(function(name){return key===name||key.endsWith('_'+name)});}
	function redact(value,key){if(isSensitiveKey(key))return'***REDACTED***';if(!value||typeof value!=='object')return value;if(Array.isArray(value))return value.map(function(item){return redact(item,'')});var result={};Object.keys(value).forEach(function(itemKey){result[itemKey]=redact(value[itemKey],itemKey)});return result;}
	function readFlatFormValues(){var scope=root.closest('form')||root.closest('[data-base3-chatbot-config-root]')||root.closest('[data-base3-agent-config-display-root]')||root.closest('[data-base3-agent-fields]')||root;var values={};Array.prototype.forEach.call(scope.querySelectorAll('input,select,textarea'),function(input){if(!input.name||input.disabled||String(input.name).endsWith('_b64'))return;if((input.type==='checkbox'||input.type==='radio')&&!input.checked)return;var value=input.type==='checkbox'?true:input.value;if(typeof value==='string'){var trimmed=value.trim();if(trimmed.charAt(0)==='{'||trimmed.charAt(0)==='['){var decoded=parseJson(trimmed,null);if(decoded!==null)value=decoded}}value=redact(value,input.name);if(Object.prototype.hasOwnProperty.call(values,input.name)){if(!Array.isArray(values[input.name]))values[input.name]=[values[input.name]];values[input.name].push(value)}else{values[input.name]=value}});return values;}
	function outerFieldValue(names){var scope=root.closest('form')||root.closest('[data-base3-chatbot-config-root]')||root.closest('[data-base3-agent-config-display-root]')||root.closest('[data-base3-agent-fields]')||root;for(var i=0;i<names.length;i++){var f=scope.querySelector('[name="'+names[i]+'"]');if(f&&String(f.value||'')!=='')return String(f.value)}return'';}
	function selectedCapabilitySources(){var result={};['tools','providers','modules','resourceProviders','promptProviders'].forEach(function(key){result[key]=selectedValues('capability_sources['+key+'][]')});result.strict=boolField('capability_sources[strict]');return result;}
	function lines(name){return String(fieldValue(name)||'').split('\n').map(function(value){return value.trim()}).filter(function(value){return value!==''});}
	function currentAgentConfig(){sync();return{chatmodel:fieldValue('chatmodel'),system_prompt:fieldValue('system_prompt'),orchestrator_profile:fieldValue('orchestrator_profile')||'standard',tool_profiles:selectedValues('tool_profiles[]'),memory_profile:fieldValue('memory_profile'),context_profile:fieldValue('context_profile'),expert_overrides_enabled:boolField('expert_overrides_enabled'),agent_components:buildComponents(),capability_sources:selectedCapabilitySources(),capability_selection:{enabled:boolField('capability_selection[enabled]'),strategy:fieldValue('capability_selection[strategy]')||'hybrid',max_tools:Number(fieldValue('capability_selection[max_tools]')||16),select_all_threshold:Number(fieldValue('capability_selection[select_all_threshold]')||16),include_tools:lines('capability_selection[include_tools]'),exclude_tools:lines('capability_selection[exclude_tools]'),include_tags:lines('capability_selection[include_tags]'),exclude_tags:lines('capability_selection[exclude_tags]'),include_categories:lines('capability_selection[include_categories]'),exclude_categories:lines('capability_selection[exclude_categories]'),always_available:lines('capability_selection[always_available]'),sticky:boolField('capability_selection[sticky]')}};}
	function resolveToolProfiles(ids){return(ids||[]).map(function(id){var profile=clone(catalogRecord('tool_profiles',id))||{id:id,missing:true};var tools=Array.isArray(profile.tools)?profile.tools:[];profile.component_presets=tools.map(function(presetId){return{preset_id:presetId,preset:clone(catalogRecord('component_presets',presetId))}});return profile});}
	function resolvePresetProfile(group,id,field){if(!id)return null;var profile=clone(catalogRecord(group,id))||{id:id,missing:true};var ids=Array.isArray(profile[field])?profile[field]:(Array.isArray(profile.presets)?profile.presets:[]);profile.component_presets=ids.map(function(presetId){return{preset_id:presetId,preset:clone(catalogRecord('component_presets',String(presetId||'')))}});return profile;}
	function resolveMemoryProfile(id){return resolvePresetProfile('memory_profiles',id,'memories');}
	function resolveContextProfile(id){return resolvePresetProfile('context_profiles',id,'contexts');}
	function resolveDirectComponents(components){return(components||[]).map(function(component){return{component:clone(component),component_preset:clone(catalogRecord('component_presets',String(component.preset||'')))}});}
	function formatMessage(message,values){values=Array.isArray(values)?values:[];var index=0;return String(message||'').replace(/%s/g,function(){return index<values.length?String(values[index++]):'%s'})}
	function memoryWarnings(memoryProfile){var warnings=[];if(!memoryProfile)return warnings;var presets=Array.isArray(memoryProfile.component_presets)?memoryProfile.component_presets:[];if(presets.length===0)warnings.push({severity:'error',code:'conversation-memory-missing',message:i18n.memoryProfileEmpty});presets.forEach(function(entry){var preset=entry.preset||{};var config=preset.config&&typeof preset.config==='object'?preset.config:{};var max=Number(config.max);if(Number.isFinite(max)&&max<2){warnings.push({severity:'error',code:'conversation-memory-max-too-small',message:formatMessage(i18n.memoryPresetMaxTooSmall,[entry.preset_id||'',max])})}else if(Number.isFinite(max)&&max<6){warnings.push({severity:'warning',code:'conversation-memory-window-small',message:formatMessage(i18n.memoryPresetWindowSmall,[entry.preset_id||'',max])})}});if(presets.length>1)warnings.push({severity:'warning',code:'multiple-conversation-memories',message:formatMessage(i18n.memoryProfileMultiple,[presets.length])});return warnings;}
	function buildExport(){var config=currentAgentConfig();var memoryProfile=resolveMemoryProfile(config.memory_profile);var contextProfile=resolveContextProfile(config.context_profile);var identity={kind:outerFieldValue(['chatbot_config_group'])?'chatbot':'agent',settings_group:outerFieldValue(['chatbot_config_group','agent_config_group'])||'agent',settings_name:outerFieldValue(['chatbot_config_name','agent_id','agent_config_name'])||'',instance_hint:outerFieldValue(['chatbot_config_name','agent_id','agent_config_name'])||''};var resolved={chatmodel_preset:clone(catalogRecord('component_presets',config.chatmodel)),orchestrator_profile:clone(catalogRecord('orchestrator_profiles',config.orchestrator_profile)),tool_profiles:resolveToolProfiles(config.tool_profiles),memory_profile:memoryProfile,context_profile:contextProfile,direct_components:resolveDirectComponents(config.agent_components)};return redact({schema:'missionbay-agent-configuration-export',schema_version:1,exported_at:new Date().toISOString(),identity:identity,agent_config:config,resolved:resolved,diagnostics:{warnings:memoryWarnings(memoryProfile)},form_values_flat:readFlatFormValues()},'');}
	async function copyText(text){if(navigator.clipboard&&typeof navigator.clipboard.writeText==='function'){await navigator.clipboard.writeText(text);return}var area=document.createElement('textarea');area.value=text;area.setAttribute('readonly','readonly');area.style.position='fixed';area.style.left='-9999px';document.body.appendChild(area);area.select();var ok=document.execCommand('copy');document.body.removeChild(area);if(!ok)throw new Error(i18n.clipboardRejected);}
	var exportButton=root.querySelector('[data-agent-config-export]');var exportStatus=root.querySelector('[data-agent-config-export-status]');if(exportButton)exportButton.addEventListener('click',async function(){try{var payload=buildExport();await copyText(JSON.stringify(payload,null,2));var warningCount=payload.diagnostics&&Array.isArray(payload.diagnostics.warnings)?payload.diagnostics.warnings.length:0;if(exportStatus){exportStatus.dataset.state=warningCount>0?'warning':'ok';exportStatus.textContent=warningCount>0?i18n.exportCopiedWarnings.replace('%d',String(warningCount)):i18n.exportCopied}}catch(error){if(exportStatus){exportStatus.dataset.state='error';exportStatus.textContent=i18n.exportFailedPrefix+' '+String(error&&error.message?error.message:error)}}});
	function setValue(name,value){var fields=root.querySelectorAll('[name="'+name.replace(/"/g,'\\"')+'"]');fields.forEach(function(f){if(f.type==='checkbox'){f.checked=!!value}else{f.value=value==null?'':String(value)}})}
	function setMulti(name,values){values=Array.isArray(values)?values.map(String):[];var escaped=name.replace(/"/g,'\\"');var select=root.querySelector('select[multiple][name="'+escaped+'"]');if(select){Array.prototype.forEach.call(select.options,function(o){o.selected=values.indexOf(String(o.value))!==-1})}root.querySelectorAll('input[type="checkbox"][name="'+escaped+'"]').forEach(function(field){field.checked=values.indexOf(String(field.value))!==-1})}
	root.__base3AgentRuntimeConfigUpdateValues=function(v){if(!v||typeof v!=='object')return;setValue('chatmodel',v.chatmodel||'');setValue('system_prompt',v.system_prompt||'');setValue('orchestrator_profile',v.orchestrator_profile||'standard');setMulti('tool_profiles[]',v.tool_profiles||[]);setValue('memory_profile',v.memory_profile||'');setValue('context_profile',v.context_profile||'');setValue('expert_overrides_enabled',!!v.expert_overrides_enabled);renderComponents(v.agent_components||[]);var src=v.capability_sources||{};['tools','providers','modules','resourceProviders','promptProviders'].forEach(function(k){setMulti('capability_sources['+k+'][]',src[k]||[])});var sel=v.capability_selection||{};setValue('capability_selection[strategy]',sel.strategy||'hybrid');setValue('capability_selection[max_tools]',sel.max_tools==null?16:sel.max_tools);setValue('capability_selection[select_all_threshold]',sel.select_all_threshold==null?16:sel.select_all_threshold);['include_tools','exclude_tools','include_tags','exclude_tags','include_categories','exclude_categories','always_available'].forEach(function(k){setValue('capability_selection['+k+']',Array.isArray(sel[k])?sel[k].join('\n'):'')});updateSummary();sync()};
	root.__base3AgentRuntimeConfigPrepareSubmit=function(){sync();return true};
})();
</script>
