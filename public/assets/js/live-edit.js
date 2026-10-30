/* =====================================================================================================
   অ্যাডমিনের "লাইভ পেইজ এডিট"-এর ফ্রেমে চলে (শুধু লগইন-করা অ্যাডমিনের ?live=1 পেইজে লোড হয়; app.js-এর আগে)।
   ১) সার্ভার প্রতিটি সম্পাদনাযোগ্য লেখার দুই পাশে অদৃশ্য চিহ্ন বসায় (App\Core\LiveEdit); এখানে সেগুলো খুঁজে
      লেখাটাকে (বা পুরো ঘরকে) data-jk-key চিহ্নিত করা হয়, তারপর চিহ্ন মুছে ফেলা হয় — পেইজ দেখতে হুবহু আগের মতো।
   ২) চিহ্নিত লেখায় ক্লিক করলে সেখানেই একটা বাক্স খোলে: লেখা বদলে "সংরক্ষণ করুন" চাপলে সঙ্গে সঙ্গে সাইটে বদলায়।
   ৩) যে লেখায় সাজ চলে (সার্ভারের colored()/t()), বাক্সে বোল্ড/ইটালিক/আন্ডারলাইন/রঙের বাটন আর পেইজের মতো দেখতে একটা নমুনা।
   ৪) পেইজের ছবি ও ছোট আইকনে (data-jk-img) ক্লিক: নতুন ছবি দেওয়া, শুরুর ছবিতে ফেরা, লুকানো/দেখানো (শেষ দুটি সুপার অ্যাডমিন)।
   ৫) ছোট হেডিংয়ের বক্সে (data-jk-box) থাকা লেখায় ক্লিক: লেখার বাক্সে "বক্সের রং" সারি (শুধু সুপার অ্যাডমিন)।
   ৬) খোলা পেইজের মূল লেখায় (data-jk-rich) ক্লিক: অ্যাডমিনের পর্দাকে জানানো হয়, সেখানে পূর্ণ এডিটরের বড় বাক্স খোলে।
   ৭) ফর্মের ঘর (data-jk-field): সুপার অ্যাডমিনের জন্য কোণে "ঘর" চিহ্ন, ক্লিকে বাক্সের রং ও (যেখানে চলে) লুকান/দেখান।
   ৮) ফর্মে অ্যাডমিনের যোগ করা ঘর (data-jk-custom): যোগ, বদল, ক্রম ও মোছা (শুধু সুপার অ্যাডমিন)।
   ৯) ওপরের মেনু বা ফুটারের লিংকের কলামে (data-jk-menu) ক্লিক: অ্যাডমিনের পর্দায় "মেনু সম্পাদনা" বাক্স (শুধু সুপার অ্যাডমিন)।
   ===================================================================================================== */
(function () {
    var dataEl = document.getElementById("jk-live-data");
    if (!dataEl) {
        return;
    }
    var data;
    try {
        data = JSON.parse(dataEl.textContent);
    } catch (e) {
        return;
    }
    var texts = data.texts || [];

    var START = "⁡";
    var END = "⁤";
    var TOKEN = /⁡([⁢⁣]+)⁡|⁤/g;
    var ANY_MARK = /[⁡-⁤]/g;

    function indexOf(bits) {
        return parseInt(bits.replace(/⁢/g, "0").replace(/⁣/g, "1"), 2);
    }

    /* ---- ১. লেখার ভেতরের চিহ্ন: একই লেখা-টুকরোর ভেতরে শুরু-শেষ হলে <span> দিয়ে ঘেরা; কয়েক ঘর জুড়ে থাকলে
       (যেমন রঙিন শব্দ) শুরু ও শেষে খালি জায়গা-চিহ্ন বসিয়ে পরে দুটোর সাধারণ ঘরটাকে চিহ্নিত করা ---- */
    function markTextNodes() {
        var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        var nodes = [];
        while (walker.nextNode()) {
            if (walker.currentNode.data.indexOf(START) !== -1 || walker.currentNode.data.indexOf(END) !== -1) {
                nodes.push(walker.currentNode);
            }
        }
        nodes.forEach(function (node) {
            var text = node.data;
            var frag = document.createDocumentFragment();
            var stack = [frag];
            var last = 0;
            var m;
            TOKEN.lastIndex = 0;
            while ((m = TOKEN.exec(text)) !== null) {
                if (m.index > last) {
                    stack[stack.length - 1].appendChild(document.createTextNode(text.slice(last, m.index)));
                }
                last = TOKEN.lastIndex;
                if (m[1] !== undefined) {
                    var rest = text.slice(last);
                    if (rest.indexOf(END) !== -1) {
                        var span = document.createElement("span");
                        span.setAttribute("data-jk-key", String(indexOf(m[1])));
                        stack[stack.length - 1].appendChild(span);
                        stack.push(span);
                    } else {
                        var s = document.createElement("span");
                        s.setAttribute("data-jk-s", String(indexOf(m[1])));
                        stack[stack.length - 1].appendChild(s);
                    }
                } else if (stack.length > 1) {
                    stack.pop();
                } else {
                    var en = document.createElement("span");
                    en.setAttribute("data-jk-e", "");
                    frag.appendChild(en);
                }
            }
            if (last < text.length) {
                stack[stack.length - 1].appendChild(document.createTextNode(text.slice(last)));
            }
            node.parentNode.replaceChild(frag, node);
        });

        // কয়েক ঘর জুড়ে থাকা লেখা: শুরু-শেষের সাধারণ ঘর
        var open = [];
        document.querySelectorAll("[data-jk-s],[data-jk-e]").forEach(function (el) {
            if (el.hasAttribute("data-jk-s")) {
                open.push(el);
                return;
            }
            var start = open.pop();
            if (start) {
                var range = document.createRange();
                range.setStartBefore(start);
                range.setEndAfter(el);
                var box = range.commonAncestorContainer;
                if (box.nodeType !== 1) {
                    box = box.parentElement;
                }
                if (box && !box.hasAttribute("data-jk-key") && box !== document.body) {
                    box.setAttribute("data-jk-key", start.getAttribute("data-jk-s"));
                }
                start.remove();
            }
            el.remove();
        });
        open.forEach(function (el) {
            el.remove();
        });

        // ঘরের পুরো লেখাই একটা লেখা হলে ঘরটাকেই চিহ্নিত করা (হেডিং, বাটন): পুরো ঘর হাইলাইট হয়,
        // আর app.js ঘরের লেখা নতুন করে সাজালেও (হিরোর উড়ে-আসা অক্ষর) চিহ্ন হারায় না
        document.querySelectorAll("span[data-jk-key]").forEach(function (span) {
            var parent = span.parentElement;
            if (parent && parent !== document.body && !parent.hasAttribute("data-jk-key")
                && parent.textContent.trim() === span.textContent.trim()) {
                parent.setAttribute("data-jk-key", span.getAttribute("data-jk-key"));
                while (span.firstChild) {
                    parent.insertBefore(span.firstChild, span);
                }
                span.remove();
            }
        });
    }

    /* ---- ২. ঘরের বৈশিষ্ট্যে (placeholder, alt, title ...) থাকা চিহ্ন: মুছে ফেলা; ফর্মের ঘর ও ছবি নিজেই বদলানো যায় ---- */
    function markAttributes() {
        document.querySelectorAll("*").forEach(function (el) {
            Array.prototype.slice.call(el.attributes).forEach(function (attr) {
                if (attr.value.indexOf(START) === -1 && attr.value.indexOf(END) === -1) {
                    return;
                }
                var m = /⁡([⁢⁣]+)⁡/.exec(attr.value);
                if (m && /^(placeholder|alt|title|value)$/.test(attr.name) && !el.hasAttribute("data-jk-key")) {
                    el.setAttribute("data-jk-key", String(indexOf(m[1])));
                }
                el.setAttribute(attr.name, attr.value.replace(TOKEN, "").replace(ANY_MARK, ""));
            });
        });
        document.title = document.title.replace(TOKEN, "").replace(ANY_MARK, "");
    }

    markTextNodes();
    markAttributes();

    // খোলা পেইজের মূল লেখা: সার্ভারের লুকানো চিহ্ন (OpenPages::html) থেকে ঘরটা চেনা
    document.querySelectorAll("span[data-jk-rich]").forEach(function (mark) {
        if (mark.parentElement) {
            mark.parentElement.setAttribute("data-jk-rich", mark.getAttribute("data-jk-rich"));
        }
        mark.remove();
    });

    var palette = window.jkPalette;

    /* ---- ৩. বদলানোর বাক্স ---- */
    var panel = null;
    var textPal = null;
    var boxPal = null;
    var current = null;

    function build() {
        panel = document.createElement("div");
        panel.className = "jk-live-panel";
        panel.setAttribute("role", "dialog");
        panel.setAttribute("aria-label", "লেখা বদলান");
        panel.innerHTML =
            '<p class="jk-live-panel__title"></p>' +
            '<p class="jk-live-panel__orig"></p>' +
            '<div class="jk-live-panel__tools" role="toolbar" aria-label="লেখার সাজ">' +
            '<button type="button" data-wrap="b" title="মোটা (বোল্ড)"><b>B</b></button>' +
            '<button type="button" data-wrap="i" title="বাঁকা (ইটালিক)"><i>I</i></button>' +
            '<button type="button" data-wrap="u" title="নিচে দাগ (আন্ডারলাইন)"><u>U</u></button>' +
            '<span class="jk-live-panel__colors"></span>' +
            '<button type="button" data-wrap="clear" title="বাছা অংশের (বা পুরো লেখার) সব সাজ মুছুন">সাজ মুছুন</button>' +
            '</div>' +
            '<textarea class="jk-live-panel__text" rows="3" maxlength="3000"></textarea>' +
            '<p class="jk-live-panel__preview" aria-label="নমুনা"></p>' +
            '<p class="jk-live-panel__hint"></p>' +
            '<div class="jk-live-panel__box"><span class="jk-live-panel__boxlabel">বক্সের রং:</span>' +
            '<span class="jk-live-panel__boxcolors"></span>' +
            '<button type="button" class="jk-live-panel__boxreset" data-box="">শুরুর রং</button></div>' +
            '<p class="jk-live-panel__msg" role="status"></p>' +
            '<div class="jk-live-panel__row">' +
            '<button type="button" class="jk-live-panel__save">সংরক্ষণ করুন</button>' +
            '<button type="button" class="jk-live-panel__cancel">বাতিল</button>' +
            '<button type="button" class="jk-live-panel__reset">শুরুর লেখায় ফিরুন</button>' +
            "</div>";
        document.body.appendChild(panel);
        var colors = panel.querySelector(".jk-live-panel__colors");
        var textItems = (data.colors || []).map(function (name) {
            return { value: name, label: (COLOR_NAMES[name] || name) + " রং", cls: "jk-sw--" + name };
        });
        textPal = palette({
            items: textItems,
            value: textItems.length ? textItems[textItems.length - 1].value : "",
            kind: "text",
            title: "লেখার রং (বাছা অংশে বসে; কিছু না বাছলে পুরো লেখায়)",
            onPick: function (name) {
                wrap("color:" + name);
            }
        });
        colors.appendChild(textPal.el);
        panel.querySelector(".jk-live-panel__tools").addEventListener("click", function (e) {
            var b = e.target.closest("[data-wrap]");
            if (b) {
                wrap(b.getAttribute("data-wrap"));
            }
        });
        panel.querySelector(".jk-live-panel__text").addEventListener("input", preview);
        var boxColors = panel.querySelector(".jk-live-panel__boxcolors");
        boxPal = palette({
            items: Object.keys(data.boxColors || {}).map(function (name) {
                return { value: name, label: data.boxColors[name], cls: "jk-bsw--" + name };
            }),
            value: "",
            kind: "fill",
            emptyCls: "jk-bsw--none",
            title: "বক্সের রং বাছুন",
            onPick: sendBox
        });
        boxColors.appendChild(boxPal.el);
        panel.querySelector(".jk-live-panel__box").addEventListener("click", function (e) {
            var b = e.target.closest("[data-box]");
            if (b) {
                sendBox(b.getAttribute("data-box"));
            }
        });
        panel.querySelector(".jk-live-panel__save").addEventListener("click", function () {
            send(false);
        });
        panel.querySelector(".jk-live-panel__reset").addEventListener("click", function () {
            send(true);
        });
        panel.querySelector(".jk-live-panel__cancel").addEventListener("click", close);
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                close();
            }
        });
    }

    function open(el) {
        var t = texts[parseInt(el.getAttribute("data-jk-key"), 10)];
        if (!t) {
            return;
        }
        closeIcon();
        if (!panel) {
            build();
        }
        if (current) {
            current.el.classList.remove("is-jk-editing");
        }
        current = { el: el, text: t };
        el.classList.add("is-jk-editing");
        panel.querySelector(".jk-live-panel__title").textContent = (t.label ? t.label + " · " : "") + "লেখা বদলান";
        panel.querySelector(".jk-live-panel__orig").textContent = "শুরুর লেখা: " + t.original;
        var area = panel.querySelector(".jk-live-panel__text");
        area.value = t.value;
        area.rows = t.value.length > 120 ? 6 : 3;
        var hint = [];
        t.placeholders.forEach(function (p) {
            hint.push(p + " রেখে দিন (এখানে নাম/এলাকা বসে)");
        });
        if (t.markup) {
            hint.push("শব্দ বেছে ওপরের বাটন চাপুন; কিছু না বাছলে পুরো লেখায় বসে");
        }
        panel.querySelector(".jk-live-panel__hint").textContent = hint.join(" · ");
        panel.querySelector(".jk-live-panel__msg").textContent = "";
        panel.querySelector(".jk-live-panel__reset").hidden = !t.changed;
        panel.querySelector(".jk-live-panel__tools").hidden = !t.markup;
        var pv = panel.querySelector(".jk-live-panel__preview");
        pv.hidden = !t.markup;
        pv.style.color = getComputedStyle(el).color;
        pv.style.backgroundColor = backgroundOf(el);
        preview();
        var box = el.closest("[data-jk-box]");
        var boxInfo = box ? (data.boxes || {})[box.getAttribute("data-jk-box")] : null;
        current.box = boxInfo ? box.getAttribute("data-jk-box") : null;
        panel.querySelector(".jk-live-panel__box").hidden = !boxInfo || !data.super;
        if (boxInfo) {
            boxPal.set(boxInfo.color);
            panel.querySelector(".jk-live-panel__boxreset").hidden = boxInfo.color === boxInfo.original;
        }
        place(panel, el);
        area.focus();
    }

    var COLOR_NAMES = {
        gold: "সোনালি", amber: "হলুদ-কমলা", white: "সাদা", red: "হালকা লাল", teal: "টিল",
        orange: "কমলা", maroon: "মেরুন", darkred: "গাঢ় লাল", brown: "বাদামি", black: "কালো"
    };

    // বাছা অংশে (কিছু বাছা না থাকলে পুরো লেখায়) সাজের চিহ্ন বসানো: {মোটা}, [[i|…]], [[u|…]], [[color:নাম|…]]
    function wrap(kind) {
        var area = panel.querySelector(".jk-live-panel__text");
        var a = area.selectionStart;
        var b = area.selectionEnd;
        if (a === b) {
            a = 0;
            b = area.value.length;
        }
        var part = area.value.slice(a, b);
        var out;
        if (kind === "clear") {
            out = stripMarkup(part);
        } else if (kind === "b") {
            out = "{" + part + "}";
        } else {
            out = "[[" + kind + "|" + part + "]]";
        }
        area.value = area.value.slice(0, a) + out + area.value.slice(b);
        area.focus();
        area.setSelectionRange(a, a + out.length);
        preview();
    }

    function stripMarkup(text) {
        var prev;
        do {
            prev = text;
            text = text.replace(/\[\[(?:color:[a-z]+|i|u)\|((?:(?!\[\[)[\s\S])*?)\]\]/g, "$1");
        } while (text !== prev);
        return text.replace(/\{([\s\S]+?)\}/g, "$1");
    }

    // সার্ভারের colored()-এর মতো: আগে escape, তারপর চিহ্ন বদল (তাই নমুনায় কোনো HTML ঢোকে না)
    function render(text) {
        var html = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        var re = /\[\[(color:[a-z]+|i|u)\|((?:(?!\[\[)[\s\S])*?)\]\]/g;
        var prev;
        var guard = 0;
        do {
            prev = html;
            html = html.replace(re, function (m, kind, inner) {
                if (kind === "i") {
                    return "<em>" + inner + "</em>";
                }
                if (kind === "u") {
                    return "<u>" + inner + "</u>";
                }
                var name = kind.slice(6);
                return (data.colors || []).indexOf(name) !== -1 ? '<span class="tc-' + name + '">' + inner + "</span>" : inner;
            });
        } while (html !== prev && ++guard < 20);
        return html.replace(/\{([\s\S]+?)\}/g, "<strong>$1</strong>");
    }

    function preview() {
        var pv = panel.querySelector(".jk-live-panel__preview");
        if (!pv.hidden) {
            pv.innerHTML = render(panel.querySelector(".jk-live-panel__text").value);
        }
    }

    // লেখাটার পেছনের আসল রং (স্বচ্ছ হলে ওপরের ঘরে খোঁজা), যাতে নমুনা পেইজের মতোই দেখায়
    function backgroundOf(el) {
        for (var n = el; n && n.nodeType === 1; n = n.parentElement) {
            var bg = getComputedStyle(n).backgroundColor;
            if (bg && bg !== "transparent" && !/rgba\(.*,\s*0\)$/.test(bg)) {
                return bg;
            }
        }
        return "#fff";
    }

    // বক্সের রং সঙ্গে সঙ্গে সংরক্ষণ (লেখার "সংরক্ষণ" থেকে আলাদা); খালি নাম = শুরুর রং
    function sendBox(color) {
        if (!current || !current.box) {
            return;
        }
        var msg = panel.querySelector(".jk-live-panel__msg");
        var body = new FormData();
        body.append("_token", data.token);
        body.append("slot", current.box);
        body.append("color", color);
        msg.textContent = "সংরক্ষণ হচ্ছে…";
        fetch(data.boxSave, { method: "POST", body: body, credentials: "same-origin" })
            .then(function (res) {
                return res.json();
            })
            .then(function (res) {
                if (res.ok) {
                    window.location.reload();
                    return;
                }
                msg.textContent = res.error || "সংরক্ষণ করা যায়নি।";
            })
            .catch(function () {
                msg.textContent = "সংরক্ষণ করা যায়নি। লগইনের মেয়াদ শেষ হয়ে থাকতে পারে; অ্যাডমিন পেইজটি রিফ্রেশ করুন।";
            });
    }

    function close() {
        if (panel) {
            panel.hidden = true;
        }
        if (current) {
            current.el.classList.remove("is-jk-editing");
            current = null;
        }
    }

    function send(reset) {
        if (!current) {
            return;
        }
        var msg = panel.querySelector(".jk-live-panel__msg");
        var body = new FormData();
        body.append("_token", data.token);
        body.append("key", current.text.key);
        if (reset) {
            body.append("reset", "1");
        } else {
            body.append("value", panel.querySelector(".jk-live-panel__text").value);
        }
        msg.textContent = "সংরক্ষণ হচ্ছে…";
        fetch(data.save, { method: "POST", body: body, credentials: "same-origin" })
            .then(function (res) {
                return res.json();
            })
            .then(function (res) {
                if (res.ok) {
                    window.location.reload(); // সাইটে যেমন দেখাবে, হুবহু তেমন দেখতে
                    return;
                }
                msg.textContent = res.error || "সংরক্ষণ করা যায়নি।";
            })
            .catch(function () {
                msg.textContent = "সংরক্ষণ করা যায়নি। লগইনের মেয়াদ শেষ হয়ে থাকতে পারে; অ্যাডমিন পেইজটি রিফ্রেশ করুন।";
            });
    }

    /* ---- ৪. পেইজের ছবি ও আইকনের বাক্স ---- */
    var iconPanel = null;
    var iconCurrent = null;

    function buildIcon() {
        iconPanel = document.createElement("div");
        iconPanel.className = "jk-live-panel jk-live-panel--icon";
        iconPanel.setAttribute("role", "dialog");
        iconPanel.setAttribute("aria-label", "ছবি বদলান");
        iconPanel.innerHTML =
            '<p class="jk-live-panel__title"></p>' +
            '<div class="jk-live-icon">' +
            '<figure><img class="jk-live-icon__now" alt=""><figcaption>এখন</figcaption></figure>' +
            '<figure><img class="jk-live-icon__orig" alt=""><figcaption>শুরুর ছবি</figcaption></figure>' +
            "</div>" +
            '<p class="jk-live-panel__hint jk-live-icon__size"></p>' +
            '<p class="jk-live-panel__hint jk-live-icon__rule">নতুন ছবি: JPG, PNG বা WebP (সর্বোচ্চ ২ MB)। আইকন ও লোগোতে স্বচ্ছ পটভূমির PNG সবচেয়ে ভালো মানায়। ছবি এই জায়গার মাপেই দেখাবে; এখনকার ছবির মতো একই অনুপাতের (চওড়া:উঁচু) ছবি দিলে নকশা ঠিক থাকে।</p>' +
            '<p class="jk-live-icon__pick"><input type="file" class="jk-live-icon__file" accept="image/jpeg,image/png,image/webp" aria-label="নতুন ছবি"></p>' +
            '<p class="jk-live-panel__msg" role="status"></p>' +
            '<div class="jk-live-panel__row">' +
            '<button type="button" class="jk-live-panel__save" data-icon-act="upload">নতুন ছবি বসান</button>' +
            '<button type="button" data-icon-act="reset">শুরুর ছবিতে ফিরুন</button>' +
            '<button type="button" data-icon-act="hide">লুকান</button>' +
            '<button type="button" data-icon-act="show">দেখান</button>' +
            '<button type="button" class="jk-live-icon__cancel">বাতিল</button>' +
            "</div>";
        document.body.appendChild(iconPanel);
        iconPanel.querySelector(".jk-live-icon__cancel").addEventListener("click", closeIcon);
        iconPanel.querySelector(".jk-live-panel__row").addEventListener("click", function (e) {
            var b = e.target.closest("[data-icon-act]");
            if (b) {
                sendIcon(b.getAttribute("data-icon-act"));
            }
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                closeIcon();
            }
        });
    }

    function openIcon(el) {
        var slot = el.getAttribute("data-jk-img");
        var info = (data.images || {})[slot];
        if (!info) {
            return;
        }
        close();
        if (!iconPanel) {
            buildIcon();
        }
        closeIcon();
        iconCurrent = { el: el, slot: slot };
        el.classList.add("is-jk-editing");
        var hidden = info.state === "hidden";
        iconPanel.querySelector(".jk-live-panel__title").textContent = info.label + (hidden ? " (এখন লুকানো)" : "");
        iconPanel.querySelector(".jk-live-icon__now").src = info.src;
        iconPanel.querySelector(".jk-live-icon__orig").src = info.original;
        iconPanel.querySelector(".jk-live-icon__file").value = "";
        iconPanel.querySelector(".jk-live-panel__msg").textContent = "";
        iconPanel.querySelector('[data-icon-act="reset"]').hidden = info.state !== "custom";
        iconPanel.querySelector('[data-icon-act="hide"]').hidden = !data.top || hidden;
        iconPanel.querySelector('[data-icon-act="show"]').hidden = !data.top || !hidden;
        iconPanel.querySelector('[data-icon-act="upload"]').hidden = hidden;
        iconPanel.querySelector(".jk-live-icon__pick").hidden = hidden;
        iconPanel.querySelector(".jk-live-icon__rule").hidden = hidden;
        var sizeNote = iconPanel.querySelector(".jk-live-icon__size");
        // আসল মাপ (ছবি লোড না হয়ে থাকলে ঘরের width/height, যা একই অনুপাতের)
        var w = el.naturalWidth || parseInt(el.getAttribute("width"), 10) || 0;
        var h = el.naturalHeight || parseInt(el.getAttribute("height"), 10) || 0;
        sizeNote.textContent = w && h ? "এখনকার ছবির মাপ: " + w + " × " + h + " পিক্সেল (চওড়া × উঁচু)।" : "";
        sizeNote.hidden = hidden || !w || !h;
        place(iconPanel, el);
    }

    function closeIcon() {
        if (iconPanel) {
            iconPanel.hidden = true;
        }
        if (iconCurrent) {
            iconCurrent.el.classList.remove("is-jk-editing");
            iconCurrent = null;
        }
    }

    function sendIcon(action) {
        if (!iconCurrent) {
            return;
        }
        var msg = iconPanel.querySelector(".jk-live-panel__msg");
        var body = new FormData();
        body.append("_token", data.token);
        body.append("slot", iconCurrent.slot);
        body.append("action", action);
        if (action === "upload") {
            var file = iconPanel.querySelector(".jk-live-icon__file").files[0];
            if (!file) {
                msg.textContent = "আগে একটা ছবি বাছুন।";
                return;
            }
            body.append("file", file);
        }
        msg.textContent = "সংরক্ষণ হচ্ছে…";
        fetch(data.imageSave, { method: "POST", body: body, credentials: "same-origin" })
            .then(function (res) {
                return res.json();
            })
            .then(function (res) {
                if (res.ok) {
                    window.location.reload();
                    return;
                }
                msg.textContent = res.error || "সংরক্ষণ করা যায়নি।";
            })
            .catch(function () {
                msg.textContent = "সংরক্ষণ করা যায়নি। লগইনের মেয়াদ শেষ হয়ে থাকতে পারে; অ্যাডমিন পেইজটি রিফ্রেশ করুন।";
            });
    }

    // বাক্সটা জিনিসটার ঠিক নিচে (জায়গা না থাকলে ওপরে), পর্দার ভেতরে
    function place(box, el) {
        box.hidden = false;
        var r = el.getBoundingClientRect();
        var w = Math.min(560, window.innerWidth - 24);
        box.style.width = w + "px";
        box.style.left = Math.max(12, Math.min(r.left, window.innerWidth - w - 12)) + "px";
        var below = r.bottom + 8;
        var h = box.offsetHeight;
        box.style.top = (below + h > window.innerHeight && r.top - h - 8 > 0 ? r.top - h - 8 : Math.max(12, Math.min(below, window.innerHeight - h - 12))) + "px";
        // লেখা বা নমুনা বাড়লে বাক্স লম্বা হয়: নিচ পর্দার বাইরে গেলে ওপরে তুলে আনা
        if (!box.jkFit && window.ResizeObserver) {
            box.jkFit = true;
            new ResizeObserver(function () {
                var top = parseFloat(box.style.top) || 12;
                var over = top + box.offsetHeight + 12 - window.innerHeight;
                if (!box.hidden && over > 0) {
                    box.style.top = Math.max(12, top - over) + "px";
                }
            }).observe(box);
        }
    }

    /* ---- ৭. ফর্মের ঘর: রং ও লুকানো/দেখানো (শুধু সুপার অ্যাডমিন; অন্যদের কাছে চিহ্নই আসে না) ---- */
    var fieldPanel = null;
    var fieldCurrent = null;
    var fieldPal = null;

    function buildField() {
        fieldPanel = document.createElement("div");
        fieldPanel.className = "jk-live-panel jk-live-panel--field";
        fieldPanel.setAttribute("role", "dialog");
        fieldPanel.setAttribute("aria-label", "ফর্মের ঘর");
        fieldPanel.innerHTML =
            '<p class="jk-live-panel__title"></p>' +
            '<div class="jk-live-panel__box"><span class="jk-live-panel__boxlabel">ঘরের রং:</span>' +
            '<span class="jk-live-field__colors"></span>' +
            '<button type="button" class="jk-live-field__colorreset" data-field-color="">সাধারণ রং</button></div>' +
            '<p class="jk-live-panel__hint"></p>' +
            '<p class="jk-live-panel__msg" role="status"></p>' +
            '<div class="jk-live-panel__row">' +
            '<button type="button" class="jk-live-panel__save" data-field-act="1">এই ঘর লুকান</button>' +
            '<button type="button" class="jk-live-panel__save" data-field-act="0">এই ঘর দেখান</button>' +
            '<button type="button" class="jk-live-field__cancel">বাতিল</button>' +
            "</div>";
        document.body.appendChild(fieldPanel);
        var colors = fieldPanel.querySelector(".jk-live-field__colors");
        fieldPal = palette({
            items: Object.keys(data.fieldColors || {}).map(function (name) {
                return { value: name, label: data.fieldColors[name], cls: "jk-fsw--" + name };
            }),
            value: "",
            kind: "fill",
            emptyCls: "jk-bsw--none",
            title: "ঘরের রং বাছুন",
            onPick: function (name) {
                sendField({ color: name });
            }
        });
        colors.appendChild(fieldPal.el);
        fieldPanel.querySelector(".jk-live-panel__box").addEventListener("click", function (e) {
            var b = e.target.closest("[data-field-color]");
            if (b) {
                sendField({ color: b.getAttribute("data-field-color") });
            }
        });
        fieldPanel.querySelector(".jk-live-field__cancel").addEventListener("click", closeField);
        fieldPanel.querySelector(".jk-live-panel__row").addEventListener("click", function (e) {
            var b = e.target.closest("[data-field-act]");
            if (b) {
                sendField({ hidden: b.getAttribute("data-field-act") });
            }
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                closeField();
            }
        });
    }

    function openField(box) {
        var id = box.getAttribute("data-jk-field");
        var info = (data.fields || {})[id];
        if (!info) {
            return;
        }
        close();
        closeIcon();
        if (!fieldPanel) {
            buildField();
        }
        closeField();
        fieldCurrent = { box: box, id: id };
        box.classList.add("is-jk-editing");
        fieldPanel.querySelector(".jk-live-panel__title").textContent = "ঘর: " + info.label + " (" + info.form + ")";
        fieldPanel.querySelector(".jk-live-panel__hint").textContent = !info.hideable
            ? "এই ঘর লুকানো যায় না (এটা ছাড়া লগইন, তালিকা বা খোঁজা চলে না); শুধু রং বদলানো যায়।"
            : info.hidden
                ? "এখন লুকানো: দর্শক ঘরটি দেখেন না, তাই এটা পূরণ করতেও হয় না।"
                : "এখন দেখা যাচ্ছে। লুকালে দর্শক ঘরটি দেখবেন না, আর এটা আবশ্যকও থাকবে না (খালি জমা হবে)।";
        fieldPal.set(info.color);
        fieldPanel.querySelector(".jk-live-field__colorreset").hidden = info.color === "";
        fieldPanel.querySelector(".jk-live-panel__msg").textContent = "";
        fieldPanel.querySelector('[data-field-act="1"]').hidden = !info.hideable || info.hidden;
        fieldPanel.querySelector('[data-field-act="0"]').hidden = !info.hideable || !info.hidden;
        place(fieldPanel, box);
    }

    function closeField() {
        if (fieldPanel) {
            fieldPanel.hidden = true;
        }
        if (fieldCurrent) {
            fieldCurrent.box.classList.remove("is-jk-editing");
            fieldCurrent = null;
        }
    }

    // change = {hidden: "1"/"0"} অথবা {color: নাম বা ""}
    function sendField(change) {
        if (!fieldCurrent) {
            return;
        }
        var msg = fieldPanel.querySelector(".jk-live-panel__msg");
        var body = new FormData();
        body.append("_token", data.token);
        body.append("field", fieldCurrent.id);
        Object.keys(change).forEach(function (k) {
            body.append(k, change[k]);
        });
        msg.textContent = "সংরক্ষণ হচ্ছে…";
        fetch(data.fieldSave, { method: "POST", body: body, credentials: "same-origin" })
            .then(function (res) {
                return res.json();
            })
            .then(function (res) {
                if (res.ok) {
                    window.location.reload();
                    return;
                }
                msg.textContent = res.error || "সংরক্ষণ করা যায়নি।";
            })
            .catch(function () {
                msg.textContent = "সংরক্ষণ করা যায়নি। লগইনের মেয়াদ শেষ হয়ে থাকতে পারে; অ্যাডমিন পেইজটি রিফ্রেশ করুন।";
            });
    }

    // প্রতিটি ঘরের কোণে চিহ্ন-বাটন
    if (data.super) {
        document.querySelectorAll("[data-jk-field]").forEach(function (box) {
            var info = (data.fields || {})[box.getAttribute("data-jk-field")];
            if (!info) {
                return;
            }
            var tag = document.createElement("button");
            tag.type = "button";
            tag.className = "jk-field__tag";
            tag.textContent = (info.hidden ? "লুকানো ঘর: " : "ঘর: ") + info.label;
            tag.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                openField(box);
            });
            box.appendChild(tag);
        });
    }

    /* ---- ৮. ফর্মে নতুন ঘর (অ্যাডমিনের যোগ করা): যোগ, বদল, ক্রম, মোছা (শুধু সুপার অ্যাডমিন) ---- */
    var customPanel = null;
    var customCurrent = null;

    function buildCustom() {
        customPanel = document.createElement("div");
        customPanel.className = "jk-live-panel jk-live-panel--custom";
        customPanel.setAttribute("role", "dialog");
        customPanel.setAttribute("aria-label", "ফর্মের নতুন ঘর");
        var types = "";
        Object.keys(data.customTypes || {}).forEach(function (k) {
            types += '<option value="' + k + '">' + data.customTypes[k] + "</option>";
        });
        customPanel.innerHTML =
            '<p class="jk-live-panel__title"></p>' +
            '<label class="jk-live-custom__lbl">ঘরের নাম (দর্শক যা দেখবেন)<input type="text" class="jk-live-custom__label" maxlength="200"></label>' +
            '<label class="jk-live-custom__lbl">ঘরের ধরন<select class="jk-live-custom__type">' + types + "</select></label>" +
            '<label class="jk-live-custom__lbl jk-live-custom__optwrap">ড্রপডাউনের বিকল্প (প্রতি লাইনে একটি)<textarea class="jk-live-custom__options" rows="4"></textarea></label>' +
            '<label class="jk-live-custom__req"><input type="checkbox" class="jk-live-custom__required"> আবশ্যক (না পূরণ করলে জমা হবে না, নামের পাশে *)</label>' +
            '<p class="jk-live-panel__hint">নতুন ঘর ফর্মের শেষে, সম্মতির টিকের ঠিক আগে বসে। পূরণ করা তথ্য ইমেইলে ও অ্যাডমিনের পেইজে দেখা যায়।</p>' +
            '<p class="jk-live-panel__msg" role="status"></p>' +
            '<div class="jk-live-panel__row">' +
            '<button type="button" class="jk-live-panel__save" data-custom-act="save">সংরক্ষণ করুন</button>' +
            '<button type="button" data-custom-act="up">ওপরে</button>' +
            '<button type="button" data-custom-act="down">নিচে</button>' +
            '<button type="button" data-custom-act="delete">ঘরটি মুছুন</button>' +
            '<button type="button" class="jk-live-custom__cancel">বাতিল</button>' +
            "</div>";
        document.body.appendChild(customPanel);
        var type = customPanel.querySelector(".jk-live-custom__type");
        type.addEventListener("change", function () {
            customPanel.querySelector(".jk-live-custom__optwrap").hidden = type.value !== "select";
        });
        customPanel.querySelector(".jk-live-custom__cancel").addEventListener("click", closeCustom);
        customPanel.querySelector(".jk-live-panel__row").addEventListener("click", function (e) {
            var b = e.target.closest("[data-custom-act]");
            if (b) {
                sendCustom(b.getAttribute("data-custom-act"));
            }
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                closeCustom();
            }
        });
    }

    // field = আগের ঘর (বদলাতে) অথবা null (নতুন); form = কোন ফর্মে
    function openCustom(anchor, form, field) {
        close();
        closeIcon();
        closeField();
        if (!customPanel) {
            buildCustom();
        }
        closeCustom();
        customCurrent = { el: anchor, form: form, id: field ? field.id : null };
        anchor.classList.add("is-jk-editing");
        var info = (data.customForms || {})[form] || {};
        customPanel.querySelector(".jk-live-panel__title").textContent = (field ? "নতুন ঘর বদলান" : "নতুন ঘর যোগ করুন") + " (" + (info.label || "") + ")";
        customPanel.querySelector(".jk-live-custom__label").value = field ? field.label : "";
        customPanel.querySelector(".jk-live-custom__type").value = field ? field.field_type : "text";
        customPanel.querySelector(".jk-live-custom__options").value = field ? field.options.join("\n") : "";
        customPanel.querySelector(".jk-live-custom__optwrap").hidden = (field ? field.field_type : "text") !== "select";
        customPanel.querySelector(".jk-live-custom__required").checked = field ? field.is_required : false;
        customPanel.querySelector(".jk-live-panel__msg").textContent = "";
        ["up", "down", "delete"].forEach(function (a) {
            customPanel.querySelector('[data-custom-act="' + a + '"]').hidden = !field;
        });
        place(customPanel, anchor);
        customPanel.querySelector(".jk-live-custom__label").focus();
    }

    function closeCustom() {
        if (customPanel) {
            customPanel.hidden = true;
        }
        if (customCurrent) {
            customCurrent.el.classList.remove("is-jk-editing");
            customCurrent = null;
        }
    }

    function sendCustom(action) {
        if (!customCurrent) {
            return;
        }
        if (action === "delete" && !window.confirm("এই ঘর আর এতে পূরণ করা সব তথ্য চিরতরে মুছে যাবে। মুছবেন?")) {
            return;
        }
        var msg = customPanel.querySelector(".jk-live-panel__msg");
        var body = new FormData();
        body.append("_token", data.token);
        body.append("action", action === "save" ? (customCurrent.id ? "update" : "add") : action);
        body.append("form", customCurrent.form);
        if (customCurrent.id) {
            body.append("id", customCurrent.id);
        }
        body.append("label", customPanel.querySelector(".jk-live-custom__label").value);
        body.append("field_type", customPanel.querySelector(".jk-live-custom__type").value);
        body.append("options", customPanel.querySelector(".jk-live-custom__options").value);
        body.append("is_required", customPanel.querySelector(".jk-live-custom__required").checked ? "1" : "0");
        msg.textContent = "সংরক্ষণ হচ্ছে…";
        fetch(data.customSave, { method: "POST", body: body, credentials: "same-origin" })
            .then(function (res) {
                return res.json();
            })
            .then(function (res) {
                if (res.ok) {
                    window.location.reload();
                    return;
                }
                msg.textContent = res.error || "সংরক্ষণ করা যায়নি।";
            })
            .catch(function () {
                msg.textContent = "সংরক্ষণ করা যায়নি। লগইনের মেয়াদ শেষ হয়ে থাকতে পারে; অ্যাডমিন পেইজটি রিফ্রেশ করুন।";
            });
    }

    // যোগ-করা প্রতিটি ঘরের কোণে চিহ্ন, আর তালিকার শেষে "+ নতুন ঘর যোগ করুন"
    if (data.super) {
        Object.keys(data.customForms || {}).forEach(function (form) {
            (data.customForms[form].fields || []).forEach(function (field) {
                var box = document.querySelector('[data-jk-custom="' + field.id + '"]');
                if (!box) {
                    return;
                }
                var tag = document.createElement("button");
                tag.type = "button";
                tag.className = "jk-field__tag jk-field__tag--custom";
                tag.textContent = "নতুন ঘর: " + field.label;
                tag.addEventListener("click", function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openCustom(box, form, field);
                });
                box.appendChild(tag);
            });
        });
        document.querySelectorAll("[data-jk-custom-add]").forEach(function (spot) {
            var add = document.createElement("button");
            add.type = "button";
            add.className = "jk-custom-add";
            add.textContent = "+ নতুন ঘর যোগ করুন";
            add.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                openCustom(add, spot.getAttribute("data-jk-custom-add"), null);
            });
            spot.appendChild(add);
        });
    }

    // চিহ্নিত লেখায় ক্লিক = বদলানোর বাক্স (লিংকের পেইজে যাওয়া বা বাটনের কাজ হয় না)
    /* ---- "এই পেইজের বার্তা": সফলতা/তথ্যের বার্তা চালু-বন্ধ (সুপার অ্যাডমিন; বাটন partials/live-messages.php-তে) ---- */
    document.querySelectorAll("[data-jk-msg-switch]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var warn = btn.getAttribute("data-warn");
            if (warn && !window.confirm(warn + "\n\nবার্তাটি বন্ধ করবেন?")) {
                return;
            }
            var body = new FormData();
            body.append("_token", data.token);
            body.append("name", btn.getAttribute("data-jk-msg-switch"));
            body.append("on", btn.getAttribute("data-on"));
            btn.disabled = true;
            fetch(data.messageSave, { method: "POST", body: body, credentials: "same-origin" })
                .then(function (res) {
                    return res.json();
                })
                .then(function (res) {
                    if (res.ok) {
                        window.location.reload();
                        return;
                    }
                    btn.disabled = false;
                    window.alert(res.error || "সংরক্ষণ করা যায়নি।");
                })
                .catch(function () {
                    btn.disabled = false;
                    window.alert("সংরক্ষণ করা যায়নি। লগইনের মেয়াদ শেষ হয়ে থাকতে পারে; অ্যাডমিন পেইজটি রিফ্রেশ করুন।");
                });
        });
    });

    /* ---- "এই পেইজের বার্তা": বাক্সের ডিজাইন, রং ও সময় ঠিক করা (সুপার অ্যাডমিন; ঘরগুলো partials/live-messages.php-তে) ---- */
    document.querySelectorAll("[data-jk-msg-style]").forEach(function (box) {
        var save = box.querySelector("[data-jk-msg-style-save]");
        // রঙের তালিকা (select) লুকিয়ে তার জায়গায় রঙের প্লেট; বাছা মান select-এই থাকে, তাই নিচের সংরক্ষণ-কোড বদলায়নি
        var colorSelect = box.querySelector('select[name="color"]');
        if (colorSelect) {
            var pal = palette({
                items: Array.prototype.map.call(colorSelect.options, function (o) {
                    return { value: o.value, label: o.textContent.trim(), cls: "jk-msw--" + (o.value || "default") };
                }),
                value: colorSelect.value,
                kind: "fill",
                title: "বার্তার বাক্সের রং বাছুন",
                onPick: function (v) {
                    colorSelect.value = v;
                }
            });
            colorSelect.hidden = true;
            colorSelect.parentNode.appendChild(pal.el);
        }
        save.addEventListener("click", function () {
            var body = new FormData();
            body.append("_token", data.token);
            body.append("name", box.getAttribute("data-jk-msg-style"));
            ["design", "color", "seconds"].forEach(function (field) {
                body.append(field, box.querySelector('[name="' + field + '"]').value);
            });
            save.disabled = true;
            fetch(data.messageSave, { method: "POST", body: body, credentials: "same-origin" })
                .then(function (res) {
                    return res.json();
                })
                .then(function (res) {
                    if (res.ok) {
                        window.location.reload();
                        return;
                    }
                    save.disabled = false;
                    window.alert(res.error || "সংরক্ষণ করা যায়নি।");
                })
                .catch(function () {
                    save.disabled = false;
                    window.alert("সংরক্ষণ করা যায়নি। লগইনের মেয়াদ শেষ হয়ে থাকতে পারে; অ্যাডমিন পেইজটি রিফ্রেশ করুন।");
                });
        });
    });

    /* ---- হোমের সেকশন: প্রতিটি সেকশনের ওপরের পট্টি (সুপার অ্যাডমিন; partials/home-section-slot.php) —
       সরানো, লুকানো/দেখানো, নতুন সেকশন যোগ ও মোছা সঙ্গে সঙ্গে সংরক্ষণ; "সম্পাদনা" পুরো অ্যাডমিন-পর্দায় সেকশনের পাতা খোলে ---- */
    document.querySelectorAll("[data-jk-home]").forEach(function (slot) {
        slot.querySelectorAll(".jk-home-bar button").forEach(function (btn) {
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                var act = btn.getAttribute("data-act");
                if (act === "edit") {
                    window.top.location.href = btn.getAttribute("data-url");
                    return;
                }
                var question = btn.getAttribute("data-confirm");
                if (question && !window.confirm(question)) {
                    return;
                }
                var body = new FormData();
                body.append("_token", data.token);
                body.append("id", slot.getAttribute("data-jk-home"));
                body.append("do", act);
                btn.disabled = true;
                fetch(data.homeSave, { method: "POST", body: body, credentials: "same-origin" })
                    .then(function (res) {
                        return res.json();
                    })
                    .then(function (res) {
                        if (res.ok) {
                            window.location.reload();
                            return;
                        }
                        btn.disabled = false;
                        window.alert(res.error || "সংরক্ষণ করা যায়নি।");
                    })
                    .catch(function () {
                        btn.disabled = false;
                        window.alert("সংরক্ষণ করা যায়নি। লগইনের মেয়াদ শেষ হয়ে থাকতে পারে; অ্যাডমিন পেইজটি রিফ্রেশ করুন।");
                    });
            });
        });
    });

    document.addEventListener("click", function (e) {
        if ((panel && panel.contains(e.target)) || (iconPanel && iconPanel.contains(e.target)) || (fieldPanel && fieldPanel.contains(e.target)) || (customPanel && customPanel.contains(e.target))) {
            return;
        }
        var ic = e.target.closest("[data-jk-img]");
        if (ic) {
            e.preventDefault();
            e.stopPropagation();
            openIcon(ic);
            return;
        }
        // মেনুর লিংক (লেখা নয়, মেনু-আইটেম): মেনু সম্পাদনার বাক্স; ওপরের মেনু শুধু সুপার, ফুটার অনুমতি "footer" হলেই
        var menuNav = e.target.closest("[data-jk-menu]");
        if (menuNav && e.target.closest("a") && (data.super || (data.footerBox && menuNav.getAttribute("data-jk-menu") === "footer"))) {
            e.preventDefault();
            e.stopPropagation();
            close();
            window.parent.postMessage({ jk: "open-menu" }, window.location.origin);
            return;
        }
        // হোমের সেবা-কার্ড: সেবার তালিকার অ্যাডমিন-পেইজে নিয়ে যায় (অনুমতি না থাকলে প্যারেন্ট কিছু করে না, কার্ডের লিংকও বন্ধ থাকে)
        var card = e.target.closest(".service-card");
        if (card) {
            e.preventDefault();
            e.stopPropagation();
            close();
            window.parent.postMessage({ jk: "open-services" }, window.location.origin);
            return;
        }
        var el = e.target.closest("[data-jk-key]");
        var rich = e.target.closest("[data-jk-rich]");
        if (rich && (!el || rich.contains(el))) {
            e.preventDefault();
            e.stopPropagation();
            close();
            closeIcon();
            window.parent.postMessage({ jk: "open-rich" }, window.location.origin);
            return;
        }
        if (!el) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        open(el);
    }, true);
})();
