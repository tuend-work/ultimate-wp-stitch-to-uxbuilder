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
         * Handle selected file.
         */
        handleFile: function (file, $display) {
            if (!file.name.match(/\.(html?|zip)$/i)) {
                this.showStatus('error', 'Please select an .html or .zip file.');
                return;
            }

            STU.file = file;
            $display.text('✓ ' + file.name).show();

            if (file.name.match(/\.html?$/i)) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#stu-html-input').val(e.target.result);
                };
                reader.readAsText(file);
            }
        },

        /**
         * Preview button handler.
         */
        bindPreview: function () {
            $('#stu-preview-btn').on('click', function () {
                STU.doPreview();
            });
        },

        /**
         * Send HTML or File to the server for parsing.
         */
        doPreview: function () {
            var html = $('#stu-html-input').val().trim();
            
            if (!html && !STU.file) {
                STU.showStatus('error', stuImport.strings.emptyHtml);
                return;
            }

            this.showStatus('loading', stuImport.strings.parsing);
            this.isPreviewReady = false;
            $('#stu-import-btn').prop('disabled', true);

            var formData = new FormData();
            formData.append('action', 'stu_preview_import');
            formData.append('nonce', stuImport.nonce);
            formData.append('post_id', stuImport.postId);

            if (STU.file) {
                formData.append('file', STU.file);
            } else {
                formData.append('html', html);
            }

            $.ajax({
                url: stuImport.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
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
         * Render multiple sections preview.
         */
        renderPreview: function (data) {
            var $area = $('#stu-preview-area');
            var $shortcode = $('#stu-shortcode-preview');
            
            // Clear existing tables but keep placeholder
            $area.find('.stu-section-preview').remove();

            if (!data.sections || data.sections.length === 0) {
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

            if (data.is_duplicate) {
                $('#stu-duplicate-warning').show();
            } else {
                $('#stu-duplicate-warning').hide();
            }

            // Create individual tables for each section
            data.sections.forEach(function (section, index) {
                var $secDiv = $('<div class="stu-section-preview">');
                $secDiv.append($('<h5>').text('Section ' + (index + 1)));
                
                var $table = $('<table class="widefat"><thead><tr><th>Slot</th><th>Type</th><th>Content</th><th>Dynamic Source</th></tr></thead><tbody></tbody></table>');
                var $tbody = $table.find('tbody');

                if (section.elements && section.elements.length > 0) {
                    section.elements.forEach(function (el) {
                        var typeLabel = el.type.replace('ux_field_', '').toUpperCase();
                        var content = el.value || el.src || (el.label ? el.label + ' → ' + el.href : '');
                        var sourceType = el.type.replace('ux_field_', '');
                        if (sourceType === 'text') { sourceType = 'text'; } // mapping

                        var sources = stuImport.dynamicSources[sourceType] || {};
                        var $select = $('<select>').attr('data-slot', el.slot);
                        $.each(sources, function (val, lbl) {
                            $select.append($('<option>').val(val).text(lbl));
                        });
                        $select.append($('<option>').val('acf:custom').text('ACF Custom Field…'));

                        var $row = $('<tr>');
                        $row.append($('<td>').append($('<span class="stu-slot-name">').text(el.slot)));
                        $row.append($('<td>').append($('<span class="stu-type-badge stu-type-badge--' + sourceType + '">').text(typeLabel)));
                        $row.append($('<td>').append($('<span class="stu-content-preview">').text(content).attr('title', content)));
                        $row.append($('<td>').append($select));
                        $tbody.append($row);
                    });
                } else {
                    $tbody.append('<tr><td colspan="4"><i>No slots in this block (Raw HTML only)</i></td></tr>');
                }

                $secDiv.append($table);
                // Insert before shortcode preview
                $('#stu-shortcode-preview').before($secDiv);
            });

            // Global select change handler
            $('.stu-section-preview select').on('change', function () {
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
                STU.updateShortcodePreview();
            });

            $shortcode.text(data.shortcode);
            $area.slideDown(300);

            if (!data.is_duplicate) {
                STU.isPreviewReady = true;
                $('#stu-import-btn').prop('disabled', false);
            }
        },

        /**
         * Update shortcode preview.
         */
        updateShortcodePreview: function () {
            var $shortcode = $('#stu-shortcode-preview');
            // Re-fetch from server or simulate multi-section update
            // For simplicity in UX, we'll just indicate it will apply on import
            // or we could do a more complex regex replacement over the whole block.
            // Let's do a simplified approach:
            var currentText = STU.parsedData.shortcode;
            
            $('.stu-section-preview select').each(function () {
                var slot = $(this).data('slot');
                var source = $(this).val();
                if (source) {
                    var regex = new RegExp('\\[ux_field_([a-z]+)\\s+([^\]]*slot="' + slot + '"[^\]]*)dynamic_enabled="0"', 'g');
                    if (currentText.indexOf('ux_field_link') !== -1 && slot.startsWith('link_')) {
                        currentText = currentText.replace(regex, '[ux_field_$1 $2dynamic_enabled="1" dynamic_href="' + source + '"');
                    } else {
                        currentText = currentText.replace(regex, '[ux_field_$1 $2dynamic_enabled="1" dynamic_source="' + source + '"');
                    }
                }
            });

            $shortcode.text(currentText);
        },

        /**
         * Import button handler.
         */
        bindImport: function () {
            $('#stu-import-btn').on('click', function () {
                if (!STU.isPreviewReady) return;
                if (!confirm(stuImport.strings.confirmImport)) return;
                STU.doImport();
            });
        },

        /**
         * Send import request.
         */
        doImport: function () {
            this.showStatus('loading', stuImport.strings.importing);
            $('#stu-import-btn').prop('disabled', true);

            var dynamicSources = {};
            $('.stu-section-preview select').each(function () {
                var slot = $(this).data('slot');
                var source = $(this).val();
                if (source) {
                    dynamicSources[slot] = source;
                }
            });

            var downloadImages = $('#stu-download-images').is(':checked') ? '1' : '0';

            var formData = new FormData();
            formData.append('action', 'stu_confirm_import');
            formData.append('nonce', stuImport.nonce);
            formData.append('post_id', stuImport.postId);
            formData.append('dynamic_sources', JSON.stringify(dynamicSources));
            formData.append('download_images', downloadImages);

            if (STU.file) {
                formData.append('file', STU.file);
            } else {
                formData.append('html', $('#stu-html-input').val());
            }

            $.ajax({
                url: stuImport.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        STU.showStatus('success', response.data.message || stuImport.strings.success);
                        $('#stu-shortcode-preview').text(response.data.shortcode);
                        STU.isPreviewReady = false;
                        STU.file = null;
                        $('#stu-html-input').val('');
                        $('#stu-file-name').hide();
                        $('#stu-import-btn').prop('disabled', true);

                        if (confirm('Import successful! Would you like to reload the page to see the new elements in UX Builder?')) {
                            window.location.reload();
                        }
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
