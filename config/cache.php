<?php
return [
    "engine" => "auto", // auto | file | apcu
    "prefix" => "",
    "path"   => "storage/cache/",
    "timeout"=> 525600, // in minutes
    "ext"    => ".txt",
    "format" => "serialize" // serialize | json
];
