/*!
 * bootstrap-tabs-x v1.3.6
 * http://plugins.krajee.com/tabs-x
 *
 * Krajee jQuery plugin for bootstrap-tabs-x.
 *
 * Author: Kartik Visweswaran
 * Copyright: 2014 - 2021, Kartik Visweswaran, Krajee.com
 *
 * Licensed under the BSD 3-Clause
 * https://github.com/kartik-v/bootstrap-tabs-x/blob/master/LICENSE.md
 */
(function (factory) {
    'use strict';
    if (typeof define === 'function' && define.amd) {
        define(['jquery'], factory);
    } else if (typeof module === 'object' && typeof module.exports === 'object') {
        factory(require('jquery'));
    } else {
        factory(window.jQuery);
    }
}(function ($) {
    "use strict";

    if (!$.fn.tabsXBsVersion) {
        $.fn.tabsXBsVersion = (window.bootstrap && window.bootstrap.Tab && bootstrap.Tab.VERSION) ||
            (window.Tab && window.Tab.VERSION) || '3.x.x';
    }

    var $h, TabsX;

    // Global helper object
    $h = {
        isEmpty: function (value, trim) {
            return value === null || value === undefined || value.length === 0 || (trim && $.trim(value) === '');
        },
        isBs: function (ver) {
            var chk = $.trim(($.fn.tabsXBsVersion || '') + '');
            ver = parseInt(ver, 10);
            if (!chk) {
                return ver === 4;
            }
            return ver === parseInt(chk.charAt(0), 10);

        },
        getDataAttr: function (prop) {
            return 'data-' + ($h.isBs(5) ? 'bs-' : '') + prop;
        }
    };
    // Main TabsX plugin function
    TabsX = function (element, options) {
        var self = this;
        self.$element = $(element);
        self.init(options);
        self.listen();
    };

    //noinspection JSUnusedGlobalSymbols
    TabsX.prototype = {
        constructor: TabsX,
        init: function (options) {
            var self = this, $el = self.$element;
            $.each(options, function (key, val) {
                self[key] = val;
            });
            self.initCache();
            self.enableCache = !!self.enableCache;
            if (!$h.isEmpty(self.addCss) && !$el.hasClass(self.addCss)) {
                $el.addClass(self.addCss);
            }
            self.$pane = $el.find('.tab-pane.active');
            self.$content = $el.find('.tab-content');
            self.$tabs = $el.find('.nav-tabs');
            self.isVertical = ($el.hasClass('tabs-left') || $el.hasClass('tabs-right'));
            self.isVerticalSide = self.isVertical && $el.hasClass('tab-sideways');
            self.initVertical();
        },
        initVertical: function () {
            var self = this;
            if (self.isVertical) {
                self.$content.css('min-height', self.$tabs.outerHeight() + 1 + 'px');
            }
        },
        setTitle: function ($el) {
            var self = this, txt = $.trim($el.text()), isVertical = self.isVertical,
                maxLen = $h.isEmpty($el.data('maxTitleLength')) ? self.maxTitleLength : $el.data('maxTitleLength');
            if (isVertical && txt.length > maxLen - 2 && $h.isEmpty($el.attr('title'))) {
                $el.attr('title', txt);
            }
        },
        listen: function () {
            var self = this, $element = self.$element, dataVar = $h.getDataAttr('toggle');
            console.log('KV SAYS', dataVar);
            $element.find('.nav-tabs li.disabled').each(function () {
                $(this).find('[' + dataVar + '="tab"]').removeAttr(dataVar);
            });
            $element.find('.nav-tabs li [' + dataVar + '="dropdown"]').each(function () {
                self.setTitle($(this));
            });
            $element.find('.nav-tabs li').on('click', function () {
                setTimeout(function () {
                    self.initVertical();
                }, 1000);
            });
            $element.find('.nav-tabs li [' + dataVar + '="tab"]').each(function () {
                var $el = $(this), $item = $el.closest('li');
                $item.removeAttr(dataVar);
                self.setTitle($el);
                $el.on('click', function (e) {
                    if ($item.hasClass('disabled')) {
                        e.preventDefault();
                        return;
                    }
                    var vUrl = $(this).attr("data-url"), vHash = this.hash, cacheKey = vUrl + vHash, settings;
                    if ($h.isEmpty(vUrl) || (self.enableCache && self.cache.exist(cacheKey))) {
                        $el.trigger('tabsX:click');
                        return;
                    }
                    e.preventDefault();
                    var $tab = $(vHash), $pane = $(this), $paneHeader = $pane,
                        css = $(this).attr("data-loading-class") || 'kv-tab-loading',
                        $element = $pane.closest('.dropdown'),
                        cbSuccess = self.successCallback[vHash] || null,
                        cbError = self.errorCallback[vHash] || null;
                    if (!$h.isEmpty($element.attr('class'))) {
                        $paneHeader = $element.find('.dropdown-toggle');
                    }
                    settings = $.extend(true, {}, {
                        type: 'post',
                        dataType: 'json',
                        url: vUrl,
                        beforeSend: function (jqXHR, settings) {
                            $tab.html('<br><br><br>');
                            $paneHeader.removeClass(css).addClass(css);
                            $el.trigger('tabsX:beforeSend', [jqXHR, settings]);
                        },
                        success: function (data, status, jqXHR) {
                            setTimeout(function () {
                                $tab.html(data);
                                $pane.tab('show');
                                $paneHeader.removeClass(css);
                                if (self.enableCache) {
                                    self.cache.set(cacheKey);
                                }
                                if (cbSuccess && typeof cbSuccess === "function") {
                                    cbSuccess(data, status, jqXHR);
                                }
                                $el.trigger('tabsX:success', [data, status, jqXHR]);
                            }, 300);
                        },
                        error: function (jqXHR, status, message) {
                            if (cbError && typeof cbError === "function") {
                                cbError(jqXHR, status, message);
                            }
                            $el.trigger('tabsX:error', [jqXHR, status, message]);
                        },
                        complete: function (jqXHR, status) {
                            $el.trigger('tabsX:click', [jqXHR, status]);
                        }
                    }, self.ajaxSettings);
                    $.ajax(settings);
                });
            });
        },
        initCache: function () {
            var self = this, t = parseFloat(self.cacheTimeout);
            if (isNaN(t)) {
                t = 0;
            }
            self.cache = {
                data: {},
                create: function () {
                    return (new Date().getTime());
                },
                exist: function (key) {
                    return !!self.cache.data[key] && ((self.cache.create() - self.cache.data[key]) < t);
                },
                set: function (key) {
                    self.cache.data[key] = self.cache.create();
                }
            };
        },
        flushCache: function (tabIds) {
            var self = this;
            if (typeof tabIds === 'string') {
                tabIds = [tabIds];
            }
            if (typeof tabIds === 'object' && !$h.isEmpty(tabIds)) {
                Object.values(tabIds).forEach(function (tabId) {
                    Object.keys(self.cache.data).forEach(function (key) {
                        if (key.endsWith(tabId)) {
                            delete self.cache.data[key];
                        }
                    });
                });
            } else {
                self.cache.data = {};
            }
        }
    };

    $.fn.tabsX = function (option) {
        var args = Array.apply(null, arguments), retvals = [];
        args.shift();
        this.each(function () {
            var self = $(this), data = self.data('tabsX'), options = typeof option === 'object' && option;
            if (!data) {
                data = new TabsX(this, $.extend(true, {}, $.fn.tabsX.defaults, options, $(this).data()));
                self.data('tabsX', data);
            }
            if (typeof option === 'string') {
                retvals.push(data[option].apply(data, args));
            }
        });
        switch (retvals.length) {
            case 0:
                return this;
            case 1:
                return retvals[0];
            default:
                return retvals;
        }
    };

    $.fn.tabsX.defaults = {
        enableCache: true,
        cacheTimeout: 300000,
        maxTitleLength: 9,
        ajaxSettings: {},
        successCallback: {},
        errorCallback: {},
        addCss: 'tabs-krajee'
    };

    $.fn.tabsX.Constructor = TabsX;

    $(document).ready(function () {
        $('.tabs-x').tabsX({});
    });
}));