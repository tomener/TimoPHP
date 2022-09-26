<?php

return [
    'env' => 'dev',
    'app_debug' => true,
    'encryption' => [
        'aes' => [
            'key' => '__RANDOM_16__',
            'method' => 'AES-128-CBC'
        ],
    ],
    'pwd_salt' => '__RANDOM_6__',
];
