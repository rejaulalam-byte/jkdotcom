/* jorurikaj.com — small enhancements. The site works without JavaScript;
   this file only adds: mobile menu, sub-menus, the "today's jobs" scroller
   and the scrolling advertisement strip. */
(function () {
    "use strict";

    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---- Mobile menu + sub-menus ---------------------------------------- */
    var toggle = document.querySelector(".nav-toggle");
    var nav = document.getElementById("site-nav");
    if (toggle && nav) {
        toggle.addEventListener("click", function () {
            var open = toggle.getAttribute("aria-expanded") === "true";
            toggle.setAttribute("aria-expanded", String(!open));
            nav.classList.toggle("is-open", !open);
        });
    }
    document.querySelectorAll(".sub-toggle").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var item = btn.closest(".has-sub");
            var open = item.classList.toggle("is-open");
            btn.setAttribute("aria-expanded", String(open));
        });
    });
    document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") {
            return;
        }
        document.querySelectorAll(".has-sub.is-open").forEach(function (item) {
            item.classList.remove("is-open");
            var b = item.querySelector(".sub-toggle");
            if (b) {
                b.setAttribute("aria-expanded", "false");
            }
        });
    });

    /* ---- Hero: every letter flies in from its own direction ---------------- */
    var heroEl = document.querySelector(".hero");
    if (heroEl && !reduceMotion) {
        var segmenter = window.Intl && Intl.Segmenter ? new Intl.Segmenter("bn", { granularity: "grapheme" }) : null;
        var flyLetters = [];
        Array.prototype.forEach.call(heroEl.querySelectorAll("[data-fly]"), function (el) {
            var text = el.textContent;
            var frag = document.createDocumentFragment();
            text.split(/(\s+)/).forEach(function (part) {
                if (part === "") {
                    return;
                }
                if (/^\s+$/.test(part)) {
                    frag.appendChild(document.createTextNode(" "));
                    return;
                }
                var word = document.createElement("span");
                word.className = "fly-word";
                // grapheme clusters keep Bangla conjuncts and vowel signs together;
                // an old browser without Intl.Segmenter lets the whole word fly as one piece
                var pieces = segmenter
                    ? Array.from(segmenter.segment(part), function (s) { return s.segment; })
                    : [part];
                // the site name at the start of a word ("Jorurikaj.com-এ") stays bold + coloured
                var brandLeft = /^jorurikaj\.com/i.test(part) ? "jorurikaj.com".length : 0;
                pieces.forEach(function (piece) {
                    var letter = document.createElement("span");
                    letter.className = "fly-letter";
                    if (brandLeft > 0) {
                        letter.className += " brandname";
                        brandLeft -= piece.length;
                    }
                    letter.textContent = piece;
                    word.appendChild(letter);
                    flyLetters.push(letter);
                });
                frag.appendChild(word);
            });
            var visual = document.createElement("span");
            visual.setAttribute("aria-hidden", "true");
            visual.appendChild(frag);
            var plain = document.createElement("span");
            plain.className = "sr-only";
            plain.textContent = text; // screen readers get the plain text
            el.textContent = "";
            el.appendChild(plain);
            el.appendChild(visual);
            el.classList.add("is-split");
        });
        var reach = Math.max(window.innerWidth, 600);
        var step = Math.min(0.03, 2.4 / Math.max(1, flyLetters.length)); // the whole stream takes about 2.4 s
        flyLetters.forEach(function (letter, i) {
            var angle = Math.random() * Math.PI * 2;
            var dist = reach * (0.25 + Math.random() * 0.5);
            letter.style.setProperty("--dx", Math.round(Math.cos(angle) * dist) + "px");
            letter.style.setProperty("--dy", Math.round(Math.sin(angle) * dist * 0.7) + "px");
            letter.style.setProperty("--rot", Math.round((Math.random() - 0.5) * 540) + "deg");
            letter.style.animationDelay = (i * step + Math.random() * 0.15).toFixed(3) + "s";
        });
        heroEl.style.setProperty("--btn-delay", (flyLetters.length * step + 0.9).toFixed(2) + "s");
    }

    /* ---- Button "back to top" -------------------------------------------------
       Works like a scroll thumb: hidden in the first half of the page; past the middle it
       fades in at the middle of the screen and slides down the screen with the scrolling,
       reaching the bottom when the page ends; scrolling up reverses it. A click glides to
       the top slowly (about 3 px per ms, 0.9 - 3.5 s) and stops if the visitor takes over. */
    var toTop = document.querySelector("[data-to-top]");
    if (toTop) {
        var placeToTop = function () {
            var scrollable = document.documentElement.scrollHeight - window.innerHeight;
            var progress = scrollable > 0 ? window.scrollY / scrollable : 0;
            if (progress <= 0.5) {
                toTop.style.opacity = "0";
                toTop.style.visibility = "hidden";
                return;
            }
            var size = toTop.offsetHeight;
            var margin = 24;
            var along = Math.min(1, (progress - 0.5) * 2);
            var startTop = window.innerHeight / 2 - size / 2;
            var endTop = window.innerHeight - margin - size;
            toTop.style.top = (startTop + along * (endTop - startTop)) + "px";
            toTop.style.opacity = String(Math.min(1, (progress - 0.5) / 0.06));
            toTop.style.visibility = "visible";
        };
        window.addEventListener("scroll", placeToTop, { passive: true });
        window.addEventListener("resize", placeToTop);
        placeToTop();

        var gliding = false;
        ["wheel", "touchstart", "keydown"].forEach(function (name) {
            window.addEventListener(name, function () { gliding = false; }, { passive: true });
        });
        toTop.addEventListener("click", function () {
            var from = window.scrollY;
            if (reduceMotion || from < 1) {
                window.scrollTo({ top: 0, behavior: "instant" });
                return;
            }
            var duration = Math.min(3500, Math.max(900, from / 3));
            var started = null;
            gliding = true;
            var frame = function (now) {
                if (!gliding) {
                    return;
                }
                started = started === null ? now : started;
                var k = Math.min(1, (now - started) / duration);
                var eased = k < 0.5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
                window.scrollTo({ top: from * (1 - eased), behavior: "instant" });
                if (k < 1) {
                    requestAnimationFrame(frame);
                } else {
                    gliding = false;
                }
            };
            requestAnimationFrame(frame);
        });
    }
    /* ---- FAQ: opening one question closes the others ---------------------
       (browsers that know <details name="..."> already do this themselves) */
    var faqItems = document.querySelectorAll(".faq-item");
    faqItems.forEach(function (item) {
        item.addEventListener("toggle", function () {
            if (!item.open) {
                return;
            }
            faqItems.forEach(function (other) {
                if (other !== item && other.open) {
                    other.open = false;
                }
            });
        });
    });

    /* ---- "আজকের কাজের তালিকা" scroller ------------------------------------
       Shows N rows. Each page of rows rises SLOWLY from the bottom (data-rise,
       default 4s, so the rows can be read while rising), rests for a few
       seconds (data-hold, default 5.5s), then quickly moves up and away; the
       next page rises the moment the last row of the previous one has left. Without JS or
       with "reduce motion" the plain list (first N rows) is shown. */
    var ticker = document.querySelector(".ticker[data-page-size]");
    if (ticker && !reduceMotion && ticker.animate) {
        var size = parseInt(ticker.getAttribute("data-page-size"), 10) || 8;
        var hold = parseInt(ticker.getAttribute("data-hold"), 10) || 5500;
        var rise = parseInt(ticker.getAttribute("data-rise"), 10) || 4000;
        var rows = Array.prototype.slice.call(ticker.children);

        if (rows.length > size) {
            // pages of `size` rows (the last page may be shorter)
            var pages = [];
            for (var i = 0; i < rows.length; i += size) {
                var page = document.createElement("ol");
                page.className = "ticker__page";
                rows.slice(i, i + size).forEach(function (row) {
                    page.appendChild(row);
                });
                ticker.appendChild(page);
                pages.push(page);
            }
            ticker.classList.add("is-live");

            var sleep = function (ms) {
                return new Promise(function (resolve) {
                    setTimeout(resolve, ms);
                });
            };
            var easing = "cubic-bezier(.25,.1,.25,1)";
            var play = function (el, from, to, ms) {
                return el.animate([{ transform: from }, { transform: to }], {
                    duration: ms,
                    easing: easing,
                    fill: "forwards"
                }).finished;
            };
            var whenVisible = function () {
                return new Promise(function (resolve) {
                    if (!document.hidden) {
                        resolve();
                        return;
                    }
                    document.addEventListener("visibilitychange", function once() {
                        if (!document.hidden) {
                            document.removeEventListener("visibilitychange", once);
                            resolve();
                        }
                    });
                });
            };

            // Owner 2026-10-20: the next page starts to rise at the very moment the LAST row of the
            // page before has left the top. A short last page (e.g. 4 of 8 rows) only has to travel
            // as far as its rows are long, so it leaves sooner and nothing waits for empty space.
            (async function loop() {
                var index = 0;
                for (;;) {
                    var current = pages[index];
                    var count = current.children.length;
                    await whenVisible();
                    current.classList.add("is-shown");
                    await play(current, "translateY(100%)", "translateY(0)", rise);
                    await sleep(hold);
                    await whenVisible();
                    await play(current, "translateY(0)", "translateY(-" + (count * 100 / size) + "%)", rise * count / size);
                    current.classList.remove("is-shown");
                    index = (index + 1) % pages.length;
                }
            })();
        }
    }

    /* ---- Teal text bar marquee: same speed whatever the text length ------- */
    document.querySelectorAll("[data-marquee]").forEach(function (bar) {
        var move = bar.querySelector(".rep-bar__move");
        if (!move || reduceMotion) {
            return;
        }
        var setSpeed = function () {
            var pxPerSecond = window.innerWidth < 768 ? 70 : 140;
            var seconds = Math.max(8, move.getBoundingClientRect().width / pxPerSecond);
            bar.style.setProperty("--marquee-duration", seconds.toFixed(1) + "s");
        };
        setSpeed();
        window.addEventListener("resize", setSpeed);
    });

    /* ---- Services: two rows running right -> left, each on its own -------
       The cards of a row are repeated so the loop has no gap. Row 2 runs at a
       different speed and starts at a different point, so the rows never move
       in step. Hover / keyboard focus pauses (CSS). */
    document.querySelectorAll("[data-service-row]").forEach(function (row) {
        var track = row.querySelector(".service-row__track");
        if (!track || reduceMotion) {
            return;
        }
        var items = Array.prototype.slice.call(track.children);
        if (items.length < 2) {
            return;
        }
        var second = row.getAttribute("data-service-row") === "2";
        var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        var setWidth = items.reduce(function (sum, li) {
            return sum + li.getBoundingClientRect().width + gap;
        }, 0);
        var copies = Math.max(1, Math.ceil(row.clientWidth / setWidth));
        for (var c = 0; c < copies; c++) {
            items.forEach(function (li) {
                var clone = li.cloneNode(true);
                clone.setAttribute("aria-hidden", "true");
                clone.querySelectorAll("a").forEach(function (a) {
                    a.setAttribute("tabindex", "-1");
                });
                track.appendChild(clone);
            });
        }
        var pxPerSecond = (window.innerWidth < 768 ? 28 : 45) * (second ? 0.8 : 1);
        var seconds = setWidth / pxPerSecond;
        row.style.setProperty("--row-shift", setWidth + "px");
        row.style.setProperty("--row-duration", seconds.toFixed(1) + "s");
        row.style.setProperty("--row-delay", (second ? -seconds * 0.37 : 0).toFixed(1) + "s");
        row.classList.add("is-moving");
    });

    /* ---- Advertisement strip: one banner leaves, the others rest ----------- */
    document.querySelectorAll("[data-ad-strip]").forEach(function (strip) {
        var track = strip.querySelector(".ad-strip__track");
        if (!track || reduceMotion) {
            return;
        }
        var items = Array.prototype.slice.call(track.children);

        function start() {
            if (items.length < 2) {
                return; // a single banner just stays still
            }
            var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
            var setWidth = items.reduce(function (sum, li) {
                return sum + li.getBoundingClientRect().width + gap;
            }, 0);
            if (setWidth <= 0) {
                return;
            }
            // repeat the banners until two full sets fill the strip, so the loop has no gap
            var copies = Math.max(1, Math.ceil(strip.clientWidth / setWidth));
            for (var c = 0; c < copies + 1; c++) {
                items.forEach(function (li) {
                    var clone = li.cloneNode(true);
                    clone.setAttribute("aria-hidden", "true");
                    clone.querySelectorAll("a").forEach(function (a) {
                        a.setAttribute("tabindex", "-1");
                    });
                    track.appendChild(clone);
                });
            }
            strip.classList.add("is-marquee");
            if (!track.animate) {
                return;
            }

            // Step by step (owner 2026-10-20): the first banner slides out to the left in ~0.9 s,
            // the rest stay still for 5.5 s (data-hold; was 2.5 s, +3 s asked by the owner), then the next one leaves. A visitor can
            // read every ad; hovering or focusing the strip holds it still.
            var hold = parseInt(strip.getAttribute("data-hold"), 10) || 5500;
            var held = false;
            strip.addEventListener("mouseenter", function () { held = true; });
            strip.addEventListener("mouseleave", function () { held = false; });
            strip.addEventListener("focusin", function () { held = true; });
            strip.addEventListener("focusout", function () { held = false; });

            function step() {
                if (held || document.hidden) {
                    setTimeout(step, 500);
                    return;
                }
                var first = track.firstElementChild;
                var distance = first.getBoundingClientRect().width + (parseFloat(getComputedStyle(track).columnGap) || 0);
                var move = track.animate(
                    [{ transform: "translateX(0)" }, { transform: "translateX(-" + distance + "px)" }],
                    { duration: 900, easing: "ease-in-out" }
                );
                move.onfinish = function () {
                    track.appendChild(first); // the banner that left joins the end of the line
                    setTimeout(step, hold);
                };
            }
            setTimeout(step, hold);
        }

        // wait for the images so the widths are real
        var imgs = Array.prototype.slice.call(track.querySelectorAll("img"));
        var pending = imgs.filter(function (img) {
            return !img.complete;
        }).length;
        if (pending === 0) {
            start();
        } else {
            imgs.forEach(function (img) {
                if (!img.complete) {
                    img.addEventListener("load", done);
                    img.addEventListener("error", done);
                }
            });
        }
        function done() {
            pending -= 1;
            if (pending === 0) {
                start();
            }
        }
    });
})();

/* Post-a-job form, part 1: "কাজের ধরন" becomes a searchable drop-down.
   The services list (the <datalist> of the page) is shown while typing; the list gets shorter with every letter.
   When nothing matches, a button lets the visitor use their own words (the admin corrects the spelling later).
   Without JavaScript the field stays a plain text box with suggestions. */
(function () {
    var box = document.querySelector("[data-combo]");
    if (!box) {
        return;
    }
    var input = box.querySelector("input");
    var dl = document.getElementById(input.getAttribute("list"));
    if (!dl) {
        return;
    }
    var names = Array.prototype.map.call(dl.options, function (o) {
        return o.value;
    });
    input.removeAttribute("list");
    dl.parentNode.removeChild(dl);

    var list = document.createElement("ul");
    list.className = "combo__list";
    list.id = "pj-title-list";
    list.setAttribute("role", "listbox");
    list.hidden = true;
    var hint = document.createElement("p");
    hint.className = "pf__hint";
    hint.hidden = true;
    hint.textContent = box.getAttribute("data-hint") || "";
    var toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "combo__toggle";
    toggle.tabIndex = -1;
    toggle.setAttribute("aria-label", "সেবার তালিকা খুলুন বা বন্ধ করুন");
    box.appendChild(toggle);
    box.appendChild(list);
    box.appendChild(hint);

    input.setAttribute("role", "combobox");
    input.setAttribute("aria-autocomplete", "list");
    input.setAttribute("aria-controls", list.id);
    input.setAttribute("aria-expanded", "false");

    var active = -1;
    var shown = [];

    function matches() {
        var q = input.value.trim().toLowerCase();
        if (q === "") {
            return names.slice();
        }
        var starts = [];
        var inside = [];
        names.forEach(function (n) {
            var i = n.toLowerCase().indexOf(q);
            if (i === 0) {
                starts.push(n);
            } else if (i > 0) {
                inside.push(n);
            }
        });
        return starts.concat(inside);
    }

    function open() {
        render();
        list.hidden = false;
        input.setAttribute("aria-expanded", "true");
    }
    function close() {
        list.hidden = true;
        input.setAttribute("aria-expanded", "false");
        active = -1;
        showHint();
    }
    function choose(value) {
        input.value = value;
        close();
    }
    function showHint() {
        var v = input.value.trim();
        hint.hidden = v === "" || names.indexOf(v) !== -1;
    }

    function render() {
        shown = matches();
        list.textContent = "";
        active = -1;
        if (shown.length === 0) {
            var none = document.createElement("li");
            none.className = "combo__none";
            none.setAttribute("role", "presentation");
            none.textContent = box.getAttribute("data-none") || "";
            var use = document.createElement("button");
            use.type = "button";
            use.className = "combo__use";
            use.textContent = box.getAttribute("data-use") || "";
            use.addEventListener("click", function () {
                choose(input.value.trim());
            });
            none.appendChild(use);
            list.appendChild(none);
            return;
        }
        shown.forEach(function (n, i) {
            var li = document.createElement("li");
            li.id = "pj-title-opt-" + i;
            li.setAttribute("role", "option");
            li.textContent = n;
            li.addEventListener("click", function () {
                choose(n);
            });
            list.appendChild(li);
        });
    }

    function setActive(i) {
        var items = list.querySelectorAll("[role=option]");
        if (!items.length) {
            return;
        }
        if (active >= 0 && items[active]) {
            items[active].classList.remove("is-active");
        }
        active = (i + items.length) % items.length;
        items[active].classList.add("is-active");
        input.setAttribute("aria-activedescendant", items[active].id);
        items[active].scrollIntoView({ block: "nearest" });
    }

    input.addEventListener("input", open);
    input.addEventListener("focus", open);
    input.addEventListener("click", function () {
        if (list.hidden) {
            open();
        }
    });
    input.addEventListener("keydown", function (e) {
        if (e.key === "ArrowDown") {
            e.preventDefault();
            if (list.hidden) {
                open();
            }
            setActive(active + 1);
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            setActive(active - 1);
        } else if (e.key === "Enter" && !list.hidden && active >= 0) {
            e.preventDefault();
            choose(shown[active]);
        } else if (e.key === "Escape" || e.key === "Tab") {
            close();
        }
    });
    toggle.addEventListener("click", function () {
        if (list.hidden) {
            input.focus();
            open();
        } else {
            close();
        }
    });
    list.addEventListener("mousedown", function (e) {
        e.preventDefault(); // keep the focus in the input
    });
    document.addEventListener("mousedown", function (e) {
        if (!box.contains(e.target)) {
            close();
        }
    });
    showHint();
})();

/* Post-a-job form, part 2: উপজেলা / থানা, জেলা and বিভাগ work together, in any order.
   Every name is listed once. Pick an upazila: the district list shows only the districts that have
   an upazila with that name (several, if the name repeats, e.g. "সদর"). Pick a district: its division
   fills in (or the divisions that have it). Auto-fill happens when only one answer is possible.
   Three hidden fields carry the real ids to the server, which checks they belong together.
   Without JavaScript the three lists stay complete and keep their own names. */
(function () {
    var upz = document.getElementById("pj-upazila");
    var dis = document.getElementById("pj-district");
    var div = document.getElementById("pj-division");
    if (!upz || !dis || !div) {
        return;
    }

    var divs = Array.prototype.slice.call(div.options, 1).map(function (o) {
        return { id: o.value, name: o.textContent };
    });
    var dists = {};
    Array.prototype.slice.call(dis.options, 1).forEach(function (o) {
        dists[o.value] = { id: o.value, name: o.textContent, div: o.getAttribute("data-parent") };
    });
    var triples = Array.prototype.slice.call(upz.options, 1).map(function (o) {
        var d = dists[o.getAttribute("data-parent")];
        return { id: o.value, uName: o.getAttribute("data-name") || o.textContent, s: d.id, sName: d.name, d: d.div };
    });
    var placeholder = {
        u: upz.options[0].textContent,
        s: dis.options[0].textContent,
        d: div.options[0].textContent
    };

    // start values (after a failed submit the server selects the earlier choice)
    var U = "";
    var S = "";
    var D = div.value;
    if (upz.value) {
        triples.forEach(function (t) {
            if (t.id === upz.value) {
                U = t.uName;
                S = t.sName;
                D = t.d;
            }
        });
    } else if (dis.value && dists[dis.value]) {
        S = dists[dis.value].name;
        D = dists[dis.value].div;
    }

    // the selects no longer send values themselves; hidden fields do
    var hidden = {};
    [["upazila_id", upz], ["district_id", dis], ["division_id", div]].forEach(function (pair) {
        var h = document.createElement("input");
        h.type = "hidden";
        h.name = pair[0];
        pair[1].removeAttribute("name");
        pair[1].parentNode.appendChild(h);
        hidden[pair[0]] = h;
    });

    function uniq(list) {
        var seen = {};
        return list.filter(function (x) {
            if (seen[x]) {
                return false;
            }
            seen[x] = true;
            return true;
        });
    }
    function fits(t, u, s, d) {
        return (!u || t.uName === u) && (!s || t.sName === s) && (!d || t.d === d);
    }
    function avail() {
        return {
            d: uniq(triples.filter(function (t) { return fits(t, U, S, ""); }).map(function (t) { return t.d; })),
            s: uniq(triples.filter(function (t) { return fits(t, U, "", D); }).map(function (t) { return t.sName; })),
            u: uniq(triples.filter(function (t) { return fits(t, "", S, D); }).map(function (t) { return t.uName; }))
        };
    }

    function settle() {
        for (var round = 0; round < 4; round++) {
            var a = avail();
            if (D && a.d.indexOf(D) === -1) { D = ""; }
            if (S && a.s.indexOf(S) === -1) { S = ""; }
            if (U && a.u.indexOf(U) === -1) { U = ""; }
            a = avail();
            if (!D && (U || S) && a.d.length === 1) { D = a.d[0]; }
            if (!S && (U || D) && a.s.length === 1) { S = a.s[0]; }
            if (!U && (S || D) && a.u.length === 1) { U = a.u[0]; }
        }
    }

    function fill(select, first, values, labels, current) {
        while (select.options.length > 1) {
            select.remove(1);
        }
        select.options[0].textContent = first;
        values.forEach(function (v, i) {
            var o = document.createElement("option");
            o.value = v;
            o.textContent = labels ? labels[i] : v;
            select.appendChild(o);
        });
        select.value = current;
    }

    function render() {
        var a = avail();
        var divName = {};
        divs.forEach(function (x) { divName[x.id] = x.name; });
        fill(div, placeholder.d, divs.filter(function (x) { return a.d.indexOf(x.id) !== -1; }).map(function (x) { return x.id; }),
            divs.filter(function (x) { return a.d.indexOf(x.id) !== -1; }).map(function (x) { return x.name; }), D);
        fill(dis, placeholder.s, a.s, null, S);
        fill(upz, placeholder.u, a.u, null, U);

        var ids = { u: "", s: "", d: "" };
        if (U && S) {
            var m = triples.filter(function (t) { return fits(t, U, S, D); });
            if (m.length === 1) {
                ids = { u: m[0].id, s: m[0].s, d: m[0].d };
            }
        }
        hidden.upazila_id.value = ids.u;
        hidden.district_id.value = ids.s;
        hidden.division_id.value = ids.d;
    }

    div.addEventListener("change", function () { D = div.value; settle(); render(); });
    dis.addEventListener("change", function () { S = dis.value; settle(); render(); });
    upz.addEventListener("change", function () { U = upz.value; settle(); render(); });
    settle();
    render();
})();

/* Typing Bangla with English letters ("Banglish", Avro phonetic): ami = আমি, tomake = তোমাকে.
   Fields opt in with data-bn. The converter is vendor/avro/avro-lib.js (MPL 1.1), loaded only on pages
   that have such fields. The small switch (data-bn-switch) turns it on/off; the choice is remembered. */
(function () {
    var fields = document.querySelectorAll("[data-bn]");
    if (!fields.length) {
        return;
    }

    var enabled = true;
    try {
        enabled = window.localStorage.getItem("bnPhonetic") !== "off";
    } catch (err) {
        enabled = true;
    }

    // do not let phones "correct" the English letters before we turn them into Bangla
    Array.prototype.forEach.call(fields, function (el) {
        el.setAttribute("autocapitalize", "none");
        el.setAttribute("autocorrect", "off");
        el.setAttribute("spellcheck", "false");
    });

    var loading = false;
    function load() {
        if (window.OmicronLab || loading) {
            return;
        }
        loading = true;
        var s = document.createElement("script");
        s.src = "/assets/vendor/avro/avro-lib.js";
        s.async = true;
        document.head.appendChild(s);
    }

    var buttons = document.querySelectorAll("[data-bn-switch] button");
    function paint() {
        Array.prototype.forEach.call(buttons, function (b) {
            b.setAttribute("aria-pressed", enabled ? "true" : "false");
            b.textContent = b.getAttribute(enabled ? "data-on" : "data-off");
            b.classList.toggle("is-off", !enabled);
        });
    }
    Array.prototype.forEach.call(buttons, function (b) {
        b.addEventListener("click", function () {
            enabled = !enabled;
            try {
                window.localStorage.setItem("bnPhonetic", enabled ? "on" : "off");
            } catch (err) {
                /* private mode: the choice just lasts for this page */
            }
            if (enabled) {
                load();
            }
            paint();
        });
    });
    paint();
    if (enabled) {
        load();
    }

    // letters, digits and the Avro signs ( . , : ^ $ ` ) belong to the word being typed
    var typed = /[A-Za-z0-9.,:^$`]+$/;
    var onlyTyped = /^[A-Za-z0-9.,:^$`]+$/;
    var states = typeof WeakMap === "function" ? new WeakMap() : null;
    var plain = {};

    function getState(el) {
        return states ? states.get(el) : plain[el.id];
    }
    function setState(el, st) {
        if (states) {
            if (st) { states.set(el, st); } else { states.delete(el); }
        } else {
            plain[el.id] = st;
        }
    }

    // capture phase: runs before the field's own listeners (e.g. the "কাজের ধরন" list)
    document.addEventListener("input", function (e) {
        var el = e.target;
        if (!enabled || !window.OmicronLab || !el.hasAttribute || !el.hasAttribute("data-bn")) {
            return;
        }
        var type = e.inputType || "insertText";
        if (type.indexOf("insert") !== 0 || type === "insertFromPaste" || type === "insertFromDrop" || type === "insertLineBreak") {
            setState(el, null);
            return;
        }
        var caret = el.selectionStart;
        if (typeof caret !== "number") {
            return;
        }
        var value = el.value;
        var left = value.slice(0, caret);
        var st = getState(el);
        var raw;
        var start;

        // several characters arrive at once (voice typing, a keyboard's word suggestion): convert every English word in them
        var chunk = typeof e.data === "string" ? e.data : "";
        if (chunk.length > 1 && left.slice(-chunk.length) === chunk) {
            var chunkStart = caret - chunk.length;
            var lastRaw = "";
            var lastOut = "";
            var converted = chunk.replace(/[A-Za-z0-9.,:^$`]+/g, function (word) {
                lastRaw = word;
                lastOut = window.OmicronLab.Avro.Phonetic.parse(word);
                return lastOut;
            });
            el.value = value.slice(0, chunkStart) + converted + value.slice(caret);
            var end = chunkStart + converted.length;
            el.setSelectionRange(end, end);
            var endsWithWord = lastOut !== "" && converted.slice(-lastOut.length) === lastOut && typed.test(chunk);
            setState(el, endsWithWord ? { start: end - lastOut.length, out: lastOut, raw: lastRaw } : null);
            return;
        }

        if (st && left.length >= st.start + st.out.length && left.substr(st.start, st.out.length) === st.out) {
            var added = left.slice(st.start + st.out.length);
            if (added === "" || !onlyTyped.test(added)) {
                setState(el, null);
                return;
            }
            raw = st.raw + added;
            start = st.start;
        } else {
            var m = typed.exec(left);
            if (!m) {
                setState(el, null);
                return;
            }
            raw = m[0];
            start = caret - raw.length;
        }

        var out = window.OmicronLab.Avro.Phonetic.parse(raw);
        el.value = value.slice(0, start) + out + value.slice(caret);
        var pos = start + out.length;
        el.setSelectionRange(pos, pos);
        setState(el, { start: start, out: out, raw: raw });
    }, true);

    // moving the cursor or leaving the field ends the word
    ["blur", "mouseup", "keyup"].forEach(function (name) {
        document.addEventListener(name, function (e) {
            var el = e.target;
            if (!el.hasAttribute || !el.hasAttribute("data-bn")) {
                return;
            }
            if (name === "keyup" && ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown", "Home", "End"].indexOf(e.key) === -1) {
                return;
            }
            setState(el, null);
        }, true);
    });
})();
