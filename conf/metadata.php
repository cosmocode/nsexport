<?php
$meta['autoexport']  = array('onoff');
$meta['usepacker']                     = array('multichoice', '_choices' => array('ziphtml', 'odt'));
$meta['packer____ziphtml____zip'] = array('string');
$meta['packer____ziphtml____compress'] = array('numeric','_pattern' => '/[0-9]{1}/');
