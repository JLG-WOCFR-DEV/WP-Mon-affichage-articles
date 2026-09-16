const fs = require('fs');
const path = require('path');

describe('Card hover stays CSS-only after Tuiles - JLG rename', () => {
    const jsDir = path.join(__dirname, '..');
    const cssPath = path.join(__dirname, '../../css/styles.css');
    const pluginJsFiles = fs
        .readdirSync(jsDir)
        .filter((name) => name.endsWith('.js'))
        .map((name) => path.join(jsDir, name));

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
        document.body.innerHTML = '';
    });

    it('does not bind mouseenter or mouseover on plugin scripts', () => {
        expect(pluginJsFiles.length).toBeGreaterThan(0);

        pluginJsFiles.forEach((file) => {
            const source = fs.readFileSync(file, 'utf8');
            expect(source).not.toContain("addEventListener('mouseenter'");
            expect(source).not.toContain('addEventListener("mouseenter"');
            expect(source).not.toContain("addEventListener('mouseover'");
            expect(source).not.toContain(".on('mouseenter'");
            expect(source).not.toContain('.my-article-item:hover');
        });
    });

    it('keeps front :hover lift, overlay and link rules in styles.css', () => {
        const css = fs.readFileSync(cssPath, 'utf8');

        expect(css).toContain('@media (hover: hover) and (pointer: fine)');
        expect(css).toContain('.my-articles-wrapper.my-articles-has-hover-lift .my-article-item:hover');
        expect(css).toContain('transform: translateY(var(--my-articles-hover-lift-offset, -6px));');
        expect(css).toContain('.my-article-item:hover .article-thumbnail-wrapper::after');
        expect(css).toContain('.my-article-item:hover .article-title-link::after');
        expect(css).toContain('.my-article-item:hover .article-meta');
        expect(css).not.toContain('Tuiles - L');
        expect(css).not.toContain('Tuiles – L');
    });

    it('passes pauseOnMouseEnter to Swiper and keeps pagination titles on the bullets', () => {
        const wrapper = document.createElement('div');
        wrapper.id = 'my-articles-wrapper-77';
        wrapper.className = 'my-articles-wrapper my-articles-slideshow my-articles-has-hover-lift';
        wrapper.dataset.instanceId = '77';

        const container = document.createElement('div');
        container.className = 'swiper-container';
        wrapper.appendChild(container);
        document.body.appendChild(wrapper);

        const swiperInstance = { destroy: jest.fn(), slides: [] };
        const swiperConstructor = jest.fn(() => swiperInstance);
        global.Swiper = swiperConstructor;

        window.myArticlesSwiperSettings_77 = {
            columns_mobile: 1,
            columns_tablet: 2,
            columns_desktop: 3,
            columns_ultrawide: 4,
            gap_size: 16,
            container_selector: '#my-articles-wrapper-77 .swiper-container',
            autoplay: {
                enabled: true,
                delay: 2500,
                pause_on_interaction: true,
                pause_on_mouse_enter: true,
            },
            a11y_pagination_bullet_message: 'Aller à {{index}}',
        };

        const { initSwiperForWrapper } = require('../swiper-init');
        initSwiperForWrapper(wrapper);

        const swiperConfig = swiperConstructor.mock.calls[0][1];
        expect(swiperConfig.autoplay).toEqual(
            expect.objectContaining({
                pauseOnMouseEnter: true,
                disableOnInteraction: true,
                delay: 2500,
            })
        );

        const paginationEl = document.createElement('div');
        const bullet = document.createElement('button');
        paginationEl.appendChild(bullet);

        const swiperMock = {
            params: { loop: false, pagination: { bulletActiveClass: 'swiper-pagination-bullet-active' } },
            navigation: { nextEl: document.createElement('button'), prevEl: document.createElement('button') },
            pagination: { el: paginationEl, bullets: [bullet] },
            slides: [],
            realIndex: 0,
            activeIndex: 0,
            isBeginning: true,
            isEnd: true,
        };

        swiperConfig.on.init.call(swiperMock);

        expect(bullet.getAttribute('title')).toBe('Aller à 1');
        expect(bullet.getAttribute('aria-label')).toBe('Aller à 1');
        expect(bullet.getAttribute('title')).toBe(bullet.getAttribute('aria-label'));
    });
});
