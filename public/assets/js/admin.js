/* =====================================================================================================
   অ্যাডমিন প্যানেলের ছোট জাভাস্ক্রিপ্ট (পাবলিক সাইটের app.js থেকে আলাদা)। JavaScript ছাড়াও প্যানেল চলে; এটা শুধু আরাম:
   ১) পাসওয়ার্ডের চোখ-বাটন  ২) "data-confirm" থাকা ফর্ম জমার আগে "নিশ্চিত?" জিজ্ঞেস
   ===================================================================================================== */

/* ---- পাসওয়ার্ড দেখা/লুকানো: বাটন লুকানো থাকে, JavaScript চালু থাকলেই দেখা যায় ---- */
(function () {
    document.querySelectorAll(".adm-eye").forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute("aria-controls"));
        if (!input) {
            return;
        }
        btn.hidden = false;
        btn.addEventListener("click", function () {
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            btn.setAttribute("aria-pressed", String(show));
            btn.setAttribute("aria-label", btn.getAttribute(show ? "data-hide" : "data-show"));
        });
    });
})();

/* ---- নিশ্চিত করার প্রশ্ন: <form data-confirm="প্রশ্ন"> জমা দেওয়ার আগে "হ্যাঁ / না" বাক্স ----
   বাক্সের লেখায় "হ্যাঁ চাপুন" থাকতে পারে, তাই বোতামের নামও "হ্যাঁ" ও "না" (ব্রাউজারের OK/Cancel নয়)।
   <dialog> না থাকলে (খুব পুরনো ব্রাউজার) সাধারণ confirm চলে। ডিফল্ট ফোকাস "না"-তে: ভুলে Enter চাপলে কাজ হয় না। */
(function () {
    var forms = document.querySelectorAll("form[data-confirm]");
    if (!forms.length) {
        return;
    }
    var dialog = null;
    var text = null;
    var onYes = null;

    function build() {
        dialog = document.createElement("dialog");
        dialog.className = "adm-dialog";
        text = document.createElement("p");
        var row = document.createElement("div");
        row.className = "adm-dialog__row";
        var yes = document.createElement("button");
        yes.type = "button";
        yes.className = "adm-small adm-small--go";
        yes.textContent = "হ্যাঁ";
        var no = document.createElement("button");
        no.type = "button";
        no.className = "adm-small";
        no.textContent = "না";
        no.autofocus = true;
        yes.addEventListener("click", function () {
            dialog.close();
            if (onYes) {
                onYes();
            }
        });
        no.addEventListener("click", function () {
            dialog.close();
        });
        row.appendChild(yes);
        row.appendChild(no);
        dialog.appendChild(text);
        dialog.appendChild(row);
        document.body.appendChild(dialog);
    }

    forms.forEach(function (form) {
        var confirmed = false;
        form.addEventListener("submit", function (e) {
            if (confirmed) {
                return;
            }
            e.preventDefault();
            var question = form.getAttribute("data-confirm");
            if (typeof HTMLDialogElement !== "function") {
                if (window.confirm(question)) {
                    confirmed = true;
                    form.submit();
                }
                return;
            }
            if (!dialog) {
                build();
            }
            text.textContent = question;
            onYes = function () {
                confirmed = true;
                form.submit();
            };
            dialog.showModal();
        });
    });
})();

/* ---- বিভাগ → জেলা → উপজেলা: একটা অন্যটাকে ছাঁকে ([data-geo] সিলেক্ট; সম্পাদনা ফর্মে) ----
   বিভাগ বাছলে শুধু সেই বিভাগের জেলা দেখায়, জেলা বাছলে শুধু সেই জেলার উপজেলা। উপজেলা বা জেলা আগে বাছলে ওপরেরগুলো নিজে বসে যায়। */
(function () {
    var division = document.querySelector('select[data-geo="division"]');
    var district = document.querySelector('select[data-geo="district"]');
    var upazila = document.querySelector('select[data-geo="upazila"]');
    if (!division || !district || !upazila) {
        return;
    }

    // $parent-এর মান অনুযায়ী $select-এর বিকল্প দেখায়/লুকায়; বর্তমান বাছাই না মিললে ফাঁকা করে
    function filter(select, parent) {
        Array.prototype.forEach.call(select.options, function (option) {
            var show = option.value === "" || parent === "" || option.getAttribute("data-parent") === parent;
            option.hidden = !show;
            option.disabled = !show;
        });
        if (select.value !== "" && select.options[select.selectedIndex].disabled) {
            select.value = "";
        }
    }
    function parentOf(select) {
        var option = select.options[select.selectedIndex];
        return option ? option.getAttribute("data-parent") || "" : "";
    }

    division.addEventListener("change", function () {
        filter(district, division.value);
        filter(upazila, district.value);
    });
    district.addEventListener("change", function () {
        if (district.value !== "" && division.value === "") {
            division.value = parentOf(district);
            filter(district, division.value);
        }
        filter(upazila, district.value);
    });
    upazila.addEventListener("change", function () {
        if (upazila.value !== "" && district.value === "") {
            district.value = parentOf(upazila);
            division.value = parentOf(district);
            filter(district, division.value);
            filter(upazila, district.value);
        }
    });

    filter(district, division.value);
    filter(upazila, district.value);
})();

/* ---- "লাইভ পেইজ এডিট"-এর ড্রপডাউন: পেইজ বাছলেই খোলে (form[data-auto-submit]); JavaScript ছাড়া "দেখুন" বাটন থাকে ---- */
(function () {
    document.querySelectorAll("form[data-auto-submit]").forEach(function (form) {
        var go = form.querySelector(".adm-live__go");
        if (go) {
            go.hidden = true;
        }
        form.querySelector("select").addEventListener("change", function () {
            form.submit();
        });
    });
})();

/* ---- "লাইভ পেইজ এডিট": ফ্রেমের মূল লেখায় ক্লিক (live-edit.js পাঠায় {jk: "open-rich"}) = পূর্ণ এডিটরের বড় বাক্স।
   প্রথমবার খোলার সময় এডিটর শুরু হয় (admin-editor.js, "jk:open-rich")। বন্ধ করার আগে না-সংরক্ষিত বদল থাকলে জিজ্ঞেস। ---- */
(function () {
    var dialog = document.getElementById("rich-dialog");
    if (!dialog || typeof dialog.showModal !== "function") {
        return;
    }
    window.addEventListener("message", function (e) {
        if (e.origin !== window.location.origin || !e.data || e.data.jk !== "open-rich" || dialog.open) {
            return;
        }
        dialog.showModal();
        document.dispatchEvent(new CustomEvent("jk:open-rich"));
    });
    dialog.querySelector("[data-rich-close]").addEventListener("click", function () {
        var editor = window.tinymce ? window.tinymce.get("rich-editor") : null;
        if (editor && editor.isDirty() && !window.confirm("লেখার বদল সংরক্ষণ হয়নি। তবুও বন্ধ করবেন?")) {
            return;
        }
        dialog.close();
    });
})();

/* ---- "লাইভ পেইজ এডিট": মেনু সম্পাদনার বাক্স (studio-menu.php; শুধু সুপার অ্যাডমিন) ----
   খোলে "মেনু সম্পাদনা" বাটনে বা ফ্রেমের মেনু/ফুটারের লিংকে ক্লিকে (live-edit.js পাঠায় {jk: "open-menu"})।
   সারি যোগ / মোছা / ওপরে-নিচে; জমার আগে প্রতিটি ঘরের নাম পর্দার ক্রমে নতুন করে নম্বর পায়
   (header[i][label|href], header[i][sub][j][...], footer[c][j][...]), তাই সার্ভার যে ক্রমে পায় সেটাই সাইটের ক্রম। ---- */
(function () {
    var dialog = document.getElementById("menu-dialog");
    if (!dialog || typeof dialog.showModal !== "function") {
        return;
    }
    var form = dialog.querySelector("[data-menu-form]");
    var template = document.getElementById("menu-row-template");
    var dirty = false;

    function open() {
        if (!dialog.open) {
            dialog.showModal();
        }
    }
    document.querySelectorAll("[data-menu-open]").forEach(function (b) {
        b.addEventListener("click", open);
    });
    window.addEventListener("message", function (e) {
        if (e.origin === window.location.origin && e.data && e.data.jk === "open-menu") {
            open();
        }
    });
    dialog.querySelector("[data-menu-close]").addEventListener("click", function () {
        if (dirty && !window.confirm("মেনুর বদল সংরক্ষণ হয়নি। তবুও বন্ধ করবেন?")) {
            return;
        }
        dialog.close();
    });
    form.addEventListener("input", function () {
        dirty = true;
    });

    // JavaScript থাকলে বাটনগুলো দেখা যায়
    dialog.querySelectorAll(".adm-menu__tools, .adm-menu__adders").forEach(function (el) {
        el.hidden = false;
    });

    function newRow(kind) {
        var row = template.content.firstElementChild.cloneNode(true);
        row.setAttribute("data-menu-row", kind);
        row.querySelector(".adm-menu__tools").hidden = false;
        return row;
    }

    // প্রতিটি মেনু-আইটেমের সাব-মেনুর নিচে "+ সাব-মেনু যোগ"
    function addSubAdder(item) {
        var p = document.createElement("p");
        p.className = "adm-menu__adders";
        var b = document.createElement("button");
        b.className = "adm-small";
        b.type = "button";
        b.setAttribute("data-menu-add", "sub");
        b.textContent = "+ সাব-মেনু যোগ করুন";
        p.appendChild(b);
        item.appendChild(p);
    }
    dialog.querySelectorAll("[data-menu-item]").forEach(addSubAdder);

    dialog.addEventListener("click", function (e) {
        var add = e.target.closest("[data-menu-add]");
        if (add) {
            var kind = add.getAttribute("data-menu-add");
            dirty = true;
            if (kind === "top") {
                var item = document.createElement("div");
                item.className = "adm-menu__item";
                item.setAttribute("data-menu-item", "");
                item.appendChild(newRow("top"));
                var sub = document.createElement("div");
                sub.className = "adm-menu__sub";
                sub.setAttribute("data-menu-list", "sub");
                item.appendChild(sub);
                addSubAdder(item);
                dialog.querySelector('[data-menu-list="top"]').appendChild(item);
                item.querySelector("input").focus();
            } else if (kind === "sub") {
                var list = add.closest("[data-menu-item]").querySelector('[data-menu-list="sub"]');
                list.appendChild(newRow("sub"));
                list.lastElementChild.querySelector("input").focus();
            } else {
                var col = dialog.querySelector('[data-menu-list="' + kind + '"]');
                col.appendChild(newRow(kind));
                col.lastElementChild.querySelector("input").focus();
            }
            return;
        }
        var act = e.target.closest("[data-menu-act]");
        if (!act) {
            return;
        }
        dirty = true;
        var row = act.closest("[data-menu-row]");
        // ওপরের মেনুর সারি মানে পুরো আইটেম (তার সাব-মেনুসহ)
        var unit = row.getAttribute("data-menu-row") === "top" ? row.closest("[data-menu-item]") : row;
        var what = act.getAttribute("data-menu-act");
        if (what === "remove") {
            unit.remove();
        } else if (what === "up" && unit.previousElementSibling) {
            unit.parentNode.insertBefore(unit, unit.previousElementSibling);
        } else if (what === "down" && unit.nextElementSibling) {
            unit.parentNode.insertBefore(unit.nextElementSibling, unit);
        }
    });

    form.addEventListener("submit", function () {
        function name(row, prefix) {
            var inputs = row.querySelectorAll("input");
            inputs[0].name = prefix + "[label]";
            inputs[1].name = prefix + "[href]";
        }
        dialog.querySelectorAll('[data-menu-list="top"] > [data-menu-item]').forEach(function (item, i) {
            name(item.querySelector('[data-menu-row="top"]'), "header[" + i + "]");
            item.querySelectorAll('[data-menu-list="sub"] > [data-menu-row]').forEach(function (row, j) {
                name(row, "header[" + i + "][sub][" + j + "]");
            });
        });
        dialog.querySelectorAll('[data-menu-list^="footer-"]').forEach(function (list) {
            var c = list.getAttribute("data-menu-list").slice(7);
            list.querySelectorAll("[data-menu-row]").forEach(function (row, j) {
                name(row, "footer[" + c + "][" + j + "]");
            });
        });
        dirty = false;
    });
})();

/* ---- থিমের নমুনা (admin/theme.php): রং-ঘর আর কোডের ঘর একে অপরকে মেলায়, আর ডানের নমুনা সঙ্গে সঙ্গে নতুন রং নেয়
   (নমুনার --p-* মানে বসে; সাইটে কিছু বদলায় না, সংরক্ষণ করলে তবেই) ---- */
(function () {
    var preview = document.querySelector("[data-theme-preview]");
    if (!preview) {
        return;
    }
    document.querySelectorAll("[data-swatch]").forEach(function (s) {
        s.style.backgroundColor = s.getAttribute("data-swatch");
    });
    function apply(name, value) {
        preview.style.setProperty("--p-" + name, value);
    }
    document.querySelectorAll("[data-theme-color]").forEach(function (picker) {
        var name = picker.getAttribute("data-theme-color");
        var hex = document.querySelector('[data-theme-hex="' + name + '"]');
        apply(name, picker.value);
        picker.addEventListener("input", function () {
            hex.value = picker.value;
            apply(name, picker.value);
        });
        hex.addEventListener("input", function () {
            if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) {
                picker.value = hex.value.toLowerCase();
                apply(name, picker.value);
            }
        });
    });
})();

/* ---- রঙের প্লেট (admin/home-section.php): select[data-palette] লুকিয়ে তার জায়গায় রঙের খোপ; বাছা মান select-এই থাকে, তাই ফর্ম জমা আগের মতোই ---- */
(function () {
    if (!window.jkPalette) {
        return;
    }
    document.querySelectorAll("select[data-palette]").forEach(function (select) {
        var colors = {};
        try {
            colors = JSON.parse(select.getAttribute("data-palette")) || {};
        } catch (e) {
            return;
        }
        var pal = window.jkPalette({
            items: Array.prototype.map.call(select.options, function (o) {
                return { value: o.value, label: o.textContent.trim(), color: colors[o.value] || "#ffffff" };
            }),
            value: select.value,
            kind: "fill",
            title: "রং বাছুন",
            onPick: function (v) {
                select.value = v;
            }
        });
        select.hidden = true;
        select.parentNode.insertBefore(pal.el, select.nextSibling);
    });
})();
