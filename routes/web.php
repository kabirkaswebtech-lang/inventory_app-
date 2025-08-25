<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CsvController;
use App\Http\Controllers\ExcelController;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return response(view('check_csv'))
        ->header('Access-Control-Allow-Origin', '*');
})->middleware(['auth.shopify'])->name('home');

Route::post('/test', [ProductController::class, 'test'])->name('test');

Route::post('/upload-file', [ProductController::class, 'uploadFile'])->name('file.upload');

Route::get('/check', function () {
    return view('check_csv');
});





Route::post('/excel/upload', [CsvController::class, 'upload'])->name('excel.upload');

Route::post('/excel-from-sharepoint', [ExcelController::class, 'downloadFromSharePoint'])
    ->name('excel.from.sharepoint');

Route::post('/excel-from-dropbox', [ExcelController::class, 'downloadFromDropBox'])
    ->name('excel.from.dropbox');

Route::get('/update-price/{sku}/{price}', [ExcelController::class, 'updatePriceBySku']);

Route::post('/shopify/prices-update', [ExcelController::class, 'batchUpdateShopifyPrices'])->middleware(['auth.shopify']);
Route::post('/shopify/inventory', [ExcelController::class, 'updateInventoryFromAjax']);
// Route::match(['get', 'post'], '/Updatepriceset', [ExcelController::class, 'updateFromSharePointset']);
// Route::match(['get', 'post'], '/Updateinventoryset', [ExcelController::class, 'updateInventoryFromDropBox']);
Route::get('/Updatepriceset', [ExcelController::class, 'updateFromSharePointset']);
Route::get('/Updateinventoryset', [ExcelController::class, 'updateFromSharePointset']);
Route::get('/migrate', function () {
      Artisan::call('migrate');
      return "migration  dddd successfully";
   });

   Route::get('/run-cron', function () {
    Artisan::call('schedule:run'); // Replace with your command name
    return "Cron job executed!";
});


Route::get('/clear-all', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    
    return response()->json([
        'status' => 'success',
        'message' => 'Cache, config, route and view cleared successfully!'
    ]);
});