(function () {
    'use strict';

    var root = document.querySelector('[data-widget-root]');
    if (!root) return;

    var state = { token: null, conversation: null, widget: null, sending: false };
    var embedded = root.dataset.embedded === 'true';
    var panel = root.querySelector('[data-widget-panel]');
    var launcher = root.querySelector('[data-widget-launcher]');
    var notice = root.querySelector('[data-widget-notice]');
    var chatStage = root.querySelector('[data-chat-stage]');
    var prechatStage = root.querySelector('[data-prechat-stage]');
    var messages = root.querySelector('[data-widget-messages]');
    var starters = root.querySelector('[data-widget-starters]');
    var composer = root.querySelector('[data-message-form]');
    var messageInput = composer.querySelector('textarea');
    var handoff = root.querySelector('[data-widget-handoff]');

    function setNotice(text, isError) {
        notice.textContent = text || '';
        notice.style.background = isError ? '#fff0f2' : '';
        notice.style.color = isError ? '#b83246' : '';
    }

    function notifyParent(open) {
        if (!embedded || window.parent === window) return;
        window.parent.postMessage({ source: 'neuraldesk-widget', widget: root.dataset.widgetId, open: open }, root.dataset.parentOrigin);
    }

    function toggle(open) {
        if (!embedded) return;
        panel.hidden = !open;
        launcher.hidden = open;
        launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
        root.classList.toggle('is-open', open);
        notifyParent(open);
        if (open) window.setTimeout(function () { messageInput.focus(); }, 120);
    }

    async function request(url, options) {
        var response = await fetch(url, Object.assign({
            headers: Object.assign({ 'Accept': 'application/json', 'Content-Type': 'application/json' }, state.token ? { 'Authorization': 'Bearer ' + state.token } : {})
        }, options || {}));
        var payload = await response.json().catch(function () { return {}; });
        if (!response.ok) {
            var errors = payload.errors || {};
            var first = Object.keys(errors).length ? errors[Object.keys(errors)[0]][0] : null;
            throw new Error(first || payload.message || 'The request could not be completed.');
        }
        return payload.data;
    }

    function addMessage(body, actor, uuid) {
        if (uuid && Array.prototype.some.call(messages.children, function (message) {
            return message.dataset.messageId === uuid;
        })) return;
        var item = document.createElement('div');
        item.className = 'nd-widget-message ' + (actor === 'visitor' ? 'is-visitor' : actor === 'status' ? 'is-status' : 'is-ai');
        item.textContent = body || '';
        if (uuid) item.dataset.messageId = uuid;
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
        return item;
    }

    function newUuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        var randomBytes = new Uint8Array(16);
        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
            window.crypto.getRandomValues(randomBytes);
            randomBytes[6] = (randomBytes[6] & 15) | 64;
            randomBytes[8] = (randomBytes[8] & 63) | 128;
            return Array.prototype.map.call(randomBytes, function (byte, index) {
                var separator = index === 4 || index === 6 || index === 8 || index === 10 ? '-' : '';
                return separator + byte.toString(16).padStart(2, '0');
            }).join('');
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
            var random = Math.random() * 16 | 0;
            var value = character === 'x' ? random : (random & 3 | 8);
            return value.toString(16);
        });
    }

    function fieldInput(field) {
        var wrapper = document.createElement('div');
        wrapper.className = 'nd-widget-field';
        var label = document.createElement('label');
        label.textContent = field.label + (field.required ? ' *' : '');
        label.htmlFor = 'ndw-field-' + field.key;
        var input;
        if (field.type === 'select') {
            input = document.createElement('select');
            var blank = document.createElement('option');
            blank.value = '';
            blank.textContent = 'Select an option';
            input.appendChild(blank);
            (field.options || []).forEach(function (value) {
                var option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                input.appendChild(option);
            });
        } else if (field.type === 'textarea') {
            input = document.createElement('textarea');
            input.rows = 3;
        } else {
            input = document.createElement('input');
            input.type = field.type === 'email' ? 'email' : field.type === 'phone' ? 'tel' : 'text';
        }
        input.id = 'ndw-field-' + field.key;
        input.name = field.key;
        input.required = Boolean(field.required);
        input.placeholder = field.placeholder || '';
        wrapper.appendChild(label);
        wrapper.appendChild(input);
        return wrapper;
    }

    function showChat() {
        prechatStage.hidden = true;
        chatStage.hidden = false;
        if (!messages.children.length) addMessage(state.widget.welcome_message, 'ai');
        starters.replaceChildren();
        (state.widget.starter_questions || []).forEach(function (question) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = question;
            button.addEventListener('click', function () { sendMessage(question); });
            starters.appendChild(button);
        });
        handoff.hidden = !state.widget.handoff_available || !state.conversation;
        messageInput.focus();
    }

    function showPrechat(fields) {
        var container = root.querySelector('[data-prechat-fields]');
        container.replaceChildren();
        fields.forEach(function (field) { container.appendChild(fieldInput(field)); });
        prechatStage.hidden = false;
        chatStage.hidden = true;
    }

    async function bootstrap() {
        try {
            var data = await request(root.dataset.bootstrapUrl, {
                method: 'POST', body: JSON.stringify({ access_proof: root.dataset.accessProof })
            });
            state.token = data.session.token;
            state.widget = data.widget;
            document.documentElement.style.setProperty('--ndw-accent', data.widget.accent_color);
            root.querySelector('[data-widget-name]').textContent = data.widget.display_name;
            root.querySelector('[data-widget-availability]').textContent = data.widget.online ? 'Online — answers in seconds' : 'Unavailable';
            setNotice('');
            if (data.widget.prechat.enabled) showPrechat(data.widget.prechat.fields); else showChat();
        } catch (error) {
            setNotice(error.message || 'Chat is currently unavailable.', true);
            composer.querySelector('button').disabled = true;
        }
    }

    async function poll(messageUuid, attempts) {
        if (!state.conversation || attempts > 60) {
            if (attempts > 60) setNotice('The answer is taking longer than expected. Please try again shortly.', true);
            return;
        }
        try {
            var url = root.dataset.conversationUrl.replace('__CONVERSATION__', encodeURIComponent(state.conversation));
            var data = await request(url, { method: 'GET' });
            var answered = false;
            (data.messages || []).forEach(function (message) {
                addMessage(message.body, message.actor, message.uuid);
                if (message.reply_to_uuid === messageUuid) answered = true;
            });
            handoff.hidden = !state.widget.handoff_available;
            if (!answered && data.status === 'open_ai') window.setTimeout(function () { poll(messageUuid, attempts + 1); }, 1000);
            if (!answered && data.status !== 'open_ai') setNotice('A support person has been requested.');
        } catch (error) {
            if (attempts < 3) window.setTimeout(function () { poll(messageUuid, attempts + 1); }, 1200);
            else setNotice(error.message, true);
        }
    }

    async function sendMessage(text) {
        text = String(text || '').trim();
        if (!text || state.sending || !state.token) return;
        state.sending = true;
        composer.querySelector('button').disabled = true;
        starters.replaceChildren();
        var optimistic = addMessage(text, 'visitor');
        messageInput.value = '';
        setNotice('Preparing an answer…');
        try {
            var data = await request(root.dataset.messageUrl, {
                method: 'POST',
                body: JSON.stringify({ message: text, idempotency_key: newUuid(), conversation_uuid: state.conversation })
            });
            state.conversation = data.conversation_uuid;
            optimistic.dataset.messageId = data.message_uuid;
            setNotice('');
            handoff.hidden = !state.widget.handoff_available;
            poll(data.message_uuid, 0);
        } catch (error) {
            optimistic.remove();
            setNotice(error.message, true);
        } finally {
            state.sending = false;
            composer.querySelector('button').disabled = false;
        }
    }

    launcher?.addEventListener('click', function () { toggle(true); });
    root.querySelector('[data-widget-close]')?.addEventListener('click', function () { toggle(false); });
    composer.addEventListener('submit', function (event) { event.preventDefault(); sendMessage(messageInput.value); });
    messageInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(messageInput.value); }
    });
    root.querySelector('[data-prechat-form]').addEventListener('submit', async function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        var button = form.querySelector('button');
        button.disabled = true;
        var values = {};
        new FormData(form).forEach(function (value, key) { values[key] = value; });
        form.querySelectorAll('.nd-widget-error').forEach(function (item) { item.remove(); });
        try {
            await request(root.dataset.prechatUrl, { method: 'POST', body: JSON.stringify({ fields: values }) });
            showChat();
        } catch (error) {
            setNotice(error.message, true);
        } finally {
            button.disabled = false;
        }
    });
    handoff.addEventListener('click', async function () {
        if (!state.conversation) return;
        handoff.disabled = true;
        try {
            await request(root.dataset.handoffUrl, { method: 'POST', body: JSON.stringify({ conversation_uuid: state.conversation }) });
            setNotice('A support person has been requested.');
            handoff.hidden = true;
        } catch (error) { setNotice(error.message, true); handoff.disabled = false; }
    });

    if (embedded) notifyParent(false);
    bootstrap();
})();
