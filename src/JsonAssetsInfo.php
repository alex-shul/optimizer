<?php

namespace alexshul\optimizer;

use Yii;

class JsonAssetsInfo
{
    private $jsonFileName = '';
    private $oldDataArray = [];
    private $newDataArray = [];
    private $configFileMTime = 0;
    private $unsavedChanges = false;

    function __construct() {
        $this->jsonFileName = Yii::getAlias('@runtime') . '/alex-shul/yii2-optimizer/assets-info.json';
    }

    /**
     *  Создает массив с данными из assets-info.json, если файл существует
     *  Данные пишет в $this->oldDataArray
     * @param $cache Cache
     */
    public function loadAssetsInfo( $cache )
    {
        if( file_exists( $this->jsonFileName ) ) {
            $data = json_decode( file_get_contents( $this->jsonFileName ), true );
            //Yii::debug($data);

            if( $data !== NULL ) {
                if( array_key_exists( 'assets', $data ) ) {
                    $this->oldDataArray = $data['assets'];
                }
                if( array_key_exists( 'configFileMTime', $data ) ) {
                    $this->configFileMTime = $data['configFileMTime'];
                }
            } elseif( YII_ENV_DEV ) throw new Exception( 'alexshul/optimizer: corrupted json data in file "' . $this->jsonFileName . '".' );
        } else {
            $cache->validateFilePath( $this->jsonFileName );
            $this->unsavedChanges = true;
        }
    }

    /**
     * Обновляет файл assets-info.json данными из сформированного массива
     */
    public function updateAssetsInfo()
    {        
        if( !$this->unsavedChanges )
            return;

        //Yii::debug('Save assets json data. Data: '.print_r($this->newDataArray,true));            
        $json = json_encode( array( 
            'assets' => $this->newDataArray,
            'configFileMTime' => $this->configFileMTime ) );
        file_put_contents( $this->jsonFileName, $json );
        //Yii::debug($json);
    }

    /** 
     * Сверяет данные полученные из json и текущий файл     
     * @param $file
     * @return bool
     */
    public function checkConfigFile( $file ) {
        $now = file_exists( $file ) ? filemtime( $file ) : 0;
        //Yii::debug('NOW: ' . $now .'OLD: ' . $this->configFileMTime);
        if( $now > $this->configFileMTime ) {
            $this->configFileMTime = $now;
            $this->unsavedChanges = true;
            return true;           
        }
        return false;
    }

    /** 
     * Сверяет данные полученные из json и полученные из конфига
     * @param $nameAsset
     * @param $file
     * @param $latest
     * @return bool
     */
    public function checkAssetSrcData($nameAsset, $file, $latest)
    {
        //Yii::debug(print_r($this->oldDataArray,true));
        if ( !array_key_exists( $nameAsset, $this->oldDataArray ) ||
             !array_key_exists( 'src', $this->oldDataArray[$nameAsset] ) ||
             !array_key_exists( $file, $this->oldDataArray[$nameAsset]['src'] ) ||            
             $this->oldDataArray[$nameAsset]['src'][$file]['latest'] != $latest) {

            //Yii::debug('checkAssetSrcData() Asset name: ' . $nameAsset . ' File: ' . $file . ' Latest: ' . $latest);
            $this->unsavedChanges = true;
            return true;
        }
        return false;
    } 

    /** 
     * Сверяет данные полученные из json и полученные из конфига
     * @param $nameAsset
     * @param $file
     * @param $latest
     * @return bool
     */
    public function checkAssetSrcCountData( $nameAsset )
    {
        //Yii::debug(print_r($this->oldDataArray,true));
        if ( !array_key_exists( $nameAsset, $this->oldDataArray ) ||
             !array_key_exists( 'src', $this->oldDataArray[$nameAsset] ) ||           
             count( $this->oldDataArray[$nameAsset]['src'] ) != count( $this->newDataArray[$nameAsset]['src'] ) ) {
                           
            $this->unsavedChanges = true;
            return true;
        }
        return false;
    } 
    
    /** 
     * Сверяет данные полученные из json и полученные из конфига
     * @param $nameAsset
     * @param $file
     * @param $latest
     * @return bool
     */
    public function checkAssetDestData($nameAsset, $file, $latest)
    {
        //Yii::debug(print_r($this->oldDataArray,true));
        if ( !array_key_exists( $nameAsset, $this->oldDataArray ) ||
             !array_key_exists( 'dest', $this->oldDataArray[$nameAsset] ) ||
             !array_key_exists( $file, $this->oldDataArray[$nameAsset]['dest'] ) ||
             $this->oldDataArray[$nameAsset]['dest'][$file]['latest'] != $latest) {

            //Yii::debug('checkAssetDestData() Asset name: ' . $nameAsset . ' File: ' . $file . ' Latest old: ' . $this->oldDataArray[$nameAsset]['dest'][$file]['latest'] . 'Latest new: ' . $latest);
            $this->unsavedChanges = true;
            return true;
        }
        return false;
    } 

    /** 
     * Добавляет данные к массиву с новыми значениями
     * @param $nameAsset
     * @param $file
     * @param $latest
     */
    public function addNewSrcData( $nameAsset, $file, $latest = 0 )
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
     * Добавляет данные к массиву с новыми значениями
     * @param $nameAsset
     * @param $file
     * @param $latest
     */
    public function addNewDestData( $nameAsset, $file, $latest = 0 )
    {
        if( !array_key_exists( $nameAsset, $this->newDataArray ) )
            $this->newDataArray[$nameAsset] = array();

        if( !array_key_exists( 'dest', $this->newDataArray[$nameAsset] ) )
            $this->newDataArray[$nameAsset]['dest'] = array();

        $this->newDataArray[$nameAsset]['dest'][$file] = [            
            'latest' => $latest           
        ];

        if( !array_key_exists( 'version', $this->newDataArray[$nameAsset] ) ) {
            $this->newDataArray[$nameAsset]['version'] = array_key_exists( 'version', $this->oldDataArray[$nameAsset] ) ? $this->oldDataArray[$nameAsset]['version'] : 1;
        }
    }

    /**
     * Вычисляет и возвращает текущую версию ассета
     * @param $nameAsset
     * @return int|mixed
     */
    public function getAssetVersion( $nameAsset ) { 
        $version = 1;       

        if ( array_key_exists( $nameAsset, $this->newDataArray ) &&
             array_key_exists( 'version', $this->newDataArray[$nameAsset] ) ) {         
            $version = $this->newDataArray[$nameAsset]['version'];
            //Yii::debug('getAssetVersion ('.$nameAsset.') -> new');

            if ( array_key_exists( $nameAsset, $this->oldDataArray ) &&
                array_key_exists( 'version', $this->oldDataArray[$nameAsset] ) ) {         
                $version = max( $this->oldDataArray[$nameAsset]['version'], $this->newDataArray[$nameAsset]['version'] );
                //Yii::debug('getAssetVersion('.$nameAsset.') -> max old: '.$this->oldDataArray[$nameAsset]['version'].' new: '.$this->newDataArray[$nameAsset]['version']);
            }
        }

        //Yii::debug('getAssetVersion('.$nameAsset.') -> return '.$version);
        return $version;
	}
    
    /** 
     * Меняет версию для ассета
     * @param $nameAsset
     */
    public function changeAssetVersion( $nameAsset ) { 
        //Yii::debug('changeAssetVersion for '.$nameAsset);
        $this->unsavedChanges = true;

        if ( !array_key_exists( $nameAsset, $this->newDataArray ) ) {
            $this->newDataArray[$nameAsset] = array();
        } 

        if ( !array_key_exists( $nameAsset, $this->oldDataArray ) ||
             !array_key_exists( 'version', $this->oldDataArray[$nameAsset] ) ) {
            $this->newDataArray[$nameAsset]['version'] = 1;
            return;
        }

		$this->newDataArray[$nameAsset]['version'] = $this->oldDataArray[$nameAsset]['version'] + 1;

		if( $this->newDataArray[$nameAsset]['version'] > 999998 ) {
			$this->newDataArray[$nameAsset]['version'] = 1;
        }	
        
        //Yii::debug('Data: '.print_r($this->newDataArray,true));        
	}
}