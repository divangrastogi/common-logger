/**
 * Common Logger Admin JavaScript
 * Version: 1.1.0 - Enhanced with AI Insights
 */

(function($) {
    'use strict';

    /**
     * Common Logger Admin functionality
     */
    var CommonLoggerAdmin = {

        /**
         * Initialize all functionality
         */
        init: function() {
            this.initContextModal();
            this.initFunctionChainToggle();
            this.initFilters();
            this.initDashboard();
            this.initExport();
            this.initDeveloperMode();
        },

        /**
         * Initialize context modal functionality
         */
        initContextModal: function() {
            $('.common-logger-view-context').on('click', function() {
                var context = $(this).data('context');
                var formattedContext = JSON.stringify(context, null, 2);

                // Syntax highlight JSON
                formattedContext = CommonLoggerAdmin.syntaxHighlightJSON(formattedContext);

                $('#modal-context-content').html(formattedContext);
                $('#common-logger-context-modal').show();
            });

            $('.close').on('click', function() {
                $('#common-logger-context-modal').hide();
            });

            $(window).on('click', function(event) {
                if (event.target.id === 'common-logger-context-modal') {
                    $('#common-logger-context-modal').hide();
                }
            });

            // ESC key to close modal
            $(document).on('keydown', function(event) {
                if (event.keyCode === 27 && $('#common-logger-context-modal').is(':visible')) {
                    $('#common-logger-context-modal').hide();
                }
            });
        },

        /**
         * Initialize function chain toggle
         */
        initFunctionChainToggle: function() {
            $('.common-logger-function-chain').each(function() {
                var $chain = $(this);
                var fullText = $chain.text();
                var isExpanded = false;

                if (fullText.length > 100) {
                    var truncatedText = fullText.substring(0, 100) + '...';
                    $chain.data('full-text', fullText);
                    $chain.data('truncated-text', truncatedText);
                    $chain.html(truncatedText + ' <span class="common-logger-function-chain-toggle">[Show More]</span>');
                }
            });

            $(document).on('click', '.common-logger-function-chain-toggle', function() {
                var $toggle = $(this);
                var $chain = $toggle.closest('.common-logger-function-chain');
                var isExpanded = $chain.hasClass('expanded');

                if (isExpanded) {
                    $chain.html($chain.data('truncated-text') + ' <span class="common-logger-function-chain-toggle">[Show More]</span>');
                    $chain.removeClass('expanded');
                } else {
                    $chain.html($chain.data('full-text') + ' <span class="common-logger-function-chain-toggle">[Show Less]</span>');
                    $chain.addClass('expanded');
                }
            });
        },

        /**
         * Initialize enhanced filters
         */
        initFilters: function() {
            // Form validation
            $('.common-logger-filters form').on('submit', function() {
                var limit = $('input[name="common_logger_limit"]').val();
                if (limit && (limit < 1 || limit > 2000)) {
                    alert('Limit must be between 1 and 2000');
                    return false;
                }
                return true;
            });

            // Auto-submit on filter change (optional)
            $('.common-logger-filters select').on('change', function() {
                // Uncomment below line if you want auto-submit on filter change
                // $(this).closest('form').submit();
            });

            // Enhanced filter reset
            $('.common-logger-filters .button[href*="reset"]').on('click', function(e) {
                e.preventDefault();
                var $form = $(this).closest('form');
                $form.find('select').val('');
                $form.find('input[type="text"], input[type="number"]').val('');
                $form.submit();
            });
        },

        /**
         * Initialize dashboard functionality
         */
        initDashboard: function() {
            // Auto-refresh dashboard data (if implemented)
            this.loadDashboardData();

            // Mode switcher
            $('.common-logger-mode-switcher input[type="radio"]').on('change', function() {
                var mode = $(this).val();
                CommonLoggerAdmin.switchMode(mode);
            });
        },

        /**
         * Load dashboard data via AJAX
         */
        loadDashboardData: function() {
            // This would load real-time dashboard data
            // For now, just initialize any dashboard elements
            this.updateDashboardMetrics();
        },

        /**
         * Update dashboard metrics
         */
        updateDashboardMetrics: function() {
            // Animate metric counters
            $('.metric').each(function() {
                var $metric = $(this);
                var targetValue = parseInt($metric.text());
                var currentValue = 0;

                var interval = setInterval(function() {
                    currentValue += Math.ceil(targetValue / 20);
                    if (currentValue >= targetValue) {
                        currentValue = targetValue;
                        clearInterval(interval);
                    }
                    $metric.text(currentValue);
                }, 50);
            });
        },

        /**
         * Switch between modes (Safe/Developer/Silent)
         */
        switchMode: function(mode) {
            var $notice = $('.common-logger-developer-notice');

            switch (mode) {
                case 'developer':
                    $notice.show().find('p').text('Developer mode enabled: Debug information will be displayed in admin pages.');
                    break;
                case 'silent':
                    $notice.show().find('p').text('Silent mode enabled: Only critical errors will be logged.');
                    break;
                default:
                    $notice.hide();
                    break;
            }
        },

        /**
         * Initialize export functionality
         */
        initExport: function() {
            $('.common-logger-export-options .button').on('click', function(e) {
                e.preventDefault();

                var format = $(this).data('format');
                var $form = $('<form>', {
                    method: 'POST',
                    action: ajaxurl,
                    style: 'display: none;'
                });

                $form.append($('<input>', {
                    name: 'action',
                    value: 'common_logger_export'
                }));

                $form.append($('<input>', {
                    name: 'format',
                    value: format
                }));

                $form.append($('<input>', {
                    name: 'nonce',
                    value: common_logger_admin.nonce
                }));

                $('body').append($form);
                $form.submit();
            });
        },

        /**
         * Initialize developer mode features
         */
        initDeveloperMode: function() {
            if ($('body').hasClass('common-logger-developer-mode')) {
                this.enableDeveloperFeatures();
            }
        },

        /**
         * Enable developer-specific features
         */
        enableDeveloperFeatures: function() {
            // Add developer toolbar
            this.addDeveloperToolbar();

            // Enable console logging
            this.enableConsoleLogging();
        },

        /**
         * Add developer toolbar
         */
        addDeveloperToolbar: function() {
            var $toolbar = $('<div>', {
                class: 'common-logger-dev-toolbar',
                css: {
                    position: 'fixed',
                    bottom: '10px',
                    right: '10px',
                    background: '#23282d',
                    color: '#fff',
                    padding: '10px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    zIndex: 9999,
                    maxWidth: '300px'
                }
            });

            $toolbar.html('<strong>Common Logger Dev Mode</strong><br>Debug info enabled');
            $('body').append($toolbar);

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $toolbar.fadeOut();
            }, 5000);
        },

        /**
         * Enable console logging for development
         */
        enableConsoleLogging: function() {
            if (typeof console !== 'undefined' && console.log) {
                console.log('Common Logger: Developer mode enabled');
            }
        },

        /**
         * Syntax highlight JSON
         */
        syntaxHighlightJSON: function(json) {
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                var cls = 'number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'key';
                    } else {
                        cls = 'string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'boolean';
                } else if (/null/.test(match)) {
                    cls = 'null';
                }
                return '<span class="json-' + cls + '">' + match + '</span>';
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        CommonLoggerAdmin.init();
    });

    // Expose for potential external use
    window.CommonLoggerAdmin = CommonLoggerAdmin;

})(jQuery);
