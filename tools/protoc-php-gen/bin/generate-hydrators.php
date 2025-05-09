<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ProtoPhpGen\Config\StandaloneConfig;
use ProtoPhpGen\Attributes\ProtoMapping;
use ProtoPhpGen\Parser\AttributeParser;
use ProtoPhpGen\Parser\DomainClassScanner;
use ProtoPhpGen\Model\ClassMapping;
use ProtoPhpGen\Generator\StandaloneHydratorGenerator;

// Parse command line arguments
$options = getopt('', [
    'domain-dir:',
    'proto-dir:',
    'output-dir:',
    'domain-namespace:',
    'proto-namespace:',
    'help',
]);

// Show help
if (isset($options['help'])) {
    echo "Hydrator Generator for Proto and Domain classes\n";
    echo "Usage: php generate-hydrators.php [options]\n";
    echo "Options:\n";
    echo "  --domain-dir=<dir>          Directory with domain classes (default: src/Domain)\n";
    echo "  --proto-dir=<dir>           Directory with proto classes (default: protos/gen)\n";
    echo "  --output-dir=<dir>          Output directory (default: gen/Hydrator)\n";
    echo "  --domain-namespace=<ns>     Namespace for domain classes (default: App\\Domain)\n";
    echo "  --proto-namespace=<ns>      Namespace for proto classes (default: App\\Api)\n";
    echo "  --help                      Show this help\n";
    exit(0);
}

// Set up config with default values
$domainDir = 'src/Domain';
if (isset($options['domain-dir']) && is_string($options['domain-dir'])) {
    $domainDir = $options['domain-dir'];
}

$protoDir = 'protos/gen';
if (isset($options['proto-dir']) && is_string($options['proto-dir'])) {
    $protoDir = $options['proto-dir'];
}

$outputDir = 'gen/Hydrator';
if (isset($options['output-dir']) && is_string($options['output-dir'])) {
    $outputDir = $options['output-dir'];
}

$domainNamespace = 'App\\Domain';
if (isset($options['domain-namespace']) && is_string($options['domain-namespace'])) {
    $domainNamespace = $options['domain-namespace'];
}

$protoNamespace = 'App\\Api';
if (isset($options['proto-namespace']) && is_string($options['proto-namespace'])) {
    $protoNamespace = $options['proto-namespace'];
}

$config = new StandaloneConfig(
    $domainDir,
    $protoDir,
    $outputDir,
    $domainNamespace,
    $protoNamespace
);

// Create output directory if it doesn't exist
if (!is_dir($config->getOutputDir())) {
    mkdir($config->getOutputDir(), 0755, true);
}

// Scan domain classes
echo "Scanning domain classes in {$config->getDomainDir()}\n";
$scanner = new DomainClassScanner($config);
$mappings = $scanner->scan();

echo "Found " . count($mappings) . " domain classes with proto mapping\n";

// Generate hydrators
$generator = new StandaloneHydratorGenerator();
$generatedFiles = [];

foreach ($mappings as $mapping) {
    $domainClass = $mapping->getDomainClass();
    $protoClass = $mapping->getProtoClass();
    
    echo "Generating hydrator for {$domainClass} <-> {$protoClass}\n";
    $outputPath = $generator->generate($mapping, $config->getOutputDir());
    $generatedFiles[] = $outputPath;
}

echo "Generation completed. Generated " . count($generatedFiles) . " hydrator classes.\n";
