<?php
/**
 * @link https://github.com/alex-shul/yii2-optimizer
 * @author Alex Shul
 * @license MIT
 */

namespace alexshul\optimizer;

use Yii;

class Cache {

	private $iniFileName = '';
	private $loaderFileName = '';
	private $options = null;

	function __construct() {
		$base = Yii::getAlias('@runtime');
		$this->iniFileName = $base . '/alex-shul/yii2-optimizer/cache.ini';
		$this->loaderPath = $base . '/alex-shul/yii2-optimizer/loader';
		$this->loaderFileName = 'loader.js';
		$this->check();	
	}

	public function check() {
		if( $this->options )
			return true;
			
		if( file_exists( $this->iniFileName ) ) {			
			$this->options = parse_ini_file( $this->iniFileName, true, INI_SCANNER_TYPED );
			return $this->options !== FALSE ? true : $this->default();
		} else {
			return $this->default();
		}	
	}

	public function default() {
		$this->options = array(
			'version' => 1
		);

		return true;
	}

	public function validateFilePath( $file ) {
		$dir = substr( $file, 0, strrpos( $file, '/' ) );
		if( !file_exists( $dir ) )
			return mkdir( $dir, 0777, true );
		return true;
	}

	public function save() {
		$content = '';        
		$linebreak = "\r\n";
        
		foreach ( $this->options as $key => $value ) {
			$content .= $key . ' = ' . $value . $linebreak;				
		}
		
		if( false === $this->validateFilePath( $this->iniFileName ) ) {
			if( YII_ENV_DEV ) throw new Exception( 'can\'t validate dir for file "'.$this->iniFileName.'".' );
			return false;
		}

        if ( false === file_put_contents( $this->iniFileName, $content ) ) {
			if( YII_ENV_DEV ) throw new Exception( 'failed to open file "'.$this->iniFileName.'" for writing.' );
			return false;			
		}
		
        return true;
	}	

	public function get( $optionName ) {		
		return ( is_string( $optionName ) && isset( $this->options[$optionName] ) ? $this->options[$optionName] : '' );
	}

	public function set( $name, $value ) {
		$this->options[$name] = $value;		
		$this->save();
	}

	public function saveLoaderScript( $script, $pathEx = '' ) {
		$fileName = $this->getLoaderScriptFileName( $pathEx );

		$dir = substr( $fileName, 0, strrpos( $fileName, '/' ) );
		if( !file_exists( $dir ) )
			mkdir( $dir, 0777, true );		
		
		if( false === file_put_contents( $fileName, $script ) && YII_ENV_DEV ) {
            throw new Exception(
                sprintf(
                    'Failed to open file `%s\' for writing.', $fileName
                )
			);			
		}
		
        return true;
	}

	public function getLoaderScriptFileName( $pathEx = '' ) {
		$path_len = strlen( $pathEx );

		if( $path_len and $pathEx[$path_len-1] !== '/' ) {			
			$pathEx .= '/';
		}

		return $this->loaderPath . '/' . $pathEx . $this->loaderFileName;		
	}

	public function getLoaderScript( $pathEx = '' ) {
		$fileName = $this->getLoaderScriptFileName( $pathEx );

		if( file_exists( $fileName ) ) {
			return file_get_contents( $fileName );
		}

		return false;		
	}

	public function clearLoaderScript( $pathEx = '' ) {
		$fileName = $this->getLoaderScriptFileName( $pathEx );

		if( file_exists( $fileName ) ) {
			return unlink( $fileName );
		}

		return false;
	}

}