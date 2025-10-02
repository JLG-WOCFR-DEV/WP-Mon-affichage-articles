const { TextDecoder, TextEncoder } = require('util');

global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder;

const { JSDOM } = require('jsdom');

describe('assets/js/filter.js REST interactions', () => {
    let $;
    let replaceStateSpy;

    beforeEach(() => {
        jest.resetModules();

        const dom = new JSDOM('<!doctype html><html><body></body></html>');
        global.window = dom.window;
        global.document = dom.window.document;
        global.Node = dom.window.Node;

        $ = require('jquery');
        global.jQuery = $;
        global.$ = $;
        window.jQuery = $;
        window.$ = $;

        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="42">
                <nav class="my-articles-filter-nav">
                    <ul>
                        <li class="active"><button type="button" data-category="all">All</button></li>
                        <li><button type="button" class="my-articles-filter-link" data-category="news">News</button></li>
                    </ul>
                </nav>
                <div class="my-articles-grid-content">
                    <article class="my-article-item">Existing</article>
                </div>
                <div class="my-articles-load-more-container">
                    <button class="my-articles-load-more-btn" data-instance-id="42" data-paged="2" data-total-pages="2" data-pinned-ids="" data-category="all">Load more</button>
                </div>
            </div>
        `;

        try {
            Object.defineProperty(window.location, 'href', {
                configurable: true,
                writable: true,
                value: 'https://example.com/articles/?paged=1',
            });
        } catch (error) {
            // jsdom may disallow redefining the property; ignore in that case.
        }
        replaceStateSpy = jest.spyOn(window.history, 'replaceState').mockImplementation(() => {});

        global.myArticlesFilter = {
            rest_root: 'https://example.com/wp-json',
            nonce: 'filter-nonce',
            errorText: 'Une erreur est survenue.'
        };

        global.myArticlesLoadMore = {
            loadMoreText: 'Charger plus',
            nonce: 'load-nonce'
        };

        global.myArticlesInitWrappers = jest.fn();
        global.myArticlesInitSwipers = jest.fn();

        $.ajax = jest.fn((options) => {
            options.beforeSend();

            options.success({
                success: true,
                data: {
                    html: '<article class="my-article-item">Nouvel article</article>',
                    total_pages: 1,
                    next_page: 0,
                    pinned_ids: '',
                    pagination_html: ''
                }
            });

            options.complete();

            return Promise.resolve({
                then: () => {}
            });
        });

        require('../filter.js');
    });

    afterEach(() => {
        if (replaceStateSpy) {
            replaceStateSpy.mockRestore();
        }
        delete global.myArticlesFilter;
        delete global.myArticlesLoadMore;
        delete global.myArticlesInitWrappers;
        delete global.myArticlesInitSwipers;
        delete global.window;
        delete global.document;
        delete global.Node;
        delete global.$;
        delete global.jQuery;
    });

    it('builds REST URL, injects nonce header and updates markup from response', () => {
        const target = document.querySelector('.my-articles-filter-nav button[data-category="news"]');
        target.click();

        expect($.ajax).toHaveBeenCalledTimes(1);
        const ajaxOptions = $.ajax.mock.calls[0][0];
        expect(ajaxOptions.url).toBe('https://example.com/wp-json/my-articles/v1/filter');
        expect(ajaxOptions.headers['X-WP-Nonce']).toBe('filter-nonce');

        const contentArea = document.querySelector('.my-articles-grid-content');
        expect(contentArea.innerHTML).toContain('Nouvel article');

        const loadMoreContainer = document.querySelector('.my-articles-load-more-container');
        expect(loadMoreContainer).toBeNull();

        const feedback = document.querySelector('.my-articles-feedback');
        expect(feedback.textContent).toContain('article');

        expect(replaceStateSpy).toHaveBeenCalled();
    });
});
