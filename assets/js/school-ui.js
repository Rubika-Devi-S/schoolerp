document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  const body = document.body;

  const sidebar = document.getElementById("sidebar");

  const toggleButton = document.getElementById("sidebarToggle");

  const closeButton = document.getElementById("sidebarMobileClose");

  const backdrop = document.getElementById("sidebarBackdrop");

  const desktopBreakpoint = 1200;

  function isDesktop() {
    return window.innerWidth >= desktopBreakpoint;
  }

  function refreshLucideIcons() {
    if (window.lucide && typeof window.lucide.createIcons === "function") {
      window.lucide.createIcons();
    }
  }

  function saveDesktopState(collapsed) {
    try {
      localStorage.setItem("school_sidebar_collapsed", collapsed ? "1" : "0");
    } catch (error) {
      console.warn("Unable to save sidebar state.", error);
    }
  }

  function readDesktopState() {
    try {
      return localStorage.getItem("school_sidebar_collapsed") === "1";
    } catch (error) {
      return false;
    }
  }

  function setDesktopCollapsed(collapsed) {
    body.classList.toggle("sidebar-collapsed", Boolean(collapsed));

    saveDesktopState(Boolean(collapsed));

    toggleButton?.setAttribute("aria-expanded", collapsed ? "false" : "true");

    toggleButton?.setAttribute(
      "title",
      collapsed ? "Expand sidebar" : "Collapse sidebar",
    );
  }

  function setMobileSidebar(open) {
    body.classList.toggle("sidebar-open", Boolean(open));

    toggleButton?.setAttribute("aria-expanded", open ? "true" : "false");
  }

  function closeMobileSidebar() {
    setMobileSidebar(false);
  }

  function handleToggle() {
    if (isDesktop()) {
      const shouldCollapse = !body.classList.contains("sidebar-collapsed");

      setDesktopCollapsed(shouldCollapse);
      return;
    }

    const shouldOpen = !body.classList.contains("sidebar-open");

    setMobileSidebar(shouldOpen);
  }

  /*
    |--------------------------------------------------------------------------
    | Restore desktop sidebar position
    |--------------------------------------------------------------------------
    */

  if (isDesktop()) {
    setDesktopCollapsed(readDesktopState());
  }

  /*
    |--------------------------------------------------------------------------
    | Main topbar sidebar toggle
    |--------------------------------------------------------------------------
    */

  toggleButton?.addEventListener("click", handleToggle);

  /*
    |--------------------------------------------------------------------------
    | Mobile close controls
    |--------------------------------------------------------------------------
    */

  closeButton?.addEventListener("click", closeMobileSidebar);

  backdrop?.addEventListener("click", closeMobileSidebar);

  /*
    |--------------------------------------------------------------------------
    | Close mobile sidebar after selecting a page
    |--------------------------------------------------------------------------
    */

  document
    .querySelectorAll('.sidebar-link[href]:not([href="#"])')
    .forEach(function (link) {
      link.addEventListener("click", function () {
        if (!isDesktop()) {
          closeMobileSidebar();
        }
      });
    });

  /*
    |--------------------------------------------------------------------------
    | Expand sidebar before opening a submenu
    |--------------------------------------------------------------------------
    */

  document.querySelectorAll(".sidebar-parent").forEach(function (parentButton) {
    parentButton.addEventListener(
      "click",
      function (event) {
        if (!isDesktop() || !body.classList.contains("sidebar-collapsed")) {
          return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        setDesktopCollapsed(false);

        const targetSelector = parentButton.getAttribute("data-bs-target");

        if (!targetSelector || !window.bootstrap) {
          return;
        }

        const submenu = document.querySelector(targetSelector);

        if (!submenu) {
          return;
        }

        window.setTimeout(function () {
          const collapse = bootstrap.Collapse.getOrCreateInstance(submenu, {
            toggle: false,
          });

          collapse.show();

          parentButton.setAttribute("aria-expanded", "true");
        }, 150);
      },
      true,
    );
  });

  /*
    |--------------------------------------------------------------------------
    | Resize handling
    |--------------------------------------------------------------------------
    */

  window.addEventListener("resize", function () {
    if (isDesktop()) {
      closeMobileSidebar();

      setDesktopCollapsed(readDesktopState());
    }
  });

  /*
    |--------------------------------------------------------------------------
    | Escape closes the mobile drawer
    |--------------------------------------------------------------------------
    */

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && body.classList.contains("sidebar-open")) {
      closeMobileSidebar();
    }
  });

  refreshLucideIcons();
});
