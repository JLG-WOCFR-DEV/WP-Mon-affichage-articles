describe('load-more endpoint interactions', () => {
    let $;
    let fetchMock;
    let consoleErrorSpy;

    const setupDom = () => {
        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="42" data-sort="date">
                <div class="my-articles-grid-content">
                    <article class="my-article-item">Initial</article>
                </div>
                <div class="my-articles-load-more-container">
                    <button
                        class="my-articles-load-more-btn"
                        data-instance-id="42"
                        data-paged="2"
                        data-total-pages="4"
                        data-pinned-ids=""
                        data-category="news"
                        data-sort="date"
                        data-auto-load="0"
                    >Charger plus</button>
                </div>
            </div>
        `;
    };

    const initGlobals = () => {
        window.myArticlesLoadMore = {
            restRoot: 'http://example.com/wp-json',
            restNonce: 'nonce-456',
            nonceEndpoint: 'http://example.com/wp-json/my-articles/v1/nonce',
            loadMoreText: 'Charger plus',
            loadingText: 'Chargement…',
            errorText: 'Impossible de charger plus.',
        };
        window.myArticlesFilter = { errorText: 'Erreur' };
        window.myArticlesInitWrappers = jest.fn();
        window.myArticlesInitSwipers = jest.fn();
    };

    beforeEach(() => {
        jest.resetModules();
        setupDom();
        initGlobals();

        $ = require('jquery');
        global.$ = global.jQuery = $;

        fetchMock = jest.fn(() => Promise.resolve());
        global.fetch = fetchMock;

        consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
        delete global.$;
        delete global.jQuery;
        delete window.myArticlesLoadMore;
        delete window.myArticlesFilter;
        delete window.myArticlesInitWrappers;
        delete window.myArticlesInitSwipers;
        delete window.myArticlesRefreshAutoLoadButtons;
        delete window.IntersectionObserver;
        delete window.fetch;
        if (consoleErrorSpy) {
            consoleErrorSpy.mockRestore();
        }
        jest.resetModules();
    });

    it('queues REST request with nonce and appends new markup on success', () => {
        let ajaxOptions;

        $.ajax = jest.fn((options) => {
            ajaxOptions = options;

            if (options.beforeSend) {
                options.beforeSend();
            }

            fetchMock(options.url, {
                method: options.type,
                headers: options.headers,
                body: options.data,
            });

            if (options.success) {
                options.success({
                    success: true,
                    data: {
                        html: '<article class="my-article-item">Nouveau</article>',
                        total_pages: 4,
                        next_page: 3,
                        pinned_ids: '10,11',
                        sort: 'comment_count',
                    },
                });
            }

            if (options.complete) {
                options.complete();
            }
        });

        require('../load-more');

        const button = $('.my-articles-load-more-btn');
        button.trigger('click');

        expect(ajaxOptions.url).toBe('http://example.com/wp-json/my-articles/v1/load-more');
        expect(ajaxOptions.headers['X-WP-Nonce']).toBe('nonce-456');
        expect(fetchMock).toHaveBeenCalledWith(
            'http://example.com/wp-json/my-articles/v1/load-more',
            expect.objectContaining({ method: 'POST' })
        );

        expect(ajaxOptions.data.sort).toBe('date');

        const articles = document.querySelectorAll('.my-articles-grid-content .my-article-item');
        expect(articles).toHaveLength(2);
        expect(articles[1].textContent).toBe('Nouveau');

        const feedback = document.querySelector('.my-articles-feedback');
        expect(feedback).not.toBeNull();
        expect(feedback.textContent).toMatch(/articles?/i);

        const buttonNode = document.querySelector('.my-articles-load-more-btn');
        expect(buttonNode.getAttribute('data-pinned-ids')).toBe('10,11');
        expect(buttonNode.getAttribute('data-paged')).toBe('3');
        expect(buttonNode.getAttribute('data-sort')).toBe('comment_count');

        const wrapper = document.querySelector('.my-articles-wrapper');
        expect(wrapper.getAttribute('data-sort')).toBe('comment_count');
    });

    it('displays server error messages when the request fails', () => {
        $.ajax = jest.fn((options) => {
            if (options.beforeSend) {
                options.beforeSend();
            }

            if (options.error) {
                options.error({
                    responseJSON: {
                        data: { message: 'Erreur serveur' },
                    },
                });
            }

            if (options.complete) {
                options.complete();
            }
        });

        require('../load-more');

        const button = $('.my-articles-load-more-btn');
        button.trigger('click');

        const feedback = document.querySelector('.my-articles-feedback');
        expect(feedback).not.toBeNull();
        expect(feedback.classList.contains('is-error')).toBe(true);
        expect(feedback.textContent).toBe('Erreur serveur');
    });

    it('installs an observer and triggers automatic loading when enabled', () => {
        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="21" data-sort="date">
                <div class="my-articles-grid-content">
                    <article class="my-article-item">Initial</article>
                </div>
                <div class="my-articles-load-more-container">
                    <button
                        class="my-articles-load-more-btn"
                        data-instance-id="21"
                        data-paged="2"
                        data-total-pages="3"
                        data-pinned-ids=""
                        data-category="news"
                        data-sort="date"
                        data-auto-load="1"
                    >Charger plus</button>
                </div>
            </div>
        `;

        const observers = [];
        window.IntersectionObserver = jest.fn(function (callback) {
            observers.push(callback);
            this.observe = jest.fn();
            this.disconnect = jest.fn();
        });

        $.ajax = jest.fn((options) => {
            if (options.beforeSend) {
                options.beforeSend();
            }

            if (options.success) {
                options.success({
                    success: true,
                    data: {
                        html: '<article class="my-article-item">Auto</article>',
                        total_pages: 3,
                        next_page: 3,
                        pinned_ids: '',
                        sort: 'date',
                    },
                });
            }

            if (options.complete) {
                options.complete();
            }
        });

        require('../load-more');

        expect(typeof window.myArticlesRefreshAutoLoadButtons).toBe('function');

        // Force initialization explicitly in case ready callbacks are delayed.
        window.myArticlesRefreshAutoLoadButtons(document);

        expect(window.IntersectionObserver).toHaveBeenCalled();

        const observerCallback = observers[0];
        const button = document.querySelector('.my-articles-load-more-btn');

        expect(observerCallback).toBeInstanceOf(Function);

        observerCallback([
            { isIntersecting: true, target: button },
        ]);

        expect($.ajax).toHaveBeenCalledTimes(1);

        const items = document.querySelectorAll('.my-articles-grid-content .my-article-item');
        expect(items).toHaveLength(2);
        expect(items[1].textContent).toBe('Auto');
    });

    it('refreshes the nonce and retries once before succeeding', () => {
        let postCallCount = 0;
        let nonceCallCount = 0;
        const postCalls = [];

        $.ajax = jest.fn((options) => {
            if (options.type === 'GET' && options.url.indexOf('/nonce') !== -1) {
                nonceCallCount += 1;

                if (options.success) {
                    options.success({ success: true, data: { nonce: 'nonce-updated' } });
                }

                if (options.complete) {
                    options.complete();
                }

                return;
            }

            if (options.type === 'POST') {
                postCallCount += 1;
                postCalls.push(options);

                if (options.beforeSend) {
                    options.beforeSend();
                }

                if (postCallCount === 1) {
                    if (options.error) {
                        options.error({
                            status: 403,
                            responseJSON: {
                                code: 'my_articles_invalid_nonce',
                                data: { status: 403 },
                            },
                        });
                    }
                } else {
                    if (options.success) {
                        options.success({
                            success: true,
                            data: {
                                html: '<article class="my-article-item">Recharge</article>',
                                total_pages: 4,
                                next_page: 3,
                                pinned_ids: '21,22',
                            },
                        });
                    }
                }

                if (options.complete) {
                    options.complete();
                }
            }
        });

        require('../load-more');

        const button = $('.my-articles-load-more-btn');
        button.trigger('click');

        expect(postCallCount).toBe(2);
        expect(nonceCallCount).toBe(1);
        expect(window.myArticlesLoadMore.restNonce).toBe('nonce-updated');
        expect(window.myArticlesFilter.restNonce).toBe('nonce-updated');

        expect(postCalls[0].headers['X-WP-Nonce']).toBe('nonce-456');
        expect(postCalls[1].headers['X-WP-Nonce']).toBe('nonce-updated');

        const articles = document.querySelectorAll('.my-articles-grid-content .my-article-item');
        expect(articles).toHaveLength(2);
        expect(articles[1].textContent).toBe('Recharge');

        const feedback = document.querySelector('.my-articles-feedback');
        expect(feedback).not.toBeNull();
        expect(feedback.classList.contains('is-error')).toBe(false);
    });
});
