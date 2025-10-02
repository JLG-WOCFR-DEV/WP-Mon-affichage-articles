const { TextDecoder, TextEncoder } = require('util');

global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder;

const { JSDOM } = require('jsdom');

describe('assets/js/load-more.js REST interactions', () => {
    let $;
    let replaceStateSpy;

    beforeEach(() => {
        jest.resetModules();

        const dom = new JSDOM('<!doctype html><html><body></body></html>');
        global.window = dom.window;
        global.document = dom.window.document;
        global.Node = dom.window.Node;

        $ = require('jquery');
        global.$ = $;
        global.jQuery = $;
        window.$ = $;
        window.jQuery = $;

        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="7">
                <div class="my-articles-grid-content">
                    <article class="my-article-item">Article initial</article>
                </div>
                <div class="my-articles-load-more-container">
                    <button class="my-articles-load-more-btn" data-instance-id="7" data-paged="2" data-total-pages="3" data-pinned-ids="1" data-category="news">Charger plus</button>
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
            // Ignore if jsdom prevents redefinition.
        }
        replaceStateSpy = jest.spyOn(window.history, 'replaceState').mockImplementation(() => {});

        global.myArticlesLoadMore = {
            rest_root: 'https://example.com/wp-json',
            nonce: 'load-nonce',
            loadMoreText: 'Charger plus',
            loadingText: 'Chargement…',
            errorText: 'Erreur de chargement.'
        };

        global.myArticlesFilter = {
            errorText: 'Erreur',
            countSingle: '%s article affiché.',
            countPlural: '%s articles affichés.'
        };

        global.myArticlesInitWrappers = jest.fn();
        global.myArticlesInitSwipers = jest.fn();

        $.ajax = jest.fn((options) => {
            expect(options.url).toBe('https://example.com/wp-json/my-articles/v1/load-more');
            expect(options.headers['X-WP-Nonce']).toBe('load-nonce');
            expect(options.data.instance_id).toBe(7);
            expect(options.data.paged).toBe(2);
            expect(options.data.pinned_ids).toBe(1);
            expect(options.data.category).toBe('news');

            options.beforeSend();

            options.success({
                success: true,
                data: {
                    html: '<article class="my-article-item">Article chargé</article>',
                    pinned_ids: '1,3',
                    total_pages: 3,
                    next_page: 3
                }
            });

            options.complete();

            return Promise.resolve({
                then: () => {}
            });
        });

        require('../load-more.js');
    });

    afterEach(() => {
        if (replaceStateSpy) {
            replaceStateSpy.mockRestore();
        }
        delete global.myArticlesLoadMore;
        delete global.myArticlesFilter;
        delete global.myArticlesInitWrappers;
        delete global.myArticlesInitSwipers;
        delete global.window;
        delete global.document;
        delete global.Node;
        delete global.$;
        delete global.jQuery;
    });

    it('calls REST endpoint, appends new markup and updates pagination state', () => {
        const button = document.querySelector('.my-articles-load-more-btn');
        button.click();

        expect($.ajax).toHaveBeenCalledTimes(1);

        const articles = document.querySelectorAll('.my-article-item');
        expect(articles.length).toBe(2);
        expect(articles[1].textContent).toBe('Article chargé');

        expect(button.getAttribute('data-paged')).toBe('3');
        expect(button.getAttribute('data-pinned-ids')).toBe('1,3');

        const feedback = document.querySelector('.my-articles-feedback');
        expect(feedback.textContent).toContain('articles');

        expect(replaceStateSpy).toHaveBeenCalled();
    });
});
