
(function(){
  "use strict";

  function initCivilinaTheme(){
    if (!document.body) return;

    document.body.classList.add("civ-theme");

    // Donne une signature visuelle aux actions principales sans casser
    // les classes existantes.
    document.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function(el){
      if (!el.classList.contains("btn") && !el.classList.contains("primary")) {
        el.classList.add("civ-action");
      }
    });

    // Tables : wrapper responsive seulement si elles débordent.
    document.querySelectorAll("table").forEach(function(table){
      if (table.parentElement && table.parentElement.classList.contains("civ-table-wrap")) return;

      var wrap = document.createElement("div");
      wrap.className = "civ-table-wrap";
      wrap.style.overflowX = "auto";
      wrap.style.borderRadius = "11px";
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    });

    // Petite signature de densité sur les écrans étroits.
    if (window.matchMedia("(max-width: 600px)").matches) {
      document.body.classList.add("civ-compact");
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initCivilinaTheme);
  } else {
    initCivilinaTheme();
  }
})();
