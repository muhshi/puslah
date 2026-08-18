<?php

namespace App\Services;

class HtmlToWordXmlConverter
{
    protected static string $defaultFont = 'Aptos Display';
    protected static int $defaultSize = 24; // 12pt (in half-points)

    /**
     * Converts Rich Text HTML into Word OpenXML paragraphs and runs
     * suitable for injecting into a PHPWord TemplateProcessor placeholder.
     */
    public static function convert(?string $html, string $font = 'Aptos Display', int $size = 24): string
    {
        if (!$html || trim($html) === '') {
            return '';
        }

        self::$defaultFont = $font;
        self::$defaultSize = $size;

        // If string contains no HTML tags, treat as plain text and convert newlines to <p>
        if (!preg_match('/<[a-z][\s\S]*>/i', $html)) {
            $lines = preg_split('/\r\n|\r|\n/', $html);
            $wrapped = '';
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $wrapped .= '<p>' . htmlspecialchars($line) . '</p>';
                }
            }
            $html = $wrapped;
        }

        // Normalize non-breaking spaces and line breaks
        $html = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $html);
        $html = str_replace(["\r\n", "\r"], "\n", $html);

        // Wrap in a container div with UTF-8 encoding
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root) {
            return htmlspecialchars(strip_tags($html), ENT_XML1, 'UTF-8');
        }

        $paragraphs = [];
        self::processNodes($root, $paragraphs);

        // Filter out completely empty paragraphs
        $filteredParagraphs = [];
        foreach ($paragraphs as $p) {
            $cleanRuns = [];
            foreach ($p['runs'] as $run) {
                if (!empty($run['is_bullet']) || !empty($run['br']) || trim($run['text']) !== '') {
                    $cleanRuns[] = $run;
                }
            }
            if (!empty($cleanRuns)) {
                // Check if paragraph is not JUST a bullet with nothing else
                $hasRealContent = false;
                foreach ($cleanRuns as $r) {
                    if (empty($r['is_bullet']) && (trim($r['text']) !== '' || !empty($r['br']))) {
                        $hasRealContent = true;
                        break;
                    }
                }
                if ($hasRealContent) {
                    $p['runs'] = $cleanRuns;
                    $filteredParagraphs[] = $p;
                }
            }
        }

        if (empty($filteredParagraphs)) {
            return '';
        }

        return self::formatForTemplateProcessor($filteredParagraphs);
    }

    protected static function formatForTemplateProcessor(array $paragraphs): string
    {
        $xml = '</w:t></w:r>'; // Close initial template <w:r><w:t>

        $count = count($paragraphs);
        foreach ($paragraphs as $idx => $p) {
            if ($idx === 0) {
                // First paragraph: replace template's container paragraph properties
                $pPr = self::buildParagraphProperties($p);
                $xml .= '</w:p><w:p>' . $pPr . self::buildRunsXml($p['runs']);
                if ($count > 1) {
                    $xml .= '</w:p>';
                }
            } elseif ($idx === $count - 1) {
                // Last paragraph
                $xml .= self::buildParagraphOpening($p);
                $xml .= self::buildRunsXml($p['runs']);
                // Leave open so template's closing </w:t></w:r></w:p> matches
            } else {
                // Middle paragraphs
                $xml .= self::buildParagraphOpening($p);
                $xml .= self::buildRunsXml($p['runs']);
                $xml .= '</w:p>';
            }
        }

        // Open dummy <w:r><w:t> to consume the template's closing </w:t></w:r></w:p>
        $xml .= '<w:r><w:t>';

        return $xml;
    }

    protected static function buildParagraphProperties(array $p): string
    {
        $isList = !empty($p['is_list']);
        $level = $p['level'] ?? 1;
        
        // Indentation in twips (1 pt = 20 twips, 1 cm ≈ 567 twips, 0.25 in = 360 twips)
        $indentLeft = $isList ? ($level * 360) : 0;
        $hanging = $isList ? 240 : 0;
        
        $pPr = '<w:pPr>';
        if ($isList) {
            $pPr .= '<w:pStyle w:val="ListParagraph"/>';
            $pPr .= '<w:spacing w:before="20" w:after="40" w:line="276" w:lineRule="auto"/>';
            $pPr .= '<w:ind w:left="' . $indentLeft . '" w:hanging="' . $hanging . '"/>';
        } else {
            $pPr .= '<w:spacing w:before="40" w:after="80" w:line="276" w:lineRule="auto"/>';
            if ($indentLeft > 0) {
                $pPr .= '<w:ind w:left="' . $indentLeft . '"/>';
            }
        }
        $pPr .= '<w:jc w:val="both"/>';
        $pPr .= '<w:rPr><w:rFonts w:ascii="' . self::$defaultFont . '" w:eastAsia="Times New Roman" w:hAnsi="' . self::$defaultFont . '"/><w:sz w:val="' . self::$defaultSize . '"/><w:szCs w:val="' . self::$defaultSize . '"/></w:rPr>';
        $pPr .= '</w:pPr>';

        return $pPr;
    }

    protected static function buildParagraphOpening(array $p): string
    {
        return '<w:p>' . self::buildParagraphProperties($p);
    }

    protected static function buildRunsXml(array $runs): string
    {
        $xml = '';
        foreach ($runs as $run) {
            $text = $run['text'] ?? '';
            if ($text === '' && empty($run['br'])) {
                continue;
            }

            if (!empty($run['br'])) {
                $xml .= '<w:r><w:br/></w:r>';
                continue;
            }

            $rPr = '<w:rPr>';
            $rPr .= '<w:rFonts w:ascii="' . self::$defaultFont . '" w:eastAsia="Times New Roman" w:hAnsi="' . self::$defaultFont . '"/>';
            $rPr .= '<w:sz w:val="' . self::$defaultSize . '"/>';
            $rPr .= '<w:szCs w:val="' . self::$defaultSize . '"/>';
            
            if (!empty($run['bold'])) {
                $rPr .= '<w:b/><w:bCs/>';
            }
            if (!empty($run['italic'])) {
                $rPr .= '<w:i/><w:iCs/>';
            }
            if (!empty($run['underline'])) {
                $rPr .= '<w:u w:val="single"/>';
            }
            if (!empty($run['strike'])) {
                $rPr .= '<w:strike/>';
            }
            $rPr .= '</w:rPr>';

            $escapedText = htmlspecialchars($text, ENT_XML1, 'UTF-8');
            
            $xml .= '<w:r>' . $rPr . '<w:t xml:space="preserve">' . $escapedText . '</w:t></w:r>';
        }
        return $xml;
    }

    protected static function processNodes(\DOMNode $node, array &$paragraphs, array $context = []): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = $child->textContent;
                
                // Ignore newline + indentation whitespace between HTML block tags
                if (trim($text) === '') {
                    if (str_contains($text, "\n") || str_contains($text, "\r")) {
                        continue;
                    }
                    if (!empty($context['in_p'])) {
                        self::addRunToCurrentParagraph($paragraphs, ' ', $context);
                    }
                    continue;
                }

                // If text starts/ends with spaces, preserve single space
                $hasLeading = (strlen($text) > 0 && ctype_space($text[0]));
                $hasTrailing = (strlen($text) > 1 && ctype_space($text[strlen($text) - 1]));
                $cleaned = preg_replace('/\s+/', ' ', trim($text));
                
                if ($cleaned !== '') {
                    $fullText = ($hasLeading ? ' ' : '') . $cleaned . ($hasTrailing ? ' ' : '');
                    self::addRunToCurrentParagraph($paragraphs, $fullText, $context);
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);
                
                if (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'blockquote'])) {
                    $newContext = $context;
                    $newContext['in_p'] = true;
                    if (in_array($tag, ['h1', 'h2', 'h3', 'h4'])) {
                        $newContext['bold'] = true;
                    }
                    
                    $paragraphs[] = [
                        'style' => $tag,
                        'is_list' => false,
                        'level' => $context['list_level'] ?? 0,
                        'runs' => [],
                    ];
                    
                    self::processNodes($child, $paragraphs, $newContext);
                } elseif (in_array($tag, ['ul', 'ol'])) {
                    $newContext = $context;
                    $newContext['list_type'] = $tag;
                    $newContext['list_level'] = ($context['list_level'] ?? 0) + 1;
                    $newContext['list_index'] = 0;
                    
                    self::processNodes($child, $paragraphs, $newContext);
                } elseif ($tag === 'li') {
                    $listType = $context['list_type'] ?? 'ul';
                    $level = $context['list_level'] ?? 1;
                    $index = ($context['list_index'] ?? 0) + 1;
                    $context['list_index'] = $index;
                    
                    // Determine bullet symbol with trailing space
                    if ($listType === 'ol') {
                        $bulletSymbol = match($level) {
                            2 => chr(96 + ($index <= 26 ? $index : 1)) . '. ',
                            3 => strtolower(self::toRoman($index)) . '. ',
                            default => "{$index}. ",
                        };
                    } else {
                        // Unordered bullet
                        $bulletSymbol = match($level) {
                            1 => "• ",
                            2 => "– ",
                            default => "▪ ",
                        };
                    }

                    // Create new list item paragraph
                    $paragraphs[] = [
                        'style' => 'ListParagraph',
                        'is_list' => true,
                        'level' => $level,
                        'runs' => [
                            ['text' => $bulletSymbol, 'bold' => false, 'is_bullet' => true]
                        ],
                    ];

                    $newContext = $context;
                    $newContext['in_p'] = true;
                    
                    self::processNodes($child, $paragraphs, $newContext);
                } elseif (in_array($tag, ['strong', 'b'])) {
                    $newContext = $context;
                    $newContext['bold'] = true;
                    $newContext['in_p'] = true;
                    self::processNodes($child, $paragraphs, $newContext);
                } elseif (in_array($tag, ['em', 'i'])) {
                    $newContext = $context;
                    $newContext['italic'] = true;
                    $newContext['in_p'] = true;
                    self::processNodes($child, $paragraphs, $newContext);
                } elseif ($tag === 'u') {
                    $newContext = $context;
                    $newContext['underline'] = true;
                    $newContext['in_p'] = true;
                    self::processNodes($child, $paragraphs, $newContext);
                } elseif (in_array($tag, ['s', 'strike', 'del'])) {
                    $newContext = $context;
                    $newContext['strike'] = true;
                    $newContext['in_p'] = true;
                    self::processNodes($child, $paragraphs, $newContext);
                } elseif ($tag === 'br') {
                    self::addRunToCurrentParagraph($paragraphs, '', array_merge($context, ['br' => true]));
                } else {
                    self::processNodes($child, $paragraphs, $context);
                }
            }
        }
    }

    protected static function addRunToCurrentParagraph(array &$paragraphs, string $text, array $context): void
    {
        if (empty($paragraphs)) {
            $paragraphs[] = [
                'style' => 'Normal',
                'is_list' => false,
                'level' => 0,
                'runs' => []
            ];
        }

        $lastIdx = count($paragraphs) - 1;
        $paragraphs[$lastIdx]['runs'][] = [
            'text' => $text,
            'bold' => !empty($context['bold']),
            'italic' => !empty($context['italic']),
            'underline' => !empty($context['underline']),
            'strike' => !empty($context['strike']),
            'br' => !empty($context['br']),
        ];
    }

    protected static function toRoman(int $num): string
    {
        $map = [
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        ];
        $result = '';
        foreach ($map as $roman => $value) {
            while ($num >= $value) {
                $result .= $roman;
                $num -= $value;
            }
        }
        return $result ?: '1';
    }
}
