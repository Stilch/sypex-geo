<?php

declare(strict_types=1);

namespace SypexGeo;

final class SypexGeoNetwork implements SypexGeoInterface
{
    private const URL = 'http://api.sypexgeo.net/';

    /**
     * @var string
     */
    private $apiUrl;

    public function __construct(string $apiKey = '')
    {
        $this->apiUrl = self::URL . ($apiKey !== '' ? $apiKey . '/' : '') . 'json/';
    }

    private function httpRequest(string $ip): array
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->apiUrl . $ip);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl);

        curl_close($curl);

        $response = $response !== false ? json_decode($response, true) : [];

        return is_array($response) ? $response : [];
    }

    /**
     * @inheritDoc
     */
    public function getCountryId(string $ip): int
    {
        $result = $this->httpRequest($ip);

        return isset($result['country']['id']) ? $result['country']['id'] : 0;
    }

    /**
     * @inheritDoc
     */
    public function getCountryIso(string $ip): string
    {
        $result = $this->httpRequest($ip);

        return isset($result['country']['iso']) ? $result['country']['iso'] : '';
    }

    /**
     * @inheritDoc
     */
    public function getCountry(string $ip): ?array
    {
        $result = $this->httpRequest($ip);

        return isset($result['country']) ? $result['country'] : null;
    }

    /**
     * @inheritDoc
     */
    public function getRegion(string $ip): ?array
    {
        $result = $this->httpRequest($ip);

        return isset($result['region']) ? $result['region'] : null;
    }

    /**
     * @inheritDoc
     */
    public function getCity(string $ip): ?array
    {
        $result = $this->httpRequest($ip);

        return isset($result['city']) ? $result['city'] : null;
    }

    /**
     * @inheritDoc
     */
    public function getFullInfo(string $ip): ?array
    {
        $result = $this->httpRequest($ip);

        $fullInfo = [];
        if (isset($result['city'])) {
            $fullInfo['city'] = $result['city'];
        }
        if (isset($result['region'])) {
            $fullInfo['region'] = $result['region'];
        }
        if (isset($result['country'])) {
            $fullInfo['country'] = $result['country'];
        }

        return count($fullInfo) > 0 ? $fullInfo : null;
    }

    /**
     * @inheritDoc
     */
    public function getCoordinates(string $ip): ?array
    {
        $result = $this->httpRequest($ip);

        if (isset($result['city']['lat'], $result['city']['lon'])) {
            return ['lat' => $result['city']['lat'], 'lon' => $result['city']['lon']];
        } elseif (isset($result['region']['lat'], $result['region']['lon'])) {
            return ['lat' => $result['region']['lat'], 'lon' => $result['region']['lon']];
        } elseif (isset($result['country']['lat'], $result['country']['lon'])) {
            return ['lat' => $result['country']['lat'], 'lon' => $result['country']['lon']];
        }

        return null;
    }
}