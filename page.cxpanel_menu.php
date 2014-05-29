<?php
$path = str_replace($amp_conf['AMPWEBROOT'], '', $amp_conf['FOPWEBROOT']);
header('Location: ' . $path);

