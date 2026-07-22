<?php $t=(string)($_GET['token']??''); header('Location: ../?token='.rawurlencode($t)); exit;
