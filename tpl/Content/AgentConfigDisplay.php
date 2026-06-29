<?php
        $messages = is_array($this->_['messages'] ?? null) ? $this->_['messages'] : [];
        $values = is_array($this->_['values'] ?? null) ? $this->_['values'] : [];
        $formId = (string)($this->_['form_id'] ?? 'base3_agent_config');
        $group = (string)($this->_['group'] ?? '');
        $name = (string)($this->_['name'] ?? '');
        $renderForm = !empty($this->_['render_form']);
        $saveMode = (string)($this->_['save_mode'] ?? 'ajax');
        $saveUrl = (string)($this->_['save_url'] ?? '');
        $useAjax = $saveMode === 'ajax';
        $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<style>
        .base3-agent-config-display {
                width: 100%;
                max-width: 980px;
                margin: 0;
        }

        .base3-agent-config-display h2 {
                margin: 0 0 8px;
        }

        .base3-agent-config-description {
                margin: 0 0 12px;
                color: #555;
        }

        .base3-agent-config-instance {
                margin: 0 0 18px;
                padding: 7px 10px;
                border-left: 3px solid #ddd;
                background: #fafafa;
                color: #666;
                font-size: 12px;
        }

        .base3-agent-config-instance code {
                color: inherit;
                font-size: 12px;
                background: transparent;
        }

        .base3-agent-config-messages {
                margin: 0 0 12px;
        }

        .base3-agent-config-message {
                margin: 0 0 12px;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-left-width: 4px;
                background: #fff;
        }

        .base3-agent-config-message-success {
                border-left-color: #5cb85c;
        }

        .base3-agent-config-message-danger {
                border-left-color: #d9534f;
        }

        .base3-agent-config-message-info {
                border-left-color: #5bc0de;
        }

        .base3-agent-config-actions {
                margin-top: 4px;
        }

        .base3-agent-config-submit {
                min-width: 120px;
                padding: 7px 14px;
                cursor: pointer;
        }

        .base3-agent-config-submit[disabled] {
                cursor: wait;
                opacity: 0.65;
        }
</style>

<div class="base3-agent-config-display">
<?php if ($renderForm) { ?>
        <form
                id="<?php echo $e($formId); ?>"
                method="post"
                action="<?php echo $e($this->_['form_action'] ?? ''); ?>"
                data-base3-agent-config-display-root="1"
                data-save-url="<?php echo $e($saveUrl); ?>"
                data-save-mode="<?php echo $e($saveMode); ?>"
        >
<?php } else { ?>
        <div
                id="<?php echo $e($formId); ?>"
                class="base3-agent-config-fields"
                data-base3-agent-config-display-root="1"
                data-save-url="<?php echo $e($saveUrl); ?>"
                data-save-mode="<?php echo $e($saveMode); ?>"
        >
<?php } ?>
                <h2><?php echo $e($this->_['title'] ?? 'Agent Configuration'); ?></h2>

<?php if (!empty($this->_['description'])) { ?>
                <p class="base3-agent-config-description"><?php echo $e($this->_['description']); ?></p>
<?php } ?>

                <div class="base3-agent-config-instance">
                        Instance:
                        <code><?php echo $e($group); ?></code>
                        /
                        <code><?php echo $e($name); ?></code>
                </div>

<?php include DIR_PLUGIN . 'MissionBay/tpl/Content/AgentFormFields.php'; ?>

                <div class="base3-agent-config-actions">
                        <div class="base3-agent-config-messages" data-base3-agent-config-display-messages>
<?php foreach ($messages as $message) {
        $type = preg_replace('/[^a-z]/', '', (string)($message['type'] ?? 'info'));
        if ($type === '') {
                $type = 'info';
        }
?>
                                <div class="base3-agent-config-message base3-agent-config-message-<?php echo $e($type); ?> alert alert-<?php echo $e($type); ?>">
                                        <?php echo $e($message['text'] ?? ''); ?>
                                </div>
<?php } ?>
                        </div>

                        <button
                                type="<?php echo $renderForm ? 'submit' : 'button'; ?>"
                                class="btn btn-primary base3-agent-config-submit"
                                data-base3-agent-config-display-save="1"
                        >
                                <?php echo $e($this->_['submit_label'] ?? 'Save'); ?>
                        </button>
                </div>

<?php if ($renderForm) { ?>
        </form>
<?php } else { ?>
        </div>
<?php } ?>
</div>

<?php if ($useAjax) { ?>
<script>
(function() {
        var root = document.getElementById(<?php echo json_encode($formId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);

        if (!root || root.getAttribute('data-base3-agent-config-display-ready') === '1') {
                return;
        }

        root.setAttribute('data-base3-agent-config-display-ready', '1');

        var button = root.querySelector('[data-base3-agent-config-display-save]');
        var messages = root.querySelector('[data-base3-agent-config-display-messages]');
        var saveUrl = root.getAttribute('data-save-url') || <?php echo json_encode($saveUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var agentFields = root.querySelector('[data-base3-agent-fields]');

        if (!button || !saveUrl) {
                return;
        }

        function escapeHtml(value) {
                return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
        }

        function prepareNestedFormData() {
                if (agentFields && typeof agentFields.__base3AgentPrepareSubmit === 'function') {
                        agentFields.__base3AgentPrepareSubmit();
                }
        }

        function collectFormData() {
                prepareNestedFormData();

                if (root.tagName && root.tagName.toLowerCase() === 'form') {
                        return new FormData(root);
                }

                var formData = new FormData();
                var fields = root.querySelectorAll('input, select, textarea');

                fields.forEach(function(field) {
                        if (!field.name || field.disabled) {
                                return;
                        }

                        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                                return;
                        }

                        formData.append(field.name, field.value);
                });

                return formData;
        }

        function renderMessages(items) {
                if (!messages) {
                        return;
                }

                if (!Array.isArray(items) || items.length === 0) {
                        messages.innerHTML = '';
                        return;
                }

                messages.innerHTML = items.map(function(item) {
                        var type = String(item.type || 'info').replace(/[^a-z]/g, '') || 'info';
                        var text = item.text || '';

                        return '<div class="base3-agent-config-message base3-agent-config-message-' + escapeHtml(type) + ' alert alert-' + escapeHtml(type) + '">' + escapeHtml(text) + '</div>';
                }).join('');
        }

        function updateValues(values) {
                if (agentFields && typeof agentFields.__base3AgentUpdateValues === 'function') {
                        agentFields.__base3AgentUpdateValues(values || {});
                }
        }

        function save(event) {
                if (event) {
                        event.preventDefault();
                }

                button.disabled = true;

                fetch(saveUrl, {
                        method: 'POST',
                        body: collectFormData(),
                        credentials: 'same-origin',
                        headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                        }
                })
                        .then(function(response) {
                                return response.json();
                        })
                        .then(function(json) {
                                renderMessages(json.messages || []);
                                updateValues(json.values || null);
                        })
                        .catch(function(error) {
                                renderMessages([
                                        {
                                                type: 'danger',
                                                text: 'Settings could not be saved: ' + error.message
                                        }
                                ]);
                        })
                        .finally(function() {
                                button.disabled = false;
                        });
        }

        if (root.tagName && root.tagName.toLowerCase() === 'form') {
                root.addEventListener('submit', save);
        }
        else {
                button.addEventListener('click', save);
        }
})();
</script>
<?php } ?>
