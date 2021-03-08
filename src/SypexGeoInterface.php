<?php

namespace SypexGeo;

interface SypexGeoInterface
{
    /**
     * Возвращает ID страны по IP адресу
     *
     * @param string $ip IP адрес
     *
     * @return int ID страны
     */
    public function getCountryId(string $ip): int;

    /**
     * Возвращает ISO страны по IP адресу
     *
     * @param string $ip IP адрес
     *
     * @return string ISO страны
     */
    public function getCountryIso(string $ip): string;

    /**
     * Возвращает информацию о стране по IP адресу
     *
     * @param string $ip IP адрес
     *
     * @return array|null Массив с данными или null в случае неудачи
     */
    public function getCountry(string $ip): ?array;

    /**
     * Возвращает информацию о регионе по IP адресу
     *
     * @param string $ip IP адрес
     *
     * @return array|null Массив с данными или null в случае неудачи
     */
    public function getRegion(string $ip): ?array;

    /**
     * Возвращает информацию о городе по IP адресу
     *
     * @param string $ip IP адрес
     *
     * @return array|null Массив с данными или null в случае неудачи
     */
    public function getCity(string $ip): ?array;

    /**
     * Возвращает информацию о стране, регионе и городе по IP адресу
     *
     * @param string $ip IP адрес
     *
     * @return array|null Массив с данными или null в случае неудачи
     */
    public function getFullInfo(string $ip): ?array;

    /**
     * Возвращает координаты города/страны
     *
     * @param string $ip IP адрес
     * @return array|null Массив с данными или null в случае неудачи
     */
    public function getCoordinates(string $ip): ?array;
}