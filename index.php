<?php
require_once('libs/Parsedown.php');
require_once('libs/TinyHtmlMinifier.php');

umask(2);

try {

    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);
    $parsedown->setMarkupEscaped(true);

    if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        die("Only local user allowed!");
    }

    $compile = false;

    $conf = [
        'pages_source' => 'pages',
        'index' => 'index',
        'type' => 'md',
        'template' => 'templates/dashboard/template.html',
        'cache' => 'cache'
    ];

    // router
    $route = trim($_SERVER['REQUEST_URI'], '/');

    if(empty($route)) {
        $route = $conf['index'];
    }

    $source = sprintf("%s/%s.%s",
        $conf['pages_source'],
        $route,
        $conf['type']
    );

    $cache=sprintf("%s/%s",
        $conf['cache'],
        $conf['pages_source']
    );

    $cacheFile = sprintf('%s/%s.cache.html', $cache, $route);
    $lastModFile = sprintf('%s/%s.mod.txt', $cache, $route);

    if (!file_exists($cacheFile) && (!file_exists($source) || !file_exists($conf['template']))) {
        die("<h2 style='color: #cf222e'>Route not found!</h2>");
    }

    // next get xor generate the cache

    if (!$compile && file_exists($cacheFile) && file_exists($lastModFile)
        && file_get_contents($lastModFile) == filemtime($source)) {
        $output = file_get_contents($cacheFile);
        die($output);
    }

    // gen
    if ($compile || (file_exists($conf['template']) && file_exists($source))) {
        $md = file_get_contents($source);
        $html = $parsedown->text($md);
        $template = file_get_contents($conf['template']);
        $html = str_replace([
            "{{content}}",
            "::br::"
        ], [
            $html,
            '<br/>'
        ], $template);

        $minifier = new TinyHtmlMinifier(['collapse_whitespace' => true, 'disable_comments' => true]);
        $html = $minifier->minify($html);

        if(!file_exists($cache)) {
            mkdir($cache);
        }

        file_put_contents($cacheFile, $html);
        file_put_contents($lastModFile, filemtime($source));
        $output = $html;
    }

    if (empty($output)) {
        $output = file_get_contents($cacheFile);
    }

    die($output);
}
catch (Exception $ex)
{
    $message = $ex->getMessage();
    $trace = $ex->getTraceAsString();
    die("<h2 style='color: #cf222e'>Unexpected error: $message<br />$trace!</h2>");
}
