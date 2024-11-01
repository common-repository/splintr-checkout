(function ($) {
    let currentSuccessUrl = new URL(window.location.href);
    $(document).ready(function () {
        function splintrVerify() {
            $.post(
                splintrCheckoutParams.ajaxUrl, {
                    action: 'splintr-verify',
                    inquiryId: currentSuccessUrl.searchParams.get('inquiry_id'),
                    orderId: currentSuccessUrl.searchParams.get('referenceNumber'),
                    wcOrderId: currentSuccessUrl.searchParams.get('wcOrderId'),
                }, function (response) {
                    if (response.verifySuccessUrl !== undefined) {
                        // If the order is authorised successfully, redirect to Woocommerce Thank you page
                        window.location.replace(response.verifySuccessUrl)
                    }
                }
            )
        }

        splintrVerify();
        // The ajax calls every 5s to check if the order is verified
        setInterval(splintrVerify, 5000);
    });

    // Redirect to cart page if the order can not be verified after 15s
    function splintrVerifyFailedRedirect() {
        window.location.replace(splintrCheckoutParams.splintrVerifyFailedUrl)
    }

    setTimeout(splintrVerifyFailedRedirect, 115000)
})(jQuery);
