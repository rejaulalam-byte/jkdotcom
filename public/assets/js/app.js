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

    /* ---- Button "back to top": appears past the middle of the page ---------- */
    var toTop = document.querySelector("[data-to-top]");
    if (toTop) {
        var toggleToTop = function () {
            var scrollable = document.documentElement.scrollHeight - window.innerHeight;
            toTop.classList.toggle("is-visible", scrollable > 0 && window.scrollY > scrollable / 2);
        };
        window.addEventListener("scroll", toggleToTop, { passive: true });
        window.addEventListener("resize", toggleToTop);
        toggleToTop();
        toTop.addEventListener("click", function () {
            window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
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
