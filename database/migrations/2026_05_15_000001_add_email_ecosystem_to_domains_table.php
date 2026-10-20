<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            if (!Schema::hasColumn('domains', 'mail_routing')) {
                $table->enum('mail_routing', ['local', 'remote', 'backup', 'auto'])->default('local')->after('status');
            }
            if (!Schema::hasColumn('domains', 'catchall_action')) {
                $table->enum('catchall_action', ['fail', 'discard', 'forward'])->default('fail')->after('mail_routing');
            }
            if (!Schema::hasColumn('domains', 'catchall_destination')) {
                $table->string('catchall_destination')->nullable()->after('catchall_action');
            }
            if (!Schema::hasColumn('domains', 'spam_score_threshold')) {
                $table->decimal('spam_score_threshold', 4, 1)->default(5.0)->after('catchall_destination');
            }
            if (!Schema::hasColumn('domains', 'spam_box_enabled')) {
                $table->boolean('spam_box_enabled')->default(true)->after('spam_score_threshold');
            }
            if (!Schema::hasColumn('domains', 'spam_autodelete_score')) {
                $table->decimal('spam_autodelete_score', 4, 1)->nullable()->after('spam_box_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'mail_routing',
                'catchall_action',
                'catchall_destination',
                'spam_score_threshold',
                'spam_box_enabled',
                'spam_autodelete_score',
            ]);
        });
    }
};
