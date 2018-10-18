<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 09.10.18
 * Time: 16:30
 */

namespace cronfy\geonameLink\common\misc;

class Service
{
    public function getCrimeaGeonameIds() {
        $data = require (__DIR__ . '/../data/crimea-geoname-ids.php');
        return $data;
    }

    public function getGeonameToCdek() {
        $path = __DIR__ . '/../../common/data/geoname-to-cdek.php';
        $data = require ($path);
        return $data;
    }

    public function getDataFilePath($name) {
        return  __DIR__ . "/../../common/data/$name.php";
    }

    public function getDataFromFile($name)
    {
        $path = $this->getDataFilePath($name);
        $data = @include $path;
        return $data ?: [];
    }

    public function saveDateToFile($name, $data) {
        $path = $this->getDataFilePath($name);
        file_put_contents($path, "<?php\n\nreturn " . var_export($data, 1) . ";\n");
    }

}