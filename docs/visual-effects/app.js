(function () {
    'use strict';

    const defaultConfigs = {
        'animated-background': {
            primaryColor: '#312e81',
            secondaryColor: '#2563eb',
            speed: 18,
            angle: 130,
            noise: 0.35
        },
        ecg: {
            strokeColor: '#f43f5e',
            backgroundColor: '#0f172a',
            thickness: 3,
            speed: 12,
            glow: 12
        },
        crt: {
            lineColor: '#1f2937',
            lineOpacity: 0.35,
            lineDensity: 3,
            glow: 18,
            flicker: 0.25
        }
    };

    const state = new Map(Object.entries(defaultConfigs).map(([key, value]) => [key, { ...value }]));

    function formatNumber(value, options) {
        const number = Number(value);
        if (Number.isNaN(number)) {
            return 0;
        }
        return options && typeof options.decimals === 'number'
            ? Number(number.toFixed(options.decimals))
            : number;
    }

    function updateAnimatedBackground(panel, config) {
        const canvas = panel.querySelector('.effect-preview__canvas--animated');
        const codeTarget = panel.querySelector('[data-css-preview]');
        if (!canvas || !codeTarget) {
            return;
        }

        const primary = config.primaryColor || defaultConfigs['animated-background'].primaryColor;
        const secondary = config.secondaryColor || defaultConfigs['animated-background'].secondaryColor;
        const angle = `${formatNumber(config.angle)}deg`;
        const speed = Math.max(5, formatNumber(config.speed));
        const noise = Math.min(1, Math.max(0, formatNumber(config.noise, { decimals: 2 })));

        canvas.style.setProperty('--animated-primary', primary);
        canvas.style.setProperty('--animated-secondary', secondary);
        canvas.style.setProperty('--animated-angle', angle);
        canvas.style.setProperty('--animated-duration', `${speed}s`);
        canvas.style.setProperty('--animated-noise', noise);

        codeTarget.textContent = `:root {
  --animated-primary: ${primary};
  --animated-secondary: ${secondary};
  --animated-angle: ${angle};
  --animated-duration: ${speed}s;
  --animated-noise: ${noise};
}

.preview {
  background: linear-gradient(var(--animated-angle), var(--animated-primary), var(--animated-secondary));
  animation: animated-gradient var(--animated-duration) ease-in-out infinite alternate;
}

.preview::after {
  opacity: var(--animated-noise);
}`;
    }

    function updateEcg(panel, config) {
        const canvas = panel.querySelector('.effect-preview__canvas--ecg');
        const wave = panel.querySelector('.ecg-wave');
        const svg = panel.querySelector('.ecg-wave__svg');
        const path = panel.querySelector('.ecg-wave__path');
        const codeTarget = panel.querySelector('[data-css-preview]');

        if (!canvas || !wave || !svg || !path || !codeTarget) {
            return;
        }

        const strokeColor = config.strokeColor || defaultConfigs.ecg.strokeColor;
        const backgroundColor = config.backgroundColor || defaultConfigs.ecg.backgroundColor;
        const thickness = Math.max(1, formatNumber(config.thickness));
        const speed = Math.max(4, formatNumber(config.speed));
        const glow = Math.max(0, formatNumber(config.glow));

        canvas.style.setProperty('--ecg-background', backgroundColor);
        wave.style.setProperty('--ecg-color', strokeColor);
        wave.style.setProperty('--ecg-thickness', `${thickness}px`);
        wave.style.setProperty('--ecg-speed', `${speed}s`);
        wave.style.setProperty('--ecg-glow', `${glow}px`);
        svg.style.animationDuration = `${speed}s`;
        svg.style.filter = `drop-shadow(0 0 ${glow}px ${strokeColor})`;
        path.style.stroke = strokeColor;
        path.style.strokeWidth = `${thickness}`;

        codeTarget.textContent = `.ecg-preview {
  background: ${backgroundColor};
}

.ecg-preview .ecg-wave {
  --ecg-color: ${strokeColor};
  --ecg-thickness: ${thickness}px;
  --ecg-speed: ${speed}s;
  --ecg-glow: ${glow}px;
}`;
    }

    function updateCrt(panel, config) {
        const overlay = panel.querySelector('.crt-overlay');
        const codeTarget = panel.querySelector('[data-css-preview]');
        if (!overlay || !codeTarget) {
            return;
        }

        const lineColor = config.lineColor || defaultConfigs.crt.lineColor;
        const lineOpacity = Math.max(0, Math.min(1, formatNumber(config.lineOpacity, { decimals: 2 })));
        const density = Math.max(1, formatNumber(config.lineDensity));
        const glow = Math.max(0, formatNumber(config.glow));
        const flicker = Math.max(0, Math.min(1, formatNumber(config.flicker, { decimals: 2 })));

        const rgbaColor = convertHexToRgba(lineColor, lineOpacity);

        overlay.style.setProperty('--crt-line-color', rgbaColor);
        overlay.style.setProperty('--crt-line-density', density);
        overlay.style.setProperty('--crt-glow', glow);
        overlay.style.setProperty('--crt-flicker', flicker);

        codeTarget.textContent = `.crt-preview {
  position: relative;
}

.crt-preview::after {
  content: "";
  inset: 0;
  position: absolute;
  background-image: repeating-linear-gradient(to bottom, transparent 0, transparent ${density * 2}px, ${rgbaColor} ${density * 2}px, ${rgbaColor} ${density * 3}px);
  mix-blend-mode: soft-light;
  animation: crt-flicker 1.4s steps(2, end) infinite;
  box-shadow: inset 0 0 ${glow}px rgba(14, 116, 144, 0.45);
}`;
    }

    function convertHexToRgba(hex, alpha) {
        if (typeof hex !== 'string' || !hex.length) {
            return `rgba(31, 41, 55, ${alpha})`;
        }

        let sanitized = hex.replace('#', '').trim();

        if (sanitized.length === 3) {
            sanitized = sanitized.split('').map((char) => char + char).join('');
        }

        if (sanitized.length !== 6) {
            return `rgba(31, 41, 55, ${alpha})`;
        }

        const bigint = parseInt(sanitized, 16);
        const r = (bigint >> 16) & 255;
        const g = (bigint >> 8) & 255;
        const b = bigint & 255;

        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function updatePanel(effectId, panel) {
        const config = state.get(effectId);
        if (!config) {
            return;
        }

        if (effectId === 'animated-background') {
            updateAnimatedBackground(panel, config);
            return;
        }

        if (effectId === 'ecg') {
            updateEcg(panel, config);
            return;
        }

        if (effectId === 'crt') {
            updateCrt(panel, config);
        }
    }

    function attachFormHandlers(panel) {
        const effectId = panel.dataset.effect;
        const form = panel.querySelector('[data-effect-form]');
        const actions = panel.querySelectorAll('[data-action]');

        if (!effectId || !form) {
            return;
        }

        const defaults = defaultConfigs[effectId];
        const currentState = state.get(effectId);

        if (!defaults || !currentState) {
            return;
        }

        // Sync initial form values with defaults
        const elements = Array.from(form.elements);
        elements.forEach((element) => {
            if (!(element instanceof HTMLInputElement)) {
                return;
            }

            const name = element.name;
            if (!Object.prototype.hasOwnProperty.call(defaults, name)) {
                return;
            }

            const defaultValue = defaults[name];
            if (element.type === 'checkbox') {
                element.checked = Boolean(defaultValue);
            } else {
                element.value = String(defaultValue);
            }
        });

        form.addEventListener('input', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            const { name, type, value } = target;
            if (!Object.prototype.hasOwnProperty.call(currentState, name)) {
                return;
            }

            if (type === 'range') {
                currentState[name] = target.step && target.step.indexOf('.') !== -1
                    ? parseFloat(value)
                    : parseInt(value, 10);
            } else if (type === 'color') {
                currentState[name] = value;
            } else if (type === 'checkbox') {
                currentState[name] = target.checked;
            } else {
                currentState[name] = value;
            }

            updatePanel(effectId, panel);
        });

        actions.forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.dataset.action;
                if (action === 'reset') {
                    Object.assign(currentState, { ...defaults });
                    elements.forEach((element) => {
                        if (!(element instanceof HTMLInputElement)) {
                            return;
                        }
                        const name = element.name;
                        if (!Object.prototype.hasOwnProperty.call(defaults, name)) {
                            return;
                        }
                        if (element.type === 'checkbox') {
                            element.checked = Boolean(defaults[name]);
                        } else {
                            element.value = String(defaults[name]);
                        }
                    });
                    updatePanel(effectId, panel);
                    return;
                }

                if (action === 'copy') {
                    const code = panel.querySelector('[data-css-preview]');
                    if (!code) {
                        return;
                    }
                    const text = code.textContent || '';
                    if (!text) {
                        return;
                    }
                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        navigator.clipboard.writeText(text).catch(() => {
                            fallbackCopy(text);
                        });
                    } else {
                        fallbackCopy(text);
                    }
                }
            });
        });

        updatePanel(effectId, panel);
    }

    function fallbackCopy(text) {
        try {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        } catch (error) {
            console.error('Impossible de copier le CSS', error);
        }
    }

    function setActiveEffect(effectId) {
        const panels = document.querySelectorAll('.effect-panel');
        panels.forEach((panel) => {
            const matches = panel.dataset.effect === effectId;
            panel.hidden = !matches;
            panel.setAttribute('aria-hidden', matches ? 'false' : 'true');
        });

        const navButtons = document.querySelectorAll('.sidebar__link');
        navButtons.forEach((button) => {
            const matches = button.dataset.target === effectId;
            button.setAttribute('aria-selected', matches ? 'true' : 'false');
            button.tabIndex = matches ? 0 : -1;
        });
    }

    function applyReducedMotionPreference(initial) {
        const toggle = document.getElementById('reduced-motion-toggle');
        if (!toggle) {
            return;
        }

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (initial) {
            toggle.checked = prefersReducedMotion;
            document.documentElement.classList.toggle('fx-reduced-motion', prefersReducedMotion);
        }

        toggle.addEventListener('change', () => {
            document.documentElement.classList.toggle('fx-reduced-motion', toggle.checked);
        });

        if (!initial) {
            return;
        }

        const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        const onPreferenceChange = (event) => {
            if (!toggle.dataset.userDefined) {
                toggle.checked = event.matches;
                document.documentElement.classList.toggle('fx-reduced-motion', event.matches);
            }
        };

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', onPreferenceChange);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(onPreferenceChange);
        }

        toggle.addEventListener('input', () => {
            toggle.dataset.userDefined = 'true';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const panels = document.querySelectorAll('.effect-panel');
        panels.forEach((panel) => {
            attachFormHandlers(panel);
        });

        const navButtons = document.querySelectorAll('.sidebar__link');
        navButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.target;
                if (!target) {
                    return;
                }
                setActiveEffect(target);
            });
        });

        setActiveEffect('animated-background');
        applyReducedMotionPreference(true);
    });
})();
