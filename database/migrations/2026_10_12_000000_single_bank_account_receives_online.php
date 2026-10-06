<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('bank_accounts')->orderBy('id')->value('id');

        if ($id !== null) {
            DB::table('bank_accounts')->where('id', '!=', $id)->update(['receives_online' => false, 'online_from' => null]);
            DB::table('bank_accounts')->where('id', $id)->update([
                'receives_online' => true,
                'online_from' => DB::raw('COALESCE(online_from, DATE(created_at))'),
            ]);
        }
    }

    public function down(): void {}
};
