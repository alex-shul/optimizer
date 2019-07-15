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
     *  Создает массив с данными из assets-info.json, если файл существует
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
    public function checkDataAssets($nameAsset, $file, $latest)
    {
        if ( !array_key_exists($nameAsset, $this->arrayAssets)) {
            return false;
        } 
        if ( !array_key_exists('files', $this->arrayAssets[$nameAsset])) {
            return false;
        } 
        if ( !array_key_exists($file, $this->arrayAssets[$nameAsset]['files']) ) {
            return false;
        }      
        if ( $this->arrayAssets[$nameAsset]['path'] != $file ) {
            return false;
        }
        if ($this->arrayAssets[$nameAsset]['latest'] != $latest) {
            return false;
        }
        return true;
    }

    /** Меняет версию для ассета
     * @param $nameAsset
     */
    public function changeAssetsVersion( $nameAsset ) {        
        if ( !array_key_exists( $nameAsset, $this->newDataArray ) ) {
            return;
        } 

        if ( !array_key_exists( 'version', $this->newDataArray[$nameAsset] ) ) {
            $this->newDataArray[$nameAsset]['version'] = 1;
            return;
        }

		$this->newDataArray[$nameAsset]['version']++;

		if( $this->newDataArray[$nameAsset]['version'] > 999998 ) {
			$this->newDataArray[$nameAsset]['version'] = 1;
		}		
	}

    /** Добавляет данные к массиву с новыми значениями
     * @param $nameAsset
     * @param $file
     * @param $latest
     */
    public function addNewData ( $nameAsset, $file, $latest = 0, $version = 1 )
    {
        if( !isset( $this->newDataArray[$nameAsset] ) )
            $this->newDataArray[$nameAsset] = array();

        if( !isset( $this->newDataArray[$nameAsset]['files'] ) )
            $this->newDataArray[$nameAsset]['files'] = array();

        $this->newDataArray[$nameAsset]['files'][$file] = [            
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
            
        $json = json_encode( $this->newDataArray );
        file_put_contents( $this->jsonFileName, $json );
    }
}