describe('slideshow utilities', () => {
    afterEach(() => {
        jest.resetModules();
        delete global.Swiper;
        if (typeof window !== 'undefined') {
            Object.keys(window)
                .filter((key) => key.indexOf('myArticlesSwiperSettings_') === 0)
                .forEach((key) => {
                    delete window[key];
                });
            delete window.mySwiperInstances;
        }
    });

    it('ignores undefined neighbouring slides without throwing', () => {
        jest.resetModules();
        const { preloadNeighbouringSlides } = require('../swiper-init');

        const neighbour = {
            dataset: {},
            querySelectorAll: jest.fn(() => []),
        };

        const swiperMock = {
            slides: [
                {
                    dataset: {},
                    querySelectorAll: jest.fn(() => []),
                },
                neighbour,
            ],
            activeIndex: 0,
        };

        expect(() => {
            preloadNeighbouringSlides(swiperMock);
        }).not.toThrow();

        expect(neighbour.querySelectorAll).toHaveBeenCalledWith('[data-src], [data-srcset], [data-background-image]');
    });

    it('configures keyboard navigation and accessibility messages on init', () => {
        document.body.innerHTML = '';
        const wrapper = document.createElement('div');
        wrapper.id = 'my-articles-wrapper-123';
        wrapper.className = 'my-articles-wrapper my-articles-slideshow';
        wrapper.dataset.instanceId = '123';

        const container = document.createElement('div');
        container.className = 'swiper-container';
        wrapper.appendChild(container);
        document.body.appendChild(wrapper);

        const swiperInstance = { destroy: jest.fn(), slides: [] };
        const swiperConstructor = jest.fn(() => swiperInstance);
        global.Swiper = swiperConstructor;

        window.myArticlesSwiperSettings_123 = {
            columns_mobile: 1,
            columns_tablet: 2,
            columns_desktop: 3,
            columns_ultrawide: 4,
            gap_size: 16,
            container_selector: '#my-articles-wrapper-123 .swiper-container',
            a11y_prev_slide_message: 'Précédente',
            a11y_next_slide_message: 'Suivante',
            a11y_first_slide_message: 'Première',
            a11y_last_slide_message: 'Dernière',
            a11y_pagination_bullet_message: 'Aller à {{index}}',
            a11y_slide_label_message: 'Diapositive {{index}}/{{slidesLength}}',
            a11y_container_message: 'Navigation clavier activée',
            a11y_container_role_description: 'Carrousel',
            a11y_item_role_description: 'Diapositive',
        };

        jest.resetModules();
        const { initSwiperForWrapper } = require('../swiper-init');
        const result = initSwiperForWrapper(wrapper);

        expect(result).toBe(swiperInstance);
        expect(swiperConstructor).toHaveBeenCalledWith('#my-articles-wrapper-123 .swiper-container', expect.objectContaining({
            keyboard: { enabled: true, onlyInViewport: true },
            watchOverflow: true,
            a11y: expect.objectContaining({
                enabled: true,
                prevSlideMessage: 'Précédente',
                nextSlideMessage: 'Suivante',
                firstSlideMessage: 'Première',
                lastSlideMessage: 'Dernière',
                paginationBulletMessage: 'Aller à {{index}}',
                slideLabelMessage: 'Diapositive {{index}}/{{slidesLength}}',
                containerMessage: 'Navigation clavier activée',
                containerRoleDescriptionMessage: 'Carrousel',
                itemRoleDescriptionMessage: 'Diapositive',
            }),
        }));
    });
});
