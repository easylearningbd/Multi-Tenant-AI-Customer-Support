(function () {
    'use strict';

    var root = document.querySelector('[data-widget-root]');
    if (!root) return;

    var state = {
        token: null,
        conversation: null,
        conversationStatus: null,
        widget: null,
        sending: false,
        pendingReplies: Object.create(null),
        typingIndicator: null
    };
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
    var submitButton = composer.querySelector('button');
    var handoff = root.querySelector('[data-widget-handoff]');

    function syncConversationControls() {
        var aiPaused = Boolean(state.conversation && state.conversationStatus && state.conversationStatus !== 'open_ai');
        messageInput.disabled = aiPaused;
        submitButton.disabled = state.sending || aiPaused || !state.token;
        handoff.hidden = !state.widget || !state.widget.handoff_available || !state.conversation || aiPaused;
        handoff.disabled = aiPaused;
    }

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

    function cleanReply(body) {
        return String(body || '')
            .replace(/[ \t]*\[source:[^\]\r\n]*\]/giu, '')
            .replace(/\r\n?/g, '\n')
            .replace(/[ \t]+\n/g, '\n')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function formatTimestamp(value) {
        var date = value ? new Date(value) : new Date();
        if (Number.isNaN(date.getTime())) date = new Date();
        var weekday = new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(date);
        var calendarDate = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
        var time = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(date);

        return { label: weekday + ', ' + calendarDate + ' \u00b7 ' + time, iso: date.toISOString() };
    }

    function appendMessageContent(container, body) {
        var cleaned = cleanReply(body);
        var blocks = cleaned ? cleaned.split(/\n{2,}/) : [''];

        blocks.forEach(function (block) {
            var lines = block.split('\n').map(function (line) { return line.trim(); }).filter(Boolean);
            var isBulletList = lines.length > 0 && lines.every(function (line) { return /^[-*\u2022]\s+/.test(line); });
            var isNumberedList = lines.length > 0 && lines.every(function (line) { return /^\d+[.)]\s+/.test(line); });
            if (isBulletList || isNumberedList) {
                var list = document.createElement(isNumberedList ? 'ol' : 'ul');
                lines.forEach(function (line) {
                    var listItem = document.createElement('li');
                    listItem.textContent = line.replace(isNumberedList ? /^\d+[.)]\s+/ : /^[-*\u2022]\s+/, '');
                    list.appendChild(listItem);
                });
                container.appendChild(list);
                return;
            }

            var paragraph = document.createElement('p');
            paragraph.textContent = lines.join('\n').replace(/^#{1,6}\s+/, '');
            container.appendChild(paragraph);
        });
    }

    function updateMessageTimestamp(item, createdAt) {
        var timestamp = formatTimestamp(createdAt);
        var time = item.querySelector('time');
        if (!time) return;
        time.dateTime = timestamp.iso;
        time.textContent = timestamp.label;
    }

    function addMessage(body, actor, uuid, createdAt) {
        var existing = null;
        if (uuid) {
            Array.prototype.some.call(messages.children, function (message) {
                if (message.dataset.messageId !== uuid) return false;
                existing = message;
                return true;
            });
        }
        if (existing) {
            updateMessageTimestamp(existing, createdAt);
            return existing;
        }

        var item = document.createElement('article');
        item.className = 'nd-widget-message ' + (actor === 'visitor' ? 'is-visitor' : actor === 'status' ? 'is-status' : 'is-ai');
        var content = document.createElement('div');
        content.className = 'nd-widget-message-content';
        appendMessageContent(content, body);
        item.appendChild(content);
        if (actor !== 'status') {
            var time = document.createElement('time');
            time.className = 'nd-widget-message-time';
            item.appendChild(time);
            updateMessageTimestamp(item, createdAt);
        }
        if (uuid) item.dataset.messageId = uuid;
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
        return item;
    }

    function showTypingIndicator() {
        if (state.typingIndicator) return;
        var item = document.createElement('div');
        item.className = 'nd-widget-message is-ai is-typing';
        item.setAttribute('role', 'status');
        item.setAttribute('aria-label', 'Assistant is typing');
        var label = document.createElement('span');
        label.className = 'nd-widget-typing-label';
        label.textContent = 'Typing';
        var dots = document.createElement('span');
        dots.className = 'nd-widget-typing-dots';
        dots.setAttribute('aria-hidden', 'true');
        for (var index = 0; index < 3; index++) dots.appendChild(document.createElement('i'));
        item.appendChild(label);
        item.appendChild(dots);
        messages.appendChild(item);
        state.typingIndicator = item;
        messages.scrollTop = messages.scrollHeight;
    }

    function syncTypingIndicator() {
        if (Object.keys(state.pendingReplies).length > 0) {
            showTypingIndicator();
            return;
        }
        if (state.typingIndicator) state.typingIndicator.remove();
        state.typingIndicator = null;
    }

    function finishPendingReply(messageUuid) {
        if (messageUuid) delete state.pendingReplies[messageUuid];
        syncTypingIndicator();
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
        if (!messages.children.length) addMessage(state.widget.welcome_message, 'ai', null, new Date().toISOString());
        starters.replaceChildren();
        (state.widget.starter_questions || []).forEach(function (question) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = question;
            button.addEventListener('click', function () { sendMessage(question); });
            starters.appendChild(button);
        });
        syncConversationControls();
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
            finishPendingReply(messageUuid);
            return;
        }
        try {
            var url = root.dataset.conversationUrl.replace('__CONVERSATION__', encodeURIComponent(state.conversation));
            var data = await request(url, { method: 'GET' });
            var answered = false;
            state.conversationStatus = data.status;
            (data.messages || []).forEach(function (message) {
                addMessage(message.body, message.actor, message.uuid, message.created_at);
                if (message.reply_to_uuid === messageUuid) answered = true;
            });
            if (answered) finishPendingReply(messageUuid);
            syncConversationControls();
            if (!answered && data.status === 'open_ai') window.setTimeout(function () { poll(messageUuid, attempts + 1); }, 1000);
            if (data.status !== 'open_ai') {
                finishPendingReply(messageUuid);
                setNotice('A support person has been requested. Reload the chat to start a new AI conversation.');
            }
        } catch (error) {
            if (attempts < 3) window.setTimeout(function () { poll(messageUuid, attempts + 1); }, 1200);
            else {
                finishPendingReply(messageUuid);
                setNotice(error.message, true);
            }
        }
    }

    async function sendMessage(text) {
        text = String(text || '').trim();
        if (!text || state.sending || !state.token) return;
        state.sending = true;
        syncConversationControls();
        starters.replaceChildren();
        var optimistic = addMessage(text, 'visitor', null, new Date().toISOString());
        messageInput.value = '';
        showTypingIndicator();
        setNotice('Preparing an answer…');
        try {
            var data = await request(root.dataset.messageUrl, {
                method: 'POST',
                body: JSON.stringify({ message: text, idempotency_key: newUuid(), conversation_uuid: state.conversation })
            });
            state.conversation = data.conversation_uuid;
            state.conversationStatus = 'open_ai';
            optimistic.dataset.messageId = data.message_uuid;
            state.pendingReplies[data.message_uuid] = true;
            syncTypingIndicator();
            setNotice('');
            syncConversationControls();
            poll(data.message_uuid, 0);
        } catch (error) {
            optimistic.remove();
            syncTypingIndicator();
            messageInput.value = text;
            setNotice(error.message, true);
        } finally {
            state.sending = false;
            syncConversationControls();
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
            var data = await request(root.dataset.handoffUrl, { method: 'POST', body: JSON.stringify({ conversation_uuid: state.conversation }) });
            state.conversationStatus = data.status;
            state.pendingReplies = Object.create(null);
            syncTypingIndicator();
            setNotice('A support person has been requested. Reload the chat to start a new AI conversation.');
            syncConversationControls();
        } catch (error) { setNotice(error.message, true); handoff.disabled = false; }
    });

    if (embedded) notifyParent(false);
    bootstrap();
})();
