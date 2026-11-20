<?php
/**
 * chat_widget.php
 * The floating chat button and panel on the right side of every page.
 * footer.php includes it for logged-in users. The behaviour is in assets/chat.js,
 * the look in the "Chat assistant" section of assets/style.css.
 */
?>
<div class="chat" data-chat>
    <section class="chat-panel" id="chat-panel" role="dialog" aria-label="<?= e(APP_NAME) ?> assistant" hidden>
        <header class="chat-head">
            <div>
                <strong>Ask <?= e(APP_NAME) ?></strong>
                <span>Answers from your live stock · powered by Gemini</span>
            </div>
            <button type="button" class="chat-icon-btn" data-chat-clear title="New conversation" aria-label="New conversation">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.4-5.7M4 4v4h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" class="chat-icon-btn" data-chat-close title="Close" aria-label="Close chat">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </header>

        <div class="chat-log" data-chat-log aria-live="polite">
            <div class="chat-welcome" data-chat-welcome>
                <p>Hi! Ask me anything about your stock.</p>
                <div class="chat-chips">
                    <button type="button" data-chat-ask>What's low on stock?</button>
                    <button type="button" data-chat-ask>What's our total stock value at cost?</button>
                    <button type="button" data-chat-ask>Which product has the best margin?</button>
                    <button type="button" data-chat-ask>What did we restock recently?</button>
                </div>
            </div>
        </div>

        <form class="chat-form" data-chat-form>
            <textarea name="message" rows="1" maxlength="<?= CHAT_MAX_CHARS ?>" placeholder="Ask about stock, prices, restocks…" aria-label="Your message" required></textarea>
            <button type="submit" class="chat-send" aria-label="Send">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </form>
    </section>

    <button type="button" class="chat-launcher" data-chat-toggle aria-controls="chat-panel" aria-expanded="false" aria-label="Open the assistant">
        <svg class="chat-launcher-open" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
        <svg class="chat-launcher-close" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>
</div>
