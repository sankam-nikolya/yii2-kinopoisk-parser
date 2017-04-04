<?php
namespace sankam\parser;

use Yii;
use yii\helpers\BaseFileHelper;

class Kinopoisk
{
    const MOVIE = 0;
    const SERIAL = 1;

    public static $login = 'viktor_r';
    public static $pass = 'viktor_r951';
    public static $cache_dir = '@runtime/kinopoisk';
    public static $use_cache = true;
    public static $cache_expire = 3600;
    public static $parse_trailers = false;

    public function getFilmData($id) {
        if(empty($id)) {
            return self::t('messages', 'The Film id is not specified');
        }

        $parser = self::Init();

        $data = $parser->getFilmData($id);

        return $data;
    }

    public function getFilmRating($id) {
        if(empty($id)) {
            return self::t('messages', 'The Film id is not specified');
        }

        $parser = self::Init();

        $data = $parser->getRating($id);

        return $data;
    }

    public function find($title, $year = null, $type = self::MOVIE) {
        if(empty($title)) {
            return self::t('messages', 'The Film title is not specified');
        }

        $parser = self::Init();

        $data = $parser->search($title, $year, $type);

        return $data;
    }

    private function Init() {
        $options = [
                'login' => self::$login,
                'pass' => self::$pass,
                'usecache' => self::$use_cache,
                'cache_dir' => self::cacheDir(self::$cache_dir),
                'cache_expire' => self::$cache_expire,
                'parse_trailers' => self::$parse_trailers
            ];

        $parser =  new Kpparser($options);

        return $parser;
    }

    private function cacheDir() {
        $dir = Yii::getAlias(self::$cache_dir);

        if(!file_exists($dir)) {
            BaseFileHelper::createDirectory($dir, '0755');
        }

        return $dir;
    }

    public static function registerTranslations()
    {
        $i18n = \Yii::$app->i18n;
        $i18n->translations['menu/*'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en',
            'basePath' => '@vendor/sankam-nikolya/yii2-kinopoisk-parser/messages',
            'fileMap' => [
                'kinopoisk/messages' => 'messages.php',
            ],
        ];
    }
    public static function t($category, $message, $params = [], $language = null)
    {
        return \Yii::t('kinopoisk/' . $category, $message, $params, $language);
    }
}

?>
