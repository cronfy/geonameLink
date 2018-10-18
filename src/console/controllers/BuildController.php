<?php

namespace cronfy\geonameLink\console\controllers;

use cronfy\cdek\BaseModule;
use cronfy\cdek\common\models\CdekCity;
use cronfy\cdek\common\models\CdekCityDTO;
use cronfy\env\Env;
use cronfy\geoname\common\models\GeonameDTO;
use cronfy\geonameLink\common\misc\GeonameSelections;
use cronfy\geonameLink\common\misc\Service;
use Yii;

/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 09.10.18
 * Time: 15:18
 */

class BuildController extends \yii\console\Controller
{
    /**
     * @return BaseModule
     */
    protected function getCdekModule() {
        return Yii::$app->getModule('cdek');
    }

    protected function correctorSelection() {
        $correct = [
            469707 => [
                // Ягры - нет такого населенного пункта, это остров, часть Северодвинска
                'exclude' => true,
            ],
            505421 => [
                // Придонской - нет такого населенного пункта, это район Воронежа
                'exclude' => true,
            ],
            509981 => [
                // Петровская в Москве - вообще непонятно что
                'exclude' => true,
            ],
            510225 => [
                // Петродворец под Спб - есть Петергоф, этот не нужен
                'exclude' => true,
            ],
            563379 => [
                // Эжва - все сложно, но сейчас это район Сыктывкара
                'exclude' => true,
            ],
            569273 => [
                // Черёмушки - район Москвы
                'exclude' => true,
            ],
            576432 => [
                // Бибирево - район Москвы
                'exclude' => true,
            ],
            874045 => [
                // Старый Малгобек - На сегодняшний день территория села находится в границах муниципального образования «Город Малгобек»
                'exclude' => true,
            ],
            6315399 => [
                // Исакогорка пассажирско-грузовая железнодорожная станция
                'exclude' => true,
            ],
            8505053 => [
                // Восточное Дегунино район Москвы
                'exclude' => true,
            ],
            534174 => [
                // Лопатинский - район Воскресенска, Московская область
                'exclude' => true,
            ],
        ];

        return function ($geonameDto) use ($correct) {
            if (array_key_exists($geonameDto->geonameid, $correct)) {
                return $correct[$geonameDto->geonameid];
            }

            return null;
        };

    }

    /**
     * @return GeonameDTO[]
     */
    protected function iterateSelection() {
        $corrector = $this->correctorSelection();
        $selections = $this->getGeonamesSelections();

        $iterateIterators = function () use ($selections) {
            yield $selections->countryPopulatedLocations('RU');
            yield $selections->spbWithin();
            yield $selections->lenOblast();
            yield $selections->lenOblastManual();
            yield $selections->moscowWithin();
            yield $selections->moscowOblast();
            yield $selections->crimea();
            yield $selections->countryPopulatedLocations('AM'); // Армения
            yield $selections->countryPopulatedLocations('BY'); // Белоруссия
            yield $selections->countryPopulatedLocations('KZ'); // Казахстан
            yield $selections->countryPopulatedLocations('KG'); // Киргизия
        };

        foreach ($iterateIterators() as $iterator) {
            foreach ($iterator as $geonameDto) {
                $correct = $corrector($geonameDto);

                if ($correct) {
                    if (@$correct['exclude']) {
                        continue;
                    }
                }
                yield $geonameDto;
            }
        }
    }

    protected function correctorRuNames() {
        $correct = [
            467525 => 'Емва',
            515804 => 'Цоци-Юрт',
            537345 => 'Дыгулыбгей',
            608679 => 'Кандыагаш', // geonames не дает поправить
            610611 => 'Актобе', // geonames не дает поправить
        ];

        // два аргумента - потому что корректор может скорректировать на null.
        // Соответственно, он всегда должен возвращать конечный результат,
        // а для этого ему надо знать исходный, чтобы вернуть его, если не нашлось замены.
        return function ($geonameDto, $ruName) use ($correct) {
            if (array_key_exists($geonameDto->geonameid, $correct)) {
                return $correct[$geonameDto->geonameid];
            }

            return $ruName;
        };
    }

    public function actionRuNames($update = false) {
        $buildName = 'ru-names';

        $service = $this->getService();
        $data = $service->getDataFromFile($buildName);

        $geonameService = $this->getGeonamesService();

        $corrector = $this->correctorRuNames();

        foreach ($this->iterateSelection() as $geonameDto) {
            if (!$update && array_key_exists($geonameDto->geonameid, $data)) {
                continue;
            }

            $officialName = $geonameService->getOfficialNameByGeoname($geonameDto, 'ru');
            $ruName = $corrector($geonameDto, $officialName ? $officialName->alternate_name : null);

            $data[$geonameDto->geonameid] = $ruName;
        }

        $service->saveDateToFile($buildName, $data);
    }

    public function actionCrimeaDataFile() {
        $path = __DIR__ . '/../../common/data/crimea-geoname-ids.php';
        $data = require ($path);

        foreach ($this->getGeonamesSelections()->crimea() as $geoname) {
            $data[] = $geoname->geonameid;
        }

        file_put_contents($path, "<?php\n\nreturn " . var_export($data, 1) . ";\n");
    }

    protected function overrideCdek() {
        $override = [
            536625 => [
                'cdekCityCode' => 2046, // Лазаревское, не нашлось по индексу, у CDEK нет ФИАС
            ],
            581928 => [
                'cdekCityCode' => 17269, // Андреевское Московская обл., ручной выбор
            ],
            584243 => [
                'cdekCityCode' => 326, // Адлер, нет ФИАС и почтовых индексов, слинковано вручную
            ],
            823674 => [
                'cdekCityCode' => 2047, // Дагомыс, нет ФИАС и почтовых индексов, слинковано вручную
            ],
            829005 => [
                'cdekCityCode' => 1403, // Лесной Свердловская обл., вручную - по индексу далеко, по ФИАС тоже
            ],
            1490256 => [
                'cdekCityCode' => 26, // Талнах Красноярский край, вручную - нет ФИАС и индекса
            ],
            1504139 => [
                'cdekCityCode' => 1020, // Кайеркан Красноярский край, вручную - нет ФИАС и индекса
            ],
            2015051 => [
                'exclude' => true, // Трудовое Приморский край, у CDEK нету
            ],
            11886891 => [
                // Федоровский
                // Их два:
                // CDEK NAME MATCH: 52616 Федоровский Ханты-Мансийский авт. округ
                // CDEK NAME MATCH: 14630 Фёдоровский, Сургутский р-н Ханты-Мансийский авт. округ
                // Предпололожительно это 14630, так как 52616 не считается калькулятором CDEK
                'cdekCityCode' => 14630,
            ],
            515558 => [
                'exclude' => true, // Ольгино, Ленинградская область. У CDEK нет.
            ],
            553615 => [
                // Каменка, Выборгский р-он Ленинградская обл.
                'cdekCityCode' => 2687,
            ],
            556575 => [
                // Посёлок имени Морозова, во Всеволожском районе Ленинградской области
                'cdekCityCode' => 14674
            ],
            473535 => [
                // Виноградово Московская обл.
                'cdekCityCode' => 14680
            ],
            516305 => [
                // Обухово, Ногинский р-н Московская обл.
                'cdekCityCode' => 14339,
            ],
            518337 => [
                // Новоподрезково Московская обл.
                'cdekCityCode' => 1704,
            ],
            544904 => [
                // Коренево Московская обл.
                'cdekCityCode' => 14490,
            ],
            174972 => [
                'exclude' => true, // Ацаван, Армения. У CDEK нет.
            ],
            174979 => [
                'exclude' => true, // Арташат, Армения. У CDEK нет.
            ],
            174991 => [
                'exclude' => true, // Арарат, Армения. У CDEK нет.
            ],
            616062 => [
                'exclude' => true, // Вагаршапат, Армения. У CDEK нет.
            ],
            616194 => [
                'exclude' => true, // Степанаван, Армения. У CDEK нет.
            ],
            624034 => [
                // Осиповичи, Могилевская обл.
                'cdekCityCode' => 9969,
            ],
            627908 => [
                // Глубокое, Витебская обл.
                'cdekCityCode' => 6508,
            ],
            629454 => [
                // Береза, Брестская обл.
                'cdekCityCode' => 5353,
            ],
            608359 => [
                // Есть два, ни по одному калькулятор на сайте не считает
                // CDEK NAME MATCH: 21754 Шалкар, Айыртауский р-н Северо-Казахстанскаяобл.
                // CDEK NAME MATCH: 14225 Шалкар, Северо-Казахстанская обл. Северо-Казахстанскаяобл.
                // Но Айтыраутский - это другой район, этот по geonames Aktyubinskaya Oblast’
                // Значит это 14225
                'cdekCityCode' => 14225,
            ],
            608362 => [
                // Есть два, ни по одному калькулятор на сайте не считает
                // CDEK NAME MATCH: 21754 Шалкар, Айыртауский р-н Северо-Казахстанскаяобл.
                // CDEK NAME MATCH: 14225 Шалкар, Северо-Казахстанская обл. Северо-Казахстанскаяобл.
                // Но у этого по geonames регион Atyraū Oblysy, значит это 21754
                'cdekCityCode' => 21754,
            ],
            610611 => [
                'cdekCityCode' => 4693,
            ],
            610612 => [
                'cdekCityCode' => 13435,
            ],
            1516589 => [
                'cdekCityCode' => 7144,
            ],
            1517637 => [
                'cdekCityCode' => 11916,
            ],
            1519938 => [
                'cdekCityCode' => 7677,
            ],
            1520947 => [
                'cdekCityCode' => 9244,
            ],
            1524245 => [
                'cdekCityCode' => 6447,
            ],
            1526970 => [
                'cdekCityCode' => 4582,
            ],
            469087 => [
                'cdekCityCode' => 14727, // Янино-2, не находится из-за дефиса
            ],


        ];

        return function ($geonameDto) use ($override) {
            if (array_key_exists($geonameDto->geonameid, $override)) {
                $currentOverride = $override[$geonameDto->geonameid];

                if (@$currentOverride['exclude']) {
                    return false;
                }

                $cdekCity = CdekCity::find()
                    ->andWhere(['city_code' => $currentOverride['cdekCityCode']])
                    ->one()
                ;
                return $cdekCity->getDto();
            }

            return null;
        };
    }

    public function actionCdek() {
        $buildName = 'cdek';
        $service = $this->getService();
        $data = $service->getDataFromFile($buildName);

        $ruNames = $service->getDataFromFile('ru-names');

        $overrider = $this->overrideCdek();

        $errorStop = false;

        foreach ($this->iterateSelection() as $geonameDTO) {
            $skipReason = null;

            $ruName = @$ruNames[$geonameDTO->geonameid];

            if (array_key_exists($geonameDTO->geonameid, $data)) {
                continue;
            }

            $cdekCityDto = $overrider($geonameDTO);


            do {
                // Если $cdekCityDto
                // false - сохраняем как skip
                // null - выдаем ошибку

                if ($cdekCityDto === false) {
                    $skipReason = 'manually excluded';
                    break; // сохраним false
                }

                if (!$ruName) {
                    $cdekCityDto = false;
                    $skipReason = 'no ru name';
                    continue;
                }


                if (!$cdekCityDto) {
                    $cdekCity = $this->findCdekCityByGeonameDto($geonameDTO, $ruName, $error);

                    if (!$cdekCity) {
                        if ($geonameDTO->country_code == 'AM') {
                            if (@$error['noMatchesByName']) {
                                echo "SKIP AM: {$ruName}\n";

                                // у CDEK немного городов Армении. Если мы не нашли даже
                                // по имени, просто считаем, что у CDEK такого города нет.

                                $cdekCityDto = false;
                                $skipReason = 'no name match';
                                break;
                            }
                        }

                        if ($geonameDTO->country_code == 'BY') {
                            if (@$error['noMatchesByName']) {
                                echo "SKIP BY: {$ruName}\n";
                                // Ввиду отсутствия экспертизы, не найденный по имени город Белоруссии
                                // просто пропускаем, считаем, что у CDEK нет.
                                $cdekCityDto = false;
                                $skipReason = 'no name match';
                                break;
                            }
                        }

                        if ($geonameDTO->country_code == 'KZ') {
                            if (@$error['noMatchesByName']) {
                                echo "SKIP KZ: {$ruName}\n";
                                // Ввиду отсутствия экспертизы, не найденный по имени город Казахстана
                                // просто пропускаем, считаем, что у CDEK нет.
                                $cdekCityDto = false;
                                $skipReason = 'no name match';
                                break;
                            }
                        }

                        if ($geonameDTO->country_code == 'KG') {
                            if (@$error['noMatchesByName']) {
                                echo "SKIP KG: {$ruName}\n";
                                // Ввиду отсутствия экспертизы, не найденный по имени город Киргизии
                                // просто пропускаем, считаем, что у CDEK нет.
                                $cdekCityDto = false;
                                $skipReason = 'no name match';
                                break;
                            }
                        }

                        $cdekCityDto = null;
                        break;
                    }

                    $cdekCityDto = $cdekCity->getDTO() ?: null;
                    break; // сохраняем информацию
                }

            } while (false);

            if ($cdekCityDto === false) {
                $data[$geonameDTO->geonameid] = [
                    'skipped' => true,
                    'skipReason' => $skipReason ? : '?',
                ];
                continue;
            }

            if (!$cdekCityDto) {
                $errorStop = true;
                break;
            }

            $data[$geonameDTO->geonameid] = [
                'CountryCode' => $cdekCityDto->CountryCode,
                'ID' => $cdekCityDto->ID
            ];
        }

        // даже при ошибке сохраняем файл - там могли появиться новые корректные данные
        $service->saveDateToFile($buildName, $data);

        if ($errorStop) {
            D('ERROR');
        }
    }

    protected $_service;

    /**
     * @return Service
     */
    protected function getService() {
        if (!$this->_service) {
            $this->_service = new Service();
        }

        return $this->_service;
    }

    /**
     * https://stackoverflow.com/a/10054282/1775065
     *
     * Calculates the great-circle distance between two points, with
     * the Vincenty formula.
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     * @return float Distance between points in [m] (same as earthRadius)
     */
    protected function vincentyGreatCircleDistance(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000.0)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }

    protected function log($message, $data) {
//        return;
        $geonamesService = $this->getGeonamesService();
        echo "$message\n";
        if ($ruName = @$data['ruName']) {
            echo "$ruName\n";
            unset($data['ruName']);
        }
        if ($geonameDto = @$data['geonameDto']) {
            /** @var GeonameDTO $geonameDto */
            unset($data['geonameDto']);
            echo "GEONAME: " . $geonameDto->geonameid . " " . $geonameDto->country_code . "\n";
            echo "COORD: " . $geonameDto->latitude . " " . $geonameDto->longitude . "\n";
            $region = $geonamesService->getRegionByGeoname($geonameDto);
            if ($region) {
                echo "REGION: " . $region->name . "\n";
            }
//            print_r($geonamesService->traceRegionByGeoname($geonameDto));
        }
        if ($postalCodeDistance = @$data['postalCodeDistance']) {
            unset($data['postalCodeDistance']);
            print_r($postalCodeDistance);
        }
        if ($postalCodeMatches = @$data['postalCodeMatches']) {
            unset($data['postalCodeMatches']);
            foreach ($postalCodeMatches as $cdekNameMatch) {
                /** @var CdekCityDTO $cdekDto */
                $cdekDto = $cdekNameMatch->getDto();
                echo "POSTAL CODE MATCH: ";
                echo "{$cdekDto->ID} {$cdekDto->CityName} {$cdekDto->OblName}\n";
            }
        }
        if ($cdekNameMatches = @$data['cdekNameMatches']) {
            unset($data['cdekNameMatches']);
            foreach ($cdekNameMatches as $cdekNameMatch) {
                /** @var CdekCityDTO $cdekDto */
                $cdekDto = $cdekNameMatch->getDto();
                echo "CDEK NAME MATCH: ";
                echo "{$cdekDto->ID} {$cdekDto->CityName} {$cdekDto->OblName}\n";
            }
        }
        if ($cdekFiasMatches = @$data['cdekFiasMatches']) {
            unset($data['cdekFiasMatches']);
            foreach ($cdekFiasMatches as $cdekFiasMatch) {
                /** @var CdekCityDTO $cdekDto */
                $cdekDto = $cdekFiasMatch->getDto();
                echo "CDEK FIAS MATCH: ";
                echo "{$cdekDto->ID} {$cdekDto->CityName} {$cdekDto->OblName}\n";
            }
        }
        if ($data) {
            print_r(array_keys($data));
        }
        echo "\n";
    }

    /**
     * @param $geonameDto GeonameDTO
     * @return CdekCity
     */
    protected function findCdekCityByGeonameDto($geonameDto, $ruName, &$error)
    {
        $error = [];

        $logData = [
            'geonameDto' => $geonameDto,
            'ruName' => $ruName,
        ];

        $service = $this->getService();

        $cdekModule = $this->getCdekModule();
        $cityRepository = $cdekModule->getCityRepository();



        $isCrimea = in_array($geonameDto->geonameid, $service->getCrimeaGeonameIds());

        $cdekMatches = $cityRepository->searchByCriteria([
            'name' => $ruName,
            // CDEK ведет Крым как Россию
            'countryIso' => $isCrimea ? 'RU' : $geonameDto->country_code
        ]);

        if (!$cdekMatches) {
            $this->log("No matches by name", $logData);
            $error['noMatchesByName'] = true;
            return null;
        }


        $logData['cdekNameMatches'] = $cdekMatches;

        /** @var \cronfy\geoname\BaseModule $geonameModule */
        $geonameModule = Yii::$app->getModule('geoname');
        $geonameService = $geonameModule->getGeonamesService();
        $postalCodesRepository = $geonameService->getPostalCodesRepository();

        $postalCodeMatches = [];
        $logData['postalCodeDistance'] = [];
        foreach ($cdekMatches as $cdekCity) {
            $cdekDto = $cdekCity->getDTO();
            foreach ($cdekDto->getPostCodes() as $cdekPostCode) {
                $postalCode = $postalCodesRepository
                    ->getByPostalCode($cdekPostCode, $geonameDto->country_code);

                if (!$postalCode || !$postalCode->latitude || !$postalCode->longitude) {
                    continue;
                }

                $distanceM = $this->vincentyGreatCircleDistance(
                    $postalCode->latitude,
                    $postalCode->longitude,
                    $geonameDto->latitude,
                    $geonameDto->longitude
                );

                $logData['postalCodeDistance'][] = [
                    'ID' => $cdekCity->getDTO()->ID,
                    'postal' => $postalCode->postal_code,
                    'distance' => $distanceM,
                ];

                if ($distanceM < 100000) {
                    // расстояние менее 50 км, значит это наш город
                    $postalCodeMatches[] = $cdekCity;
                    break;
                }

            }
        }

        $logData['postalCodeMatches'] = $postalCodeMatches;

        // нашли
        if (count($postalCodeMatches) === 1) {
            return $postalCodeMatches[0];
        }

        // не нашли, ищем по ФИАС

        do {
            if ($geonameDto->country_code === 'RU') {
                break;
            }

            if ($isCrimea) {
                break;
            }

            // Это не Россия, здесь можно только сравнивать по имени с некоторым допущением.
            // По индексу поиск срабатывает очень редко.
            // Поэтому если нашелся только один вариант по имени, считаем его верным.
            if (count($cdekMatches) === 1) {
                return array_shift($cdekMatches);
            }
            // Если не один, то дальше способов найти не российский город нет.
            $this->log('Not RU city, too many matches ' . $ruName, $logData);
            return null;
        } while (false);

        // для России еще можно поискать по ФИАС

        $candidates = $postalCodeMatches ?: $cdekMatches;

        $fiasMatches = [];
        foreach ($candidates as $k => $cdekCity) {
            $fias = @$cdekCity->data['FIAS'];

            if (!$fias) {
//                $this->log('No FIAS for ' . $ruName, $logData);

                // У CDEK не для всех российских населенных пунктов есть FIAS.
                // Это не exception, возможно дальше мы найдем нужный город,
                // который сопадет как надо.
                continue;
            }

            if ($this->isMatchesByFiasLatLng($fias, $geonameDto->latitude, $geonameDto->longitude)) {
                // на всякий случай проверяем все города, а не останавливаемся на
                // первом совпавшем - вдруг совпадений будет несколько, тогда нужно
                // будет вернуть null, а не город.
                $fiasMatches[] = $cdekCity;
            }
        }

        $logData['cdekFiasMatches'] = $fiasMatches;

        if (count($fiasMatches) === 1) {
            return $fiasMatches[0];
        }

        if (!$fiasMatches) {
            $this->log('No matches by FIAS ' . $ruName, $logData);
        } else {
            $this->log('Too many matches by FIAS  ' . $ruName, $logData);
        }

        return null;

    }

    protected $_dadataClient;
    protected function getDadataClient() {
        if (!$this->_dadataClient) {
            $this->_dadataClient = new \Dadata\Client(new \GuzzleHttp\Client(), [
                'token' => Env::get('DADATA_TOKEN'),
                'secret' => Env::get('DADATA_SECRET'),
            ]);
        }

        return $this->_dadataClient;
    }

    protected function isMatchesByFiasLatLng($fias, $lat, $lng) {
        $cacheKey = 'geonameLink.dadata.fias.' . $fias;
//        Yii::$app->cache->delete($cacheKey);
        $data = Yii::$app->cache->getOrSet(
            $cacheKey,
            function () use ($fias) {
                $result = $this->getDadataClient()->getAddressById($fias);
                // если ничего не нашлось, result будет === null.
                // мы кешируем и такой ответ, потому что если сейчас по этому fias
                // ничего нет, то и завтра не будет.
                // Если же будет ошибка запроса, то будет Exception и кеш не сохранится.
                return $result;
            },
            60 * 60 * 24 * 30
        );

        if (!$data) {
            return false;
        }

        $currentLat = $data->geo_lat;
        $currentLng = $data->geo_lon;

        $distanceM = $this->vincentyGreatCircleDistance($lat, $lng, $currentLat, $currentLng);

        if ($distanceM < 100000) {
            // расстояние менее 50 км, значит это наш город
            return true;
        }

        return false;
    }

    /**
     * @return \cronfy\geoname\common\misc\GeonameService
     */
    protected function getGeonamesService() {
        return Yii::$app->getModule('geoname')->getGeonamesService();
    }

    protected $_geonamesSelections;
    /**
     * @return GeonameSelections
     */
    protected function getGeonamesSelections() {
        if (!$this->_geonamesSelections) {
            $this->_geonamesSelections = new GeonameSelections();
            $this->_geonamesSelections->geonamesService = $this->getGeonamesService();
        }

        return $this->_geonamesSelections;
    }



}