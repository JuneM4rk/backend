     <?php
     require __DIR__ . '/../vendor/autoload.php';
     $app = require __DIR__ . '/../bootstrap/app.php';
     $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
     $kernel->call('route:clear');
     $kernel->call('config:clear');
     $kernel->call('cache:clear');
     echo "done";