(function () {
    'use strict';

    var root = document.querySelector('[data-conversation-inbox]');
    if (!root) return;

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var searchForm = root.querySelector('[data-conversation-search]');
    var searchInput = searchForm?.querySelector('input[type="search"]');
    var refreshButton = root.querySelector('[data-inbox-refresh]');
    var messageArea = root.querySelector('[data-message-area]');
    var messageList = root.querySelector('[data-message-list]');
    var replyForm = root.querySelector('[data-reply-form]');
    var replyInput = root.querySelector('[data-reply-input]');
    var sendButton = root.querySelector('[data-send-button]');
    var fileInput = root.querySelector('[data-attachment-input]');
    var selectedFiles = root.querySelector('[data-selected-attachments]');
    var feedback = root.querySelector('[data-composer-feedback]');
    var loadOlder = root.querySelector('[data-load-older]');
    var newMessages = root.querySelector('[data-new-messages]');
    var pollSeconds = Math.max(2, Number(root.dataset.pollSeconds || 4));
    var pollTimer = null;
    var activityTimer = null;
    var sending = false;

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
            var random = Math.random() * 16 | 0;
            var value = character === 'x' ? random : (random & 3 | 8);
            return value.toString(16);
        });
    }

    function request(url, options) {
        var settings = Object.assign({ headers: { 'Accept': 'application/json' } }, options || {});
        settings.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, settings.headers || {});
        return fetch(url, settings).then(async function (response) {
            var payload = await response.json().catch(function () { return {}; });
            if (!response.ok) {
                var errors = payload.errors || {};
                var key = Object.keys(errors)[0];
                var message = key && errors[key] ? errors[key][0] : payload.message;
                var error = new Error(message || 'The request could not be completed.');
                error.status = response.status;
                throw error;
            }
            return payload.data;
        });
    }

    function setFeedback(message) {
        if (!feedback) return;
        feedback.textContent = message || '';
        feedback.hidden = !message;
    }

    function nearBottom() {
        if (!messageArea) return true;
        return messageArea.scrollHeight - messageArea.scrollTop - messageArea.clientHeight < 100;
    }

    function scrollToBottom(behavior) {
        if (!messageArea) return;
        messageArea.scrollTo({ top: messageArea.scrollHeight, behavior: behavior || 'auto' });
        if (newMessages) newMessages.hidden = true;
    }

    function timestamp(value) {
        var date = value ? new Date(value) : new Date();
        if (Number.isNaN(date.getTime())) date = new Date();
        var day = new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(date);
        var calendar = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
        var time = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(date);
        return { label: day + ', ' + calendar + ' · ' + time, iso: date.toISOString() };
    }

    function attachmentNode(attachment) {
        var item = document.createElement('li');
        var link = document.createElement('a');
        link.href = attachment.download_url;
        var icon = document.createElement('i');
        icon.className = 'iconoir-attachment';
        icon.setAttribute('aria-hidden', 'true');
        var name = document.createElement('span');
        name.textContent = attachment.name;
        var size = document.createElement('small');
        var bytes = Number(attachment.size || 0);
        size.textContent = bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
        link.append(icon, name, size);
        item.appendChild(link);
        return item;
    }

    function messageNode(message) {
        var article = document.createElement('article');
        article.className = 'nd-sub-inbox-message is-' + message.actor;
        article.dataset.messageId = message.uuid;
        var visitor = message.actor === 'visitor';
        var system = message.actor === 'system';

        if (visitor) {
            var visitorAvatar = document.createElement('span');
            visitorAvatar.className = 'nd-sub-inbox-message-avatar';
            visitorAvatar.setAttribute('aria-hidden', 'true');
            var visitorIcon = document.createElement('i');
            visitorIcon.className = 'iconoir-user';
            visitorAvatar.appendChild(visitorIcon);
            article.appendChild(visitorAvatar);
        }

        var bubble = document.createElement('div');
        bubble.className = 'nd-sub-inbox-bubble';
        if (!visitor && !system) {
            var sender = document.createElement('strong');
            sender.textContent = message.actor === 'ai' ? 'AI assistant' : (message.sender_name || 'Team member');
            bubble.appendChild(sender);
        }
        if (message.body) {
            var body = document.createElement('p');
            body.textContent = message.body;
            bubble.appendChild(body);
        }
        if (Array.isArray(message.attachments) && message.attachments.length) {
            var files = document.createElement('ul');
            files.className = 'nd-sub-inbox-attachments';
            message.attachments.forEach(function (attachment) { files.appendChild(attachmentNode(attachment)); });
            bubble.appendChild(files);
        }
        var footer = document.createElement('footer');
        var formatted = timestamp(message.created_at);
        var time = document.createElement('time');
        time.dateTime = formatted.iso;
        time.textContent = formatted.label;
        footer.appendChild(time);
        if (!visitor && !system) {
            var check = document.createElement('i');
            check.className = 'iconoir-check';
            check.setAttribute('aria-hidden', 'true');
            footer.appendChild(check);
        }
        bubble.appendChild(footer);
        article.appendChild(bubble);

        if (message.actor === 'ai') {
            var botAvatar = document.createElement('span');
            botAvatar.className = 'nd-sub-inbox-bot-avatar';
            botAvatar.setAttribute('aria-label', 'AI response');
            var botIcon = document.createElement('i');
            botIcon.className = 'iconoir-brain-electricity';
            botIcon.setAttribute('aria-hidden', 'true');
            botAvatar.appendChild(botIcon);
            article.appendChild(botAvatar);
        }
        return article;
    }

    function appendMessages(messages, prepend) {
        if (!messageList || !messages.length) return;
        var shouldScroll = nearBottom();
        var previousHeight = messageArea.scrollHeight;
        var fragment = document.createDocumentFragment();
        messages.forEach(function (message) {
            if (messageList.querySelector('[data-message-id="' + CSS.escape(message.uuid) + '"]')) return;
            fragment.appendChild(messageNode(message));
        });
        if (prepend) {
            messageList.prepend(fragment);
            messageArea.scrollTop += messageArea.scrollHeight - previousHeight;
        } else {
            messageList.appendChild(fragment);
            if (shouldScroll) scrollToBottom('smooth');
            else if (newMessages) newMessages.hidden = false;
        }
    }

    function lastMessageId() {
        return messageList?.lastElementChild?.dataset.messageId || '';
    }

    function firstMessageId() {
        return messageList?.firstElementChild?.dataset.messageId || '';
    }

    function schedulePoll(delay) {
        window.clearTimeout(pollTimer);
        pollTimer = window.setTimeout(pollMessages, delay == null ? pollSeconds * 1000 : delay);
    }

    function applyConversationState(state) {
        if (!state) return;
        var openManual = state.handling_mode === 'manual' && ['needs_human', 'open_manual'].indexOf(state.status) !== -1;
        if (replyInput) replyInput.disabled = !openManual;
        if (fileInput) fileInput.disabled = !openManual;
        if (sendButton && !sending) sendButton.disabled = !openManual;
        var statusBadge = root.querySelector('[data-conversation-status]');
        if (statusBadge) {
            var labels = { open_ai: 'Open', needs_human: 'Needs human reply', open_manual: 'Manual', resolved: 'Resolved', archived: 'Archived', spam: 'Spam' };
            statusBadge.textContent = labels[state.status] || state.status;
            statusBadge.className = 'nd-sub-inbox-status is-' + state.status;
        }
        if (!openManual && state.status !== 'open_ai') {
            setFeedback(state.status === 'resolved' ? 'This conversation was resolved by another team member.' : 'This conversation is no longer accepting replies.');
        }
    }

    async function pollMessages() {
        if (!messageArea || document.hidden) {
            schedulePoll(pollSeconds * 2000);
            return;
        }
        try {
            var after = lastMessageId();
            var url = messageArea.dataset.messagesUrl + (after ? '?after=' + encodeURIComponent(after) : '');
            var data = await request(url, { method: 'GET' });
            appendMessages(data.messages || [], false);
            applyConversationState(data.conversation);
            if (data.conversation && (data.conversation.status === 'open_ai' || ['needs_human', 'open_manual'].indexOf(data.conversation.status) !== -1)) setFeedback('');
        } catch (error) {
            if (error.status === 403) setFeedback('You no longer have permission to view this conversation.');
            else if (error.status === 404) setFeedback('This conversation is no longer available. Refresh the inbox.');
        } finally {
            schedulePoll();
        }
    }

    async function markRead() {
        if (!messageArea) return;
        try {
            await request(messageArea.dataset.readUrl, { method: 'POST' });
            var card = root.querySelector('[data-conversation-card="' + CSS.escape(messageArea.dataset.conversation) + '"]');
            card?.classList.remove('is-unread');
            card?.querySelector('.nd-sub-inbox-unread')?.remove();
        } catch (_) {}
    }

    async function pollActivity() {
        try {
            var data = await request(root.dataset.activityUrl, { method: 'GET' });
            if (data.latest_activity && root.dataset.latestActivity && data.latest_activity !== root.dataset.latestActivity && refreshButton) {
                refreshButton.hidden = false;
            }
        } catch (_) {}
        activityTimer = window.setTimeout(pollActivity, pollSeconds * 2000);
    }

    var searchDelay;
    searchInput?.addEventListener('input', function () {
        window.clearTimeout(searchDelay);
        searchDelay = window.setTimeout(function () { searchForm.requestSubmit(); }, 350);
    });
    refreshButton?.addEventListener('click', function () { window.location.reload(); });
    newMessages?.addEventListener('click', function () { scrollToBottom('smooth'); });

    root.querySelector('[data-mode-toggle]')?.addEventListener('change', function (event) {
        var form = event.currentTarget.closest('form');
        event.currentTarget.disabled = true;
        form.requestSubmit();
    });

    fileInput?.addEventListener('change', function () {
        if (!selectedFiles) return;
        var names = Array.from(fileInput.files || []).map(function (file) { return file.name; });
        selectedFiles.textContent = names.join(', ');
        selectedFiles.hidden = names.length === 0;
    });

    replyInput?.addEventListener('input', function () {
        replyInput.style.height = 'auto';
        replyInput.style.height = Math.min(replyInput.scrollHeight, 130) + 'px';
    });
    replyInput?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            replyForm?.requestSubmit();
        }
    });

    replyForm?.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (sending) return;
        sending = true;
        sendButton.disabled = true;
        setFeedback('');
        try {
            var data = await request(replyForm.action, { method: 'POST', body: new FormData(replyForm) });
            appendMessages([data.message], false);
            replyInput.value = '';
            replyInput.style.height = 'auto';
            fileInput.value = '';
            if (selectedFiles) { selectedFiles.textContent = ''; selectedFiles.hidden = true; }
            replyForm.querySelector('[data-idempotency-key]').value = uuid();
        } catch (error) {
            setFeedback(error.message);
        } finally {
            sending = false;
            sendButton.disabled = false;
            replyInput.focus();
        }
    });

    loadOlder?.addEventListener('click', async function () {
        var before = firstMessageId();
        if (!before) return;
        loadOlder.disabled = true;
        try {
            var data = await request(messageArea.dataset.messagesUrl + '?before=' + encodeURIComponent(before), { method: 'GET' });
            appendMessages(data.messages || [], true);
            loadOlder.hidden = !data.has_more;
        } catch (error) {
            setFeedback(error.message);
        } finally {
            loadOlder.disabled = false;
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) schedulePoll(100);
    });
    if (messageArea) {
        scrollToBottom();
        markRead();
        schedulePoll();
    }
    activityTimer = window.setTimeout(pollActivity, pollSeconds * 2000);
    window.addEventListener('beforeunload', function () {
        window.clearTimeout(pollTimer);
        window.clearTimeout(activityTimer);
    });
})();
