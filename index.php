<?php
$html = file_get_contents(__DIR__ . '/index.html');
$html = str_replace('</head>', '<link rel="stylesheet" href="readonly.css?v=20260722-1"></head>', $html);
$html = str_replace('<script type="module" src="js/app.js?v=20260721-7"></script>', '<script type="module" src="js/bootstrap.js?v=20260722-1"></script>', $html);
echo $html;
