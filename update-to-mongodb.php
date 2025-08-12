<?php
/**
 * Update all files to use MongoDB instead of SQLite
 * This script will replace database.php references with mongodb-database.php
 */

echo "🔄 Updating Discord Broadcaster Pro to use MongoDB...\n\n";

// Files to update
$filesToUpdate = [
    'index.php',
    'broadcast.php', 
    'wallet.php',
    'admin-access.php',
    'payment-checker.php',
    'debug-admin.php',
    'make-admin.php'
];

$replacements = [
    // Replace database class inclusion
    "require_once 'database.php';" => "require_once 'mongodb-database.php';",
    
    // Replace class instantiation
    '$db = new Database();' => '$db = new MongoDatabase();',
    'new Database()' => 'new MongoDatabase()',
    
    // Replace any remaining Database references
    'Database()' => 'MongoDatabase()'
];

$updatedFiles = 0;
$totalReplacements = 0;

foreach ($filesToUpdate as $file) {
    if (!file_exists($file)) {
        echo "⚠️  File not found: {$file}\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $fileReplacements = 0;
    
    foreach ($replacements as $search => $replace) {
        $newContent = str_replace($search, $replace, $content);
        if ($newContent !== $content) {
            $fileReplacements += substr_count($content, $search);
            $content = $newContent;
        }
    }
    
    if ($content !== $originalContent) {
        if (file_put_contents($file, $content)) {
            echo "✅ Updated {$file} ({$fileReplacements} replacements)\n";
            $updatedFiles++;
            $totalReplacements += $fileReplacements;
        } else {
            echo "❌ Failed to update {$file}\n";
        }
    } else {
        echo "ℹ️  No changes needed for {$file}\n";
    }
}

echo "\n📊 Summary:\n";
echo "- Files updated: {$updatedFiles}\n";
echo "- Total replacements: {$totalReplacements}\n";

// Create backup of old database.php
if (file_exists('database.php')) {
    if (copy('database.php', 'database-sqlite-backup.php')) {
        echo "✅ Created backup: database-sqlite-backup.php\n";
    }
}

echo "\n🎉 MongoDB migration completed!\n";
echo "📝 Next steps:\n";
echo "1. Install MongoDB PHP driver: composer install\n";
echo "2. Test the connection with: php test-mongodb.php\n";
echo "3. Deploy to Railway\n";
?>
