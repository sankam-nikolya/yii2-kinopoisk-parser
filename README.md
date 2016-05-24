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
"sankam-nikolya/yii2-kinopoisk-parser": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :


```php

use sankam\parser\Kinopoisk;

```

```php

	$data = Kinopoisk::getFilmData('160946');
	$data1 = Kinopoisk::getFilmRating('160946');
	$data2 = Kinopoisk::find('Первый мститель');

```