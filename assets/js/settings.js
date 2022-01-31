jQuery(document).ready(function ($) {

    var selectedElement;
    var watermarkFileUpload = {
        frame: function (el) {
            if (this._frameWatermark)
                return this._frameWatermark;

            this._frameWatermark = wp.media({
                title: ultimateWatermarkSettings.title,
                frame: ultimateWatermarkSettings.frame,
                button: ultimateWatermarkSettings.button,
                multiple: ultimateWatermarkSettings.multiple,
                library: {
                    type: 'image'
                }
            });

            this._frameWatermark.on('open', this.updateFrame).state('library').on('select', this.select);
            return this._frameWatermark;
        },
        select: function () {
            var _that = this;
            var attachment = this.frame.state().get('selection').first();

            var elementTd = $(selectedElement).closest('td');
            selectedElement = null;
            if ($.inArray(attachment.attributes.mime, ['image/gif', 'image/jpg', 'image/jpeg', 'image/png']) !== -1) {

                elementTd.find('input.attachment_id').val(attachment.attributes.id);

                elementTd.find('.preview-image').find('img').attr('src', attachment.attributes.url);

                elementTd.find('.preview-image').show();

                elementTd.find('.ultimate_watermark_remove_image_button').removeAttr('disabled');
                var img = new Image();
                img.src = attachment.attributes.url;
                img.onload = function () {
                    elementTd.find('.preview-image').find('p').html(ultimateWatermarkSettings.originalSize + ': ' + this.width + ' ' + ultimateWatermarkSettings.px + ' / ' + this.height + ' ' + ultimateWatermarkSettings.px);
                }

            } else {

                elementTd.find('.ultimate_watermark_remove_image_button').attr('disabled', 'true');
                elementTd.find('input.attachment_id').val(0);
                elementTd.find('.preview-image').hide();
                elementTd.find('.preview-image').find('p').html('<strong>' + ultimateWatermarkSettings.notAllowedImg + '</strong>');

            }
        },
        init: function () {
            var _that = this;
            $('body').on('click', '.ultimate_watermark_upload_image_button', function (e) {
                e.preventDefault();
                selectedElement = $(this);
                _that.frame().open();
            });
            _that.initSlider();
        },
        initSlider: function () {

            $(".ultimate-watermark-range-slider").slider({
                range: "max",
                min: 1,
                max: 10,
                value: 2,
                slide: function (event, ui) {
                    $("#amount").val(ui.value);
                }
            });
        }
    };

    watermarkFileUpload.init();

    $(document).on('click', '.ultimate_watermark_remove_image_button', function (event) {
        $(this).attr('disabled', 'true');
        $(this).closest('td').find('input.attachment_id').val(0);
        $(this).closest('td').find('.preview-image').hide();
    });

});