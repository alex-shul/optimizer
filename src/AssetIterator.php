<?php 

namespace alexshul\optimizer;
use Yii;

class AssetIterator implements \Iterator {

	private $position = 0;
    private $assets = [];

    const UNKNOWN = 0;
	const LINK 	  = 1;
	const SCRIPT  = 2;    	


    /**
     *  Construct with given data array
     */
    public function __construct( array $assetsToWatch = [] ) {
        foreach( $assetsToWatch as $assetName => $assetOptions ) {
            $assetOptions['name'] = $assetName;
            $this->push( $assetOptions );            
        }
        $this->position = 0;         
    }




    /**
     *  Returns element with current position
     */
    public function &current() {        
        return $this->assets[$this->position];
    }
    /**
     *  Returns current position
     */
    public function key() {
        return $this->position;
    }
    /**
     *  Moves current position forward by 1
     *  NOTE: This function is invoked on each step of the iteration
     */
    public function next() 
    {
        ++$this->position;
        //return $this->assets[$this->position];        
    }
    /**
     *  Resets position
     */
    public function rewind() {
        $this->position = 0;
        //return $this->assets[$this->position];
    }
    /**
     *  Validates element at current position
     */
    public function valid() {
        return isset( $this->assets[$this->position] );
    }



    /**
     *  Pushes element at the end
     */
    public function push( $element ) {
        $default = array(
            'name' => 'default',
            'src' => [],
            'dest' => '',
		    'autoload' => false,
		    'condition' => '',
		    'type' => '',
		    'assetType' => self::UNKNOWN,
		    'version' => 1
        );        
        $count = array_push( $this->assets, array_merge( $default, $element ) ); 
        $this->validateAssetType( $count - 1 );//Yii::debug( print_r( $this->assets ) );
        return $count;       
    }
    /**
     *  Validates asset type from file extension or from type 'option'
     */
    public function validateAssetType( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        
        if( strpos( $this->assets[$index]['dest'], '.css' ) !== FALSE || $this->assets[$index]['type'] === 'link' ) {
            $this->assets[$index]['assetType'] = self::LINK;
        } else if( strpos( $this->assets[$index]['dest'], '.js' ) !== FALSE || $this->assets[$index]['type'] === 'script' ) {
            $this->assets[$index]['assetType'] = self::SCRIPT;
        } else {
            if ( YII_ENV_DEV ) throw new Exception( 'alexshul/optimizer: asset type not detected for asset with destination "' . $this->assets[$index]['dest'] . '"' );            
        }
        return true;
    }




    /**
     *  Returns destionation
     */
    public function &dest( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return $this->assets[$index]['dest'];
    }
    /**
     *  Checks if destionation is set
     */
    public function hasDest( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return strlen( $this->assets[$index]['dest'] );
    }
    /**
     *  Extends destination with base path
     */
    public function extendDest( $base, $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
           
        return ( $this->assets[$index]['dest'] = $base . $this->assets[$index]['dest'] );
    }
    /**
     *  Checks if asset is not local resource (will be loaded from CDN)
     */
    public function fromCDN( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return ( filter_var( $this->assets[$index]['dest'], FILTER_VALIDATE_URL ) !== FALSE );
    }
    



    /**
     *  Returns source files array
     */
    public function &src( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return $this->assets[$index]['src'];
    }
    /**
     *  Checks if destionation is set
     */
    public function hasSrc( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return count( $this->assets[$index]['src'] );
    }
    /**
     *  Extends each source file with base path
     */
    public function extendSrc( $base, $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;

        foreach( $this->assets[$index]['src'] as &$srcFile ) {
            $srcFile = $base . $srcFile;
        }
    }




    /**
     *  Returns asset name
     */
    public function &name( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return $this->assets[$index]['name'];
    }

    /**
     *  Determines if asset type is script
     */
    public function isScript( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;

        return ( $this->assets[$index]['assetType'] === self::SCRIPT );
    }




    /**
     *  Returns asset version
     */
    public function &version( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return $this->assets[$index]['version'];
    }
    /**
     *  Set asset version
     */
    public function setVersion( $version = 1, $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return $this->assets[$index]['version'] = $version;
    }



    
    /**
     *  Checks if asset option is set
     */
    public function hasOption( string $option, $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return isset( $this->assets[$index][$option] );
    }
    /**
     *  Returns asset option if set
     */
    public function getOption( string $option, $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return isset( $this->assets[$index][$option] ) ? $this->assets[$index][$option] : FALSE;
    }
    /**
     *  Returns asset options
     */
    public function &options( $index = -1 ) {
        if( $index < 0 )
            $index = $this->position;
        return $this->assets[$index];
    }
}

