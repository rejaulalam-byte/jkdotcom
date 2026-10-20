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
       next page rises in the same slow way. Without JS or with "reduce motion" the plain list
       (first N rows) is shown. */
    var ticker = document.querySelector(".ticker[data-page-size]");
    if (ticker && !reduceMotion && ticker.animate) {
        var size = parseInt(ticker.getAttribute("data-page-size"), 10) || 8;
        var hold = parseInt(ticker.getAttribute("data-hold"), 10) || 5500;
        var rise = parseInt(ticker.getAttribute("data-rise"), 10) || 4000;
        var rows = Array.prototype.slice.call(ticker.children);

        if (rows.length > size) {
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
            var play = function (el, from, to, ms, easing) {
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

            (async function loop() {
                var index = 0;
                for (;;) {
                    var current = pages[index];
                    await whenVisible();
                    current.classList.add("is-shown");
                    await play(current, "translateY(100%)", "translateY(0)", rise, "cubic-bezier(.25,.1,.25,1)");
                    await sleep(hold);
                    await whenVisible();
                    await play(current, "translateY(0)", "translateY(-100%)", 450, "ease-in");
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

    /* ---- Advertisement strip: endless sideways scroll -------------------- */
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
            strip.style.setProperty("--ad-shift", setWidth + "px");
            strip.style.setProperty("--ad-duration", Math.max(15, setWidth / 55) + "s");
            strip.classList.add("is-marquee");
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
