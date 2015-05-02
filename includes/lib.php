<?php

function count_days($start,$end) {
    $datetime1 = new DateTime($start);
    $datetime2 = new DateTime($end);
    $interval = $datetime1->diff($datetime2);
    var_dump($interval);
    return $interval->days;
}

function substr_words($body, $n = 255){
	$body = clear_text($body);
	$line = "";
	if (preg_match('/^.{1,'.$n.'}\b/su', $body, $match)){
		$line=$match[0];
	}
	return trim($line);
}

function sort_col($table, $colname, $sort = SORT_ASC) {
  $tn = $ts = $temp_num = $temp_str = array();
  foreach ($table as $key => $row) {
    if(is_numeric(substr($row[$colname], 0, 1))) {
      $tn[$key] = $row[$colname];
      $temp_num[$key] = $row;
    }
    else {
      $ts[$key] = $row[$colname];
      $temp_str[$key] = $row;
    }
  }
  unset($table);
	//var_dump( $sort );
  array_multisort($tn, $sort, SORT_NUMERIC, $temp_num);
  array_multisort($ts, $sort, SORT_STRING, $temp_str);
  return array_merge($temp_num, $temp_str);
}

function clear_text( $text ) {

	$text = strip_tags($text);
	$text = str_replace(
		array(
			'"','&','@','?','.',',',
	        '!',')','#','№','~','*',
			'^','%','$','<','|','>',
			'+','«','…','»','(',"/",
			"\\","’","•","-",':',';',
			'_','='
		)," ",$text );
    $text= htmlspecialchars($text);
	return $text;
}

function in_arrayi($in, $arr) {
    for($i =0; $i < count($arr); $i++ ){
	if(mb_strtolower($in, "UTF-8") == mb_strtolower($arr[$i], "UTF-8") ) {
	    //echo mb_strtolower($in, "UTF-8")." == ".mb_strtolower($arr[$i], "UTF-8")."\n" ;
	    return true;
	}else{
	    //echo mb_strtolower($in, "UTF-8")." == ".mb_strtolower($arr[$i], "UTF-8")."\n" ;
        }
    }
    return false;
}


function parse_query( $str ) {
	$result = [];
	if( preg_match('#"(.*)"#xi', $str, $matches) ) {
		$result['phrase'] = $matches[1];
		$str = str_replace($result['phrase'],"",$str);
	}

	$commands = array(
		"not" => ''
	);

	$words = explode(" ", $str);
	for( $i = 0; $i < count($words); $i++ ){
		if( mb_strlen($words[$i], "UTF-8") >= 3  ){
			if( preg_match('#^(-)(.*)#xi', $words[$i], $matches ) )
				$result['not'][] = trim($matches[2]);
			else
				$result['yes'][] = trim($words[$i]);
		}
	}
	return $result;
}
