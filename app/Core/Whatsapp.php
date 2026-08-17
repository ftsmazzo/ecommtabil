<?php

namespace App\Core;

use DOMDocument;

class Whatsapp
{
    /**
     * Converte HTML do TinyMCE para formato WhatsApp.
     */
    public static function toWhatsApp(string $html): string
    {
        // Forçar encoding para DOMDocument (sem deprecated do mb_convert_encoding)
        $html = '<?xml encoding="UTF-8">' . $html;

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();

        $output = self::processNodes($doc->childNodes);

        // Normalizações
        $output = preg_replace("/\r\n|\r/", "\n", $output);
        $output = preg_replace("/\n{3,}/", "\n\n", $output);

        return trim($output);
    }


    /**
     * Converte texto em formato WhatsApp para HTML (para reexibir no TinyMCE)
     */
    public static function toHtml(string $text): string
    {
        // Protege caracteres HTML
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Negrito
        $text = preg_replace('/\*(.*?)\*/s', '<strong>$1</strong>', $text);

        // Itálico
        $text = preg_replace('/_(.*?)_/s', '<em>$1</em>', $text);

        // Tachado
        $text = preg_replace('/~(.*?)~/s', '<span style="text-decoration: line-through;">$1</span>', $text);

        // Links: transforma URLs em <a>
        $text = preg_replace(
            '/(https?:\/\/[^\s]+)/i',
            '<a href="$1" target="_blank">$1</a>',
            $text
        );

        // Quebras de linha → <br>
        return nl2br($text);
    }


    /* ===========================
       Funções internas auxiliares
       =========================== */

    private static function processNodes($nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= self::processNode($node);
        }
        return $text;
    }

    private static function processNode($node): string
    {
        // Text node
        if ($node->nodeType === XML_TEXT_NODE) {
            return preg_replace('/[ \t]{2,}/', ' ', $node->nodeValue);
        }

        // Element node
        if ($node->nodeType === XML_ELEMENT_NODE) {

            $tag = strtolower($node->nodeName);

            switch ($tag) {
                case 'br':
                    return "\n";

                case 'p':
                    return self::processNodes($node->childNodes) . "\n";

                // Negrito
                case 'b':
                case 'strong':
                    return self::wrap('*', self::processNodes($node->childNodes));

                // Itálico
                case 'i':
                case 'em':
                    return self::wrap('_', self::processNodes($node->childNodes));

                // Tachado
                case 'del':
                case 's':
                case 'strike':
                    return self::wrap('~', self::processNodes($node->childNodes));

                // Span com line-through
                case 'span':
                    $style = $node->getAttribute('style');
                    if (preg_match('/text-decoration\s*:\s*line-?through/i', $style)) {
                        return self::wrap('~', self::processNodes($node->childNodes));
                    }
                    return self::processNodes($node->childNodes);

                // Links → só a URL
                case 'a':
                    $href = $node->getAttribute('href');
                    return $href ?: self::processNodes($node->childNodes);

                // Emojis (via <img alt="😎">)
                case 'img':
                    $alt = $node->getAttribute('alt');
                    return $alt ?: '';

                // Underline → WhatsApp não suporta
                case 'u':
                    return self::processNodes($node->childNodes);

                // Monoespaçado
                case 'code':
                case 'pre':
                    return '`' . trim(self::processNodes($node->childNodes)) . '`';

                default:
                    return self::processNodes($node->childNodes);
            }
        }

        return '';
    }


    private static function wrap(string $char, string $content): string
    {
        $content = trim($content);
        return $content === '' ? '' : $char . $content . $char;
    }
}
