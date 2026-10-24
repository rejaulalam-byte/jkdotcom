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
