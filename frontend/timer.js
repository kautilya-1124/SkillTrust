(function () {
    function start(config) {
        if (!config || !config.state || typeof config.onTick !== 'function') {
            return;
        }

        const state = config.state;
        clearInterval(state.timerInterval);

        config.onTick(state.timeLeft);
        state.timerInterval = setInterval(function () {
            state.timeLeft = Math.max(0, state.timeLeft - 1);
            config.onTick(state.timeLeft);
            if (typeof config.onPersist === 'function') {
                config.onPersist();
            }
            if (state.timeLeft <= 0) {
                clearInterval(state.timerInterval);
                if (typeof config.onExpire === 'function') {
                    config.onExpire();
                }
            }
        }, 1000);
    }

    window.SkillTrustTimer = {
        start: start
    };
})();
