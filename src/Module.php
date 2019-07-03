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
use tubalmartin\CssMin\Minifier as CSSmin;
use yii\console\Exception;
use alexshul\optimizer\Cache as Cache;
use alexshul\optimizer\AssetLoader as AssetLoader;

class Module extends \yii\base\Module implements BootstrapInterface {

	public  $assetsToWatch = array();
	public  $assetsClearStyles = false;
	public  $assetsClearScripts = false;
	public  $assetsAddLoader = false;

	private $cache = null;	
	private $basePath = null;

	function __construct() {
		$this->cache = new Cache;	
		$this->basePath = Yii::getAlias('@app') . '/';	
	}	

	public function bootstrap($app) {
        $app->on(Application::EVENT_AFTER_REQUEST, function () {			
            $this->run();
        });
	}

	protected function run () {
		if( !is_dir($this->basePath) )
			return false;

		$this->checkSourceFiles();

		if( $this->assetsClearStyles || $this->assetsClearScripts )
			$this->clearLinks();

		if( $this->assetsAddLoader )
			$this->addLoader();
	}

	public function checkSourceFiles() {
		foreach( $this->assetsToWatch as $bundle ) {
			//	Break if destination not set
			//		OR
			//	Break if external url given, i. e. "https://fonts.googleapis.com/css?family=..."
			if( !isset( $bundle['dest'] ) || filter_var( $bundle['dest'], FILTER_VALIDATE_URL ) !== FALSE )
				continue;

			$src = is_array( $bundle['src'] ) ? $bundle['src'] : array();
			$dest = $this->basePath . $bundle['dest'];
			
			//-----------------------------
			//	Process CSS bundles
			//-----------------------------
			if( strpos( $dest, '.css' ) !== FALSE || ( is_string( $bundle['type'] ) && $bundle['type'] === 'link' ) ) {

				$css_latest = 0;		
				foreach( $src as $key => $path ) {
					$src[$key] = $this->basePath . $path;

					if( !file_exists( $src[$key] ) )
						continue;

					$css_latest = max( filemtime( $src[$key] ), $css_latest );
				}						
				
				if( !file_exists( $dest ) || $css_latest > filemtime( $dest ) ) {
					$out_buf = $this->minifyCSS( $src );
					if( false === file_put_contents( $dest, $out_buf) && YII_ENV_DEV ) {
						throw new Exception( 'alexshul/optimizer: can\'t write to file "' . $dest . '"' );
					} else {
						$this->cache->changeVersion();
					}								
				}

			//-----------------------------
			//	Process JS bundles
			//-----------------------------			
			} else if( strpos( $dest, '.js' ) !== FALSE || ( is_string( $bundle['type'] ) && $bundle['type'] === 'script' ) ) {

				$js_latest = 0;		
				foreach( $src as $key => $path ) {
					$src[$key] = $this->basePath . $path;

					if( !file_exists( $src[$key] ) )
						continue;

					$js_latest = max( filemtime( $src[$key] ), $js_latest );
				}						
				
				if( !file_exists( $dest ) || $js_latest > filemtime( $dest ) ) {
					$out_buf = $this->minifyJS( $src );
					if( false === file_put_contents( $dest, $out_buf) && YII_ENV_DEV ) {
						throw new Exception( 'alexshul/optimizer: can\'t write to file "' . $dest . '"' );
					} else {
						$this->cache->changeVersion();
					}				
				}

			} else if ( YII_ENV_DEV ) {
				throw new Exception( 'alexshul/optimizer: unknow type of asset with destination "' . $dest . '"' );
			}

		}
	}

	public function clearLinks() {
        //var_dump( Yii::$app->getResponse()->data );
		//exit;
		
		//Yii::$app->getResponse()->data = 'www';
    }

	public function addLoader() {
		$loader = new AssetLoader( $this->assetsToWatch );
		$loader->printTo( Yii::$app->response->data );		
    }

	
	
	public function minifyCSS($files = array()) {
		$input_css = NULL;

		foreach($files as $file) {
			if( !file_exists( $file ) )
				continue;

			// Extract the CSS code you want to compress from your CSS files
			$input_css .= file_get_contents($file);
		}

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

	public function minifyJS($files = array()) {		
		$minifier = new MatthiasMullie\Minify\JS;		
		
		foreach ( $files as $file ) {
			if( !file_exists( $file ) )
				continue;
				
			$minifier->add($file);
		}
		
		return $minifier->minify();
	}

	public function combineFiles($files = array()) {
		$buf = null;

		foreach($files as $file) {			
			$buf .= file_get_contents($file);
		}

		return $buf;
	}

	public function getVersion() {
		return $this->cache->get( 'version' );
	}
}