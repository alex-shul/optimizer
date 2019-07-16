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
     *  Resets position
     */
    public function __construct( array $assetsToWatch = [] ) {
        foreach( $assetsToWatch as $assetName => $assetOptions ) {
            $assetOptions['name'] = $assetName;
            $this->push( $assetOptions );            
        }         
    }

    /**
     *  Returns element with current position
     */
    public function current() {
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
        return $this->assets[$this->position];        
    }
    /**
     *  Resets position
     */
    public function rewind() {
        $this->position = 0;
        return $this->assets[$this->position];
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
     *  Pushes element at end
     */
    public function validateAssetType( $index = -1 ) {
        if( $index === -1 )
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
     *  Pushes element at end
     */
    public function validateDestination( $index = -1 ) {
        if( $index === -1 )
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
     *  Pushes element at end
     */
    public function fromCDN( $index = -1 ) {
        if( $index === -1 )
            $index = $this->position;
        return ( filter_var( $this->assets[$index]['dest'], FILTER_VALIDATE_URL ) !== FALSE );
    }
    /**
     *  Pushes element at end
     */
    public function hasDestination( $index = -1 ) {
        if( $index === -1 )
            $index = $this->position;
        return strlen( $this->assets[$index]['dest'] );
    }
    /**
     *  Pushes element at end
     */
    public function getSrc( $index = -1 ) {
        if( $index === -1 )
            $index = $this->position;
        return $this->assets[$index]['src'];
    }
}

