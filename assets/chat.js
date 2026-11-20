/* PartsBin — floating chat assistant (talks to chat.php, which talks to Gemini)
 *
 * The conversation lives here in the browser: every time you send a message,
 * the whole list goes to chat.php, because Gemini doesn't remember earlier
 * requests by itself. sessionStorage keeps the list while you move between
 * pages, and forgets it when the tab is closed.
 */
(function () {
    var root = document.querySelector('[data-chat]');
    if (!root) { return; }

    var panel    = root.querySelector('.chat-panel');
    var toggle   = root.querySelector('[data-chat-toggle]');
    var log      = root.querySelector('[data-chat-log]');
    var welcome  = root.querySelector('[data-chat-welcome]');
    var form     = root.querySelector('[data-chat-form]');
    var input    = form.querySelector('textarea');
    var sendBtn  = form.querySelector('button');
    var STORE    = 'partsbin-chat';

    var state = load();   // { open: bool, messages: [{role, content}] }
    var busy  = false;

    /* ---------- Saving between pages ---------- */

    function load() {
        try {
            var saved = JSON.parse(sessionStorage.getItem(STORE));
            if (saved && Array.isArray(saved.messages)) { return saved; }
        } catch (e) { /* storage blocked or empty: start fresh */ }
        return { open: false, messages: [] };
    }

    function save() {
        try { sessionStorage.setItem(STORE, JSON.stringify(state)); } catch (e) { /* ignore */ }
    }

    /* ---------- Drawing messages ---------- */

    // Escape HTML first, then allow a tiny bit of formatting:
    // **bold**, `code`, "- " bullet lists and line breaks.
    function format(text) {
        var html = text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');

        var out = [], inList = false;
        html.split('\n').forEach(function (line) {
            var item = line.match(/^\s*(?:[-*•]|\d+\.)\s+(.*)$/);
            if (item) {
                if (!inList) { out.push('<ul>'); inList = true; }
                out.push('<li>' + item[1] + '</li>');
            } else {
                if (inList) { out.push('</ul>'); inList = false; }
                out.push(line === '' ? '' : '<p>' + line + '</p>');
            }
        });
        if (inList) { out.push('</ul>'); }
        return out.join('');
    }

    function bubble(role, text) {
        var div = document.createElement('div');
        div.className = 'chat-msg ' + role;
        if (role === 'assistant') { div.innerHTML = format(text); }
        else { div.textContent = text; }
        log.appendChild(div);
        return div;
    }

    function note(text, kind) {
        var div = document.createElement('div');
        div.className = 'chat-note ' + (kind || '');
        div.textContent = text;
        log.appendChild(div);
        scrollDown();
    }

    function renderAll() {
        log.querySelectorAll('.chat-msg, .chat-note').forEach(function (el) { el.remove(); });
        welcome.hidden = state.messages.length > 0;
        state.messages.forEach(function (m) { bubble(m.role, m.content); });
        scrollDown();
    }

    function scrollDown() { log.scrollTop = log.scrollHeight; }

    /* ---------- Open / close ---------- */

    function setOpen(open) {
        state.open = open;
        panel.hidden = !open;
        root.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close the assistant' : 'Open the assistant');
        save();
        if (open) { scrollDown(); input.focus(); }
    }

    toggle.addEventListener('click', function () { setOpen(!state.open); });
    root.querySelector('[data-chat-close]').addEventListener('click', function () { setOpen(false); toggle.focus(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && state.open) { setOpen(false); toggle.focus(); }
    });

    root.querySelector('[data-chat-clear]').addEventListener('click', function () {
        if (busy) { return; }
        state.messages = [];
        save();
        renderAll();
        input.focus();
    });

    /* ---------- Typing ---------- */

    // Grow the box with its text (up to the CSS max-height)
    function autosize() {
        input.style.height = 'auto';
        input.style.height = input.scrollHeight + 'px';
    }
    input.addEventListener('input', autosize);

    // Enter sends, Shift+Enter makes a new line
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        send(input.value);
    });

    root.querySelectorAll('[data-chat-ask]').forEach(function (chip) {
        chip.addEventListener('click', function () { send(chip.textContent); });
    });

    /* ---------- Sending a message and reading the streamed answer ---------- */

    async function send(text) {
        text = text.trim();
        if (!text || busy) { return; }

        busy = true;
        sendBtn.disabled = true;
        input.value = '';
        autosize();

        state.messages.push({ role: 'user', content: text });
        save();
        welcome.hidden = true;
        var mine = bubble('user', text);

        // Empty answer bubble with "typing" dots until the first words arrive
        var live = bubble('assistant', '');
        live.classList.add('typing');
        live.innerHTML = '<span></span><span></span><span></span>';
        scrollDown();

        var answer = '', stop = null, error = null;

        try {
            var response = await fetch('chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'PartsBin' },
                body: JSON.stringify({ messages: state.messages })
            });

            // chat.php sends one JSON object per line. Pieces of a line can
            // arrive split across reads, so keep the unfinished end in `buffer`.
            var reader  = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer  = '';

            while (true) {
                var chunk = await reader.read();
                if (chunk.done) { break; }
                buffer += decoder.decode(chunk.value, { stream: true });

                var lines = buffer.split('\n');
                buffer = lines.pop();
                lines.forEach(function (line) {
                    if (!line.trim()) { return; }
                    var event = JSON.parse(line);
                    if (event.type === 'text') {
                        answer += event.text;
                        live.classList.remove('typing');
                        live.innerHTML = format(answer);
                        scrollDown();
                    } else if (event.type === 'done') {
                        stop = event.stop;
                    } else if (event.type === 'error') {
                        error = event.message;
                    }
                });
            }
            if (!error && stop === null) { error = 'The answer was cut off. Please try again.'; }
        } catch (e) {
            error = 'Could not reach the server. Is Apache running?';
        }

        if (error || stop === 'blocked') {
            // Take the question back out of the history so you can retry it
            live.remove();
            mine.remove();
            welcome.hidden = state.messages.length > 1;
            state.messages.pop();
            input.value = text;
            autosize();
            note(error || "Gemini's safety filter blocked that one. Try rephrasing the question.", 'error');
        } else {
            state.messages.push({ role: 'assistant', content: answer });
            if (stop === 'max_tokens') { note('The answer hit the length limit and was cut short.'); }
        }

        save();
        busy = false;
        sendBtn.disabled = false;
        input.focus();
    }

    /* ---------- Start ---------- */

    renderAll();
    if (state.open) { setOpen(true); }
})();
