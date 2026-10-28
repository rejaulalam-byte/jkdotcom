/* =====================================================================================================
   অ্যাডমিনের "লাইভ পেইজ এডিট"-এর ফ্রেমে চলে (শুধু লগইন-করা অ্যাডমিনের ?live=1 পেইজে লোড হয়; app.js-এর আগে)।
   ১) সার্ভার প্রতিটি সম্পাদনাযোগ্য লেখার দুই পাশে অদৃশ্য চিহ্ন বসায় (App\Core\LiveEdit); এখানে সেগুলো খুঁজে
      লেখাটাকে (বা পুরো ঘরকে) data-jk-key চিহ্নিত করা হয়, তারপর চিহ্ন মুছে ফেলা হয় — পেইজ দেখতে হুবহু আগের মতো।
   ২) চিহ্নিত লেখায় ক্লিক করলে সেখানেই একটা বাক্স খোলে: লেখা বদলে "সংরক্ষণ করুন" চাপলে সঙ্গে সঙ্গে সাইটে বদলায়।
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
            '<textarea class="jk-live-panel__text" rows="3" maxlength="3000"></textarea>' +
            '<p class="jk-live-panel__hint"></p>' +
            '<p class="jk-live-panel__msg" role="status"></p>' +
            '<div class="jk-live-panel__row">' +
            '<button type="button" class="jk-live-panel__save">সংরক্ষণ করুন</button>' +
            '<button type="button" class="jk-live-panel__cancel">বাতিল</button>' +
            '<button type="button" class="jk-live-panel__reset">শুরুর লেখায় ফিরুন</button>' +
            "</div>";
        document.body.appendChild(panel);
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
            hint.push("রং: [[color:gold|শব্দ]] (gold, amber, white, red), মোটা: {শব্দ}");
        }
        panel.querySelector(".jk-live-panel__hint").textContent = hint.join(" · ");
        panel.querySelector(".jk-live-panel__msg").textContent = "";
        panel.querySelector(".jk-live-panel__reset").hidden = !t.changed;

        // বাক্সটা লেখার ঠিক নিচে (জায়গা না থাকলে ওপরে), পর্দার ভেতরে
        panel.hidden = false;
        var r = el.getBoundingClientRect();
        var w = Math.min(560, window.innerWidth - 24);
        panel.style.width = w + "px";
        panel.style.left = Math.max(12, Math.min(r.left, window.innerWidth - w - 12)) + "px";
        var below = r.bottom + 8;
        var h = panel.offsetHeight;
        panel.style.top = (below + h > window.innerHeight && r.top - h - 8 > 0 ? r.top - h - 8 : Math.min(below, window.innerHeight - h - 12)) + "px";
        area.focus();
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

    // চিহ্নিত লেখায় ক্লিক = বদলানোর বাক্স (লিংকের পেইজে যাওয়া বা বাটনের কাজ হয় না)
    document.addEventListener("click", function (e) {
        if (panel && panel.contains(e.target)) {
            return;
        }
        var el = e.target.closest("[data-jk-key]");
        if (!el) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        open(el);
    }, true);
})();
