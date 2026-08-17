<?php

return [
    'flash' => [
        // alert | notify | toastr | both
        'renderer' => 'notify',

        'notify' => [
            'duration' => 5,
            'x' => 'right',
            'y' => 'bottom',
            'close' => true,
        ],

        'toastr' => [
            'title' => '',
            'side' => 'top right',
            'duration' => 5000,
            'extras' => [],
        ],
    ],
];
