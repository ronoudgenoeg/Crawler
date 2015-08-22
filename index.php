<?php
require_once('DataGetters/BiedVeilingDataGetter.php');

$getter = new BiedVeilingDataGetter();
$items = $getter->getItems();
var_dump($items);