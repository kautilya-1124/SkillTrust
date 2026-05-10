(function () {
    var storageKey = 'skilltrust-theme';
    var lightClass = 'skilltrust-light';
    var darkClass = 'dark';
    var buttonSelector = '[data-theme-toggle], #themeToggle, #skilltrustThemeToggle';

    function readStoredTheme() {
        try {
            var stored = window.localStorage.getItem(storageKey);
            return stored === 'light' || stored === 'dark' ? stored : null;
        } catch (error) {
            return null;
        }
    }

    function getSystemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function getActiveTheme() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    }

    function persistTheme(theme) {
        try {
            window.localStorage.setItem(storageKey, theme);
        } catch (error) {
            // Ignore storage failures and keep the selected theme in memory.
        }
    }

    function applyTheme(theme) {
        var isLight = theme === 'light';
        var root = document.documentElement;
        root.setAttribute('data-theme', isLight ? 'light' : 'dark');
        root.classList.toggle(lightClass, isLight);
        root.classList.toggle(darkClass, !isLight);
        root.style.colorScheme = isLight ? 'light' : 'dark';
    }

    function syncLucideIcon(iconElement, theme) {
        if (!iconElement) {
            return;
        }

        var iconName = theme === 'light' ? 'sun-medium' : 'moon-star';
        if (iconElement.hasAttribute('data-lucide')) {
            iconElement.setAttribute('data-lucide', iconName);
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
            return;
        }

        iconElement.textContent = theme === 'light' ? '\u2600' : '\u263E';
    }

    function ensureToggleParts(button) {
        var icon = button.querySelector('[data-theme-icon], .skilltrust-theme-icon, i[data-lucide], .lucide');
        var label = button.querySelector('[data-theme-label], #themeLabel, #themeToggleLabel, .skilltrust-theme-text');

        if (!icon) {
            icon = document.createElement('span');
            icon.className = 'skilltrust-theme-icon';
            icon.setAttribute('data-theme-icon', 'true');
            button.insertBefore(icon, button.firstChild);
        } else if (!icon.classList.contains('skilltrust-theme-icon')) {
            icon.classList.add('skilltrust-theme-icon');
        }

        if (!label) {
            label = document.createElement('span');
            label.className = 'skilltrust-theme-text';
            label.setAttribute('data-theme-label', 'true');
            button.appendChild(label);
        } else if (!label.classList.contains('skilltrust-theme-text')) {
            label.classList.add('skilltrust-theme-text');
        }

        return { icon: icon, label: label };
    }

    function syncButton(button) {
        var parts = ensureToggleParts(button);
        var theme = getActiveTheme();
        var nextTheme = theme === 'dark' ? 'light' : 'dark';
        parts.label.textContent = nextTheme === 'light' ? 'Light mode' : 'Dark mode';
        syncLucideIcon(parts.icon, nextTheme);
        button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        button.setAttribute('aria-label', nextTheme === 'light' ? 'Switch to light theme' : 'Switch to dark theme');
        button.setAttribute('title', nextTheme === 'light' ? 'Switch to light theme' : 'Switch to dark theme');
    }

    function syncAllButtons() {
        var buttons = document.querySelectorAll(buttonSelector);
        for (var index = 0; index < buttons.length; index += 1) {
            syncButton(buttons[index]);
        }
    }

    function toggleTheme() {
        var nextTheme = getActiveTheme() === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
        persistTheme(nextTheme);
        syncAllButtons();
    }

    function bindToggle(button) {
        if (!button || button.dataset.themeBound === 'true') {
            return;
        }

        button.dataset.themeBound = 'true';
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            toggleTheme();
        }, true);
        syncButton(button);
    }

    function bindExistingToggles() {
        var buttons = document.querySelectorAll(buttonSelector);
        for (var index = 0; index < buttons.length; index += 1) {
            bindToggle(buttons[index]);
        }
        return buttons.length;
    }

    function mountFloatingToggle() {
        if (document.getElementById('skilltrustThemeToggle')) {
            return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.id = 'skilltrustThemeToggle';
        button.className = 'skilltrust-theme-toggle';
        button.setAttribute('data-theme-toggle', 'true');

        var icon = document.createElement('span');
        icon.className = 'skilltrust-theme-icon';
        icon.setAttribute('data-theme-icon', 'true');

        var label = document.createElement('span');
        label.className = 'skilltrust-theme-text';
        label.setAttribute('data-theme-label', 'true');

        button.appendChild(icon);
        button.appendChild(label);
        document.body.appendChild(button);
        bindToggle(button);
    }

    function handleSystemThemeChange(event) {
        if (readStoredTheme() !== null) {
            return;
        }

        applyTheme(event.matches ? 'dark' : 'light');
        syncAllButtons();
    }

    applyTheme(readStoredTheme() || getSystemTheme());
    window.toggleTheme = toggleTheme;

    if (window.matchMedia) {
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', handleSystemThemeChange);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(handleSystemThemeChange);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (bindExistingToggles() === 0) {
            mountFloatingToggle();
        } else {
            syncAllButtons();
        }
    });
}());
