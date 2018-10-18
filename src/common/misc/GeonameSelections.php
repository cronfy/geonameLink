<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 18.10.18
 * Time: 19:24
 */

namespace cronfy\geonameLink\common\misc;


use cronfy\geoname\common\models\GeonameDTO;
use cronfy\geoname\common\models\sqlite\Geoname;
use yii\db\ActiveQuery;

class GeonameSelections
{
    /**
     * @var \cronfy\geoname\common\misc\GeonameService
     */
    public $geonamesService;

    /**
     * @return \cronfy\geoname\common\misc\GeonameService
     */
    protected function getGeonamesService() {
        return $this->geonamesService;
    }

    public function countryPopulatedLocations($countryIso) {
        $service = $this->getGeonamesService();

        $countryRepository = $service->getCountryGeonamesSqliteRepository();

        $query = $countryRepository->getFindQuery()
            ->country($countryIso)
            ->populatedLocations()
            ->population(15000)
        ;

        return $this->iterateGeonamesQuery($query);
    }

    public function spbWithin() {
        $service = $this->getGeonamesService();

        $countryRepository = $service->getCountryGeonamesSqliteRepository();

        $query = $countryRepository->getFindQuery()
            ->country('RU')
            ->populatedLocations()
            ->population(7000)
            // Санкт-Петербург (населенные пункты внутри Спб (по geonames), например, Зеленогорск)
            ->andWhere(['admin1_code' => 66])
        ;

        return $this->iterateGeonamesQuery($query);
    }

    public function lenOblast() {
        $service = $this->getGeonamesService();

        $countryRepository = $service->getCountryGeonamesSqliteRepository();

        $query = $countryRepository->getFindQuery()
            ->country('RU')
            ->populatedLocations()
            ->population(7000)
            // Ленинградская область
            ->andWhere(['admin1_code' => 42])
        ;

        return $this->iterateGeonamesQuery($query);
    }

    public function lenOblastManual() {
        $service = $this->getGeonamesService();

        $countryRepository = $service->getCountryGeonamesSqliteRepository();

        $geonameIds = [
            546213, // Янино-1
            469087, // Янино-2
            824070, // Бугры
            539839, // Кудрово
        ];

        $query = $countryRepository->getFindQuery()
            ->andWhere(['geonameid' => $geonameIds])
        ;

        return $this->iterateGeonamesQuery($query);
    }

    public function moscowWithin() {
        $service = $this->getGeonamesService();

        $countryRepository = $service->getCountryGeonamesSqliteRepository();

        $query = $countryRepository->getFindQuery()
            ->country('RU')
            ->populatedLocations()
            ->population(7000)
            // Москва (населенные пункты внутри Москвы (по geonames), например, Зеленоград)
            ->andWhere(['admin1_code' => 48])
        ;

        return $this->iterateGeonamesQuery($query);
    }

    public function moscowOblast() {
        $service = $this->getGeonamesService();

        $countryRepository = $service->getCountryGeonamesSqliteRepository();

        $query = $countryRepository->getFindQuery()
            ->country('RU')
            ->populatedLocations()
            ->population(7000)
            // Московская область
            ->andWhere(['admin1_code' => 47])
        ;

        return $this->iterateGeonamesQuery($query);
    }

    public function crimea() {
        $sevastopolGeonameId = 694423;
        $crimeaGeonameId = 703883;

        $geonameService = $this->getGeonamesService();

        foreach ($this->countryPopulatedLocations('UA') as $geoname) {
            /** @var GeonameDTO $geoname */

            if ($geoname->geonameid == $sevastopolGeonameId) {
                yield $geoname;
            }

            $regionGeoname = $geonameService->getRegionByGeoname($geoname);

            if ($regionGeoname->geonameid == $crimeaGeonameId) {
                yield $geoname;
            }
        }
    }


    /**
     * @param ActiveQuery $query
     * @param callable|null $filter
     * @return GeonameDTO[]
     */
    public function iterateGeonamesQuery(ActiveQuery $query, callable $filter = null) {
        foreach ($query->batch(100) as $batch) {
            foreach ($batch as $geoname) {
                /** @var Geoname $geoname */
                if ($filter && !$filter($geoname)) {
                    continue;
                }

                yield $geoname->getDto();
            }
        }
    }

}