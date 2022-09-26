<?php

return [
    'router' => [
        'mode' => 1, //0单级 1分级
    ],
    'url' => [
        'mode' => 2, //0普通模式 1PATHINFO模式 2REWRITE模式 3兼容模式
        'ext' => '/',
    ],
    'default_return_type' => '__TYPE__',
];
