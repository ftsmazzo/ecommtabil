<?php

return [
    "api_key" => getenv("OPENROUTER_API_KEY")
        ?: ($_ENV["OPENROUTER_API_KEY"] ?? "")
        ?: ($_SERVER["OPENROUTER_API_KEY"] ?? ""),
    // Modelo barato que recebe o texto já parseado pelo file-parser
    "model"   => getenv("OPENROUTER_MODEL") ?: "openai/gpt-4o-mini",
    // mistral-ocr | cloudflare-ai | native
    "pdf_engine" => getenv("OPENROUTER_PDF_ENGINE") ?: "mistral-ocr",
    "http_referer" => getenv("OPENROUTER_HTTP_REFERER")
        ?: (getenv("JWT_ISSUER") ?: "https://ecommtabil-app.kxryyk.easypanel.host"),
    "app_title" => getenv("OPENROUTER_APP_TITLE") ?: "E-commtabil",
];
