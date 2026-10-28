/* =====================================================================================================
   খোলা পেইজের লেখার পূর্ণ ফরম্যাটিং এডিটর (TinyMCE 6, public/assets/vendor/tinymce)।
   এখানের অনুমোদিত ট্যাগ / style / ক্লাসের তালিকা সার্ভারের ছাঁকনির (app/Core/RichText.php) সঙ্গে মিলিয়ে রাখা:
   এডিটরে যা বানানো যায় তা-ই সংরক্ষণে টিকে থাকে। একটা বদলালে অন্যটাও বদলান।
   ===================================================================================================== */
(function () {
    var area = document.getElementById("rich-editor");
    if (!area || typeof tinymce === "undefined") {
        return;
    }
    // লাইভ পেইজ এডিটের বড় বাক্সে (data-lazy): বাক্স খোলার পরে শুরু, যাতে এডিটর ঠিক মাপে বসে
    if (area.hasAttribute("data-lazy")) {
        document.addEventListener("jk:open-rich", start, { once: true });
    } else {
        start();
    }

    function start() {
        var form = area.form;
        var startHtml = null;
        var token = form.querySelector('input[name="_token"]').value;

        /* ---- ছবি বসানো: ছবিতে সারিবদ্ধ-বাটন চাপলে ক্লাস (img-left/right/center), অন্য ঘরে text-align ---- */
        function align(side, imgClass) {
            return [
                { selector: "p,h2,h3,h4,li,td,th", styles: { textAlign: side } },
                { selector: "img", classes: imgClass }
            ];
        }

        /* ---- অক্ষরের ঘনত্ব ও অনুচ্ছেদের নিচের ফাঁক: নিজের মেনু-বাটন ---- */
        var WEIGHTS = [["300", "হালকা (300)"], ["400", "সাধারণ (400)"], ["500", "মাঝারি (500)"], ["600", "একটু মোটা (600)"], ["700", "মোটা (700)"], ["800", "খুব মোটা (800)"]];
        var SPACES = [["0", "কোনো ফাঁক নয়"], ["8px", "কম (8px)"], ["16px", "সাধারণ (16px)"], ["24px", "বেশি (24px)"], ["36px", "অনেক বেশি (36px)"]];
        var formats = {
            bold: { inline: "strong" },
            italic: { inline: "em" },
            underline: { inline: "u", exact: true },
            strikethrough: { inline: "s", exact: true },
            alignleft: align("left", "img-left"),
            aligncenter: align("center", "img-center"),
            alignright: align("right", "img-right"),
            alignjustify: [{ selector: "p,h2,h3,h4,li,td,th", styles: { textAlign: "justify" } }]
        };
        WEIGHTS.forEach(function (w) {
            formats["weight" + w[0]] = { inline: "span", styles: { fontWeight: w[0] } };
        });
        SPACES.forEach(function (s, i) {
            formats["pspace" + i] = { selector: "p,h2,h3,h4", styles: { marginBottom: s[0] } };
        });

        tinymce.init({
            target: area,
            base_url: "/assets/vendor/tinymce",
            suffix: ".min",
            promotion: false,
            branding: false,
            height: 760,
            menubar: "edit view insert format table",
            plugins: "lists advlist link image table code charmap searchreplace fullscreen visualblocks wordcount",
            toolbar: "undo redo | blocks fontfamily fontsize lineheight paragraphspace | bold italic underline strikethrough fontweight forecolor backcolor removeformat | "
                + "alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table charmap | "
                + "searchreplace visualblocks code fullscreen",
            toolbar_mode: "wrap",
            toolbar_sticky: true,
            content_css: area.getAttribute("data-content-css"),
            body_class: "rich",
            block_formats: "অনুচ্ছেদ=p; বড় শিরোনাম=h2; শিরোনাম=h3; ছোট শিরোনাম=h4",
            font_family_formats: "Hind Siliguri (সাইটের ফন্ট)=Hind Siliguri, sans-serif; Poppins (ইংরেজি)=Poppins, sans-serif",
            font_size_formats: "10px 12px 14px 16px 18px 20px 22px 24px 26px 28px 30px 32px 36px 40px 44px 48px 54px 60px 66px 70px",
            lineheight_formats: "1 1.2 1.4 1.5 1.6 1.8 2 2.2 2.5",
            formats: formats,
            // সার্ভারের ছাঁকনির সঙ্গে মেলানো তালিকা (img-এ style না থাকলে টেনে-বদলানো মাপ এডিটর নিজেই মুছে দেয়)
            valid_elements: "p[style|class],h2[style|class],h3[style|class],h4[style|class],br,hr,strong/b,em/i,u,s/strike,sub,sup,"
                + "span[style],blockquote[style],ul[style],ol[style],li[style],a[href|target|rel],img[src|alt|class|style|width|height],"
                + "table[style],thead,tbody,tfoot,tr[style],th[colspan|rowspan|style],td[colspan|rowspan|style]",
            valid_styles: {
                p: "text-align,margin-left,line-height,margin-top,margin-bottom",
                h2: "text-align,margin-left,line-height,margin-top,margin-bottom",
                h3: "text-align,margin-left,line-height,margin-top,margin-bottom",
                h4: "text-align,margin-left,line-height,margin-top,margin-bottom",
                li: "text-align,line-height",
                blockquote: "margin-left",
                ul: "list-style-type,margin-left",
                ol: "list-style-type,margin-left",
                span: "color,background-color,font-family,font-size,font-weight,line-height",
                img: "width,height",
                table: "width,margin-left,margin-right,border,border-width,border-style,border-color",
                tr: "background-color,height",
                th: "width,height,vertical-align,text-align,background-color,border,border-width,border-style,border-color,border-top,border-right,border-bottom,border-left",
                td: "width,height,vertical-align,text-align,background-color,border,border-width,border-style,border-color,border-top,border-right,border-bottom,border-left"
            },
            valid_classes: {
                p: "ta-center ta-right ta-justify legal-meta indent",
                h2: "ta-center ta-right", h3: "ta-center ta-right", h4: "ta-center ta-right",
                img: "img-left img-right img-center img-left-sm img-right-sm img-shape img-down-10"
            },
            image_class_list: [
                { title: "লেখার সারিতে (ভাসবে না)", value: "" },
                { title: "ডানে, লেখা বাঁ পাশে মোড়ানো", value: "img-right" },
                { title: "বাঁয়ে, লেখা ডান পাশে মোড়ানো", value: "img-left" },
                { title: "ডানে, ছোট", value: "img-right-sm" },
                { title: "বাঁয়ে, ছোট", value: "img-left-sm" },
                { title: "মাঝখানে, পাশে লেখা নেই", value: "img-center" },
                { title: "ডানে, PNG-এর কিনারা ঘেঁষে লেখা", value: "img-right img-shape" },
                { title: "বাঁয়ে, PNG-এর কিনারা ঘেঁষে লেখা", value: "img-left img-shape" }
            ],
            image_dimensions: true,
            image_advtab: false,
            image_description: true,
            object_resizing: "img",
            resize_img_proportional: true,
            images_file_types: "jpg,jpeg,png,webp",
            automatic_uploads: true,
            paste_data_images: true,
            images_upload_handler: function (blobInfo) {
                var data = new FormData();
                data.append("_token", token);
                data.append("file", blobInfo.blob(), blobInfo.filename());
                return fetch(area.getAttribute("data-upload"), { method: "POST", body: data, credentials: "same-origin" })
                    .then(function (r) {
                        return r.json().catch(function () { return { error: "ছবি আপলোড হয়নি (সার্ভারের উত্তর বোঝা যায়নি)।" }; });
                    })
                    .then(function (j) {
                        if (!j.location) {
                            throw new Error(j.error || "ছবি আপলোড হয়নি।");
                        }
                        return j.location;
                    });
            },
            convert_urls: false,
            relative_urls: false,
            link_default_protocol: "https",
            link_target_list: [{ title: "একই ট্যাবে", value: "" }, { title: "নতুন ট্যাবে", value: "_blank" }],
            link_title: false,
            table_use_colgroups: false,
            table_default_attributes: {},
            table_default_styles: { width: "100%" },
            table_advtab: true,
            table_row_advtab: true,
            table_cell_advtab: true,
            indent_use_margin: true,
            indentation: "40px",
            entity_encoding: "raw",
            setup: function (editor) {
                // শুরুর লেখা মনে রাখা: সংরক্ষণের সময় এর সঙ্গে মিলিয়ে বোঝা হয় বদলেছে কি না
                editor.on("init", function () {
                    startHtml = editor.getContent();
                });
                editor.ui.registry.addMenuButton("fontweight", {
                    text: "ঘনত্ব",
                    tooltip: "অক্ষরের ঘনত্ব (মোটা/হালকা)",
                    fetch: function (done) {
                        done(WEIGHTS.map(function (w) {
                            return { type: "menuitem", text: w[1], onAction: function () { editor.formatter.toggle("weight" + w[0]); } };
                        }));
                    }
                });
                editor.ui.registry.addMenuButton("paragraphspace", {
                    text: "অনুচ্ছেদের ফাঁক",
                    tooltip: "অনুচ্ছেদের নিচে কত ফাঁক",
                    fetch: function (done) {
                        done(SPACES.map(function (s, i) {
                            return {
                                type: "menuitem", text: s[1], onAction: function () {
                                    SPACES.forEach(function (x, j) { editor.formatter.remove("pspace" + j); });
                                    editor.formatter.apply("pspace" + i);
                                }
                            };
                        }));
                    }
                });
            }
        });

        /* ---- সংরক্ষণের আগে এডিটরের লেখা ঘরে তুলে দেওয়া এবং সার্ভারকে জানানো লেখা বদলেছে কি না
           (না বদলালে পুরো লেখা আবার সংরক্ষণ হয় না; JavaScript না থাকলে সার্ভার ঘরের লেখা সবসময় নেয়) ---- */
        function flag(name, value) {
            var input = form.querySelector('input[name="' + name + '"]');
            if (!input) {
                input = document.createElement("input");
                input.type = "hidden";
                input.name = name;
                form.appendChild(input);
            }
            input.value = value;
        }
        // এডিটরের নিজের "বদলেছে" চিহ্ন ফর্ম জমার সময় নিজেই মুছে যেতে পারে, তাই শুরুর লেখার সঙ্গে মিলিয়ে দেখা
        form.addEventListener("submit", function () {
            var editor = tinymce.get(area.id);
            var changed = editor !== null && editor !== undefined && (editor.isDirty() || editor.getContent() !== startHtml);
            flag("html_js", "1");
            flag("html_dirty", changed ? "1" : "0");
            tinymce.triggerSave();
        });
    }
})();
