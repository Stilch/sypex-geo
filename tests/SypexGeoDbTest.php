<?php

declare(strict_types=1);

use SypexGeo\SypexGeoDb;
use PHPUnit\Framework\TestCase;

class SypexGeoDbTest extends TestCase
{
    protected static $sypexGeoDb;
    protected static $ip = '77.37.136.11';

    public static function setUpBeforeClass()
    {
        self::$sypexGeoDb = new SypexGeoDb(__DIR__ . '/SypexGeoDb.dat');
    }

    public function testGetCountryId()
    {
        $this->assertEquals(185, self::$sypexGeoDb->getCountryId(self::$ip));
        $this->assertEquals(null, self::$sypexGeoDb->getCountryId('0.0.0.1'));
        $this->assertEquals(null, self::$sypexGeoDb->getCountryId('10.0.0.1'));
        $this->assertEquals(null, self::$sypexGeoDb->getCountryId('127.0.0.1'));
    }

    public function testGetCountryIso()
    {
        $this->assertEquals('RU', self::$sypexGeoDb->getCountryIso(self::$ip));
    }

    public function testGetCountry()
    {
        $this->assertIsArray(self::$sypexGeoDb->getCountry(self::$ip));
        $this->assertArrayHasKey('iso', self::$sypexGeoDb->getCountry(self::$ip));
        $this->assertArrayHasKey('id', self::$sypexGeoDb->getCountry(self::$ip));
    }

    public function testGetRegion()
    {
        $this->assertIsArray(self::$sypexGeoDb->getRegion(self::$ip));
    }

    public function testGetCity()
    {
        $this->assertIsArray(self::$sypexGeoDb->getCity(self::$ip));
    }

    public function testGetFullInfo()
    {
        $this->assertIsArray(self::$sypexGeoDb->getFullInfo(self::$ip));
    }

    public function testGetCoordinates()
    {
        $this->assertIsArray(self::$sypexGeoDb->getCoordinates(self::$ip));
        $this->assertArrayHasKey('lat', self::$sypexGeoDb->getCoordinates(self::$ip));
        $this->assertArrayHasKey('lon', self::$sypexGeoDb->getCoordinates(self::$ip));
    }
}
