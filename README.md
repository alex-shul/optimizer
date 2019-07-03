# yii2-optimizer
> **Help is welcome!** 
The project is under development, so you can commit to the master branch. You can also write about errors found in "issues".

Optimization tool for CSS &amp; JS files, created as Yii2 extension. Refactoring in progress...

## Install

#### Using composer:
1) Edit file composer.json in the root directory of your yii2 app ("basic" or "advanced" folder), adding following lines:
```
    ...
    "require": {
        ...
        "alex-shul/yii2-optimizer": "*"        
    }
    ...
    "repositories": [        
        ...
        {
            "type": "git",
            "url": "https://github.com/alex-shul/yii2-optimizer.git"
        }       
    ]
    ...
```
2) Run composer command `update` in app root folder.

## Configure

1) Open config file `basic/config/web.php` or `advanced/common/config/main.php`.
2) Add parameters for extension to the `components` section of config. Example:
```
    'components' => [
        ...
        'optimizer' => [
            'class' => 'alexshul\optimizer\Module',
            'assetsClearStyles' => false,
            'assetsClearScripts' => false,
	          'assetsAddLoader' => true,
            'assetsToWatch' => [
                'styles' => [
                    'src' => [
                        'assets/css/common.css',
                        'assets/css/media.css'
                    ],
                    'dest' => 'web/assets/styles.min.css'                    
                ],
                'Promise fallback' => [ 
                    'condition' => 'typeof Promise !== \'function\'',          
                    'dest' => 'web/assets/fallbacks/promise.min.js'                                 
                ],
                'fetch fallback' => [ 
                    'condition' => 'typeof fetch !== \'function\'',          
                    'dest' => 'web/assets/fallbacks/fetch.umd.js'                                        
                ],
                'scripts' => [
                    'src' => [
                        'assets/js/common.js'                        
                    ],
                    'dest' => 'web/assets/scripts.min.js'                  
                ],
                'font' => [
                    'dest' => 'https://fonts.googleapis.com/css?family=Yanone+Kaffeesatz&display=swap&subset=cyrillic',
                    'type' => 'link'
                ]
            ],
        ],
    ],
```

## Parameters

Extension has options:
- `assetsClearStyles` (bool)
- `assetsClearScripts` (bool)
- `assetsAddLoader` (bool)
- `assetsToWatch` (bool)
...
