/* রঙের প্লেট: একটা আইকনে ক্লিক করলে রঙের খোপ খোলে, রঙ চোখে দেখে বাছা যায় (নাম নিচের লাইনে)।
   লাইভ পেইজ এডিটের বাক্স (live-edit.js) আর অ্যাডমিনের পেইজ (admin.js) দুই জায়গাতেই ব্যবহার হয়; স্টাইল palette.css-এ। */
(function () {
    "use strict";

    /* opts: items [{value, label, cls অথবা color}] — cls = palette.css-এর রঙের ক্লাস, color = সরাসরি CSS রং (জেএস দিয়ে বসে, CSP আটকায় না);
       value (এখনকার); kind "text" (নিচে রঙের দাগসহ "A") | "fill" (বর্তমান রঙের চারকোনা); emptyCls (কিছু বাছা না থাকলে দাগের ক্লাস);
       title; onPick(value) */
    function palette(opts) {
        var wrap = document.createElement("span");
        wrap.className = "jk-pal";
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "jk-pal__btn" + (opts.kind === "fill" ? " jk-pal__btn--fill" : "");
        btn.title = opts.title;
        btn.setAttribute("aria-label", opts.title);
        btn.setAttribute("aria-haspopup", "true");
        btn.setAttribute("aria-expanded", "false");
        var bar = document.createElement("span");
        bar.className = "jk-pal__bar";
        if (opts.kind === "fill") {
            btn.appendChild(bar);
            btn.appendChild(document.createTextNode("▾"));
        } else {
            btn.appendChild(document.createTextNode("A"));
            btn.appendChild(bar);
        }
        var pop = document.createElement("div");
        pop.className = "jk-pal__pop";
        pop.hidden = true;
        var grid = document.createElement("div");
        grid.className = "jk-pal__grid";
        var name = document.createElement("p");
        name.className = "jk-pal__name";
        var current = opts.value;

        function find(v) {
            return opts.items.filter(function (i) {
                return i.value === v;
            })[0];
        }
        function nameOf(v) {
            var found = find(v);
            return found ? found.label : "";
        }
        function mark(v) {
            current = v;
            var found = find(v);
            bar.className = "jk-pal__bar " + (found ? found.cls || "" : opts.emptyCls || "");
            bar.style.background = found && found.color ? found.color : "";
            grid.querySelectorAll(".jk-pal__sw").forEach(function (s) {
                s.classList.toggle("is-on", s.getAttribute("data-v") === v);
            });
            name.textContent = nameOf(v);
        }
        function toggle(open) {
            pop.hidden = !open;
            btn.setAttribute("aria-expanded", open ? "true" : "false");
            // নিচে জায়গা না থাকলে (ছোট পর্দা) প্লেট ওপরের দিকে খোলে
            pop.classList.remove("jk-pal__pop--up");
            if (open) {
                var r = pop.getBoundingClientRect();
                var b = btn.getBoundingClientRect();
                if (r.bottom > window.innerHeight && b.top - r.height - 4 > 0) {
                    pop.classList.add("jk-pal__pop--up");
                }
            }
        }

        opts.items.forEach(function (item) {
            var s = document.createElement("button");
            s.type = "button";
            s.className = "jk-pal__sw " + (item.cls || "");
            if (item.color) {
                s.style.background = item.color;
            }
            s.setAttribute("data-v", item.value);
            s.title = item.label;
            s.setAttribute("aria-label", item.label);
            s.addEventListener("mouseenter", function () {
                name.textContent = item.label;
            });
            s.addEventListener("focus", function () {
                name.textContent = item.label;
            });
            s.addEventListener("mouseleave", function () {
                name.textContent = nameOf(current);
            });
            s.addEventListener("click", function () {
                mark(item.value);
                toggle(false);
                opts.onPick(item.value);
            });
            grid.appendChild(s);
        });
        pop.appendChild(grid);
        pop.appendChild(name);
        wrap.appendChild(btn);
        wrap.appendChild(pop);
        btn.addEventListener("click", function () {
            toggle(pop.hidden);
        });
        document.addEventListener("click", function (e) {
            if (!pop.hidden && !wrap.contains(e.target)) {
                toggle(false);
            }
        });
        wrap.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && !pop.hidden) {
                e.stopPropagation();
                toggle(false);
                btn.focus();
            }
        });
        mark(current);
        return { el: wrap, set: mark };
    }

    window.jkPalette = palette;
})();
