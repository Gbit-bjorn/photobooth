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
 * Eucalyptus-hoekdecoratie als inline SVG (uitnodigingsstijl): twee takken
 * met organische bladeren in drie tinten die vanuit de hoek waaieren.
 * $side: 'left' of 'right' (gespiegeld). Kleurtinten via CSS-classes.
 */
function leaf_corner(string $side): void
{
    $flip = $side === 'right' ? 'leaf-corner-right' : 'leaf-corner-left';
    $leaf = 'M0,0Q8,-6.5 18,0Q8,6.5 0,0Z';
    echo <<<SVG
<svg class="leaf-corner {$flip}" viewBox="0 0 170 170" fill="none" aria-hidden="true">
  <g class="tak" stroke-width="1.1" fill="none">
    <path d="M6 2 C 44 26, 88 52, 132 68"/>
    <path d="M2 8 C 20 54, 40 100, 64 138"/>
    <path d="M14 18 C 36 34, 58 48, 76 56" stroke-width="0.8"/>
  </g>
  <g class="blad-donker">
    <path d="{$leaf}" transform="translate(34 20) rotate(28)"/>
    <path d="{$leaf}" transform="translate(76 46) rotate(20)"/>
    <path d="{$leaf}" transform="translate(116 62) rotate(14)"/>
    <path d="{$leaf}" transform="translate(16 42) rotate(70)"/>
    <path d="{$leaf}" transform="translate(42 92) rotate(64)"/>
  </g>
  <g class="blad-midden">
    <path d="{$leaf}" transform="translate(54 32) rotate(-6) scale(0.9)"/>
    <path d="{$leaf}" transform="translate(96 56) rotate(44) scale(0.85)"/>
    <path d="{$leaf}" transform="translate(28 66) rotate(84) scale(0.9)"/>
    <path d="{$leaf}" transform="translate(56 116) rotate(56) scale(0.95)"/>
    <path d="{$leaf}" transform="translate(46 12) rotate(-24) scale(0.75)"/>
  </g>
  <g class="blad-licht">
    <path d="{$leaf}" transform="translate(66 40) rotate(30) scale(0.7)"/>
    <path d="{$leaf}" transform="translate(108 68) rotate(2) scale(0.65)"/>
    <path d="{$leaf}" transform="translate(20 84) rotate(58) scale(0.7)"/>
    <path d="{$leaf}" transform="translate(64 130) rotate(88) scale(0.6)"/>
    <path d="{$leaf}" transform="translate(88 40) rotate(60) scale(0.55)"/>
    <path d="{$leaf}" transform="translate(36 48) rotate(48) scale(0.6)"/>
  </g>
</svg>
SVG;
}

/** Klein eucalyptus-takje als scheidingsteken (zoals tussen de namen op de uitnodiging). */
function leaf_divider(): void
{
    $leaf = 'M0,0Q5,-4 11,0Q5,4 0,0Z';
    echo <<<SVG
<svg class="leaf-divider" viewBox="0 0 96 22" fill="none" aria-hidden="true">
  <path class="tak" d="M10 14 C 34 8, 62 8, 86 12" stroke-width="0.9" fill="none"/>
  <g class="blad-donker">
    <path d="{$leaf}" transform="translate(24 9) rotate(-22)"/>
    <path d="{$leaf}" transform="translate(48 6) rotate(-8)"/>
    <path d="{$leaf}" transform="translate(70 8) rotate(12)"/>
  </g>
  <g class="blad-midden">
    <path d="{$leaf}" transform="translate(35 14) rotate(24) scale(0.85)"/>
    <path d="{$leaf}" transform="translate(58 13) rotate(28) scale(0.85)"/>
    <path d="{$leaf}" transform="translate(15 10) rotate(-40) scale(0.7)"/>
  </g>
  <g class="blad-licht">
    <path d="{$leaf}" transform="translate(43 8) rotate(-30) scale(0.6)"/>
    <path d="{$leaf}" transform="translate(66 14) rotate(40) scale(0.6)"/>
    <path d="{$leaf}" transform="translate(80 10) rotate(-12) scale(0.55)"/>
  </g>
</svg>
SVG;
}

/** LS-monogram in uitnodigingsstijl: grote L met kleinere overlappende S. */
function ls_monogram(): void
{
    $ev = pb_event();
    $parts = array_map('trim', explode('&', $ev['couple']));
    $a = mb_substr($parts[0] ?? 'L', 0, 1);
    $b = mb_substr($parts[1] ?? 'S', 0, 1);
    echo '<div class="monogram" aria-hidden="true"><span>' . htmlspecialchars($a)
        . '</span><span class="monogram-tweede">' . htmlspecialchars($b) . '</span></div>';
}

/** Namen gestapeld zoals op de uitnodiging: LOTTE / takje / STEF. */
function names_lockup(string $extraClass = ''): void
{
    $couple = pb_event()['couple'];
    $parts = array_map('trim', explode('&', $couple));
    if (count($parts) === 2) {
        echo '<h1 class="lockup ' . htmlspecialchars($extraClass) . '">';
        echo '<span>' . htmlspecialchars($parts[0]) . '</span>';
        leaf_divider();
        echo '<span>' . htmlspecialchars($parts[1]) . '</span>';
        echo '</h1>';
    } else {
        echo '<h1 class="display">' . htmlspecialchars($couple) . '</h1>';
    }
}
