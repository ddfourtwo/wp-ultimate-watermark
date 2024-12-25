jQuery(document).ready(function($) {
    // Handle purchase button click in popup
    $(document).on('click', '.yprm-popup .purchase', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var attachmentId = $btn.data('attachment-id');

        if ($btn.hasClass('loading')) {
            return;
        }

        $btn.addClass('loading');
        var originalBackground = $btn.css('background');
        $btn.css('background', 'rgba(204, 204, 204, 0.2)');

        $.ajax({
            url: ulwm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'ulwm_add_to_cart',
                attachment_id: attachmentId,
                nonce: ulwm_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    $btn.css('background', 'rgba(40, 167, 69, 0.2)');
                    setTimeout(function() {
                        window.location.href = response.data.cart_url;
                    }, 1000);
                } else {
                    $btn.css('background', 'rgba(220, 53, 69, 0.2)');
                    setTimeout(function() {
                        $btn.css('background', originalBackground);
                        $btn.removeClass('loading');
                    }, 2000);
                }
            },
            error: function() {
                $btn.css('background', 'rgba(220, 53, 69, 0.2)');
                setTimeout(function() {
                    $btn.css('background', originalBackground);
                    $btn.removeClass('loading');
                }, 2000);
            }
        });
    });
});
