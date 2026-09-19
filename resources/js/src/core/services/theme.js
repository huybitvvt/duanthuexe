const themeKey = "himoto-theme";

export function applyTheme(theme) {
  const value = theme === "dark" ? "dark" : "light";
  document.documentElement.setAttribute("data-theme", value);
  try { localStorage.setItem(themeKey, value); } catch (_) { /* Storage may be disabled. */ }
  return value;
}

export function initTheme() {
  let theme = "light";
  try { theme = localStorage.getItem(themeKey) || theme; } catch (_) { /* Keep light default. */ }
  return applyTheme(theme);
}
