<?php

$textDomain = 'splintr';

$config = [
    'version' => SPLINTR_CHECKOUT_VERSION,
    'basePath' => __DIR__,
    'baseUrl' => plugins_url(null, __FILE__),
    'textDomain' => $textDomain,
    'services' => [
    ],
];

return $config;
