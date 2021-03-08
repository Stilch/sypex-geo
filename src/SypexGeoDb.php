<?php

declare(strict_types=1);

namespace SypexGeo;

use SypexGeo\Exception\FileErrorException;

final class SypexGeoDb implements SypexGeoInterface
{
    /**
     * Кодировка на выходе
     */
    private const OUTPUT_CHARSET = 'UTF-8';

    /**
     * Префикс файла БД SypexGeo
     */
    private const DB_FILE_PREFIX = 'SxG';

    /**
     * Формат заголовка
     */
    private const HEADER_FORMAT = [
        ['size' => 3, 'name' => 'prefix'],
        ['format' => 'C', 'size' => 1, 'name' => 'version'],
        ['format' => 'N', 'size' => 4, 'name' => 'timestamp'],
        ['format' => 'C', 'size' => 1, 'name' => 'parser'],
        ['format' => 'C', 'size' => 1, 'name' => 'charset'],
        ['format' => 'C', 'size' => 1, 'name' => 'firstOctetIndexCount'],
        ['format' => 'n', 'size' => 2, 'name' => 'mainIndexCount'],
        ['format' => 'n', 'size' => 2, 'name' => 'oneIndexRange'],
        ['format' => 'N', 'size' => 4, 'name' => 'rangesCount'],
        ['format' => 'C', 'size' => 1, 'name' => 'idLength'],
        ['format' => 'n', 'size' => 2, 'name' => 'maxRegionLength'],
        ['format' => 'n', 'size' => 2, 'name' => 'maxCityLength'],
        ['format' => 'N', 'size' => 4, 'name' => 'dbRegionLength'],
        ['format' => 'N', 'size' => 4, 'name' => 'dbCityLength'],
        ['format' => 'n', 'size' => 2, 'name' => 'maxCountryLength'],
        ['format' => 'N', 'size' => 4, 'name' => 'dbCountryLength'],
        ['format' => 'n', 'size' => 2, 'name' => 'packFormatSize'],
    ];

    private const CHARSET = [
        0 => 'UTF-8',
        1 => 'latin1',
        2 => 'cp1251'
    ];

    /**
     * @var bool Кэшировать БД SypexGeo или нет
     */
    private $useDbCache;

    /**
     * @var resource Указатель на файл для чтения
     */
    private $resource;

    /**
     * @var string Префикс файла
     */
    private $prefix;

    /**
     * @var int Версия файла (21 => 2.1)
     */
    private $version;

    /**
     * @var int Время создания файла (Unix timestamp)
     */
    private $timestamp;

    /**
     * @var int Парсер (0 - Universal, 1 - SxGeo Country, 2 - SxGeo City, 11 - GeoIP Country, 12- GeoIP City, 21 - ipgeobase)
     */
    private $parser;

    /**
     * @var string Кодировка (0 - UTF-8, 1 - latin1, 2 - cp1251)
     */
    private $charset;

    /**
     * @var int Элементов в индексе первых байт (до 255)
     */
    private $firstOctetIndexCount;

    /**
     * @var int Элементов в основном индексе (до 65 тыс.)
     */
    private $mainIndexCount;

    /**
     * @var int Блоков в одном элементе индекса (до 65 тыс.)
     */
    private $oneIndexRange;

    /**
     * @var int Количество диапазонов (до 4 млрд.)
     */
    private $rangesCount;

    /**
     * @var int Размер ID-блока в байтах (1 для стран, 3 для городов)
     */
    private $idLength;

    /**
     * @var int Максимальный размер записи региона (до 64 КБ)
     */
    private $maxRegionLength;

    /**
     * @var int Максимальный размер записи страны(до 64 КБ)
     */
    private $maxCountryLength;

    /**
     * @var int Максимальный размер записи города (до 64 КБ)
     */
    private $maxCityLength;

    /**
     * @var int Размер справочника регионов
     */
    private $dbRegionLength;

    /**
     * @var int Размер справочника стран
     */
    private $dbCountryLength;

    /**
     * @var int Размер справочника городов
     */
    private $dbCityLength;

    /**
     * @var int Размер описания формата упаковки города/региона/страны
     */
    private $packFormatSize;

    /**
     * @var array Формат упаковки города/региона/страны
     */
    private $packFormat;

    /**
     * @var string Индекс первых байт (октетов)
     */
    private $firstOctetIndex;

    /**
     * @var string Основной индекс
     */
    private $mainIndex;

    /**
     * @var int Длина одного диапазона в байтах
     */
    private $oneRangeLength;

    /**
     * @var int Начало БД диапазонов
     */
    private $dbRangesBegin;

    /**
     * @var int Начало БД регионов
     */
    private $dbRegionsBegin;

    /**
     * @var int Начало БД городов
     */
    private $dbCitiesBegin;

    /**
     * @var string БД диапазонов
     */
    private $dbRanges;

    /**
     * @var string БД регионов
     */
    private $dbRegions;

    /**
     * @var string БД городов
     */
    private $dbCities;

    /**
     * @var bool Конвертировать кодировку или нет
     */
    private $convertCharset;

    /**
     * @param string $dbFile Путь к файлу БД SypexGeo
     * @param bool $useDbCache Кэшировать БД в памяти или нет (загружает БД в память)
     *
     * @throws FileErrorException Если произошла какая-либо ошибка при чтении/парсинге файла БД
     */
    public function __construct(string $dbFile, bool $useDbCache = false)
    {
        if (!file_exists($dbFile)) {
            throw new FileErrorException("Файл {$dbFile} не найден");
        }

        $this->useDbCache = $useDbCache;

        //Откроем файл для чтения
        $this->resource = fopen($dbFile, 'rb');

        if ($this->resource === false) {
            throw new FileErrorException("Файл {$dbFile} не удалось открыть для чтения");
        }

        //Прочитаем заголовок БД SypexGeo
        $this->readHeader();

        if ($this->prefix !== self::DB_FILE_PREFIX || min(
                $this->firstOctetIndexCount,
                $this->mainIndexCount,
                $this->oneIndexRange,
                $this->rangesCount,
                $this->timestamp,
                $this->idLength
            ) === 0) {
            throw new FileErrorException("Неизвестный формат файла БД SypexGeo");
        }

        //Стоит конвертировать кодировку или нет
        $this->convertCharset = self::OUTPUT_CHARSET !== $this->charset;

        //Прочитаем и распарсим из файла БД описания формата упаковки города/региона/страны
        $this->readPackFormat();

        //Рассчитаем длину одного диапазона
        $this->oneRangeLength = $this->idLength + 3;
        //Загрузим индекс первых байт (октетов)
        $this->firstOctetIndex = $this->read($this->firstOctetIndexCount * 4);
        //Загрузим основной индекс
        $this->mainIndex = $this->read($this->mainIndexCount * 4);

        //Оба преобразования быстрее в 5 раз, чем с циклом
        $this->firstOctetIndex = array_values(unpack("N*", $this->firstOctetIndex));
        $this->mainIndex = str_split($this->mainIndex, 4);

        //Зафиксируем начало БД диапазонов
        $this->dbRangesBegin = ftell($this->resource);
        //Рассчитаем начало БД регионов
        $this->dbRegionsBegin = $this->dbRangesBegin + $this->rangesCount * $this->oneRangeLength;
        //Рассчитаем начало БД городов
        $this->dbCitiesBegin = $this->dbRegionsBegin + $this->dbRegionLength;

        //Загрузим в кэш все БД
        if ($this->useDbCache) {
            $this->dbRanges = $this->read($this->rangesCount * $this->oneRangeLength);
            $this->dbRegions = $this->read($this->dbRegionLength);
            //Справочник стран совмещается со справочником городов (так как могут быть IP у которых не определен город)
            $this->dbCities = $this->read($this->dbCountryLength + $this->dbCityLength, $this->dbCitiesBegin);
            //Закрываем дескриптор файла
            $this->close();
        }
    }

    /**
     * @inheritDoc
     */
    public function getFullInfo(string $ip): ?array
    {
        $indexId = $this->getNumber($ip);
        if ($indexId <= 0) {
            return null;
        }

        if ($this->dbCityLength > 0) {
            return $this->parseCity($indexId, true);
        } else {
            return ['country' => ['id' => $indexId, 'iso' => Iso::ISO[$indexId]]];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCity(string $ip): ?array
    {
        $fullInfo = $this->getFullInfo($ip);

        return isset($fullInfo['city']) ? $fullInfo['city'] : null;
    }

    /**
     * @inheritDoc
     */
    public function getRegion(string $ip): ?array
    {
        $fullInfo = $this->getFullInfo($ip);

        return isset($fullInfo['region']) ? $fullInfo['region'] : null;
    }

    /**
     * @inheritDoc
     */
    public function getCountry(string $ip): ?array
    {
        $fullInfo = $this->getFullInfo($ip);

        return isset($fullInfo['country']) ? $fullInfo['country'] : null;
    }

    /**
     * @inheritDoc
     */
    public function getCountryId(string $ip): int
    {
        if ($this->dbCityLength > 0) {
            $tmp = $this->parseCity($this->getNumber($ip));
            return $tmp['country']['id'];
        } else {
            return $this->getNumber($ip);
        }
    }

    /**
     * @inheritDoc
     */
    public function getCountryIso(string $ip): string
    {
        if ($this->dbCityLength > 0) {
            $tmp = $this->parseCity($this->getNumber($ip));
            return $tmp['country']['iso'];
        } else {
            return Iso::ISO[$this->getNumber($ip)];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCoordinates(string $ip): ?array
    {
        if ($this->dbCityLength === 0) {
            return null;
        }

        $fullInfo = $this->getFullInfo($ip);

        if (isset($fullInfo['city']['lat'], $fullInfo['city']['lon'])) {
            return ['lat' => $fullInfo['city']['lat'], 'lon' => $fullInfo['city']['lon']];
        } elseif (isset($fullInfo['region']['lat'], $fullInfo['region']['lon'])) {
            return ['lat' => $fullInfo['region']['lat'], 'lon' => $fullInfo['region']['lon']];
        } elseif (isset($fullInfo['country']['lat'], $fullInfo['country']['lon'])) {
            return ['lat' => $fullInfo['country']['lat'], 'lon' => $fullInfo['country']['lon']];
        }

        return null;
    }

    /**
     * Считывает заголовок файл БД SypexGeo
     */
    private function readHeader()
    {
        fseek($this->resource, 0);

        foreach (self::HEADER_FORMAT as $oneItem) {
            if (!isset($oneItem['format'])) {
                $result = $this->read($oneItem['size']);
            } else {
                $result = current(unpack($oneItem['format'], $this->read($oneItem['size'])));
            }

            $propertyName = $oneItem['name'];
            $this->$propertyName = $result;
        }

        $this->charset = self::CHARSET[$this->charset];
    }

    private function getNumber(string $ip): int
    {
        $firstOctet = (int)$ip; // Первый октет IP адреса

        $ipLong = ip2long($ip);

        if (in_array(
                $firstOctet,
                [0, 10, 127],
                true
            ) || $firstOctet >= $this->firstOctetIndexCount || $ipLong === false) {
            return 0;
        }

        $ipLong = pack('N', $ipLong);

        // Ищем блок данных в индексе первых байт
        $blocks = ['min' => $this->firstOctetIndex[$firstOctet - 1], 'max' => $this->firstOctetIndex[$firstOctet]];

        if ($blocks['max'] - $blocks['min'] > $this->oneIndexRange) {
            // Ищем блок в основном индексе
            $part = $this->searchInMainIndex(
                $ipLong,
                (int)floor($blocks['min'] / $this->oneIndexRange),
                (int)(floor($blocks['max'] / $this->oneIndexRange) - 1)
            );

            // Нашли номер блока в котором нужно искать IP, теперь находим нужный блок в БД
            $min = $part > 0 ? $part * $this->oneIndexRange : 0;
            $max = $part > $this->mainIndexCount ? $this->rangesCount : ($part + 1) * $this->oneIndexRange;

            // Нужно проверить чтобы блок не выходил за пределы блока первого байта
            if ($min < $blocks['min']) {
                $min = $blocks['min'];
            }
            if ($max > $blocks['max']) {
                $max = $blocks['max'];
            }
        } else {
            $min = $blocks['min'];
            $max = $blocks['max'];
        }

        $len = $max - $min;

        // Находим нужный диапазон в БД
        if ($this->useDbCache) {
            return $this->searchInRangeDb($this->dbRanges, $ipLong, $min, $max);
        }

        $offset = $this->dbRangesBegin + $min * $this->oneRangeLength;

        return $this->searchInRangeDb($this->read($len * $this->oneRangeLength, $offset), $ipLong, 0, $len);
    }

    private function parseCity(int $seek, $full = false): array
    {
        if (is_null($this->packFormat)) {
            return [];
        }

        $onlyCountry = false;
        if ($seek < $this->dbCountryLength) {
            $country = $this->readData($seek, $this->maxCountryLength, 0);
            $city = $this->unpack($this->packFormat[2]);
            $city['lat'] = $country['lat'];
            $city['lon'] = $country['lon'];
            $onlyCountry = true;
        } else {
            $city = $this->readData($seek, $this->maxCityLength, 2);
            $country = ['id' => $city['country_id'], 'iso' => Iso::ISO[$city['country_id']]];
            unset($city['country_id']);
        }

        if ($full) {
            $region = $this->readData($city['region_seek'], $this->maxRegionLength, 1);
            if (!$onlyCountry) {
                $country = $this->readData($region['country_seek'], $this->maxCountryLength, 0);
            }
            unset($city['region_seek'], $region['country_seek']);

            return ['city' => $city, 'region' => $region, 'country' => $country];
        } else {
            unset($city['region_seek']);
            return ['city' => $city, 'country' => ['id' => $country['id'], 'iso' => $country['iso']]];
        }
    }

    /**
     * @param int $seek
     * @param int $length
     * @param int $type Тип БД (0 - города, 1 - регионы, 2 - страны)
     * @return array
     */
    private function readData(int $seek, int $length, int $type): array
    {
        $raw = '';
        if ($seek > 0 && $length > 0) {
            if ($this->useDbCache) {
                $raw = substr($type === 1 ? $this->dbRegions : $this->dbCities, $seek, $length);
            } else {
                $offset = $type === 1 ? $this->dbRegionsBegin : $this->dbCitiesBegin;
                $raw = $this->read($length, $offset + $seek);
            }
        }

        return $this->unpack($this->packFormat[$type], $raw);
    }

    /**
     * Распаковывает бинарные данные в массив согласно формату
     *
     * @param array $packFormat Формат распаковки
     * @param string $raw Бинарная строка
     *
     * @return array Массив данных
     */
    private function unpack(array $packFormat, string $raw = ''): array
    {
        $unpacked = [];
        $emptyRaw = $raw === '';

        $pos = 0;
        foreach ($packFormat as $itemFormat) {
            if ($emptyRaw) {
                $unpacked[$itemFormat['name']] = ($itemFormat['type'] === 'b' || $itemFormat['type'] === 'c') ? '' : 0;
                continue;
            }

            //Определим нужную длину для копирования строки
            $length = $itemFormat['type'] === 'b' ? (strpos($raw, "\0", $pos) - $pos + 1) : $itemFormat['length'];

            //Копируем нужное кол-во байт
            switch ($itemFormat['type']) {
                case 'm':
                    $binStr = substr($raw, $pos, $length);
                    $binStr .= ord($binStr[2]) >> 7 ? "\xff" : "\0";
                    break;
                case 'M':
                    $binStr = substr($raw, $pos, $length) . "\0";
                    break;
                default:
                    $binStr = substr($raw, $pos, $length);
            }

            //Преобразуем в нужный вид
            $value = $itemFormat['format'] === '' ? rtrim($binStr) : current(unpack($itemFormat['format'], $binStr));

            //Для чисел имеющих знаки после запятой, приводим к нужному виду
            if (in_array($itemFormat['type'], ['n', 'N'])) {
                $value = $value / pow(10, $itemFormat['size']);
            }

            //Для строковых значений преобразуем в UTF-8 кодировку
            if ($this->convertCharset && in_array($itemFormat['type'], ['b', 'c'])) {
                $value = mb_convert_encoding($value, self::OUTPUT_CHARSET, $this->charset);
            }

            $pos += $length;
            $unpacked[$itemFormat['name']] = $value;
        }

        return $unpacked;
    }

    private function searchInRangeDb(string $str, string $ipLong, int $min, int $max): int
    {
        if ($max - $min > 1) {
            $ipLong = substr($ipLong, 1);
            while ($max - $min > 8) {
                $offset = ($min + $max) >> 1;
                if ($ipLong > substr($str, $offset * $this->oneRangeLength, 3)) {
                    $min = $offset;
                } else {
                    $max = $offset;
                }
            }

            while ($ipLong >= substr($str, $min * $this->oneRangeLength, 3) && ++$min < $max) {
            }
        } else {
            $min++;
        }

        return hexdec(bin2hex(substr($str, $min * $this->oneRangeLength - $this->idLength, $this->idLength)));
    }


    private function readPackFormat(): void
    {
        if ($this->packFormatSize === 0) {
            return;
        }

        //Прочитаем формат упаковки
        $this->packFormat = $this->read($this->packFormatSize);

        //Заменим snake_case на camelCase
        /*
        $this->packFormat = preg_replace_callback(
            '/_([a-z])/is',
            function ($matches) {
                return strtoupper($matches[1]);
            },
            $this->packFormat
        );*/

        //Разбиваем формат упаковки на формат для города/региона/страны
        $this->packFormat = explode("\0", $this->packFormat);

        //Распарсим формат упаковки
        foreach ($this->packFormat as $key => $formatStr) {
            $format = [];
            $formatArr = explode('/', $formatStr);
            foreach ($formatArr as $item) {
                list($type, $name) = explode(':', $item);

                $size = strlen($type) > 1 ? (int)substr($type, 1) : 1;
                $formatDictionary = [
                    't' => ['length' => 1, 'format' => 'c'],
                    'T' => ['length' => 1, 'format' => 'C'],
                    's' => ['length' => 2, 'format' => 's'],
                    'S' => ['length' => 2, 'format' => 'S'],
                    'n' => ['length' => 2, 'format' => 's'],
                    'm' => ['length' => 3, 'format' => 'l'],
                    'M' => ['length' => 3, 'format' => 'L'],
                    'd' => ['length' => 8, 'format' => 'd'],
                    'c' => ['length' => 1, 'format' => ''],
                    'N' => ['length' => 4, 'format' => 'l'],
                    'i' => ['length' => 4, 'format' => 'l'],
                    'I' => ['length' => 4, 'format' => 'L'],
                    'f' => ['length' => 4, 'format' => 'f'],
                ];

                $length = isset($formatDictionary[$type[0]]) ? $formatDictionary[$type[0]]['length'] : 4;
                if ($type[0] === 'c') {
                    //Строка фиксированного размера. После типа указывается количество символов
                    $length = $size;
                } elseif ($type[0] === 'b') {
                    //Строка завершающаяся нулевым символов
                    $length = null;
                }

                $format[] = [
                    'name' => $name,
                    'type' => $type[0],
                    'length' => $length,
                    'size' => $size,
                    'format' => isset($formatDictionary[$type[0]]) ? $formatDictionary[$type[0]]['format'] : ''
                ];
            }

            $this->packFormat[$key] = $format;
        }
    }

    /**
     * Ищет IP в основном индексе
     *
     * @param string $ipLong Запакованный IP: pack('N', ip2long($ip))
     * @param int $min
     * @param int $max
     *
     * @return int Индекс
     */
    private function searchInMainIndex(string $ipLong, int $min, int $max): int
    {
        while ($max - $min > 8) {
            $offset = ($min + $max) >> 1;
            if ($ipLong > $this->mainIndex[$offset]) {
                $min = $offset;
            } else {
                $max = $offset;
            }
        }

        while ($ipLong > $this->mainIndex[$min] && $min++ < $max) {
        }

        return $min;
    }

    /**
     * Читает нужное кол-во байт из потока
     *
     * @param int $length Кол-во байт для чтения из файла
     * @param int|null $offset Новая позиция указателя (если null, то указатель не остается на прежнем месте)
     *
     * @return string Прочитанная строка
     */
    private function read(int $length, ?int $offset = null): string
    {
        if ($length <= 0 || !is_resource($this->resource)) {
            return '';
        }

        if (!is_null($offset)) {
            fseek($this->resource, $offset);
        }

        return fread($this->resource, $length);
    }

    /**
     * Закроет дескриптор файла при завершении работы
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Закрывает дескриптор файла
     */
    private function close()
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
            $this->resource = null;
        }
    }
}