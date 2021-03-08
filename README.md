# SypexGeo

Определяет геолокацию по IPv4 адресу. Работает с [БД](http://sypexgeo.net/ru/download/) и [API](http://sypexgeo.net/ru/api/) от sypexgeo.net\
Последнюю вресию БД можно скачать на официальном сайте [SypexGeo](http://sypexgeo.net/ru/download/)
## Установка

```sh
composer require stilch/sypex-geo
```

Минимальная версия PHP: 7.2

## Использование локальной БД

Для работы с локальной БД необходимо создать экземпляр класса **SypexGeoDb**
```php
$sypexGeoDb = new SypexGeoDb('pathToDbFile');
```

Если вы собираетесь проверять большое кол-во IP адресов, то для увеличения скорости проверки IP адресов, рекомендуется включить кэширование БД в памяти (ВНИМАНИЕ! Вся БД будет загружена в оперативную память).

```php
//Инициализация с кэшированием БД в памяти
$sypexGeoDb = new SypexGeoDb('pathToDbFile', true);
```
Пример использования:
```php
<?php
declare(strict_types=1);

use SypexGeo\SypexGeoDb;

include __DIR__ . '/vendor/autoload.php';

$sypexGeoDb = new SypexGeoDb('pathToDbFile');

//Определение ID страны
$countryId = $sypexGeoDb->getCountryId($ip);

//Получение всей доступной информации
$ipInfo = $sypexGeoDb->getFullInfo($ip);

//Определение города
$city = $sypexGeoDb->getCity($ip);
```

## Использование REST API SypexGeo

Без API ключа для SypexGeo доступно только 10 000 запросов в месяц. Подробнее в разделе с информацией по [REST API](http://sypexgeo.net/ru/api/).
```php
//Создание экземпляра класса для работы с API без ключа
$sypexGeoDb = new SypexGeoNetwork();

//Создание экземпляра класса для работы с API c ключом
$sypexGeoDb = new SypexGeoNetwork('apiKeySypexGeo');
```
Пример использования:
```php
<?php
declare(strict_types=1);

use SypexGeo\SypexGeoNetwork;

include __DIR__ . '/vendor/autoload.php';

$sypexGeoDb = new SypexGeoNetwork('apiKeySypexGeo');

//Определение ID страны
$countryId = $sypexGeoDb->getCountryId($ip);

//Получение всей доступной информации
$ipInfo = $sypexGeoDb->getFullInfo($ip);

//Определение города
$city = $sypexGeoDb->getCity($ip);
```

## Список доступных методов

### 1. getCountryId
Вернет ID страны или 0 если не удалось определить ID страны

```php
$countryId = $sypexGeo->getCountryId($ip);
```

### 2. getCountryIso
Вернет ISO страны или пустую строку если не удалось определить ISO страны

```php
$countryIso = $sypexGeo->getCountryIso($ip);
```

### 3. getCountry
Вернет массив со всей доступной информацией по стране или null в случае неудачи. Объем доступной информации по стране зависит от используемой БД, подробнее на [оф. сайте](http://sypexgeo.net/ru/editions/). Минимально в массиве будет присутствовать ID и ISO страны при использовании открытой БД Sypex Geo Country. Для более полной информации рекомендуется использовать БД Sypex Geo City или приобрести более полную БД на оф. сайте.

```php
$country = $sypexGeo->getCountry($ip);
```

### 4. getRegion
Вернет массив со всей доступной информацией о регионе или null в случае неудачи. Информация о регионе доступна только в бесплатной БД Sypex Geo City, платных БД или через API.

```php
$region = $sypexGeo->getRegion($ip);
```

### 5. getCity
Вернет массив со всей доступной информацией о городе или null в случае неудачи. Информация о городе доступна только в бесплатной БД Sypex Geo City, платных БД или через API.

```php
$city = $sypexGeo->getCity($ip);
```

### 6. getFullInfo
Вернет массив со всей доступной информацией о стране, регионе и городе или null в случае неудачи. Полная информация доступна только в бесплатной БД Sypex Geo City, платных БД или через API.

```php
$fullInfo = $sypexGeo->getFullInfo($ip);
```

### 7. getCoordinates
Вернет массив с координатами или null в случае неудачи. В случае использования БД Sypex Geo Country это будут координаты страны. Если в используемой БД есть регион или город, то будут возвращены координаты региона или города. Координаты города имеют приоритет над остальными, т.е. если вы используете БД в которой есть координаты города, региона и страны, то будут возвращены координаты города.

```php
$fullInfo = $sypexGeo->getCoordinates($ip);
```