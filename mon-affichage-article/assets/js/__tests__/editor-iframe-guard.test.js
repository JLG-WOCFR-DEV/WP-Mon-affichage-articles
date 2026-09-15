describe('Editor iframe guard', () => {
    const setupFilterDom = () => {
        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="42" data-sort="date" data-sort-param="my_articles_sort_42" data-results-target="my-articles-results-42" aria-busy="false">
                <ul class="my-articles-filter-nav" role="tablist">
                    <li class="active" role="presentation"><button role="tab" id="my-articles-tab-42-all" data-category="all" aria-controls="my-articles-results-42" aria-selected="true" tabindex="0">Tous</button></li>
                    <li role="presentation"><button role="tab" id="my-articles-tab-42-news" data-category="news" aria-controls="my-articles-results-42" aria-selected="false" tabindex="-1">Actualités</button></li>
                </ul>
                <div id="my-articles-results-42" class="my-articles-results" data-my-articles-role="results" aria-live="polite" aria-busy="false">
                    <div class="my-articles-grid-content">
                        <article class="my-article-item">Initial</article>
                    </div>
                </div>
            </div>
        `;
    };

    afterEach(() => {
        document.body.innerHTML = '';
        document.body.className = '';
        delete window.MY_ARTICLES_IS_EDITOR;
        delete window.myArticlesShared;
        delete window.myArticlesFilter;
        delete window.myArticlesLoadMore;
        delete window.myArticlesInitWrappers;
        delete window.myArticlesInitSwipers;
        delete window.myArticlesRefreshAutoLoadButtons;
        delete global.$;
        delete global.jQuery;
        delete window.$;
        delete window.jQuery;
        delete window.frameElement;
        jest.resetModules();
    });

    const loadShared = () => require('../shared-runtime');

    test('detects the editor flag, iframe body class and canvas frame', () => {
        const shared = loadShared();

        expect(shared.isEditorCanvas()).toBe(false);

        window.MY_ARTICLES_IS_EDITOR = true;
        expect(shared.isEditorCanvas()).toBe(true);
        delete window.MY_ARTICLES_IS_EDITOR;

        document.body.classList.add('block-editor-iframe__body');
        expect(shared.isEditorCanvas()).toBe(true);
        document.body.className = '';

        document.body.innerHTML = '<div data-my-articles-editor="1"></div>';
        expect(shared.isEditorCanvas()).toBe(true);
        document.body.innerHTML = '';

        Object.defineProperty(window, 'frameElement', {
            configurable: true,
            value: {
                getAttribute: () => 'editor-canvas',
                className: 'editor-canvas__iframe',
            },
        });
        expect(shared.isEditorCanvas()).toBe(true);
    });

    test('does not bind filter clicks when the editor flag is set', () => {
        setupFilterDom();
        window.MY_ARTICLES_IS_EDITOR = true;
        window.myArticlesFilter = {
            restRoot: 'http://example.com/wp-json',
            restNonce: 'nonce-123',
            nonceEndpoint: 'http://example.com/wp-json/my-articles/v1/nonce',
            errorText: 'Une erreur est survenue.',
        };

        const $ = require('jquery');
        global.$ = global.jQuery = $;
        window.$ = window.jQuery = $;
        $.ajax = jest.fn();

        require('../filter');

        $('.my-articles-filter-nav li').eq(1).find('button').trigger('click');
        expect($.ajax).not.toHaveBeenCalled();
    });

    test('does not bind load-more clicks when the iframed canvas body class is set', () => {
        document.body.classList.add('block-editor-iframe__body');
        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="42">
                <button class="my-articles-load-more-btn" data-instance-id="42" data-paged="2" data-total-pages="4">Charger plus</button>
            </div>
        `;
        window.myArticlesLoadMore = {
            restRoot: 'http://example.com/wp-json',
            restNonce: 'nonce-456',
            nonceEndpoint: 'http://example.com/wp-json/my-articles/v1/nonce',
            loadMoreText: 'Charger plus',
            loadingText: 'Chargement…',
            errorText: 'Impossible de charger plus.',
        };

        const $ = require('jquery');
        global.$ = global.jQuery = $;
        window.$ = window.jQuery = $;
        $.ajax = jest.fn();

        require('../load-more');

        $('.my-articles-load-more-btn').trigger('click');
        expect($.ajax).not.toHaveBeenCalled();
    });

    test('skips automatic layout and swiper boot in the editor canvas', () => {
        window.MY_ARTICLES_IS_EDITOR = true;
        document.body.innerHTML = '<div class="my-articles-wrapper" data-instance-id="7"></div>';

        require('../responsive-layout');
        const swiper = require('../swiper-init');

        expect(typeof window.myArticlesInitWrappers).toBe('function');
        expect(typeof swiper.initSwipers).toBe('function');
        expect(document.querySelector('.my-articles-wrapper').__myArticlesResizeObserver).toBeUndefined();
    });
});
