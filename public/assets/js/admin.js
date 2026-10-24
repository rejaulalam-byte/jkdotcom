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

/* ---- নিশ্চিত করার প্রশ্ন: <form data-confirm="প্রশ্ন"> ---- */
(function () {
    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
        form.addEventListener("submit", function (e) {
            if (!window.confirm(form.getAttribute("data-confirm"))) {
                e.preventDefault();
            }
        });
    });
})();
