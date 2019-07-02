<?php
/**
 * @link https://github.com/alex-shul/yii2-optimizer
 * @author Alex Shul
 * @license MIT
 */

require './vendor/autoload.php';

namespace alexshul\optimizer;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\Application;
use tubalmartin\CssMin\Minifier as CSSmin;

class Optimizer implements BootstrapInterface {

	public  $filesToWatch = array();

	private $options = null;
	private $iniFileName = Yii::getAlias('@runtime') . 'alex-shul/optimizer/cache.ini';

	function __construct() {		
	}

	public function bootstrap($app)
    {
        $app->on(Application::EVENT_BEFORE_REQUEST, function () {
             $this->run();
        });
    }

	public function run () {
		var_dump( $this->$filesToWatch );
		return;	
		
		$this->checkOptions();
		//list($msec, $sec) = explode(chr(32), microtime());
		//define( 'START_TIME', $sec + $msec );
		$log_out = "";


		$sections = array_keys( $this->options );

		//echo '<pre>' . print_r( $opt, true ) . '</opt>';
		//echo '<pre>' . print_r( $sections, true ) . '</opt>';		
		
		foreach( $sections as $bundle ) {
			//-----------------------------
			//	Process CSS bundles
			//-----------------------------
			if( strpos( $bundle, '.css' ) !== FALSE ) {
				$in_css = array_values( $this->options[$bundle] );
				$in_css_latest = 0;				
				foreach( $in_css as $key => $path_end ) {
					$in_css[$key] = $this->getOption( 'path_css' ) . $path_end;
					$in_css_latest = max( filemtime( $in_css[$key] ), $in_css_latest );
				}

				$out_css = $this->getOption( 'path_css' ) . $bundle;			
				
				if( !file_exists( $out_css ) || $in_css_latest > filemtime( $out_css ) ) {
					$out_buf = $this->minifyCss($in_css);
					$result = file_put_contents($out_css, $out_buf);
					$this->changeVersion();					
					$log_out .= '<br><br>Minified ' . $out_css . '!!!<br><br>';
				} else {
					$log_out .= '<br><br>Minifying not need for ' . $out_css . '.<br><br>';
				}
			//-----------------------------
			//	Process JS bundles
			//-----------------------------			
			} else if( strpos( $bundle, '.js' ) !== FALSE ) {
				$in_js = array_values( $this->options[$bundle] );
				$in_js_latest = 0;				
				foreach( $in_js as $key => $path_end ) {
					$in_js[$key] = $this->getOption( 'path_js' ) . $path_end;
					$in_js_latest = max( filemtime( $in_js[$key] ), $in_js_latest );
				}

				$out_js = $this->getOption( 'path_js' ) . $bundle;			
				
				if( !file_exists( $out_js ) || $in_js_latest > filemtime( $out_js ) ) {
					$out_buf = $this->minifyJSNew($in_js);
					$result = file_put_contents($out_js, $out_buf);
					$this->changeVersion();					
					$log_out .= '<br><br>Minified ' . $out_js . '!!!<br><br>';
				} else {
					$log_out .= '<br><br>Minifying not need for ' . $out_js . '.<br><br>';
				}				
			}

		}
		//echo '<h1>'.round(microtime(true)-START_TIME,4).'</h1>';

		define( 'MINIFY_SYSTEM_MSG', $log_out );
		define( 'MINIFY_SYSTEM_FILES_VERSION', $this->getOption( 'version' ) );
		
	}

	protected function checkOptions() {
		if( !$this->options ) {			
			$this->options = parse_ini_file( $this->iniFileName, true, INI_SCANNER_TYPED );
		}		
	}

	protected function getOption( $optionName ) {
		$this->checkOptions();
		return ( $optionName && isset( $this->options['options'][$optionName] ) ? $this->options['options'][$optionName] : '' );
	}

	protected function changeVersion() {
		if( !isset( $this->options['options']['version'] ) ) {
			$this->options['options']['version'] = 1;
		}
		$this->options['options']['version']++;
		if( $this->options['options']['version'] > 999998 ) {
			$this->options['options']['version'] = 1;
		}
		define( 'CSS_AND_JS_FILES_VERSION', $this->options['options']['version'] );		
		$this->writeIniFile( $this->iniFileName, $this->options );
	}

	public function writeIniFile($filename, $sectionsarray) {

		$content = $this->buildOutputString($sectionsarray);
        if (false === file_put_contents($filename, $content)) {
            throw new Exception(
                sprintf(
                    'failed to write file `%s\' for writing.', $filename
                )
            );
        }
        return true;
	}

	protected function buildOutputString($sectionsarray)
    {
        $content = '';
        $sections = '';
		$globals  = '';
		$linebreak = "\r\n";
        if (!empty($sectionsarray)) {
            // 2 loops to write `globals' on top, alternative: buffer
            foreach ($sectionsarray as $section => $item) {
                if (!is_array($item)) {
                    $value    = $item;
                    $globals .= $section . ' = ' . $value . $linebreak;
                }
            }
            $content .= $globals;
            foreach ($sectionsarray as $section => $item) {
                if (is_array($item)) {
                    $sections .= $linebreak
                                . "[" . $section . "]" . $linebreak;
                    foreach ($item as $key => $value) {
                        if (is_array($value)) {
                            foreach ($value as $arrkey => $arrvalue) {
                                $arrvalue  = $arrvalue;
                                $arrkey    = $key . '[' . $arrkey . ']';
                                $sections .= $arrkey . ' = ' . $arrvalue
                                            . $linebreak;
                            }
                        } else {
                            $value     = $value;
                            $sections .= $key . ' = ' . $value . $linebreak;
                        }
                    }
                }
            }
            $content .= $sections;
        }
        return $content;
	}
	
	public function minifyCss($files = array()) {

		$input_css = NULL;

		foreach($files as $file) {
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

	public function minifyJSNew($files = array()) {
		
		$minifier = new MatthiasMullie\Minify\JS;		
		
		foreach ( $files as $file ) {
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
}