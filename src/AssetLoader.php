<?php
/**
 * @link https://github.com/alex-shul/yii2-optimizer
 * @author Alex Shul
 * @license MIT
 */

namespace alexshul\optimizer;

class AssetLoader {

	private $assetsList = array();

	function __construct( $assetsToWatch ) {
		$this->assetsList = $assetsToWatch;
	}

	public function generateScript( $version = 1 ) {
		$script = <<< JS
			window.addEventListener( 'load', function(){
				function AssetManager() {
					var _queue = [],
						_loading = false;			
					function next() {
						if( _queue.length && !_loading ) {
							load( _queue.shift(), function(){
								_loading = false;
								next();
							});
							_loading = true;
						}
					}
					function load( asset, cb ) {						
						var t = 'link';
						if( typeof asset.type === 'string' )
							t = asset.type;
						else if( asset.src.indexOf('.js') > 0 )
							t = 'script';

						var e = document.createElement( t );
						if( t == 'script' ) {
							e.src = asset.src;
						} else {
							e.rel = 'stylesheet';
							e.href = asset.src;
						}				
						e.onload = cb;
						e.onerror = cb;
						
						document.head.appendChild( e );
					}
					this.enqueue = function( resource ) {
						if( typeof resource !== 'string' )
							return;

						var asset = {
							'src' : resource
						}						
						if( arguments.length > 1 ) {
							asset.type = arguments[1];
						}

						_queue.push( asset );
						next();

						return this;			
					}
				}
				var m = new AssetManager();
				
JS;
		$script_end = <<< JS
			});
JS;
		$this->printAssetsTo( $script, $version );
		$script .= $script_end;

		return $script;
	}

	private function printAssetsTo( &$script, $version = 1 ) {			
		foreach( $this->assetsList as $asset ) {
			if( !isset( $asset['dest'] ) )
				continue;

			if( isset( $asset['autoload'] ) && !$asset['autoload'] )
				continue;
			
			$tab = '				';
			$type = '';
			//	If 'type' is set:
			//		1) Push it into js 
			//		2) Not set version due to errors with CDN links, for example:
			//			"https://fonts.googleapis.com/css?family=Roboto"
			if( is_string( $asset['type'] ) ) {
				$type =  ", '" . $asset['type'] . "'";
				$version = '';
			} else
				$version_print = '?v=' . $version;

			if( is_string( $asset['condition'] ) ) {
				$script .= "\r\n" . $tab . 'if( ' . $asset['condition'] . ' )';
				$tab = '					';
			}

			$script .= "\r\n" . $tab . "m.enqueue('" . $asset['dest'] . $version_print . "'" . $type . ");";
		}
		$script .= "\r\n";	
	}
}
