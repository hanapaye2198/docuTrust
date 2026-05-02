<?php

use App\Enums\OrganizationRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->string('organization_role')->default(OrganizationRole::Member->value)->after('role');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });

        $users = DB::table('users')->select(['id', 'name'])->get();
        foreach ($users as $user) {
            $organizationId = DB::table('organizations')->insertGetId([
                'name' => ((string) $user->name).' Organization',
                'slug' => Str::slug((string) $user->name).'-'.(string) $user->id,
                'plan' => 'free',
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $user->id)->update([
                'organization_id' => $organizationId,
                'organization_role' => OrganizationRole::Admin->value,
            ]);
        }

        DB::table('documents')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunk(100, function ($documents): void {
                foreach ($documents as $document) {
                    $organizationId = DB::table('users')->where('id', $document->user_id)->value('organization_id');
                    DB::table('documents')->where('id', $document->id)->update(['organization_id' => $organizationId]);
                }
            });

        DB::table('templates')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunk(100, function ($templates): void {
                foreach ($templates as $template) {
                    $organizationId = DB::table('users')->where('id', $template->user_id)->value('organization_id');
                    DB::table('templates')->where('id', $template->id)->update(['organization_id' => $organizationId]);
                }
            });

        DB::table('tags')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunk(100, function ($tags): void {
                foreach ($tags as $tag) {
                    $organizationId = DB::table('users')->where('id', $tag->user_id)->value('organization_id');
                    DB::table('tags')->where('id', $tag->id)->update(['organization_id' => $organizationId]);
                }
            });

        DB::table('contacts')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunk(100, function ($contacts): void {
                foreach ($contacts as $contact) {
                    $organizationId = DB::table('users')->where('id', $contact->user_id)->value('organization_id');
                    DB::table('contacts')->where('id', $contact->id)->update(['organization_id' => $organizationId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('organization_role');
        });
    }
};
