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
