jQuery(document).ready(function($) {
    // Check if we have our required variables
    if (typeof ulwm_vars === 'undefined') {
        console.error('Required variables not found. Make sure the script is properly localized.');
        return;
    }

    // Log available variables for debugging
    console.log('AJAX URL:', ulwm_vars.ajax_url);
    console.log('Nonce available:', !!ulwm_vars.nonce);

    // Function to add purchase button
    function addPurchaseButton() {
        var $popup = $('.yprm-popup-block');
        if ($popup.length && !$popup.find('.purchase').length) {
            var $buttons = $popup.find('.buttons');
            if ($buttons.length) {
                // Get the image URL
                var $currentImage = $popup.find('.item.build[data-image]');
                if ($currentImage.length) {
                    try {
                        var imageData = $currentImage.data('image');
                        if (imageData && imageData.url) {
                            console.log('Found image URL:', imageData.url);
                            // Insert purchase button before the read-more link
                            var $readMore = $buttons.find('.read-more');
                            var $purchaseBtn = $('<div class="purchase" style="opacity: 1;">Add to Cart</div>');
                            
                            if ($readMore.length) {
                                $purchaseBtn.insertBefore($readMore);
                            } else {
                                $buttons.append($purchaseBtn);
                            }

                            // Store the image URL as data attribute
                            $purchaseBtn.attr('data-image-url', imageData.url);
                        } else {
                            console.error('No image URL found in data:', imageData);
                        }
                    } catch (error) {
                        console.error('Error processing image data:', error);
                    }
                } else {
                    console.error('No image element found in popup');
                }
            } else {
                console.error('No buttons container found in popup');
            }
        }
    }

    // Watch for popup opening
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                var $popup = $(mutation.target);
                if ($popup.hasClass('yprm-popup-block') && $popup.css('opacity') === '1') {
                    addPurchaseButton();
                }
            }
        });
    });

    // Start observing
    var $body = $('body')[0];
    observer.observe($body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style']
    });

    // Handle purchase button click
    $(document).on('click', '.yprm-popup-block .purchase', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var imageUrl = $btn.data('image-url');

        if (!ulwm_vars || !ulwm_vars.ajax_url || !ulwm_vars.nonce) {
            console.error('Required AJAX variables not found:', {
                ulwm_vars_exists: !!ulwm_vars,
                ajax_url_exists: !!(ulwm_vars && ulwm_vars.ajax_url),
                nonce_exists: !!(ulwm_vars && ulwm_vars.nonce)
            });
            handleError('Configuration error');
            return;
        }

        if ($btn.hasClass('loading') || !imageUrl) {
            console.error('Button click prevented:', $btn.hasClass('loading') ? 'Already loading' : 'No image URL');
            return;
        }

        console.log('Processing image URL:', imageUrl);
        console.log('AJAX request data:', {
            action: 'ulwm_get_attachment_id',
            image_url: imageUrl,
            nonce: ulwm_vars.nonce
        });

        $btn.addClass('loading').text('Adding...');
        var originalBackground = $btn.css('background');
        $btn.css('background', 'rgba(204, 204, 204, 0.2)');

        // First get the attachment ID from the URL
        $.ajax({
            url: ulwm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'ulwm_get_attachment_id',
                image_url: imageUrl,
                nonce: ulwm_vars.nonce
            },
            success: function(response) {
                console.log('Attachment ID response:', response);
                if (response && response.success && response.data && response.data.attachment_id) {
                    // Now add to cart
                    console.log('Adding to cart with attachment ID:', response.data.attachment_id);
                    $.ajax({
                        url: ulwm_vars.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ulwm_add_to_cart',
                            attachment_id: response.data.attachment_id,
                            nonce: ulwm_vars.nonce
                        },
                        success: function(cartResponse) {
                            console.log('Add to cart response:', cartResponse);
                            if (cartResponse && cartResponse.success && cartResponse.data) {
                                $btn.css('background', 'rgba(40, 167, 69, 0.2)').text('Added!');
                                setTimeout(function() {
                                    window.location.href = cartResponse.data.cart_url;
                                }, 1000);
                            } else {
                                handleError('Failed to add to cart: ' + (cartResponse && cartResponse.data ? cartResponse.data : 'Unknown error'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Cart request failed:', {
                                status: status,
                                error: error,
                                response: xhr.responseText
                            });
                            handleError('Cart request failed: ' + error);
                        }
                    });
                } else {
                    handleError('Invalid attachment ID response: ' + (response && response.data ? response.data : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Attachment ID request failed:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                handleError('Attachment ID request failed: ' + error);
            }
        });

        function handleError(errorMsg) {
            console.error(errorMsg);
            $btn.css('background', 'rgba(220, 53, 69, 0.2)').text('Error');
            setTimeout(function() {
                $btn.css('background', originalBackground).text('Add to Cart');
                $btn.removeClass('loading');
            }, 2000);
        }
    });
});
