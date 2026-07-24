<?php
$html = file_get_contents(__DIR__ . '/index.html');
$assetFiles=['style.css','css/fontawesome.css','css/sharp-light.css','js/bootstrap.js','js/app.js','js/state.js','js/vendor/html2canvas.min.js','js/vendor/jspdf.umd.min.js'];
$version=(string)max(array_map(static fn(string $file):int=>(int)@filemtime(__DIR__.'/'.$file),$assetFiles));
$html = preg_replace('/<script type="module" src="js\/app\.js(?:\?v=[^"]*)?"><\/script>/', '<script type="module" src="js/bootstrap.js"></script>', $html);
$html = preg_replace('/((?:href|src)="(?!https?:\/\/)[^"?]+\.(?:css|js))(?:\?v=[^"]*)?"/', '$1?v='.$version.'"', $html);
$html = str_replace('</head>', '<script>window.__ASSET_VERSION__='.json_encode($version).';</script></head>', $html);
echo $html;
