<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add snapshots to purchasing tables
        if (Schema::hasTable('purchase_request_lines')) {
            Schema::table('purchase_request_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_request_lines', 'fabric_color_code')) {
                    $table->string('fabric_color_code', 50)->nullable()->after('fabric_color_id');
                }
                if (! Schema::hasColumn('purchase_request_lines', 'fabric_supplier_color_code')) {
                    $table->string('fabric_supplier_color_code', 100)->nullable()->after('fabric_color_code');
                }
            });
        }

        if (Schema::hasTable('supplier_quotation_lines')) {
            Schema::table('supplier_quotation_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier_quotation_lines', 'fabric_color_code')) {
                    $table->string('fabric_color_code', 50)->nullable()->after('fabric_color_id');
                }
                if (! Schema::hasColumn('supplier_quotation_lines', 'fabric_supplier_color_code')) {
                    $table->string('fabric_supplier_color_code', 100)->nullable()->after('fabric_color_code');
                }
            });
        }

        if (Schema::hasTable('purchase_order_lines')) {
            Schema::table('purchase_order_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_order_lines', 'fabric_color_code')) {
                    $table->string('fabric_color_code', 50)->nullable()->after('fabric_color_id');
                }
                if (! Schema::hasColumn('purchase_order_lines', 'fabric_supplier_color_code')) {
                    $table->string('fabric_supplier_color_code', 100)->nullable()->after('fabric_color_code');
                }
            });
        }

        // 2. Add snapshots to material issue lines, return lines, and adjustment lines if missing
        if (Schema::hasTable('material_issue_lines')) {
            Schema::table('material_issue_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('material_issue_lines', 'fabric_color_code')) {
                    $table->string('fabric_color_code', 50)->nullable()->after('fabric_color_id');
                }
                if (! Schema::hasColumn('material_issue_lines', 'fabric_supplier_color_code')) {
                    $table->string('fabric_supplier_color_code', 100)->nullable()->after('fabric_color_code');
                }
            });
        }

        if (Schema::hasTable('material_return_lines')) {
            Schema::table('material_return_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('material_return_lines', 'fabric_color_code')) {
                    $table->string('fabric_color_code', 50)->nullable()->after('fabric_color_id');
                }
                if (! Schema::hasColumn('material_return_lines', 'fabric_supplier_color_code')) {
                    $table->string('fabric_supplier_color_code', 100)->nullable()->after('fabric_color_code');
                }
            });
        }

        if (Schema::hasTable('inventory_adjustment_lines')) {
            Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_adjustment_lines', 'fabric_color_code')) {
                    $table->string('fabric_color_code', 50)->nullable()->after('fabric_color_id');
                }
                if (! Schema::hasColumn('inventory_adjustment_lines', 'fabric_supplier_color_code')) {
                    $table->string('fabric_supplier_color_code', 100)->nullable()->after('fabric_color_code');
                }
            });
        }

        // 3. Backfill snapshots from fabric_colors where fabric_color_id is present
        $this->backfillPurchasingAndInventorySnapshots();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'purchase_request_lines',
            'supplier_quotation_lines',
            'purchase_order_lines',
            'material_issue_lines',
            'material_return_lines',
            'inventory_adjustment_lines',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $cols = [];
                    if (Schema::hasColumn($table, 'fabric_supplier_color_code')) {
                        $cols[] = 'fabric_supplier_color_code';
                    }
                    if (Schema::hasColumn($table, 'fabric_color_code')) {
                        $cols[] = 'fabric_color_code';
                    }
                    if (! empty($cols)) {
                        $t->dropColumn($cols);
                    }
                });
            }
        }
    }

    private function backfillPurchasingAndInventorySnapshots(): void
    {
        $tables = [
            'purchase_request_lines',
            'supplier_quotation_lines',
            'purchase_order_lines',
            'material_issue_lines',
            'material_return_lines',
            'inventory_adjustment_lines',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'fabric_color_id')) {
                continue;
            }

            $rows = DB::table($table)
                ->whereNotNull('fabric_color_id')
                ->where(function ($q) {
                    $q->whereNull('fabric_color_code')
                        ->orWhereNull('fabric_supplier_color_code');
                })
                ->get();

            foreach ($rows as $row) {
                $color = DB::table('fabric_colors')->where('id', $row->fabric_color_id)->first();
                if ($color) {
                    DB::table($table)->where('id', $row->id)->update([
                        'fabric_color_code' => $row->fabric_color_code ?: $color->color_code,
                        'fabric_supplier_color_code' => $row->fabric_supplier_color_code ?: $color->supplier_color_code,
                    ]);
                }
            }
        }
    }
};
