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

	public function generateScript() {
		$script = <<< JS
			window.addEventListener( 'load', function(){
				function AssetManager() {
					var _queue = [],
						_loading = false,
						_loaded = false,			
					_next = this.next = function () {
						if( _queue.length && !_loading ) {
							load( _queue.shift(), function(){
								if( _loaded ) {
									document.body.classList.add('loaded');
									_loaded = false;
								}
								_loading = false;
								_next();
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

						if( typeof asset.loaded !== 'undefined' )
							_loaded = true;
					}
					this.enqueue = function ( asset ) {
						_queue.push( asset );
					}					
				}
				var m = new AssetManager();
				
JS;
		$script_end = <<< JS
			});
JS;
		$this->printAssetsTo( $script );
		$script .= $script_end;

		return $script;
	}

	private function printAssetsTo( &$script ) {			
		foreach( $this->assetsList as $asset ) {
			if( !isset( $asset['dest'] ) )
				continue;

			if( isset( $asset['autoload'] ) && !$asset['autoload'] )
				continue;

			if( !isset( $asset['version'] ) )
				$asset['version'] = 1;			
			
			$tab = '				';
			$type = '';
			$loaded = '';
			//	If 'type' is set:
			//		1) Push it into js 
			//		2) Not set version due to errors with CDN links, for example:
			//			"https://fonts.googleapis.com/css?family=Roboto"
			if( is_string( $asset['type'] ) ) {
				$type =  ", type:'" . $asset['type'] . "'";
				$version_print = '';
			} else
				$version_print = '?v=' . $asset['version'];

			if( isset( $asset['showPage'] ) && $asset['showPage'] ) {
				$loaded = ', loaded:true';
			}
				

			if( is_string( $asset['condition'] ) ) {
				$script .= "\r\n" . $tab . 'if( ' . $asset['condition'] . ' )';
				$tab = '					';
			}

			$script .= "\r\n" . $tab . "m.enqueue({src:'" . $asset['dest'] . $version_print . "'" . $type . $loaded . "});";
		}
		$script .= "\r\n" . $tab ."m.next();\r\n";	
	}
}
