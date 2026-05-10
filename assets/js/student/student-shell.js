(function () {
    var shellPages = {
        'dashboard.php': true,
        'tests.php': true,
        'jobs.php': true,
        'applied_jobs.php': true,
        'results.php': true,
        'profile.php': true
    };

    function isModifiedEvent(event) {
        return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
    }

    function getPageName(url) {
        var pathname = url.pathname || '';
        var clean = pathname.split('/').pop() || '';
        return clean;
    }

    function parseHtml(html) {
        return new window.DOMParser().parseFromString(html, 'text/html');
    }

    function cloneScript(script) {
        var next = document.createElement('script');
        for (var i = 0; i < script.attributes.length; i += 1) {
            var attr = script.attributes[i];
            next.setAttribute(attr.name, attr.value);
        }
        if (!script.src) {
            next.textContent = script.textContent;
        }
        return next;
    }

    function getManagedHeadNodes(doc) {
        var nodes = [];
        var headNodes = doc.head ? doc.head.children : [];
        for (var i = 0; i < headNodes.length; i += 1) {
            var node = headNodes[i];
            var tag = node.tagName;
            if (tag === 'LINK') {
                var href = node.getAttribute('href') || '';
                if (node.getAttribute('rel') === 'stylesheet' && /assets\/css\/student\/|assets\/css\/theme-overrides\.css/.test(href)) {
                    nodes.push(node);
                }
            } else if (tag === 'STYLE') {
                nodes.push(node);
            }
        }
        return nodes;
    }

    function waitForStyles(nodes) {
        var pending = [];
        for (var i = 0; i < nodes.length; i += 1) {
            if (nodes[i].tagName === 'LINK') {
                pending.push((function (link) {
                    return new Promise(function (resolve) {
                        var done = false;
                        function finish() {
                            if (done) {
                                return;
                            }
                            done = true;
                            resolve();
                        }
                        link.addEventListener('load', finish, { once: true });
                        link.addEventListener('error', finish, { once: true });
                        window.setTimeout(finish, 1200);
                    });
                }(nodes[i])));
            }
        }
        return Promise.all(pending);
    }

    function syncHead(nextDoc) {
        var currentManaged = document.head.querySelectorAll('[data-student-soft-head="1"]');
        for (var i = 0; i < currentManaged.length; i += 1) {
            currentManaged[i].parentNode.removeChild(currentManaged[i]);
        }

        var nodes = getManagedHeadNodes(nextDoc);
        var appended = [];
        for (var j = 0; j < nodes.length; j += 1) {
            var clone = nodes[j].cloneNode(true);
            clone.setAttribute('data-student-soft-head', '1');
            document.head.appendChild(clone);
            appended.push(clone);
        }
        return waitForStyles(appended);
    }

    function syncBody(nextDoc) {
        var nextBody = nextDoc.body;
        if (!nextBody) {
            return;
        }

        document.title = nextDoc.title || document.title;
        document.body.className = nextBody.className;
        document.body.innerHTML = nextBody.innerHTML;
        document.body.classList.add('student-shell-page');
    }

    function runBodyScripts() {
        var scripts = Array.prototype.slice.call(document.body.querySelectorAll('script'));
        for (var i = 0; i < scripts.length; i += 1) {
            var replacement = cloneScript(scripts[i]);
            scripts[i].parentNode.replaceChild(replacement, scripts[i]);
        }
    }

    function finalizeNavigation() {
        markReady();
        document.documentElement.classList.remove('student-soft-loading');
        document.dispatchEvent(new Event('DOMContentLoaded', { bubbles: true }));
        window.dispatchEvent(new Event('load'));
    }

    function isShellLink(link) {
        if (!link || !link.href || link.target === '_blank' || link.hasAttribute('download')) {
            return false;
        }

        var url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return false;
        }

        if (url.origin !== window.location.origin) {
            return false;
        }

        if (!/\/student\//.test(url.pathname)) {
            return false;
        }

        if (!shellPages[getPageName(url)]) {
            return false;
        }

        return true;
    }

    function markReady() {
        document.documentElement.classList.add('student-shell-ready');
        if (document.body) {
            document.body.classList.add('student-shell-page');
        }
    }

    function navigateSoft(url, replaceState) {
        document.documentElement.classList.add('student-soft-loading');

        return window.fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'SkillTrustStudentShell'
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Navigation failed');
            }
            return response.text();
        }).then(function (html) {
            var nextDoc = parseHtml(html);
            return syncHead(nextDoc).then(function () {
                syncBody(nextDoc);
                if (replaceState) {
                    window.history.replaceState({ soft: true }, '', url);
                } else {
                    window.history.pushState({ soft: true }, '', url);
                }
                runBodyScripts();
                finalizeNavigation();
            });
        }).catch(function () {
            window.location.href = url;
        });
    }

    window.addEventListener('pageshow', function () {
        document.documentElement.classList.remove('student-soft-loading');
        markReady();
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || isModifiedEvent(event) || !isShellLink(link)) {
            return;
        }

        var url = new URL(link.href, window.location.href);
        if (url.href === window.location.href) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        navigateSoft(url.href, false);
    });

    window.addEventListener('popstate', function () {
        navigateSoft(window.location.href, true);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markReady, { once: true });
    } else {
        markReady();
    }
}());
