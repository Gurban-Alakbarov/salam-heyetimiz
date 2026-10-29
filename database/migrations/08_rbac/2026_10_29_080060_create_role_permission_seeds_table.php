<?php

use App\Domain\Admin\Authorization\RolePermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// RBAC — which role→permission DEFAULTS the RolePermissionSeeder has already applied. With it the seeder (re-run on
// every deploy) adds only defaults it has never applied and never undoes a change made in the panel (role
// templates). Backfilled with the matrix defaults the roles hold right now (earlier deploys applied them): a
// default that is new in this release is not in the DB yet, so the seeder still delivers it; a grant an admin
// added that is not in the matrix is not logged, so the seeder never drops it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission_seeds', function (Blueprint $table) {
            $table->id();
            $table->string('role', 40);
            $table->unsignedBigInteger('permission_id');
            $table->timestamp('seeded_at')->nullable();

            $table->unique(['role', 'permission_id'], 'uq_role_permission_seed');

            $table->foreign('permission_id', 'fk_role_permission_seeds_permission_id')
                ->references('id')->on('permissions')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });

        // A fresh install has no grants yet: leave the log empty so the seeder applies the whole matrix.
        if (! DB::table('role_permissions')->exists()) {
            return;
        }

        $idByKey = DB::table('permissions')->pluck('id', 'key')->all();
        $now = now();
        foreach (RolePermissionMatrix::map() as $role => $keys) {
            $held = DB::table('role_permissions')->where('role', $role)->pluck('permission_id')
                ->map(fn ($id): int => (int) $id)->all();
            foreach ($keys as $key) {
                if (isset($idByKey[$key]) && in_array((int) $idByKey[$key], $held, true)) {
                    DB::table('role_permission_seeds')->insertOrIgnore([
                        'role' => $role,
                        'permission_id' => (int) $idByKey[$key],
                        'seeded_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_seeds');
    }
};
