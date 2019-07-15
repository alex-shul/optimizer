<?php

namespace alexshul\optimizer;

use Yii;

class JsonAssetsInfo
{
    private $jsonFileName = '';
    private $arrayAssets = [];
    private $newDataArray = [];
    private $unsavedChanges = false;

    function __construct() {
        $this->jsonFileName = Yii::getAlias('@runtime') . '/alex-shul/yii2-optimizer/assets-info.json';
    }

    /**
     *  Создает массив с данными из assets-info.json, если файл существует
     *  Данные пишет в $this->arrayAssets
     */
    public function loadAssetsInfo ()
    {
        if ( file_exists( $this->jsonFileName ) ) {
            $assetsInfo = file_get_contents( $this->jsonFileName );
            $this->arrayAssets = json_decode( $assetsInfo, true );
        }     
    }

    /**
     * Обновляет файл assets-info.json данными из сформированного массива
     */
    public function updateAssetsInfo ()
    {
        if( empty($this->newDataArray) )
            return;
        
        if( !$this->unsavedChanges )
            return;
            
        $json = json_encode( $this->newDataArray );
        file_put_contents( $this->jsonFileName, $json );
    }

    /** 
     * Сверяет данные полученные из json и полученные из конфига
     * @param $nameAsset
     * @param $file
     * @param $latest
     * @return bool
     */
    public function checkDataAssets($nameAsset, $file, $latest)
    {
        if ( !array_key_exists( $nameAsset, $this->arrayAssets ) ||
             !array_key_exists( 'src', $this->arrayAssets[$nameAsset] ) ||
             !array_key_exists( $file, $this->arrayAssets[$nameAsset]['src'] ) ||
             $this->arrayAssets[$nameAsset]['path'] != $file ||
             $this->arrayAssets[$nameAsset]['latest'] != $latest ) {

            $this->unsavedChanges = true;
            return false;
        }
        return true;
    }    

    /** 
     * Добавляет данные к массиву с новыми значениями
     * @param $nameAsset
     * @param $file
     * @param $latest
     */
    public function addNewData ( $nameAsset, $file, $latest = 0, $version = 1 )
    {
        if( !array_key_exists( $nameAsset, $this->newDataArray ) )
            $this->newDataArray[$nameAsset] = array();

        if( !array_key_exists( 'src', $this->newDataArray[$nameAsset] ) )
            $this->newDataArray[$nameAsset]['src'] = array();

        $this->newDataArray[$nameAsset]['src'][$file] = [            
            'latest' => $latest           
        ];
    }
    
    /** 
     * Меняет версию для ассета
     * @param $nameAsset
     */
    public function getAssetVersion( $nameAsset ) {       
        if ( array_key_exists( $nameAsset, $this->newDataArray ) &&
             array_key_exists( 'version', $this->newDataArray[$nameAsset] ) ) {           
            return $this->newDataArray[$nameAsset]['version'];
        }
        return 1;
	}
    
    /** 
     * Меняет версию для ассета
     * @param $nameAsset
     */
    public function changeAssetVersion( $nameAsset ) {        
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
}