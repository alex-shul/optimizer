<?php
/**
 * @link https://github.com/alex-shul/yii2-optimizer
 * @author Alex Shul
 * @license MIT
 */

namespace alexshul\optimizer;

use Yii;

class AssetLoader {

	private $assetsList = array();

	function __construct( $assetsToWatch ) {
		$this->assetsList = $assetsToWatch;
	}

	public function getScriptPathEx() {
		$cur_c = Yii::$app->controller->id;
		$cur_a = Yii::$app->controller->action->id;
		$use_action = false;

		foreach( $this->assetsList as $asset ) {
			if( array_key_exists( 'used', $asset ) and
				array_key_exists( 'controller', $asset['used'] ) and
				$asset['used']['controller'] == $cur_c and
				array_key_exists( 'action', $asset['used'] ) ) {
				$use_action = true;
			}
		}

		$pathEx = $cur_c;
		if( $use_action ) {
			$pathEx .= '/' . $cur_a;
		}			
		
		return $pathEx;
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
		$cur_c = Yii::$app->controller->id;
		$cur_a = Yii::$app->controller->action->id;

		foreach( $this->assetsList as $asset ) {
			if( array_key_exists( 'used', $asset ) ) {
				// Script is need by default
				$skip = false;

				// Get paramters for checking conditions for script need on this page
				$only = array_key_exists( 'condition', $asset['used'] ) && $asset['used']['condition'] == 'except' ? false : true;
				$target_c = array_key_exists( 'controller', $asset['used'] ) ? $asset['used']['controller'] : false;
				$target_a = array_key_exists( 'action', $asset['used'] ) ? $asset['used']['action'] : false;
				
				// Checking conditions only if controller is set
				if( $target_c ) {

					// If condition = used only on specified pages
					if( $only ) {
						// Current controller not a target controller
						if( $cur_c != $target_c ) {
							$skip = true;
						}

						// Current action is set AND is not a target action
						if( $target_a && $target_a != $cur_a ) {
							$skip = true;
						}

					// If condition = used on all pages except specified
					} else {
						// Current controller is a target controller
						if( $cur_c == $target_c ) {
							$skip = true;
						}

						// Current action is set AND is a target action
						if( $target_a && $target_a == $cur_a ) {
							$skip = true;
						}	
					}	
	
					// If conditions checked and script not needed on this page -> then skip him
					if( $skip ) continue;
				}				
			}

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
			//		2) Do not set version due to errors with CDN links, for example:
			//			"https://fonts.googleapis.com/css?family=Roboto?v=1"
			if( isset( $asset['type'] ) ) {
				$type =  ", type:'" . $asset['type'] . "'";
				$version_print = '';
			} else
				$version_print = '?v=' . $asset['version'];

			if( isset( $asset['showPage'] ) && $asset['showPage'] ) {
				$loaded = ', loaded:true';
			}
				

			if( isset( $asset['condition'] ) ) {
				$script .= "\r\n" . $tab . 'if( ' . $asset['condition'] . ' )';
				$tab = '					';
			}

			$script .= "\r\n" . $tab . "m.enqueue({src:'" . $asset['dest'] . $version_print . "'" . $type . $loaded . "});";
		}
		$script .= "\r\n" . $tab ."m.next();\r\n";	
	}
}
