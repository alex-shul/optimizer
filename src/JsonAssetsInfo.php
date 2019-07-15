<?php

namespace alexshul\optimizer;

use Yii;

class JsonAssetsInfo
{
    private $jsonFileName = '';
    private $arrayAssets = [];
    private $newDataArray = [];

    function __construct() {
        $this->jsonFileName = Yii::getAlias('@runtime') . '/alex-shul/yii2-optimizer/assets-info.json';
    }

    /**
     *  Создает массив с данными из assets-info.json, если файла нет - создает пустой файл
     *  Данные пишет в $this->arrayAssets
     */
    public function getAssetsInfo ()
    {
        if ( file_exists( $this->jsonFileName ) ) {
            $assetsInfo = file_get_contents( $this->jsonFileName );
            $this->arrayAssets = json_decode( $assetsInfo, true );
        }     
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
        if ($this->arrayAssets[$nameAsset]['path'] != $file) {
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
            'path' => $file,
            'latest' => $latest
        ];
    }

    /**
     * Обновляет файл assets-info.json данными из сформированного массива
     */
    public function jsonAssetsUpdate ()
    {
        if( empty($this->newDataArray) )
            return;
            
        $json = json_encode($this->newDataArray);
        file_put_contents($this->jsonFileName, $json);
    }
}