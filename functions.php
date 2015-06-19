<?php
/**
 * get data from array feel like real oops array_get($array, 'namme.first_name')
 * @param Array $array
 * @param String $key (dot seperated)
 * @param String $default (if not found)
 */
function array_get(Array $array, $key, $default = null) {
	foreach(explode('.', $key) as $key) {
		if(!isset($array[$key])) {
			return $default;
		}
		$array = $array[$key];
	}
	return $array;
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
function array_fetch(Array &$array, $key, $default) {
	$result = null;
	foreach(explode('.', $key) as $key) {
		if(!isset($result[$key])) {
			return $default;
		}
	}
}

/**
 * get data from object feel like real oops object_get($object, 'namme.first_name')
 * @param Object $object
 * @param String $property (dot seperated)
 * @param String $default (if not found)
 */
function object_get(Object $object, $prop, $default = null) {
	foreach(explode('.', $prop) as $prop) {
		if(!isset($object->$prop)) {
			return $default;
		}
		$object = $object->$prop;
	}
	return $object;
}

/**
 * get data from GLOBAL input like real oops object_get($object, 'namme.first_name')
 * @param Array $key
 * @param Or String $keu
 */
function input() {
	$inputs = $_POST + $_GET;
	$args = func_get_args();
	$argc = count($args);
	if($argc == 0) {
		return $inputs;
	}
	$arg = $args[0];
	if($argc == 1 && gettype($arg) == 'string') {
		return array_get($inputs, $arg);
	}
	$result = [];
	foreach($args as $arg) {
		if(gettype($arg) == 'string') {
			if(isset($inputs[$arg])) {
				$result[$arg] = $inputs[$arg];
			}
		} else {
			foreach($arg as $str) {
				if(isset($inputs[$str])) {
					$result[$str] = $inputs[$str];
				}
			}
		}
	}
	return $result;
}	


function input_appends() {
	return $_POST + $_GET + func_get_args();
}


function input_not() {

}