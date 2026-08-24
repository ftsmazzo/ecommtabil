<?php
$openaiKey = getenv("OPENAI_API_KEY")
    ?: ($_ENV["OPENAI_API_KEY"] ?? "")
    ?: ($_SERVER["OPENAI_API_KEY"] ?? "");

return [
    "openai_key" => $openaiKey,
    "model"      => getenv("OPENAI_MODEL") ?: "gpt-4.1-mini",
];
