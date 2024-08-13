<?php

use App\Http\Controllers\{BankController, BuyerController, SupplierController, ExpenseCategoryController, ExpenseController, PackingController, PaymentInFlowController, ProductStockController,ProductController,PaymentOutFlowController, PurchaseBookController, SupplierLedgerController};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::apiResource('supplier', SupplierController::class);
Route::post('/supplier/{id}', [SupplierController::class, 'update']);

Route::apiResource('buyer', BuyerController::class);
Route::post('/buyer/{id}', [BuyerController::class, 'update']);

Route::apiResource('product', ProductController::class);
Route::post('/product/{id}', [ProductController::class, 'update']);

Route::apiResource('packing', PackingController::class);
Route::post('/packing/{id}', [PackingController::class, 'update']);

Route::apiResource('product_stock', ProductStockController::class);
Route::post('/product_stock/{id}', [ProductStockController::class, 'update']);

Route::apiResource('bank', BankController::class);
Route::post('/bank/{id}', [BankController::class, 'update']);


Route::apiResource('expense_category', ExpenseCategoryController::class);
Route::post('/expense_category/{id}', [ExpenseCategoryController::class, 'update']);

Route::apiResource('payment_in', PaymentInFlowController::class);
Route::post('/payment_in/{id}', [PaymentInFlowController::class, 'update']);

Route::apiResource('payment_out', PaymentOutFlowController::class);
Route::post('/payment_out/{id}', [PaymentOutFlowController::class, 'update']);

Route::apiResource('expense', ExpenseController::class);
Route::post('/expense/{id}', [ExpenseController::class, 'update']);

Route::apiResource('purchase_book', PurchaseBookController::class);
Route::post('/purchase_book/{id}', [PurchaseBookController::class, 'update']);

Route::apiResource('supplier_ledger', SupplierLedgerController::class);
Route::post('/supplier_ledger/{id}', [SupplierLedgerController::class, 'update']);

