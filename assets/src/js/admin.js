/* Splintr Admin JS */

// 'use strict';
let splintrEnvToggle = document.getElementById('woocommerce_splint-payment-gateway_environment');
let valueSelected;

// Display/Hide fields on selected
function getValueSelected() {
    valueSelected = splintrEnvToggle.value;

    if ('live_mode' === valueSelected) {
        document.querySelector('#woocommerce_splint-payment-gateway_live_base_api_url').closest('tr').style.display = 'table-row';
        document.querySelector('#woocommerce_splint-payment-gateway_live_merchant_id').closest('tr').style.display = 'table-row';
        document.querySelector('#woocommerce_splint-payment-gateway_live_merchant_name').closest('tr').style.display = 'table-row';
        document.querySelector('#woocommerce_splint-payment-gateway_sandbox_base_api_url').closest('tr').style.display = 'none';
        document.querySelector('#woocommerce_splint-payment-gateway_sandbox_merchant_id').closest('tr').style.display = 'none';
        document.querySelector('#woocommerce_splint-payment-gateway_sandbox_merchant_name').closest('tr').style.display = 'none';

    } else if ('sandbox_mode' === valueSelected) {
        document.querySelector('#woocommerce_splint-payment-gateway_live_base_api_url').closest('tr').style.display = 'none';
        document.querySelector('#woocommerce_splint-payment-gateway_live_merchant_id').closest('tr').style.display = 'none';
        document.querySelector('#woocommerce_splint-payment-gateway_live_merchant_name').closest('tr').style.display = 'none';
        document.querySelector('#woocommerce_splint-payment-gateway_sandbox_base_api_url').closest('tr').style.display = 'table-row';
        document.querySelector('#woocommerce_splint-payment-gateway_sandbox_merchant_id').closest('tr').style.display = 'table-row';
        document.querySelector('#woocommerce_splint-payment-gateway_sandbox_merchant_name').closest('tr').style.display = 'table-row';
    }

}

// Get the selected value on change
splintrEnvToggle.addEventListener('change', getValueSelected);

// Get the selected value on save
window.addEventListener('load', getValueSelected);



