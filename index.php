<?php
require_once('libs/Parsedown.php');
require_once('libs/TinyHtmlMinifier.php');

umask(2);

$parsedown = new Parsedown();
$parsedown->setSafeMode(true);
$parsedown->setMarkupEscaped(true);

if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die("Přístup pouze pro lokálního uživatele.");
}

$compile = true;

$source = "./index.md";
$template = './templates/dashboard/template.html';
$cache = "./cache/index.cache.html";
$lastModification = "./cache/index-last-modification.txt";

if (!$compile && !file_exists($cache) && (!file_exists($source) || !file_exists($template))) {
    die("<h2 style='color: #cf222e'>#1 - Unexpected error!</h2>");
}

// nyní lze buď načíst cache nebo generovat

if (!$compile && file_exists($cache) && file_exists($lastModification)
    && file_get_contents($lastModification) == filemtime($source)) {
    $output = file_get_contents($cache);
    die($output);
}

// gen
if ($compile || (file_exists($template) && file_exists($source))) {
    $md = file_get_contents($source);
    $html = $parsedown->text($md);
    $template = file_get_contents($template);
    $html = str_replace([
        "{{content}}",
        "::br::"
    ], [
        $html,
        '<br/>'
    ], $template);

    $minifier = new TinyHtmlMinifier(['collapse_whitespace' => true, 'disable_comments' => true]);
    $html = $minifier->minify($html);

    file_put_contents($cache, $html);
    file_put_contents($lastModification, filemtime($source));
    $output = $html;
}

if (empty($output)) {
    $output = file_get_contents($cache);
}

die($output);
