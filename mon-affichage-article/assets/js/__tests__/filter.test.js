const $ = require('jquery');

global.$ = $;
global.jQuery = $;

describe('filter.js', () => {
    let filterModule;

    beforeEach(() => {
        jest.resetModules();
        document.body.innerHTML = '';
        $(document).off('click', '.my-articles-filter-nav button, .my-articles-filter-nav a');
        global.myArticlesFilter = {
            ajax_url: '/fake-endpoint',
            nonce: 'nonce-value',
            errorText: 'Erreur générique'
        };
        global.myArticlesLoadMore = {
            loadMoreText: 'Charger plus'
        };
        $.ajax = jest.fn();

        filterModule = require('../filter.js');
    });

    afterEach(() => {
        delete global.myArticlesFilter;
        delete global.myArticlesLoadMore;
        $(document).off('click', '.my-articles-filter-nav button, .my-articles-filter-nav a');
        jest.restoreAllMocks();
    });

    test('buildFilterFeedbackMessage returns expected labels', () => {
        const { buildFilterFeedbackMessage } = filterModule;

        expect(buildFilterFeedbackMessage(0)).toBe('Aucun article à afficher.');
        expect(buildFilterFeedbackMessage(1)).toBe('1 article affiché.');
        expect(buildFilterFeedbackMessage(5)).toBe('5 articles affichés.');
    });

    test('updateInstanceQueryParams updates URL parameters', () => {
        const { updateInstanceQueryParams } = filterModule;

        const mockReplace = jest.fn();
        const mockWindow = {
            location: { href: 'https://example.com/?foo=bar' },
            history: { replaceState: mockReplace }
        };

        updateInstanceQueryParams('123', { 'my_articles_cat_123': 'news', remove: null }, mockWindow);

        expect(mockReplace).toHaveBeenCalledWith(null, '', 'https://example.com/?foo=bar&my_articles_cat_123=news');
    });

    test('clicking a filter updates content, feedback and history', () => {
        jest.spyOn(window.history, 'replaceState');
        window.history.replaceState({}, '', '/?initial=1');

        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="123">
                <div class="my-articles-filter-nav">
                    <ul>
                        <li class="active"><button data-category="all" aria-pressed="true">Tous</button></li>
                        <li><button data-category="news">Actualités</button></li>
                    </ul>
                </div>
                <div class="my-articles-grid-content">
                    <article class="my-article-item">Ancien article</article>
                </div>
                <div class="my-articles-load-more-container">
                    <button class="my-articles-load-more-btn" data-instance-id="123" data-paged="2" data-total-pages="3" data-pinned-ids="" data-category="">
                        Charger plus
                    </button>
                </div>
            </div>
        `;

        const wrapper = $('.my-articles-wrapper');
        const contentArea = wrapper.find('.my-articles-grid-content');
        const targetButton = wrapper.find('.my-articles-filter-nav li').eq(1).find('button');

        $.ajax.mockImplementation((options) => {
            if (options.beforeSend) {
                options.beforeSend();
            }

            const response = {
                success: true,
                data: {
                    html: '<article class="my-article-item">Article 1</article><article class="my-article-item">Article 2</article>',
                    total_pages: 3,
                    next_page: 2,
                    pinned_ids: '10,20'
                }
            };

            if (options.success) {
                options.success(response);
            }

            return Promise.resolve(response);
        });

        targetButton.trigger('click');

        expect($.ajax).toHaveBeenCalled();
        expect(wrapper.attr('aria-busy')).toBe('false');
        expect(contentArea.attr('aria-busy')).toBe('false');

        const feedback = wrapper.find('.my-articles-feedback');
        expect(feedback.text()).toBe('2 articles affichés.');

        const loadMoreBtn = wrapper.find('.my-articles-load-more-btn');
        expect(loadMoreBtn.attr('data-category')).toBe('news');
        expect(loadMoreBtn.prop('disabled')).toBe(false);
        expect(loadMoreBtn.attr('data-paged')).toBe('2');

        const activeItem = wrapper.find('.my-articles-filter-nav li').eq(1);
        expect(activeItem.hasClass('active')).toBe(true);
        expect(activeItem.find('button').attr('aria-pressed')).toBe('true');

        expect(window.history.replaceState).toHaveBeenCalled();
    });
});
