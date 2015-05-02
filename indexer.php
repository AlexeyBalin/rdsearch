<?php

ini_set("default_charset",'utf-8');
include_once("./includes/lib.php");
include_once("./includes/rdsearch.class.php");

setlocale(LC_ALL, 'ru_RU.UTF-8');

$my = new mysqli("localhost", "spider", "spider","spider");
$user_rate = 90000;
$i = 0;
$res = $my->query("SELECT ID,post_title AS title, post_content AS content,post_date as `date`, guid as uri FROM wp_posts WHERE post_status = 'publish' LIMIT $i,1000 ");
while( $res->num_rows > 0 ){
	echo "\n\t".$i;
	$ind = new RDSearch();
	while ( $row = $res->fetch_assoc() ){
//		$row['uri'] = "http://www.business-top.info/?p=".$row['ID'];
		$row['description'] = substr_words($row['content'], 128);
		$ind->get_uri_id( $row, $user_rate);
		$ind->index( $row['title'], $user_rate / 5);
		$ind->index( $row['content'], $user_rate / 10);
	}
	$i += 1000;

	$res = $my->query("SELECT ID,post_title AS title, post_content AS content,post_date as `date`, guid as uri FROM wp_posts WHERE post_status = 'publish' LIMIT $i,1000 ");
}



