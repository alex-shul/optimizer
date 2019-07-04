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
		$this->loaderFileName = $base . '/alex-shul/yii2-optimizer/loader.js';
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

	public function save() {
		$content = '';        
		$linebreak = "\r\n";
        
		foreach ( $this->options as $key => $value ) {
			$content .= $key . ' = ' . $value . $linebreak;				
		}
		
		$dir = substr( $this->iniFileName, 0, strrpos( $this->iniFileName, '/' ) );
		if( !file_exists( $dir ) )
			mkdir( $dir, 0777, true );

        if ( false === file_put_contents( $this->iniFileName, $content ) ) {
            throw new Exception(
                sprintf(
                    'failed to open file `%s\' for writing.', $this->iniFileName
                )
			);			
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

	public function changeAssetsVersion() {
		if( !isset( $this->options['version'] ) ) {
			$this->options['version'] = 1;
		}

		$this->options['version']++;

		if( $this->options['version'] > 999998 ) {
			$this->options['version'] = 1;
		}
		
		$this->save();
	}

	public function saveLoaderScript( $script ) {
		$dir = substr( $this->loaderFileName, 0, strrpos( $this->loaderFileName, '/' ) );
		if( !file_exists( $dir ) )
			mkdir( $dir, 0777, true );		
		
		if( false === file_put_contents( $this->loaderFileName, $script ) && YII_ENV_DEV ) {
            throw new Exception(
                sprintf(
                    'failed to open file `%s\' for writing.', $this->loaderFileName
                )
			);			
		}
		
        return true;
	}

	public function getLoaderScript() {
		if( file_exists( $this->loaderFileName ) ) {
			return file_get_contents( $this->loaderFileName );
		}

		return false;		
	}

}