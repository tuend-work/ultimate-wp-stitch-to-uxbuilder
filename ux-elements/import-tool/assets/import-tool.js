/**
 * Stitch to UX Builder — Import Tool JavaScript
 *
 * Handles:
 * - Tab switching (paste/upload)
 * - File upload + drag & drop
 * - AJAX preview (parse HTML → show elements table)
 * - Dynamic source dropdown assignment
 * - AJAX import confirm (append shortcode to post)
 */
(function ($) {
    'use strict';

    var STU = {
        html: '',
        parsedData: null,
        isPreviewReady: false,

        /**
         * Initialize all event listeners.
         */
        init: function () {
            this.bindTabs();
            this.bindFileUpload();
            this.bindPreview();
            this.bindImport();
        },

        /**
         * Tab switching logic.
         */
        bindTabs: function () {
            $(document).on('click', '.stu-tab', function () {
                var tab = $(this).data('tab');

                // Toggle active tab
                $('.stu-tab').removeClass('stu-tab--active');
                $(this).addClass('stu-tab--active');

                // Toggle content
                $('.stu-tab-content').removeClass('stu-tab-content--active');
                $('.stu-tab-content[data-tab-content="' + tab + '"]').addClass('stu-tab-content--active');
            });
        },

        /**
         * File upload handling with drag & drop.
         */
        bindFileUpload: function () {
            var $zone = $('#stu-upload-zone');
            var $input = $('#stu-file-input');
            var $fileName = $('#stu-file-name');

            // Drag events
            $zone.on('dragenter dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('stu-drag-over');
            });

            $zone.on('dragleave drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('stu-drag-over');
            });

            // Drop handler
            $zone.on('drop', function (e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    STU.handleFile(files[0], $fileName);
                }
            });

            // File input change
            $input.on('change', function () {
                if (this.files.length > 0) {
                    STU.handleFile(this.files[0], $fileName);
                }
            });
        },

        /**
         * Read the uploaded file and store its content.
         *
         * @param {File} file      The selected file.
         * @param {jQuery} $display jQuery element to show file name.
         */
        handleFile: function (file, $display) {
            if (!file.name.match(/\.html?$/i)) {
                this.showStatus('error', 'Please select an .html or .htm file.');
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                STU.html = e.target.result;
                $display.text('✓ ' + file.name).show();

                // Auto-fill the textarea as well
                $('#stu-html-input').val(STU.html);
            };
            reader.readAsText(file);
        },

        /**
         * Preview button handler — sends HTML to server for parsing.
         */
        bindPreview: function () {
            $('#stu-preview-btn').on('click', function () {
                // Get HTML from textarea (takes priority) or stored file
                var html = $('#stu-html-input').val().trim();
                if (!html) {
                    html = STU.html;
                }

                if (!html) {
                    STU.showStatus('error', stuImport.strings.emptyHtml);
                    return;
                }

                STU.html = html;
                STU.doPreview(html);
            });
        },

        /**
         * Send HTML to the server for parsing and display results.
         *
         * @param {string} html Raw HTML to parse.
         */
        doPreview: function (html) {
            this.showStatus('loading', stuImport.strings.parsing);
            this.isPreviewReady = false;
            $('#stu-import-btn').prop('disabled', true);

            $.ajax({
                url: stuImport.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'stu_preview_import',
                    nonce: stuImport.nonce,
                    html: html,
                    post_id: stuImport.postId
                },
                success: function (response) {
                    if (!response.success) {
                        STU.showStatus('error', response.data.message || stuImport.strings.error);
                        return;
                    }

                    var data = response.data;
                    STU.parsedData = data;
                    STU.renderPreview(data);
                    STU.hideStatus();
                },
                error: function () {
                    STU.showStatus('error', stuImport.strings.error);
                }
            });
        },

        /**
         * Render the preview table and shortcode output.
         *
         * @param {Object} data Parsed data from server.
         */
        renderPreview: function (data) {
            var $area = $('#stu-preview-area');
            var $tbody = $('#stu-preview-table tbody');
            var $shortcode = $('#stu-shortcode-preview');

            $tbody.empty();

            // No elements warning
            if (!data.elements || data.elements.length === 0) {
                STU.showStatus('error', stuImport.strings.noElements);
                $area.hide();
                return;
            }

            // Existing sections warning
            if (data.existing_sections > 0) {
                var msg = stuImport.strings.existingSections.replace('%d', data.existing_sections);
                $('#stu-preview-warning p').text(msg);
                $('#stu-preview-warning').show();
            } else {
                $('#stu-preview-warning').hide();
            }

            // Duplicate warning
            if (data.is_duplicate) {
                $('#stu-duplicate-warning').show();
            } else {
                $('#stu-duplicate-warning').hide();
            }

            // Build table rows
            data.elements.forEach(function (el) {
                var typeClass = '';
                var typeLabel = '';
                var content = '';
                var sourceType = 'text';

                switch (el.type) {
                    case 'ux_field_text':
                        typeClass = 'stu-type-badge--text';
                        typeLabel = 'TEXT';
                        content = el.value || '';
                        sourceType = 'text';
                        break;
                    case 'ux_field_image':
                        typeClass = 'stu-type-badge--image';
                        typeLabel = 'IMAGE';
                        content = el.src || '';
                        sourceType = 'image';
                        break;
                    case 'ux_field_link':
                        typeClass = 'stu-type-badge--link';
                        typeLabel = 'LINK';
                        content = (el.label || '') + ' → ' + (el.href || '');
                        sourceType = 'link';
                        break;
                }

                // Build dynamic source dropdown
                var sources = stuImport.dynamicSources[sourceType] || {};
                var $select = $('<select>').attr('data-slot', el.slot);
                $.each(sources, function (val, label) {
                    $select.append($('<option>').val(val).text(label));
                });

                // ACF custom field option
                $select.append($('<option>').val('acf:custom').text('ACF Custom Field…'));

                var $row = $('<tr>');
                $row.append($('<td>').append($('<span class="stu-slot-name">').text(el.slot)));
                $row.append($('<td>').append($('<span class="stu-type-badge ' + typeClass + '">').text(typeLabel)));
                $row.append($('<td>').append($('<span class="stu-content-preview">').text(content).attr('title', content)));
                $row.append($('<td>').append($select));

                $tbody.append($row);
            });

            // Handle ACF custom field selection
            $tbody.find('select').on('change', function () {
                var $this = $(this);
                if ($this.val() === 'acf:custom') {
                    var fieldName = prompt('Enter ACF field name:');
                    if (fieldName) {
                        var customVal = 'acf:' + fieldName;
                        $this.find('option[value="acf:custom"]').val(customVal).text('acf:' + fieldName);
                        $this.val(customVal);
                    } else {
                        $this.val('');
                    }
                }

                // Regenerate shortcode preview with current selections
                STU.updateShortcodePreview();
            });

            // Show shortcode
            $shortcode.text(data.shortcode);

            $area.slideDown(300);

            // Enable import button (unless duplicate)
            if (!data.is_duplicate) {
                STU.isPreviewReady = true;
                $('#stu-import-btn').prop('disabled', false);
            }
        },

        /**
         * Update shortcode preview when dynamic source selections change.
         */
        updateShortcodePreview: function () {
            // This is a client-side approximation — the actual shortcode
            // will be generated server-side on import
            var $shortcode = $('#stu-shortcode-preview');
            var lines = STU.parsedData.shortcode.split('\n');
            var updated = [];

            lines.forEach(function (line) {
                // Check if line has a matching element
                var modified = line;

                $('#stu-preview-table tbody select').each(function () {
                    var slot = $(this).data('slot');
                    var source = $(this).val();

                    if (line.indexOf('slot="' + slot + '"') !== -1 && source) {
                        modified = modified.replace('dynamic_enabled="0"', 'dynamic_enabled="1"');

                        if (line.indexOf('ux_field_link') !== -1) {
                            modified += ' dynamic_href="' + source + '"';
                        } else {
                            modified += ' dynamic_source="' + source + '"';
                        }
                    }
                });

                // Clean up: remove trailing ] then re-add if we appended attributes
                updated.push(modified);
            });

            $shortcode.text(updated.join('\n'));
        },

        /**
         * Import button handler — sends confirmed data to server.
         */
        bindImport: function () {
            $('#stu-import-btn').on('click', function () {
                if (!STU.isPreviewReady) {
                    return;
                }

                if (!confirm(stuImport.strings.confirmImport)) {
                    return;
                }

                STU.doImport();
            });
        },

        /**
         * Send the import request to the server.
         */
        doImport: function () {
            this.showStatus('loading', stuImport.strings.importing);
            $('#stu-import-btn').prop('disabled', true);

            // Collect dynamic source selections
            var dynamicSources = {};
            $('#stu-preview-table tbody select').each(function () {
                var slot = $(this).data('slot');
                var source = $(this).val();
                if (source) {
                    dynamicSources[slot] = source;
                }
            });

            $.ajax({
                url: stuImport.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'stu_confirm_import',
                    nonce: stuImport.nonce,
                    post_id: stuImport.postId,
                    html: STU.html,
                    dynamic_sources: JSON.stringify(dynamicSources)
                },
                success: function (response) {
                    if (response.success) {
                        STU.showStatus('success', response.data.message || stuImport.strings.success);

                        // Update shortcode preview
                        if (response.data.shortcode) {
                            $('#stu-shortcode-preview').text(response.data.shortcode);
                        }

                        // Reset form
                        STU.isPreviewReady = false;
                        STU.html = '';
                        $('#stu-html-input').val('');
                        $('#stu-file-name').hide();

                        // Disable import to prevent double-click
                        $('#stu-import-btn').prop('disabled', true);
                    } else {
                        STU.showStatus('error', response.data.message || stuImport.strings.error);
                        $('#stu-import-btn').prop('disabled', false);
                    }
                },
                error: function () {
                    STU.showStatus('error', stuImport.strings.error);
                    $('#stu-import-btn').prop('disabled', false);
                }
            });
        },

        /**
         * Show a status message.
         *
         * @param {string} type    Status type: 'success', 'error', 'loading'.
         * @param {string} message Message text.
         */
        showStatus: function (type, message) {
            var $status = $('#stu-status');
            $status.removeClass('stu-status--success stu-status--error stu-status--loading');
            $status.addClass('stu-status--' + type);

            var prefix = '';
            if (type === 'loading') {
                prefix = '<span class="stu-spinner"></span>';
            }

            $status.html(prefix + message).show();
        },

        /**
         * Hide the status message.
         */
        hideStatus: function () {
            $('#stu-status').hide();
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function () {
        if ($('#stu-import-tool').length) {
            STU.init();
        }
    });

})(jQuery);
