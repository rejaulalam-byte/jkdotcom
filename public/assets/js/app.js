/* ==========================================================================
   jorurikaj.com — ছোট ছোট উন্নতি (JavaScript ছাড়াও সাইট চলে; এটা শুধু বাড়তি আরাম দেয়)

   সূচিপত্র (এই নামে খুঁজুন):
     মেনু (মোবাইল মেনু, সাব-মেনু) · বার্তা-বক্স · তারিখ ঘর · Hero অক্ষরের অ্যানিমেশন
     ওপরে-যাওয়ার বাটন · FAQ · আজকের কাজের তালিকা · Marquee · Services সারি
     Advertisement strip · কাজের ধরন ড্রপডাউন · এলাকার তিন ড্রপডাউন
     Banglish টাইপিং · লুকানো ফোন/ইমেইল ও আগ্রহের পপ-আপ · পাসওয়ার্ড দেখা/লুকানো · রেজিস্ট্রেশনের ছবি · ফর্মের ভুল-বার্তা

   প্রতিটি অংশ নিজের মতো স্বাধীন: ওই অংশের উপাদান পেইজে না থাকলে সে চুপচাপ বাদ পড়ে।
   ========================================================================== */
(function () {
    "use strict";

    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---- মোবাইল মেনু ও সাব-মেনু -------------------------------------------- */
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
    /* সাব-মেনু আছে এমন মেনুর নামে ক্লিক করলে কোনো পেইজ খোলে না; শুধু সাব-মেনু দেখায়।
       চওড়া পর্দায় মাউস নিলেই সাব-মেনু খোলে, তাই সেখানে ক্লিকে কিছু হয় না;
       ফোন/ট্যাবলেটে ট্যাপ করলে খোলে-বন্ধ হয়। (JavaScript বন্ধ থাকলে নামটা নিজের পেইজের লিংকই থাকে।) */
    var hoverMenu = window.matchMedia("(hover: hover) and (min-width: 1200px)");
    document.querySelectorAll(".has-sub > .nav__link").forEach(function (link) {
        link.addEventListener("click", function (e) {
            e.preventDefault();
            if (hoverMenu.matches) {
                return;
            }
            var item = link.closest(".has-sub");
            var open = item.classList.toggle("is-open");
            var b = item.querySelector(".sub-toggle");
            if (b) {
                b.setAttribute("aria-expanded", String(open));
            }
        });
    });
    document.addEventListener("click", function (e) {
        document.querySelectorAll(".has-sub.is-open").forEach(function (item) {
            if (!item.contains(e.target) && !hoverMenu.matches) {
                item.classList.remove("is-open");
            }
        });
    });
    /* ---- লগইন-করা সদস্যের ছবির মেনু (<details class="nav__user">): বাইরে ক্লিকে বা Esc-এ বন্ধ ---- */
    var userMenu = document.querySelector(".nav__user");
    if (userMenu) {
        document.addEventListener("click", function (e) {
            if (!userMenu.contains(e.target)) {
                userMenu.removeAttribute("open");
            }
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                userMenu.removeAttribute("open");
            }
        });
    }
    /* ---- সফল-জমার বার্তা-বক্স (কাজ জানান, দুই রেজিস্ট্রেশন): ২০ সেকেন্ড পরে নিজে সরে যায়, ✕ বাটনে সঙ্গে সঙ্গে;
       সরে যাওয়ার পর হোম পেইজে নিয়ে যায়, কারণ ফর্মের পেইজে আর কিছু করার নেই ---- */
    var toast = document.getElementById("post-toast");
    if (toast) {
        var closeToast = function () {
            toast.classList.add("is-closed");
            window.location.assign("/");
        };
        var toastClose = toast.querySelector(".post-toast__close");
        if (toastClose) {
            toastClose.addEventListener("click", closeToast);
            toastClose.focus();
        }
        window.setTimeout(closeToast, 20000);
    }

    /* ---- তারিখের ঘর: তারিখ বাছার আগে ব্রাউজারের নিজের ফরম্যাট (mm/dd/yyyy) ফ্যাকাশে দেখায় ---- */
    document.querySelectorAll(".pf--date input[type=date]").forEach(function (d) {
        var mark = function () {
            d.classList.toggle("is-empty", d.value === "");
        };
        mark();
        d.addEventListener("input", mark);
        d.addEventListener("change", mark);
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

    /* ---- Hero: প্রতিটি অক্ষর নিজের দিক থেকে উড়ে আসে (স্টাইল: CSS-এর "Hero entrance") ---- */
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
                // গ্রাফিম-ক্লাস্টার ধরে ভাঙলে বাংলা যুক্তাক্ষর ও কার-চিহ্ন একসঙ্গে থাকে;
                // Intl.Segmenter নেই এমন পুরনো ব্রাউজারে গোটা শব্দ একটুকরো হয়ে ওড়ে
                var pieces = segmenter
                    ? Array.from(segmenter.segment(part), function (s) { return s.segment; })
                    : [part];
                // শব্দের শুরুতে সাইটের নাম ("Jorurikaj.com-এ") মোটা ও রঙিন থাকে
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

    /* ---- ওপরে-যাওয়ার বাটন ("back to top") --------------------------------------
       স্ক্রল-থাম্বের মতো কাজ করে: পেইজের প্রথম অর্ধেকে লুকানো; মাঝামাঝি পেরোলে পর্দার মাঝখানে
       ফুটে ওঠে এবং স্ক্রলের সঙ্গে নিচে নেমে পেইজ শেষে পর্দার তলায় পৌঁছায়; ওপরে স্ক্রলে উল্টোটা হয়।
       ক্লিক করলে ধীরে ওপরে গড়িয়ে যায় (প্রতি মিলিসেকেন্ডে প্রায় ৩px, মোট ০.৯–৩.৫ সেকেন্ড)
       এবং ব্যবহারকারী নিজে স্ক্রল করলে থেমে যায়। */
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
    /* ---- FAQ: একটা প্রশ্ন খুললে বাকিগুলো বন্ধ হয় ---------------------------------
       (যে ব্রাউজার <details name="..."> বোঝে, সে নিজেই এটা করে; এটা অন্যদের জন্য) */
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

    /* ---- "আজকের কাজের তালিকা" (hero-র ডানের বাক্স) -------------------------------
       একসঙ্গে N সারি দেখায়। প্রতিটি পাতা নিচ থেকে ধীরে ওঠে (data-rise, ডিফল্ট ৪s, যাতে
       ওঠার সময়ই পড়া যায়), কয়েক সেকেন্ড থামে (data-hold, ডিফল্ট ৫.৫s), তারপর দ্রুত ওপরে সরে
       যায়; আগের পাতার শেষ সারি বেরিয়ে যাওয়ার মুহূর্তেই পরের পাতা উঠতে শুরু করে।
       JavaScript না থাকলে বা "reduce motion" চালু থাকলে সাধারণ তালিকা (প্রথম N সারি) দেখায়। */
    var ticker = document.querySelector(".ticker[data-page-size]");
    if (ticker && !reduceMotion && ticker.animate) {
        var size = parseInt(ticker.getAttribute("data-page-size"), 10) || 8;
        var hold = parseInt(ticker.getAttribute("data-hold"), 10) || 5500;
        var rise = parseInt(ticker.getAttribute("data-rise"), 10) || 4000;
        var rows = Array.prototype.slice.call(ticker.children);

        if (rows.length > size) {
            // `size` সারির পাতা (শেষ পাতা ছোট হতে পারে)
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

            // পরের পাতা ওঠা শুরু করে ঠিক তখন, যখন আগের পাতার শেষ সারিটা ওপর দিয়ে বেরিয়ে যায়।
            // ছোট শেষ পাতা (যেমন ৮-এর মধ্যে ৪ সারি) শুধু নিজের সারির দৈর্ঘ্যটুকু পথ চলে,
            // তাই আগে বেরিয়ে যায় — খালি জায়গার জন্য কেউ অপেক্ষা করে না।
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

    /* ---- টিল লেখার পট্টির marquee: লেখা ছোট-বড় যা-ই হোক, গতি সমান ------------------- */
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

    /* ---- Services (হোম): দুই সারি ডান থেকে বাঁয়ে, প্রতিটি নিজের গতিতে ---------------
       সারির কার্ড পুনরাবৃত্তি করা হয়, যাতে ঘুরে আসার সময় ফাঁক না থাকে। ২য় সারির গতি ও শুরুর
       বিন্দু আলাদা, তাই দুই সারি কখনও তালে তাল মিলিয়ে চলে না।
       মাউস/কীবোর্ড-ফোকাসে থামে (এটা CSS করে)। */
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

    /* ---- Advertisement strip: একটা ব্যানার বেরিয়ে যায়, বাকিরা স্থির থাকে ------------------ */
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
            // ব্যানারগুলো এতবার নকল করা হয় যাতে দুই সেট স্ট্রিপ ভরে ফেলে; তাহলে ঘুরে আসার সময় ফাঁক থাকে না
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

            // ধাপে ধাপে: প্রথম ব্যানার প্রায় ০.৯ সেকেন্ডে বাঁ দিকে সরে যায়, বাকিরা ৫.৫ সেকেন্ড
            // (data-hold) স্থির থাকে, তারপর পরেরটা বেরোয়। ফলে প্রতিটি বিজ্ঞাপন পড়া যায়;
            // স্ট্রিপের ওপর মাউস বা ফোকাস থাকলে সব স্থির।
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

        // ছবি লোড হওয়ার জন্য অপেক্ষা: তখনই প্রস্থগুলো আসল মাপের হয়
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

/* ---- কাজের ধরন ড্রপডাউন ("কাজ সম্পর্কে জানান" ফর্ম, অংশ ১) -----------------------
   "কাজের ধরন" ঘরটা খোঁজা-যায় এমন তালিকা হয়ে যায়: টাইপ করার সময় পেইজের <datalist>-এর সেবাগুলো দেখায়,
   প্রতিটি অক্ষরে তালিকা ছোট হয়। কিছু না মিললে একটা বাটন ব্যবহারকারীকে নিজের শব্দ ব্যবহার করতে দেয়
   (বানান অ্যাডমিন পরে ঠিক করবেন)। JavaScript ছাড়া ঘরটা পরামর্শসহ সাধারণ লেখার ঘরই থাকে। */
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

/* ---- এলাকার তিন ড্রপডাউন ("কাজ সম্পর্কে জানান" ফর্ম, অংশ ২) ------------------------
   উপজেলা/থানা, জেলা ও বিভাগ একসঙ্গে কাজ করে, যেকোনো ক্রমে বাছা যায়। প্রতিটি নাম একবারই থাকে।
   উপজেলা বাছলে জেলার তালিকায় শুধু সেই নামের উপজেলা আছে এমন জেলাগুলো থাকে (নাম এক হলে একাধিক,
   যেমন "সদর")। জেলা বাছলে তার বিভাগ নিজে বসে (বা সেই জেলা আছে এমন বিভাগগুলো থাকে)।
   উত্তর একটাই হলে নিজে ভরে যায়। আসল আইডি সার্ভারে যায় তিনটি লুকানো ঘরে; সার্ভার যাচাই করে
   তারা পরস্পরের সঙ্গে মেলে কি না। JavaScript ছাড়া তিনটি তালিকা পূর্ণ থাকে, নিজের নামেই যায়। */
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

    // শুরুর মান (জমা ব্যর্থ হলে সার্ভার আগের বাছাইটা ফিরিয়ে দেয়)
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

    // ড্রপডাউনগুলো নিজে আর মান পাঠায় না; লুকানো ঘরগুলো পাঠায়
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

    /* reset: "সকল ... দেখান" অপশন; কিছু বাছা থাকলেই (তালিকা ছোট হয়ে থাকলে) প্লেসহোল্ডারের ঠিক নিচে বসে।
       এটা বাছলে তিনটি ঘরই খালি হয়ে সব নাম ফিরে আসে (RESET মান)। */
    var RESET = "__all";
    function fill(select, first, values, labels, current, reset) {
        while (select.options.length > 1) {
            select.remove(1);
        }
        select.options[0].textContent = first;
        if (reset && (U || S || D)) {
            var r = document.createElement("option");
            r.value = RESET;
            r.textContent = reset;
            select.appendChild(r);
        }
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
            divs.filter(function (x) { return a.d.indexOf(x.id) !== -1; }).map(function (x) { return x.name; }), D, div.getAttribute("data-all"));
        fill(dis, placeholder.s, a.s, null, S, dis.getAttribute("data-all"));
        fill(upz, placeholder.u, a.u, null, U, upz.getAttribute("data-all"));

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

        // খোঁজার ঘরগুলোকে (নিচের অংশ) জানায় যে তালিকা ও বাছাই বদলেছে
        [div, dis, upz].forEach(function (s) {
            s.dispatchEvent(new CustomEvent("geo:render"));
        });
    }

    function pick(select, set) {
        select.addEventListener("change", function () {
            if (select.value === RESET) {
                U = S = D = "";
            } else {
                set(select.value);
            }
            settle();
            render();
        });
    }
    pick(div, function (v) { D = v; });
    pick(dis, function (v) { S = v; });
    pick(upz, function (v) { U = v; });
    settle();
    render();
})();

/* ---- খোঁজা-যায় এমন ড্রপডাউন (select[data-search]): উপজেলা/থানা, জেলা, বিভাগ -------------------
   আসল <select> থাকে (মান ও যুক্তি আগের অংশের হাতে) কিন্তু লুকানো; তার জায়গায় একটা লেখার ঘর। ২-৩ অক্ষর
   লিখলেই যে নামের যেকোনো জায়গায় সেই অক্ষর আছে সেগুলো আসে (শুরুতে মিললে আগে)। বাছলে select-এর মান বদলে
   "change" ঘটে, ফলে আগের অংশ বাকি দুই ঘর মিলিয়ে নেয়। তালিকা প্রতিবার select-এর তখনকার অপশন থেকে নেওয়া হয়। */
(function () {
    var RESET = "__all";

    document.querySelectorAll("select[data-search]").forEach(function (select) {
        var box = select.parentNode;
        var placeholder = select.options[0] ? select.options[0].textContent : "";

        var input = document.createElement("input");
        input.type = "text";
        input.id = select.id + "-search";
        input.autocomplete = "off";
        input.placeholder = placeholder;
        input.setAttribute("data-bn", "");
        input.setAttribute("data-ss", select.id);
        input.setAttribute("role", "combobox");
        input.setAttribute("aria-autocomplete", "list");
        input.setAttribute("aria-expanded", "false");
        if (select.required) {
            input.required = true;
        }
        var label = document.querySelector('label[for="' + select.id + '"]');
        if (label) {
            label.setAttribute("for", input.id);
        }

        var list = document.createElement("ul");
        list.className = "combo__list";
        list.id = select.id + "-list";
        list.setAttribute("role", "listbox");
        list.hidden = true;
        input.setAttribute("aria-controls", list.id);
        var toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "combo__toggle";
        toggle.tabIndex = -1;
        toggle.setAttribute("aria-label", "তালিকা খুলুন বা বন্ধ করুন");

        select.hidden = true;
        select.tabIndex = -1;
        box.insertBefore(input, select);
        box.appendChild(toggle);
        box.appendChild(list);

        var typed = false; // ব্যবহারকারী নিজে লিখেছেন কি না; না লিখলে পুরো তালিকা
        var active = -1;
        var shown = [];

        function selectedText() {
            var o = select.options[select.selectedIndex];
            return o && o.value !== "" && o.value !== RESET ? o.textContent : "";
        }
        function sync() {
            input.value = selectedText();
            typed = false;
        }
        function items() {
            var q = typed ? input.value.trim().toLowerCase() : "";
            var starts = [];
            var inside = [];
            Array.prototype.slice.call(select.options, 1).forEach(function (o) {
                if (o.value === RESET) {
                    if (q === "") {
                        starts.unshift(o);
                    }
                } else if (q === "") {
                    starts.push(o);
                } else {
                    var i = o.textContent.toLowerCase().indexOf(q);
                    if (i === 0) {
                        starts.push(o);
                    } else if (i > 0) {
                        inside.push(o);
                    }
                }
            });
            return starts.concat(inside);
        }
        function render() {
            shown = items();
            active = -1;
            list.textContent = "";
            if (!shown.length) {
                var none = document.createElement("li");
                none.className = "combo__none";
                none.setAttribute("role", "presentation");
                none.textContent = select.getAttribute("data-none") || "";
                list.appendChild(none);
                return;
            }
            shown.forEach(function (o, i) {
                var li = document.createElement("li");
                li.id = list.id + "-" + i;
                li.setAttribute("role", "option");
                li.textContent = o.textContent;
                li.addEventListener("click", function () {
                    choose(o);
                });
                list.appendChild(li);
            });
        }
        function open() {
            render();
            list.hidden = false;
            input.setAttribute("aria-expanded", "true");
        }
        function close() {
            list.hidden = true;
            input.setAttribute("aria-expanded", "false");
            sync();
        }
        function choose(option) {
            select.value = option.value;
            list.hidden = true;
            input.setAttribute("aria-expanded", "false");
            select.dispatchEvent(new Event("change"));
            sync();
            input.dispatchEvent(new Event("change"));
        }
        function setActive(i) {
            var nodes = list.querySelectorAll("[role=option]");
            if (!nodes.length) {
                return;
            }
            if (nodes[active]) {
                nodes[active].classList.remove("is-active");
            }
            active = (i + nodes.length) % nodes.length;
            nodes[active].classList.add("is-active");
            input.setAttribute("aria-activedescendant", nodes[active].id);
            nodes[active].scrollIntoView({ block: "nearest" });
        }

        input.addEventListener("focus", function () {
            input.select();
            open();
        });
        input.addEventListener("click", function () {
            if (list.hidden) {
                open();
            }
        });
        input.addEventListener("input", function () {
            typed = true;
            open();
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
            } else if (e.key === "Enter" && !list.hidden) {
                e.preventDefault();
                if (active >= 0) {
                    choose(shown[active]);
                } else if (shown.length === 1) {
                    choose(shown[0]);
                }
            } else if (e.key === "Escape" || e.key === "Tab") {
                if (e.key === "Tab" && typed && shown.length === 1 && !list.hidden) {
                    choose(shown[0]);
                } else {
                    close();
                }
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
            e.preventDefault(); // ফোকাস লেখার ঘরেই থাকে
        });
        document.addEventListener("mousedown", function (e) {
            if (!box.contains(e.target) && !list.hidden) {
                close();
            }
        });
        select.addEventListener("geo:render", function () {
            if (list.hidden) {
                sync();
            }
        });
        sync();
    });
})();

/* ---- Banglish টাইপিং: ইংরেজি অক্ষরে লিখলে বাংলা (Avro ফোনেটিক): ami = আমি, tomake = তোমাকে ------
   যে ঘরে data-bn আছে সেটা এই সুবিধা পায়। রূপান্তরকারী vendor/avro/avro-lib.js (MPL 1.1, অপরিবর্তিত),
   শুধু এমন ঘর আছে এমন পেইজেই লোড হয়। ছোট সুইচ (data-bn-switch) চালু/বন্ধ করে; পছন্দ মনে রাখা হয়। */
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

    // বাংলায় বদলানোর আগে ফোন যেন ইংরেজি অক্ষর "শুধরে" না দেয়
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
                /* ব্রাউজারের প্রাইভেট মোডে পছন্দ সংরক্ষণ হয় না; শুধু এই পেইজের জন্য থাকে */
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

    // অক্ষর, অঙ্ক ও Avro-র চিহ্ন ( . , : ^ $ ` ) যে শব্দ টাইপ হচ্ছে তারই অংশ
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

    // capture ধাপ: ঘরের নিজের শ্রোতাদের (যেমন "কাজের ধরন" তালিকা) আগে চলে
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

        // একসঙ্গে কয়েকটা অক্ষর এলে (ভয়েস টাইপিং, কীবোর্ডের শব্দ-পরামর্শ) তার প্রতিটি ইংরেজি শব্দ বদলানো হয়
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

    // কার্সর সরালে বা ঘর ছাড়লে শব্দ শেষ ধরা হয়
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

/* ---- "আজকের প্রকাশিত" তালিকা: লুকানো ফোন নাম্বার / ইমেইল --------------------------
   একটাতে ক্লিক করলে সার্ভার সেটাকে একটা "কল" হিসেবে গোনে। এই দর্শক আগে পরিচয় না দিয়ে থাকলে পপ-আপ
   জিজ্ঞেস করে "আপনি কি আগ্রহী?"; "হ্যাঁ" বললে নাম + মোবাইল দিতে হয়, তারপরই (শুধু যেটাতে ক্লিক
   করেছিলেন সেই) ফোন নাম্বার বা ইমেইল কল/মেইল লিংক হয়ে দেখায়। পেইজের সোর্সে আসল তথ্য থাকে না। */
(function () {
    var list = document.querySelector(".job-list");
    var dialog = document.getElementById("lead-dialog");
    if (!list) {
        return;
    }
    var token = list.getAttribute("data-token");
    var current = null; // { btn, id, kind } of the click that opened the pop-up

    function post(fields) {
        var body = new FormData();
        Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
        body.append("_token", token);
        return fetch("/today/contact", { method: "POST", body: body, credentials: "same-origin" })
            .then(function (r) {
                return r.json().then(function (j) { return { ok: r.ok, status: r.status, data: j }; });
            });
    }

    function hintOf(btn) {
        return btn.closest(".job__row") ? btn.closest(".job__row").querySelector(".job__hint") : null;
    }

    function show(btn, info) {
        var link = document.createElement("a");
        link.className = "job-reveal";
        link.href = info.href;
        link.textContent = info.value;
        var hint = hintOf(btn);
        btn.parentNode.replaceChild(link, btn);
        if (hint) {
            hint.parentNode.removeChild(hint);
        }
    }

    function message(btn, text) {
        var hint = hintOf(btn);
        if (hint) {
            hint.textContent = text;
        } else {
            btn.title = text;
        }
    }

    function step(name) {
        Array.prototype.forEach.call(dialog.querySelectorAll(".lead__step"), function (el) {
            el.hidden = el.getAttribute("data-step") !== name;
        });
    }

    function closeDialog() {
        if (dialog && dialog.open) {
            dialog.close();
        }
        current = null;
    }

    list.addEventListener("click", function (e) {
        var btn = e.target.closest ? e.target.closest(".job-reveal") : null;
        if (!btn || btn.tagName !== "BUTTON" || btn.getAttribute("data-busy")) {
            return;
        }
        e.preventDefault();
        btn.setAttribute("data-busy", "1");
        post({ step: "click", id: btn.getAttribute("data-id"), kind: btn.getAttribute("data-kind") })
            .then(function (res) {
                btn.removeAttribute("data-busy");
                if (res.ok && res.data.value) {
                    show(btn, res.data);
                } else if (res.ok && res.data.need_lead && dialog && dialog.showModal) {
                    current = { btn: btn, id: btn.getAttribute("data-id"), kind: btn.getAttribute("data-kind") };
                    var err = dialog.querySelector(".lead__error");
                    err.hidden = true;
                    step("ask");
                    dialog.showModal();
                } else {
                    message(btn, list.getAttribute("data-limit") || "");
                }
            })
            .catch(function () {
                btn.removeAttribute("data-busy");
                message(btn, list.getAttribute("data-limit") || "");
            });
    });

    if (!dialog) {
        return;
    }

    dialog.addEventListener("click", function (e) {
        var act = e.target.getAttribute ? e.target.getAttribute("data-act") : null;
        if (act === "yes") {
            step("form");
            var first = dialog.querySelector("#lead-name");
            if (first) { first.focus(); }
        } else if (act === "no" || e.target === dialog) {
            closeDialog(); // "no" or a click on the dark background
        }
    });

    dialog.querySelector("form").addEventListener("submit", function (e) {
        e.preventDefault();
        if (!current) {
            return;
        }
        var form = e.target;
        var err = form.querySelector(".lead__error");
        var submit = form.querySelector("[type=submit]");
        submit.disabled = true;
        err.hidden = true;
        var target = current;
        post({
            step: "lead", id: target.id, kind: target.kind,
            name: form.elements.name.value, phone: form.elements.phone.value
        })
            .then(function (res) {
                submit.disabled = false;
                if (res.ok && res.data.value) {
                    closeDialog();
                    if (target.btn.parentNode) {
                        show(target.btn, res.data);
                    }
                } else {
                    err.textContent = (res.data && res.data.message) || (list.getAttribute("data-limit") || "");
                    err.hidden = false;
                }
            })
            .catch(function () {
                submit.disabled = false;
                err.textContent = list.getAttribute("data-limit") || "";
                err.hidden = false;
            });
    });
})();

/* ---- পাসওয়ার্ড দেখা/লুকানো (লগইন): চোখ-বাটন JavaScript চালু থাকলেই দেখা যায় -------------- */
(function () {
    document.querySelectorAll(".lg-eye").forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute("aria-controls"));
        if (!input) {
            return;
        }
        btn.hidden = false;
        btn.addEventListener("click", function () {
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            btn.setAttribute("aria-pressed", String(show));
            var label = btn.getAttribute(show ? "data-hide" : "data-show");
            btn.setAttribute("aria-label", label);
        });
    });
})();

/* ---- রেজিস্ট্রেশনের ছবি: নাম ও প্রিভিউ দেখায়, বড় ছবি নিজে ছোট করে ---------------------------
   মোবাইলের ছবি কয়েক MB হয়; ১৫০ KB-এর বেশি হলে ছবির মান যতটা সম্ভব ধরে রেখে ব্রাউজারেই ছোট করা হয়
   (আগে মাপ ধরে রেখে গুণমান কমে, তারপর মাপ)। আসল সাইজ ও নতুন সাইজ ছবির নিচের লেখায় বসে।
   সার্ভারে আবার যাচাই হয় (PhotoUpload); প্রিভিউ data: ঠিকানায় (Content-Security-Policy-তে blob: নিষিদ্ধ)। */
(function () {
    var box = document.querySelector("[data-photo]");
    if (!box) {
        return;
    }
    var input = box.querySelector("input[type=file]");
    var nameEl = box.querySelector("[data-photo-name]");
    var preview = box.querySelector("[data-photo-preview]");
    var errorEl = box.querySelector("[data-photo-error]");
    var sizeEl = box.querySelector("[data-photo-size]");
    var placeholder = preview.getAttribute("src");
    var sizeDefault = sizeEl ? sizeEl.textContent : "";
    var max = parseInt(box.getAttribute("data-max"), 10);
    var types = ["image/jpeg", "image/png", "image/webp"];
    var sides = [1600, 1280, 1024, 800, 640, 480];
    var qualities = [0.9, 0.82, 0.74, 0.66, 0.58];
    var canWebp = document.createElement("canvas").toDataURL("image/webp").indexOf("data:image/webp") === 0;

    function fmt(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + " MB" : Math.max(1, Math.round(bytes / 1024)) + " KB";
    }
    function say(attr, original, size) {
        return box.getAttribute(attr).replace("{original}", fmt(original)).replace("{size}", fmt(size));
    }

    function reset(message) {
        input.value = "";
        preview.src = placeholder;
        nameEl.textContent = box.getAttribute("data-none");
        if (sizeEl) {
            sizeEl.textContent = sizeDefault;
        }
        errorEl.textContent = message || "";
        errorEl.hidden = !message;
    }

    function readImage(file) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onerror = reject;
            reader.onload = function () {
                var img = new Image();
                img.onload = function () { resolve({ img: img, url: String(reader.result) }); };
                img.onerror = reject;
                img.src = String(reader.result);
            };
            reader.readAsDataURL(file);
        });
    }

    function toBlob(img, side, type, quality) {
        var scale = Math.min(1, side / Math.max(img.naturalWidth, img.naturalHeight));
        var canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
        var ctx = canvas.getContext("2d");
        if (type === "image/jpeg") {
            ctx.fillStyle = "#fff";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        return new Promise(function (resolve) { canvas.toBlob(resolve, type, quality); });
    }

    /* বড় ছবি ছোট করে: প্রথম যে মাপ-গুণমানে max-এর ভিতরে আসে সেটাই নেওয়া হয় (সবচেয়ে ভালো মান) */
    function shrink(img) {
        var type = canWebp ? "image/webp" : "image/jpeg";
        var steps = [];
        sides.forEach(function (side) {
            qualities.forEach(function (q) { steps.push([side, q]); });
        });
        return steps.reduce(function (chain, step) {
            return chain.then(function (found) {
                return found || toBlob(img, step[0], type, step[1]).then(function (blob) {
                    return blob && blob.size <= max ? blob : null;
                });
            });
        }, Promise.resolve(null));
    }

    function useFile(file, url, original, shrunk) {
        var dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        preview.src = url;
        nameEl.textContent = box.getAttribute("data-chosen") + file.name;
        if (sizeEl) {
            sizeEl.textContent = shrunk ? say("data-size-shrunk", original, file.size) : say("data-size-same", original, file.size);
        }
        errorEl.hidden = true;
    }

    input.addEventListener("change", function () {
        var file = input.files && input.files[0];
        if (!file) {
            reset("");
            return;
        }
        if (types.indexOf(file.type) === -1) {
            reset(box.getAttribute("data-bad-type"));
            return;
        }
        readImage(file).then(function (loaded) {
            if (file.size <= max) {
                useFile(file, loaded.url, file.size, false);
                return null;
            }
            return shrink(loaded.img).then(function (blob) {
                if (!blob) {
                    reset(box.getAttribute("data-too-big"));
                    return;
                }
                var ext = blob.type === "image/webp" ? "webp" : "jpg";
                var small = new File([blob], file.name.replace(/\.[^.]+$/, "") + "." + ext, { type: blob.type });
                return readImage(small).then(function (done) {
                    useFile(small, done.url, file.size, true);
                });
            });
        }).catch(function () {
            reset(box.getAttribute("data-bad-type"));
        });
    });
})();

/* ---- ফর্মের ভুল-বার্তা: ঘর ভুল হলে ঠিক তার নিচে বাংলায় বলে (form[data-validate]) --------------
   মোবাইল ১১ সংখ্যা না হলে, ইমেইল/জাতীয় পরিচয়পত্র/পাসওয়ার্ড ভুল হলে, বাধ্যতামূলক ঘর খালি থাকলে।
   মোবাইল ও পরিচয়পত্র ঘরে শুধু অঙ্ক চলে (বাংলা অঙ্ক নিজে ইংরেজি হয়)। ভুল থাকলে জমা আটকায় ও প্রথম
   ভুল ঘরে নিয়ে যায়। সার্ভারও একই যাচাই করে, এটা শুধু দ্রুত জানানোর জন্য। */
(function () {
    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var bnDigits = { "০": "0", "১": "1", "২": "2", "৩": "3", "৪": "4", "৫": "5", "৬": "6", "৭": "7", "৮": "8", "৯": "9" };

    function digits(value) {
        return value.replace(/[০-৯]/g, function (c) { return bnDigits[c]; }).replace(/\D/g, "");
    }

    document.querySelectorAll("form[data-validate]").forEach(function (form) {
        var msg = function (key) { return form.getAttribute("data-msg-" + key) || ""; };

        function holder(field) {
            return field.closest(".pf") || field.parentNode;
        }
        function show(field, text) {
            var box = holder(field);
            var note = box.querySelector(".pf__msg");
            if (!text) {
                box.classList.remove("is-invalid");
                if (note) {
                    note.remove();
                }
                field.removeAttribute("aria-invalid");
                return;
            }
            if (!note) {
                note = document.createElement("p");
                note.className = "pf__msg";
                note.setAttribute("role", "alert");
                box.appendChild(note);
            }
            note.textContent = text;
            box.classList.add("is-invalid");
            field.setAttribute("aria-invalid", "true");
        }

        /* ঘরটা ভুল হলে বার্তা, ঠিক থাকলে ফাঁকা লেখা ফেরত দেয় */
        function check(field, live) {
            var linked = field.getAttribute("data-ss"); // খোঁজার ঘর: মান আসে তার আসল select থেকে
            var v = (linked ? document.getElementById(linked).value : field.value).trim();
            var required = field.hasAttribute("required");
            if (linked || field.tagName === "SELECT") {
                return required && (v === "" || v === "__all") ? msg("select") : "";
            }
            if (v === "") {
                return required ? msg("required") : "";
            }
            if (field.type === "tel" && field.hasAttribute("pattern")) {
                if (v.length > 11) {
                    return msg("phone-long");
                }
                return live && v.length < 11 ? "" : (/^01[3-9][0-9]{8}$/.test(v) ? "" : msg("phone"));
            }
            if (field.name === "nid") {
                return live || /^([0-9]{10}|[0-9]{13}|[0-9]{17})$/.test(v) ? "" : msg("nid");
            }
            if (field.type === "email") {
                return live || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? "" : msg("email");
            }
            if (field.name === "password" && field.hasAttribute("minlength")) {
                return live || v.length >= parseInt(field.getAttribute("minlength"), 10) ? "" : msg("password");
            }
            if (field.name === "password_confirm") {
                return live || v === form.elements.password.value ? "" : msg("match");
            }
            return "";
        }

        var fields = Array.prototype.filter.call(form.querySelectorAll("input, select, textarea"), function (f) {
            return f.type !== "hidden" && f.type !== "file" && f.type !== "submit" && !f.classList.contains("hp") && f.tabIndex !== -1;
        });

        fields.forEach(function (field) {
            if (field.type === "tel" && field.hasAttribute("pattern") || field.name === "nid") {
                field.addEventListener("input", function () {
                    var cleaned = digits(field.value);
                    if (cleaned !== field.value) {
                        field.value = cleaned;
                    }
                });
            }
            field.addEventListener("input", function () {
                // টাইপের সময় শুধু "বেশি হয়ে গেছে"-জাতীয় স্পষ্ট ভুল বলে; বাকিটা ঘর ছাড়ার সময়
                show(field, field.type === "tel" ? check(field, true) : (holder(field).classList.contains("is-invalid") ? check(field, false) : ""));
            });
            field.addEventListener("blur", function () {
                if (field.value !== "" || holder(field).classList.contains("is-invalid")) {
                    show(field, check(field, false));
                }
            });
            field.addEventListener("change", function () {
                if (field.tagName === "SELECT" || field.hasAttribute("data-ss")) {
                    show(field, check(field, false));
                }
            });
        });

        form.addEventListener("submit", function (e) {
            var first = null;
            fields.forEach(function (field) {
                var text = check(field, false);
                show(field, text);
                if (text && !first) {
                    first = field;
                }
            });
            if (first) {
                e.preventDefault();
                first.focus({ preventScroll: true });
                first.scrollIntoView({ block: "center", behavior: reduceMotion ? "auto" : "smooth" });
            }
        });
    });
})();

/* ---- "পাসওয়ার্ড মনে নেই" (লগইন পেইজের পপ-আপ) --------------------------------------------
   ধাপ: email -> code -> password -> done। প্রতিটি ধাপ নিজের ফর্মের action-এ (/password/forgot, /verify, /reset)
   POST করে JSON উত্তর পায়; ভুল হলে সার্ভারের বাংলা বার্তা ধাপের ভেতরেই দেখায়। JavaScript না থাকলে
   এই লিংক কাজ করে না, তখন হেল্পলাইনে কল করা যায়। */
(function () {
    var dialog = document.getElementById("forgot-dialog");
    var openers = document.querySelectorAll("[data-forgot]");
    if (!dialog || !openers.length || typeof dialog.showModal !== "function") {
        return;
    }
    var token = dialog.getAttribute("data-token");
    var steps = dialog.querySelectorAll("[data-step]");

    function show(name) {
        Array.prototype.forEach.call(steps, function (el) {
            el.hidden = el.getAttribute("data-step") !== name;
        });
        var first = dialog.querySelector('[data-step="' + name + '"] input');
        if (first) {
            first.focus();
        }
    }
    function fail(step, text) {
        var err = step.querySelector(".lead__error");
        if (err) {
            err.textContent = text;
            err.hidden = !text;
        }
    }

    openers.forEach(function (btn) {
        btn.addEventListener("click", function () {
            dialog.querySelectorAll("form").forEach(function (f) {
                f.reset();
                fail(f, "");
            });
            show("email");
            dialog.showModal();
        });
    });
    dialog.querySelectorAll('[data-act="close"]').forEach(function (b) {
        b.addEventListener("click", function () {
            dialog.close();
        });
    });

    var next = { email: "code", code: "password", password: "done" };
    dialog.querySelectorAll("form").forEach(function (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var name = form.getAttribute("data-step");
            var body = new FormData(form);
            body.append("_token", token);
            var send = form.querySelector('button[type="submit"]');
            send.disabled = true;
            fail(form, "");
            fetch(form.getAttribute("action"), { method: "POST", body: body, credentials: "same-origin" })
                .then(function (r) {
                    return r.json().then(function (j) { return { ok: r.ok, data: j }; });
                })
                .then(function (res) {
                    if (res.ok) {
                        show(next[name]);
                    } else {
                        fail(form, res.data.message || dialog.getAttribute("data-net"));
                        // মেয়াদ শেষ বা বেশি ভুল হলে আবার ইমেইল থেকে শুরু
                        if (res.data.error === "expired" || res.data.error === "tries") {
                            show("email");
                            fail(dialog.querySelector('[data-step="email"]'), res.data.message);
                        }
                    }
                })
                .catch(function () {
                    fail(form, dialog.getAttribute("data-net"));
                })
                .then(function () {
                    send.disabled = false;
                });
        });
    });
})();

/* ---- "বর্তমান পাসওয়ার্ড" ঘরে ব্রাউজারের পুরনো পাসওয়ার্ড বসানো আটকানো (data-no-autofill) ---------
   ব্রাউজার অন্য অ্যাকাউন্টের মনে-রাখা পাসওয়ার্ড নিজে বসিয়ে দিলে সার্ভার "বর্তমান পাসওয়ার্ড ভুল" বলত।
   autocomplete="new-password" বেশিরভাগ ব্রাউজারে আটকায়; তবু বসে গেলে ব্যবহারকারী লেখার আগে ঘরটা খালি করা হয়। */
(function () {
    document.querySelectorAll("input[data-no-autofill]").forEach(function (field) {
        var typed = false;
        field.addEventListener("keydown", function () { typed = true; });
        function clearIfAutofilled() {
            if (!typed && field.value !== "") {
                field.value = "";
            }
        }
        [100, 500, 1200].forEach(function (ms) { window.setTimeout(clearIfAutofilled, ms); });
    });
})();
