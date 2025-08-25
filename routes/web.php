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
 Route::get('/migrate', function () {
      Artisan::call('migrate');
      return "migration  dddd successfully";
   });
