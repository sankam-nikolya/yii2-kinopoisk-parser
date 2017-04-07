<?php
namespace sankam\parser;

use Yii;
use yii\base\Component;

class Kinopoisk extends Component
{
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
     * @var boolean
     */
    public $useCache = true;
    /**
     * @var int
     */
    public $cacheExpire = 3600;
    /**
     * @var boolean
     */
    public $parseTrailers = false;
    /**
     * @var object \sankam\parser\Kpparser
     */
    protected $parser;

    public function init() {
        parent::init();

        if(empty($this->authLogin)) {
            throw new InvalidConfigException('`authLogin` must be set.');
        }

        if(empty($this->authPassword)) {
            throw new InvalidConfigException('`authPassword` must be set.');
        }

        $this->parser = Yii::createObject([
            'class' => Kpparser::className(),
            'authLogin' => $this->authLogin,
            'authPassword' => $this->authPassword,
            'cacheComponent' => $this->cacheComponent,
            'componentName' => $this->componentName,
            'cacheExpire' => $this->cacheExpire,
            'parseTrailers' => $this->parseTrailers
        ]);
    }

    public function getFilmData($id) {
        return $this->parser->getFilmData($id);
    }

    public function getFilmRating($id) {
        return $this->parser->getFilmRating($id);
    }

    public function find($title, $year = null, $type = Kpparser::MOVIE) {
        return $this->parser->find($title, $year, $type);
    }
}

?>