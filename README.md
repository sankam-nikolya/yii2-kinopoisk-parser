Kinopoisk Parser
===================
Kinopoisk Parser for Yii2

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require --prefer-dist sankam-nikolya/yii2-kinopoisk-parser "*"
```

or add

```
"sankam-nikolya/yii2-kinopoisk-parser": "^1.0"
```

to the require section of your `composer.json` file.


Usage as component:
-----

Config:
```
	'components' => [
        ...
        'kinopoisk' => [
            'class' => 'sankam\parser\kinopoisk',
            'authLogin' => 'login',
            'authPassword' => 'password',
            'cacheExpire' => 3600 * 24, // day
            'parseTrailers' => true
        ]
        ...
    ]

```

then
```
$data1 = Yii::$app->kinopoisk->getFilmData('160946');
$data2 = Yii::$app->kinopoisk->getFilmRating('160946');
$data3 = Yii::$app->kinopoisk->find('Первый мститель');

```

Usage without component:
-----

```
use sankam\parser\Kpparser;

	$kinopoisk = Yii::createObject([
            'class' => Kpparser::className(),
            'authLogin' => 'viktor_r',
            'authPassword' => 'viktor_r951',
            'cacheExpire' => 3600 * 24, // day
            'parseTrailers' => true
        ]);

    $data1 = $kinopoisk->getFilmData('160946');
    $data2 = $kinopoisk->getFilmRating('160946');
    $data3 = $kinopoisk->find('Первый мститель');

```