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
//use MatthiasMullie\Minify\JS as JSmin;
use \JShrink\Minifier;
use yii\console\Exception;
use alexshul\optimizer\Cache as Cache;
use alexshul\optimizer\AssetLoader as AssetLoader;

class Module extends \yii\base\Module implements BootstrapInterface {

	public  $assetsToWatch = array();
	public  $assetsClearStyles = false;
	public  $assetsClearScripts = false;
	public  $assetsAddLoader = false;
	public  $assetsMinifyLoader = false;

	private $cache = null;	
	private $basePath = null;
	private $webPath = null;

	function __construct() {
		$this->cache = new Cache;	
		$this->basePath = Yii::getAlias('@app') . '/';	
		$this->webPath = Yii::getAlias('@webroot') . '/';		
	}	

	public function bootstrap($app) {
		Yii::debug('Optimizer bootstrap!');
        $app->on(Application::EVENT_AFTER_REQUEST, function () {	
			Yii::debug('Optimizer after request!');		
			$this->run();
			Yii::debug('Optimizer finished!');		
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
			Yii::debug($this->assetsAddLoader);
	}

	public function checkSourceFiles() {
		foreach( $this->assetsToWatch as $asset ) {
			//	Break if destination not set
			//		OR
			//	Break if external url given, i. e. "https://fonts.googleapis.com/css?family=..."
			if( !isset( $asset['dest'] ) || filter_var( $asset['dest'], FILTER_VALIDATE_URL ) !== FALSE )
				continue;

			$src = is_array( $asset['src'] ) ? $asset['src'] : array();
			$dest = $this->webPath . $asset['dest'];
			
			//-----------------------------
			//	Process CSS assets
			//-----------------------------
			if( strpos( $dest, '.css' ) !== FALSE || ( is_string( $asset['type'] ) && $asset['type'] === 'link' ) ) {

				$css_latest = 0;		
				foreach( $src as $key => $path ) {
					$src[$key] = $this->basePath . $path;

					if( !file_exists( $src[$key] ) )
						continue;

					$css_latest = max( filemtime( $src[$key] ), $css_latest );
				}						
				
				if( count( $src ) && ( !file_exists( $dest ) || $css_latest > filemtime( $dest ) ) ) {
					$out_buf = $this->minifyCSS( $src );
					if( false === file_put_contents( $dest, $out_buf) && YII_ENV_DEV ) {
						throw new Exception( 'alexshul/optimizer: can\'t write to file "' . $dest . '"' );
					} else {
						$this->cache->changeAssetsVersion();
					}								
				}

			//-----------------------------
			//	Process JS assets
			//-----------------------------			
			} else if( strpos( $dest, '.js' ) !== FALSE || ( is_string( $asset['type'] ) && $asset['type'] === 'script' ) ) {

				$js_latest = 0;		
				foreach( $src as $key => $path ) {
					$src[$key] = $this->basePath . $path;

					if( !file_exists( $src[$key] ) )
						continue;

					$js_latest = max( filemtime( $src[$key] ), $js_latest );
				}						
				
				if( count( $src ) && ( !file_exists( $dest ) || $js_latest > filemtime( $dest ) ) ) {
					$out_buf = $this->minifyJS( $src );
					if( false === file_put_contents( $dest, $out_buf) && YII_ENV_DEV ) {
						throw new Exception( 'alexshul/optimizer: can\'t write to file "' . $dest . '"' );
					} else {
						$this->cache->changeAssetsVersion();
					}				
				}

			} else if ( YII_ENV_DEV ) {
				throw new Exception( 'alexshul/optimizer: unknow type of asset with destination "' . $dest . '"' );
			}

		}
	}

	public function clearLinks() {
		//	Not released yet...
    }

	public function addLoader() {		
		$script = $this->cache->getLoaderScript();
		Yii::debug($script);
		if( $script === false ) {
			$loader = new AssetLoader( $this->assetsToWatch );			 
			$script = $loader->generateScript( $this->getAssetsVersion() );			

			if( $this->assetsMinifyLoader )
				$script = $this->minifyJS( $script );

			$this->cache->saveLoaderScript( $script );
		}

		Yii::$app->getView()->registerJs( $script, POS_END, 'loader' );
		//Yii::$app->response->data = str_replace( '</body>', "\r\n<script>\r\n" . $script . "\r\n</script>\r\n</body>", Yii::$app->response->data );				
    }
	
	public function minifyCSS( $input = array() ) {
		$input_css = '';	
		
		if( is_array( $input ) ) {
			$input_css = $this-> combineFiles( $input );			
		} else if( is_string( $input ) ) {
			$input_css = $input;
		} else {
			if( YII_ENV_DEV ) throw new Exception('yii2-optimizer::minifyCSS - invalid input format.');
			return $input_css;
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

	public function minifyJS( $input = array() ) {		
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

	public function combineFiles( $files = array() ) {
		$buf = null;

		foreach($files as $file) {			
			$buf .= file_get_contents($file);
		}

		return $buf;
	}

	public function getAssetsVersion() {
		return $this->cache->get( 'version' );
	}
}