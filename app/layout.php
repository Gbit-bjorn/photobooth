<?php
declare(strict_types=1);

function page_header(string $title, string $bodyClass = ''): void
{
    $ev = pb_event();
    $couple = htmlspecialchars($ev['couple']);
    $titleEsc = htmlspecialchars($title);
    echo <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>{$titleEsc} — {$couple}</title>
<link rel="stylesheet" href="/assets/css/theme.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="{$bodyClass}">
HTML;
}

function page_footer(): void
{
    echo "\n</body>\n</html>\n";
}

/** Eucalyptus-takje als inline SVG (decoratief, uit de uitnodigingsstijl). */
function leaf_divider(): void
{
    echo <<<'SVG'
<svg class="leaf-divider" viewBox="0 0 88 20" fill="none" aria-hidden="true">
  <path d="M4 10 H84" stroke="currentColor" stroke-width="0.8"/>
  <g fill="currentColor" opacity="0.85">
    <ellipse cx="30" cy="7" rx="4.5" ry="2" transform="rotate(-28 30 7)"/>
    <ellipse cx="40" cy="5.5" rx="4.5" ry="2" transform="rotate(-18 40 5.5)"/>
    <ellipse cx="50" cy="6.8" rx="4.5" ry="2" transform="rotate(-30 50 6.8)"/>
    <ellipse cx="36" cy="13" rx="4.5" ry="2" transform="rotate(22 36 13)"/>
    <ellipse cx="47" cy="13.6" rx="4.5" ry="2" transform="rotate(26 47 13.6)"/>
    <ellipse cx="57" cy="12" rx="4.5" ry="2" transform="rotate(18 57 12)"/>
  </g>
</svg>
SVG;
}
