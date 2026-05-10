(function () {
    function shouldIgnoreTarget(target) {
        if (!target) {
            return false;
        }
        const tagName = (target.tagName || '').toUpperCase();
        return tagName === 'INPUT' || tagName === 'TEXTAREA' || target.isContentEditable === true;
    }

    function init(config) {
        if (!config || !config.state) {
            return;
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden && !config.state.submitted && typeof config.onViolation === 'function') {
                config.onViolation('You switched away from the test tab.', 'tab_switch');
            }
        });

        document.addEventListener('contextmenu', function (event) {
            event.preventDefault();
        });

        document.addEventListener('copy', function (event) {
            event.preventDefault();
        });

        document.addEventListener('paste', function (event) {
            event.preventDefault();
        });

        document.addEventListener('keydown', function (event) {
            const key = (event.key || '').toLowerCase();
            const ctrlOrCmd = event.ctrlKey || event.metaKey;
            const blockedDevShortcut =
                event.key === 'F12' ||
                (ctrlOrCmd && event.shiftKey && ['i', 'j', 'c'].includes(key)) ||
                (ctrlOrCmd && ['u', 's'].includes(key));

            if (blockedDevShortcut) {
                event.preventDefault();
                return;
            }

            if ((event.ctrlKey || event.metaKey) && ['c', 'v', 'x'].includes(key) && !shouldIgnoreTarget(event.target)) {
                event.preventDefault();
            }
        });

        if (typeof config.onBanner === 'function') {
            config.onBanner('Anti-cheat is active. Tab switches and fullscreen exits will trigger warnings.');
        }
    }

    window.SkillTrustAntiCheat = {
        init: init
    };
})();
