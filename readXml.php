<?php
/**
 * Created by PhpStorm.
 * project: hit
 * User: leav
 * Date: 20.02.2020
 * Time: 8:50
 * fileName: readXml.php
 */

include_once 'lib/StoreXMLReader.php';

$xmlr = new StoreXMLReader();
$r = $xmlr->parse('in/response.xml');
