<?php
/**
 * Сниппет/Модификатор: phpthumbon - Генератор превью изображений
 * Вызывается из: Чанки siteExtra как модификатор Fenom (| phpthumbon)
 * Назначение: Создание миниатюр изображений с указанными размерами
 *
 * Copyright 2013 by Agel_Nash <Agel_Nash@xaker.ru>
 *
 * @category images
 * @license GNU General Public License (GPL)
 * @author Agel_Nash <Agel_Nash@xaker.ru>
 */

if(empty($modx) || !($modx instanceof modX)) return '';

$componentPath = (string)$modx->getOption('phpthumbon.core_path', null, $modx->getOption('core_path').'components/phpthumbon/');

if(!isset($modx->phpThumbOn)){
    $modx->phpThumbOn = $modx->getService("phpthumbon","phpThumbOn",$componentPath.'model/phpthumbon/', $scriptProperties);
}

if(!($flag = ($modx->phpThumbOn instanceof phpThumbOn))){
    $modx->phpThumbOn = null;
}
return $flag ? $modx->phpThumbOn->run($scriptProperties) : $modx->getOption('phpthumbon.noimage', $scriptProperties);