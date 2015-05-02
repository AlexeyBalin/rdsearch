<?php
class RDSearch {

	/* слова, которые нужно проигнорировать*/
	var $stop_words = array("http","nbsp","raquo","чтобы","как","куда","откуда","почему","зачем","как-бы","так","вот","когда","никогда","который","наверно","наверное","возможно","например","всегда","иногда","многий","только");

	var $r;
	var $morphy_ru;
	var $morphy_en;


	function RDSearch() {

		$this->r = new Redis();
    		$this->r->connect('127.0.0.1');
    		$this->r->select(3);
    		$this->r->setOption(Redis::OPT_PREFIX, 'RDSearch:');

		require_once(dirname( __FILE__ ).'/morphy/src/common.php');
		$opts = array(
			'storage' => PHPMORPHY_STORAGE_FILE,
			'with_gramtab' => true,
			'predict_by_suffix' => true,
			'predict_by_db' => true
		);

		$dir =  dirname( __FILE__ ).'/morphy/dicts';
		try {
			$dict_ru = new phpMorphy_FilesBundle($dir, 'rus');
			$this->morphy_ru = new phpMorphy($dict_ru, $opts);
		}catch(phpMorphy_Exception $e) {
			die('Error occured while creating phpMorphy instance: ' . $e->getMessage());
		}

		try {
			$dict_en = new phpMorphy_FilesBundle($dir, 'eng');
			$this->morphy_en = new phpMorphy($dict_en, $opts);
		}catch(phpMorphy_Exception $e) {
			die('Error occured while creating phpMorphy instance: ' . $e->getMessage());
		}
	}


	function get_word_id($word) {
		//echo "*";
		//$hash = sha1( $word );
		if( ($word_id = $this->r->hGet("Words:word:id",$word ))  == false ) {
			$word_id = $this->r->Incr("WordId");
			$this->r->hSet('Words:word:id', $word, $word_id );
			$this->r->hSet('Words:id:word', $word_id, $word);
		}
		return $word_id;
	}

	function get_form( $word, $lang = 'ru' ) {
		$word = mb_convert_case($word,  MB_CASE_UPPER,"UTF-8" );
		if( preg_match('#[A-Z]#xi', $word) )
			$lang = "en";

		switch( $lang ){
			case "ru":
				$form = $this->morphy_ru->getBaseForm($word);
			break;
			case "en":
				$form = $this->morphy_en->getBaseForm($word);
			break;
			default:
				return $lang;
		}

		return mb_convert_case($form[0], MB_CASE_LOWER,"UTF-8");

	}

	function get_words ($text){

		$text = clear_text($text);
		$matches = preg_split("/[\s]/", $text);

		$words = array();


		for($i = 0;$i < count($matches);$i++){
			if( mb_strlen($matches[$i], "UTF-8") > 3 && in_arrayi( $matches[$i], $this->stop_words) === false ){

				$word = mb_convert_case($matches[$i], MB_CASE_LOWER,"UTF-8");
				$form = $this->get_form($word);

			//	echo $word.' => '.$form."\n";
				if( mb_strlen($word, 'UTF-8') > 3 && in_arrayi( $word, $this->stop_words) === false ){
					isset($words['word'][$word]) ? $words['word'][$word]++ : $words['word'][$word]=1;
				}

				if( $form != $word && mb_strlen($form, 'UTF-8') > 3 && in_arrayi( $form, $this->stop_words) === false ){
					isset($words['form'][$form]) ? $words['form'][$form]++ : $words['form'][$form]=1;

				}
			}
		}
		//arsort($words);
		//print_r($words);
		return $words;
		$i=0;
		$str = array();
	}

	function get_uri_id( $row ) {
		//echo "\n+";
		$hash = sha1($row['uri']);
		if( ($this->uri_id = $this->r->hGet("Uri:hash:id",$hash ))  == false ) {
			$this->uri_id = $this->r->Incr("UriId");
			$this->r->hSet('Uri:hash:id', $hash, $this->uri_id );
			$this->r->hSet('Uri:id:uri', $this->uri_id, $row['uri']);
			$this->r->hSet('Uri:id:date', $this->uri_id, $row['date']);
			$this->r->hSet('Uri:id:title', $this->uri_id, $row['title']);
			$this->r->hSet('Uri:id:description', $this->uri_id, $row['description']);
		}
	}

	function index($text, $base_rate ) {

		$words = $this->get_words($text);

		if( isset(  $words['word'] ) ){
			foreach($words['word'] as $word => $rate ){
				$word_id = $this->get_word_id($word);
				$rate = $rate*20 + $base_rate;
				$this->r->zAdd( "Index:{$word_id}",$rate ,$this->uri_id );
			}
		}

		if( isset(  $words['form'] ) ){
			foreach($words['form'] as $word => $rate ){
				$word_id = $this->get_word_id($word);
				$rate = $rate*10 + $base_rate;
				$this->r->zAdd( "Index:{$word_id}",$rate ,$this->uri_id );
			}
		}
	}

    function search($key,$offset = 0, $limit = 10){

		$sq = parse_query($key);
		$hash = sha1($key);
		$tmpkeyname = "Result:tmp:all:".$hash;
		$keyarray = array();
		//print_r($sq);
		if( isset($sq['phrase']) ){
			$parts = explode(" ", $sq['phrase'] );
			$keyarray = array();
			for( $i = 0; $i < count($parts);$i++ ){
				$keyarray["Index:".$this->get_word_id($parts[$i])] = 2;
			}
		}



		if( isset($sq['yes']) ){
			foreach($sq['yes'] as $word ){
				$form = $this->get_form($word);
				$keyarray["Index:".$this->get_word_id($form)] = 1;
			}
		}


		if( isset($sq['not']) ){
			foreach($sq['not'] as $word  ){
				$form = $this->get_form($word);
				$keyarray["Index:".$this->get_word_id($form)] = -1;
			}
		}


		//print_r($keyarray);

		if( count($keyarray) > 1 ){
			$this->r->zInter($tmpkeyname, array_keys($keyarray),  array_values($keyarray) );
			$this->r->setTimeout($tmpkeyname, 3600);
		}else{
			$tmpkeyname  = array_keys($keyarray)[0];
		}


/*		if( isset($sq['not']) ){
			$this->r->zRemRangeByScore($tmpkeyname['all'], '-inf', 0 );
		}
*/
        $data = $this->r->zRevRange($tmpkeyname,$offset,$limit, true);
        $uris = $this->r->hMget('Uri:id:uri', array_keys($data) );
        $titles = $this->r->hMget('Uri:id:title', array_keys($data) );

        $descriptions = $this->r->hMget('Uri:id:description', array_keys($data) );
        $result = array();
        $links = array();
        $result['total'] = $this->r->zCard($tmpkeyname);
        foreach( $data as $id => $val ){
			$links[] = array(
				"id" => $id,
				"rate" => $val,
				"date" => $dates[$id],
				"title" => $titles[$id],
				"description" => $descriptions[$id],
				"uri" => $uris[$id]
			);
		}
	
        $result['results'] = sort_col($links, "rate", SORT_DESC);
        return $result;
    }

}
