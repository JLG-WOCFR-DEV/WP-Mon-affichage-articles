const $ = require('jquery');

global.$ = $;
global.jQuery = $;

describe('load-more.js', () => {
    let loadMoreModule;

    beforeEach(() => {
        jest.resetModules();
        document.body.innerHTML = '';
        $(document).off('click', '.my-articles-load-more-btn');
        global.myArticlesLoadMore = {
            ajax_url: '/fake-endpoint',
            nonce: 'nonce-value',
            errorText: 'Erreur générique',
            loadMoreText: 'Charger plus',
            loadingText: 'Chargement...'
        };
        $.ajax = jest.fn();

        loadMoreModule = require('../load-more.js');
    });

    afterEach(() => {
        delete global.myArticlesLoadMore;
        $(document).off('click', '.my-articles-load-more-btn');
        jest.restoreAllMocks();
    });

    test('buildLoadMoreFeedbackMessage returns expected labels', () => {
        const { buildLoadMoreFeedbackMessage } = loadMoreModule;

        expect(buildLoadMoreFeedbackMessage(0, 0)).toBe('Aucun article à afficher.');
        expect(buildLoadMoreFeedbackMessage(5, 2)).toBe('2 articles ajoutés. 5 articles affichés au total.');
        expect(buildLoadMoreFeedbackMessage(1, 0)).toBe('Aucun article supplémentaire. 1 article affiché au total.');
    });

    test('updateInstanceQueryParams updates URL parameters', () => {
        const { updateInstanceQueryParams } = loadMoreModule;

        const mockReplace = jest.fn();
        const mockWindow = {
            location: { href: 'https://example.com/?foo=bar' },
            history: { replaceState: mockReplace }
        };

        updateInstanceQueryParams('123', { 'paged_123': '3', foo: null }, mockWindow);

        expect(mockReplace).toHaveBeenCalledWith(null, '', 'https://example.com/?paged_123=3');
    });

    test('clicking load more appends content, updates feedback and hides button when finished', () => {
        jest.spyOn(window.history, 'replaceState');
        window.history.replaceState({}, '', '/?initial=1');

        document.body.innerHTML = `
            <div class="my-articles-wrapper" data-instance-id="123">
                <div class="my-articles-grid-content">
                    <article class="my-article-item">Article initial</article>
                </div>
                <div class="my-articles-load-more-container">
                    <button class="my-articles-load-more-btn"
                        data-instance-id="123"
                        data-paged="2"
                        data-total-pages="2"
                        data-pinned-ids=""
                        data-category="news">
                        Charger plus
                    </button>
                </div>
            </div>
        `;

        const wrapper = $('.my-articles-wrapper');
        const contentArea = wrapper.find('.my-articles-grid-content');
        const button = wrapper.find('.my-articles-load-more-btn');

        $.ajax.mockImplementation((options) => {
            if (options.beforeSend) {
                options.beforeSend();
                expect(button.attr('aria-busy')).toBe('true');
                expect(contentArea.attr('aria-busy')).toBe('true');
                expect(wrapper.attr('aria-busy')).toBe('true');
            }

            const response = {
                success: true,
                data: {
                    html: '<article class="my-article-item">Article ajouté</article>',
                    total_pages: 2,
                    next_page: 0,
                    pinned_ids: '10,20'
                }
            };

            if (options.success) {
                options.success(response);
            }

            return Promise.resolve(response);
        });

        button.trigger('click');

        expect($.ajax).toHaveBeenCalled();
        expect(wrapper.attr('aria-busy')).toBe('false');
        expect(contentArea.attr('aria-busy')).toBe('false');
        expect(button.attr('aria-busy')).toBe('false');

        const articles = contentArea.find('.my-article-item');
        expect(articles.length).toBe(2);
        expect(articles.eq(1).text()).toBe('Article ajouté');

        const feedback = wrapper.find('.my-articles-feedback');
        expect(feedback.text()).toBe('1 article ajouté. 2 articles affichés au total.');

        expect(button.css('display')).toBe('none');
        expect(button.prop('disabled')).toBe(false);
        expect(button.attr('data-pinned-ids')).toBe('10,20');

        expect(window.history.replaceState).toHaveBeenCalled();
    });
});
