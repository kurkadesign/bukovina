<?php $t=(string)($_GET['token']??''); header('Location: ../?share='.rawurlencode($t)); exit;
