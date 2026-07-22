<?php
$html = file_get_contents(__DIR__ . '/index.html');
$version=(string)random_int(10000,99999);
$html = str_replace('</head>', '<link rel="stylesheet" href="readonly.css"></head>', $html);
$html = preg_replace('/<script type="module" src="js\/app\.js(?:\?v=[^"]*)?"><\/script>/', '<script type="module" src="js/bootstrap.js"></script>', $html);
$html = preg_replace('/((?:href|src)="(?!https?:\/\/)[^"?]+\.(?:css|js))(?:\?v=[^"]*)?"/', '$1?v='.$version.'"', $html);
$html = str_replace('</head>', '<script>window.__ASSET_VERSION__='.json_encode($version).';</script></head>', $html);
echo $html;
