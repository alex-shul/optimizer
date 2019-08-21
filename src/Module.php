<?php
/**
 * @link https://github.com/alex-shul/yii2-optimizer
 * @author Alex Shul
 * @license MIT
 */

namespace alexshul\optimizer;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\Application;
use yii\base\Event;
use yii\web\View;
use yii\console\Exception;
use tubalmartin\CssMin\Minifier as CSSmin;
use \JShrink\Minifier;
use ScssPhp\ScssPhp\Compiler;
use alexshul\optimizer\Cache as Cache;
use alexshul\optimizer\AssetLoader as AssetLoader;
use alexshul\optimizer\AssetIterator as AssetIterator;

class Module extends \yii\base\Module implements BootstrapInterface {

	public  $assetsConfigFile = '';
	public  $assetsToWatch = array();
	public  $assetsClearStyles = false;
	public  $assetsClearScripts = false;
	public  $assetsAddLoader = false;
	public  $assetsMinifyLoader = false;
	public  $scssImportBasePath = '';

	private $cache = null;	
	private $basePath = null;
	private $webPath = null;
	private $jsonData = null;
	private $scriptNeedRebuild = false;

	function __construct() {
		$this->cache = new Cache;	
		$this->basePath = Yii::getAlias('@app') . '/';
		$this->webPath = Yii::getAlias('@webroot') . '/';		
	}	

	public function bootstrap($app) {		
		Event::on(View::className(), View::EVENT_BEGIN_PAGE, function ( $event ) {				
			$this->onBeginPage( $event->sender );				
		});
		Event::on(View::className(), View::EVENT_END_PAGE, function ( $event ) {				
			$this->onEndPage( $event->sender );				
		});
	}

	protected function onBeginPage( \yii\web\View &$view ) {
		if( $this->assetsClearStyles || $this->assetsClearScripts )
			$this->unregisterAssets( $view );	
	}

	protected function onEndPage( \yii\web\View &$view ) {
		if( !is_dir( $this->basePath ) )
			return;

		$asset = new AssetIterator( $this->assetsToWatch );
		$this->checkForChanges( $asset, $view );

		if( $this->assetsAddLoader )
			$this->addLoader();			
	}

	public function checkForChanges( AssetIterator &$asset, \yii\web\View &$view ) {		
        $this->jsonData = new JsonAssetsInfo();
		$this->jsonData->loadAssetsInfo( $this->cache );

		// 1. Check config file
		$changes_cfg = $this->jsonData->checkConfigFile( $this->assetsConfigFile );	

		// 2. Check scss files in import base directory
		$changes_scss = $this->jsonData->checkScssData( Yii::getAlias('@app') . '/' . $this->scssImportBasePath );

		// 3. Check asset files
		foreach( $asset as $assetOptions ) {
			if( !$asset->hasDest() || $asset->fromCDN() )
				continue;
			
			$dest = $asset->extendDest( $this->webPath );
			$destMTime = file_exists( $dest ) ? filemtime( $dest ) : 0;

			$changes_src = false;
			$changes_dest = $this->jsonData->checkAssetDestData( $asset->name(), $dest, $destMTime );
			$need_rebuild = false;

			$src_latest = 0;
			foreach( $asset->src() as &$srcFile ) {
				$srcFile = $this->basePath . $srcFile;

				if( !file_exists( $srcFile ) )
					continue;
				
				// Пишет новые данные в массив
				$srcMTime = filemtime( $srcFile );
				$this->jsonData->addNewSrcData( $asset->name(), $srcFile, $srcMTime );

				$src_latest = max( $srcMTime, $src_latest );

				if ( !$changes_src && !$changes_dest ) {
					// Сверяет данные из json и конфига
					$changes_src = $this->jsonData->checkAssetSrcData( $asset->name(), $srcFile, $srcMTime );
				}
			}

			//	Если изменений всё ещё не обнаружено - проверяем изменилось ли количество исходных файлов в конфиге
			if( !$changes_src )
				$changes_src = $this->jsonData->checkAssetSrcCountData( $asset->name() );

			if( $asset->hasSrc() ) {
				$is_script = $asset->isScript();				

				// If dest file not exists or source files has changes
				if( !file_exists( $dest ) || $changes_src ) {
					$need_rebuild = true;
				}

				// If this is styles asset bundle and scss files has changes
				if( !$is_script && $changes_scss ) {
					$need_rebuild = true;
				}
	
				// If need rebuild - minifying/compiling js/css/scss
				if( $need_rebuild ) {
					Yii::debug( 'Minifying ' . $asset->name() . '...' );
					$data = $is_script ? $this->minifyJS( $asset->src() ) :  $this->minifyCSS( $asset->src() );
					
					if( false === file_put_contents( $dest, $data ) && YII_ENV_DEV ) {
						throw new Exception( 'alexshul/optimizer: can\'t write to file "' . $dest . '"' );
					}	
				}														
			}

			if( $need_rebuild ) {
				$this->jsonData->changeAssetVersion( $asset->name() );
				$changes_cfg = true;
			}

			$this->jsonData->addNewDestData( $asset->name(), $dest, $destMTime );
			$asset->setVersion( $this->jsonData->getAssetVersion( $asset->name() ) );

			$this->performAdditionalActions( $asset, $view );
		}	

		if( $changes_cfg ) {
			$this->scriptNeedRebuild = true;			
		}
		
		$this->jsonData->updateAssetsInfo();
	}

	public function performAdditionalActions( AssetIterator &$asset, \yii\web\View &$view  ) {
		$assetOptions = $asset->options();
		
		//	Add additional link for styles: <noscript><link rel="stylesheet" ...></noscript>
		// 	IF 
		//		- option is not set 
		//				OR
		//		- option is set to 'true'
		$noscript = ( !isset( $assetOptions['noscript'] ) || $assetOptions['noscript'] );
		if( !$asset->isScript() && $noscript ) {	
			$dest = substr( $asset->dest(), strlen( $this->webPath ) - 1 );	
			$view->registerCssFile( $dest, [ 'rel' => 'stylesheet', 'noscript' => true ], $asset->name() );
		}

		//	Add additional preload link for all assets if option is set		
		// 	IF 		
		//		- option is set to 'true'
		$preload = ( isset( $assetOptions['preload'] ) && $assetOptions['preload'] );
		if( $preload ) {	
			$dest = substr( $asset->dest(), strlen( $this->webPath ) - 1 );
			$as = $asset->isScript() ? 'script' : 'style';
			$view->registerCssFile( $dest, [ 'rel' => 'preload', 'as' => $as ], $asset->name() );
		}
	}

	public function unregisterAssets( \yii\web\View &$view ) {
		foreach( $view->assetBundles as $key => &$asset ) {			
			if( $this->assetsClearStyles && count( $asset->css ) ) {
				array_splice( $asset->css, 0 );		
			}
			if( $this->assetsClearScripts && count( $asset->js ) ) {
				array_splice( $asset->js, 0 );
			}
		}
	}

	public function addLoader() {	
		$loader = new AssetLoader( $this->assetsToWatch );
		$pathEx = $loader->getScriptPathEx();
		$script = false;
		
		if( $this->scriptNeedRebuild ) {
			$this->cache->clearLoaderScript();
		} else {
			$script = $this->cache->getLoaderScript( $pathEx );
		}
		
		//Yii::debug($script);
		if( $script === false ) {					 
			$script = $loader->generateScript();			

			if( $this->assetsMinifyLoader )
				$script = $this->minifyJS( $script );

			$this->cache->saveLoaderScript( $script, $pathEx );
		}

		Yii::$app->getView()->registerJs( $script, View::POS_END, 'loader' );
		//Yii::$app->response->data = str_replace( '</body>', "\r\n<script>\r\n" . $script . "\r\n</script>\r\n</body>", Yii::$app->response->data );				
    }
	
	public function minifyCSS( &$input = array() ) {
		$input_css = '';
		//$base_path = Yii::getAlias('@app') . '/';	
		
		if( is_array( $input ) ) {
			$input_css = $this->combineFiles( $input );
			//$base_path .= $this->findBasePath( $input ) . '/';			
		} else if( is_string( $input ) ) {
			$input_css = $input;
		} else {
			if( YII_ENV_DEV ) throw new Exception('yii2-optimizer::minifyCSS - invalid input format.');
			return $input_css;
		}

		// Compile scss, if exists
		// yii::debug('SCSS PATH: '.$this->scssImportBasePath);
		// return;
		
		$scss = new Compiler();
		$scss->setImportPaths( Yii::getAlias('@app') . '/' . $this->scssImportBasePath );
		$input_css = $scss->compile( $input_css );

		// Create a new CSSmin object.
		// By default CSSmin will try to raise PHP settings.
		// If you don't want CSSmin to raise the PHP settings pass FALSE to
		// the constructor i.e. $compressor = new CSSmin(false);
		$compressor = new CSSmin;

		// Set the compressor up before compressing (global setup):

		// Keep sourcemap comment in the output.
		// Default behavior removes it.
		$compressor->keepSourceMapComment(false);

		// Remove important comments from output.
		$compressor->removeImportantComments();

		// Split long lines in the output approximately every 1000 chars.
		$compressor->setLineBreakPosition(0);

		// Override any PHP configuration options before calling run() (optional)
		$compressor->setMemoryLimit('256M');
		$compressor->setMaxExecutionTime(120);
		$compressor->setPcreBacktrackLimit(3000000);
		$compressor->setPcreRecursionLimit(150000);

		// Compress the CSS code!
		$output_css = $compressor->run($input_css);

		// You can override any setup between runs without having to create another CSSmin object.
		// Let's say you want to remove the sourcemap comment from the output and
		// disable splitting long lines in the output.
		// You can achieve that using the methods `keepSourceMap` and `setLineBreakPosition`:
		// $compressor->keepSourceMapComment(false);
		// $compressor->setLineBreakPosition(0);
		// $output_css = $compressor->run($input_css); 

		// Do whatever you need with the compressed CSS code
		return $output_css;
	}	

	public function minifyJS( &$input = array() ) {		
		$data = '';		
		
		if( is_array( $input ) ) {
			$data = $this-> combineFiles( $input );			
		} else if( is_string( $input ) ) {
			$data = $input;
		} else {
			if( YII_ENV_DEV ) throw new Exception('yii2-optimizer::minifyJS - invalid input format.');
			return $data;
		}
		
		// Disable YUI style comment preservation.		
		return \JShrink\Minifier::minify($data, array('flaggedComments' => false));
	}

	public function combineFiles( &$files = array() ) {
		$buf = null;

		foreach($files as $file) {
			try {
				$data = file_get_contents($file);
			} catch( Exception $e ) {
				if( 0 && YII_ENV_DEV ) throw $e;
				continue;
			}
			
			$buf .= $data;
		}

		return $buf;
	}
	
	public function findBasePath( &$paths = [] )
	{
		$base = FALSE;

		foreach( $paths as $path ) {
			$pos_win = strrpos( $path, '\\' );
			$pos_win = $pos_win === FALSE ? 999 : $pos_win;		
			
			$pos_lin = strrpos( $path, '/' );
			$pos_lin = $pos_lin === FALSE ? 999 : $pos_lin;			
			
			$pos = min( $pos_win, $pos_lin );
		   
			
			$path_base = substr( $path, 0, $pos );
			if( $base === FALSE or strlen( $path_base ) < strlen( $base ) ) {
				$base = $path_base;
			}
		}

		return $base;
	}
}