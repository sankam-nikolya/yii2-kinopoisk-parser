<?php

namespace sankam\parser;

use Yii;
use yii\base\Object;
use yii\base\InvalidConfigException;
use linslin\yii2\curl;

class Kpparser extends Object
{
	const MOVIE = 0;
	const SERIAL = 1;

    /**
     * @var string
     */
    public $authLogin;
    /**
     * @var string
     */
    public $authPassword;
    /**
     * @var string
     */
    public $cacheComponent = 'cache';
    /**
     * @var string
     */
    public $componentName = 'kinopoisk';
    /**
     * @var int
     */
    public $cacheExpire = 3600;
    /**
     * @var boolean
     */
    public $parseTrailers = false;

    /**
     * @var boolean
     */
    protected $login = false;
    /**
     * @var object \sankam\parser\Kpparser
     */
    protected $parser;

	protected $auth_url = 'https://www.kinopoisk.ru/login';
	protected $film_url = 'https://www.kinopoisk.ru/film/%s/';
	protected $trailers_url = 'https://www.kinopoisk.ru/film/%s/video/type/1/';
	protected $poster_url = 'http://st.kp.yandex.net/images/film_big/%s.jpg';
	protected $poster_sm_url = 'https://st.kp.yandex.net/images/sm_film/%s.jpg';

	protected $search_film_url = 'https://www.kinopoisk.ru/s/type/film/find/%s/';
	protected $search_film_year_url = 'https://www.kinopoisk.ru/s/type/film/find/%s/m_act[year]/%d/';
	protected $search_serial_url = 'https://www.kinopoisk.ru/index.php?level=7&from=forma&result=adv&m_act%5Bfrom%5D=forma&m_act%5Bwhat%5D=content&m_act%5Bfind%5D=%s&m_act%5Bcontent_find%5D=serial';

	protected $rating_url = 'https://rating.kinopoisk.ru/%s.xml';
	protected $months = [
			1  => 'января',
			2  => 'февраля',
			3  => 'марта',
			4  => 'апреля',
			5  => 'мая',
			6  => 'июня',
			7  => 'июля',
			8  => 'августа',
			9  => 'сентября',
			10 => 'октября',
			11 => 'ноября',
			12 => 'декабря',
		];

	public function init() {
        parent::init();

        if(empty($this->authLogin) || empty($this->authPassword)) {
            throw new InvalidConfigException('`authLogin` and `authPassword` must be set.');
        }

        self::registerTranslations();

        if(!$this->login) {
            $this->auth();
        }
    }

	protected function auth() {
		$this->parser = new Snoopy;
		$this->parser->maxredirs = 2;

		$post_array = array(
			'shop_user[login' => $this->authLogin,
			'password' => $this->authPassword,
			'shop_user[mem]' => 'on',
			'auth' => 'go',
		);

		$this->parser->agent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; uk; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13 Some plugins";
		$login = $this->parser->submit($this->auth_url, $post_array);

		if($login) {
			$this->login = true;
		}
	}

	private function getRating($id) {
		$key_prefix = 'rating_';

		$rating = $this->getCache($key_prefix . $id);

		if($rating) {
			return $rating;
		}

		$data = $this->getPage(sprintf($this->rating_url, $id));

		if(!$data) {
			return false;
		}

		$data = simplexml_load_string($data);

		$rating = new \StdClass();

		$rating->id = $id;
		$rating->rating = (float) $data->kp_rating;
		$rating->votes  = (int) $data->kp_rating['num_vote'];

		$this->setCache($key_prefix . $id, $rating);

		return $rating;
	}

	public function getFilmRating($id) {
		if(empty($id)) {
            return Yii::t('kinopoisk', 'The Film id is not specified');
        }

		return $this->getRating($id);
	}

	public function getFilmData($id) {
		if(empty($id)) {
            return Yii::t('kinopoisk', 'The Film id is not specified');
        }

		$key_prefix = 'filmData_';

		$results = $this->getCache($key_prefix . $id);

		if($results) {
			return $results;
		}

		$main_page = $this->getPage(sprintf($this->film_url, $id));
		$main_page = iconv('windows-1251' , 'utf-8', $main_page);

		$parse = [
			'name' =>         '#<h1.*?class="moviename-big".*?>(.*?)</h1>#si',
			'originalname'=>  '#<span itemprop="alternativeHeadline">(.*?)</span>#si',
			'year' =>         '#год</td>.*?<a[^>]*>(.*?)</a>#si',
			'country_title' =>'#страна</td>.*?<td[^>]*>(.*?)</td>#si',
			'slogan' =>       '#слоган</td><td[^>]*>(.*?)</td></tr>#si',
			'actors_main' =>  '#В главных ролях:</h4>[^<]*<ul>(.*?)</ul>#si',
			'actors_voices' =>'#Роли дублировали:</h4>[^<]*<ul>(.*?)</ul>#si',
			'director' =>     '#режиссер</td><td[^>]*>(.*?)</td></tr>#si',
			'script' =>       '#сценарий</td><td[^>]*>(.*?)</td></tr>#si',
			'producer' =>     '#продюсер</td><td[^>]*>(.*?)</td></tr>#si',
			'operator' =>     '#оператор</td><td[^>]*>(.*?)</td></tr>#si',
			'composer' =>     '#композитор</td><td[^>]*>(.*?)</td></tr>#si',
			'painter' =>      '#художник</td><td[^>]*>(.*?)</td></tr>#si',
			'editor' =>       '#монтаж</td><td[^>]*>(.*?)</td></tr>#si',
			'genre' =>        '#жанр</td><td[^>]*>[^<]*<span[^>]*>(.*?)</span>#si',
			'budget' =>       '#бюджет</td>.*?<a href="/film/[0-9]+/.*?" title="">(.*?)</a>#si',
			'usa_charges' =>  '#сборы в США</td>.*?<a href="/film/[0-9]+/.*?" title="">(.*?)</a>#si',
			'world_charges' =>'#сборы в мире</td>.*?<a href="/film/[0-9]+/.*?" title="">(.*?)</a>#si',
			'rus_charges' =>  '#сборы в России</td>.*?<a href="/film/[0-9]+/.*?/.*?/" title="">(.*?)</a>#si',
			'world_premiere'=>'#премьера \(мир\)</td>[^<]*<td[^>]*>.*?<a[^>]*>(.*?)</a>#si',
			'rus_premiere' => '#премьера \(РФ\)</td>[^<]*<td[^>]*>.*?<a[^>]*>(.*?)</a>#si',
			'ua_premiere' => '#премьера \(Укр\.\)</td>[^<]*<td[^>]*>.*?<a[^>]*>(.*?)</a>#si',
			'time' =>         '#id="runtime">(.*?)</td></tr>#si',
			'age' =>          '#<div class=\"ageLimit(.*?)\">#si',
			'description' =>  '#itemprop="description"[^>]*>(.*?)<\/div>#si',
			'imdb' =>         '#IMDB:\s(.*?)</div>#si',
			'kinopoisk' =>    '#<div id="block_rating".*?<span class="rating_ball">(.*?)</span>#si',
			'kp_votes' =>     '#<span style=\"font:100 14px tahoma, verdana\">(.*?)</span>#si',
			'trailer_url'=>   '#trailerFile.*?\s"(.*)"#i',
		];

		$new = [];

		foreach($parse as $index => $value){
			if (preg_match($value, $main_page, $matches)) {
				if (in_array($index, ['actors_voices','actors_main'])) {
					if (preg_match_all(
							'#<li itemprop="actors"><a href="/name/(\d+)/">(.*?)</a></li>#si',
							$matches[1],
							$matches2,
							PREG_SET_ORDER
						)) {
						$new[$index] = [];
						foreach ($matches2 as $match) {
							if (strip_tags($match[2]) != '...') {
								$tmp = new \StdClass;
								$tmp->name = strip_tags($match[2]);
								$tmp->id = $match[1];

								$new[$index][] = $tmp;
							}
						}
					}
				} else if (in_array($index, ['director','script','producer','operator','composer','painter','editor'])) {
					if (preg_match_all(
							'#<a href="/name/(\d+)/">(.*?)</a>#si',
							$matches[1],
							$matches2,
							PREG_SET_ORDER)) {
						$new[$index] = [];
						foreach ($matches2 as $match) {
							if (strip_tags($match[2]) != '...') {
								$tmp = new \StdClass;
								$tmp->name = strip_tags($match[2]);
								$tmp->id = $match[1];

								$new[$index][] = $tmp;
							}
						}
					}
				} else if ($index == 'genre') {
					if (preg_match_all(
							'#<a href="/lists/.*?/(\d+)/">(.*?)</a>#si',
							$matches[1],
							$matches2,
							PREG_SET_ORDER
							)) {
						$new[$index] = [];
						foreach ($matches2 as $match) {
							if (strip_tags($match[2]) != '...') {
								$tmp = new \StdClass;
								$tmp->title = strip_tags($match[2]);
								$tmp->id = $match[1];

								$new[$index][] = $tmp;
							}
						}
					}
				} else if ($index == 'poster_url') {
					$new[ $index ] = 'https://www.kinopoisk.ru' . $matches[1];
				} else if(in_array($index, ['budget', 'usa_charges', 'world_charges', 'rus_charges'])) {
					$tmp = preg_replace('#\\n\s*#si', '', html_entity_decode(strip_tags($matches[1]), ENT_COMPAT | ENT_HTML401, 'UTF-8'));
					$tmp = str_replace('$', '', $tmp);
					if (strpos($tmp, '=') !== false) {
						$tmp = explode('=', $tmp);
						$tmp = end($tmp);
					}
					$tmp = preg_replace('~\x{00a0}~siu',' ', $tmp);
					$tmp = trim(preg_replace('/\s\s+/', ' ', $tmp));
					$new[ $index ] = $tmp;
				} else if ($index == 'rus_premiere' || $index == 'world_premiere' || $index == 'ua_premiere') {
					$tmp = preg_replace('#\\n\s*#si', '', html_entity_decode(strip_tags($matches[1]), ENT_COMPAT | ENT_HTML401, 'UTF-8'));
					$tmp = str_replace(' ', '.', $tmp);
					$tmp = str_replace(array_values($this->months), array_keys($this->months), $tmp);
					$new[ $index ] = $tmp;
				} else if ($index == 'age') {
					$tmp = preg_replace('#\\n\s*#si', '', html_entity_decode(strip_tags($matches[1]), ENT_COMPAT | ENT_HTML401, 'UTF-8'));
					$tmp = preg_replace('~\x{00a0}~siu',' ', $tmp);
					$tmp = preg_replace('/\s\s+/', ' ', $tmp);
					$tmp = str_replace('age', '', trim($tmp));
					$new[ $index ] = $tmp.'+';
				} else if ($index == 'time') {
					$tmp = preg_replace('#\\n\s*#si', '', html_entity_decode(strip_tags($matches[1]), ENT_COMPAT | ENT_HTML401, 'UTF-8'));
					$tmp = preg_replace('~\x{00a0}~siu',' ', $tmp);
					$tmp = preg_replace('/\s\s+/', ' ', $tmp);
					$tmp = explode('/', $tmp);
					$time = new \StdClass;
					$time->min   = trim(str_replace(' мин.', '', $tmp[0]));
					$time->hours = trim(end($tmp));
					$new[ $index ] = $time;
				} else if($index == 'trailer_url'){
				    if(preg_match(
				    	'/getTrailersDomain[\s\S]*?\'(.*)\'/',
				    	$main_page,
				    	$domains)){
					$trailer_url = sprintf('https://%s/%s', $domains[1], $matches[1]);
					$new[$index] = $trailer_url;
				    }
				} else {
					$new[ $index ] = preg_replace('#\\n\s*#si', '', html_entity_decode(strip_tags($matches[1]), ENT_COMPAT | ENT_HTML401, 'UTF-8'));
					$new[ $index ] = $this->result_clear( $new[ $index ], $index );
				}
			}
		}

		$new['poster_url'] = sprintf($this->poster_url, $id);
		$new['thumb_url'] = sprintf($this->poster_sm_url, $id);

		if($this->parseTrailers) {
			$trailers_page = $this->getPage(sprintf($this->trailers_url, $id));
			$trailers_page = iconv('windows-1251' , 'utf-8', $trailers_page);

			$trailers_parse = [
				'url' =>     '#<a href="/getlink\.php[^"]*?link=([^"]*)" class="continue">(.*?)</a>#si',
				'trailer_page' => '#<a href="([^"]*)" class="all"#si',
				'html'	=> '#<!-- ролик -->([\w\W]*?)<!-- \/ролик -->#si'
			];

			$url = [];
			$trailer_page = [];
			$all_trailers = [];

			foreach($trailers_parse as $index => $regex){
				if ($index == 'html') {
					if (preg_match_all(
							$regex,
							$trailers_page,
							$matches,
							PREG_SET_ORDER)) {
						foreach ($matches as $match) {

							if (preg_match(
									'#<tr>[\w\W]*?<a href="[^"]*" class="all">(.*?)</a>\s*<table[\w\W]*?</table>[\w\W]*?<tr>[\w\W]*?<table[\w\W]*?</table>[\w\W]*?<tr>[\w\W]*?<table[\w\W]*?</td>\s*<td>([\w\W]*?)</table>[\w\W]*?<td[\w\W]*?<td>([\w\W]*?)</table>#si',
									$match[1],
									$title_sd_hd_matches
									)) {
								// название, стандартное качество и HD
								$trailer_family = [];
								$trailer_family['title'] = $title_sd_hd_matches[1];
								// SD качество
								$sd = [];
								if (preg_match_all(
										'#<a href="/getlink\.php[^"]*?link=([^"]*)" class="continue">(.*?)</a>#si',
										$title_sd_hd_matches[2],
										$single_videos,
										PREG_SET_ORDER
										)) {
									foreach ($single_videos as $single_video){
										$sd[] = [
											'url' => $single_video[1],
											'quality' => strip_tags($single_video[2])
										];
									}
								}
								$trailer_family['sd'] = $sd;
								// HD качество
								$hd = [];
								if (preg_match_all(
										'#<a href="/getlink\.php[^"]*?link=([^"]*)" class="continue">(.*?)</a>#si',
										$title_sd_hd_matches[3],
										$single_videos,
										PREG_SET_ORDER
										)) {
									foreach ($single_videos as $single_video) {
										$hd[] = [
											'url' => $single_video[1],
											'quality' => strip_tags($single_video[2])
										];
									}
								}
								$trailer_family['hd'] = $hd;
								$all_trailers[] = $trailer_family;
							}

						}
					}
				} else if (preg_match_all($regex,$trailers_page,$matches,PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						${$index}[] = $match[1];
					}
				}
			}

			$main_trailer_url = [];
			if (isset($trailer_page[0])) {
				$main_trailer_page = $this->getPage('https://www.kinopoisk.ru' . $trailer_page[0]);
				$main_trailer_page = iconv('windows-1251' , 'utf-8', $main_trailer_page);

				if (preg_match_all(
						'#<a href="/getlink\.php[^"]*?link=([^"]*)" class="continue">(.*?)</a>#si',
						$main_trailer_page,
						$matches,
						PREG_SET_ORDER
						)) {
					foreach ($matches as $match) {
						$main_trailer_url[] = [
							'description' => strip_tags($match[2]),
							'url' => $match[1]
						];
					}
				}
			}

			$new['trailer_url'] = $main_trailer_url[count($main_trailer_url)-1]['url'];
			$new['trailers'] = $all_trailers;
		}

		$this->setCache($key_prefix . $id, $new);

		return $new;
	}

	private function search($title, $year = null, $type = self::MOVIE) {
		$key_prefix = 'search_';
		$key_value = $_title . '_' . (int) $year . '_' . $type;

		$results = $this->getCache($key_prefix . $key_value);

		$_title = $title;
		$title = urlencode($title);

		if($results) {
			return $results;
		}

		if($type == self::MOVIE) {

			if(!empty($year)) {
				$url = sprintf($this->search_film_year_url, $title);
			} else {
				$url = sprintf($this->search_film_url, $title, (int) $year);
			}

		} else {
			$url = sprintf($this->search_serial_url, $title);
		}

		$search_page = $this->getPage($url);
		$search_page = iconv('windows-1251' , 'utf-8', $search_page);

		$search_page = preg_match_all(
			'#<p class="name"><a href="\/level\/1\/film\/\d+\/sr\/1\/".*?data-id="(\d+)"[^>]*>(.*?)<\/a>.*?<span class="year">(\d{4})</span>#si',
			$search_page,
			$matches
		);

		$results = [];
		if(!empty($matches[1])) {
			foreach ($matches[1] as $key => $val) {
				$result = new \StdClass;
				$result->id = (int) $val;
				$result->title = $matches[2][$key];
				$result->year = $matches[3][$key];
				$result->thumb = sprintf($this->poster_sm_url, $result->id);

				$results[] = $result;
			}
		} else {
			return false;
		}

		$this->setCache($key_prefix . $key_value, $results);

		return $results;
	}

	public function find($title, $year = null, $type = self::MOVIE) {
		if(empty($title)) {
            return Yii::t('kinopoisk', 'The Film title is not specified');
        }

		return $this->search($title, $year, $type);
	}

	private function getPage($url, $type = 'get') {
        $curl = new curl\Curl();

        $response = $curl->{$type}($url);

        switch ($curl->responseCode) {

            case 'timeout':
                //timeout error logic here
            	return false;
                break;

            case 200:
                return $response;
                break;

            case 404:
                //404 Error logic here
            	return false;
                break;
        }
	}

	private function result_clear( $val, $key = '' ){
		if ( empty( $val ) || $val == '-' ){
			$val = '';
		} else {
			$pattern = array('&nbsp;', '&laquo;', '&raquo;');
			$pattern_replace = array(' ','','');
			$val = str_replace( $pattern, $pattern_replace, $val );
		}
		$val = preg_replace('~\x{00a0}~siu',' ', $val);
		$val = trim(preg_replace('/\s\s+/', ' ', $val));

		switch ($key) {
			case 'genre':
			case 'producer':
			case 'operator':
			case 'director':
			case 'script':
			case 'composer':
				$val = str_replace(', ...', '', $val );
				break;
		}

		return $val;
	}

	private function getCache($key) {
		$cacheKey = [
			'Kinopoisk',
			$key
		];

		return Yii::$app->{$this->cacheComponent}->get($cacheKey);
	}

	private function setCache($key, $value) {
		$cacheKey = [
			'Kinopoisk',
			$key
		];

		Yii::$app->{$this->cacheComponent}->set(
			$cacheKey,
			$value,
			$this->cacheExpire
		);
	}

	private static function registerTranslations()
    {
        if (!isset(Yii::$app->i18n->translations['kinopoisk'])) {
            Yii::$app->i18n->translations['kinopoisk'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => 'en',
                'basePath' => '@sankam/parser/messages',
            ];
        }
    }

}
