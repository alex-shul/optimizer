<?php

namespace alexshul\optimizer;

use Yii;

class JsonAssetsInfo
{
    private $jsonFileName = '';
    private $arrayAssets = [];
    private $newDataArray = [];

    /**
     *  Создает массив с данными из assets-info.json, если файла нет - создает пустой файл
     *  Данные пишет в $this->arrayAssets
     */
    public function getAssetsInfo ()
    {
        $this->jsonFileName = Yii::getAlias('@runtime') . '/alex-shul/yii2-optimizer/assets-info.json';
        if (!file_exists($this->jsonFileName)) {
            $file = fopen($this->jsonFileName, "w");
            fclose($file);
        }
        $strJsonAssets = file_get_contents($this->jsonFileName);
        $this->arrayAssets = json_decode($strJsonAssets, true);
    }

    /** Сверяет данные полученные из json и полученные из конфига
     * @param $nameAsset
     * @param $file
     * @param $latest
     * @return bool
     */
    public function checkDataAssets ($nameAsset, $file, $latest)
    {
        if (empty($this->arrayAssets)) {
            return false;
        }
        if (!array_key_exists ($nameAsset, $this->arrayAssets)) {
            return false;
        }
        $pathFile = $file['pathDirectory'] . $file['version'] . '/' . $file['fileName'];
        if ($this->arrayAssets[$nameAsset]['path'] != $pathFile) {
            return false;
        }
        if ($this->arrayAssets[$nameAsset]['latest'] != $latest) {
            return false;
        }
        return true;
    }

    /** Добавляет данные к массиву с новыми значениями
     * @param $nameAsset
     * @param $file
     * @param $latest
     */
    public function addNewData ($nameAsset, $file, $latest)
    {
        $this->newDataArray[$nameAsset] = [
            'path' => $file['pathDirectory'] . $file['version'] . '/' . $file['fileName'],
            'latest' => $latest
        ];
    }

    /**
     * Обновляет файл assets-info.json данными из сформированного массива
     */
    public function jsonAssetsUpdate ()
    {
        $json = json_encode($this->newDataArray);
        file_put_contents($this->jsonFileName, $json);
    }
}