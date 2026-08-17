<?php
return [
    'algo' => PASSWORD_BCRYPT, // ou PASSWORD_DEFAULT / PASSWORD_ARGON2ID
    'option' => [
        'cost' => 10, // quanto maior, mais seguro (mas mais lento)
    ],
];