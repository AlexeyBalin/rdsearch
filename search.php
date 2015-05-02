<?php

ini_set("default_charset",'utf-8');
include_once("./lib.php");
include_once("./indexer.class.php");

setlocale(LC_ALL, 'ru_RU.UTF-8');

$ind = new indexer();
?>
<!DOCTYPE html>
<html>
    <head>
	<style>
	    body{font-family: verdana;}
	    li {margin-bottom: 10px;}
	    .uri{ color: green; font-size: 10px;}
	</style> 
    </head>
    <body style="margin: 20px;">
	<a href="http://doshare.ru/"><img style="float: left; margin-right:100px;" src="http://doshare.ru/logo_DS_.png" /></a>
	<form action="search.php">
	    <div style="height: 50px; float: left;padding-top: 10px;">
	    <strong>Поиск</strong>:
	    <input style="width: 450px;height: 20px;;" type="text" name="text" value="" />
	    <input type="submit" value="Ok" />
	    </div>
	</form>
	<br style="clear:both" />
	<ul>

<?php
if(isset($_REQUEST['text'])) {
    $result = $ind->search($_REQUEST['text'], isset($_REQUEST['page']) ? $_REQUEST['page']*10 : 0, 10 );
    echo 'Всего: '.$result['total'];
    foreach( $result['results'] as $link ){
	?><li>
	    <a href="<?php echo $link['uri']?>"><?php echo $link['title']?></a> [<?php echo $link['rate']?>] - <?php echo $link['description']?><br/>
	    <a class="uri" href="<?php echo $link['uri']?>"><?php echo $link['uri']?></a>
	  </li>    
	<?php
    }
}
//print_r($result);

?>
    </ul>
 </body>
</html>


