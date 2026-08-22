(function (window, document) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const blockedPathKeys = ['__proto__', 'prototype', 'constructor'];
    const booleanAttributes = ['disabled', 'hidden', 'checked', 'selected'];
    const urlAttributes = ['src', 'href'];

    function TemplateRendererError(message) {
        this.name = 'TemplateRendererError';
        this.message = message;

        if (Error.captureStackTrace) {
            Error.captureStackTrace(this, TemplateRendererError);
        }
    }

    TemplateRendererError.prototype = Object.create(Error.prototype);
    TemplateRendererError.prototype.constructor = TemplateRendererError;

    function isRecord(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function readOwnPath(data, path) {
        const segments = String(path || '').split('.');
        let current = data;

        if (segments.length === 0 || segments.some(function (segment) { return segment === ''; })) {
            throw new TemplateRendererError('Malformed binding path.');
        }

        for (let index = 0; index < segments.length; index += 1) {
            const segment = segments[index];

            if (blockedPathKeys.indexOf(segment) !== -1) {
                throw new TemplateRendererError('Unsafe binding path.');
            }

            if (!isRecord(current) || !Object.prototype.hasOwnProperty.call(current, segment)) {
                return undefined;
            }

            current = current[segment];
        }

        return current;
    }

    function isDefaultAttributeAllowed(name) {
        return name.indexOf('data-') === 0
            || name.indexOf('aria-') === 0
            || ['title', 'value', 'alt'].indexOf(name) !== -1
            || booleanAttributes.indexOf(name) !== -1;
    }

    function TemplateRenderer(urlValidators) {
        this.urlValidators = isRecord(urlValidators) ? urlValidators : {};
    }

    TemplateRenderer.prototype.registerUrlValidator = function (attribute, validator) {
        const name = String(attribute || '').toLowerCase();

        if (urlAttributes.indexOf(name) === -1 || typeof validator !== 'function') {
            throw new TemplateRendererError('Invalid URL validator.');
        }

        this.urlValidators[name] = validator;
    };

    TemplateRenderer.prototype.bindFields = function (fragment, data) {
        fragment.querySelectorAll('[data-field]').forEach(function (element) {
            const value = readOwnPath(data, element.getAttribute('data-field'));
            element.textContent = value === undefined || value === null ? '' : String(value);
        });
    };

    TemplateRenderer.prototype.bindAttributes = function (fragment, data) {
        const renderer = this;

        fragment.querySelectorAll('[data-attr]').forEach(function (element) {
            const declaration = element.getAttribute('data-attr') || '';
            const mappings = declaration.split(';').filter(Boolean);

            if (mappings.length === 0) {
                throw new TemplateRendererError('Malformed attribute binding.');
            }

            mappings.forEach(function (mapping) {
                const separator = mapping.indexOf(':');

                if (separator <= 0 || separator === mapping.length - 1) {
                    throw new TemplateRendererError('Malformed attribute binding.');
                }

                const name = mapping.slice(0, separator).trim().toLowerCase();
                const path = mapping.slice(separator + 1).trim();
                const value = readOwnPath(data, path);

                if (name.indexOf('on') === 0 || ['style', 'srcdoc'].indexOf(name) !== -1) {
                    throw new TemplateRendererError('Unsafe attribute binding.');
                }

                if (urlAttributes.indexOf(name) !== -1) {
                    const validator = renderer.urlValidators[name];

                    if (typeof validator !== 'function' || !validator(value)) {
                        throw new TemplateRendererError('Unsafe URL attribute binding.');
                    }
                } else if (!isDefaultAttributeAllowed(name)) {
                    throw new TemplateRendererError('Attribute is not allowed.');
                }

                if (booleanAttributes.indexOf(name) !== -1) {
                    const enabled = Boolean(value);
                    element[name] = enabled;

                    if (enabled) {
                        element.setAttribute(name, '');
                    } else {
                        element.removeAttribute(name);
                    }

                    return;
                }

                if (value === undefined || value === null || value === false) {
                    element.removeAttribute(name);
                    return;
                }

                element.setAttribute(name, String(value));
            });
        });
    };

    TemplateRenderer.prototype.bindKeys = function (fragment, data) {
        fragment.querySelectorAll('[data-key]').forEach(function (element) {
            const value = readOwnPath(data, element.getAttribute('data-key'));

            if (value === undefined || value === null || value === '') {
                throw new TemplateRendererError('Template item is missing its stable key.');
            }

            element.setAttribute('data-key', String(value));
        });
    };

    TemplateRenderer.prototype.render = function (templateId, data) {
        const template = document.getElementById(String(templateId || ''));

        if (!(template instanceof window.HTMLTemplateElement)) {
            throw new TemplateRendererError('Unknown template.');
        }

        if (!isRecord(data)) {
            throw new TemplateRendererError('Template data must be an object.');
        }

        const fragment = template.content.cloneNode(true);
        this.bindFields(fragment, data);
        this.bindAttributes(fragment, data);
        this.bindKeys(fragment, data);

        return fragment;
    };

    TemplateRenderer.prototype.renderList = function (templateId, items, target) {
        if (!Array.isArray(items)) {
            throw new TemplateRendererError('Template list data must be an array.');
        }

        if (!target || typeof target.replaceChildren !== 'function') {
            throw new TemplateRendererError('Template target is invalid.');
        }

        const output = document.createDocumentFragment();
        const renderer = this;

        items.forEach(function (item) {
            output.appendChild(renderer.render(templateId, item));
        });

        target.replaceChildren(output);
    };

    CoffeePOS.ui = CoffeePOS.ui || {};
    CoffeePOS.ui.TemplateRenderer = TemplateRenderer;
    CoffeePOS.ui.TemplateRendererError = TemplateRendererError;
    window.CoffeePOS = CoffeePOS;
}(window, document));
