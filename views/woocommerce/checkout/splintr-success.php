<?php
get_header();
?>
    <div id="splintr-success-overlay"></div>
    <div class="splintr-success">
        <div class="splintr-success__heading">
            <div class="splintr-success__heading__logo">
            </div>
            <p class="splintr-success__heading__text"><?php echo __( 'Payment Received by Splintr', 'splintr-checkout' ) ?></p>
        </div>
        <div class="splintr-success__content">
            <h4><?php echo __( "Please don't close this window.", 'splintr-checkout' ) ?></h4>
            <h4><?php echo __( 'Your order paying with Splintr is still under process.', 'splintr-checkout' ) ?></h4>
        </div>
    </div>
<?php
get_footer();

