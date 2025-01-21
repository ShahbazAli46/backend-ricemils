<?php

use App\Http\Controllers\{AuthController, BankController, BuyerController, SupplierController, ExpenseCategoryController, ExpenseController, PackingController, PaymentInFlowController, ProductStockController,ProductController,PaymentOutFlowController, PurchaseBookController, SaleBookController, SupplierLedgerController,BuyerLedgerController, AdvanceChequeController, CompanyLedgerController, DashboardController, InvestorController, InvestorLedgerController};
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

Route::post('login', [AuthController::class,'login']);

Route::middleware('auth:sanctum')->group( function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::apiResource('supplier', SupplierController::class);
    Route::post('/supplier/{id}', [SupplierController::class, 'update']);
    
    Route::apiResource('buyer', BuyerController::class);
    Route::post('/buyer/{id}', [BuyerController::class, 'update']);
    
    Route::apiResource('investor', InvestorController::class);
    Route::apiResource('investor_ledger', InvestorLedgerController::class);
    Route::get('/received_investor_amount', [InvestorLedgerController::class, 'receivedInvestorAmount']);
    // Route::post('/investor_ledger/{id}', [InvestorLedgerController::class, 'update']);

    Route::apiResource('product', ProductController::class);
    Route::post('/product/{id}', [ProductController::class, 'update']);
    
    Route::apiResource('packing', PackingController::class);
    Route::post('/packing/{id}', [PackingController::class, 'update']);
    
    Route::apiResource('product_stock', ProductStockController::class);
    Route::post('/product_stock/{id}', [ProductStockController::class, 'update']);
    
    Route::apiResource('bank', BankController::class);
    Route::post('/bank/{id}', [BankController::class, 'update']);
    Route::get('/bank/transection/detail/{id}', [BankController::class, 'bankTransectionDetail']);

    
    Route::apiResource('expense_category', ExpenseCategoryController::class);
    Route::post('/expense_category/{id}', [ExpenseCategoryController::class, 'update']);
    
    Route::apiResource('expense', ExpenseController::class);
    Route::post('/expense/{id}', [ExpenseController::class, 'update']);
    
    Route::apiResource('purchase_book', PurchaseBookController::class);
    Route::post('/purchase_book/{id}', [PurchaseBookController::class, 'update']);
    
    Route::apiResource('supplier_ledger', SupplierLedgerController::class);
    Route::get('/get_supplier_paid_amount', [SupplierLedgerController::class, 'getSupplierPaidAmount']);
    Route::post('/supplier_ledger/{id}', [SupplierLedgerController::class, 'update']);

    Route::get('/sale_book/get_next_ref_no', [SaleBookController::class, 'getNextRefNo']);
    Route::post('/sale_book/add_item', [SaleBookController::class, 'AddItem']);
    Route::get('/sale_book/remove_item/{id}', [SaleBookController::class, 'RemoveItem']);
    Route::get('/sale_book/clear_items/{id}', [SaleBookController::class, 'ClearItems']);

    Route::apiResource('sale_book', SaleBookController::class);
    Route::post('/sale_book/{id}', [SaleBookController::class, 'update']);


    Route::apiResource('buyer_ledger', BuyerLedgerController::class);
    Route::get('/received_buyer_amount', [BuyerLedgerController::class, 'receivedBuyerAmount']);
    Route::post('/buyer_ledger/{id}', [BuyerLedgerController::class, 'update']);

    Route::apiResource('advance_cheque', AdvanceChequeController::class);
    Route::post('/advance_cheque/{id}', [AdvanceChequeController::class, 'update']);
    Route::get('/advance_cheque/is_deferred/{id}/{value}', [AdvanceChequeController::class, 'changeStatus'])->where('value', '0|1');
    
    Route::get('/dr/api', [DashboardController::class, 'drApi']);
    Route::get('/cr/api', [DashboardController::class, 'crApi']);

    Route::apiResource('company_ledger', CompanyLedgerController::class);
});


