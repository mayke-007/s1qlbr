/*
 * S1QLBR — menu do topo (usado em todas as páginas).
 * Para adicionar uma opção nova, inclua um item em ITENS. Só isso.
 *   href  : endereço da página
 *   breve : true mostra a etiqueta "em breve"
 *   t     : nome em cada idioma
 */
(function () {
  var ITENS = [
    { href: "/", t: { pt: "Gerador de queries", en: "Query builder", es: "Generador de queries", zh: "查询生成器" } },
    { href: "/analise/", breve: true, t: { pt: "Análise de resultados", en: "Results analysis", es: "Análisis de resultados", zh: "结果分析" } },
    { href: "/donate/donate.html", breve: true, t: { pt: "Apoie o projeto", en: "Support us", es: "Apoya el proyecto", zh: "支持项目" } },
    { href: "/contato/contato.php", t: { pt: "Fale conosco", en: "Contact us", es: "Contáctanos", zh: "联系我们" } }
  ];
  var BREVE = { pt: "em breve", en: "soon", es: "pronto", zh: "即将推出" };
  var ARIA = { pt: "Menu principal", en: "Main menu", es: "Menú principal", zh: "主菜单" };
  var NOMES = { pt: "PT", en: "EN", es: "ES", zh: "中文" };
  var HTML_LANG = { pt: "pt-BR", en: "en", es: "es", zh: "zh-CN" };

  function lang() {
    var sel = document.getElementById("lang");
    if (sel && sel.options.length && BREVE[sel.value]) return sel.value;
    return inicial();
  }
  function inicial() {
    try {
      var u = new URLSearchParams(location.search).get("lang");
      if (BREVE[u]) return u;
      var s = localStorage.getItem("s1ql-lang");
      if (BREVE[s]) return s;
    } catch (e) {}
    var n = (navigator.language || "pt").slice(0, 2).toLowerCase();
    return BREVE[n] ? n : "pt";
  }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; }); }
  function pasta(h) { return h.replace(/[^/]*$/, ""); }
  function atual(h) {
    var p = location.pathname.replace(/index\.html$/, "");
    if (h === "/") return p === "/";
    return p === h || p.indexOf(pasta(h)) === 0;
  }

  var css = document.createElement("style");
  css.textContent =
    ".s1nav{grid-column:1/-1;border-bottom:1px solid var(--line);margin-top:4px}" +
    ".s1nav ul{list-style:none;margin:0;padding:0;display:flex;gap:2px;overflow-x:auto;scrollbar-width:none;position:relative}" +
    "@media (max-width:560px){.s1nav ul{-webkit-mask-image:linear-gradient(90deg,#000 85%,transparent);mask-image:linear-gradient(90deg,#000 85%,transparent)}.s1nav a{padding:9px 9px 10px}}" +
    ".s1nav ul::-webkit-scrollbar{display:none}" +
    ".s1nav li.dir{margin-left:auto}" +
    ".s1nav a{display:inline-flex;align-items:center;gap:7px;padding:9px 11px 10px;color:var(--muted);text-decoration:none;font-size:.9rem;font-weight:500;white-space:nowrap;border-bottom:2px solid transparent}" +
    ".s1nav a:hover{color:var(--ink)}" +
    ".s1nav a:focus-visible{outline:2px solid var(--accent);outline-offset:-2px;border-radius:4px}" +
    ".s1nav a[aria-current=page]{color:var(--ink);border-bottom-color:var(--accent)}" +
    ".s1nav .breve{font-family:var(--mono);font-size:.66rem;font-weight:500;padding:1px 7px;border-radius:999px;background:var(--accent-soft);color:var(--ink)}";
  document.head.appendChild(css);

  var nav = document.createElement("nav");
  nav.className = "s1nav";

  function render() {
    var L = lang(), q = L === "pt" ? "" : "?lang=" + L, ult = ITENS.length - 1;
    nav.setAttribute("aria-label", ARIA[L]);
    nav.innerHTML = "<ul>" + ITENS.map(function (it, i) {
      return '<li' + (i === ult ? ' class="dir"' : "") + '><a href="' + esc(it.href + q) + '"' + (atual(it.href) ? ' aria-current="page"' : "") + ">" +
        esc(it.t[L] || it.t.pt) + (it.breve ? ' <span class="breve">' + esc(BREVE[L]) + "</span>" : "") + "</a></li>";
    }).join("") + "</ul>";
    // no celular o menu rola de lado: deixa o item da página atual visível
    var ul = nav.firstChild, at = nav.querySelector("[aria-current]");
    if (ul && at && ul.scrollWidth > ul.clientWidth) {
      var li = at.parentNode;
      ul.scrollLeft = Math.max(0, li.offsetLeft - ul.offsetLeft - (ul.clientWidth - li.offsetWidth) / 2);
    }
  }

  function monta() {
    var header = document.querySelector("header");
    if (!header) return;
    var p = header.querySelector(":scope > p");
    if (p) header.insertBefore(nav, p); else header.appendChild(nav);
    render();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", monta); else monta();
  document.addEventListener("change", function (e) { if (e.target && e.target.id === "lang") render(); });

  // Idioma das páginas internas (contato, análise, apoio). O index tem o seu próprio.
  function idioma(TX, cb) {
    var sel = document.getElementById("lang");
    if (!sel) return;
    sel.innerHTML = Object.keys(NOMES).map(function (k) { return '<option value="' + k + '">' + NOMES[k] + "</option>"; }).join("");
    sel.value = inicial();
    function aplica() {
      var L = sel.value, d = TX[L] || TX.pt;
      var t = function (k) { return d[k] !== undefined ? d[k] : TX.pt[k]; };
      document.documentElement.lang = HTML_LANG[L];
      if (t("title")) document.title = t("title");
      document.querySelectorAll("[data-i]").forEach(function (el) { var v = t(el.getAttribute("data-i")); if (v !== undefined) el.textContent = v; });
      document.querySelectorAll("[data-ph]").forEach(function (el) { var v = t(el.getAttribute("data-ph")); if (v !== undefined) el.placeholder = v; });
      sel.setAttribute("aria-label", t("idioma") || "Idioma");
      render();
      if (cb) cb(L, t);
    }
    sel.addEventListener("change", function () {
      try { localStorage.setItem("s1ql-lang", sel.value); var u = new URL(location.href); u.searchParams.set("lang", sel.value); history.replaceState(null, "", u); } catch (e) {}
      aplica();
    });
    aplica();
  }

  window.S1Menu = { render: render, idioma: idioma };
})();
