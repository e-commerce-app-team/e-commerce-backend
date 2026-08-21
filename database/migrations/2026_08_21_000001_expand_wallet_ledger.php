<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'locked_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('locked_balance', 15, 2)->default(0)->after('balance');
            });
        }

        if (! Schema::hasColumn('users', 'wallet_qr_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('wallet_qr_token')->nullable()->unique()->after('wallet_pin');
            });
        }

        if (Schema::hasTable('transactions')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE transactions MODIFY user_id BIGINT UNSIGNED NULL');
                DB::statement('ALTER TABLE transactions MODIFY type VARCHAR(40) NOT NULL');
            }

            Schema::table('transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('transactions', 'direction')) {
                    $table->string('direction', 12)->default('credit')->after('type');
                }
                if (! Schema::hasColumn('transactions', 'status')) {
                    $table->string('status', 20)->default('completed')->after('direction');
                }
                if (! Schema::hasColumn('transactions', 'reference')) {
                    $table->string('reference', 120)->nullable()->unique()->after('description');
                }
                if (! Schema::hasColumn('transactions', 'counterparty_user_id')) {
                    $table->foreignId('counterparty_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('transactions', 'account_type')) {
                    $table->string('account_type', 20)->default('user')->after('status');
                }
            });
        }

        if (Schema::hasTable('wallet_deposit_requests')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE wallet_deposit_requests MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
            }
            Schema::table('wallet_deposit_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('wallet_deposit_requests', 'reviewed_by_admin_id')) {
                    $table->foreignId('reviewed_by_admin_id')->nullable()->after('reviewed_at')->constrained('admins')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('payout_requests')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE payout_requests MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
            }
            Schema::table('payout_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('payout_requests', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('admin_notes');
                }
                if (! Schema::hasColumn('payout_requests', 'reviewed_by_admin_id')) {
                    $table->foreignId('reviewed_by_admin_id')->nullable()->after('reviewed_at')->constrained('admins')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('sub_orders')) {
            Schema::table('sub_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('sub_orders', 'escrow_amount')) {
                    $table->decimal('escrow_amount', 15, 2)->nullable()->after('total');
                }
                if (! Schema::hasColumn('sub_orders', 'commission_rate_snapshot')) {
                    $table->decimal('commission_rate_snapshot', 5, 2)->default(0)->after('escrow_amount');
                }
                if (! Schema::hasColumn('sub_orders', 'platform_commission')) {
                    $table->decimal('platform_commission', 15, 2)->default(0)->after('commission_rate_snapshot');
                }
                if (! Schema::hasColumn('sub_orders', 'seller_net_amount')) {
                    $table->decimal('seller_net_amount', 15, 2)->default(0)->after('platform_commission');
                }
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'payment_qr_token')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->uuid('payment_qr_token')->nullable()->unique()->after('payment_status');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'wallet_qr_token')) {
            DB::table('users')->whereNull('wallet_qr_token')->orderBy('id')->eachById(function ($user) {
                DB::table('users')->where('id', $user->id)->update(['wallet_qr_token' => (string) Str::uuid()]);
            });
        }
    }

    public function down(): void
    {
        // Deliberately conservative: wallet ledger data must not be destroyed by rollback.
    }
};
