(function () {
    function requestFullscreen() {
        const element = document.documentElement;
        if (document.fullscreenElement || !element || !element.requestFullscreen) {
            return Promise.resolve();
        }
        return element.requestFullscreen();
    }

    function init(config) {
        if (!config || !config.state || !config.requireFullscreen) {
            return;
        }

        let hasEnteredFullscreen = false;

        function promptFullscreen() {
            requestFullscreen()
                .then(function () {
                    hasEnteredFullscreen = true;
                    if (typeof config.onBanner === 'function') {
                        config.onBanner('Fullscreen mode is active. Stay in fullscreen until you finish the test.');
                    }
                })
                .catch(function () {
                    if (typeof config.onBanner === 'function') {
                        config.onBanner('Click anywhere on the page to re-enter fullscreen mode and continue the test.');
                    }
                });
        }

        document.addEventListener('fullscreenchange', function () {
            if (document.fullscreenElement) {
                hasEnteredFullscreen = true;
                return;
            }
            if (!config.state.submitted && hasEnteredFullscreen && typeof config.onViolation === 'function') {
                config.onViolation('Fullscreen mode was exited.', 'fullscreen_exit');
            }
        });

        document.addEventListener('click', function handleFirstClick() {
            if (config.state.submitted || document.fullscreenElement) {
                return;
            }
            promptFullscreen();
        }, { passive: true });

        promptFullscreen();
    }

    window.SkillTrustFullscreen = {
        init: init
    };
})();
