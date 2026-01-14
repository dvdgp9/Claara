<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../src/App/bootstrap.php';

use App\Session;
use Repos\UserFeatureAccessRepo;
use Sop\SopGenerator;
use Sop\AudioTranscriber;
use Sop\ImageDescriber;
use Utils\DocumentGenerator;

echo "<!DOCTYPE html><html><head><title>Debug SOP</title></head><body>";
echo "<h1>Debug SOP Generator</h1>";
echo "<pre>";

try {
    echo "1. Bootstrap cargado OK\n\n";
    echo "2. Uses declarados OK\n\n";
    
    echo "3. Obteniendo usuario...\n";
    $user = Session::user();
    echo "✅ Usuario: " . ($user ? $user['email'] : 'NO LOGUEADO') . "\n\n";
    
    echo "4. Creando UserFeatureAccessRepo...\n";
    $accessRepo = new UserFeatureAccessRepo();
    echo "✅ UserFeatureAccessRepo OK\n\n";
    
    if ($user) {
        echo "5. Verificando acceso al gesto...\n";
        $hasAccess = $accessRepo->hasGestureAccess((int)$user['id'], 'sop-generator');
        echo "✅ Acceso al gesto: " . ($hasAccess ? 'SÍ' : 'NO') . "\n\n";
    }
    
    echo "6. Creando SopGenerator...\n";
    $generator = new SopGenerator();
    echo "✅ SopGenerator OK\n\n";
    
    echo "7. Creando AudioTranscriber...\n";
    $transcriber = new AudioTranscriber();
    echo "✅ AudioTranscriber OK\n\n";
    
    echo "8. Creando ImageDescriber...\n";
    $describer = new ImageDescriber();
    echo "✅ ImageDescriber OK\n\n";
    
    echo "9. Creando DocumentGenerator...\n";
    $docGen = new DocumentGenerator();
    echo "✅ DocumentGenerator OK\n\n";
    
    echo "=================================\n";
    echo "✅ TODOS LOS TESTS PASARON\n";
    echo "=================================\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR CAPTURADO:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
} catch (\Error $e) {
    echo "\n❌ ERROR FATAL:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "</body></html>";
