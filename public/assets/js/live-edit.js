/* =====================================================================================================
   অ্যাডমিনের "লাইভ পেইজ এডিট"-এর ফ্রেমে চলে (শুধু লগইন-করা অ্যাডমিনের ?live=1 পেইজে লোড হয়; app.js-এর আগে)।
   ১) সার্ভার প্রতিটি সম্পাদনাযোগ্য লেখার দুই পাশে অদৃশ্য চিহ্ন বসায় (App\Core\LiveEdit); এখানে সেগুলো খুঁজে
      লেখাটাকে (বা পুরো ঘরকে) data-jk-key চিহ্নিত করা হয়, তারপর চিহ্ন মুছে ফেলা হয় — পেইজ দেখতে হুবহু আগের মতো।
   ২) চিহ্নিত লেখায় ক্লিক করলে সেখানেই একটা বাক্স খোলে: লেখা বদলে "সংরক্ষণ করুন" চাপলে সঙ্গে সঙ্গে সাইটে বদলায়।
   ৩) যে লেখায় সাজ চলে (সার্ভারের colored()/t()), বাক্সে বোল্ড/ইটালিক/আন্ডারলাইন/রঙের বাটন আর পেইজের মতো দেখতে একটা নমুনা।
   ৪) পেইজের ছবি ও ছোট আইকনে (data-jk-img) ক্লিক: নতুন ছবি দেওয়া, শুরুর ছবিতে ফেরা, লুকানো/দেখানো (শেষ দুটি সুপার অ্যাডমিন)।
   ৫) ছোট হেডিংয়ের বক্সে (data-jk-box) থাকা লেখায় ক্লিক: লেখার বাক্সে "বক্সের রং" সারি (শুধু সুপার অ্যাডমিন)।
   ৬) খোলা পেইজের মূল লেখায় (data-jk-rich) ক্লিক: অ্যাডমিনের পর্দাকে জানানো হয়, সেখানে পূর্ণ এডিটরের বড় বাক্স খোলে।
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

    /* ---- ৩. বদলানোর বাক্স ---- */
    var panel = null;
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
        (data.colors || []).forEach(function (name) {
            var b = document.createElement("button");
            b.type = "button";
            b.className = "jk-sw jk-sw--" + name;
            b.setAttribute("data-wrap", "color:" + name);
            b.title = (COLOR_NAMES[name] || name) + " রং";
            b.setAttribute("aria-label", b.title);
            colors.appendChild(b);
        });
        panel.querySelector(".jk-live-panel__tools").addEventListener("click", function (e) {
            var b = e.target.closest("[data-wrap]");
            if (b) {
                wrap(b.getAttribute("data-wrap"));
            }
        });
        panel.querySelector(".jk-live-panel__text").addEventListener("input", preview);
        var boxColors = panel.querySelector(".jk-live-panel__boxcolors");
        Object.keys(data.boxColors || {}).forEach(function (name) {
            var b = document.createElement("button");
            b.type = "button";
            b.className = "jk-bsw jk-bsw--" + name;
            b.setAttribute("data-box", name);
            b.title = data.boxColors[name];
            b.setAttribute("aria-label", "বক্সের রং: " + data.boxColors[name]);
            boxColors.appendChild(b);
        });
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
            panel.querySelectorAll(".jk-bsw").forEach(function (b) {
                b.classList.toggle("is-on", b.getAttribute("data-box") === boxInfo.color);
            });
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
        iconPanel.querySelector('[data-icon-act="hide"]').hidden = !data.super || hidden;
        iconPanel.querySelector('[data-icon-act="show"]').hidden = !data.super || !hidden;
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
    }

    // চিহ্নিত লেখায় ক্লিক = বদলানোর বাক্স (লিংকের পেইজে যাওয়া বা বাটনের কাজ হয় না)
    document.addEventListener("click", function (e) {
        if ((panel && panel.contains(e.target)) || (iconPanel && iconPanel.contains(e.target))) {
            return;
        }
        var ic = e.target.closest("[data-jk-img]");
        if (ic) {
            e.preventDefault();
            e.stopPropagation();
            openIcon(ic);
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
