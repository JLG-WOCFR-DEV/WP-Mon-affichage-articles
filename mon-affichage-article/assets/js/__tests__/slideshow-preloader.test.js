const { preloadNeighbouringSlides } = require('../swiper-init');

describe('slideshow preloader', () => {
    it('ignores undefined neighbouring slides without throwing', () => {
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
});
