<?php

namespace alexshul\optimizer;

use Yii;

class JsonAssetsInfo
{
    private $jsonFileName = '';
    private $oldAssetDataArray = [];
    private $newAssetDataArray = [];
    private $oldScssDataArray = [];
    private $newScssDataArray = [];
    private $configFileMTime = 0;
    private $unsavedChanges = false;

    function __construct() {
        $this->jsonFileName = Yii::getAlias('@runtime') . '/alex-shul/yii2-optimizer/assets-info.json';
    }

    /**
     *  Создает массив с данными из assets-info.json, если файл существует
     *  Данные пишет в $this->oldAssetDataArray
     * @param $cache Cache
     */
    public function loadAssetsInfo( $cache )
    {
        if( file_exists( $this->jsonFileName ) ) {
            $data = json_decode( file_get_contents( $this->jsonFileName ), true );
            //Yii::debug($data);

            if( $data !== NULL ) {
                if( array_key_exists( 'assets', $data ) ) {
                    $this->oldAssetDataArray = $data['assets'];
                }
                if( array_key_exists( 'scss', $data ) ) {
                    $this->oldScssDataArray = $data['scss'];
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

        //Yii::debug('Save assets json data. Data: '.print_r($this->newAssetDataArray,true));            
        $json = json_encode( array( 
            'assets' => $this->newAssetDataArray,
            'scss' => $this->newScssDataArray,
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
    public function checkScssData( $path )
    {
        // 1. Get all scss files from import directory
        $di = new \RecursiveDirectoryIterator( $path ,\RecursiveDirectoryIterator::SKIP_DOTS);
        $it = new \RecursiveIteratorIterator($di);

        foreach($it as $file) {
        	$ext = pathinfo($file, PATHINFO_EXTENSION);
        	if ( $ext == "scss" ) {  
                $file_path = str_replace( '\\', '/', $file);
        		$this->newScssDataArray[$file_path] = filemtime( $file_path );
        	}
        }
        //Yii::debug('Scss new array size: '.count($this->newScssDataArray));
        //Yii::debug('Scss old array size: '.count($this->oldScssDataArray));

        // 2. If files count different with previous scan - changes detected
        if( count( $this->newScssDataArray ) !== count( $this->oldScssDataArray ) ) {
            //Yii::debug('Scss new & old arrays not equal!');
            $this->unsavedChanges = true;
            return true;
        }

        // 3. Check each file's last modified time
        foreach( $this->newScssDataArray as $new_file => $new_mtime ) {
            // 3.a Check if previously recorded file exists now
            if( !array_key_exists( $new_file, $this->oldScssDataArray ) ) {
                //Yii::debug("Scss old array not has file: $new_file");
                $this->unsavedChanges = true;
                return true;
            }

            // 3.b Check mtime for equal
            if( $this->newScssDataArray[$new_file] !== $this->oldScssDataArray[$new_file] ) {
                //Yii::debug("Scss fileMTime not equal for file: $new_file\r\nOld: $this->oldScssDataArray[$new_file]\r\nNew: $this->newScssDataArray[$new_file]");
                $this->unsavedChanges = true;
                return true;
            }
        }
    
        //Yii::debug(print_r($this->oldAssetDataArray,true));        
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
        //Yii::debug(print_r($this->oldAssetDataArray,true));
        if ( !array_key_exists( $nameAsset, $this->oldAssetDataArray ) ||
             !array_key_exists( 'src', $this->oldAssetDataArray[$nameAsset] ) ||
             !array_key_exists( $file, $this->oldAssetDataArray[$nameAsset]['src'] ) ||            
             $this->oldAssetDataArray[$nameAsset]['src'][$file]['latest'] != $latest) {

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
        //Yii::debug(print_r($this->oldAssetDataArray,true));
        if ( !array_key_exists( $nameAsset, $this->oldAssetDataArray ) ||
             !array_key_exists( 'src', $this->oldAssetDataArray[$nameAsset] ) ||           
             count( $this->oldAssetDataArray[$nameAsset]['src'] ) != count( $this->newAssetDataArray[$nameAsset]['src'] ) ) {
                           
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
        //Yii::debug(print_r($this->oldAssetDataArray,true));
        if ( !array_key_exists( $nameAsset, $this->oldAssetDataArray ) ||
             !array_key_exists( 'dest', $this->oldAssetDataArray[$nameAsset] ) ||
             !array_key_exists( $file, $this->oldAssetDataArray[$nameAsset]['dest'] ) ||
             $this->oldAssetDataArray[$nameAsset]['dest'][$file]['latest'] != $latest) {

            //Yii::debug('checkAssetDestData() Asset name: ' . $nameAsset . ' File: ' . $file . ' Latest old: ' . $this->oldAssetDataArray[$nameAsset]['dest'][$file]['latest'] . 'Latest new: ' . $latest);
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
        if( !array_key_exists( $nameAsset, $this->newAssetDataArray ) )
            $this->newAssetDataArray[$nameAsset] = array();

        if( !array_key_exists( 'src', $this->newAssetDataArray[$nameAsset] ) )
            $this->newAssetDataArray[$nameAsset]['src'] = array();

        $this->newAssetDataArray[$nameAsset]['src'][$file] = [            
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
        if( !array_key_exists( $nameAsset, $this->newAssetDataArray ) )
            $this->newAssetDataArray[$nameAsset] = array();

        if( !array_key_exists( 'dest', $this->newAssetDataArray[$nameAsset] ) )
            $this->newAssetDataArray[$nameAsset]['dest'] = array();

        $this->newAssetDataArray[$nameAsset]['dest'][$file] = [            
            'latest' => $latest           
        ];

        if( !array_key_exists( 'version', $this->newAssetDataArray[$nameAsset] ) ) {
            $this->newAssetDataArray[$nameAsset]['version'] = array_key_exists( $nameAsset, $this->oldAssetDataArray ) && array_key_exists( 'version', $this->oldAssetDataArray[$nameAsset] ) ? $this->oldAssetDataArray[$nameAsset]['version'] : 1;
        }
    }

    /**
     * Вычисляет и возвращает текущую версию ассета
     * @param $nameAsset
     * @return int|mixed
     */
    public function getAssetVersion( $nameAsset ) { 
        $version = 1;       

        if ( array_key_exists( $nameAsset, $this->newAssetDataArray ) &&
             array_key_exists( 'version', $this->newAssetDataArray[$nameAsset] ) ) {         
            $version = $this->newAssetDataArray[$nameAsset]['version'];
            //Yii::debug('getAssetVersion ('.$nameAsset.') -> new');

            if ( array_key_exists( $nameAsset, $this->oldAssetDataArray ) &&
                array_key_exists( 'version', $this->oldAssetDataArray[$nameAsset] ) ) {         
                $version = max( $this->oldAssetDataArray[$nameAsset]['version'], $this->newAssetDataArray[$nameAsset]['version'] );
                //Yii::debug('getAssetVersion('.$nameAsset.') -> max old: '.$this->oldAssetDataArray[$nameAsset]['version'].' new: '.$this->newAssetDataArray[$nameAsset]['version']);
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

        if ( !array_key_exists( $nameAsset, $this->newAssetDataArray ) ) {
            $this->newAssetDataArray[$nameAsset] = array();
        } 

        if ( !array_key_exists( $nameAsset, $this->oldAssetDataArray ) ||
             !array_key_exists( 'version', $this->oldAssetDataArray[$nameAsset] ) ) {
            $this->newAssetDataArray[$nameAsset]['version'] = 1;
            return;
        }

		$this->newAssetDataArray[$nameAsset]['version'] = $this->oldAssetDataArray[$nameAsset]['version'] + 1;

		if( $this->newAssetDataArray[$nameAsset]['version'] > 999998 ) {
			$this->newAssetDataArray[$nameAsset]['version'] = 1;
        }	
        
        //Yii::debug('Data: '.print_r($this->newAssetDataArray,true)); 
        
        return $this->newAssetDataArray[$nameAsset]['version'];
	}
}