<?php
/**
 * get data from array feel like real oops array_get($array, 'namme.first_name')
 * @param Array $array
 * @param String $key (dot seperated)
 * @param String $default (if not found)
 */
function array_get(Array $array, $key, $default = null) {
	$result = null;
	foreach(explode('.', $key), $key) {
		if(isset($array[$key])) {
			$result = $array[$key];
		} else {
			return $default;
		}
	}
	return $result;
}

/**
 * @param 
 */
function array_except() {
	$args = func_get_args();
	$array = array_shift($args);
}

/**
 * @param
 */
function array_fetch(Array $array, $key) {

}

/**
 * get data from object feel like real oops object_get($object, 'namme.first_name')
 * @param Object $object
 * @param String $property (dot seperated)
 * @param String $default (if not found)
 */
function object_get(Object $object, $prop, $default = null) {

}

function input() {

}

function input_appends() {

}

function input_except() {

}