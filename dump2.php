<?php
require 'vendor/autoload.php';
require 'includes/firebase_config.php';
\ = \['db']->request('GET', '?pageSize=5');
echo \->getBody();
