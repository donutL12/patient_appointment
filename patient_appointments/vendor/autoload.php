<?php
/**
 * Manual Autoloader for PHPMailer
 * Use this if you installed PHPMailer manually
 */

// Define the base directory
$baseDir = __DIR__ . '/phpmailer/phpmailer/src/';
//vendor/autoload.php
// Register autoloader
spl_autoload_register(function ($class) use ($baseDir) {
    // Check if the class is part of PHPMailer namespace
    if (strpos($class, 'PHPMailer\\PHPMailer\\') === 0) {
        // Remove namespace prefix
        $class = str_replace('PHPMailer\\PHPMailer\\', '', $class);
        
        // Build file path
        $file = $baseDir . $class . '.php';
        
        // Include file if it exists
        if (file_exists($file)) {
            require $file;
        }
    }
});
?>