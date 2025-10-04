describe('filter endpoint interactions', () => {
    let $;
    let fetchMock;

    const setupDom = () => {
        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="42">
                <ul class="my-articles-filter-nav">
                    <li class="active"><button data-category="all" aria-pressed="true">Tous</button></li>
                    <li><button data-category="news">Actualités</button></li>
                </ul>
                <div class="my-articles-grid-content">
                    <article class="my-article-item">Initial</article>
                </div>
            </div>
        `;
    };

    const initGlobals = () => {
        window.myArticlesFilter = {
            restRoot: 'http://example.com/wp-json',
            restNonce: 'nonce-123',
            nonceEndpoint: 'http://example.com/wp-json/my-articles/v1/nonce',
            errorText: 'Une erreur est survenue.',
        };
        window.myArticlesLoadMore = { loadMoreText: 'Charger plus' };
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
    });

    afterEach(() => {
        delete global.$;
        delete global.jQuery;
        delete window.myArticlesFilter;
        delete window.myArticlesLoadMore;
        delete window.myArticlesInitWrappers;
        delete window.myArticlesInitSwipers;
        delete window.fetch;
        jest.resetModules();
    });

    it('sends REST request with nonce and updates DOM on success', () => {
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
                        html: '<article class="my-article-item">Filtré</article>',
                        total_pages: 2,
                        next_page: 3,
                        pinned_ids: '5,7',
                        pagination_html: '<nav class="my-articles-pagination">Page 1</nav>',
                    },
                });
            }

            if (options.complete) {
                options.complete();
            }
        });

        require('../filter');

        const secondFilter = $('.my-articles-filter-nav li').eq(1).find('button');
        secondFilter.trigger('click');

        expect(ajaxOptions.url).toBe('http://example.com/wp-json/my-articles/v1/filter');
        expect(ajaxOptions.headers['X-WP-Nonce']).toBe('nonce-123');
        expect(fetchMock).toHaveBeenCalledWith(
            'http://example.com/wp-json/my-articles/v1/filter',
            expect.objectContaining({ method: 'POST' })
        );

        const contentHtml = document.querySelector('.my-articles-grid-content').innerHTML;
        expect(contentHtml).toContain('Filtré');

        const feedback = document.querySelector('.my-articles-feedback');
        expect(feedback).not.toBeNull();
        expect(feedback.textContent).toMatch(/articles?/i);

        const loadMoreButton = document.querySelector('.my-articles-load-more-btn');
        expect(loadMoreButton).not.toBeNull();
        expect(loadMoreButton.getAttribute('data-pinned-ids')).toBe('5,7');
    });

    it('shows API error messages when the request fails', () => {
        window.myArticlesFilter.errorText = 'Impossible de filtrer.';

        $.ajax = jest.fn((options) => {
            if (options.beforeSend) {
                options.beforeSend();
            }

            if (options.error) {
                options.error({
                    responseJSON: {
                        data: { message: 'Erreur REST' },
                    },
                });
            }

            if (options.complete) {
                options.complete();
            }
        });

        require('../filter');

        const secondFilter = $('.my-articles-filter-nav li').eq(1).find('button');
        secondFilter.trigger('click');

        const feedback = document.querySelector('.my-articles-feedback');
        expect(feedback).not.toBeNull();
        expect(feedback.classList.contains('is-error')).toBe(true);
        expect(feedback.textContent).toBe('Erreur REST');
    });

    it('refreshes the nonce once and retries the request on 403 errors', () => {
        let postCallCount = 0;
        let nonceCallCount = 0;
        const postCalls = [];

        $.ajax = jest.fn((options) => {
            if (options.type === 'GET' && options.url.indexOf('/nonce') !== -1) {
                nonceCallCount += 1;

                if (options.success) {
                    options.success({ success: true, data: { nonce: 'nonce-refreshed' } });
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
                                html: '<article class="my-article-item">Nouveau</article>',
                                total_pages: 1,
                                next_page: 0,
                                pinned_ids: '',
                            },
                        });
                    }
                }

                if (options.complete) {
                    options.complete();
                }
            }
        });

        require('../filter');

        const secondFilter = $('.my-articles-filter-nav li').eq(1).find('button');
        secondFilter.trigger('click');

        expect(postCallCount).toBe(2);
        expect(nonceCallCount).toBe(1);
        expect(window.myArticlesFilter.restNonce).toBe('nonce-refreshed');

        expect(postCalls[0].headers['X-WP-Nonce']).toBe('nonce-123');
        expect(postCalls[1].headers['X-WP-Nonce']).toBe('nonce-refreshed');

        const articles = document.querySelectorAll('.my-articles-grid-content .my-article-item');
        expect(articles).toHaveLength(1);
        expect(articles[0].textContent).toBe('Nouveau');

        const activeFilter = document.querySelector('.my-articles-filter-nav li.active');
        expect(activeFilter).not.toBeNull();
        expect(activeFilter.querySelector('button').dataset.category).toBe('news');
    });
});
