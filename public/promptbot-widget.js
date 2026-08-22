(function () {
    'use strict';

    var script = document.currentScript;
    if (!script || script.dataset.promptbotLoaded) return;
    script.dataset.promptbotLoaded = 'true';

    var scriptUrl = new URL(script.src);
    var widgetKey = scriptUrl.searchParams.get('key');
    if (!widgetKey) return;

    var origin = scriptUrl.origin;
    var storageKey = 'promptbot_widget_' + widgetKey;
    var token = localStorage.getItem(storageKey);
    var lastSeenId = 0;
    var config;
    var seenMessages = {};
    var unreadCount = 0;
    var panelOpen = false;
    var typingTimeout = null;
    var pollTimer = null;
    var quickPollTimer = null;
    /** Counts genuine back-and-forth turns; gates the satisfaction prompt. */
    var exchangeCount = 0;

    // ---- HTTP -----------------------------------------------------------

    function api(path, options) {
        options = options || {};
        options.headers = Object.assign(
            { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            options.headers || {}
        );
        if (token) options.headers.Authorization = 'Bearer ' + token;

        return fetch(origin + '/widget/api/' + encodeURIComponent(widgetKey) + path, options)
            .then(function (response) {
                if (!response.ok) throw new Error('Widget request failed');
                return response.json();
            });
    }

    // ---- Small helpers ----------------------------------------------------

    /** Derives a readable gradient partner shade from the tenant's brand color, so the launcher/header always look intentional rather than flat. */
    function shade(hex, amount) {
        var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '#4f46e5');
        if (!m) return hex;
        var clamp = function (v) { return Math.max(0, Math.min(255, v)); };
        var apply = function (h) { return clamp(Math.round(parseInt(h, 16) * (1 + amount))); };
        var toHex = function (v) { return ('0' + v.toString(16)).slice(-2); };
        return '#' + toHex(apply(m[1])) + toHex(apply(m[2])) + toHex(apply(m[3]));
    }

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) {
            if (key === 'class') node.className = attrs[key];
            else if (key === 'html') node.innerHTML = attrs[key];
            else node.setAttribute(key, attrs[key]);
        });
        (children || []).forEach(function (child) { if (child) node.appendChild(child); });
        return node;
    }

    function text(tag, className, content) {
        var node = document.createElement(tag);
        node.className = className;
        node.textContent = content;
        return node;
    }

    /**
     * Triggers a one-off "pop/rise in" transition without ever depending on
     * an animation frame actually firing. `@keyframes` + `animation-fill-mode`
     * looked right but had a real failure mode: if the tab is backgrounded,
     * prerendered, or the compositor is otherwise not ticking when the
     * element mounts, the animation's timeline can stay parked at time 0 —
     * and with fill-mode forwards/both, that means the element is stuck
     * showing its FROM keyframe (scale(0) / opacity:0) forever, not its
     * resting state. A support widget launcher that's invisible and
     * unclickable because a background tab never painted a frame is a much
     * worse outcome than a missing entrance animation.
     *
     * This sets the "from" state, forces layout, then synchronously clears
     * it back to the element's normal resting style in the same tick — so
     * the element's *actual* state is correct immediately regardless of
     * whether the browser ever animates the visual transition between them.
     * Cleanup runs on setTimeout, which — unlike rAF/CSS animation
     * timelines — still fires (throttled, but not indefinitely paused) in a
     * backgrounded tab.
     */
    function popIn(node, fromTransform, fromOpacity, duration) {
        duration = duration || 260;
        var previousTransition = node.style.transition;
        node.style.transition = 'none';
        if (fromTransform != null) node.style.transform = fromTransform;
        if (fromOpacity != null) node.style.opacity = fromOpacity;
        void node.offsetWidth; // force layout so the "from" state is registered before the next write
        node.style.transition = 'transform ' + duration + 'ms cubic-bezier(.34,1.56,.64,1), opacity ' + Math.min(duration, 200) + 'ms ease';
        node.style.transform = '';
        node.style.opacity = '';
        setTimeout(function () { node.style.transition = previousTransition; }, duration + 40);
    }

    function icon(paths, size) {
        return '<svg width="' + (size || 20) + '" height="' + (size || 20) + '" viewBox="0 0 24 24" fill="none" '
            + 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>';
    }

    var ICONS = {
        // A rounded message bubble with a soft tail and three dots — reads
        // as "chat" at a glance without the generic multi-curve outline the
        // old icon used, and its paths are symmetric around the viewBox
        // center so it sits dead-center once the launcher centers it properly.
        chat: icon('<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v9a2.5 2.5 0 0 1-2.5 2.5H9l-4.5 4v-4.06A2.5 2.5 0 0 1 4 14.5v-9Z"/>'
            + '<circle cx="8.3" cy="10" r=".95" fill="currentColor" stroke="none"/>'
            + '<circle cx="12" cy="10" r=".95" fill="currentColor" stroke="none"/>'
            + '<circle cx="15.7" cy="10" r=".95" fill="currentColor" stroke="none"/>', 26),
        close: icon('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>', 22),
        send: icon('<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>', 18),
        sparkle: icon('<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/>', 16),
        bot: icon('<rect x="4" y="9" width="16" height="11" rx="3"/><path d="M12 9V5"/><circle cx="12" cy="3.5" r="1.5"/><path d="M9 14v1M15 14v1"/>', 15),
        retry: icon('<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 5v5h5"/>', 13),
    };

    // ---- Styling (Shadow DOM keeps the host page's CSS from ever touching this, and vice versa) ----

    function stylesheet(primary, accent) {
        return ''
            + ':host{all:initial}'
            + '*{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
            + '.pb-root{position:fixed;bottom:20px;z-index:2147483000}'
            + '.pb-root.pos-right{right:20px}.pb-root.pos-left{left:20px}'
            + '.pb-launcher{width:60px;height:60px;border:0;border-radius:50%;cursor:pointer;color:#fff;padding:0;'
            + 'background:linear-gradient(135deg,' + primary + ',' + accent + ');'
            + 'box-shadow:0 12px 28px -6px rgba(15,23,42,.35),0 0 0 1px rgba(255,255,255,.06) inset;'
            // Grid, not flex+position:absolute: an absolutely-positioned
            // child is taken out of flow entirely, so a flex container's
            // align/justify-content never actually centers it (it falls
            // back to browser-dependent "static position" guessing, which
            // is why the icon looked off-center). Grid centers every child
            // — in or out of flow doesn't matter — and grid-area:1/1 on
            // both icons stacks them in the same cell for the crossfade.
            + 'display:grid;place-items:center;position:relative;'
            + 'transition:box-shadow .2s ease,transform .12s ease}'
            + '.pb-launcher:hover{box-shadow:0 16px 34px -6px rgba(15,23,42,.42),0 0 0 1px rgba(255,255,255,.08) inset}'
            + '.pb-launcher:active{transform:scale(.94)}'
            // grid-area belongs on the direct grid-item children (the
            // wrapping spans) — a grid container only positions its direct
            // children, and the <svg> itself is a grandchild via innerHTML.
            + '.pb-launcher>span{grid-area:1/1;display:grid;place-items:center;transition:opacity .15s ease,transform .2s ease}'
            + '.pb-launcher .ic-close{opacity:0;transform:rotate(-45deg) scale(.7)}'
            + '.pb-launcher.open .ic-chat{opacity:0;transform:rotate(45deg) scale(.7)}'
            + '.pb-launcher.open .ic-close{opacity:1;transform:rotate(0) scale(1)}'
            + '.pb-badge{position:absolute;top:-2px;right:-2px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;'
            + 'background:#ef4444;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;'
            + 'box-shadow:0 0 0 2px #fff}'
            + '.pb-panel{position:absolute;bottom:76px;width:min(376px,calc(100vw - 40px));'
            + 'height:min(600px,calc(100vh - 140px));height:min(600px,calc(100dvh - 140px));'
            + 'background:#fff;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;'
            + 'box-shadow:0 24px 60px -12px rgba(15,23,42,.28),0 4px 16px -4px rgba(15,23,42,.12),0 0 0 1px rgba(15,23,42,.04);'
            + 'opacity:0;transform:scale(.94) translateY(8px);pointer-events:none;'
            + 'transition:opacity .18s ease,transform .18s cubic-bezier(.34,1.56,.64,1)}'
            + '.pos-right .pb-panel{right:0;transform-origin:bottom right}'
            + '.pos-left .pb-panel{left:0;transform-origin:bottom left}'
            + '.pb-panel.open{opacity:1;transform:scale(1) translateY(0);pointer-events:auto}'
            // Below ~480px, stop floating the panel as a small card guessed
            // against `vh` (mobile browsers' 100vh includes address-bar area
            // that isn't actually visible, which is exactly what can push a
            // fixed-height floating card's bottom content out of view) and
            // instead take the full real viewport. A flex column pinned to
            // the actual visible edges can never clip its own last child.
            + '@media (max-width:480px){'
            + '.pb-root.pos-right,.pb-root.pos-left{right:0;left:0;bottom:0}'
            + '.pb-panel{position:fixed;inset:0;width:100%;height:100%;height:100dvh;border-radius:0;transform-origin:center!important}'
            + '.pb-launcher{position:fixed;right:16px;bottom:16px}'
            + '.pb-panel.open~.pb-launcher{display:none}'
            + '.pb-composer{padding-bottom:max(12px,env(safe-area-inset-bottom))}'
            + '.pb-head{padding-top:max(20px,env(safe-area-inset-top))}'
            + '}'
            + '.pb-head{padding:20px;color:#fff;background:linear-gradient(135deg,' + primary + ',' + accent + ');flex-shrink:0}'
            + '.pb-head-row{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}'
            + '.pb-head-name{font-size:16px;font-weight:700;letter-spacing:-.01em}'
            + '.pb-head-sub{font-size:12.5px;opacity:.85;margin-top:3px;display:flex;align-items:center;gap:5px}'
            + '.pb-status-dot{width:7px;height:7px;border-radius:50%;background:#4ade80;box-shadow:0 0 0 2px rgba(255,255,255,.35)}'
            + '.pb-icon-btn{border:0;background:rgba(255,255,255,.16);color:#fff;width:30px;height:30px;border-radius:9px;'
            + 'display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s ease,transform .12s ease}'
            + '.pb-icon-btn:hover{background:rgba(255,255,255,.26)}.pb-icon-btn:active{transform:scale(.92)}'
            + '.pb-body{flex:1;min-height:0;overflow-y:auto;padding:16px;background:#f8fafc;display:flex;flex-direction:column;gap:2px}'
            + '.pb-body::-webkit-scrollbar{width:6px}.pb-body::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}'
            + '.pb-welcome{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;font-size:13.5px;'
            + 'line-height:1.5;color:#475569;margin-bottom:10px;box-shadow:0 1px 2px rgba(15,23,42,.04)}'
            + '.pb-offline{background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:12px 14px;font-size:13px;'
            + 'line-height:1.5;color:#92400e;margin-bottom:10px}'
            + '.pb-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}'
            + '.pb-chip{border:1px solid ' + primary + '55;background:#fff;color:' + primary + ';border-radius:999px;'
            + 'padding:7px 12px;font-size:12.5px;font-weight:600;cursor:pointer;text-align:left;line-height:1.35;'
            + 'transition:background .15s ease,transform .12s ease}'
            + '.pb-chip:hover{background:' + primary + '11}.pb-chip:active{transform:scale(.97)}'
            + '.pb-rate{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;margin:10px 0;'
            + 'box-shadow:0 1px 2px rgba(15,23,42,.04)}'
            + '.pb-rate p{margin:0 0 9px;font-size:13px;color:#475569}'
            + '.pb-rate-row{display:flex;gap:8px}'
            + '.pb-rate-btn{flex:1;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;padding:9px;cursor:pointer;'
            + 'font-size:18px;line-height:1;transition:background .15s ease,border-color .15s ease}'
            + '.pb-rate-btn:hover{background:#fff;border-color:' + primary + '}'
            + '.pb-rate-done{font-size:12.5px;color:#059669;font-weight:600}'
            + '.pb-row{display:flex;margin:5px 0}'
            + '.pb-row.out{justify-content:flex-end}.pb-row.in{justify-content:flex-start;align-items:flex-end;gap:8px}'
            + '.pb-avatar{width:26px;height:26px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,' + primary + ',' + accent + ');'
            + 'color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(15,23,42,.15)}'
            + '.pb-bubble{max-width:76%;padding:10px 13px;border-radius:16px;font-size:14px;line-height:1.48;white-space:pre-wrap;word-break:break-word}'
            + '.pb-row.out .pb-bubble{background:linear-gradient(135deg,' + primary + ',' + accent + ');color:#fff;border-bottom-right-radius:5px;'
            + 'box-shadow:0 2px 8px -2px rgba(15,23,42,.25)}'
            + '.pb-row.in .pb-bubble{background:#fff;color:#1e293b;border-bottom-left-radius:5px;border:1px solid #e7ebf1;'
            + 'box-shadow:0 1px 3px rgba(15,23,42,.06)}'
            + '.pb-row.pending .pb-bubble{opacity:.6}'
            + '.pb-row.failed .pb-bubble{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}'
            + '.pb-retry{display:flex;align-items:center;gap:4px;font-size:11px;color:#dc2626;font-weight:600;cursor:pointer;'
            + 'margin-top:4px;justify-content:flex-end}'
            + '.pb-typing{display:flex;align-items:center;gap:8px;margin:4px 0 8px}'
            + '.pb-typing-bubble{background:#fff;border:1px solid #e7ebf1;border-radius:16px;border-bottom-left-radius:5px;'
            + 'padding:11px 14px;display:flex;gap:4px;box-shadow:0 1px 3px rgba(15,23,42,.06)}'
            + '.pb-typing-bubble span{width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:pb-bounce 1.1s ease infinite}'
            + '.pb-typing-bubble span:nth-child(2){animation-delay:.15s}.pb-typing-bubble span:nth-child(3){animation-delay:.3s}'
            + '.pb-composer{flex-shrink:0;border-top:1px solid #eef1f5;padding:12px;display:flex;gap:8px;align-items:flex-end;background:#fff}'
            + '.pb-input{flex:1;min-width:0;border:1.5px solid #e2e8f0;border-radius:22px;padding:11px 16px;font-size:14px;'
            + 'outline:none;transition:border-color .15s ease,box-shadow .15s ease;background:#f8fafc}'
            + '.pb-input:focus{border-color:' + primary + ';box-shadow:0 0 0 3px ' + primary + '22;background:#fff}'
            + '.pb-send{width:40px;height:40px;border-radius:50%;border:0;flex-shrink:0;cursor:pointer;color:#fff;'
            + 'background:linear-gradient(135deg,' + primary + ',' + accent + ');display:flex;align-items:center;justify-content:center;'
            + 'transition:transform .12s ease,opacity .15s ease;box-shadow:0 2px 8px -2px rgba(15,23,42,.3)}'
            + '.pb-send:hover{transform:scale(1.06)}.pb-send:active{transform:scale(.92)}'
            + '.pb-send:disabled{opacity:.45;cursor:default;transform:none}'
            + '.pb-footer{text-align:center;font-size:10.5px;color:#94a3b8;padding:7px 0 10px;flex-shrink:0;background:#fff}'
            + '.pb-precha{flex:1;min-height:0;display:flex;flex-direction:column;justify-content:center;gap:12px;padding:24px;background:#f8fafc;overflow-y:auto}'
            + '.pb-precha p{font-size:13px;color:#64748b;margin:0 0 4px;line-height:1.5}'
            + '.pb-field{display:flex;flex-direction:column;gap:5px}'
            + '.pb-field label{font-size:12px;font-weight:600;color:#334155}'
            + '.pb-field input{border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:14px;outline:none;'
            + 'transition:border-color .15s ease,box-shadow .15s ease}'
            + '.pb-field input:focus{border-color:' + primary + ';box-shadow:0 0 0 3px ' + primary + '22}'
            + '.pb-start{margin-top:4px;border:0;border-radius:12px;padding:12px;font-size:14px;font-weight:700;color:#fff;cursor:pointer;'
            + 'background:linear-gradient(135deg,' + primary + ',' + accent + ');box-shadow:0 4px 14px -4px rgba(15,23,42,.3);'
            + 'transition:transform .12s ease}'
            + '.pb-start:active{transform:scale(.98)}'
            + '@keyframes pb-bounce{0%,60%,100%{transform:translateY(0);opacity:.5}30%{transform:translateY(-4px);opacity:1}}'
            + '@media (prefers-reduced-motion:reduce){.pb-launcher,.pb-panel,.pb-row,.pb-badge{transition:none!important}'
            + '.pb-typing-bubble span{animation:none!important;opacity:1!important}}';
    }

    // ---- Build & mount ----------------------------------------------------

    function start() {
        var primary = config.primaryColor || '#4f46e5';
        var accent = shade(primary, -0.22);
        var position = config.position === 'left' ? 'left' : 'right';

        var host = document.createElement('div');
        var shadow = host.attachShadow({ mode: 'open' });
        var style = document.createElement('style');
        style.textContent = stylesheet(primary, accent);
        shadow.appendChild(style);

        var root = el('div', { class: 'pb-root pos-' + position });

        var launcher = el('button', { class: 'pb-launcher', type: 'button', 'aria-label': 'Open support chat' });
        launcher.innerHTML = '<span class="ic-chat">' + ICONS.chat + '</span><span class="ic-close">' + ICONS.close + '</span>';
        var badge = el('span', { class: 'pb-badge' }, [text('span', '', '')]);
        badge.style.display = 'none';
        launcher.appendChild(badge);

        var panel = el('div', { class: 'pb-panel', role: 'dialog', 'aria-label': (config.name || 'Support chat') + ' chat window' });

        // -- Header --
        var closeBtn = el('button', { class: 'pb-icon-btn', type: 'button', 'aria-label': 'Close chat', html: ICONS.close });
        var head = el('div', { class: 'pb-head' }, [
            el('div', { class: 'pb-head-row' }, [
                el('div', {}, [
                    text('div', 'pb-head-name', config.name || 'Support'),
                    el('div', { class: 'pb-head-sub' }, [el('span', { class: 'pb-status-dot' }), text('span', '', 'Usually replies in a few minutes')]),
                ]),
                closeBtn,
            ]),
        ]);

        // -- Message body --
        var body = el('div', { class: 'pb-body' });
        var welcome = text('div', 'pb-welcome', config.welcomeMessage || 'How can we help?');
        body.appendChild(welcome);

        // Out-of-hours notice. Only shown when the server says the workspace
        // is closed AND no AI is answering — if the bot is on, the visitor
        // genuinely can get help right now and telling them otherwise would
        // be wrong.
        if (config.online === false && !config.aiEnabled) {
            var offlineText = config.offlineMessage || 'We are currently offline. Leave a message and we will get back to you.';
            if (config.opensAt) {
                var opens = new Date(config.opensAt);
                if (!isNaN(opens.getTime())) {
                    offlineText += ' We are back ' + opens.toLocaleString([], { weekday: 'short', hour: 'numeric', minute: '2-digit' }) + '.';
                }
            }
            body.appendChild(text('div', 'pb-offline', offlineText));
        }

        // Suggested questions. Rendered once, above the transcript, and
        // removed as soon as the visitor engages — leaving them in place
        // while a conversation is underway turns helpful prompts into clutter
        // that competes with the thread.
        var chips = null;
        if ((config.suggestedQuestions || []).length) {
            chips = el('div', { class: 'pb-chips' });
            config.suggestedQuestions.slice(0, 6).forEach(function (question) {
                var chip = el('button', { class: 'pb-chip', type: 'button' });
                chip.textContent = question;
                chip.onclick = function () { submitMessage(question); };
                chips.appendChild(chip);
            });
            body.appendChild(chips);
        }

        var typingRow = el('div', { class: 'pb-typing' }, [
            el('div', { class: 'pb-avatar', html: ICONS.bot }),
            el('div', { class: 'pb-typing-bubble' }, [el('span'), el('span'), el('span')]),
        ]);
        typingRow.style.display = 'none';

        // -- Composer --
        var input = el('input', { class: 'pb-input', type: 'text', placeholder: 'Type a message…', autocomplete: 'off' });
        var sendBtn = el('button', { class: 'pb-send', type: 'submit', 'aria-label': 'Send message', html: ICONS.send });
        var form = el('form', { class: 'pb-composer' }, [input, sendBtn]);
        var footer = text('div', 'pb-footer', 'Powered by PromptBot');

        // flex:1 (not height:100%) is what actually matters here: `.pb-head`
        // is a sibling in the same flex column, so height:100% on chatView
        // meant "100% of the panel" ON TOP OF the header's own height —
        // oversizing chatView past the panel's bounds and clipping the
        // composer off (via the panel's overflow:hidden) instead of just
        // filling whatever space is left after the header. flex:1 fills
        // exactly the remaining space; min-height:0 lets it shrink below its
        // content's natural size so the inner .pb-body (not chatView) is
        // what scrolls.
        var chatView = el('div', { style: 'display:flex;flex-direction:column;flex:1;min-height:0' }, [body, form, footer]);

        // -- Pre-chat form (only rendered/used if the widget requires a name/email and we have no session yet) --
        var needsPrechat = (config.requireName || config.requireEmail) && !token;
        var nameInput, emailInput, prechatView;

        if (needsPrechat) {
            var fields = [];
            if (config.requireName) fields.push(el('div', { class: 'pb-field' }, [text('label', '', 'Your name'), (nameInput = el('input', { type: 'text', autocomplete: 'name' }))]));
            if (config.requireEmail) fields.push(el('div', { class: 'pb-field' }, [text('label', '', 'Your email'), (emailInput = el('input', { type: 'email', autocomplete: 'email' }))]));

            var startBtn = el('button', { class: 'pb-start', type: 'submit' }, [text('span', '', 'Start chat')]);
            var prechatForm = el('form', {}, fields.concat([startBtn]));
            prechatForm.style.cssText = 'display:flex;flex-direction:column;gap:12px';
            prechatForm.onsubmit = function (event) {
                event.preventDefault();
                identify(nameInput ? nameInput.value.trim() : '', emailInput ? emailInput.value.trim() : '').then(function () {
                    prechatView.style.display = 'none';
                    chatView.style.display = 'flex';
                    input.focus();
                });
            };

            prechatView = el('div', { class: 'pb-precha' }, [
                text('p', '', "We'd love to know who we're chatting with."),
                prechatForm,
            ]);
            chatView.style.display = 'none';
            panel.appendChild(head);
            panel.appendChild(prechatView);
            panel.appendChild(chatView);
        } else {
            panel.appendChild(head);
            panel.appendChild(chatView);
        }

        root.appendChild(panel);
        root.appendChild(launcher);
        shadow.appendChild(root);
        document.body.appendChild(host);
        popIn(launcher, 'scale(0)', null, 280);

        // ---- Behaviour ----

        function scrollToBottom() { body.scrollTop = body.scrollHeight; }

        function showTyping() {
            body.appendChild(typingRow);
            typingRow.style.display = 'flex';
            scrollToBottom();
            clearTimeout(typingTimeout);
            // Safety net: never leave the visitor staring at "…" forever if a
            // reply never arrives (AI disabled, provider down, no auto-reply).
            typingTimeout = setTimeout(hideTyping, 20000);
        }

        function hideTyping() {
            clearTimeout(typingTimeout);
            typingRow.style.display = 'none';
        }

        function setUnread(n) {
            var wasHidden = unreadCount === 0;
            unreadCount = n;
            badge.firstChild.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
            badge.style.display = unreadCount > 0 ? 'flex' : 'none';
            if (unreadCount > 0 && wasHidden) popIn(badge, 'scale(0)', null, 200);
        }

        function bubble(message, pendingKey) {
            var key = message.id != null ? String(message.id) : (message.uuid || pendingKey || message.body);
            if (seenMessages[key]) return null;
            seenMessages[key] = true;

            var isOutbound = message.direction === 'inbound'; // visitor's own message renders on the right
            var row = el('div', { class: 'pb-row ' + (isOutbound ? 'out' : 'in') + (pendingKey ? ' pending' : '') });
            if (!isOutbound) row.appendChild(el('div', { class: 'pb-avatar', html: ICONS.bot }));

            var box = el('div', { class: 'pb-bubble' });
            box.textContent = message.body;
            row.appendChild(box);

            if (typingRow.style.display !== 'none') body.insertBefore(row, typingRow);
            else body.appendChild(row);
            popIn(row, 'translateY(6px)', '0', 200);
            scrollToBottom();

            if (typeof message.id === 'number' && isFinite(message.id)) lastSeenId = Math.max(lastSeenId, message.id);

            if (!isOutbound) {
                hideTyping();
                // A reply arriving is what completes an exchange; asking for
                // a rating is deferred a beat so it lands after the answer
                // rather than on top of it.
                exchangeCount++;
                setTimeout(maybeAskForRating, 1500);
            }

            return row;
        }

        function markFailed(row, retryFn) {
            row.classList.remove('pending');
            row.classList.add('failed');
            var retry = el('div', { class: 'pb-retry', html: ICONS.retry + '<span>Failed — tap to retry</span>' });
            retry.onclick = retryFn;
            row.appendChild(retry);
        }

        function identify(name, email) {
            if (token) return Promise.resolve();
            return api('/session', {
                method: 'POST',
                body: JSON.stringify({ name: name || '', email: email || '', locale: navigator.language }),
            }).then(function (response) {
                token = response.token;
                localStorage.setItem(storageKey, token);
            });
        }

        function poll() {
            if (!token) return;
            api('/messages?after=' + lastSeenId)
                .then(function (response) { response.messages.forEach(function (m) { bubble(m); }); })
                .catch(function () {});
        }

        function pollSoonThenSettle() {
            // A visitor who just sent a message wants a snappy reply, not a
            // wait for the next scheduled 5s tick — but the ongoing interval
            // below still owns the steady-state polling cadence.
            clearTimeout(quickPollTimer);
            quickPollTimer = setTimeout(poll, 1200);
        }

        function openPanel() {
            panelOpen = true;
            launcher.classList.add('open');
            panel.classList.add('open');
            setUnread(0);
            identify().then(poll).catch(function () {});
            if (!needsPrechat || token) input.focus();
        }

        function closePanel() {
            panelOpen = false;
            launcher.classList.remove('open');
            panel.classList.remove('open');
        }

        launcher.onclick = function () { panelOpen ? closePanel() : openPanel(); };
        closeBtn.onclick = closePanel;

        /**
         * Shared by the composer and the suggested-question chips, so a chip
         * behaves in every respect like the visitor typing that question —
         * same optimistic render, same retry, same typing indicator.
         */
        function submitMessage(value) {
            value = (value || '').trim();
            if (!value) return;

            // Prompts have served their purpose the moment one is used.
            if (chips && chips.parentNode) { chips.parentNode.removeChild(chips); chips = null; }

            input.value = '';
            sendBtn.disabled = true;

            var clientId = Date.now() + '-' + Math.random().toString(36).slice(2);
            var row = bubble({ body: value, direction: 'inbound' }, clientId);

            identify()
                .then(function () { return api('/messages', { method: 'POST', body: JSON.stringify({ body: value, client_id: clientId }) }); })
                .then(function (response) {
                    delete seenMessages[clientId];
                    if (row) row.classList.remove('pending');
                    if (typeof response.message.id === 'number') {
                        lastSeenId = Math.max(lastSeenId, response.message.id);
                        // Registered even though `after=` already excludes this id
                        // from future polls — belt-and-suspenders against ever
                        // rendering the same message twice.
                        seenMessages[String(response.message.id)] = true;
                    }
                    showTyping();
                    pollSoonThenSettle();
                })
                .catch(function () {
                    if (row) markFailed(row, function () {
                        row.remove();
                        delete seenMessages[clientId];
                        input.value = value;
                        input.focus();
                    });
                })
                .finally(function () { sendBtn.disabled = false; });
        }

        form.onsubmit = function (event) {
            event.preventDefault();
            submitMessage(input.value);
        };

        /**
         * Asks for a rating once, after a real exchange has happened. Gated
         * on the conversation actually having gone somewhere — prompting
         * after a single unanswered message reads as tone-deaf, and rating a
         * conversation that never happened produces meaningless CSAT.
         */
        var ratingShown = false;
        function maybeAskForRating() {
            if (ratingShown || exchangeCount < 2) return;
            ratingShown = true;

            var card = el('div', { class: 'pb-rate' });
            card.appendChild(text('p', '', 'Was this helpful?'));
            var row = el('div', { class: 'pb-rate-row' });

            [['👍', 5], ['👌', 3], ['👎', 1]].forEach(function (pair) {
                var btn = el('button', { class: 'pb-rate-btn', type: 'button', 'aria-label': 'Rate ' + pair[1] + ' out of 5' });
                btn.textContent = pair[0];
                btn.onclick = function () {
                    api('/rate', { method: 'POST', body: JSON.stringify({ score: pair[1] }) }).catch(function () {});
                    card.innerHTML = '';
                    card.appendChild(text('div', 'pb-rate-done', 'Thanks for the feedback!'));
                };
                row.appendChild(btn);
            });

            card.appendChild(row);
            body.appendChild(card);
            scrollToBottom();
        }

        // Background polling runs whenever we have a session, open or
        // closed, so a visitor who steps away still sees an unread badge
        // when they come back — this only starts once identify() has run at
        // least once (i.e. token is set), matching prior behaviour.
        pollTimer = setInterval(function () {
            if (!token) return;
            var before = lastSeenId;
            api('/messages?after=' + before)
                .then(function (response) {
                    response.messages.forEach(function (m) {
                        var wasOutbound = m.direction !== 'inbound';
                        bubble(m);
                        if (!panelOpen && wasOutbound) setUnread(unreadCount + 1);
                    });
                })
                .catch(function () {});
        }, 5000);
    }

    api('/config')
        .then(function (response) {
            config = response;
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
            else start();
        })
        .catch(function () {});
})();
