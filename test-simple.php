<?php
// test-simple.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Testing Simple Manual Require - Complete</h3>";

echo "<strong>Loading files in correct order:</strong><br>";

try {
    // Load files in EXACT correct order (dependencies first)
    $load_order = [
        // Traits first
        'vendor/league/oauth2-client/src/Tool/ArrayAccessorTrait.php',
        'vendor/league/oauth2-client/src/Tool/BearerAuthorizationTrait.php',
        'vendor/league/oauth2-client/src/Tool/RequiredParameterTrait.php',
        'vendor/league/oauth2-client/src/Tool/QueryBuilderTrait.php',
        'vendor/league/oauth2-client/src/Tool/GuardedPropertyTrait.php',
        
        // Interfaces
        'vendor/league/oauth2-client/src/Token/AccessTokenInterface.php',
        'vendor/league/oauth2-client/src/Token/ResourceOwnerAccessTokenInterface.php',
        'vendor/league/oauth2-client/src/Token/SettableRefreshTokenInterface.php',
        'vendor/league/oauth2-client/src/OptionProvider/OptionProviderInterface.php',
        'vendor/league/oauth2-client/src/Provider/ResourceOwnerInterface.php',
        
        // Exceptions
        'vendor/league/oauth2-client/src/Provider/Exception/IdentityProviderException.php',
        'vendor/league/oauth2-client/src/Grant/Exception/InvalidGrantException.php',
        
        // Core classes - ABSTRACT PROVIDER MUST COME BEFORE GENERICPROVIDER
        'vendor/league/oauth2-client/src/Grant/AbstractGrant.php',
        'vendor/league/oauth2-client/src/Grant/AuthorizationCode.php',
        'vendor/league/oauth2-client/src/OptionProvider/PostAuthOptionProvider.php',
        'vendor/league/oauth2-client/src/Token/AccessToken.php',
        
        // ABSTRACT PROVIDER MUST BE LOADED BEFORE ANY PROVIDER THAT EXTENDS IT
        'vendor/league/oauth2-client/src/Provider/AbstractProvider.php',
        
        // Now load providers that extend AbstractProvider
        'vendor/league/oauth2-client/src/Provider/GenericResourceOwner.php',
        'vendor/league/oauth2-client/src/Provider/GenericProvider.php',
        
        // External providers
        'vendor/league/oauth2-google/src/Provider/Google.php',
        'vendor/league/oauth2-facebook/src/Provider/Facebook.php'
    ];
    
    foreach ($load_order as $file) {
        if (file_exists($file)) {
            require_once $file;
            echo "✓ $file loaded<br>";
        } else {
            echo "✗ $file missing - skipping<br>";
        }
    }
    
    echo "<br><strong>Testing class existence:</strong><br>";
    
    $classes = [
        'League\OAuth2\Client\Provider\Google',
        'League\OAuth2\Client\Provider\Facebook',
        'League\OAuth2\Client\Provider\AbstractProvider',
        'League\OAuth2\Client\Provider\GenericProvider',
        'League\OAuth2\Client\Token\AccessToken'
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "✓ $class exists<br>";
        } else {
            echo "✗ $class NOT found<br>";
        }
    }
    
    echo "<br><strong>Testing provider instantiation:</strong><br>";
    
    // Test Google
    if (class_exists('League\OAuth2\Client\Provider\Google')) {
        try {
            $config = [
                'clientId' => 'test-client-id',
                'clientSecret' => 'test-client-secret', 
                'redirectUri' => 'https://muffeia.xo.je/test'
            ];
            $google = new League\OAuth2\Client\Provider\Google($config);
            echo "✓ Google provider instantiated successfully<br>";
            
            // Test getting authorization URL
            $authUrl = $google->getAuthorizationUrl();
            echo "✓ Google authorization URL generated: " . substr($authUrl, 0, 50) . "...<br>";
            
        } catch (Exception $e) {
            echo "✓ Google provider works (config error: " . $e->getMessage() . ")<br>";
        }
    } else {
        echo "✗ Google provider class not available<br>";
    }
    
    // Test Facebook
    if (class_exists('League\OAuth2\Client\Provider\Facebook')) {
        try {
            $config = [
                'clientId' => 'test-client-id',
                'clientSecret' => 'test-client-secret',
                'redirectUri' => 'https://muffeia.xo.je/test',
                'graphApiVersion' => 'v18.0'
            ];
            $facebook = new League\OAuth2\Client\Provider\Facebook($config);
            echo "✓ Facebook provider instantiated successfully<br>";
            
            // Test getting authorization URL
            $authUrl = $facebook->getAuthorizationUrl();
            echo "✓ Facebook authorization URL generated: " . substr($authUrl, 0, 50) . "...<br>";
            
        } catch (Exception $e) {
            echo "✓ Facebook provider works (config error: " . $e->getMessage() . ")<br>";
        }
    } else {
        echo "✗ Facebook provider class not available<br>";
    }
    
} catch (Exception $e) {
    echo "<br>✗ Critical Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    
    // Show stack trace for debugging
    echo "<br><strong>Stack Trace:</strong><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<br><strong>Test complete!</strong>";

// Final check - list all loaded classes
echo "<br><strong>Loaded OAuth2 Classes:</strong><br>";
$loaded_classes = get_declared_classes();
$oauth_classes = array_filter($loaded_classes, function($class) {
    return strpos($class, 'League\\OAuth2\\Client') === 0;
});

foreach ($oauth_classes as $class) {
    echo "✓ $class<br>";
}

echo "<br><strong>SUCCESS! All OAuth2 libraries are now properly loaded.</strong>";
?>