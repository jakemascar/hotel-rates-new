(function ($) {
        'use strict';

        var $admin = $('.hr-package-itinerary-admin').first();
        if ( !$admin.length ) return;

        var $days = $admin.find('.hr-package-itinerary-days');
        var $empty = $admin.find('.hr-package-itinerary-empty');
        var editorCounter = 0;

        function uniqueId(prefix) {
                editorCounter += 1;
                return prefix + '-' + Date.now() + '-' + editorCounter;
        }

        function editorValue($textarea) {
                var editor = window.tinymce && $textarea.attr('id') ? window.tinymce.get($textarea.attr('id')) : null;
                return editor ? editor.getContent() : String($textarea.val() || '');
        }

        function initializeEditor($textarea) {
                if ( !window.wp || !wp.editor || typeof wp.editor.initialize !== 'function' ) return;

                wp.editor.initialize($textarea.attr('id'), {
                        tinymce: {
                                wpautop: true,
                                toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo'
                        },
                        quicktags: true,
                        mediaButtons: false
                });
        }

        function removeEditor($textarea) {
                if ( window.wp && wp.editor && typeof wp.editor.remove === 'function' ) {
                        wp.editor.remove($textarea.attr('id'));
                }
        }

        function field(label, $control) {
                return $('<div class="hr-package-itinerary-field"></div>')
                        .append($('<label></label>').text(label))
                        .append($control);
        }

        function mediaItem(kind, item) {
                var isImage = kind === 'images';
                var $item = $('<div class="hr-package-itinerary-media-item"></div>');
                var $preview = $('<div class="hr-package-itinerary-media-preview"></div>');
                var $details = $('<div class="hr-package-itinerary-media-details"></div>');
                var previewUrl = item && item.preview_url ? String(item.preview_url) : '';

                $item.append($('<input type="hidden" class="hr-package-itinerary-attachment-id">').val(parseInt(item && item.attachment_id, 10) || 0));
                $preview.append(previewUrl ? $('<img alt="">').attr('src', previewUrl) : '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>');
                $item.append($preview);

                if ( isImage ) {
                        $details.append(field('Alternative text', $('<input type="text" class="hr-package-itinerary-image-alt">').val(item && item.alt ? item.alt : '')));
                        $details.append(field('Caption', $('<input type="text" class="hr-package-itinerary-image-caption">').val(item && item.caption ? item.caption : '')));
                } else {
                        $details.append(field('Icon heading', $('<input type="text" class="hr-package-itinerary-icon-title">').val(item && item.title ? item.title : '')));
                        $details.append(field('Short description', $('<textarea rows="2" class="hr-package-itinerary-icon-description"></textarea>').val(item && item.description ? item.description : '')));
                }

                $item.append($details);
                $item.append('<div class="hr-package-itinerary-media-actions"><button type="button" class="button-link hr-package-itinerary-media-handle" aria-label="Drag to reorder"><span class="dashicons dashicons-move"></span></button><button type="button" class="button-link-delete hr-package-itinerary-remove-media">Remove</button></div>');
                return $item;
        }

        function addMedia($day, kind, item) {
                var $list = $day.find('.hr-package-itinerary-' + kind + '-list');
                if ( $list.children().length >= 8 ) {
                        window.alert('A maximum of 8 ' + (kind === 'images' ? 'images' : 'icon items') + ' is allowed per itinerary day.');
                        return;
                }
                $list.append(mediaItem(kind, item || {}));
                reindex();
        }

        function openMedia($day, kind) {
                var gallery = kind === 'images';
                var remaining = 8 - $day.find('.hr-package-itinerary-' + kind + '-list').children().length;
                if ( remaining <= 0 ) {
                        window.alert('A maximum of 8 items is allowed per itinerary day.');
                        return;
                }
                if ( !window.wp || !wp.media ) {
                        window.alert('The WordPress Media Library is not available.');
                        return;
                }

                var frame = wp.media({
                        title: gallery ? 'Select itinerary images' : 'Select an icon image',
                        button: { text: gallery ? 'Add images' : 'Use icon' },
                        library: { type: 'image' },
                        multiple: gallery
                });

                frame.on('select', function () {
                        var selected = frame.state().get('selection').toJSON();
                        if ( selected.length > remaining ) window.alert('Only the first ' + remaining + ' selected image(s) were added.');

                        $.each(selected.slice(0, remaining), function (index, attachment) {
                                addMedia($day, kind, {
                                        attachment_id: attachment.id,
                                        preview_url: attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url,
                                        alt: attachment.alt || '',
                                        caption: attachment.caption || ''
                                });
                        });
                });
                frame.open();
        }

        function collectDay($day) {
                var data = {
                        title: String($day.find('.hr-package-itinerary-title').val() || ''),
                        summary: String($day.find('.hr-package-itinerary-summary').val() || ''),
                        description: editorValue($day.find('.hr-package-itinerary-description')),
                        note_title: String($day.find('.hr-package-itinerary-note-title').val() || ''),
                        note_content: editorValue($day.find('.hr-package-itinerary-note-content')),
                        images: [],
                        icons: []
                };

                $day.find('.hr-package-itinerary-images-list .hr-package-itinerary-media-item').each(function () {
                        var $item = $(this);
                        data.images.push({
                                attachment_id: parseInt($item.find('.hr-package-itinerary-attachment-id').val(), 10) || 0,
                                preview_url: $item.find('img').attr('src') || '',
                                alt: String($item.find('.hr-package-itinerary-image-alt').val() || ''),
                                caption: String($item.find('.hr-package-itinerary-image-caption').val() || '')
                        });
                });

                $day.find('.hr-package-itinerary-icons-list .hr-package-itinerary-media-item').each(function () {
                        var $item = $(this);
                        data.icons.push({
                                attachment_id: parseInt($item.find('.hr-package-itinerary-attachment-id').val(), 10) || 0,
                                preview_url: $item.find('img').attr('src') || '',
                                title: String($item.find('.hr-package-itinerary-icon-title').val() || ''),
                                description: String($item.find('.hr-package-itinerary-icon-description').val() || '')
                        });
                });
                return data;
        }

        function reindex() {
                $days.children('.hr-package-itinerary-day').each(function (dayIndex) {
                        var $day = $(this);
                        var base = 'package_itinerary_days[' + dayIndex + ']';
                        var title = String($day.find('.hr-package-itinerary-title').val() || '').trim();

                        $day.find('.hr-package-itinerary-day-number').text('Day ' + (dayIndex + 1) + (title ? ': ' + title : ''));
                        $day.find('.hr-package-itinerary-title').attr('name', base + '[title]');
                        $day.find('.hr-package-itinerary-summary').attr('name', base + '[summary]');
                        $day.find('.hr-package-itinerary-description').attr('name', base + '[description]');
                        $day.find('.hr-package-itinerary-note-title').attr('name', base + '[note_title]');
                        $day.find('.hr-package-itinerary-note-content').attr('name', base + '[note_content]');

                        $day.find('.hr-package-itinerary-images-list .hr-package-itinerary-media-item').each(function (index) {
                                var imageBase = base + '[images][' + index + ']';
                                $(this).find('.hr-package-itinerary-attachment-id').attr('name', imageBase + '[attachment_id]');
                                $(this).find('.hr-package-itinerary-image-alt').attr('name', imageBase + '[alt]');
                                $(this).find('.hr-package-itinerary-image-caption').attr('name', imageBase + '[caption]');
                        });

                        $day.find('.hr-package-itinerary-icons-list .hr-package-itinerary-media-item').each(function (index) {
                                var iconBase = base + '[icons][' + index + ']';
                                $(this).find('.hr-package-itinerary-attachment-id').attr('name', iconBase + '[attachment_id]');
                                $(this).find('.hr-package-itinerary-icon-title').attr('name', iconBase + '[title]');
                                $(this).find('.hr-package-itinerary-icon-description').attr('name', iconBase + '[description]');
                        });
                });
                $empty.toggleClass('is-hidden', $days.children().length > 0);
        }

        function initializeSorting($day) {
                $day.find('.hr-package-itinerary-media-list').sortable({
                        items: '> .hr-package-itinerary-media-item',
                        handle: '.hr-package-itinerary-media-handle',
                        placeholder: 'hr-package-itinerary-media-placeholder',
                        forcePlaceholderSize: true,
                        update: reindex
                });
        }

        function createDay(day, focus) {
                day = day || {};
                var descriptionId = uniqueId('hr-package-itinerary-description');
                var noteId = uniqueId('hr-package-itinerary-note-content');
                var $day = $('<section class="hr-package-itinerary-day"></section>');
                var $body = $('<div class="hr-package-itinerary-day-body"></div>');
                var $description = $('<textarea rows="8" class="hr-package-itinerary-description"></textarea>').attr('id', descriptionId).val(day.description || '');
                var $note = $('<textarea rows="5" class="hr-package-itinerary-note-content"></textarea>').attr('id', noteId).val(day.note_content || '');

                $day.append('<div class="hr-package-itinerary-day-header"><button type="button" class="hr-package-itinerary-day-toggle" aria-expanded="true"><span class="dashicons dashicons-menu hr-package-itinerary-day-handle" aria-hidden="true"></span><span class="hr-package-itinerary-day-number"></span><span class="dashicons dashicons-arrow-up-alt2 hr-package-itinerary-day-toggle-icon" aria-hidden="true"></span></button><div class="hr-package-itinerary-day-actions"><button type="button" class="button button-small hr-package-itinerary-duplicate-day">Duplicate</button><button type="button" class="button-link-delete hr-package-itinerary-remove-day">Remove</button></div></div>');

                $body.append(field('Day title', $('<input type="text" class="hr-package-itinerary-title">').val(day.title || '')));
                $body.append(field('Collapsed summary', $('<textarea rows="3" class="hr-package-itinerary-summary"></textarea>').val(day.summary || '')));
                $body.append(field('Full description', $description));
                $body.append(field('Travel Note heading (optional)', $('<input type="text" class="hr-package-itinerary-note-title">').val(day.note_title || '')));
                $body.append(field('Travel Note content (optional)', $note));
                $body.append('<section class="hr-package-itinerary-media-section"><div class="hr-package-itinerary-media-section__heading"><div><h4>Day images</h4><p>Required: 2–8 images. These will appear in the frontend slider.</p></div><button type="button" class="button hr-package-itinerary-add-images">Add Images</button></div><div class="hr-package-itinerary-media-list hr-package-itinerary-images-list"></div></section>');
                $body.append('<section class="hr-package-itinerary-media-section"><div class="hr-package-itinerary-media-section__heading"><div><h4>Day icon information</h4><p>Required: 2–8 icon items with a heading and description.</p></div><button type="button" class="button hr-package-itinerary-add-icon">Add Icon Item</button></div><div class="hr-package-itinerary-media-list hr-package-itinerary-icons-list"></div></section>');
                $day.append($body);
                $days.append($day);

                $.each($.isArray(day.images) ? day.images : [], function (index, image) { addMedia($day, 'images', image); });
                $.each($.isArray(day.icons) ? day.icons : [], function (index, icon) { addMedia($day, 'icons', icon); });
                initializeSorting($day);
                initializeEditor($description);
                initializeEditor($note);
                reindex();
                if ( focus ) $day.find('.hr-package-itinerary-title').focus();
        }

        function fail($day, message, $field) {
                $day.removeClass('is-collapsed').find('.hr-package-itinerary-day-toggle').attr('aria-expanded', 'true');
                window.alert(message);
                $day.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
                if ( $field && $field.length ) $field.focus();
        }

        function validate() {
                var valid = true;
                if ( window.tinymce ) window.tinymce.triggerSave();

                $days.children().each(function (index) {
                        var $day = $(this);
                        var data = collectDay($day);
                        var number = index + 1;

                        if ( !data.title.trim() ) {
                                fail($day, 'Itinerary Day ' + number + ' requires a title.', $day.find('.hr-package-itinerary-title'));
                                valid = false;
                                return false;
                        }
                        if ( !$('<div></div>').html(data.description).text().trim() ) {
                                fail($day, 'Itinerary Day ' + number + ' requires a description.', $day.find('.hr-package-itinerary-description'));
                                valid = false;
                                return false;
                        }
                        if ( data.images.length < 2 || data.images.length > 8 ) {
                                fail($day, 'Itinerary Day ' + number + ' must contain between 2 and 8 images.');
                                valid = false;
                                return false;
                        }
                        if ( data.icons.length < 2 || data.icons.length > 8 ) {
                                fail($day, 'Itinerary Day ' + number + ' must contain between 2 and 8 icon items.');
                                valid = false;
                                return false;
                        }
                        $.each(data.icons, function (iconIndex, icon) {
                                if ( !icon.title.trim() || !icon.description.trim() ) {
                                        fail($day, 'Icon item ' + (iconIndex + 1) + ' in Itinerary Day ' + number + ' requires a title and description.');
                                        valid = false;
                                        return false;
                                }
                        });
                        return valid;
                });
                return valid;
        }

        $days.sortable({
                items: '> .hr-package-itinerary-day',
                handle: '.hr-package-itinerary-day-handle',
                placeholder: 'hr-package-itinerary-day-placeholder',
                forcePlaceholderSize: true,
                update: reindex
        });

        $admin.on('click', '.hr-package-itinerary-add-day', function () { createDay({}, true); });
        $admin.on('input', '.hr-package-itinerary-title', reindex);
        $admin.on('click', '.hr-package-itinerary-day-toggle', function (event) {
                if ( $(event.target).closest('.hr-package-itinerary-day-handle').length ) return;
                var $day = $(this).closest('.hr-package-itinerary-day');
                $day.toggleClass('is-collapsed');
                $(this).attr('aria-expanded', $day.hasClass('is-collapsed') ? 'false' : 'true');
        });
        $admin.on('click', '.hr-package-itinerary-remove-day', function () {
                if ( !window.confirm('Remove this itinerary day?') ) return;
                var $day = $(this).closest('.hr-package-itinerary-day');
                removeEditor($day.find('.hr-package-itinerary-description'));
                removeEditor($day.find('.hr-package-itinerary-note-content'));
                $day.remove();
                reindex();
        });
        $admin.on('click', '.hr-package-itinerary-duplicate-day', function () {
                if ( window.tinymce ) window.tinymce.triggerSave();
                createDay(collectDay($(this).closest('.hr-package-itinerary-day')), true);
        });
        $admin.on('click', '.hr-package-itinerary-add-images', function () { openMedia($(this).closest('.hr-package-itinerary-day'), 'images'); });
        $admin.on('click', '.hr-package-itinerary-add-icon', function () { openMedia($(this).closest('.hr-package-itinerary-day'), 'icons'); });
        $admin.on('click', '.hr-package-itinerary-remove-media', function () { $(this).closest('.hr-package-itinerary-media-item').remove(); reindex(); });
        $('#hr-package-deal-form').on('submit', function (event) { if ( !validate() ) event.preventDefault(); });

        var existing = [];
        var node = document.getElementById('hr-package-itinerary-existing-data');
        try { existing = JSON.parse(node ? node.textContent : '[]') || []; } catch (error) { existing = []; }
        $.each(existing, function (index, day) { createDay(day, false); });
        reindex();
})(jQuery);
