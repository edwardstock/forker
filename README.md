# PHP Forker
PHP POSIX process manager and async ProcessPool

## Features
* Easy to create multi-processed daemons
* POSIX Signals dispatching
* Serializing objects/arrays that contains closures (thx to [SuperClosure](https://github.com/jeremeamia/super_closure))
* Uses shared memory
* *.pid file managing

## Usage examples
#### Basic usage
```php
<?php
use edwardstock\forker\handler\CallbackTask;
use edwardstock\forker\ProcessManager;

$updated = 0;

// simple background job
$bigTableUpdate = CallbackTask::create(function(CallbackTask $task) {
    
    return DB::bigQuery('UPDATE very_big_table SET field=TRUE WHERE another=FALSE'); //it's just example
    
})->future(function($updatedCount, CallbackTask $task) use(&$updated) {
    
    $updated = $updatedCount;
    
})->error(function(\Throwable $exception, CallbackTask $task){
    
    // handle exception occurred while DB::bigQuery()
    Logger::log($exception);
    
});

$processManager = new ProcessManager('/path/to/file.pid');
$processManager
    ->add($bigTableUpdate)
    ->run(true) // true - join to main process, if you don't have an expensive and complex logic in future method
    ->wait(); // wait while process will complete doing job
    // if don't call wait() method, process will be detached from terminal or main process and continue to working in background
    
echo $updated; // count of updated very_big_table

```

That was just a very simple example, now more useful

#### Batch usage

```php
<?php
use edwardstock\forker\handler\CallbackTask;
use edwardstock\forker\handler\BatchTask;
use edwardstock\forker\ProcessManager;
$toDownload = [
    'https://google.com',
    'https://facebook.com',
];

$results = [];


/** @var BatchTask $downloads */
$downloads = BatchTask::create($toDownload, function ($toDownloadItem, CallbackTask $task) {

    return @file_get_contents($toDownloadItem);
    
})->future(function ($sitesContent, BatchTask $task) use (&$results) {
    
    $results = $sitesContent;
    
});

$pm = new ProcessManager();
$pm->add($downloads);
$pm->run(true)->wait(); 

var_dump($results); 
// result
// array(2) {
//     0 => string(28) 'html_content_from_google.com'
//     1 => string(30) 'html_content_from_facebook.com'
// }

// Order of results in this case is random, cause, for example,
// facebook.com can be downloaded faster than google.com
```

More examples will soon... ;)
