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
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cellipse cx='8' cy='8' rx='6' ry='3.2' transform='rotate(-30 8 8)' fill='%235f6d55'/%3E%3C/svg%3E">
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

/**
 * Eucalyptus-hoekdecoratie als inline SVG (uitnodigingsstijl): twee takjes
 * die vanuit de hoek waaieren. $side: 'left' of 'right' (gespiegeld).
 */
function leaf_corner(string $side): void
{
    $flip = $side === 'right' ? 'leaf-corner-right' : 'leaf-corner-left';
    echo <<<SVG
<svg class="leaf-corner {$flip}" viewBox="0 0 150 150" fill="none" aria-hidden="true">
  <g stroke="currentColor" stroke-width="1" opacity="0.5">
    <path d="M8 4 C 40 30, 78 58, 118 74"/>
    <path d="M4 12 C 22 52, 42 92, 68 124"/>
  </g>
  <g fill="currentColor">
    <ellipse cx="38" cy="26" rx="12" ry="4.6" transform="rotate(34 38 26)" opacity="0.55"/>
    <ellipse cx="58" cy="41" rx="11" ry="4.4" transform="rotate(28 58 41)" opacity="0.4"/>
    <ellipse cx="80" cy="55" rx="12" ry="4.6" transform="rotate(22 80 55)" opacity="0.6"/>
    <ellipse cx="101" cy="66" rx="10" ry="4" transform="rotate(18 101 66)" opacity="0.45"/>
    <ellipse cx="46" cy="16" rx="10" ry="4" transform="rotate(-8 46 16)" opacity="0.42"/>
    <ellipse cx="70" cy="34" rx="9" ry="3.6" transform="rotate(60 70 34)" opacity="0.38"/>
    <ellipse cx="18" cy="44" rx="12" ry="4.6" transform="rotate(66 18 44)" opacity="0.55"/>
    <ellipse cx="31" cy="70" rx="11" ry="4.4" transform="rotate(72 31 70)" opacity="0.42"/>
    <ellipse cx="45" cy="96" rx="12" ry="4.6" transform="rotate(64 45 96)" opacity="0.58"/>
    <ellipse cx="58" cy="117" rx="10" ry="4" transform="rotate(58 58 117)" opacity="0.4"/>
    <ellipse cx="10" cy="58" rx="9" ry="3.6" transform="rotate(110 10 58)" opacity="0.4"/>
  </g>
</svg>
SVG;
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
