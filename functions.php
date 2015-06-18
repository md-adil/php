<?php
/**
 * get data from array feel like real oops array_get($array, 'namme.first_name')
 * @param Array
 * @param String
 */
function array_get(Array $array, $key, $default = null) {
	$result = null;
	foreach(explode('.', $key), $key) {
		if(isset($array[$key])) {
			$result = $array[$key];
		}	
	}
	if($result) {
		return $result;
	}
	return $default;
}