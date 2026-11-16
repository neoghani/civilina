<?php

declare(strict_types=1);

/*
 * Civilina writing syntax for Journal articles (milestone M2).
 *
 * The back-office stores articles as plain text in this syntax and turns it
 * into the exact HTML of the site. Only the constructs below exist: no raw
 * HTML is ever accepted, so an article cannot inject markup or scripts.
 *
 *   ## Titre de section {#ancre}     section (h2), listed in the summary
 *   ### Sous-titre                    h3
 *   Paragraphe sur une ou plusieurs lignes, séparé par une ligne vide.
 *   **gras**  *italique*  ^exposant^  [texte du lien](/journal/slug/)
 *   - élément de liste                1. liste numérotée
 *   - [ ] point de checklist
 *   | En-tête | En-tête |             tableau (2e ligne : |---|---|)
 *   {.table-wrap}                     ligne avant un tableau large (défilement sur mobile)
 *   :::retenir   :::exemple   :::eviter  [titre personnalisé]  …  :::   encadrés
 *   \*  \[  \]  \^  \|  \\             caractères littéraux
 */

const CIVILINA_BOXES = [
    'retenir' => ['box-key', 'À retenir'],
    'exemple' => ['box-ex', 'Exemple type'],
    'eviter' => ['box-warn', 'À éviter'],
];

function civilina_md_slug(string $text): string
{
    $map = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ú' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae'];
    $slug = strtr(mb_strtolower(strip_tags($text)), $map);
    $slug = (string) preg_replace('~[^a-z0-9]+~', '-', $slug);
    return trim($slug, '-');
}

/* ---------- Inline ---------- */

function civilina_md_inline(string $text): string
{
    $escaped = [];
    $text = (string) preg_replace_callback('~\\\\([\\\\*\[\]()^|])~', static function (array $m) use (&$escaped): string {
        $key = "\u{E000}" . count($escaped) . "\u{E001}";
        $escaped[$key] = htmlspecialchars($m[1], ENT_NOQUOTES, 'UTF-8');
        return $key;
    }, $text);

    $out = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

    $out = (string) preg_replace_callback('~\[([^\]]+)\]\(([^)\s]+)\)~u', static function (array $m): string {
        $url = html_entity_decode($m[2], ENT_NOQUOTES, 'UTF-8');
        if (!preg_match('~^(https?://|mailto:|/|#)~', $url)) {
            return $m[0];
        }
        $external = preg_match('~^https?://~', $url) && !preg_match('~^https?://(www\.)?civilina\.com(/|$)~', $url);
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="color:var(--blue);text-decoration:underline"'
            . ($external ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
    }, $out);
    $out = (string) preg_replace('~\*\*(.+?)\*\*~su', '<strong>$1</strong>', $out);
    $out = (string) preg_replace('~(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])~su', '<em>$1</em>', $out);
    $out = (string) preg_replace('~\^([^\^\s][^\^]*)\^~u', '<sup>$1</sup>', $out);

    return strtr($out, $escaped);
}

/* ---------- Blocks ---------- */

function civilina_md_is_block_start(string $line): bool
{
    return (bool) preg_match('~^(#{2,3}\s|:::|\{\.table-wrap\}$|\||- |\d+\.\s)~', $line);
}

/** @return list<array<string, mixed>> */
function civilina_md_parse(array $lines): array
{
    $blocks = [];
    $count = count($lines);
    $wrapNext = false;
    $i = 0;
    while ($i < $count) {
        $line = rtrim((string) $lines[$i]);
        if (trim($line) === '') {
            $i++;
            continue;
        }

        if (preg_match('~^(#{2,3})\s+(.+?)(?:\s+\{#([a-z0-9-]+)\})?\s*$~u', $line, $m)) {
            $blocks[] = ['type' => strlen($m[1]) === 2 ? 'h2' : 'h3', 'text' => $m[2], 'id' => $m[3] ?? ''];
            $i++;
            continue;
        }

        if (preg_match('~^:::\s*(retenir|exemple|eviter)\s*(.*)$~u', $line, $m)) {
            $end = $i + 1;
            while ($end < $count && trim((string) $lines[$end]) !== ':::') {
                $end++;
            }
            $blocks[] = ['type' => 'box', 'kind' => $m[1], 'label' => trim($m[2]), 'blocks' => civilina_md_parse(array_slice($lines, $i + 1, $end - $i - 1))];
            $i = $end + 1;
            continue;
        }

        if ($line === '{.table-wrap}') {
            $wrapNext = true;
            $i++;
            continue;
        }

        if (str_starts_with($line, '|')) {
            $rows = [];
            while ($i < $count && str_starts_with(rtrim((string) $lines[$i]), '|')) {
                $row = trim(rtrim((string) $lines[$i]));
                $i++;
                if (preg_match('~^\|[\s:|-]+\|$~', $row)) {
                    continue; // header separator
                }
                $cells = preg_split('~(?<!\\\\)\|~', trim($row, '|')) ?: [];
                $rows[] = array_map('trim', $cells);
            }
            $blocks[] = ['type' => 'table', 'rows' => $rows, 'wrap' => $wrapNext];
            $wrapNext = false;
            continue;
        }

        if (preg_match('~^- \[ \] ~u', $line)) {
            $items = [];
            while ($i < $count && preg_match('~^- \[ \] (.*)$~u', rtrim((string) $lines[$i]), $m)) {
                $items[] = $m[1];
                $i++;
            }
            $blocks[] = ['type' => 'checklist', 'items' => $items];
            continue;
        }

        if (str_starts_with($line, '- ')) {
            $items = [];
            while ($i < $count && str_starts_with(rtrim((string) $lines[$i]), '- ') && !preg_match('~^- \[ \] ~u', (string) $lines[$i])) {
                $items[] = substr(rtrim((string) $lines[$i]), 2);
                $i++;
            }
            $blocks[] = ['type' => 'ul', 'items' => $items];
            continue;
        }

        if (preg_match('~^(\d+)\.\s~', $line, $m)) {
            $start = (int) $m[1];
            $items = [];
            while ($i < $count && preg_match('~^\d+\.\s+(.*)$~u', rtrim((string) $lines[$i]), $mm)) {
                $items[] = $mm[1];
                $i++;
            }
            $blocks[] = ['type' => 'ol', 'items' => $items, 'start' => $start];
            continue;
        }

        $text = [];
        while ($i < $count && trim((string) $lines[$i]) !== '' && ($text === [] || !civilina_md_is_block_start(rtrim((string) $lines[$i])))) {
            $text[] = trim((string) $lines[$i]);
            $i++;
        }
        $blocks[] = ['type' => 'p', 'text' => implode(' ', $text)];
    }
    return $blocks;
}

/**
 * Renders the article body.
 * @return array{html: string, toc: list<array{id: string, text: string}>, words: int, errors: list<string>}
 */
function civilina_md_render(string $markup): array
{
    $blocks = civilina_md_parse(preg_split('~\r\n|\r|\n~', $markup) ?: []);
    $state = ['toc' => [], 'ids' => [], 'errors' => []];
    $html = civilina_md_render_blocks($blocks, '      ', $state);
    $words = count(preg_split('~\s+~u', trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    return ['html' => $html, 'toc' => $state['toc'], 'words' => $words, 'errors' => $state['errors']];
}

function civilina_md_render_blocks(array $blocks, string $pad, array &$state): string
{
    $out = [];
    foreach ($blocks as $block) {
        switch ($block['type']) {
            case 'h2':
                $id = $block['id'] !== '' ? $block['id'] : civilina_md_slug($block['text']);
                $id = $id !== '' ? $id : 'section';
                $base = $id;
                for ($n = 2; isset($state['ids'][$id]); $n++) {
                    $id = $base . '-' . $n;
                }
                $state['ids'][$id] = true;
                $inline = civilina_md_inline($block['text']);
                $state['toc'][] = ['id' => $id, 'html' => $inline];
                $out[] = ($out !== [] ? "\n" : '') . $pad . '<h2 id="' . $id . '">' . $inline . '</h2>';
                break;
            case 'h3':
                $out[] = $pad . '<h3>' . civilina_md_inline($block['text']) . '</h3>';
                break;
            case 'p':
                $out[] = $pad . '<p>' . civilina_md_inline($block['text']) . '</p>';
                break;
            case 'ul':
            case 'ol':
            case 'checklist':
                $tag = $block['type'] === 'ol' ? 'ol' : 'ul';
                $attr = $block['type'] === 'checklist' ? ' class="checklist"' : (($block['start'] ?? 1) !== 1 ? ' start="' . (int) $block['start'] . '"' : '');
                $lines = [$pad . '<' . $tag . $attr . '>'];
                foreach ($block['items'] as $item) {
                    $lines[] = $pad . '  <li>' . civilina_md_inline($item) . '</li>';
                }
                $lines[] = $pad . '</' . $tag . '>';
                $out[] = implode("\n", $lines);
                break;
            case 'table':
                $rows = $block['rows'];
                if ($rows === []) {
                    break;
                }
                $inner = $block['wrap'] ? $pad : $pad;
                $lines = [];
                if ($block['wrap']) {
                    $lines[] = $pad . '<div class="table-wrap">';
                }
                $lines[] = $inner . '<table>';
                $lines[] = $inner . '  <thead><tr>' . implode('', array_map(static fn (string $c): string => '<th>' . civilina_md_inline($c) . '</th>', $rows[0])) . '</tr></thead>';
                $lines[] = $inner . '  <tbody>';
                foreach (array_slice($rows, 1) as $row) {
                    $lines[] = $inner . '    <tr>' . implode('', array_map(static fn (string $c): string => '<td>' . civilina_md_inline($c) . '</td>', $row)) . '</tr>';
                }
                $lines[] = $inner . '  </tbody>';
                $lines[] = $inner . '</table>';
                if ($block['wrap']) {
                    $lines[] = $pad . '</div>';
                }
                $out[] = implode("\n", $lines);
                break;
            case 'box':
                [$class, $default] = CIVILINA_BOXES[$block['kind']];
                $label = htmlspecialchars($block['label'] !== '' ? $block['label'] : $default, ENT_NOQUOTES, 'UTF-8');
                $inner = $block['blocks'];
                if (count($inner) === 1 && $inner[0]['type'] === 'p') {
                    $out[] = $pad . '<div class="box ' . $class . '"><strong>' . $label . '</strong>' . civilina_md_inline($inner[0]['text']) . '</div>';
                } else {
                    $sub = ['toc' => [], 'ids' => $state['ids'], 'errors' => []];
                    $out[] = $pad . '<div class="box ' . $class . '"><strong>' . $label . "</strong>\n" . civilina_md_render_blocks($inner, $pad . '  ', $sub) . "\n" . $pad . '</div>';
                }
                break;
        }
    }
    return implode("\n", $out);
}

/* ---------- Import: site HTML back to the syntax ---------- */

function civilina_md_escape(string $text): string
{
    return (string) preg_replace('~([\\\\*\[\]^|])~', '\\\\$1', $text);
}

function civilina_md_normalize_href(string $href): string
{
    if ($href === '../') {
        return '/journal/';
    }
    if (str_starts_with($href, '../../')) {
        return '/' . substr($href, 6);
    }
    if (str_starts_with($href, '../')) {
        return '/journal/' . substr($href, 3);
    }
    return $href;
}

function civilina_md_from_inline(DOMNode $node): string
{
    $out = '';
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText) {
            $out .= civilina_md_escape((string) preg_replace('~\s+~u', ' ', $child->textContent));
            continue;
        }
        if (!$child instanceof DOMElement) {
            continue;
        }
        $inner = civilina_md_from_inline($child);
        $out .= match ($child->nodeName) {
            'strong', 'b' => '**' . $inner . '**',
            'em', 'i' => '*' . $inner . '*',
            'sup' => '^' . $inner . '^',
            'a' => '[' . $inner . '](' . civilina_md_normalize_href($child->getAttribute('href')) . ')',
            default => $inner,
        };
    }
    return $out;
}

/** Converts the children of a container (prose or box) to the syntax. */
function civilina_md_from_blocks(DOMNode $container, bool $skipFirstStrong = false): string
{
    $parts = [];
    $loose = '';
    $skipped = !$skipFirstStrong;
    foreach ($container->childNodes as $child) {
        if (!$skipped && $child instanceof DOMElement && $child->nodeName === 'strong') {
            $skipped = true;
            continue;
        }
        if ($child instanceof DOMText || ($child instanceof DOMElement && in_array($child->nodeName, ['strong', 'em', 'a', 'sup', 'b', 'i'], true))) {
            $wrapper = $container->ownerDocument->createElement('span');
            $wrapper->appendChild($child->cloneNode(true));
            $loose .= civilina_md_from_inline($wrapper);
            continue;
        }
        if (trim($loose) !== '') {
            $parts[] = trim($loose);
        }
        $loose = '';
        if (!$child instanceof DOMElement) {
            continue;
        }
        $class = $child->getAttribute('class');
        switch ($child->nodeName) {
            case 'h2':
                $parts[] = '## ' . trim(civilina_md_from_inline($child)) . ($child->getAttribute('id') !== '' ? ' {#' . $child->getAttribute('id') . '}' : '');
                break;
            case 'h3':
                $parts[] = '### ' . trim(civilina_md_from_inline($child));
                break;
            case 'p':
                $parts[] = trim(civilina_md_from_inline($child));
                break;
            case 'ul':
            case 'ol':
                $lines = [];
                $n = $child->nodeName === 'ol' ? (int) ($child->getAttribute('start') ?: 1) : 0;
                foreach ($child->childNodes as $li) {
                    if ($li instanceof DOMElement && $li->nodeName === 'li') {
                        $prefix = $child->nodeName === 'ol' ? ($n++) . '. ' : (str_contains($class, 'checklist') ? '- [ ] ' : '- ');
                        $lines[] = $prefix . trim(civilina_md_from_inline($li));
                    }
                }
                $parts[] = implode("\n", $lines);
                break;
            case 'div':
                if ($class === 'table-wrap') {
                    foreach ($child->getElementsByTagName('table') as $table) {
                        $parts[] = "{.table-wrap}\n" . civilina_md_from_table($table);
                    }
                } elseif (str_starts_with($class, 'box ')) {
                    $kind = array_search(trim(substr($class, 4)), array_column(CIVILINA_BOXES, 0, null), true);
                    $kinds = array_keys(CIVILINA_BOXES);
                    $kind = $kind === false ? 'retenir' : $kinds[$kind];
                    $strong = $child->getElementsByTagName('strong')->item(0);
                    $label = $strong ? trim($strong->textContent) : '';
                    $parts[] = ':::' . $kind . ($label !== '' && $label !== CIVILINA_BOXES[$kind][1] ? ' ' . $label : '') . "\n" . civilina_md_from_blocks($child, true) . "\n:::";
                }
                break;
            case 'table':
                $parts[] = civilina_md_from_table($child);
                break;
        }
    }
    if (trim($loose) !== '') {
        $parts[] = trim($loose);
    }
    return implode("\n\n", $parts);
}

function civilina_md_from_table(DOMElement $table): string
{
    $lines = [];
    foreach ($table->getElementsByTagName('tr') as $index => $tr) {
        $cells = [];
        foreach ($tr->childNodes as $cell) {
            if ($cell instanceof DOMElement && in_array($cell->nodeName, ['td', 'th'], true)) {
                $cells[] = trim(civilina_md_from_inline($cell));
            }
        }
        $lines[] = '| ' . implode(' | ', $cells) . ' |';
        if ($index === 0) {
            $lines[] = '|' . str_repeat('---|', count($cells));
        }
    }
    return implode("\n", $lines);
}
