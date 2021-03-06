<?php
require_once ("include/browser/browseremulator.class.php");
require_once ("include/browser/info_extractor.php");
require_once (dirname(__FILE__)."/imdb_config.php");

class imdb extends imdb_config {
	var $doubanxml,$DB_array,$dbinfo;
	var $imdbID = "";
	var $page;
	var $main_title = "";
	var $main_year = "";
	var $main_transname = "";
	var $main_runtime = "";
	var $main_runtimes;
	var $main_rating = "";
	var $main_votes = "";
	var $main_language = "";
	var $main_languages = "";
	var $main_genre = "";
	var $main_genres = "";
	var $main_tagline = "";
	var $main_plotoutline = "";
	var $main_comment = "";
	var $main_alttitle = "";
	var $main_colors = "";

	var $plot_plot = "";
	var $taglines = "";

	var $credits_cast = "";
	var $credits_director = "";
	var $credits_writing = "";
	var $credits_producer = "";

	var $main_director = "";
	var $main_credits = "";
	var $main_photo = "";
	var $main_country = "";
	var $main_alsoknow = "";
	var $main_sound = "";

	var $info_excer;
	
	var $similiar_movies = array(array('Name' => '', 'Link' => '', 'Local' => ''));	// no Thumbnail here, since it works different from last.fm, douban
	var $extension = array('Title', 'Credits', 'Amazon', 'Goofs', 'Plot', 'Comments', 'Quotes', 'Taglines', 'Plotoutline', 'Trivia', 'Directed');

	function debug_scalar($scalar) {
		echo "<b><font color='#ff0000'>$scalar</font></b><br>";
	}
	function debug_object($object) {
		echo "<font color='#ff0000'><pre>";print_r($object);echo "</pre></font>";
	}
	function debug_html($html) {
		echo "<b><font color='#ff0000'>".htmlentities($html)."</font></b><br>";
	}

	/** Get similiar movies
	 * @method similiar_movies
	 * @return list similiar_movies
	 */
	function similiar_movies(){
		if (!isset($this->similiar_movies))
		{
			if ($this->page["Title"] == "")
			{
				$this->openpage ("Title");
			}
			$similiar_movies = $this->info_excer->truncate($this->page["Title"], "<h3>Recommendations</h3>", "<tr class=\"rating\">");
			$similiar_movies = $this->info_excer->truncate($similiar_movies, "<tr>", "</tr>");
			$res_where_array = array('Link' => '1', 'Name' => '3');
			if($res_array = $this->info_excer->find_pattern($similiar_movies,"/<td><a href=\"((\s|.)+?)\">((\s|.)+?)<\/a><\/td>/",true,$res_where_array))
			{
				$counter = 0;
				foreach($res_array as $res_array_each)
				{
					$this->similiar_movies[$counter]['Link'] = $res_array_each[0];
					$this->similiar_movies[$counter]['Name'] = $res_array_each[1];
					
					$imdb_id = ltrim(strrchr($res_array_each[0],'tt'),'tt');
					$imdb_id = preg_replace("/[^A-Za-z0-9]/", "", $imdb_id);
					
					//die("ss" . $imdb_id);
					$imdb_sim_movies = new imdb($imdb_id);
					//$imdb_sim_movies->setid($imdb_id);
					$target = array('Title', 'Credits', 'Plot');
					$imdb_sim_movies->preparecache($target,false);
					$this->similiar_movies[$counter]['Local'] = $imdb_sim_movies->photo_localurl();
					$counter++;
				}
			}
		}
		return $this->similiar_movies;
	}
	
	
	/** Test if IMDB url is valid
	 * @method urlstate ()
	 * @param none
	 * @return int state (0-not valid, 1-valid)
	 */
	function urlstate () {
	 if (strlen($this->imdbID) != 7)
		return 0;
	 else
	 	return 1;
	}

	/** Test if caching IMDB page is complete
	 * 检查IMDB缓存是否完整
	 * @method cachestate ()
	 * @param $target array
	 * @return int state (0-not complete, 1-cache complete, 2-cache not enabled, 3-not valid imdb url)
	 */
	function cachestate ($target = "") {
	 if (strlen($this->imdbID) != 7){
		//echo "not valid imdbID: ".$this->imdbID."<BR>".strlen($this->imdbID);
		$this->page[$wt] = "cannot open page";
		return 3;
	 }
	 if ($this->usecache)
	 {
	 	$ext_arr = isset($target) ? $target : $this->extension;
		 	if(!file_exists("$this->cachedir/$this->imdbID.xml"))
		 		return 0;
			@$fp = fopen ("$this->cachedir/$this->imdbID.xml", "r");
			if (!$fp)
				return 0;
		return 1;
	 }
	 else
	 	return 2;
	}
	
	 /** prepare IMDB page cache
	 * @method preparecache
	 * @param $target array
	 */
	function preparecache ($target = "", $retrive_similiar = false) {
		$ext_arr = isset($target) ? $target : $this->extension;
		foreach($ext_arr as $ext)
		{
			$tar_ext = array($ext);
			if($this->cachestate($tar_ext) == 0) 
				$this->openpage($ext);
		}
	}
	
	/** Open an IMDB page
	 * @method openpage
	 * @param string wt
	 */
	function openpage ($wt) {
	 if (strlen($this->imdbID) != 7){
		echo "not valid imdbID: ".$this->imdbID."<BR>".strlen($this->imdbID);
		$this->page[$wt] = "cannot open page";
		return;
	 }
	 $urlname="";
	 
	 if ($this->usecache) {
		@$fp = fopen ("$this->cachedir/$this->imdbID.xml", "r");
		if ($fp) {
		 $temp="";
		 while (!feof ($fp)) {
	$temp .= fread ($fp, 1024);
		 }
	if ($temp) {
		$this->page[$wt] = $temp;
		
		$this->doubanxml = file_get_contents("$this->cachedir/$this->imdbID.xml");
		$xmlparser = xml_parser_create();
		xml_parse_into_struct($xmlparser,$this->doubanxml,$this->DB_array);
		$this->dbinfo=array();
		foreach($this->DB_array as $db){
			switch($db["tag"]){
				case "DB:TAG":
					$this->dbinfo["tag"][] = $db["attributes"]["NAME"];
					break;
				case "DB:ATTRIBUTE":
					if($db["attributes"]["LANG"]=="zh_CN")
						$this->dbinfo["transname"]=$db["value"];
					else 
						$this->dbinfo[$db["attributes"]["NAME"]][] = $db["value"];
					break;
				case "LINK":
					$this->dbinfo["link"][$db["attributes"]["REL"]] = $db["attributes"]["HREF"];
					break;
				case "TITLE":
					$this->dbinfo["name"] = $db["value"];
					break;
				case "NAME":
					$this->dbinfo["author"][] = $db["value"];
					break;
				case "SUMMARY":
					$this->dbinfo["summary"] = $db["value"];
					break;
				case "GD:RATING":
					$this->dbinfo["rating"] = $db["attributes"]["AVERAGE"];
					$this->dbinfo["votes"] = $db["attributes"]["NUMRATERS"];
					break;
				default:
					break;
				}
			}
			
			
		 		return;
	}
		}
	 } // end cache

	 $req = new IMDB_Request("");
//	 $req->setURL("http://".$this->imdbsite."/title/tt".$this->imdbID.$urlname);
//	 $req->setURL("http://".$this->imdbsite."/imdb/tt".$this->imdbID.$urlname."?apikey=03aa7b16be6307e40aff71443b2917ac");
	 $req->setURL("http://".$this->imdbsite."/imdb/tt".$this->imdbID.$urlname."?apikey=076aac0464c2f9b21640040d2e48ed16");
	 $response = $req->send();
	$responseBody = $response->getBody();
	 if ($responseBody) {
//			 $this->page[$wt] = utf8_encode($responseBody);
			 $this->page[$wt] = $responseBody;
	 }
	 if( $this->page[$wt] ){ //storecache
		if ($this->storecache) {
			$fp = fopen ("$this->cachedir/$this->imdbID.xml", "w");
			fputs ($fp, $this->page[$wt]);
			fclose ($fp);
			}
		return;
	 }
	 $this->page[$wt] = "cannot open page";
	 //echo "page not found";
	}

	/** Retrieve the IMDB ID
	 * @method imdbid
	 * @return string id
	 */
	function imdbid () {
		return $this->imdbID;
	}

	/** Setup class for a new IMDB id
	* @method setid
	* @param string id
	*/
	function setid ($id) {
		$this->imdbID = $id;

		$this->page["Title"] = "";
		$this->page["Credits"] = "";
		$this->page["Amazon"] = "";
		$this->page["Goofs"] = "";
		$this->page["Plot"] = "";
		$this->page["Comments"] = "";
		$this->page["Quotes"] = "";
		$this->page["Taglines"] = "";
		$this->page["Plotoutline"] = "";
		$this->page["Trivia"] = "";
		$this->page["Directed"] = "";

		$this->main_title = "";
		$this->main_year = "";
		$this->main_runtime = "";
		$this->main_rating = "";
		$this->main_comment = "";
		$this->main_votes = "";
		$this->main_language = "";
		$this->main_genre = "";
		$this->main_genres = "";
		$this->main_tagline = "";
		$this->main_plotoutline = "";
		$this->main_alttitle = "";
		$this->main_colors = "";
		$this->credits_cast = "";
		$this->main_director = "";
		$this->main_creator = "";
   
   unset($this->similiar_movies);
   $this->info_excer = new info_extractor();
  }

  /** Initialize class
   * @constructor imdb
   * @param string id
   */
  function imdb ($id) {
   $this->imdb_config();
   $this->setid($id);
   //if ($this->storecache && ($this->cache_expire > 0)) $this->purge();
  }

  /** Check cache and purge outdated files
   *  This method looks for files older than the cache_expire set in the
   *  imdb_config and removes them
   * @method purge
   */
  function purge($explicit = false) {
    if (is_dir($this->cachedir))  {
      $thisdir = dir($this->cachedir);
      $now = time();
      while( $file=$thisdir->read() ) {
        if ($file!="." && $file!="..") {
          $fname = $this->cachedir ."/". $file;
		 if (is_dir($fname)) continue;
          $mod = filemtime($fname);
          if ($mod && (($now - $mod > $this->cache_expire) || $explicit == true)) unlink($fname);
        }
      }
    }
  }
  
  /** Check cache and purge outdated single imdb title file
   *  This method looks for files older than the cache_expire set in the
   *  imdb_config and removes them
   * @method purge
   */
  function purge_single($explicit = false) {
    if (is_dir($this->cachedir)) 
    {
      $thisdir = dir($this->cachedir);
      foreach($this->extension as $ext)
      {
	      $fname = $this->cachedir ."/". $this->imdbid() . ".xml";
	      //return $fname;
	      if(file_exists($fname))
	      {
	      	  $now = time();
	          $mod = filemtime($fname);
	          if ($mod && (($now - $mod > $this->cache_expire) || $explicit == true)) unlink($fname);
	      }
      }
    }
  }

  /** get the time that cache is stored
   * @method getcachetime
   */
  function getcachetime() {
  	$mod =0;
    if (is_dir($this->cachedir)) 
    {
      $thisdir = dir($this->cachedir);
//      foreach($this->extension as $ext)
      {
	      $fname = $this->cachedir ."/". $this->imdbid() . ".xml";
	      if(file_exists($fname))
	      {
//	      	if($mod > filemtime($fname) || $mod==0)
	          $mod = filemtime($fname);
	      }
      }
    }
     return $mod;
  }
  
  /** Set up the URL to the movie title page
   * @method main_url
   * @return string url
   */
  function main_url(){
   return "http://".$this->imdbsite."/title/tt".$this->imdbid()."/";
  }

  /** Get movie title
   * @method title
   * @return string title
   */
  function title () {
   if ($this->main_title == "") {
    if ($this->page["Title"] == "") {
     $this->openpage ("Title");
    }
	/*
    $this->main_title = strstr ($this->page["Title"], "<title>");
    $endpos = strpos ($this->main_title, "</title>");
    $this->main_title = substr ($this->main_title, 7, $endpos - 7);
    $year_s = strpos ($this->main_title, "(", 0);
    $year_e = strpos ($this->main_title, ")", 0);
    $this->main_title = substr ($this->main_title, 0, $year_s - 1);
	*/
	$this->main_title = $this->dbinfo['name'];
   }
   return $this->main_title;
  }

  /** Get year
   * @method year
   * @return string year
   */
  function year () {
   if ($this->main_year == "") {
    if ($this->page["Title"] == "") {
     $this->openpage ("Title");
    }

    $this->main_year = $this->dbinfo[year][0];
   }
   return $this->main_year;
  }
  
    /** 获取译名
   * @method year
   * @return string transname
   */
  function transname () {
   if ($this->main_year == "") {
    if ($this->page["Title"] == "") {
     $this->openpage ("Title");
    }
    $this->main_transname = $this->dbinfo[transname];
   }
   return $this->main_transname;
  }
  

  /** Get general runtime
   * @method runtime_all
   * @return string runtime
   */
  function runtime_all () {
   if ($this->main_runtime == "") {
    if ($this->page["Title"] == "") {
	$this->openpage ("Title");
    }
    $this->main_runtime =  str_replace("分钟"," min",$this->dbinfo[movie_duration][0]);
	}
    return $this->main_runtime;
  }

  /** Get overall runtime
   * @method runtime
   * @return mixed string runtime (if set), NULL otherwise
   */
  function runtime(){
   $runarr = $this->runtimes();
   if (isset($runarr[0]["time"])){
	return $runarr[0]["time"];
   }else{
	return NULL;
   }
  }

  /** Retrieve language specific runtimes
   * @method runtimes
   * @return array runtimes (array[0..n] of array[time,country,comment])
   返回片长数组 array[0..n] of array[time,country,comment]
   */
  function runtimes(){
   if ($this->main_runtimes == "") {
    if ($this->runtime_all() == ""){
	return array();
    }
#echo $this->runtime_all();
    $run_arr= explode( "|" , $this->runtime_all());
    $max = count($run_arr);
    for ( $i=0; $i < $max ; $i++){
	$time_e = strpos( $run_arr[$i], " min");
	$country_e = strpos($run_arr[$i], ":");
	if ( $country_e == 0){
	 $time_s = 0;
	}else{
	 $time_s = $country_e+1;
	}
	$comment_s = strpos( $run_arr[$i], '(');
	$comment_e = strpos( $run_arr[$i], ')');
	$runtemp["time"]= substr( $run_arr[$i], $time_s, $time_e - $time_s);
	$country_s = 0;
	if ($country_s != $country_e){
	 $runtemp["country"]= substr( $run_arr[$i], $country_s, $country_e - $country_s);
	}else{
	 $runtemp["country"]=NULL;
	}
	if ($comment_s != $comment_e){
	 $runtemp["comment"]= substr( $run_arr[$i], $comment_s + 1, $comment_e - $comment_s - 1);
	}else{
	 $runtemp["comment"]=NULL;
	}
	$this->main_runtimes[$i] = $runtemp;
    }
   }
   return $this->main_runtimes;
  }

  /** Get movie rating
   * @method rating
   * @return string rating
   */
  function rating () {
   if ($this->main_rating == "") {
    if ($this->page["Title"] == "") {
	$this->openpage ("Title");
    }
//    $rate_s = strpos ($this->page["Title"], "User Rating:");
#    $rate_s = strpos ($this->page["Title"], '/ratings">');
//    if ( $rate_s == 0 )	return FALSE;
//    if (strpos ($this->page["Title"], "awaiting 5 votes")) return false;
//    $rate_s = strpos ($this->page["Title"], "<b>", $rate_s);
//    $rate_e = strpos ($this->page["Title"], "/", $rate_s);
    $this->main_rating = "<a href=\"".$this->dbinfo[link][alternate]."collections\" target=\"_blank\">".$this->dbinfo[rating]."</a>";
//    if ($rate_e - $rate_s > 7) $this->main_rating = "";
    return $this->main_rating;
   }
  }

  /** Get movie comment
   * @method comment
   * @return string comment
   */
  function comment () {
     if ($this->main_comment == "") {
      if ($this->page["Title"] == "") $this->openpage ("Title");
      $comment_s = strpos ($this->page["Title"], "people found the following comment useful:-");
      if ( $comment_s == 0) return false;
      $comment_e = strpos ($this->page["Title"], "Was the above comment useful to you?", $comment_s);
      $forward_safeval = 50;
      $comment_s_fix = $forward_safeval - strpos(substr($this->page["Title"], $comment_s - $forward_safeval, $comment_e - $comment_s + $forward_safeval),"<div class=\"small\">") - strlen("<div class=\"small\">");
      
      $this->main_comment = substr ($this->page["Title"], $comment_s - $comment_s_fix, $comment_e - $comment_s + $comment_s_fix);
      $this->main_comment = preg_replace("/a href\=\"\//i","a href=\"http://".$this->imdbsite."/",$this->main_comment);
      $this->main_comment = preg_replace("/http:\/\/[a-zA-Z.-]+\/images\/showtimes\//i","pic/imdb_pic/",$this->main_comment);
      $this->main_comment = preg_replace("/<\/?div.*>/i","",$this->main_comment);
      $this->main_comment = preg_replace("/<form.*>/i","",$this->main_comment);
     }
     return $this->main_comment;
  }

  /** Return votes for this movie
   * @method votes
   * @return string votes
   */
  function votes () {
   if ($this->main_votes == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
/*    $vote_s = strpos ($this->page["Title"], "User Rating:");
    if ( $vote_s == 0) return false;
    if (strpos ($this->page["Title"], "awaiting 5 votes")) return false;
//    $vote_s = strpos ($this->page["Title"], "<a", $vote_s);
//    $vote_e = strpos ($this->page["Title"], "votes", $vote_s);
//    $this->main_votes = substr ($this->page["Title"], $vote_s, $vote_e - $vote_s);
    preg_match('/href=\"ratings\".*>([0-9,][0-9,]*)/', $this->page["Title"], $matches);
    $this->main_votes = $matches[1];*/
//    $this->main_votes = /*"<a href=\"http://".$this->imdbsite."/title/tt".$this->imdbID."/ratings\">" . */$this->dbinfo[votes] /*. "</a>"*/;
	$this->main_votes ="<a href=\"".$this->dbinfo[link][alternate]."collections\" target=\"_blank\">".$this->dbinfo[votes]."</a>";
   }
   return $this->main_votes;
  }

  /** Get movies original language
   * @method language
   * @return string language
   */
  function language () {
   if ($this->main_language == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
/*    $lang_s = strpos ($this->page["Title"], "/Sections/Languages/");
    if ( $lang_s == 0) return FALSE;
    $lang_s = strpos ($this->page["Title"], ">", $lang_s);
    $lang_e = strpos ($this->page["Title"], "<", $lang_s);
    $this->main_language = substr ($this->page["Title"], $lang_s + 1, $lang_e - $lang_s - 1);
*/
	$this->main_language = $this->dbinfo['language'][0];
   }
   return $this->main_language;
  }

  /** Get all langauges this movie is available in
   * @method languages
   * @return array languages (array[0..n] of strings)
   */
  function languages () {
   if ($this->main_languages == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
//    $lang_s = 0;
//    $lang_e = 0;
    $this->main_languages = array();
    /*while (strpos($this->page["Title"], "/Sections/Languages/", $lang_e) > $lang_s) {
	$lang_s = strpos ($this->page["Title"], "/Sections/Languages/", $lang_s);
	$lang_s = strpos ($this->page["Title"], ">", $lang_s);
	$lang_e = strpos ($this->page["Title"], "<", $lang_s);
	$this->main_languages[$i] = substr ($this->page["Title"], $lang_s + 1, $lang_e - $lang_s - 1);
	$i++;
    }*/
	foreach($this->dbinfo['language'] as $key => $value)$this->main_languages[]=$value;
	
   }
   return $this->main_languages;
  }

  /** Get the movies main genre
   * @method genre
   * @return string genre
   */
  function genre () {
   if ($this->main_genre == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
/*    $genre_s = strpos ($this->page["Title"], "/Sections/Genres/");
    if ( $genre_s === FALSE )	return FALSE;
    $genre_s = strpos ($this->page["Title"], ">", $genre_s);
    $genre_e = strpos ($this->page["Title"], "<", $genre_s);
     substr ($this->page["Title"], $genre_s + 1, $genre_e - $genre_s - 1);*/
	 
	 if(isset($this->dbinfo['movie_type']))//added by pirateutopia
	foreach($this->dbinfo['movie_type'] as $key => $value){
			if($key > 0)
				$this->main_genre .="，".$value;
			else
				$this->main_genre .= $value;
			}
   }
   return $this->main_genre;
  }

  /** Get all genres the movie is registered for
   * @method genres
   * @return array genres (array[0..n] of strings)
   */
  function genres () {
   if ($this->main_genres == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
    $this->main_genres = array();
/*    $genre_s = strpos($this->page["Title"],"/Sections/Genres/") -5;
    if ($genre_s === FALSE) return array(); // no genre found
    if ($genre_s < 0) return array(); // no genre found
    $genre_e = strpos($this->page["Title"],"/rg/title-tease/",$genre_s);
    $block = substr($this->page["Title"],$genre_s,$genre_e-$genre_s);
    $diff = $genre_e-$genre_s;
    $genre_s = 0;
    $genre_e = 0;
    $i = 0;
    while (strpos($block, "/Sections/Genres/", $genre_e) > $genre_s) {
	$genre_s = strpos ($block, "/Sections/Genres/", $genre_s);
	$genre_s = strpos ($block, ">", $genre_s);
	$genre_e = strpos ($block, "<", $genre_s);
	$this->main_genres[$i] = substr ($block, $genre_s + 1, $genre_e - $genre_s - 1);
	$i++;
    }*/
	$i = 0;
	for ( $i = 0; $i < count ($this->dbinfo['movie_type']); $i++)
		$this->main_genres[] = $this->dbinfo['movie_type'][$i];
	
   }
   return $this->main_genres;
  }

  /** Get colors
   * @method colors
   * @return array colors (array[0..1] of strings)
   */
  function colors () {
   if ($this->main_colors == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
    $color_s = 0;
    $color_e = 0;
    $i = 0;
    while (strpos ($this->page["Title"], "/List?color-info", $color_e) > $color_s) {
	$color_s = strpos ($this->page["Title"], "/List?color-info", $color_s);
	$color_s = strpos ($this->page["Title"], ">", $color_s);
	$color_e = strpos ($this->page["Title"], "<", $color_s);
	$this->main_colors[$i] = substr ($this->page["Title"], $color_s + 1, $color_e - $color_s - 1);
	$i++;
    }
   }
   return $this->main_colors;
  }

  /** Get the main tagline for the movie
   * @method tagline
   * @return string tagline
   */
  function tagline () {
   if ($this->main_tagline == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
/*    $tag_s = strpos ($this->page["Title"], "Tagline:");
    if ( $tag_s == 0) return FALSE;
    $tag_s = strpos ($this->page["Title"], ">", $tag_s);
    $tag_e = strpos ($this->page["Title"], "<", $tag_s);
    $this->main_tagline = substr ($this->page["Title"], $tag_s + 1, $tag_e - $tag_s - 1);*/
	
	for ( $i = 0; $i < count ($this->dbinfo['tag']); $i++){
			if($i > 0)
				$this->main_tagline .=", <a href=http://movie.douban.com/tag/".$this->dbinfo['tag'][$i]." target=\"_blank\">".$this->dbinfo['tag'][$i]."</a>";
			else
				$this->main_tagline .="<a href=http://movie.douban.com/tag/".$this->dbinfo['tag'][$i]." target=\"_blank\">".$this->dbinfo['tag'][$i]."</a>";
		}
   }
   return $this->main_tagline;
  }

  /** Get the main Plot outline for the movie
   * @method plotoutline
   * @return string plotoutline
   */
  function plotoutline () {
    if ($this->main_plotoutline == "") {
      if ($this->page["Title"] == "") $this->openpage ("Title");
      /*
	  $plotoutline_s = strpos ($this->page["Title"], "Plot:");
      if ( $plotoutline_s == 0) return FALSE;
      $plotoutline_s = strpos ($this->page["Title"], ">", $plotoutline_s);
      $plotoutline_e = strpos ($this->page["Title"], "<", $plotoutline_s);
      $this->main_plotoutline = substr ($this->page["Title"], $plotoutline_s + 1, $plotoutline_e - $plotoutline_s - 1);
	  */
	  $this->main_plotoutline = $this->dbinfo["summary"];
    }
	
	
    return $this->main_plotoutline;
  }

  /** Get the movies plot(s)
   * @method plot
   * @return array plot (array[0..n] of strings)
   */
  function plot () {
   if ($this->plot_plot == "") {
    if ($this->page["Plot"] == "") $this->openpage ("Plot");
    $plot_e = 0;
    $i = 0;
    $this->plot_plot = array();
    while (($plot_s = strpos ($this->page["Plot"], "<p class=\"plotpar\">", $plot_e)) !== FALSE) 
    {
		$plot_e = strpos ($this->page["Plot"], "</p>", $plot_s);
		$tmplot = substr ($this->page["Plot"], $plot_s + 19, $plot_e - $plot_s - 19);
		$tmplot = str_replace("href=\"/", "href=\"http://". $this->imdbsite ."/", $tmplot); 
		$this->plot_plot[$i] = $tmplot;
		$i++;
    }
   }
   return $this->plot_plot;
  }

  /** Get all available taglines for the movie
   * @method taglines
   * @return array taglines (array[0..n] of strings)
   */
  function taglines () {
   if ($this->taglines == "") {
    if ($this->page["Taglines"] == "") $this->openpage ("Taglines");
    $tags_e = 0;
    $i = 0;
    $tags_s = strpos ($this->page["Taglines"], "<td width=\"90%\" valign=\"top\" >", $tags_e);
    $tagend = strpos ($this->page["Taglines"], "<form method=\"post\" action=\"/updates\">", $tags_s);
    $this->taglines = array();
    while (($tags_s = strpos ($this->page["Taglines"], "<p>", $tags_e)) < $tagend) {
	$tags_e = strpos ($this->page["Taglines"], "</p>", $tags_s);
	$tmptag = substr ($this->page["Taglines"], $tags_s + 3, $tags_e - $tags_s - 3);
	if (preg_match("/action\=\"\//i",$tmptag)) continue;
	$this->taglines[$i] = $tmptag;
	$i++;
    }
   }
   return $this->taglines;
  }

  /** Get rows for a given table on the page
   * @method get_table_rows
   * @param string html
   * @param string table_start
   * @return mixed rows (FALSE if table not found, array[0..n] of strings otherwise)
   */
  function get_table_rows ( $html, $table_start ){
   $row_s = strpos ( $html, ">".$table_start."<");
   $row_e = $row_s;
   if ( $row_s == 0 )  return FALSE;
   $endtable = strpos($html, "</table>", $row_s);
   $i=0;
   while ( ($row_e + 5 < $endtable) && ($row_s != 0) ){
     $row_s = strpos ( $html, "<tr>", $row_s);
     $row_e = strpos ($html, "</tr>", $row_s);
     $temp = trim(substr ($html, $row_s + 4 , $row_e - $row_s - 4));
     if ( strncmp( $temp, "<td valign=",10) == 0 ){
       $rows[$i] = $temp;
       $i++;
     }
     $row_s = $row_e;
   }
   return $rows;
  }

  /** Get rows for the cast table on the page
   * @method get_table_rows_cast
   * @param string html
   * @param string table_start
   * @return mixed rows (FALSE if table not found, array[0..n] of strings otherwise)
   */
  function get_table_rows_cast ( $html, $table_start ){
   $row_s = strpos ( $html, '<table class="cast">');
   $row_e = $row_s;
   if ( $row_s == 0 )  return FALSE;
   $endtable = strpos($html, "</table>", $row_s);
   $i=0;
   while ( ($row_e + 5 < $endtable) && ($row_s != 0) ){
     $row_s = strpos ( $html, "<tr", $row_s);
     $row_e = strpos ($html, "</tr>", $row_s);
     $temp = trim(substr ($html, $row_s , $row_e - $row_s));
#     $row_x = strpos( $temp, '<td valign="middle"' );
     $row_x = strpos( $temp, '<td class="nm">' );
     $temp = trim(substr($temp,$row_x));
     if ( strncmp( $temp, "<td class=",10) == 0 ){
       $rows[$i] = $temp;
       $i++;
     }
     $row_s = $row_e;
   }
   return $rows;
  }

  /** Get content of table row cells
   * @method get_row_cels
   * @param string row (as returned by imdb::get_table_rows)
   * @return array cells (array[0..n] of strings)
   */
  function get_row_cels ( $row ){
   $cel_s = 0;
   $cel_e = 0;
   $endrow = strlen($row);
   $i = 0;
   $cels = array();
   while ( $cel_e + 5 < $endrow ){
	$cel_s = strpos( $row, "<td",$cel_s);
	$cel_s = strpos( $row, ">" , $cel_s);
	$cel_e = strpos( $row, "</td>", $cel_s);
	$cels[$i] = substr( $row, $cel_s + 1 , $cel_e - $cel_s - 1);
	$i++;
   }
   return $cels;
  }

  /** Get the IMDB name (?)
   * @method get_imdbname
   * @param string href
   * @return string
   */
  function get_imdbname( $href){
   if ( strlen( $href) == 0) return $href;
   $name_s = 15;
   $name_e = strpos ( $href, '"', $name_s);
   if ( $name_e != 0){
	return substr( $href, $name_s, $name_e -1 - $name_s);
   }else{
	return $href;
   }
  }

  /** Get the director(s) of the movie
   * @method director
   * @return array director (array[0..n] of strings)
   */
  function director () {
   if ($this->credits_director == ""){
    if ($this->page["Credits"] == "") $this->openpage ("Credits");
   }
/*
   $director_rows = $this->get_table_rows($this->page["Credits"], "Directed by");
   for ( $i = 0; $i < count ($director_rows); $i++){
	$cels = $this->get_row_cels ($director_rows[$i]);
	if (!isset ($cels[0])) return array();
	$dir["imdb"] = $this->get_imdbname($cels[0]);
	$dir["name"] = strip_tags($cels[0]);
	$role = trim(strip_tags($cels[2]));
	if ( $role == ""){
		$dir["role"] = NULL;
	}else{
		$dir["role"] = $role;
	}
	$this->credits_director[$i] = $dir;
	
	}
	*/
	$this->credits_director[] = array("name" => $this->dbinfo[director][0] );
	return $this->credits_director;
  }

  /** Get the creator of the tv series
   * @method creator
   * @return string
   */
  function creator(){
   if ($this->main_creator == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");

    $vote_s = strpos ($this->page["Title"], "Creator:");
    if ( $vote_s == 0) return false;
    $vote_s = strpos ($this->page["Title"], "<a", $vote_s);
    $vote_e = strpos ($this->page["Title"], "</a>", $vote_s);
    $this->main_creator = substr ($this->page["Title"], $vote_s, $vote_e - $vote_s);
    $this->main_creator = str_replace("/name","http://".$this->imdbsite."/name", $this->main_creator);
    $this->main_creator .= "</a>";

   }
   return $this->main_creator;
  }

  /** Get the actors
   * @method cast
   * @return array cast (array[0..n] of strings)
   */
  function cast () {
   if ($this->credits_cast == "") {
    if ($this->page["Credits"] == "") $this->openpage ("Credits");
   }
   /*
   $cast_rows = $this->get_table_rows_cast($this->page["Credits"], "Cast");
   for ( $i = 0; $i < count ($cast_rows); $i++){
	$cels = $this->get_row_cels ($cast_rows[$i]);
	if (!isset ($cels[0])) return array();
	$dir["imdb"] = $this->get_imdbname($cels[0]);
	$dir["name"] = strip_tags($cels[0]);
	$role = strip_tags($cels[2]);
	if ( $role == ""){
		$dir["role"] = NULL;
	}else{
		$dir["role"] = $role;
	}
	$this->credits_cast[$i] = $dir;
   }*/
    for ( $i = 0; $i < count ($this->dbinfo['cast']); $i++)
   $this->credits_cast[]=array("name" => $this->dbinfo['cast'][$i]);
   return $this->credits_cast;
  }

  /** Get the writer(s)
   * @method writing
   * @return array writers (array[0..n] of strings)
   */
  function writing () {
   if ($this->credits_writing == "") {
    if ($this->page["Credits"] == "") $this->openpage ("Credits");
   }
   $this->credits_writing = array();
/*   $writing_rows = $this->get_table_rows($this->page["Credits"], "Writing credits");
   for ( $i = 0; $i < count ($writing_rows); $i++){
     $cels = $this->get_row_cels ($writing_rows[$i]);
     if ( count ( $cels) > 2){
       $wrt["imdb"] = $this->get_imdbname($cels[0]);
       $wrt["name"] = strip_tags($cels[0]);
       $role = strip_tags($cels[2]);
       if ( $role == ""){
         $wrt["role"] = NULL;
       }else{
         $wrt["role"] = $role;
       }
       $this->credits_writing[$i] = $wrt;
     }
   }

*/
	 for ( $i = 0; $i < count ($this->dbinfo['writer']); $i++)
		$this->credits_writing[]=array("name" => $this->dbinfo['writer'][$i]);
   return $this->credits_writing;
  }

  /** Obtain the producer(s)
   * @method producer
   * @return array producer (array[0..n] of strings)
   */
  function producer () {
   if ($this->credits_producer == "") {
    if ($this->page["Credits"] == "") $this->openpage ("Credits");
   }
   $this->credits_producer = array();
   $producer_rows = $this->get_table_rows($this->page["Credits"], "Produced by");
   for ( $i = 0; $i < count ($producer_rows); $i++){
	$cels = $this->get_row_cels ($producer_rows[$i]);
	if ( count ( $cels) > 2){
		$wrt["imdb"] = $this->get_imdbname($cels[0]);
		$wrt["name"] = strip_tags($cels[0]);
		$role = strip_tags($cels[2]);
		if ( $role == ""){
			$wrt["role"] = NULL;
		}else{
			$wrt["role"] = $role;
		}
		$this->credits_producer[$i] = $wrt;
	}
   }
   return $this->credits_producer;
  }

  /** Obtain the composer(s) ("Original Music by...")
   * @method composer
   * @return array composer (array[0..n] of strings)
   */
  function composer () {
   if ($this->credits_composer == "") {
    if ($this->page["Credits"] == "") $this->openpage ("Credits");
   }
   $this->credits_composer = array();
   $composer_rows = $this->get_table_rows($this->page["Credits"], "Original Music by");
   for ( $i = 0; $i < count ($composer_rows); $i++){
	$cels = $this->get_row_cels ($composer_rows[$i]);
	if ( count ( $cels) > 2){
		$wrt["imdb"] = $this->get_imdbname($cels[0]);
		$wrt["name"] = strip_tags($cels[0]);
		$role = strip_tags($cels[2]);
		if ( $role == ""){
			$wrt["role"] = NULL;
		}else{
			$wrt["role"] = $role;
		}
		$this->credits_composer[$i] = $wrt;
	}
   }
   return $this->credits_composer;
  }

  /** Get cover photo
   * @method photo
   * @return mixed photo (string url if found, FALSE otherwise)
   */
  function photo () {
   if ($this->main_photo == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
	/*
#    $tag_s = strpos ($this->page["Title"], "<img border=\"0\" alt=\"cover\"");
    $tag_s = strpos ($this->page["Title"], "<a name=\"poster\"");
    if ($tag_s == 0) return FALSE;
#    $tag_s = strpos ($this->page["Title"], "http://ia.imdb.com/media",$tag_s);
    $tag_s = strpos ($this->page["Title"], "http://",$tag_s);
    $tag_e = strpos ($this->page["Title"], '"', $tag_s);
    $this->main_photo = substr ($this->page["Title"], $tag_s, $tag_e - $tag_s);
    if ($tag_s == 0) return FALSE;
	*/
	$this->main_photo=$this->dbinfo[link][image];
   }
   return $this->main_photo;
  }

  /** Save the photo to disk
   * @method savephoto
   * @param string path
   * @return boolean success
   */
  function savephoto ($path) {
   $req = new IMDB_Request("");
   $photo_url = $this->photo ();
   if (!$photo_url) return FALSE;
   $req->setUrl($photo_url);
   $response = $req->send();
   if (strpos($response->getHeader("Content-Type"),'image/jpeg') === 0
     || strpos($response->getHeader("Content-Type"),'image/gif') === 0
     || strpos($response->getHeader("Content-Type"), 'image/bmp') === 0 ){
	$fp = $response->getBody();
   }else{
	//echo "<BR>*photoerror* ".$photo_url.": Content Type is '".$req->getResponseHeader("Content-Type")."'<BR>";
	return false;
   }

   $fp2 = fopen ($path, "w");
   if ((!$fp) || (!$fp2)){
     echo "image error...<BR>";
     return false;
   }

   fputs ($fp2, $fp);
   return TRUE;
  }

  /** Get the URL for the movies cover photo
   * @method photo_localurl
   * @return mixed url (string URL or FALSE if none)
   */
  function photo_localurl(){
   $path = $this->photodir.$this->imdbid().".jpg";
   if ( @fopen($path,"r")) return $this->photoroot.$this->imdbid().'.jpg';
   if ($this->savephoto($path))	return $this->photoroot.$this->imdbid().'.jpg';
   return false;
  }

  /** Get country of production
   * @method country
   * @return array country (array[0..n] of string)
   */
  function country () 
  {
   if ($this->main_country == "") 
   {
    if ($this->page["Title"] == "") $this->openpage ("Title");
    $this->main_country = array();
	$this->main_country=$this->dbinfo[country];
	/*
    $country_s = strpos($this->page["Title"],"/Sections/Countries/") -5;
    if ($country_s === FALSE) return array(); // no country found
    if ($country_s < 0) return array(); // no country found
		//print($country_s);
		//print($this->page["Title"]);
    $country_e = strpos($this->page["Title"],"</div>",$country_s);
    $block = substr($this->page["Title"],$country_s,$country_e-$country_s);
    $country_s = 0;
    $country_e = 0;
    $i = 0;
    while (strpos ($block, "/Sections/Countries/", $country_e) > $country_s) 
    {
			$country_s = strpos ($block, "/Sections/Countries/", $country_s);
			$country_s = strpos ($block, ">", $country_s);
			$country_e = strpos ($block, "<", $country_s);
			$this->main_country[$i] = substr ($block, $country_s + 1, $country_e - $country_s - 1);
			$i++;
    }*/
   }
   return $this->main_country;
  }

  /** Get movies alternative names
   * @method alsoknow
   * @return array aka (array[0..n] of strings)
   */
  function alsoknow () {
   if ($this->main_alsoknow == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
/*
    $ak_s = strpos ($this->page["Title"], "Also Known As:</h5>");
    if ($ak_s>0) $ak_s += 19;
    if ($ak_s == 0) $ak_s = strpos ($this->page["Title"], "Alternativ:");
    if ($ak_s == 0) return array();
    $alsoknow_end = strpos ($this->page["Title"], "</div>", $ak_s);
    $alsoknow_all = strip_tags(substr($this->page["Title"], $ak_s, $alsoknow_end - $ak_s), '<br>');
    $alsoknow_arr = explode ( "<br>", $alsoknow_all);
    $j=0;
    for ( $i=0; $i< count($alsoknow_arr); $i++){
      if (strpos($alsoknow_arr[$i],"href=")!==FALSE) continue; // link to more AKAs
      $alsoknow_arr[$i] = trim($alsoknow_arr[$i]);
      if (strlen($alsoknow_arr[$i])>0) {
	$tmparr = explode('(', $alsoknow_arr[$i]);
        unset($ak_temp);
	$ak_temp["title"]= $tmparr[0];
        $elems = count($tmparr);
        for ($k=1;$k<$elems;++$k) {
          if (strpos($tmparr[$k],')'))
            $var = substr($tmparr[$k],0,strrpos($tmparr[$k],')'));
          else $var = $tmparr[$k];
          if (!isset($ak_temp["year"])) {
            if (is_numeric($var)) {
              $ak_temp["year"] = $var;
              continue;
            } else {
              $ak_temp["year"] = "";
            }
            if ( ($country_e = strpos($var, ":")) != 0){
              $ak_temp["country"]= substr( $var, 0, $country_e);
              $ak_temp["comment"]= substr( $var, $country_e+2, strlen($var) - $country_e -2);
            }else{
              $ak_temp["country"]= $var;
            }
          } elseif (!isset($ak_temp["country"])) {
            if ( ($country_e = strpos($var, ":")) != 0){
              $ak_temp["country"]= substr( $var, 0, $country_e);
              $ak_temp["comment"]= substr( $var, $country_e+2, strlen($var) - $country_e -2);
            }else{
              $ak_temp["country"]= $var;
            }
          } else {
            if (strpos($var,')'))
              $var = substr($var,0,strrpos($var,')'));
            if (isset($ak_temp["comment"]) && !empty($ak_temp["comment"])) $ak_temp["comment"] .= " $var";
            else $ak_temp["comment"] = $var;
          }
        }
        if (!isset($ak_temp["year"])) $ak_temp["year"] = "";
        if (!isset($ak_temp["country"])) $ak_temp["country"] = "";
        if (!isset($ak_temp["comment"])) $ak_temp["comment"] = "";
        $this->main_alsoknow[$j] = $ak_temp;
	$j++;
      }
    }
   }
   
   */
    for ( $i = 0; $i < count ($this->dbinfo['aka']); $i++)
			if(!($value == $this->dbinfo['aka'][0] && $key !=0))
				$this->main_alsoknow[]['title']=$this->dbinfo['aka'][$i];
				
	}		
   return $this->main_alsoknow;
  }

  /** Get sound formats
   * @method sound
   * @return array sound (array[0..n] of strings)
   */
  function sound () {
   if ($this->main_sound == "") {
    if ($this->page["Title"] == "") $this->openpage ("Title");
    $sound_s = 0;
    $sound_e = 0;
    $i = 0;
    while (strpos ($this->page["Title"], "/List?sound", $sound_e) > $sound_s) {
	$sound_s = strpos ($this->page["Title"], "/List?sound", $sound_s);
	$sound_s = strpos ($this->page["Title"], ">", $sound_s);
	$sound_e = strpos ($this->page["Title"], "<", $sound_s);
	$this->main_sound[$i] = substr ($this->page["Title"], $sound_s + 1, $sound_e - $sound_s - 1);
	$i++;
    }
   }
   return $this->main_sound;
  }

  /** Get the MPAA data (PG?)
   * @method mpaa
   * @return array mpaa (array[country]=rating)
   */
  function mpaa () { // patch by Brian Ruth not yet tested (by me...)
   if (empty($this->main_mpaa)) {
    if ($this->page["Title"] == "") $this->openpage ("");
    $mpaa_s = 0;
    $mpaa_e = 0;
    $this->main_mpaa = array();
    while (strpos ($this->page["Title"], "/List?certificates", $mpaa_e) > $mpaa_s) {
	$mpaa_s = strpos ($this->page["Title"], "/List?certificates", $mpaa_s);
	$mpaa_s = strpos ($this->page["Title"], ">", $mpaa_s);
	$mpaa_c = strpos ($this->page["Title"], ":", $mpaa_s);
	$mpaa_e = strpos ($this->page["Title"], "<", $mpaa_s);
	$country = substr ($this->page["Title"], $mpaa_s + 1, $mpaa_c - $mpaa_s - 1);
	$rating = substr ($this->page["Title"], $mpaa_c + 1, $mpaa_e - $mpaa_c - 1);
	$this->main_mpaa[$country] = $rating;
    }
   }
   return $this->main_mpaa;
  }

 } // end class imdb

 #====================================================[ IMDB Search class ]===
 /** Search the IMDB for a title and obtain the movies IMDB ID
  * @package Api
  * @class imdbsearch
  * @extends imdb_config
  */
  
 class imdbsearch extends imdb_config{
  var $page = "";
  var $name = NULL;
  var $resu = array();
  var $url = "http://www.imdb.com/";

  /** Read the config
   * @constructor imdbsearch
   */
  function imdbsearch() {
    $this->imdb_config();
  }

  /** Set the name (title) to search for
   * @method setsearchname
   */
  function setsearchname ($name) {
   $this->name = $name;
   $this->page = "";
   $this->url = NULL;
  }

  /** Set the URL
   * @method seturl
   */
  function seturl($url){
   $this->url = $url;
  }

  /** Create the IMDB URL for the movie search
   * @method mkurl
   * @return string url
   */
  function mkurl () {
   if ($this->url !== NULL){
    $url = $this->url;
   }else{
     if (!isset($this->maxresults)) $this->maxresults = 20;
     switch ($this->searchvariant) {
       case "moonface" : $query = ";more=tt;nr=1"; // @moonface variant (untested)
       case "sevec"    : $query = "&restrict=Movies+only&GO.x=0&GO.y=0&GO=search;tt=1"; // Sevec ori
       default         : $query = ";tt=on"; // Izzy
     }
     if ($this->maxresults > 0) $query .= ";mx=20";
     $url = "http://".$this->imdbsite."/find?q=".rawurlencode($this->name).$query;
   }
   return $url;
  }

  /** Setup search results
   * @method results
   * @return array results
   */
  function results ($url="") {
   if ($this->page == "") {
     if (empty($url)) $url = $this->mkurl();
     $be = new IMDB_Request($url);
     $be->sendrequest();
     $fp = $be->getResponseBody();
     if ( !$fp ){
       if ($header = $be->getResponseHeader("Location")){
        if (strpos($header,$this->imdbsite."/find?")) {
          return $this->results($header);
          break(4);
        }
        #--- @moonface variant (not tested)
        # $idpos = strpos($header, "/Title?") + 7;
        # $this->resu[0] = new imdb( substr($header, $idpos,7));
        #--- end @moonface / start sevec variant
        $url = explode("/",$header);
        $id  = substr($url[count($url)-2],2);
        $this->resu[0] = new imdb($id);
        #--- end Sevec variant
        return $this->resu;
       }else{
        return NULL;
       }
     }
     $this->page = $fp;
   } // end (page="")

   $searchstring = array( '<A HREF="/title/tt', '<A href="/title/tt', '<a href="/Title?', '<a href="/title/tt');
   $i = 0;
   foreach( $searchstring as $srch){
    $res_e = 0;
    $res_s = 0;
    $len = strlen($srch);
    while ((($res_s = strpos ($this->page, $srch, $res_e)) > 10)) {
      $res_e = strpos ($this->page, "(", $res_s);
      $tmpres = new imdb ( substr($this->page, $res_s+$len, 7)); // make a new imdb object by id
      $ts = strpos($this->page, ">",$res_s) +1; // >movie title</a>
      $te = strpos($this->page,"<",$ts);
      $tmpres->main_title = substr($this->page,$ts,$te-$ts);
      $ts = strpos($this->page,"(",$te) +1;
      $te = strpos($this->page,")",$ts);
      $tmpres->main_year=substr($this->page,$ts,$te-$ts);
      $i++;
      $this->resu[$i] = $tmpres;
    }
   }
   return $this->resu;
  }
} // end class imdbsearch

?>
