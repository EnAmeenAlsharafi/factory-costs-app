<?php

use App\Http\Controllers\Api\SearchApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerCreditProfileController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\CustomerProductAliasController;
use App\Http\Controllers\CustomerReturnController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentQueueController;
use App\Http\Controllers\FabricColorController;
use App\Http\Controllers\FinishedGoodsController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\ManufacturingRecipeController;
use App\Http\Controllers\ManufacturingTemplateController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialIssueController;
use App\Http\Controllers\MaterialReceiptController;
use App\Http\Controllers\MaterialReturnController;
use App\Http\Controllers\MaterialSupplierController;
use App\Http\Controllers\MaterialUnitConversionController;
use App\Http\Controllers\PaymentAllocationController;
use App\Http\Controllers\PaymentControlOverrideController;
use App\Http\Controllers\ProcurementPlanningController;
use App\Http\Controllers\ProductConfigurationController;
use App\Http\Controllers\ProductionBoardController;
use App\Http\Controllers\ProductionMaterialRequestController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductionReportController;
use App\Http\Controllers\ProductionReviewController;
use App\Http\Controllers\ProductionReworkController;
use App\Http\Controllers\ProductionRoutingController;
use App\Http\Controllers\ProductionWasteController;
use App\Http\Controllers\ProductModelController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\PurchaseRfqController;
use App\Http\Controllers\QualityIncidentController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReceivablesReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesChannelController;
use App\Http\Controllers\SemiFinishedComponentController;
use App\Http\Controllers\StandardBedSizeController;
use App\Http\Controllers\StockBalanceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierQuotationController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Sadir Furniture Factory
|--------------------------------------------------------------------------
*/

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated Application Routes
Route::middleware('auth')->group(function () {

    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Main Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Users Management
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::resource('users', UserController::class)->except(['destroy', 'show']);

    // Roles and Permissions Management
    Route::resource('roles', RoleController::class)->only(['index', 'show', 'edit', 'update']);

    // Master Data: Customers
    Route::post('/customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
    Route::resource('customers', CustomerController::class)->except(['destroy']);

    // Master Data: Suppliers
    Route::post('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');
    Route::resource('suppliers', SupplierController::class)->except(['destroy']);

    // Master Data: Sales Channels
    Route::post('/sales-channels/{sales_channel}/toggle-status', [SalesChannelController::class, 'toggleStatus'])->name('sales-channels.toggle-status');
    Route::resource('sales-channels', SalesChannelController::class)->except(['show', 'destroy']);

    // Master Data: Customer Types
    Route::post('/customer-types/{customer_type}/toggle-status', [CustomerTypeController::class, 'toggleStatus'])->name('customer-types.toggle-status');
    Route::resource('customer-types', CustomerTypeController::class)->except(['show', 'destroy']);

    // Master Data: Units of Measure
    Route::post('/units/{unit}/toggle-status', [UnitOfMeasureController::class, 'toggleStatus'])->name('units.toggle-status');
    Route::resource('units', UnitOfMeasureController::class)->except(['show', 'destroy']);

    // Master Data: Factory Departments
    Route::post('/departments/{department}/toggle-status', [DepartmentController::class, 'toggleStatus'])->name('departments.toggle-status');
    Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);

    // Master Data: Material Categories
    Route::post('/material-categories/{category}/toggle-status', [MaterialCategoryController::class, 'toggleStatus'])->name('material-categories.toggle-status');
    Route::resource('material-categories', MaterialCategoryController::class)->except(['show', 'destroy']);

    // Master Data: Materials Catalog & Specs
    Route::post('/materials/{material}/toggle-status', [MaterialController::class, 'toggleStatus'])->name('materials.toggle-status');
    Route::resource('materials', MaterialController::class)->except(['destroy']);

    // Material Extensions: Fabric Colors, Suppliers, Unit Conversions
    Route::post('/fabric-colors', [FabricColorController::class, 'store'])->name('fabric-colors.store');
    Route::put('/fabric-colors/{color}', [FabricColorController::class, 'update'])->name('fabric-colors.update');
    Route::delete('/fabric-colors/{color}', [FabricColorController::class, 'destroy'])->name('fabric-colors.destroy');

    Route::post('/material-suppliers', [MaterialSupplierController::class, 'store'])->name('material-suppliers.store');
    Route::delete('/materials/{material}/suppliers/{supplier}', [MaterialSupplierController::class, 'destroy'])->name('material-suppliers.destroy');

    Route::post('/material-conversions', [MaterialUnitConversionController::class, 'store'])->name('material-conversions.store');
    Route::delete('/material-conversions/{conversion}', [MaterialUnitConversionController::class, 'destroy'])->name('material-conversions.destroy');

    // Stage 9 & 10: Work Centers, Production Routing, Orders, WIP, Consumption, Quality & Waste
    Route::prefix('production')->name('production.')->group(function () {
        // Production Orders
        Route::get('/orders', [ProductionOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/create', [ProductionOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [ProductionOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [ProductionOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/release', [ProductionOrderController::class, 'releaseForm'])->name('orders.release.form');
        Route::post('/orders/{order}/release', [ProductionOrderController::class, 'release'])->name('orders.release');
        Route::post('/orders/{order}/hold', [ProductionOrderController::class, 'hold'])->name('orders.hold');
        Route::post('/orders/{order}/resume', [ProductionOrderController::class, 'resume'])->name('orders.resume');
        Route::post('/orders/{order}/cancel', [ProductionOrderController::class, 'cancel'])->name('orders.cancel');

        // Department Queue & Progress Tracking
        Route::get('/queue', [DepartmentQueueController::class, 'index'])->name('queue.index');
        Route::post('/operations/{operation}/progress', [DepartmentQueueController::class, 'updateProgress'])->name('operations.progress');
        Route::post('/operations/{operation}/correct', [DepartmentQueueController::class, 'correctProgress'])->name('operations.correct');

        // Production Routings
        Route::get('/routings', [ProductionRoutingController::class, 'index'])->name('routings.index');
        Route::get('/routings/create', [ProductionRoutingController::class, 'create'])->name('routings.create');
        Route::post('/routings', [ProductionRoutingController::class, 'store'])->name('routings.store');
        Route::get('/routings/{routing}', [ProductionRoutingController::class, 'show'])->name('routings.show');
        Route::post('/routings/{routing}/toggle-status', [ProductionRoutingController::class, 'toggleStatus'])->name('routings.toggle-status');

        // Production Monitoring Board
        Route::get('/board', [ProductionBoardController::class, 'index'])->name('board.index');

        // Stage 10: Material Requests, Quality Incidents, Rework & Waste
        Route::get('/material-requests', [ProductionMaterialRequestController::class, 'index'])->name('material-requests.index');
        Route::get('/material-requests/create', [ProductionMaterialRequestController::class, 'create'])->name('material-requests.create');
        Route::post('/material-requests', [ProductionMaterialRequestController::class, 'store'])->name('material-requests.store');
        Route::get('/material-requests/{materialRequest}', [ProductionMaterialRequestController::class, 'show'])->name('material-requests.show');
        Route::post('/material-requests/{materialRequest}/submit', [ProductionMaterialRequestController::class, 'submit'])->name('material-requests.submit');
        Route::get('/material-requests/{materialRequest}/fulfill', [ProductionMaterialRequestController::class, 'fulfillForm'])->name('material-requests.fulfill.form');
        Route::post('/material-requests/{materialRequest}/fulfill', [ProductionMaterialRequestController::class, 'fulfill'])->name('material-requests.fulfill');

        Route::get('/quality-incidents', [QualityIncidentController::class, 'index'])->name('quality-incidents.index');
        Route::get('/quality-incidents/create', [QualityIncidentController::class, 'create'])->name('quality-incidents.create');
        Route::post('/quality-incidents', [QualityIncidentController::class, 'store'])->name('quality-incidents.store');
        Route::get('/quality-incidents/{qualityIncident}', [QualityIncidentController::class, 'show'])->name('quality-incidents.show');
        Route::post('/quality-incidents/{qualityIncident}/disposition', [QualityIncidentController::class, 'updateDisposition'])->name('quality-incidents.disposition');

        Route::get('/rework', [ProductionReworkController::class, 'index'])->name('rework.index');
        Route::post('/rework', [ProductionReworkController::class, 'store'])->name('rework.store');
        Route::post('/rework/{reworkAction}/complete', [ProductionReworkController::class, 'complete'])->name('rework.complete');

        Route::get('/waste', [ProductionWasteController::class, 'index'])->name('waste.index');
        Route::get('/waste/create', [ProductionWasteController::class, 'create'])->name('waste.create');
        Route::post('/waste', [ProductionWasteController::class, 'store'])->name('waste.store');
        Route::post('/waste/{waste}/approve', [ProductionWasteController::class, 'approve'])->name('waste.approve');

        Route::get('/reports', [ProductionReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/cost', [ProductionReportController::class, 'actualVsPlannedCost'])->name('reports.cost');
        Route::get('/reports/waste', [ProductionReportController::class, 'wasteAnalysis'])->name('reports.waste');
        Route::get('/reports/quality', [ProductionReportController::class, 'qualitySummary'])->name('reports.quality');
    });

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/balances', [StockBalanceController::class, 'index'])->name('balances.index');
        Route::get('/lots/{lot}', [StockBalanceController::class, 'showLot'])->name('lots.show');

        Route::get('/receipts', [MaterialReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/receipts/create', [MaterialReceiptController::class, 'create'])->name('receipts.create');
        Route::post('/receipts', [MaterialReceiptController::class, 'store'])->name('receipts.store');
        Route::get('/receipts/{receipt}', [MaterialReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/receipts/{receipt}/edit', [MaterialReceiptController::class, 'edit'])->name('receipts.edit');
        Route::put('/receipts/{receipt}', [MaterialReceiptController::class, 'update'])->name('receipts.update');
        Route::post('/receipts/{receipt}/post', [MaterialReceiptController::class, 'post'])->name('receipts.post');

        Route::get('/issues', [MaterialIssueController::class, 'index'])->name('issues.index');
        Route::get('/issues/create', [MaterialIssueController::class, 'create'])->name('issues.create');
        Route::post('/issues', [MaterialIssueController::class, 'store'])->name('issues.store');
        Route::get('/issues/{issue}', [MaterialIssueController::class, 'show'])->name('issues.show');
        Route::post('/issues/{issue}/post', [MaterialIssueController::class, 'post'])->name('issues.post');

        Route::get('/returns', [MaterialReturnController::class, 'index'])->name('returns.index');
        Route::get('/returns/create', [MaterialReturnController::class, 'create'])->name('returns.create');
        Route::post('/returns', [MaterialReturnController::class, 'store'])->name('returns.store');
        Route::get('/returns/{return}', [MaterialReturnController::class, 'show'])->name('returns.show');
        Route::post('/returns/{return}/post', [MaterialReturnController::class, 'post'])->name('returns.post');

        Route::get('/adjustments', [InventoryAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('/adjustments/create', [InventoryAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('/adjustments', [InventoryAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('/adjustments/{adjustment}', [InventoryAdjustmentController::class, 'show'])->name('adjustments.show');
        Route::post('/adjustments/{adjustment}/post', [InventoryAdjustmentController::class, 'post'])->name('adjustments.post');
    });

    Route::prefix('sales')->name('sales.')->group(function () {
        // Quotations
        Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
        Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::post('/quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
        Route::post('/quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
        Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');

        // Customer Orders
        Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/create', [CustomerOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [CustomerOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/edit', [CustomerOrderController::class, 'edit'])->name('orders.edit');
        Route::put('/orders/{order}', [CustomerOrderController::class, 'update'])->name('orders.update');
        Route::post('/orders/{order}/submit-review', [CustomerOrderController::class, 'submitReview'])->name('orders.submit-review');
        Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->name('orders.cancel');

        // Production Review & Approval
        Route::get('/orders/{order}/review', [ProductionReviewController::class, 'showReviewPage'])->name('orders.review');
        Route::post('/orders/{order}/approve-production', [ProductionReviewController::class, 'approveForProduction'])->name('orders.approve-production');

        Route::get('/customers', function () {
            return redirect()->route('customers.index');
        })->name('customers');
    });

    // Stage 6: Product Models, Configurations, Standard Sizes, & Customer Aliases
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/sizes', [StandardBedSizeController::class, 'index'])->name('sizes.index');
        Route::post('/sizes', [StandardBedSizeController::class, 'store'])->name('sizes.store');
        Route::put('/sizes/{size}', [StandardBedSizeController::class, 'update'])->name('sizes.update');
        Route::post('/sizes/{size}/toggle-status', [StandardBedSizeController::class, 'toggleStatus'])->name('sizes.toggle-status');

        Route::get('/models', [ProductModelController::class, 'index'])->name('models.index');
        Route::get('/models/create', [ProductModelController::class, 'create'])->name('models.create');
        Route::post('/models', [ProductModelController::class, 'store'])->name('models.store');
        Route::get('/models/{model}', [ProductModelController::class, 'show'])->name('models.show');
        Route::get('/models/{model}/edit', [ProductModelController::class, 'edit'])->name('models.edit');
        Route::put('/models/{model}', [ProductModelController::class, 'update'])->name('models.update');
        Route::post('/models/{model}/toggle-status', [ProductModelController::class, 'toggleStatus'])->name('models.toggle-status');

        Route::post('/configurations', [ProductConfigurationController::class, 'store'])->name('configurations.store');
        Route::put('/configurations/{configuration}', [ProductConfigurationController::class, 'update'])->name('configurations.update');
        Route::post('/configurations/{configuration}/toggle-status', [ProductConfigurationController::class, 'toggleStatus'])->name('configurations.toggle-status');
        Route::delete('/configurations/{configuration}', [ProductConfigurationController::class, 'destroy'])->name('configurations.destroy');

        Route::post('/aliases', [CustomerProductAliasController::class, 'store'])->name('aliases.store');
        Route::put('/aliases/{alias}', [CustomerProductAliasController::class, 'update'])->name('aliases.update');
        Route::post('/aliases/{alias}/set-default', [CustomerProductAliasController::class, 'setDefault'])->name('aliases.set-default');
        Route::post('/aliases/{alias}/toggle-status', [CustomerProductAliasController::class, 'toggleStatus'])->name('aliases.toggle-status');
    });

    // Stage 7: Manufacturing Recipes, Templates & Semi-Finished Components
    Route::prefix('recipes')->name('recipes.')->group(function () {
        Route::get('/', [ManufacturingRecipeController::class, 'index'])->name('index');
        Route::get('/create', [ManufacturingRecipeController::class, 'create'])->name('create');
        Route::post('/', [ManufacturingRecipeController::class, 'store'])->name('store');

        // Templates
        Route::get('/templates', [ManufacturingTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [ManufacturingTemplateController::class, 'create'])->name('templates.create');
        Route::post('/templates', [ManufacturingTemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}', [ManufacturingTemplateController::class, 'show'])->name('templates.show');
        Route::get('/templates/{template}/edit', [ManufacturingTemplateController::class, 'edit'])->name('templates.edit');
        Route::put('/templates/{template}', [ManufacturingTemplateController::class, 'update'])->name('templates.update');
        Route::post('/templates/{template}/toggle-status', [ManufacturingTemplateController::class, 'toggleStatus'])->name('templates.toggle-status');
        Route::post('/templates/{template}/create-recipe', [ManufacturingRecipeController::class, 'createFromTemplate'])->name('templates.create-recipe');

        // Semi-Finished Components
        Route::get('/components', [SemiFinishedComponentController::class, 'index'])->name('components.index');
        Route::post('/components', [SemiFinishedComponentController::class, 'store'])->name('components.store');
        Route::put('/components/{component}', [SemiFinishedComponentController::class, 'update'])->name('components.update');
        Route::post('/components/{component}/toggle-status', [SemiFinishedComponentController::class, 'toggleStatus'])->name('components.toggle-status');

        // Dynamic recipe routes must remain after the static module prefixes.
        Route::get('/{recipe}', [ManufacturingRecipeController::class, 'show'])->name('show');
        Route::get('/{recipe}/versions/{version}/edit', [ManufacturingRecipeController::class, 'edit'])->name('versions.edit');
        Route::put('/{recipe}/versions/{version}', [ManufacturingRecipeController::class, 'updateVersion'])->name('versions.update');
        Route::post('/{recipe}/versions/{version}/approve', [ManufacturingRecipeController::class, 'approve'])->name('versions.approve');
        Route::post('/{recipe}/versions/{version}/copy', [ManufacturingRecipeController::class, 'copyVersion'])->name('versions.copy');
    });

    // Stage 11: Finished Goods, Delivery & Installation, and Customer Returns
    Route::prefix('finished-goods')->name('finished-goods.')->group(function () {
        Route::get('/', [FinishedGoodsController::class, 'index'])->name('index');
        Route::get('/receipts/create', [FinishedGoodsController::class, 'createReceipt'])->name('receipts.create');
        Route::post('/receipts', [FinishedGoodsController::class, 'storeReceipt'])->name('receipts.store');
        Route::get('/receipts/{receipt}', [FinishedGoodsController::class, 'showReceipt'])->name('receipts.show');
        Route::post('/receipts/{receipt}/post', [FinishedGoodsController::class, 'postReceipt'])->name('receipts.post');
    });

    Route::prefix('delivery')->name('delivery.')->group(function () {
        Route::get('/orders', [DeliveryOrderController::class, 'index'])->name('orders.index');
        Route::get('/my-tasks', [DeliveryOrderController::class, 'myTasks'])->name('orders.my-tasks');
        Route::get('/orders/create', [DeliveryOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [DeliveryOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{delivery}', [DeliveryOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{delivery}/assign', [DeliveryOrderController::class, 'assign'])->name('orders.assign');
        Route::post('/orders/{delivery}/dispatch', [DeliveryOrderController::class, 'dispatch'])->name('orders.dispatch');
        Route::post('/orders/{delivery}/complete', [DeliveryOrderController::class, 'complete'])->name('orders.complete');
        Route::post('/orders/{delivery}/install', [DeliveryOrderController::class, 'install'])->name('orders.install');
        Route::post('/orders/{delivery}/fail-or-reschedule', [DeliveryOrderController::class, 'failOrReschedule'])->name('orders.fail-or-reschedule');
    });

    Route::prefix('customer-returns')->name('customer-returns.')->group(function () {
        Route::get('/', [CustomerReturnController::class, 'index'])->name('index');
        Route::get('/create', [CustomerReturnController::class, 'create'])->name('create');
        Route::post('/', [CustomerReturnController::class, 'store'])->name('store');
        Route::get('/{customerReturn}', [CustomerReturnController::class, 'show'])->name('show');
        Route::post('/{customerReturn}/receive', [CustomerReturnController::class, 'receive'])->name('receive');
        Route::post('/{customerReturn}/link-quality', [CustomerReturnController::class, 'linkQuality'])->name('link-quality');
    });

    // Stage 12: Purchasing & Procurement Workflow
    Route::prefix('purchasing')->name('purchasing.')->group(function () {
        // Procurement Planning
        Route::get('/planning', [ProcurementPlanningController::class, 'index'])->name('planning.index');
        Route::post('/planning/bulk-request', [ProcurementPlanningController::class, 'createBulkRequest'])->name('planning.bulk-request');

        // Purchase Requests
        Route::get('/requests', [PurchaseRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/create', [PurchaseRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [PurchaseRequestController::class, 'store'])->name('requests.store');
        Route::get('/requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{purchaseRequest}/review', [PurchaseRequestController::class, 'review'])->name('requests.review');

        // RFQs
        Route::get('/rfqs', [PurchaseRfqController::class, 'index'])->name('rfqs.index');
        Route::get('/rfqs/create', [PurchaseRfqController::class, 'create'])->name('rfqs.create');
        Route::post('/rfqs', [PurchaseRfqController::class, 'store'])->name('rfqs.store');
        Route::get('/rfqs/{purchaseRfq}', [PurchaseRfqController::class, 'show'])->name('rfqs.show');

        // Supplier Quotations & Matrix Comparison
        Route::get('/quotations', [SupplierQuotationController::class, 'index'])->name('quotations.index');
        Route::get('/quotations/create', [SupplierQuotationController::class, 'create'])->name('quotations.create');
        Route::post('/quotations', [SupplierQuotationController::class, 'store'])->name('quotations.store');
        Route::get('/quotations/compare', [SupplierQuotationController::class, 'compare'])->name('quotations.compare');
        Route::get('/quotations/{supplierQuotation}', [SupplierQuotationController::class, 'show'])->name('quotations.show');
        Route::post('/quotations/{supplierQuotation}/select', [SupplierQuotationController::class, 'select'])->name('quotations.select');

        // Purchase Orders
        Route::get('/orders', [PurchaseOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/create', [PurchaseOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('orders.approve');
        Route::post('/orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->name('orders.close');
        Route::post('/orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{purchaseOrder}/receive-form', [PurchaseOrderController::class, 'receiveForm'])->name('orders.receive-form');
        Route::get('/orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('orders.print');
    });

    Route::prefix('catalog')->name('catalog.')->group(function () {
        Route::get('/models', function () {
            return redirect()->route('products.models.index');
        })->name('models');
        Route::get('/aliases', function () {
            return redirect()->route('products.models.index');
        })->name('aliases');
    });

    Route::get('/reports', function () {
        return view('placeholders.module', ['title' => 'التقارير الإدارية والتكلفة (Reports & Costing)']);
    })->name('reports.index');

    Route::get('/settings', function () {
        return view('placeholders.module', ['title' => 'إعدادات النظام والقياسات (System Settings)']);
    })->name('settings.index');

    // Stage 13: Customer Payments, Receivables, Credit & Overrides
    Route::prefix('receivables')->name('receivables.')->group(function () {
        Route::get('/dashboard', [ReceivablesReportController::class, 'dashboard'])->name('dashboard');

        // Customer Payments
        Route::get('/payments', [CustomerPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/create', [CustomerPaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [CustomerPaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}', [CustomerPaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/confirm', [CustomerPaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('/payments/{payment}/cancel', [CustomerPaymentController::class, 'cancel'])->name('payments.cancel');
        Route::post('/payments/{payment}/reverse', [CustomerPaymentController::class, 'reverse'])->name('payments.reverse');
        Route::get('/payments/{payment}/receipt', [CustomerPaymentController::class, 'receipt'])->name('payments.receipt');

        // Payment Allocations
        Route::post('/allocations', [PaymentAllocationController::class, 'store'])->name('allocations.store');
        Route::delete('/allocations/{allocation}', [PaymentAllocationController::class, 'destroy'])->name('allocations.destroy');

        // Customer Credit Profiles & Balances
        Route::get('/customers', [ReceivablesReportController::class, 'balances'])->name('customers.index');
        Route::get('/customers/{customer}', [ReceivablesReportController::class, 'statement'])->name('customers.show');
        Route::put('/customers/{customer}/credit', [CustomerCreditProfileController::class, 'update'])->name('customers.credit.update');

        // Credit Monitoring & Aging Reports
        Route::get('/credit', [CustomerCreditProfileController::class, 'index'])->name('credit.index');
        Route::get('/reports/aging', [ReceivablesReportController::class, 'aging'])->name('reports.aging');

        // Payment Control Overrides
        Route::post('/overrides', [PaymentControlOverrideController::class, 'store'])->name('overrides.store');
    });

    // Search API Endpoints (Typeahead Selectors)
    Route::prefix('api/search')->name('api.search.')->group(function () {
        Route::get('/product-models', [SearchApiController::class, 'productModels'])->name('product-models');
        Route::get('/product-configurations', [SearchApiController::class, 'productConfigurations'])->name('product-configurations');
        Route::get('/suppliers', [SearchApiController::class, 'suppliers'])->name('suppliers');
        Route::get('/fabric-materials', [SearchApiController::class, 'fabricMaterials'])->name('fabric-materials');
    });
});
