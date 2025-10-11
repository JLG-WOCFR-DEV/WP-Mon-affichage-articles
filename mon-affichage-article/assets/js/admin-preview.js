/* global jQuery, myArticlesPreview */
(function ($) {
    'use strict';

    var settings = window.myArticlesPreview || {};
    var adapterRegistry = Array.isArray(settings.adapters) ? settings.adapters : [];

    function parseFieldDescriptor(name, prefix) {
        if (!name || !prefix) {
            return null;
        }

        var expectedStart = prefix + '[';
        if (name.indexOf(expectedStart) !== 0) {
            return null;
        }

        var suffix = name.substring(expectedStart.length);
        if (!suffix.endsWith(']')) {
            return null;
        }

        var isArray = false;
        if (suffix.endsWith('[]')) {
            isArray = true;
            suffix = suffix.slice(0, -2);
        } else {
            suffix = suffix.slice(0, -1);
        }

        if (!suffix) {
            return null;
        }

        return { key: suffix, isArray: isArray };
    }

    function assignValue(target, key, value, isArray) {
        if (isArray) {
            if (!Array.isArray(target[key])) {
                target[key] = [];
            }

            if (Array.isArray(value)) {
                value.forEach(function (entry) {
                    if (entry !== undefined && entry !== null && entry !== '') {
                        target[key].push(entry);
                    }
                });
            } else if (value !== undefined && value !== null && value !== '') {
                target[key].push(value);
            }

            return;
        }

        target[key] = value;
    }

    function collectSettings(form, prefix) {
        if (!form || !prefix) {
            return {};
        }

        var data = {};
        var selector = '[name^="' + prefix + '["]';
        var elements = form.querySelectorAll(selector);

        elements.forEach(function (field) {
            if (!field || field.disabled) {
                return;
            }

            var descriptor = parseFieldDescriptor(field.name, prefix);
            if (!descriptor) {
                return;
            }

            var key = descriptor.key;
            var isArray = descriptor.isArray;

            if (field.type === 'checkbox') {
                if (isArray) {
                    if (field.checked) {
                        assignValue(data, key, field.value === 'on' ? '1' : field.value, true);
                    }
                } else {
                    assignValue(data, key, field.checked ? (field.value === 'on' ? '1' : field.value) : '0', false);
                }
                return;
            }

            if (field.type === 'radio') {
                if (field.checked) {
                    assignValue(data, key, field.value, false);
                }
                return;
            }

            if (field.multiple && field.options) {
                var selected = Array.from(field.selectedOptions || []).map(function (option) {
                    return option.value;
                });
                assignValue(data, key, selected, true);
                return;
            }

            if (isArray) {
                assignValue(data, key, field.value, true);
            } else {
                assignValue(data, key, field.value, false);
            }
        });

        return data;
    }

    function parseAvailableAdapters(container) {
        if (!container) {
            return adapterRegistry.slice();
        }

        var inline = container.getAttribute('data-available-adapters');
        if (inline) {
            try {
                var parsed = JSON.parse(inline);
                if (Array.isArray(parsed)) {
                    return parsed;
                }
            } catch (error) {
                // Ignore parsing failure and fall back to localized registry.
            }
        }

        return adapterRegistry.slice();
    }

    function normalizeItems(value) {
        if (!Array.isArray(value)) {
            return [];
        }

        return value.map(function (entry) {
            if (!entry || typeof entry !== 'object') {
                return { id: '', config: {} };
            }

            var normalized = {
                id: typeof entry.id === 'string' ? entry.id : (typeof entry.adapter === 'string' ? entry.adapter : ''),
                config: {}
            };

            if (entry.config && typeof entry.config === 'object') {
                normalized.config = entry.config;
            }

            return normalized;
        });
    }

    function initAdapters() {
        var container = document.querySelector('.my-articles-content-adapters');
        if (!container) {
            return;
        }

        var hiddenInput = container.querySelector('input[type="hidden"]');
        var list = container.querySelector('.my-articles-content-adapters__list');
        var addButton = container.querySelector('.my-articles-content-adapters__add');
        var available = parseAvailableAdapters(container);
        var items = [];

        if (hiddenInput && hiddenInput.value) {
            try {
                var initial = JSON.parse(hiddenInput.value);
                items = normalizeItems(initial);
            } catch (error) {
                items = [];
            }
        }

        function updateHiddenInput() {
            if (!hiddenInput) {
                return;
            }

            var exportItems = items.filter(function (item) {
                return item && typeof item.id === 'string' && item.id.length > 0;
            }).map(function (item) {
                return {
                    id: item.id,
                    config: item.config || {}
                };
            });

            hiddenInput.value = JSON.stringify(exportItems);
            try {
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {
                var changeEvent = document.createEvent('HTMLEvents');
                changeEvent.initEvent('change', true, false);
                hiddenInput.dispatchEvent(changeEvent);
            }
        }

        function parseConfig(text) {
            if (!text || !text.trim()) {
                return { ok: true, value: {} };
            }

            try {
                var parsed = JSON.parse(text);
                return { ok: true, value: parsed && typeof parsed === 'object' ? parsed : {} };
            } catch (error) {
                return { ok: false, value: {} };
            }
        }

        function render() {
            if (!list) {
                return;
            }

            list.innerHTML = '';

            if (!items.length) {
                return;
            }

            items.forEach(function (item, index) {
                var row = document.createElement('div');
                row.className = 'my-articles-content-adapters__row';

                var removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'button-link-delete my-articles-content-adapters__remove';
                removeButton.textContent = settings.strings && settings.strings.remove ? settings.strings.remove : 'Supprimer';
                removeButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    items.splice(index, 1);
                    render();
                    updateHiddenInput();
                });
                row.appendChild(removeButton);

                var adapterLabel = document.createElement('label');
                adapterLabel.textContent = settings.strings && settings.strings.adapterLabel ? settings.strings.adapterLabel : 'Adaptateur';
                var select = document.createElement('select');

                var defaultOption = document.createElement('option');
                defaultOption.value = '';
                var defaultLabel = settings.strings && settings.strings.selectAdapter ? settings.strings.selectAdapter : '';
                defaultOption.textContent = defaultLabel;
                select.appendChild(defaultOption);

                available.forEach(function (adapter) {
                    if (!adapter || typeof adapter.id !== 'string') {
                        return;
                    }
                    var option = document.createElement('option');
                    option.value = adapter.id;
                    option.textContent = adapter.label || adapter.id;
                    if (item.id === adapter.id) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                select.addEventListener('change', function () {
                    items[index].id = select.value;
                    updateHiddenInput();
                });

                adapterLabel.appendChild(select);
                row.appendChild(adapterLabel);

                var configLabel = document.createElement('label');
                configLabel.textContent = settings.strings && settings.strings.configLabel ? settings.strings.configLabel : 'Configuration (JSON)';
                var textarea = document.createElement('textarea');
                textarea.value = JSON.stringify(item.config || {}, null, 2);
                var description = document.createElement('span');
                description.className = 'description';

                textarea.addEventListener('blur', function () {
                    var parsed = parseConfig(textarea.value);
                    if (parsed.ok) {
                        row.classList.remove('is-invalid');
                        description.textContent = '';
                        items[index].config = parsed.value;
                        updateHiddenInput();
                    } else {
                        row.classList.add('is-invalid');
                        description.textContent = settings.strings && settings.strings.invalidJson ? settings.strings.invalidJson : 'JSON invalide.';
                    }
                });

                configLabel.appendChild(textarea);
                configLabel.appendChild(description);
                row.appendChild(configLabel);

                list.appendChild(row);
            });
        }

        if (addButton) {
            addButton.addEventListener('click', function (event) {
                event.preventDefault();
                if (!available.length) {
                    return;
                }
                items.push({ id: available[0].id || '', config: {} });
                render();
                updateHiddenInput();
            });

            if (!available.length) {
                addButton.disabled = true;
            }
        }

        render();
        updateHiddenInput();
    }

    function initPreview() {
        var container = document.querySelector('.my-articles-metabox-preview');
        if (!container) {
            return;
        }

        var refreshButton = container.querySelector('.my-articles-metabox-preview__refresh');
        var status = container.querySelector('.my-articles-metabox-preview__status');
        var canvas = container.querySelector('.my-articles-metabox-preview__canvas');
        var form = container.closest('form');
        var prefix = container.getAttribute('data-settings-prefix') || '_my_articles_settings';
        var pendingRequest = null;
        var debounceTimer = null;

        function setStatus(message) {
            if (status) {
                status.textContent = message || '';
            }
        }

        function requestPreview() {
            var postId = parseInt(container.getAttribute('data-instance-id'), 10) || 0;
            if (!postId) {
                container.classList.remove('is-loading');
                if (canvas) {
                    canvas.innerHTML = '';
                }
                setStatus(settings.strings && settings.strings.missingId ? settings.strings.missingId : '');
                return;
            }

            if (pendingRequest && typeof pendingRequest.abort === 'function') {
                pendingRequest.abort();
            }

            container.classList.add('is-loading');
            setStatus(settings.strings && settings.strings.loading ? settings.strings.loading : '');

            var payload = {
                action: 'my_articles_render_preview',
                nonce: container.getAttribute('data-nonce') || settings.nonce || '',
                post_id: postId,
                settings: JSON.stringify(collectSettings(form, prefix))
            };

            pendingRequest = $.ajax({
                url: settings.ajaxUrl || (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : ''),
                method: 'POST',
                dataType: 'json',
                data: payload
            }).done(function (response) {
                if (!canvas) {
                    return;
                }

                if (response && response.success) {
                    canvas.innerHTML = response.data && response.data.html ? response.data.html : '';
                    setStatus(settings.strings && settings.strings.success ? settings.strings.success : '');
                } else {
                    var message = settings.strings && settings.strings.error ? settings.strings.error : '';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    }
                    setStatus(message);
                }
            }).fail(function (xhr) {
                var message = settings.strings && settings.strings.error ? settings.strings.error : '';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }
                setStatus(message);
            }).always(function () {
                container.classList.remove('is-loading');
                pendingRequest = null;
            });
        }

        function schedulePreview() {
            if (!form) {
                return;
            }

            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(requestPreview, 600);
        }

        if (refreshButton) {
            refreshButton.addEventListener('click', function (event) {
                event.preventDefault();
                requestPreview();
            });
        }

        if (form) {
            form.addEventListener('change', schedulePreview);
            form.addEventListener('input', schedulePreview);
        }

        requestPreview();
    }

    $(function () {
        initPreview();
        initAdapters();
    });
})(jQuery);
